<?php

declare(strict_types=1);

namespace app\controller\admin;

use app\controller\ApiController;
use app\service\FulfillmentService;
use think\Response;

class Shipments extends ApiController
{
    public function ship(int $orderId): Response
    {
        return $this->run(fn () => (new FulfillmentService())->ship($orderId, $this->payload()), 201);
    }

    public function addNode(int $orderId): Response
    {
        return $this->run(fn () => (new FulfillmentService())->addNode($orderId, $this->payload()), 201);
    }

    public function read(int $orderId): Response
    {
        return $this->run(fn () => (new FulfillmentService())->logistics($orderId));
    }
}
