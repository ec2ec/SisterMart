<?php

declare(strict_types=1);

namespace app\service;

use app\support\ApiException;

/**
 * 圖片上傳場景：壓縮參數（可後台改）+ 固定分桶路徑。
 * 舊 /storage/{avatar|review|...} 仍允許落庫。仍在使用的舊圖由上傳素材概覽原樣搬入場景目錄。
 */
class ImageUploadSceneService
{
    public const SETTING_KEY = 'image.upload_scenes';

    /**
     * @var array<string, array{
     *   label: string,
     *   group: string,
     *   bucket: string,
     *   legacy: list<string>,
     *   format: string,
     *   max_edge: int,
     *   max_upload_bytes: int,
     *   max_bytes: int,
     *   quality: int,
     *   min_quality: int
     * }>
     */
    public const SCENES = [
        'member.avatar' => [
            'label' => '會員頭像',
            'group' => '會員',
            'bucket' => 'member/avatar',
            'legacy' => ['/storage/avatar/'],
            'format' => 'jpg',
            'max_edge' => 1000,
            'max_upload_bytes' => 5242880,
            'max_bytes' => 512000,
            'quality' => 85,
            'min_quality' => 40,
        ],
        'member.review' => [
            'label' => '評價圖',
            'group' => '會員',
            'bucket' => 'member/review',
            'legacy' => ['/storage/review/'],
            'format' => 'jpg',
            'max_edge' => 1000,
            'max_upload_bytes' => 5242880,
            'max_bytes' => 512000,
            'quality' => 85,
            'min_quality' => 40,
        ],
        'member.aftersale' => [
            'label' => '售後證據',
            'group' => '會員',
            'bucket' => 'member/aftersale',
            'legacy' => ['/storage/aftersale/'],
            'format' => 'jpg',
            'max_edge' => 1000,
            'max_upload_bytes' => 5242880,
            'max_bytes' => 512000,
            'quality' => 85,
            'min_quality' => 40,
        ],
        'catalog.product' => [
            'label' => '商品主圖',
            'group' => '商品',
            'bucket' => 'catalog/product',
            'legacy' => ['/storage/product/'],
            'format' => 'jpg',
            'max_edge' => 1600,
            'max_upload_bytes' => 5242880,
            'max_bytes' => 716800,
            'quality' => 85,
            'min_quality' => 45,
        ],
        'catalog.product_detail' => [
            'label' => '商品詳情圖',
            'group' => '商品',
            'bucket' => 'content/richtext/product',
            'legacy' => ['/storage/product/', '/storage/admin/', '/storage/content/'],
            'format' => 'jpg',
            'max_edge' => 1600,
            'max_upload_bytes' => 5242880,
            'max_bytes' => 716800,
            'quality' => 85,
            'min_quality' => 45,
        ],
        'catalog.category' => [
            'label' => '分類圖標',
            'group' => '商品',
            'bucket' => 'catalog/category',
            'legacy' => ['/storage/product/', '/storage/admin/'],
            'format' => 'png',
            'max_edge' => 512,
            'max_upload_bytes' => 5242880,
            'max_bytes' => 204800,
            'quality' => 90,
            'min_quality' => 50,
        ],
        'marketing.banner' => [
            'label' => '輪播廣告',
            'group' => '運營',
            'bucket' => 'marketing/banner',
            'legacy' => ['/storage/banner/'],
            'format' => 'jpg',
            'max_edge' => 1920,
            'max_upload_bytes' => 5242880,
            'max_bytes' => 819200,
            'quality' => 85,
            'min_quality' => 45,
        ],
        'marketing.image_ad' => [
            'label' => '圖片廣告',
            'group' => '運營',
            'bucket' => 'marketing/image_ad',
            'legacy' => ['/storage/banner/', '/storage/product/'],
            'format' => 'jpg',
            'max_edge' => 1920,
            'max_upload_bytes' => 5242880,
            'max_bytes' => 819200,
            'quality' => 85,
            'min_quality' => 45,
        ],
        'marketing.campaign' => [
            'label' => '活動橫幅',
            'group' => '運營',
            'bucket' => 'marketing/campaign',
            'legacy' => ['/storage/banner/', '/storage/product/', '/storage/admin/'],
            'format' => 'jpg',
            'max_edge' => 1920,
            'max_upload_bytes' => 5242880,
            'max_bytes' => 819200,
            'quality' => 85,
            'min_quality' => 45,
        ],
        'marketing.popup' => [
            'label' => '首頁遮罩',
            'group' => '運營',
            'bucket' => 'marketing/popup',
            'legacy' => ['/storage/product/', '/storage/banner/', '/storage/admin/'],
            'format' => 'jpg',
            'max_edge' => 1600,
            'max_upload_bytes' => 5242880,
            'max_bytes' => 716800,
            'quality' => 85,
            'min_quality' => 45,
        ],
        'marketing.coupon' => [
            'label' => '優惠券背景',
            'group' => '運營',
            'bucket' => 'marketing/coupon',
            'legacy' => ['/storage/product/', '/storage/admin/', '/storage/banner/'],
            'format' => 'jpg',
            'max_edge' => 1600,
            'max_upload_bytes' => 5242880,
            'max_bytes' => 716800,
            'quality' => 85,
            'min_quality' => 45,
        ],
        'content.article' => [
            'label' => '文章插圖',
            'group' => '內容',
            'bucket' => 'content/article',
            'legacy' => ['/storage/product/', '/storage/admin/', '/storage/content/'],
            'format' => 'jpg',
            'max_edge' => 1600,
            'max_upload_bytes' => 5242880,
            'max_bytes' => 716800,
            'quality' => 85,
            'min_quality' => 45,
        ],
        'content.welcome' => [
            'label' => '註冊歡迎文案圖',
            'group' => '內容',
            'bucket' => 'content/richtext/welcome',
            'legacy' => ['/storage/admin/', '/storage/product/', '/storage/content/'],
            'format' => 'jpg',
            'max_edge' => 1600,
            'max_upload_bytes' => 5242880,
            'max_bytes' => 716800,
            'quality' => 85,
            'min_quality' => 45,
        ],
        'content.campaign' => [
            'label' => '活動介紹圖',
            'group' => '內容',
            'bucket' => 'content/richtext/campaign',
            'legacy' => ['/storage/admin/', '/storage/product/', '/storage/content/'],
            'format' => 'jpg',
            'max_edge' => 1600,
            'max_upload_bytes' => 5242880,
            'max_bytes' => 716800,
            'quality' => 85,
            'min_quality' => 45,
        ],
        'content.coupon' => [
            'label' => '優惠券細則圖',
            'group' => '內容',
            'bucket' => 'content/richtext/coupon',
            'legacy' => ['/storage/admin/', '/storage/product/', '/storage/content/'],
            'format' => 'jpg',
            'max_edge' => 1600,
            'max_upload_bytes' => 5242880,
            'max_bytes' => 716800,
            'quality' => 85,
            'min_quality' => 45,
        ],
        'content.richtext' => [
            'label' => '通用富文本圖',
            'group' => '內容',
            'bucket' => 'content/richtext/general',
            'legacy' => ['/storage/admin/', '/storage/product/', '/storage/content/'],
            'format' => 'jpg',
            'max_edge' => 1600,
            'max_upload_bytes' => 5242880,
            'max_bytes' => 716800,
            'quality' => 85,
            'min_quality' => 45,
        ],
    ];

    /** @return array<string, mixed> */
    public function list(): array
    {
        $saved = $this->savedMap();
        $items = [];
        foreach (self::SCENES as $key => $def) {
            $items[] = $this->present($key, $this->merge($def, $saved[$key] ?? []));
        }

        return ['items' => $items];
    }

    /** @param array<string, mixed> $body */
    public function save(array $body): array
    {
        $incoming = $body['items'] ?? $body;
        if (!is_array($incoming)) {
            throw new ApiException('INVALID', '配置格式不正確', 422, 42201);
        }
        $map = [];
        foreach ($incoming as $row) {
            if (!is_array($row)) {
                continue;
            }
            $key = (string) ($row['key'] ?? $row['scene'] ?? '');
            if (!isset(self::SCENES[$key])) {
                throw new ApiException('INVALID', '未知圖片場景：' . $key, 422, 42201);
            }
            $map[$key] = $this->sanitizeOverride($row);
        }
        TradeSettings::set(self::SETTING_KEY, json_encode($map, JSON_UNESCAPED_UNICODE));

        return $this->list();
    }

    /** @return array<string, mixed> */
    public function resolved(string $scene): array
    {
        if (!isset(self::SCENES[$scene])) {
            throw new ApiException('INVALID', '未知圖片場景：' . $scene, 422, 42201);
        }
        $saved = $this->savedMap();

        return $this->merge(self::SCENES[$scene], $saved[$scene] ?? []) + ['key' => $scene];
    }

    public static function sceneFromPreset(string $preset): string
    {
        return match ($preset) {
            'avatar' => 'member.avatar',
            'review' => 'member.review',
            'aftersale' => 'member.aftersale',
            'banner' => 'marketing.banner',
            'product' => 'catalog.product',
            default => 'content.richtext',
        };
    }

    /** 本站 /storage 路徑須落在場景桶或歷史前綴；外鏈原樣保留。 */
    public function assertUrl(string $scene, string $url): string
    {
        $url = trim($url);
        $storage = $this->extractStorageUrl($url);
        if ($storage === null) {
            return $url;
        }
        $cfg = $this->resolved($scene);
        $prefixes = array_merge(['/storage/' . $cfg['bucket'] . '/'], $cfg['legacy']);
        foreach ($prefixes as $prefix) {
            if (str_starts_with($storage, $prefix)) {
                return $storage;
            }
        }
        throw new ApiException('INVALID', '圖片路徑與場景不符', 422, 42201);
    }

    /**
     * 富文本裡的本站圖片須符合場景；外鏈與 /mock 保留。files 代理會改寫成 /storage/...。
     */
    public function assertHtml(string $scene, string $html): string
    {
        if ($html === '' || (!str_contains($html, '/storage/') && !str_contains($html, 'path='))) {
            return $html;
        }
        $raws = [];
        $patterns = [
            '#https?://[^\s"\'<>]+/storage/[A-Za-z0-9_./\-]+#i',
            '#/storage/[A-Za-z0-9_./\-]+#',
            '#https?://[^\s"\'<>]*\?[^\s"\'<>]*path=[^\s"\'<>&]+#i',
            '#/api/v1/files\?path=[^\s"\'<>&]+#i',
        ];
        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $html, $matches)) {
                foreach ($matches[0] as $raw) {
                    $raws[$raw] = strlen($raw);
                }
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
            $canonical = $this->assertUrl($scene, $raw);
            if ($canonical !== $raw) {
                $html = str_replace($raw, $canonical, $html);
            }
        }

        return $html;
    }

    /**
     * 後台素材庫：只列該場景分桶裡已上傳的圖。不含會員頭像／評價／售後。
     *
     * @return array{scene: string, items: list<array{url: string, bytes: int}>, total: int, page: int, page_size: int}
     */
    public function library(string $scene, int $page, int $pageSize): array
    {
        if (!isset(self::SCENES[$scene]) || str_starts_with($scene, 'member.')) {
            throw new ApiException('INVALID', '素材庫只收後台圖片場景', 422, 42201);
        }
        $page = max(1, $page);
        $pageSize = max(1, min(60, $pageSize));
        $bucket = trim((string) $this->resolved($scene)['bucket'], '/');
        $files = $this->bucketFiles($bucket);
        $total = count($files);
        $slice = array_slice($files, ($page - 1) * $pageSize, $pageSize);
        $items = [];
        foreach ($slice as $row) {
            $items[] = ['url' => $row['url'], 'bytes' => $row['bytes']];
        }

        return [
            'scene' => $scene,
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'page_size' => $pageSize,
        ];
    }

    public function assertMemberUrl(string $scene, int $memberId, string $url): string
    {
        $storage = $this->assertUrl($scene, $url);
        if (!preg_match('#/' . $memberId . '/#', $storage)) {
            throw new ApiException('INVALID', '圖片路徑無效', 422, 42201);
        }
        if (preg_match('#\.\.|[^\w./\-]#', $storage)) {
            throw new ApiException('INVALID', '圖片路徑無效', 422, 42201);
        }

        return $storage;
    }

    /** @param array<string, mixed> $def @param array<string, mixed> $override @return array<string, mixed> */
    private function merge(array $def, array $override): array
    {
        $format = (string) ($override['format'] ?? $def['format']);
        if (!in_array($format, ['keep', 'jpg', 'png'], true)) {
            $format = (string) $def['format'];
        }

        return [
            'label' => $def['label'],
            'group' => $def['group'],
            'bucket' => $def['bucket'],
            'legacy' => $def['legacy'],
            'format' => $format,
            'max_edge' => $this->clampInt($override['max_edge'] ?? $def['max_edge'], 200, 4096, (int) $def['max_edge']),
            'max_upload_bytes' => $this->clampInt($override['max_upload_bytes'] ?? $def['max_upload_bytes'], 102400, 20 * 1024 * 1024, (int) $def['max_upload_bytes']),
            'max_bytes' => $this->clampInt($override['max_bytes'] ?? $def['max_bytes'], 50 * 1024, 8 * 1024 * 1024, (int) $def['max_bytes']),
            'quality' => $this->clampInt($override['quality'] ?? $def['quality'], 40, 95, (int) $def['quality']),
            'min_quality' => (int) $def['min_quality'],
        ];
    }

    /** @param array<string, mixed> $row @return array<string, mixed> */
    private function sanitizeOverride(array $row): array
    {
        $format = (string) ($row['format'] ?? 'jpg');
        if (!in_array($format, ['keep', 'jpg', 'png'], true)) {
            throw new ApiException('INVALID', '輸出格式須為 keep / jpg / png', 422, 42201);
        }
        $uploadMb = $row['max_upload_mb'] ?? null;
        $maxKb = $row['max_kb'] ?? null;
        $maxUpload = $uploadMb !== null
            ? (int) round((float) $uploadMb * 1024 * 1024)
            : (int) ($row['max_upload_bytes'] ?? 0);
        $maxBytes = $maxKb !== null
            ? (int) round((float) $maxKb * 1024)
            : (int) ($row['max_bytes'] ?? 0);

        return [
            'format' => $format,
            'max_edge' => $this->clampInt($row['max_edge'] ?? 1000, 200, 4096, 1000),
            'max_upload_bytes' => $this->clampInt($maxUpload, 102400, 20 * 1024 * 1024, 5242880),
            'max_bytes' => $this->clampInt($maxBytes, 50 * 1024, 8 * 1024 * 1024, 716800),
            'quality' => $this->clampInt($row['quality'] ?? 85, 40, 95, 85),
        ];
    }

    /** @param array<string, mixed> $cfg @return array<string, mixed> */
    private function present(string $key, array $cfg): array
    {
        return [
            'key' => $key,
            'label' => $cfg['label'],
            'group' => $cfg['group'],
            'bucket' => $cfg['bucket'],
            'format' => $cfg['format'],
            'max_edge' => $cfg['max_edge'],
            'max_upload_bytes' => $cfg['max_upload_bytes'],
            'max_upload_mb' => round($cfg['max_upload_bytes'] / (1024 * 1024), 2),
            'max_bytes' => $cfg['max_bytes'],
            'max_kb' => (int) round($cfg['max_bytes'] / 1024),
            'quality' => $cfg['quality'],
        ];
    }

    /** @return array<string, array<string, mixed>> */
    private function savedMap(): array
    {
        $raw = TradeSettings::get(self::SETTING_KEY, '');
        if ($raw === '') {
            return [];
        }
        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function extractStorageUrl(string $url): ?string
    {
        if (preg_match('#[?&]path=([^&]+)#', $url, $m)) {
            $path = rawurldecode($m[1]);
            $path = ltrim($path, '/');
            if (str_starts_with($path, 'storage/')) {
                $path = substr($path, strlen('storage/'));
            }

            return '/storage/' . ltrim($path, '/');
        }
        if (preg_match('#https?://[^/]+(/storage/.+)$#i', $url, $m)) {
            return $m[1];
        }
        if (str_starts_with($url, '/storage/')) {
            return $url;
        }
        if (str_starts_with($url, 'storage/')) {
            return '/' . $url;
        }

        return null;
    }

    /**
     * @return list<array{url: string, bytes: int, mtime: int}>
     */
    private function bucketFiles(string $bucket): array
    {
        $storageRoot = rtrim(str_replace('\\', '/', app()->getRootPath()), '/') . '/public/storage';
        $bucketRoot = $storageRoot . '/' . $bucket;
        if (!is_dir($bucketRoot)) {
            return [];
        }
        $realStorage = realpath($storageRoot);
        $realBucket = realpath($bucketRoot);
        if ($realStorage === false || $realBucket === false) {
            return [];
        }
        $realStorage = str_replace('\\', '/', $realStorage);
        $realBucket = str_replace('\\', '/', $realBucket);
        if (!str_starts_with($realBucket . '/', $realStorage . '/')) {
            return [];
        }

        $rows = [];
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($bucketRoot, \FilesystemIterator::SKIP_DOTS),
        );
        foreach ($it as $file) {
            if (!$file instanceof \SplFileInfo || !$file->isFile()) {
                continue;
            }
            $ext = strtolower($file->getExtension());
            if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)) {
                continue;
            }
            $realFile = $file->getRealPath();
            if ($realFile === false) {
                continue;
            }
            $realFile = str_replace('\\', '/', $realFile);
            if (!str_starts_with($realFile, $realBucket . '/')) {
                continue;
            }
            $rel = substr($realFile, strlen($realStorage));
            $rows[] = [
                'url' => '/storage' . $rel,
                'bytes' => (int) $file->getSize(),
                'mtime' => (int) $file->getMTime(),
            ];
            if (count($rows) >= 2000) {
                break;
            }
        }
        usort($rows, static function (array $a, array $b): int {
            return $b['mtime'] <=> $a['mtime'] ?: strcmp($a['url'], $b['url']);
        });

        return $rows;
    }

    private function clampInt(mixed $value, int $min, int $max, int $fallback): int
    {
        if ($value === null || $value === '') {
            return $fallback;
        }
        $n = (int) $value;
        if ($n < $min) {
            return $min;
        }
        if ($n > $max) {
            return $max;
        }

        return $n;
    }
}
