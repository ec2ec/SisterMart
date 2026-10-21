<?php

declare(strict_types=1);

namespace app\controller\admin;

use app\controller\ApiController;
use app\service\DashboardService;
use think\Response;

class Dashboard extends ApiController
{
    public function summary(): Response
    {
        return $this->run(fn () => (new DashboardService())->summary());
    }
}
