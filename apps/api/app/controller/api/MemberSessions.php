<?php

declare(strict_types=1);

namespace app\controller\api;

use app\controller\ApiController;
use app\service\MemberAuthService;
use app\service\TokenService;
use think\Response;

class MemberSessions extends ApiController
{
    public function store(): Response
    {
        return $this->run(fn () => (new MemberAuthService())->login($this->payload()), 201);
    }

    public function destroy(): Response
    {
        return $this->run(function () {
            TokenService::revoke('member_tokens', TokenService::bearer($this->request));

            return null;
        });
    }
}
