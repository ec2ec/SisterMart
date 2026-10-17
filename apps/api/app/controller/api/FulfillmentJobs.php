<?php

declare(strict_types=1);

namespace app\controller\api;

use app\controller\ApiController;
use app\service\FulfillmentService;
use think\Response;

class FulfillmentJobs extends ApiController
{
    public function autoReceive(): Response
    {
        return $this->run(fn () => (new FulfillmentService())->autoReceive());
    }
}
