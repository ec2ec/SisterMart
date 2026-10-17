<?php

declare(strict_types=1);

namespace app\controller\api;

use app\controller\ApiController;
use app\service\AftersaleService;
use think\Response;

class Aftersales extends ApiController
{
    private function memberId(): int
    {
        return (int) app('sistermart.member')['id'];
    }

    public function index(): Response
    {
        return $this->run(fn () => (new AftersaleService())->listForMember($this->memberId()));
    }

    public function store(): Response
    {
        return $this->run(fn () => (new AftersaleService())->apply($this->memberId(), $this->payload()), 201);
    }

    public function read(int $id): Response
    {
        return $this->run(fn () => (new AftersaleService())->detail($id, $this->memberId()));
    }

    public function cancel(int $id): Response
    {
        return $this->run(fn () => (new AftersaleService())->cancel($this->memberId(), $id));
    }
}
