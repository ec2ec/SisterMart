<?php

declare(strict_types=1);

namespace app\controller\api;

use app\controller\ApiController;
use app\service\DistributionService;
use think\Response;

class Distribution extends ApiController
{
    public function overview(): Response
    {
        return $this->run(function () {
            $member = app('sistermart.member');

            return (new DistributionService())->overview((int) $member['id']);
        });
    }

    public function downlines(): Response
    {
        return $this->run(function () {
            $member = app('sistermart.member');

            return (new DistributionService())->downlines((int) $member['id']);
        });
    }

    public function commissions(): Response
    {
        return $this->run(function () {
            $member = app('sistermart.member');

            return (new DistributionService())->commissions((int) $member['id']);
        });
    }

    public function orders(): Response
    {
        return $this->run(function () {
            $member = app('sistermart.member');

            return (new DistributionService())->promoOrders((int) $member['id']);
        });
    }

    public function ranking(): Response
    {
        $period = (string) $this->request->get('period', 'month');

        return $this->run(fn () => (new DistributionService())->ranking($period));
    }

    public function withdraw(): Response
    {
        return $this->run(function () {
            $member = app('sistermart.member');

            return (new DistributionService())->withdraw((int) $member['id'], $this->payload());
        }, 201);
    }

    public function poster(): Response
    {
        return $this->run(function () {
            $member = app('sistermart.member');
            $productId = $this->request->get('product_id');

            return (new DistributionService())->poster((int) $member['id'], $productId ? (string) $productId : null);
        });
    }
}
