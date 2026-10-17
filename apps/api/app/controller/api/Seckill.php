<?php

declare(strict_types=1);

namespace app\controller\api;

use app\controller\ApiController;
use app\service\SeckillService;
use think\Response;

class Seckill extends ApiController
{
    public function index(): Response
    {
        return $this->run(fn () => (new SeckillService())->sessions());
    }

    public function read(string $id): Response
    {
        return $this->run(fn () => (new SeckillService())->detail($id));
    }

    public function product(string $productId): Response
    {
        return $this->run(fn () => (new SeckillService())->product($productId));
    }
}
