<?php

declare(strict_types=1);

namespace app\controller\api;

use app\controller\ApiController;
use app\service\HomeSectionService;
use think\Response;

class HomeSections extends ApiController
{
    public function index(): Response
    {
        return $this->run(fn () => (new HomeSectionService())->listPublic());
    }

    public function products(string $code): Response
    {
        return $this->run(fn () => (new HomeSectionService())->productsByCode($code));
    }
}
