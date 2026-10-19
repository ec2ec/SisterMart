<?php

declare(strict_types=1);

namespace app\controller\admin;

use app\controller\ApiController;
use app\service\ImageUploadService;
use app\support\ApiException;
use think\Response;

/**
 * 後台通用圖片上傳（壓縮參數可配）。
 *
 * POST /api/v1/admin/uploads/image
 * multipart: file
 * 可選字段：preset=banner|product|admin|review|aftersale
 *           max_edge / max_bytes / quality / min_quality / bucket
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
            $svc = new ImageUploadService();
            $input = array_merge($this->request->get(), $this->request->post(), $this->payload());
            $opts = $svc->optionsFromRequest($input, 'admin');
            if (empty($opts['bucket'])) {
                $opts['bucket'] = match ((string) ($opts['preset'] ?? 'admin')) {
                    'banner' => 'banner',
                    'product' => 'product',
                    'review' => 'review',
                    'aftersale' => 'aftersale',
                    default => 'admin',
                };
            }
            $opts['owner_id'] = $adminId > 0 ? $adminId : 'shared';

            return $svc->store($file, $opts);
        }, 201);
    }
}
