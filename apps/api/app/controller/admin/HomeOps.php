<?php

declare(strict_types=1);

namespace app\controller\admin;

use app\controller\ApiController;
use app\service\HomeOpsService;
use think\Response;

class HomeOps extends ApiController
{
    public function read(): Response
    {
        return $this->run(fn () => (new HomeOpsService())->adminRead());
    }

    public function update(): Response
    {
        return $this->run(fn () => (new HomeOpsService())->adminUpdate($this->payload()));
    }
}
