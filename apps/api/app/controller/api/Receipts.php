<?php

declare(strict_types=1);

namespace app\controller\api;

use app\controller\ApiController;
use app\service\FulfillmentService;
use think\Response;

class Receipts extends ApiController
{
    private function memberId(): int
    {
        return (int) app('sistermart.member')['id'];
    }

    public function confirm(int $id): Response
    {
        return $this->run(fn () => (new FulfillmentService())->confirmReceipt($id, $this->memberId(), 'manual'));
    }
}
