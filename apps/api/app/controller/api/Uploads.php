<?php

declare(strict_types=1);

namespace app\controller\api;

use app\controller\ApiController;
use app\support\ApiException;
use think\Response;

class Uploads extends ApiController
{
    public function aftersaleEvidence(): Response
    {
        return $this->run(fn () => $this->storeImage('aftersale'), 201);
    }

    public function reviewImage(): Response
    {
        return $this->run(fn () => $this->storeImage('review'), 201);
    }

    public function avatar(): Response
    {
        return $this->run(function () {
            $memberId = (int) app('sistermart.member')['id'];
            $stored = $this->storeImage('avatar', 512 * 1024, 512);
            // 寫入會員資料
            \think\facade\Db::name('members')->where('id', $memberId)->update([
                'avatar_url' => $stored['url'],
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            $member = \think\facade\Db::name('members')->where('id', $memberId)->find();

            return [
                'url' => $stored['url'],
                'member' => (new \app\service\MemberAuthService())->present($member),
            ];
        }, 201);
    }

    /**
     * @return array{url: string}
     */
    private function storeImage(string $bucket, int $maxBytes = 1048576, ?int $maxEdge = null): array
    {
        $memberId = (int) app('sistermart.member')['id'];
        $file = $this->request->file('file');
        if (!$file) {
            throw new ApiException('INVALID', '請選擇圖片', 422, 42201);
        }
        $size = (int) $file->getSize();
        if ($size <= 0 || $size > $maxBytes) {
            throw new ApiException('INVALID', '圖片過大', 422, 42289);
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
        $full = $root . '/' . $name;

        // 可選：服務端再壓一邊（GD），確保頭像不超邊長
        if ($maxEdge !== null && function_exists('imagecreatefromstring')) {
            $raw = @file_get_contents($full);
            if ($raw !== false) {
                $src = @imagecreatefromstring($raw);
                if ($src) {
                    $w = imagesx($src);
                    $h = imagesy($src);
                    $side = min($w, $h, $maxEdge);
                    $dst = imagecreatetruecolor($side, $side);
                    $sx = (int) (($w - min($w, $h)) / 2);
                    $sy = (int) (($h - min($w, $h)) / 2);
                    $crop = min($w, $h);
                    imagecopyresampled($dst, $src, 0, 0, $sx, $sy, $side, $side, $crop, $crop);
                    $outName = bin2hex(random_bytes(8)) . '.jpg';
                    $outFull = $root . '/' . $outName;
                    imagejpeg($dst, $outFull, 82);
                    imagedestroy($src);
                    imagedestroy($dst);
                    @unlink($full);
                    $name = $outName;
                }
            }
        }

        return ['url' => '/storage/' . $relDir . '/' . $name];
    }
}
