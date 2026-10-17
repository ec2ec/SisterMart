<?php

declare(strict_types=1);

namespace app\controller\admin;

use app\controller\ApiController;
use app\service\AdminAuthService;
use app\service\TokenService;
use think\Response;

class Sessions extends ApiController
{
    public function store(): Response
    {
        return $this->run(fn () => (new AdminAuthService())->login($this->payload()), 201);
    }

    public function destroy(): Response
    {
        return $this->run(function () {
            TokenService::revoke('admin_tokens', TokenService::bearer($this->request));

            return null;
        });
    }
}
