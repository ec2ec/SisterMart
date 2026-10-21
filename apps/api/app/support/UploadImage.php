<?php

declare(strict_types=1);

namespace app\support;

use think\file\UploadedFile;

/**
 * 上傳圖副檔名推斷：優先 getimagesize，避免共享主機未開 fileinfo 時
 * ThinkPHP File::getMime() 調用 finfo_open() 在 think 命名空間下致命錯誤。
 */
final class UploadImage
{
    /** @var array<string, string> */
    private const MIME_EXT = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    public static function resolveExt(UploadedFile $file): string
    {
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

        $mime = '';
        if (method_exists($file, 'getOriginalMime')) {
            $mime = strtolower((string) $file->getOriginalMime());
        }
        if (($mime === '' || $mime === 'application/octet-stream') && \function_exists('finfo_open')) {
            try {
                $mime = strtolower((string) ($file->getMime() ?: ''));
            } catch (\Throwable $e) {
                $mime = '';
            }
        }
        if (isset(self::MIME_EXT[$mime])) {
            return self::MIME_EXT[$mime];
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
