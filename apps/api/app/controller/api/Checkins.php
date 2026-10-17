<?php

declare(strict_types=1);

namespace app\controller\api;

use app\controller\ApiController;
use app\service\CheckinService;
use think\Response;

class Checkins extends ApiController
{
    public function calendar(): Response
    {
        return $this->run(function () {
            $member = app('sistermart.member');

            return (new CheckinService())->calendar((int) $member['id']);
        });
    }

    public function store(): Response
    {
        return $this->run(function () {
            $member = app('sistermart.member');

            return (new CheckinService())->checkin((int) $member['id']);
        }, 201);
    }
}
