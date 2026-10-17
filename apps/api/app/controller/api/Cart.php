<?php

declare(strict_types=1);

namespace app\controller\api;

use app\controller\ApiController;
use app\service\CartService;
use think\Response;

class Cart extends ApiController
{
    private function memberId(): int
    {
        return (int) app('sistermart.member')['id'];
    }

    public function show(): Response
    {
        return $this->run(fn () => (new CartService())->get($this->memberId()));
    }

    public function storeItem(): Response
    {
        $body = $this->payload();

        return $this->run(fn () => (new CartService())->add(
            $this->memberId(),
            (int) ($body['sku_id'] ?? 0),
            (int) ($body['qty'] ?? 1),
        ), 201);
    }

    public function updateItem(int $id): Response
    {
        return $this->run(fn () => (new CartService())->updateItem($this->memberId(), $id, $this->payload()));
    }

    public function destroyItems(): Response
    {
        $ids = $this->payload()['ids'] ?? [];

        return $this->run(fn () => (new CartService())->removeItems($this->memberId(), is_array($ids) ? $ids : []));
    }

    public function checkAll(): Response
    {
        $checked = !empty($this->payload()['checked']);

        return $this->run(fn () => (new CartService())->setAllChecked($this->memberId(), $checked));
    }
}
