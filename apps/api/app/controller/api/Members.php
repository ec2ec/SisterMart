<?php

declare(strict_types=1);

namespace app\controller\api;

use app\controller\ApiController;
use app\service\MemberAuthService;
use think\Response;

class Members extends ApiController
{
    public function store(): Response
    {
        return $this->run(fn () => (new MemberAuthService())->register($this->payload()), 201);
    }

    public function me(): Response
    {
        return $this->run(fn () => (new MemberAuthService())->present(app('sistermart.member')));
    }

    public function updateMe(): Response
    {
        return $this->run(function () {
            $member = app('sistermart.member');
            $body = $this->payload();
            $result = (new MemberAuthService())->updateLocale((int) $member['id'], (string) ($body['locale'] ?? ''));

            return $result['member'];
        });
    }
}
