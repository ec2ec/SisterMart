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
            if (isset($body['locale']) && count($body) === 1) {
                $result = (new MemberAuthService())->updateLocale((int) $member['id'], (string) $body['locale']);

                return $result['member'];
            }

            return (new MemberAuthService())->updateProfile((int) $member['id'], $body);
        });
    }

    public function bindCode(): Response
    {
        return $this->run(function () {
            $member = app('sistermart.member');

            return (new MemberAuthService())->sendBindCode((int) $member['id'], $this->payload());
        }, 201);
    }

    public function bind(): Response
    {
        return $this->run(function () {
            $member = app('sistermart.member');

            return (new MemberAuthService())->confirmBind((int) $member['id'], $this->payload());
        });
    }
}
