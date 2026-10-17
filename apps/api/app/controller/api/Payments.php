<?php

declare(strict_types=1);

namespace app\controller\api;

use app\controller\ApiController;
use app\service\OrderService;
use think\Response;

class Payments extends ApiController
{
    /** 模擬渠道異步通知 / 前端確認成功 */
    public function mockConfirm(int $id): Response
    {
        return $this->run(fn () => (new OrderService())->mockConfirmPayment($id));
    }
}
