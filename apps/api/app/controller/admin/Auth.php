<?php

declare(strict_types=1);

namespace app\controller\admin;

use app\controller\ApiController;
use app\service\AdminAuthService;
use app\service\TokenService;
use think\Response;

class Auth extends ApiController
{
    public function login(): Response
    {
        return $this->run(fn () => (new AdminAuthService())->login($this->payload()));
    }

    public function logout(): Response
    {
        return $this->run(function () {
            TokenService::revoke('admin_tokens', TokenService::bearer($this->request));

            return ['logged_out' => true];
        });
    }

    public function me(): Response
    {
        return $this->run(fn () => ['admin' => (new AdminAuthService())->present(app('sistermart.admin'))]);
    }
}
