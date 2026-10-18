<?php

declare(strict_types=1);

namespace app\controller\api;

use app\controller\ApiController;
use app\support\ApiException;
use app\support\ImageProcess;
use think\Response;

class Uploads extends ApiController
{
    public function aftersaleEvidence(): Response
    {
        return $this->run(fn () => $this->storeRawImage('aftersale', 1024 * 1024), 201);
    }

    public function reviewImage(): Response
    {
        return $this->run(function () {
            $memberId = (int) app('sistermart.member')['id'];
            $file = $this->request->file('file');
            if (!$file) {
                throw new ApiException('INVALID', '請選擇圖片', 422, 42201);
            }
            $size = (int) $file->getSize();
            if ($size <= 0 || $size > 5 * 1024 * 1024) {
                throw new ApiException('INVALID', '單張圖片不可超過 5MB', 422, 42289);
            }
            $mime = (string) ($file->getMime() ?: '');
            $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
            if (!isset($allowed[$mime])) {
                throw new ApiException('INVALID', '僅支持 JPG / PNG / WebP', 422, 42290);
            }

            $tmpRoot = sys_get_temp_dir() . '/sm-review-' . $memberId;
            if (!is_dir($tmpRoot) && !mkdir($tmpRoot, 0775, true) && !is_dir($tmpRoot)) {
                throw new ApiException('SERVER', '無法建立臨時目錄', 500, 50001);
            }
            $tmpName = bin2hex(random_bytes(8)) . '.' . $allowed[$mime];
            $file->move($tmpRoot, $tmpName);
            $tmpPath = $tmpRoot . '/' . $tmpName;

            $relDir = 'review/' . $memberId . '/' . date('Ymd');
            $root = app()->getRootPath() . 'public/storage/' . $relDir;
            $outName = bin2hex(random_bytes(8)) . '.jpg';
            $destPath = $root . '/' . $outName;

            try {
                $meta = ImageProcess::toJpegMax($tmpPath, $destPath, 1200, 512000);
            } finally {
                @unlink($tmpPath);
            }

            return [
                'url' => '/storage/' . $relDir . '/' . $outName,
                'width' => $meta['width'],
                'height' => $meta['height'],
                'bytes' => $meta['bytes'],
            ];
        }, 201);
    }

    private function storeRawImage(string $bucket, int $maxBytes): array
    {
        $memberId = (int) app('sistermart.member')['id'];
        $file = $this->request->file('file');
        if (!$file) {
            throw new ApiException('INVALID', '請選擇圖片', 422, 42201);
        }
        $size = (int) $file->getSize();
        if ($size <= 0 || $size > $maxBytes) {
            throw new ApiException('INVALID', '單張圖片不可超過 1MB', 422, 42289);
        }
        $mime = (string) ($file->getMime() ?: '');
        $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        if (!isset($allowed[$mime])) {
            throw new ApiException('INVALID', '僅支持 JPG / PNG / WebP', 422, 42290);
        }
        $ext = $allowed[$mime];
        $relDir = $bucket . '/' . $memberId . '/' . date('Ymd');
        $root = app()->getRootPath() . 'public/storage/' . $relDir;
        if (!is_dir($root) && !mkdir($root, 0775, true) && !is_dir($root)) {
            throw new ApiException('SERVER', '無法建立上傳目錄', 500, 50001);
        }
        $name = bin2hex(random_bytes(8)) . '.' . $ext;
        $file->move($root, $name);

        return ['url' => '/storage/' . $relDir . '/' . $name];
    }
}
