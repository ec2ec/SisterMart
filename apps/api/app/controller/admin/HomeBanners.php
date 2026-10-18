<?php

declare(strict_types=1);

namespace app\controller\admin;

use app\controller\ApiController;
use app\service\HomeBannerService;
use app\support\ApiException;
use think\Response;

class HomeBanners extends ApiController
{
    public function index(): Response
    {
        return $this->run(fn () => (new HomeBannerService())->listAdmin());
    }

    public function store(): Response
    {
        return $this->run(fn () => (new HomeBannerService())->save($this->payload()), 201);
    }

    public function update(int $id): Response
    {
        return $this->run(fn () => (new HomeBannerService())->save($this->payload(), $id));
    }

    public function destroy(int $id): Response
    {
        return $this->run(fn () => (new HomeBannerService())->delete($id));
    }

    public function upload(): Response
    {
        return $this->run(function () {
            $file = $this->request->file('file');
            if (!$file) {
                throw new ApiException('INVALID', '請選擇圖片', 422, 42201);
            }
            $size = (int) $file->getSize();
            if ($size <= 0 || $size > 2 * 1024 * 1024) {
                throw new ApiException('INVALID', '單張圖片不可超過 2MB', 422, 42289);
            }
            $mime = (string) ($file->getMime() ?: '');
            $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
            if (!isset($allowed[$mime])) {
                throw new ApiException('INVALID', '僅支持 JPG / PNG / WebP', 422, 42290);
            }
            $ext = $allowed[$mime];
            $relDir = 'banner/' . date('Ymd');
            $root = app()->getRootPath() . 'public/storage/' . $relDir;
            if (!is_dir($root) && !mkdir($root, 0775, true) && !is_dir($root)) {
                throw new ApiException('SERVER', '無法建立上傳目錄', 500, 50001);
            }
            $name = bin2hex(random_bytes(8)) . '.' . $ext;
            $file->move($root, $name);

            return ['url' => '/storage/' . $relDir . '/' . $name];
        }, 201);
    }
}
