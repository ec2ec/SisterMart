<?php

declare(strict_types=1);

namespace app\controller\admin;

use app\controller\ApiController;
use app\service\CouponService;
use think\Response;

class Coupons extends ApiController
{
    public function index(): Response
    {
        return $this->run(fn () => (new CouponService())->adminList());
    }

    public function store(): Response
    {
        return $this->run(fn () => (new CouponService())->adminCreate($this->payload()), 201);
    }

    public function update(int $id): Response
    {
        return $this->run(fn () => (new CouponService())->adminUpdate($id, $this->payload()));
    }
}
