<?php

declare(strict_types=1);

namespace app\controller\admin;

use app\controller\ApiController;
use app\service\MessageService;
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

    public function buyerMessage(string $id): Response
    {
        return $this->run(
            fn () => (new MessageService())->adminLeaveOrderNote((int) $id, $this->payload()),
            201,
        );
    }

    public function closeExpired(): Response
    {
        return $this->run(fn () => (new OrderService())->closeExpired());
    }
}
