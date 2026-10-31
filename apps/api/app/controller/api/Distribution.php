<?php

declare(strict_types=1);

namespace app\controller\api;

use app\controller\ApiController;
use app\service\DistributionAccessService;
use app\service\DistributionService;
use think\Response;

class Distribution extends ApiController
{
    private function memberIdAllowed(): int
    {
        $member = app('sistermart.member');
        $id = (int) $member['id'];
        (new DistributionAccessService())->assertMemberAllowed($id);

        return $id;
    }

    public function overview(): Response
    {
        return $this->run(function () {
            return (new DistributionService())->overview($this->memberIdAllowed());
        });
    }

    public function downlines(): Response
    {
        return $this->run(function () {
            return (new DistributionService())->downlines($this->memberIdAllowed());
        });
    }

    public function commissions(): Response
    {
        return $this->run(function () {
            return (new DistributionService())->commissions($this->memberIdAllowed());
        });
    }

    public function orders(): Response
    {
        return $this->run(function () {
            return (new DistributionService())->promoOrders($this->memberIdAllowed());
        });
    }

    public function ranking(): Response
    {
        $period = (string) $this->request->get('period', 'month');

        return $this->run(function () use ($period) {
            $this->memberIdAllowed();

            return (new DistributionService())->ranking($period);
        });
    }

    public function withdraw(): Response
    {
        return $this->run(function () {
            return (new DistributionService())->withdraw($this->memberIdAllowed(), $this->payload());
        }, 201);
    }

    public function poster(): Response
    {
        return $this->run(function () {
            $memberId = $this->memberIdAllowed();
            $productId = $this->request->get('product_id');

            return (new DistributionService())->poster($memberId, $productId ? (string) $productId : null);
        });
    }
}
