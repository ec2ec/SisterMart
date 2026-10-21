<?php

declare(strict_types=1);

namespace app\controller\api;

use app\controller\ApiController;
use app\service\MaintenanceService;
use think\Response;

class ShopStatus extends ApiController
{
    public function index(): Response
    {
        return $this->run(fn () => (new MaintenanceService())->publicStatus());
    }
}
