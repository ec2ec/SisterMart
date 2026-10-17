<?php

declare(strict_types=1);

namespace app\controller\api;

use app\controller\ApiController;
use app\service\OrderService;
use think\Response;

class TradeJobs extends ApiController
{
    /** 手動觸發未支付超時關單（驗收與運維） */
    public function closeExpired(): Response
    {
        return $this->run(fn () => (new OrderService())->closeExpired());
    }
}
