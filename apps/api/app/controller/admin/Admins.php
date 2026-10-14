<?php

declare(strict_types=1);

namespace app\controller\admin;

use app\controller\ApiController;
use app\service\AdminAuthService;
use think\Response;

class Admins extends ApiController
{
    public function me(): Response
    {
        return $this->run(fn () => (new AdminAuthService())->present(app('sistermart.admin')));
    }
}
