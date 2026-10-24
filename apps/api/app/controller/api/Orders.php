<?php

declare(strict_types=1);

namespace app\controller\api;

use app\controller\ApiController;
use app\service\OrderMessageService;
use app\service\OrderService;
use think\Response;

class Orders extends ApiController
{
    private function memberId(): int
    {
        return (int) app('sistermart.member')['id'];
    }

    public function quote(): Response
    {
        return $this->run(fn () => (new OrderService())->quote($this->memberId(), $this->payload()));
    }

    public function index(): Response
    {
        $status = $this->request->get('status');

        return $this->run(fn () => (new OrderService())->listForMember($this->memberId(), $status ?: null));
    }

    public function counts(): Response
    {
        return $this->run(fn () => (new OrderService())->countsForMember($this->memberId()));
    }

    public function store(): Response
    {
        return $this->run(fn () => (new OrderService())->create($this->memberId(), $this->payload()), 201);
    }

    public function read(string $id): Response
    {
        return $this->run(fn () => (new OrderService())->detail($this->memberId(), $id));
    }

    public function messages(string $id): Response
    {
        return $this->run(function () use ($id) {
            $items = (new OrderMessageService())->listForOrder((int) $id, $this->memberId(), false);

            return ['items' => $items];
        });
    }

    public function postMessage(string $id): Response
    {
        return $this->run(
            fn () => (new OrderMessageService())->buyerPost($this->memberId(), (int) $id, $this->payload()),
            201,
        );
    }

    public function cancel(int $id): Response
    {
        return $this->run(fn () => (new OrderService())->cancel($this->memberId(), $id));
    }

    public function pay(int $id): Response
    {
        $channel = (string) ($this->payload()['channel'] ?? '');

        return $this->run(fn () => (new OrderService())->createPayment($this->memberId(), $id, $channel), 201);
    }
}
