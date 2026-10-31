<?php

declare(strict_types=1);

namespace app\controller\admin;

use app\controller\ApiController;
use app\service\HomeBannerService;
use app\service\ImageUploadService;
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

    /**
     * 兼容舊路徑 POST /uploads/banner；底層改走 ImageUploadService（與 /uploads/image 一致）。
     */
    public function upload(): Response
    {
        return $this->run(function () {
            $file = $this->request->file('file');
            if (!$file) {
                throw new ApiException('INVALID', '請選擇圖片', 422, 42201);
            }
            $admin = app('sistermart.admin') ?? [];
            $adminId = (int) ($admin['id'] ?? 0);
            $svc = new ImageUploadService();
            $opts = $svc->optionsFromRequest(['preset' => 'banner'], 'banner');
            $opts['bucket'] = 'banner';
            $opts['owner_id'] = $adminId > 0 ? $adminId : 'shared';

            return $svc->store($file, $opts);
        }, 201);
    }
}
