<?php

declare(strict_types=1);

namespace app\controller\api;

use app\controller\ApiController;
use app\service\FulfillmentService;
use think\Response;

class Shipments extends ApiController
{
    private function memberId(): int
    {
        return (int) app('sistermart.member')['id'];
    }

    public function read(int $orderId): Response
    {
        return $this->run(fn () => (new FulfillmentService())->logistics($orderId, $this->memberId()));
    }
}
