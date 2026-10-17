<?php

declare(strict_types=1);

namespace app\controller\api;

use app\controller\ApiController;
use app\service\MockMarketingService;
use think\Response;

class PointsMall extends ApiController
{
    public function index(): Response
    {
        return $this->run(fn () => (new MockMarketingService())->pointsMall());
    }

    public function read(string $id): Response
    {
        return $this->run(fn () => (new MockMarketingService())->pointsMallDetail($id));
    }
}
