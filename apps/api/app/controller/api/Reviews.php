<?php

declare(strict_types=1);

namespace app\controller\api;

use app\controller\ApiController;
use app\service\ReviewService;
use think\Response;

class Reviews extends ApiController
{
    private function memberId(): int
    {
        return (int) app('sistermart.member')['id'];
    }

    public function store(): Response
    {
        return $this->run(fn () => (new ReviewService())->create($this->memberId(), $this->payload()), 201);
    }

    public function follow(int $id): Response
    {
        return $this->run(fn () => (new ReviewService())->follow($this->memberId(), $id, $this->payload()));
    }
}
