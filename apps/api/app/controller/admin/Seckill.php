<?php

declare(strict_types=1);

namespace app\controller\admin;

use app\controller\ApiController;
use app\service\SeckillService;
use think\Response;

/**
 * M21 後台秒殺場次 CRUD。
 */
class Seckill extends ApiController
{
    public function index(): Response
    {
        return $this->run(fn () => (new SeckillService())->adminList());
    }

    public function read(int $id): Response
    {
        return $this->run(fn () => (new SeckillService())->adminRead($id));
    }

    public function store(): Response
    {
        return $this->run(fn () => (new SeckillService())->adminCreate($this->payload()), 201);
    }

    public function update(int $id): Response
    {
        return $this->run(fn () => (new SeckillService())->adminUpdate($id, $this->payload()));
    }
}
