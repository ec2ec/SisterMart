<?php

declare(strict_types=1);

namespace app\controller\api;

use app\BaseController;
use think\Response;

/**
 * 公開讀取 public/storage 下的上傳檔（共享主機常無 /storage 靜態別名）。
 * URL: GET /api/v1/media/avatar/1/20260101/xxx.jpg
 */
class Media extends BaseController
{
    public function show(string $path = ''): Response
    {
        $path = str_replace('\\', '/', $path);
        $path = ltrim($path, '/');
        if ($path === '' || str_contains($path, '..') || !preg_match('#^[a-zA-Z0-9_./\-]+$#', $path)) {
            return response('Not Found', 404);
        }
        $full = app()->getRootPath() . 'public/storage/' . $path;
        $root = realpath(app()->getRootPath() . 'public/storage');
        $real = realpath($full);
        if ($root === false || $real === false || !str_starts_with($real, $root) || !is_file($real)) {
            return response('Not Found', 404);
        }
        $mime = 'application/octet-stream';
        if (function_exists('mime_content_type')) {
            $detected = @mime_content_type($real);
            if (is_string($detected) && $detected !== '') {
                $mime = $detected;
            }
        }
        $ext = strtolower(pathinfo($real, PATHINFO_EXTENSION));
        $byExt = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
        ];
        if (isset($byExt[$ext])) {
            $mime = $byExt[$ext];
        }

        return response((string) file_get_contents($real), 200, [
            'Content-Type' => $mime,
            'Cache-Control' => 'public, max-age=86400',
            'Content-Length' => (string) filesize($real),
        ]);
    }
}
