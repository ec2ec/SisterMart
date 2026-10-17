<?php

declare(strict_types=1);

namespace app\controller\api;

use app\controller\ApiController;
use app\service\CouponService;
use think\Response;

class Coupons extends ApiController
{
    public function claimable(): Response
    {
        return $this->run(function () {
            $memberId = null;
            $token = \app\service\TokenService::bearer($this->request);
            $id = \app\service\TokenService::ownerId('member_tokens', 'member_id', $token);
            if ($id) {
                $memberId = (int) $id;
            }

            return (new CouponService())->claimable($memberId);
        });
    }

    public function claim(): Response
    {
        return $this->run(function () {
            $member = app('sistermart.member');
            $id = (int) ($this->payload()['coupon_id'] ?? 0);
            if ($id <= 0) {
                $id = (int) $this->request->param('id', 0);
            }

            return (new CouponService())->claim((int) $member['id'], $id);
        }, 201);
    }

    public function mine(): Response
    {
        return $this->run(function () {
            $member = app('sistermart.member');
            $status = $this->request->get('status');

            return (new CouponService())->mine((int) $member['id'], $status ? (string) $status : null);
        });
    }
}
