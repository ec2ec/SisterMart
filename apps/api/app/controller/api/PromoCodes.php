<?php

declare(strict_types=1);

namespace app\controller\api;

use app\controller\ApiController;
use app\service\PromoCodeService;
use app\support\ApiException;
use think\facade\Db;
use think\Response;

class PromoCodes extends ApiController
{
    /** 註冊前校驗推薦人編號（空碼視為有效／選填） */
    public function verify(): Response
    {
        return $this->run(function () {
            $code = trim((string) ($this->request->get('code') ?? $this->payload()['code'] ?? ''));
            if ($code === '') {
                return ['valid' => true, 'empty' => true];
            }
            $parsed = PromoCodeService::parse($code);
            if (!$parsed['ok']) {
                throw new ApiException('BAD_REF', '推薦人編號不正確');
            }
            $parent = Db::name('members')->where('promo_code', $parsed['code'])->find();
            if (!$parent || (int) $parent['status'] !== 1) {
                throw new ApiException('BAD_REF', '推薦人編號不正確');
            }

            return ['valid' => true, 'empty' => false];
        });
    }
}
