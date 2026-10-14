<?php

declare(strict_types=1);

namespace app\controller\api;

use app\controller\ApiController;
use app\service\CatalogService;
use think\Response;

class Categories extends ApiController
{
    public function index(): Response
    {
        return $this->run(fn () => ['items' => (new CatalogService())->categoryTree(true)]);
    }
}
