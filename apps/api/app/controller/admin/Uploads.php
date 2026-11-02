<?php

declare(strict_types=1);

namespace app\controller\admin;

use app\controller\ApiController;
use app\service\ImageUploadService;
use app\support\ApiException;
use think\Response;

/**
 * 後台通用圖片上傳。壓縮只讀「圖片上傳配置」，請求不能覆蓋。
 *
 * POST /api/v1/admin/uploads/image
 * multipart: file
 * 字段：scene=catalog.product|marketing.banner|...（或舊 preset）
 */
class Uploads extends ApiController
{
    public function image(): Response
    {
        return $this->run(function () {
            $file = $this->request->file('file');
            if (!$file) {
                throw new ApiException('INVALID', '請選擇圖片', 422, 42201);
            }
            $admin = app('sistermart.admin') ?? [];
            $adminId = (int) ($admin['id'] ?? 0);
            $input = array_merge($this->request->get(), $this->request->post(), $this->payload());
            $opts = [
                'owner_id' => $adminId > 0 ? 'draft-' . $adminId : 'shared',
            ];
            $scene = trim((string) ($input['scene'] ?? ''));
            if ($scene !== '') {
                $opts['scene'] = $scene;
            } else {
                $opts['preset'] = (string) ($input['preset'] ?? 'admin');
            }

            return (new ImageUploadService())->store($file, $opts);
        }, 201);
    }
}
