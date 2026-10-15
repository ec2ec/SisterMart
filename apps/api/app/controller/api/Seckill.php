<?php

declare(strict_types=1);

namespace app\controller\api;

use app\controller\ApiController;
use app\service\MockMarketingService;
use think\Response;

class Seckill extends ApiController
{
    public function index(): Response
    {
        return $this->run(fn () => (new MockMarketingService())->seckillSessions());
    }

    public function read(string $id): Response
    {
        return $this->run(fn () => (new MockMarketingService())->seckillDetail($id));
    }

    public function product(string $productId): Response
    {
        return $this->run(fn () => (new MockMarketingService())->seckillProduct($productId));
    }
}
