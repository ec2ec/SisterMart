<?php

declare(strict_types=1);

namespace app\controller\api;

use app\controller\ApiController;
use app\service\HomeOpsService;
use think\Response;

class HomeOps extends ApiController
{
    public function index(): Response
    {
        return $this->run(fn () => (new HomeOpsService())->publicBundle());
    }
}
