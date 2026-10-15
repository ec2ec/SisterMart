<?php

declare(strict_types=1);

namespace app\controller\api;

use app\controller\ApiController;
use app\service\MockMarketingService;
use think\Response;

class Checkins extends ApiController
{
    public function calendar(): Response
    {
        return $this->run(function () {
            $member = app('sistermart.member');

            return (new MockMarketingService())->checkinCalendar((int) $member['id']);
        });
    }

    public function store(): Response
    {
        return $this->run(function () {
            $member = app('sistermart.member');

            return (new MockMarketingService())->checkin((int) $member['id']);
        }, 201);
    }
}
