<?php

declare(strict_types=1);

namespace app\support;

/**
 * 評價圖：等比縮放至最長邊 ≤ maxEdge，輸出 JPEG，目標體積 ≤ maxBytes。
 */
final class ImageProcess
{
    /**
     * @return array{path: string, width: int, height: int, bytes: int}
     */
    public static function toJpegMax(
        string $sourcePath,
        string $destPath,
        int $maxEdge = 1200,
        int $maxBytes = 512000,
    ): array {
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

        $src = match ($type) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($sourcePath),
            IMAGETYPE_PNG => @imagecreatefrompng($sourcePath),
            IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($sourcePath) : false,
            default => false,
        };
        if ($src === false) {
            throw new ApiException('INVALID', '不支持的圖片格式', 422, 42290);
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
        // JPEG 無透明；白底避免 PNG 透明變黑
        $white = imagecolorallocate($dst, 255, 255, 255);
        imagefilledrectangle($dst, 0, 0, $dstW, $dstH, $white);
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $dstW, $dstH, $srcW, $srcH);
        imagedestroy($src);

        $dir = dirname($destPath);
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            imagedestroy($dst);
            throw new ApiException('SERVER', '無法建立上傳目錄', 500, 50001);
        }

        $quality = 85;
        $bytes = 0;
        do {
            if (!@imagejpeg($dst, $destPath, $quality)) {
                imagedestroy($dst);
                throw new ApiException('SERVER', '圖片寫入失敗', 500, 50001);
            }
            $bytes = (int) filesize($destPath);
            if ($bytes <= $maxBytes || $quality <= 40) {
                break;
            }
            $quality -= 8;
        } while (true);

        imagedestroy($dst);

        // 極端大圖仍超限：再縮一半重試一次
        if ($bytes > $maxBytes && max($dstW, $dstH) > 600) {
            return self::toJpegMax($destPath, $destPath, (int) floor($maxEdge * 0.75), $maxBytes);
        }
        if ($bytes > $maxBytes) {
            @unlink($destPath);
            throw new ApiException('INVALID', '圖片壓縮後仍超過 500KB，請換一張圖', 422, 42289);
        }

        return [
            'path' => $destPath,
            'width' => $dstW,
            'height' => $dstH,
            'bytes' => $bytes,
        ];
    }
}
