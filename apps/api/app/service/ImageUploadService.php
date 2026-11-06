<?php

declare(strict_types=1);

namespace app\service;

use app\support\ApiException;
use app\support\ImageProcess;
use app\support\UploadImage;
use think\file\UploadedFile;

/**
 * 通用圖片上傳。壓縮與輸出格式只讀場景配置（H5／後台請求都不能覆蓋）。
 * 路徑：/storage/{bucket}/{owner}/{Ymd}/{hex}.{ext}
 */
class ImageUploadService
{
    /**
     * @param array{scene?: string, preset?: string, owner_id?: int|string, tags?: list<string>|string} $options
     * @return array{url: string, thumb_url: ?string, width: int, height: int, bytes: int, quality: int, max_edge: int, format: string, scene: string, preset: string, tags: list<string>}
     */
    public function store(UploadedFile $file, array $options = []): array
    {
        $scenes = new ImageUploadSceneService();
        $scene = trim((string) ($options['scene'] ?? ''));
        if ($scene === '') {
            $scene = ImageUploadSceneService::sceneFromPreset((string) ($options['preset'] ?? 'admin'));
        }
        $cfg = $scenes->resolved($scene);

        $maxUpload = (int) $cfg['max_upload_bytes'];
        $size = (int) $file->getSize();
        if ($size <= 0 || $size > $maxUpload) {
            $mb = max(1, (int) ceil($maxUpload / (1024 * 1024)));
            throw new ApiException('INVALID', '單張圖片不可超過 ' . $mb . 'MB', 422, 42289);
        }
        UploadImage::resolveExt($file);

        $bucket = trim((string) $cfg['bucket'], '/');
        if ($bucket === '' || !preg_match('#^[a-z0-9_\-]+(/[a-z0-9_\-]+)*$#i', $bucket)) {
            throw new ApiException('INVALID', '存儲目錄無效', 422, 42201);
        }
        $owner = (string) ($options['owner_id'] ?? 'shared');
        $owner = preg_replace('#[^a-zA-Z0-9_\-]#', '', $owner) ?: 'shared';

        $tmpRoot = sys_get_temp_dir() . '/sm-img-' . str_replace('/', '-', $bucket) . '-' . $owner;
        if (!is_dir($tmpRoot) && !mkdir($tmpRoot, 0775, true) && !is_dir($tmpRoot)) {
            throw new ApiException('SERVER', '無法建立臨時目錄', 500, 50001);
        }
        $inExt = UploadImage::resolveExt($file);
        $tmpName = bin2hex(random_bytes(8)) . '.' . $inExt;
        $file->move($tmpRoot, $tmpName);
        $tmpPath = $tmpRoot . '/' . $tmpName;

        try {
            $outExt = ImageProcess::resolveOutputExt((string) $cfg['format'], $tmpPath);
            $relDir = $bucket . '/' . $owner . '/' . date('Ymd');
            $root = app()->getRootPath() . 'public/storage/' . $relDir;
            $outName = bin2hex(random_bytes(8)) . '.' . $outExt;
            $destPath = $root . '/' . $outName;
            $meta = ImageProcess::compress($tmpPath, $destPath, [
                'max_edge' => (int) $cfg['max_edge'],
                'max_bytes' => (int) $cfg['max_bytes'],
                'quality' => (int) $cfg['quality'],
                'min_quality' => (int) $cfg['min_quality'],
                'format' => (string) $cfg['format'],
            ]);
        } finally {
            @unlink($tmpPath);
        }

        $url = '/storage/' . $relDir . '/' . $outName;
        $thumbUrl = ImageProcess::thumbUrlFor($url);
        $thumbPath = $this->absolutePath($thumbUrl);
        try {
            ImageProcess::coverThumb($destPath, $thumbPath, ImageProcess::COVER_THUMB_SIZE);
        } catch (\Throwable) {
            $thumbUrl = '';
        }
        $uploader = $this->resolveUploader($options, $scene);
        $tags = $this->normalizeTags($options['tags'] ?? null);
        try {
            (new UploadAssetService())->record([
                'url' => $url,
                'scene' => $scene,
                'bytes' => (int) $meta['bytes'],
                'width' => (int) $meta['width'],
                'height' => (int) $meta['height'],
                'uploader_type' => $uploader['uploader_type'],
                'uploader_id' => $uploader['uploader_id'],
                'thumb_path' => $thumbUrl !== '' ? $thumbUrl : null,
                'tags' => $tags,
            ]);
        } catch (\Throwable) {
            // 索引寫入失敗不影響圖片本身已落盤。
        }

        return [
            'url' => $url,
            'thumb_url' => $thumbUrl !== '' ? $thumbUrl : null,
            'width' => $meta['width'],
            'height' => $meta['height'],
            'bytes' => $meta['bytes'],
            'quality' => $meta['quality'],
            'max_edge' => $meta['max_edge'],
            'format' => $meta['format'],
            'scene' => $scene,
            'preset' => (string) ($options['preset'] ?? $scene),
            'tags' => $tags,
        ];
    }

    /** @param mixed $raw @return list<string> */
    private function normalizeTags(mixed $raw): array
    {
        if (is_string($raw)) {
            $raw = preg_split('/[,，]/u', $raw) ?: [];
        }
        if (!is_array($raw)) {
            return [];
        }
        $out = [];
        foreach ($raw as $tag) {
            $tag = trim((string) $tag);
            if ($tag === '' || mb_strlen($tag) > 32) {
                continue;
            }
            if (!isset($out[$tag])) {
                $out[$tag] = $tag;
            }
            if (count($out) >= 12) {
                break;
            }
        }

        return array_values($out);
    }

    /**
     * @param array{scene?: string, preset?: string, owner_id?: int|string, uploader_type?: string, uploader_id?: int} $options
     * @return array{uploader_type: string, uploader_id: int}
     */
    private function resolveUploader(array $options, string $scene): array
    {
        $explicit = (string) ($options['uploader_type'] ?? '');
        if ($explicit === 'member' || $explicit === 'admin') {
            return [
                'uploader_type' => $explicit,
                'uploader_id' => max(0, (int) ($options['uploader_id'] ?? 0)),
            ];
        }
        $owner = (string) ($options['owner_id'] ?? '');
        if (str_starts_with($scene, 'member.') && preg_match('/(\d+)/', $owner, $match)) {
            return ['uploader_type' => 'member', 'uploader_id' => (int) $match[1]];
        }
        if (preg_match('/^draft-(\d+)$/', $owner, $match)) {
            return ['uploader_type' => 'admin', 'uploader_id' => (int) $match[1]];
        }

        return [
            'uploader_type' => str_starts_with($scene, 'member.') ? 'member' : 'admin',
            'uploader_id' => 0,
        ];
    }

    /**
     * 把仍在 draft-{管理員} 的圖複製到實體目錄（不重新壓縮）。
     * 其他實體還引用舊路徑時，舊檔留到 releaseUnreferencedDrafts() 再決定刪不刪。
     */
    public function claimDraft(string $url, string $folder, string $entityType, int $entityId): string
    {
        $url = trim($url);
        if ($url === '' || !$this->validFolder($folder)) {
            return $url;
        }
        $parsed = $this->parseOwnedUrl($url);
        if ($parsed === null) {
            return $url;
        }
        if ($parsed['owner'] === $folder) {
            $this->bindClaimed($url, $parsed['bucket'], $entityType, $entityId);

            return $url;
        }
        if (!preg_match('/^draft-\d+$/', $parsed['owner'])) {
            return $url;
        }
        $dest = '/storage/' . $parsed['bucket'] . '/' . $folder . '/' . $parsed['ymd'] . '/' . $parsed['file'];
        if (!$this->copyStored($url, $dest)) {
            return $url;
        }
        $this->bindClaimed($dest, $parsed['bucket'], $entityType, $entityId);
        $this->pendingDrafts[] = $url;

        return $dest;
    }

    public function claimDraftHtml(string $html, string $folder, string $entityType, int $entityId): string
    {
        if ($html === '' || !str_contains($html, '/storage/')) {
            return $html;
        }
        $raws = [];
        if (preg_match_all('#/storage/[A-Za-z0-9_./\-]+#', $html, $matches)) {
            foreach ($matches[0] as $raw) {
                $raws[$raw] = strlen($raw);
            }
        }
        if ($raws === []) {
            return $html;
        }
        arsort($raws);
        foreach (array_keys($raws) as $raw) {
            if (!str_contains($html, $raw)) {
                continue;
            }
            $claimed = $this->claimDraft($raw, $folder, $entityType, $entityId);
            if ($claimed !== $raw) {
                $html = str_replace($raw, $claimed, $html);
            }
        }

        return $html;
    }

    /** 資料已寫入後呼叫：沒人再指向的 draft 檔才刪。 */
    public function releaseUnreferencedDrafts(): void
    {
        if ($this->pendingDrafts === []) {
            return;
        }
        $old = array_values(array_unique($this->pendingDrafts));
        $this->pendingDrafts = [];
        $this->purgeUnreferencedPaths($old);
    }

    /**
     * 刪除已無任何資料列引用的圖片檔（含多 SKU 共用：仍有引用則保留）。
     *
     * @param list<string>|array<int, string|null> $paths
     */
    public function purgeUnreferencedPaths(array $paths): void
    {
        $candidates = [];
        foreach ($paths as $path) {
            $url = trim((string) $path);
            if ($url === '' || !str_starts_with($url, '/storage/')) {
                continue;
            }
            $candidates[$url] = true;
        }
        if ($candidates === []) {
            return;
        }
        try {
            $referenced = (new UploadAssetService())->referencedPathSet();
        } catch (\Throwable) {
            return;
        }
        $assets = new UploadAssetService();
        foreach (array_keys($candidates) as $url) {
            if (isset($referenced[$url])) {
                continue;
            }
            $full = $this->jailedFile($url);
            if ($full !== null) {
                @unlink($full);
            }
            $thumb = ImageProcess::thumbUrlFor($url);
            if ($thumb !== $url) {
                $thumbFull = $this->jailedFile($thumb);
                if ($thumbFull !== null) {
                    @unlink($thumbFull);
                }
            }
            try {
                $assets->markDeleted($url);
            } catch (\Throwable) {
            }
        }
    }

    /** @var list<string> */
    private array $pendingDrafts = [];

    private function bindClaimed(string $url, string $bucket, string $entityType, int $entityId): void
    {
        $full = $this->jailedFile($url);
        $bytes = $full !== null ? (int) (filesize($full) ?: 0) : 0;
        $scene = 'content.richtext';
        foreach (ImageUploadSceneService::SCENES as $key => $def) {
            if (trim((string) $def['bucket'], '/') === $bucket) {
                $scene = $key;
                break;
            }
        }
        try {
            (new UploadAssetService())->bindClaim($url, $scene, $entityType, $entityId, $bytes);
        } catch (\Throwable) {
        }
    }

    /** @return array{bucket: string, owner: string, ymd: string, file: string}|null */
    private function parseOwnedUrl(string $url): ?array
    {
        if (!preg_match('#^/storage/(.+)/([^/]+)/(\d{8})/([A-Za-z0-9]+\.[A-Za-z0-9]+)$#', $url, $m)) {
            return null;
        }
        $bucket = $m[1];
        if (!preg_match('#^[a-z0-9_\-]+(/[a-z0-9_\-]+)*$#i', $bucket)) {
            return null;
        }
        if (!preg_match('/^[a-z0-9][a-z0-9_\-]{0,64}$/i', $m[2])) {
            return null;
        }

        return [
            'bucket' => $bucket,
            'owner' => $m[2],
            'ymd' => $m[3],
            'file' => $m[4],
        ];
    }

    private function validFolder(string $folder): bool
    {
        return preg_match('/^[a-z0-9][a-z0-9_\-]{0,63}$/i', $folder) === 1
            && !str_starts_with($folder, 'draft-');
    }

    private function copyStored(string $fromUrl, string $toUrl): bool
    {
        $from = $this->jailedFile($fromUrl);
        $toFull = $this->absolutePath($toUrl);
        if ($from === null) {
            return $this->jailedFile($toUrl) !== null;
        }
        $destDir = dirname($toFull);
        if (!is_dir($destDir) && !mkdir($destDir, 0775, true) && !is_dir($destDir)) {
            return false;
        }
        if (is_file($toFull)) {
            return $this->jailedFile($toUrl) !== null
                && filesize($from) === filesize($toFull)
                && md5_file($from) === md5_file($toFull);
        }
        if (!copy($from, $toFull)) {
            return false;
        }
        $fromThumb = ImageProcess::thumbUrlFor($fromUrl);
        $toThumb = ImageProcess::thumbUrlFor($toUrl);
        $fromThumbFile = $this->jailedFile($fromThumb);
        if ($fromThumbFile !== null) {
            $toThumbFull = $this->absolutePath($toThumb);
            $thumbDir = dirname($toThumbFull);
            if (!is_dir($thumbDir)) {
                @mkdir($thumbDir, 0775, true);
            }
            if (!is_file($toThumbFull)) {
                @copy($fromThumbFile, $toThumbFull);
            }
        }

        return $this->jailedFile($toUrl) !== null;
    }

    /** 為已落盤原圖補產 cover 縮略圖；`$force` 時覆蓋已有 `.thumb.` 檔。 */
    public function ensureThumb(string $url, bool $force = false): ?string
    {
        $url = trim($url);
        if ($url === '' || !str_starts_with($url, '/storage/') || preg_match('/\.thumb\./i', $url)) {
            return null;
        }
        $full = $this->jailedFile($url);
        if ($full === null) {
            return null;
        }
        $thumbUrl = ImageProcess::thumbUrlFor($url);
        $thumbFull = $this->absolutePath($thumbUrl);
        if (!$force && is_file($thumbFull)) {
            return $thumbUrl;
        }
        try {
            ImageProcess::coverThumb($full, $thumbFull, ImageProcess::COVER_THUMB_SIZE);
        } catch (\Throwable) {
            return null;
        }

        return is_file($thumbFull) ? $thumbUrl : null;
    }

    private function jailedFile(string $url): ?string
    {
        if (!str_starts_with($url, '/storage/') || str_contains($url, '..')) {
            return null;
        }
        $full = $this->absolutePath($url);
        if (!is_file($full)) {
            return null;
        }
        $root = rtrim(str_replace('\\', '/', app()->getRootPath()), '/') . '/public/storage';
        $realRoot = realpath($root);
        $realFile = realpath($full);
        if ($realRoot === false || $realFile === false) {
            return null;
        }
        $realRoot = str_replace('\\', '/', $realRoot);
        $realFile = str_replace('\\', '/', $realFile);
        if (!str_starts_with($realFile, $realRoot . '/')) {
            return null;
        }

        return $realFile;
    }

    /** 將 /storage/... URL 轉為 public 絕對路徑（供後續裁切等）。 */
    public function absolutePath(string $url): string
    {
        $path = str_replace('\\', '/', $url);
        if (str_starts_with($path, '/storage/')) {
            $path = substr($path, strlen('/storage/'));
        }
        $path = ltrim($path, '/');

        return app()->getRootPath() . 'public/storage/' . $path;
    }
}
