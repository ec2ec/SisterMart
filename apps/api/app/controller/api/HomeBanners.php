<?php

declare(strict_types=1);

namespace app\controller\api;

use app\controller\ApiController;
use app\service\HomeBannerService;
use think\Response;

class HomeBanners extends ApiController
{
    public function index(): Response
    {
        return $this->run(fn () => (new HomeBannerService())->listPublic());
    }
}
