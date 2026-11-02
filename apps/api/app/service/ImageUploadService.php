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
     * @param array{scene?: string, preset?: string, owner_id?: int|string} $options
     * @return array{url: string, width: int, height: int, bytes: int, quality: int, max_edge: int, format: string, scene: string, preset: string}
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

        return [
            'url' => '/storage/' . $relDir . '/' . $outName,
            'width' => $meta['width'],
            'height' => $meta['height'],
            'bytes' => $meta['bytes'],
            'quality' => $meta['quality'],
            'max_edge' => $meta['max_edge'],
            'format' => $meta['format'],
            'scene' => $scene,
            'preset' => (string) ($options['preset'] ?? $scene),
        ];
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
