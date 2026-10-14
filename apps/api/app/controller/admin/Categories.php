<?php

declare(strict_types=1);

namespace app\controller\admin;

use app\controller\ApiController;
use app\service\CatalogService;
use think\Response;

class Categories extends ApiController
{
    public function index(): Response
    {
        return $this->run(fn () => ['items' => (new CatalogService())->categoryTree(false)]);
    }

    public function store(): Response
    {
        return $this->run(fn () => (new CatalogService())->saveCategory($this->payload()), 201);
    }

    public function update(int $id): Response
    {
        return $this->run(fn () => (new CatalogService())->saveCategory($this->payload(), $id));
    }
}
