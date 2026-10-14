<?php

declare(strict_types=1);

namespace app\controller\api;

use app\controller\ApiController;
use app\service\MemberAuthService;
use app\service\TokenService;
use think\Response;

class Auth extends ApiController
{
    public function code(): Response
    {
        return $this->run(fn () => (new MemberAuthService())->sendCode($this->payload()));
    }

    public function register(): Response
    {
        return $this->run(fn () => (new MemberAuthService())->register($this->payload()));
    }

    public function login(): Response
    {
        return $this->run(fn () => (new MemberAuthService())->login($this->payload()));
    }

    public function reset(): Response
    {
        return $this->run(fn () => (new MemberAuthService())->reset($this->payload()));
    }

    public function logout(): Response
    {
        return $this->run(function () {
            TokenService::revoke('member_tokens', TokenService::bearer($this->request));

            return ['logged_out' => true];
        });
    }

    public function me(): Response
    {
        return $this->run(fn () => ['member' => (new MemberAuthService())->present(app('sistermart.member'))]);
    }

    public function locale(): Response
    {
        return $this->run(function () {
            $member = app('sistermart.member');

            return (new MemberAuthService())->updateLocale((int) $member['id'], (string) ($this->payload()['locale'] ?? ''));
        });
    }
}
