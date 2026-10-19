<?php

declare(strict_types=1);

namespace app\controller\api;

use app\controller\ApiController;
use app\service\ImageUploadService;
use app\support\ApiException;
use app\support\ImageProcess;
use think\file\UploadedFile;
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
                throw new ApiException('INVALID', '請選擇圖片（上傳可能被主機禁用，請換 JPG 再試）', 422, 42201);
            }
            $size = (int) $file->getSize();
            // 客戶端已裁成 ≤512 方圖；放寬到 5MB 兼容未壓縮原圖誤傳
            if ($size <= 0 || $size > 5 * 1024 * 1024) {
                throw new ApiException('INVALID', '圖片過大（上限 5MB）', 422, 42289);
            }
            $ext = $this->resolveImageExt($file);
            $relDir = 'avatar/' . $memberId . '/' . date('Ymd');
            $root = app()->getRootPath() . 'public/storage/' . $relDir;
            if (!is_dir($root) && !mkdir($root, 0775, true) && !is_dir($root)) {
                throw new ApiException('SERVER', '無法建立上傳目錄，請檢查 public/storage 寫入權限', 500, 50001);
            }
            $tmpName = bin2hex(random_bytes(8)) . '.' . $ext;
            $file->move($root, $tmpName);
            $tmpPath = $root . '/' . $tmpName;
            $outName = bin2hex(random_bytes(8)) . '.jpg';
            $destPath = $root . '/' . $outName;
            try {
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

    /** 優先 MIME，失敗則用 getimagesize／副檔名推斷（兼容 Blob 無 Content-Type）。 */
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

    private function cropSquare(string $path, int $side): void
    {
        if (!function_exists('imagecreatefromjpeg') || !function_exists('imagecreatetruecolor')) {
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
        if ($dst === false) {
            imagedestroy($src);

            return;
        }
        imagecopyresampled($dst, $src, 0, 0, $sx, $sy, $side, $side, $crop, $crop);
        imagejpeg($dst, $path, 82);
        imagedestroy($src);
        imagedestroy($dst);
    }
}
