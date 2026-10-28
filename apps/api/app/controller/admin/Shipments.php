<?php

declare(strict_types=1);

namespace app\controller\admin;

use app\controller\ApiController;
use app\service\FulfillmentService;
use app\support\ApiException;
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

    /**
     * 未發貨時返回 null（HTTP 200），避免後台打開待發貨訂單因 404 彈全局錯誤。
     */
    public function read(int $orderId): Response
    {
        return $this->run(function () use ($orderId) {
            try {
                return (new FulfillmentService())->logistics($orderId);
            } catch (ApiException $e) {
                if ($e->businessCode === 40451) {
                    return null;
                }
                throw $e;
            }
        });
    }
}
