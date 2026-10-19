<?php

declare(strict_types=1);

namespace app\controller\api;

use app\controller\ApiController;
use app\service\ImageUploadService;
use app\support\ApiException;
use app\support\ImageProcess;
use think\Response;

class Uploads extends ApiController
{
    public function aftersaleEvidence(): Response
    {
        return $this->run(function () {
            $file = $this->request->file('file');
            if (!$file) {
                throw new ApiException('INVALID', '請選擇圖片', 422, 42201);
            }
            $memberId = (int) app('sistermart.member')['id'];

            return (new ImageUploadService())->store($file, [
                'preset' => 'aftersale',
                'bucket' => 'aftersale',
                'owner_id' => $memberId,
            ]);
        }, 201);
    }

    public function reviewImage(): Response
    {
        return $this->run(function () {
            $file = $this->request->file('file');
            if (!$file) {
                throw new ApiException('INVALID', '請選擇圖片', 422, 42201);
            }
            $memberId = (int) app('sistermart.member')['id'];

            return (new ImageUploadService())->store($file, [
                'preset' => 'review',
                'bucket' => 'review',
                'owner_id' => $memberId,
            ]);
        }, 201);
    }

    public function avatar(): Response
    {
        return $this->run(function () {
            $memberId = (int) app('sistermart.member')['id'];
            $file = $this->request->file('file');
            if (!$file) {
                throw new ApiException('INVALID', '請選擇圖片', 422, 42201);
            }
            $size = (int) $file->getSize();
            if ($size <= 0 || $size > 512 * 1024) {
                throw new ApiException('INVALID', '圖片過大', 422, 42289);
            }
            $mime = (string) ($file->getMime() ?: '');
            $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
            if (!isset($allowed[$mime])) {
                throw new ApiException('INVALID', '僅支持 JPG / PNG / WebP', 422, 42290);
            }
            $relDir = 'avatar/' . $memberId . '/' . date('Ymd');
            $root = app()->getRootPath() . 'public/storage/' . $relDir;
            if (!is_dir($root) && !mkdir($root, 0775, true) && !is_dir($root)) {
                throw new ApiException('SERVER', '無法建立上傳目錄', 500, 50001);
            }
            $tmpName = bin2hex(random_bytes(8)) . '.' . $allowed[$mime];
            $file->move($root, $tmpName);
            $tmpPath = $root . '/' . $tmpName;
            $outName = bin2hex(random_bytes(8)) . '.jpg';
            $destPath = $root . '/' . $outName;
            try {
                // 頭像：先壓最長邊，再中心裁正方形（沿用原行為）
                ImageProcess::compress($tmpPath, $destPath, [
                    'max_edge' => 512,
                    'max_bytes' => 512 * 1024,
                    'quality' => 85,
                ]);
                $this->cropSquare($destPath, 512);
            } finally {
                @unlink($tmpPath);
            }
            $url = '/storage/' . $relDir . '/' . $outName;
            \think\facade\Db::name('members')->where('id', $memberId)->update([
                'avatar_url' => $url,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            $member = \think\facade\Db::name('members')->where('id', $memberId)->find();

            return [
                'url' => $url,
                'member' => (new \app\service\MemberAuthService())->present($member),
            ];
        }, 201);
    }

    private function cropSquare(string $path, int $side): void
    {
        if (!function_exists('imagecreatefromjpeg')) {
            return;
        }
        $src = @imagecreatefromjpeg($path);
        if (!$src) {
            return;
        }
        $w = imagesx($src);
        $h = imagesy($src);
        $crop = min($w, $h);
        $sx = (int) (($w - $crop) / 2);
        $sy = (int) (($h - $crop) / 2);
        $dst = imagecreatetruecolor($side, $side);
        imagecopyresampled($dst, $src, 0, 0, $sx, $sy, $side, $side, $crop, $crop);
        imagejpeg($dst, $path, 82);
        imagedestroy($src);
        imagedestroy($dst);
    }
}
