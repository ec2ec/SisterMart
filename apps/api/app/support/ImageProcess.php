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
        $keepAlpha = $outExt !== 'jpg' && self::hasAlpha($src, $type);
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

        if ($maxBytes > 0 && $bytes > $maxBytes) {
            $longNow = max($dstW, $dstH);
            $nextEdge = max(96, (int) floor($longNow * 0.75));
            if ($nextEdge < $longNow) {
                return self::compress($destPath, $destPath, [
                    'max_edge' => $nextEdge,
                    'max_bytes' => $maxBytes,
                    'quality' => $quality,
                    'min_quality' => $minQuality,
                    'quality_step' => $qualityStep,
                    'format' => $outExt,
                ]);
            }
            @unlink($destPath);
            $kb = max(1, (int) ceil($maxBytes / 1024));
            throw new ApiException('INVALID', '圖片壓縮後仍超過 ' . $kb . 'KB，請換一張較簡單的圖', 422, 42289);
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

    /**
     * 管理列表用縮略圖：中心裁切 cover，固定正方形邊長。
     *
     * @return array{path: string, width: int, height: int, bytes: int, format: string, ext: string}
     */
    public static function coverThumb(string $sourcePath, string $destPath, int $size = 64, int $quality = 82): array
    {
        $size = max(16, min(256, $size));
        $quality = self::clampQuality($quality);
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
        $outExt = self::outputExt('jpg', $type);

        $src = match ($type) {
            IMAGETYPE_JPEG => function_exists('imagecreatefromjpeg') ? @imagecreatefromjpeg($sourcePath) : false,
            IMAGETYPE_PNG => function_exists('imagecreatefrompng') ? @imagecreatefrompng($sourcePath) : false,
            IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($sourcePath) : false,
            default => false,
        };
        if ($src === false) {
            throw new ApiException('INVALID', '不支持的圖片格式，請使用 JPG／PNG／WebP', 422, 42290);
        }

        $scale = max($size / max(1, $srcW), $size / max(1, $srcH));
        $cropW = max(1, (int) round($size / $scale));
        $cropH = max(1, (int) round($size / $scale));
        $srcX = max(0, (int) floor(($srcW - $cropW) / 2));
        $srcY = max(0, (int) floor(($srcH - $cropH) / 2));
        if ($srcX + $cropW > $srcW) {
            $srcX = max(0, $srcW - $cropW);
        }
        if ($srcY + $cropH > $srcH) {
            $srcY = max(0, $srcH - $cropH);
        }

        $dst = imagecreatetruecolor($size, $size);
        if ($dst === false) {
            imagedestroy($src);
            throw new ApiException('SERVER', '圖片處理失敗', 500, 50001);
        }
        $white = imagecolorallocate($dst, 255, 255, 255);
        imagefilledrectangle($dst, 0, 0, $size, $size, $white);
        imagecopyresampled($dst, $src, 0, 0, $srcX, $srcY, $size, $size, $cropW, $cropH);
        imagedestroy($src);

        $dir = dirname($destPath);
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            imagedestroy($dst);
            throw new ApiException('SERVER', '無法建立上傳目錄', 500, 50001);
        }
        if (!self::write($dst, $destPath, $outExt, $quality)) {
            imagedestroy($dst);
            throw new ApiException('SERVER', '圖片寫入失敗', 500, 50001);
        }
        imagedestroy($dst);
        $bytes = (int) (filesize($destPath) ?: 0);

        return [
            'path' => $destPath,
            'width' => $size,
            'height' => $size,
            'bytes' => $bytes,
            'format' => $outExt,
            'ext' => $outExt,
        ];
    }

    /** 原圖 URL → 同目錄 `.thumb.` 縮略圖 URL。 */
    public static function thumbUrlFor(string $storageUrl): string
    {
        $url = trim($storageUrl);
        if ($url === '' || !str_starts_with($url, '/storage/')) {
            return $url;
        }
        if (preg_match('/\.thumb\.[a-z0-9]+$/i', $url)) {
            return $url;
        }

        return (string) preg_replace('/(\.[a-z0-9]+)$/i', '.thumb$1', $url);
    }

    /** 縮略圖 URL → 原圖 URL（無 `.thumb.` 則原樣）。 */
    public static function originalUrlFor(string $storageUrl): string
    {
        $url = trim($storageUrl);
        if ($url === '') {
            return $url;
        }

        return (string) preg_replace('/\.thumb(\.[a-z0-9]+)$/i', '$1', $url);
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

    /** @param \GdImage|resource $img */
    private static function hasAlpha($img, int $type): bool
    {
        if (!in_array($type, [IMAGETYPE_PNG, IMAGETYPE_WEBP, IMAGETYPE_GIF], true)) {
            return false;
        }
        if (function_exists('imageistruecolor') && !imageistruecolor($img)) {
            return imagecolortransparent($img) >= 0;
        }
        $w = imagesx($img);
        $h = imagesy($img);
        if ($w < 1 || $h < 1) {
            return false;
        }
        $stepX = max(1, intdiv($w, 24));
        $stepY = max(1, intdiv($h, 24));
        for ($y = 0; $y < $h; $y += $stepY) {
            for ($x = 0; $x < $w; $x += $stepX) {
                $alpha = (imagecolorat($img, $x, $y) >> 24) & 0x7F;
                if ($alpha > 0) {
                    return true;
                }
            }
        }

        return false;
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
