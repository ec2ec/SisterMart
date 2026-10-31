<?php

declare(strict_types=1);

namespace app\controller\api;

use app\controller\ApiController;
use app\service\HomeLayoutService;
use think\Response;

class HomeLayout extends ApiController
{
    public function index(): Response
    {
        return $this->run(fn () => (new HomeLayoutService())->listPublic());
    }
}
