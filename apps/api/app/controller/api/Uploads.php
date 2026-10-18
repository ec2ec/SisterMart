<?php

declare(strict_types=1);

namespace app\controller\api;

use app\controller\ApiController;
use app\service\ImageUploadService;
use app\support\ApiException;
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

            // 會員端固定 preset，不開放壓縮參數覆蓋（防繞過體積/邊長限制）
            return (new ImageUploadService())->store($file, [
                'preset' => 'review',
                'bucket' => 'review',
                'owner_id' => $memberId,
            ]);
        }, 201);
    }
}
