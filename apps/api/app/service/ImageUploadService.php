<?php

declare(strict_types=1);

namespace app\service;

use app\support\ApiException;
use app\support\ImageProcess;
use think\file\UploadedFile;

/**
 * 通用圖片上傳 + 壓縮。
 *
 * 預設 preset（可被顯式參數覆蓋）：
 * - review    上傳≤5MB，最長邊 1200，目標 ≤500KB，質量 85
 * - aftersale 上傳≤5MB，最長邊 1200，目標 ≤500KB，質量 85
 * - banner    上傳≤5MB，最長邊 1920，目標 ≤800KB，質量 85
 * - product   上傳≤5MB，最長邊 1600，目標 ≤700KB，質量 85
 * - admin     上傳≤5MB，最長邊 1600，目標 ≤700KB，質量 85（後台通用）
 */
class ImageUploadService
{
    /** @var array<string, array{max_upload_bytes: int, max_edge: int, max_bytes: int, quality: int, min_quality: int}> */
    public const PRESETS = [
        'review' => [
            'max_upload_bytes' => 5 * 1024 * 1024,
            'max_edge' => 1200,
            'max_bytes' => 512000,
            'quality' => 85,
            'min_quality' => 40,
        ],
        'aftersale' => [
            'max_upload_bytes' => 5 * 1024 * 1024,
            'max_edge' => 1200,
            'max_bytes' => 512000,
            'quality' => 85,
            'min_quality' => 40,
        ],
        'banner' => [
            'max_upload_bytes' => 5 * 1024 * 1024,
            'max_edge' => 1920,
            'max_bytes' => 819200,
            'quality' => 85,
            'min_quality' => 45,
        ],
        'product' => [
            'max_upload_bytes' => 5 * 1024 * 1024,
            'max_edge' => 1600,
            'max_bytes' => 716800,
            'quality' => 85,
            'min_quality' => 45,
        ],
        'admin' => [
            'max_upload_bytes' => 5 * 1024 * 1024,
            'max_edge' => 1600,
            'max_bytes' => 716800,
            'quality' => 85,
            'min_quality' => 45,
        ],
    ];

    /**
     * @param array{
     *   preset?: string,
     *   bucket?: string,
     *   owner_id?: int|string,
     *   max_upload_bytes?: int,
     *   max_edge?: int,
     *   max_bytes?: int,
     *   quality?: int,
     *   min_quality?: int,
     *   quality_step?: int
     * } $options
     * @return array{url: string, width: int, height: int, bytes: int, quality: int, max_edge: int, preset: string}
     */
    public function store(UploadedFile $file, array $options = []): array
    {
        $presetName = (string) ($options['preset'] ?? 'admin');
        if (!isset(self::PRESETS[$presetName])) {
            throw new ApiException('INVALID', '未知圖片預設：' . $presetName, 422, 42201);
        }
        $preset = self::PRESETS[$presetName];
        $maxUpload = (int) ($options['max_upload_bytes'] ?? $preset['max_upload_bytes']);
        $maxEdge = (int) ($options['max_edge'] ?? $preset['max_edge']);
        $maxBytes = (int) ($options['max_bytes'] ?? $preset['max_bytes']);
        $quality = (int) ($options['quality'] ?? $preset['quality']);
        $minQuality = (int) ($options['min_quality'] ?? $preset['min_quality']);
        $qualityStep = (int) ($options['quality_step'] ?? 8);

        $size = (int) $file->getSize();
        if ($size <= 0 || $size > $maxUpload) {
            $mb = max(1, (int) ceil($maxUpload / (1024 * 1024)));
            throw new ApiException('INVALID', '單張圖片不可超過 ' . $mb . 'MB', 422, 42289);
        }
        $ext = $this->resolveImageExt($file);

        $bucket = trim((string) ($options['bucket'] ?? $presetName), '/');
        if ($bucket === '' || !preg_match('#^[a-z0-9_\-]+$#i', $bucket)) {
            throw new ApiException('INVALID', '存儲目錄無效', 422, 42201);
        }
        $owner = (string) ($options['owner_id'] ?? 'shared');
        $owner = preg_replace('#[^a-zA-Z0-9_\-]#', '', $owner) ?: 'shared';

        $tmpRoot = sys_get_temp_dir() . '/sm-img-' . $bucket . '-' . $owner;
        if (!is_dir($tmpRoot) && !mkdir($tmpRoot, 0775, true) && !is_dir($tmpRoot)) {
            throw new ApiException('SERVER', '無法建立臨時目錄', 500, 50001);
        }
        $tmpName = bin2hex(random_bytes(8)) . '.' . $ext;
        $file->move($tmpRoot, $tmpName);
        $tmpPath = $tmpRoot . '/' . $tmpName;

        $relDir = $bucket . '/' . $owner . '/' . date('Ymd');
        $root = app()->getRootPath() . 'public/storage/' . $relDir;
        $outName = bin2hex(random_bytes(8)) . '.jpg';
        $destPath = $root . '/' . $outName;

        try {
            $meta = ImageProcess::compress($tmpPath, $destPath, [
                'max_edge' => $maxEdge,
                'max_bytes' => $maxBytes,
                'quality' => $quality,
                'min_quality' => $minQuality,
                'quality_step' => $qualityStep,
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
            'preset' => $presetName,
        ];
    }

    /**
     * 從請求合併 preset + 可選覆蓋參數（form / query / json）。
     *
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function optionsFromRequest(array $input, string $defaultPreset = 'admin'): array
    {
        $opts = ['preset' => (string) ($input['preset'] ?? $defaultPreset)];
        foreach (['bucket', 'owner_id', 'max_upload_bytes', 'max_edge', 'max_bytes', 'quality', 'min_quality', 'quality_step'] as $key) {
            if (array_key_exists($key, $input) && $input[$key] !== '' && $input[$key] !== null) {
                $opts[$key] = $input[$key];
            }
        }

        return $opts;
    }

    private function resolveImageExt(UploadedFile $file): string
    {
        $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        $mime = strtolower((string) ($file->getMime() ?: ''));
        if (isset($allowed[$mime])) {
            return $allowed[$mime];
        }
        $path = method_exists($file, 'getPathname') ? (string) $file->getPathname() : '';
        if ($path !== '' && is_file($path)) {
            $info = @getimagesize($path);
            $type = (int) ($info[2] ?? 0);
            $byType = [
                IMAGETYPE_JPEG => 'jpg',
                IMAGETYPE_PNG => 'png',
                IMAGETYPE_WEBP => 'webp',
            ];
            if (isset($byType[$type])) {
                return $byType[$type];
            }
        }
        $name = strtolower((string) $file->getOriginalName());
        if (preg_match('/\.(jpe?g)$/', $name)) {
            return 'jpg';
        }
        if (preg_match('/\.png$/', $name)) {
            return 'png';
        }
        if (preg_match('/\.webp$/', $name)) {
            return 'webp';
        }
        throw new ApiException('INVALID', '僅支持 JPG／PNG／WebP', 422, 42290);
    }
}
