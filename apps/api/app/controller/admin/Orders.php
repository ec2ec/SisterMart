<?php

declare(strict_types=1);

namespace app\controller\admin;

use app\controller\ApiController;
use app\service\OrderMessageService;
use app\service\OrderService;
use think\Response;

class Orders extends ApiController
{
    public function index(): Response
    {
        $status = $this->request->get('status');

        return $this->run(fn () => (new OrderService())->listAdmin($status ?: null));
    }

    public function read(string $id): Response
    {
        return $this->run(fn () => (new OrderService())->detail(0, $id, true));
    }

    public function messages(string $id): Response
    {
        return $this->run(function () use ($id) {
            $items = (new OrderMessageService())->listForOrder((int) $id, 0, true);

            return ['items' => $items];
        });
    }

    public function buyerMessage(string $id): Response
    {
        return $this->run(function () use ($id) {
            $admin = app('sistermart.admin');
            $adminId = is_array($admin) ? (int) ($admin['id'] ?? 0) : 0;

            return (new OrderMessageService())->sellerPost((int) $id, $this->payload(), $adminId ?: null);
        }, 201);
    }

    public function closeExpired(): Response
    {
        return $this->run(fn () => (new OrderService())->closeExpired());
    }
}
