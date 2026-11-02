<?php

declare(strict_types=1);

namespace app\support;

/**
 * 通用圖片壓縮：等比縮放（最長邊 ≤ max_edge），不裁切。
 *
 * format:
 * - jpg  一律 JPEG
 * - png  一律 PNG
 * - keep 沿用原圖類型（JPEG / PNG / WebP；其餘按 JPEG）
 */
final class ImageProcess
{
    /**
     * @param array{
     *   max_edge?: int,
     *   max_bytes?: int,
     *   quality?: int,
     *   min_quality?: int,
     *   quality_step?: int,
     *   format?: string
     * } $options
     * @return array{path: string, width: int, height: int, bytes: int, quality: int, max_edge: int, format: string, ext: string}
     */
    public static function compress(string $sourcePath, string $destPath, array $options = []): array
    {
        $maxEdge = max(1, (int) ($options['max_edge'] ?? 1000));
        $maxBytes = (int) ($options['max_bytes'] ?? 500 * 1024);
        $quality = self::clampQuality((int) ($options['quality'] ?? 85));
        $minQuality = self::clampQuality((int) ($options['min_quality'] ?? 40));
        $qualityStep = max(1, (int) ($options['quality_step'] ?? 8));
        $format = (string) ($options['format'] ?? 'jpg');
        if ($minQuality > $quality) {
            $minQuality = $quality;
        }

        if (!function_exists('imagecreatetruecolor') || !function_exists('imagejpeg')) {
            throw new ApiException('SERVER', '伺服器未啟用 GD 圖片擴展，無法處理圖片', 500, 50002);
        }
        if (!is_file($sourcePath)) {
            throw new ApiException('INVALID', '圖片讀取失敗', 422, 42290);
        }
        $info = @getimagesize($sourcePath);
        if (!$info || empty($info[0]) || empty($info[1])) {
            throw new ApiException('INVALID', '無法解析圖片', 422, 42290);
        }
        $srcW = (int) $info[0];
        $srcH = (int) $info[1];
        $type = (int) ($info[2] ?? 0);
        $outExt = self::outputExt($format, $type);

        $src = match ($type) {
            IMAGETYPE_JPEG => function_exists('imagecreatefromjpeg') ? @imagecreatefromjpeg($sourcePath) : false,
            IMAGETYPE_PNG => function_exists('imagecreatefrompng') ? @imagecreatefrompng($sourcePath) : false,
            IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($sourcePath) : false,
            default => false,
        };
        if ($src === false) {
            if ($type === IMAGETYPE_WEBP && !function_exists('imagecreatefromwebp')) {
                throw new ApiException('INVALID', '伺服器 GD 不支持 WebP，請改用 JPG／PNG', 422, 42290);
            }
            throw new ApiException('INVALID', '不支持的圖片格式，請使用 JPG／PNG／WebP', 422, 42290);
        }

        $scale = 1.0;
        $long = max($srcW, $srcH);
        if ($long > $maxEdge) {
            $scale = $maxEdge / $long;
        }
        $dstW = max(1, (int) round($srcW * $scale));
        $dstH = max(1, (int) round($srcH * $scale));

        $dst = imagecreatetruecolor($dstW, $dstH);
        if ($dst === false) {
            imagedestroy($src);
            throw new ApiException('SERVER', '圖片處理失敗', 500, 50001);
        }
        $keepAlpha = $outExt !== 'jpg' && in_array($type, [IMAGETYPE_PNG, IMAGETYPE_WEBP], true);
        if ($keepAlpha) {
            imagealphablending($dst, false);
            imagesavealpha($dst, true);
            $transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
            imagefilledrectangle($dst, 0, 0, $dstW, $dstH, $transparent);
            imagealphablending($dst, true);
        } else {
            $white = imagecolorallocate($dst, 255, 255, 255);
            imagefilledrectangle($dst, 0, 0, $dstW, $dstH, $white);
        }
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $dstW, $dstH, $srcW, $srcH);
        imagedestroy($src);

        $dir = dirname($destPath);
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            imagedestroy($dst);
            throw new ApiException('SERVER', '無法建立上傳目錄', 500, 50001);
        }

        $usedQuality = $quality;
        $bytes = 0;
        do {
            if (!self::write($dst, $destPath, $outExt, $usedQuality)) {
                imagedestroy($dst);
                throw new ApiException('SERVER', '圖片寫入失敗', 500, 50001);
            }
            $bytes = (int) filesize($destPath);
            if ($maxBytes <= 0 || $bytes <= $maxBytes || $usedQuality <= $minQuality) {
                break;
            }
            $usedQuality = max($minQuality, $usedQuality - $qualityStep);
        } while (true);

        imagedestroy($dst);

        if ($maxBytes > 0 && $bytes > $maxBytes && max($dstW, $dstH) > 600) {
            return self::compress($destPath, $destPath, [
                'max_edge' => max(600, (int) floor($maxEdge * 0.75)),
                'max_bytes' => $maxBytes,
                'quality' => $quality,
                'min_quality' => $minQuality,
                'quality_step' => $qualityStep,
                'format' => $outExt === 'jpg' ? 'jpg' : $outExt,
            ]);
        }
        if ($maxBytes > 0 && $bytes > $maxBytes) {
            @unlink($destPath);
            throw new ApiException('INVALID', '圖片壓縮後仍超過限制，請換一張圖', 422, 42289);
        }

        return [
            'path' => $destPath,
            'width' => $dstW,
            'height' => $dstH,
            'bytes' => $bytes,
            'quality' => $usedQuality,
            'max_edge' => $maxEdge,
            'format' => $outExt,
            'ext' => $outExt,
        ];
    }

    /**
     * @return array{path: string, width: int, height: int, bytes: int, quality: int, max_edge: int, format: string, ext: string}
     */
    public static function toJpegMax(
        string $sourcePath,
        string $destPath,
        int $maxEdge = 1000,
        int $maxBytes = 500 * 1024,
        int $quality = 85,
    ): array {
        return self::compress($sourcePath, $destPath, [
            'max_edge' => $maxEdge,
            'max_bytes' => $maxBytes,
            'quality' => $quality,
            'format' => 'jpg',
        ]);
    }

    public static function resolveOutputExt(string $format, string $sourcePath): string
    {
        $info = @getimagesize($sourcePath);
        $type = (int) ($info[2] ?? 0);

        return self::outputExt($format, $type);
    }

    private static function outputExt(string $format, int $type): string
    {
        if ($format === 'png' || $format === 'webp') {
            if ($format === 'png' && !function_exists('imagepng')) {
                throw new ApiException('SERVER', '伺服器 GD 不支持 PNG 輸出', 500, 50002);
            }
            if ($format === 'webp' && !function_exists('imagewebp')) {
                throw new ApiException('SERVER', '伺服器 GD 不支持 WebP 輸出', 500, 50002);
            }

            return $format === 'webp' ? 'webp' : 'png';
        }
        if ($format === 'keep') {
            if ($type === IMAGETYPE_PNG && function_exists('imagepng')) {
                return 'png';
            }
            if ($type === IMAGETYPE_WEBP && function_exists('imagewebp')) {
                return 'webp';
            }

            return 'jpg';
        }

        return 'jpg';
    }

    /** @param \GdImage|resource $dst */
    private static function write($dst, string $destPath, string $ext, int $quality): bool
    {
        if ($ext === 'png') {
            $level = (int) round((100 - self::clampQuality($quality)) * 9 / 99);

            return (bool) @imagepng($dst, $destPath, max(0, min(9, $level)));
        }
        if ($ext === 'webp') {
            return (bool) @imagewebp($dst, $destPath, self::clampQuality($quality));
        }

        return (bool) @imagejpeg($dst, $destPath, self::clampQuality($quality));
    }

    private static function clampQuality(int $q): int
    {
        return max(1, min(100, $q));
    }
}
