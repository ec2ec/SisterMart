<?php

declare(strict_types=1);

namespace app\controller\admin;

use app\controller\ApiController;
use app\service\CatalogService;
use think\Response;

class Products extends ApiController
{
    public function index(): Response
    {
        return $this->run(fn () => (new CatalogService())->listProducts($this->request->get(), true));
    }

    public function read(string $id): Response
    {
        return $this->run(fn () => (new CatalogService())->productDetail($id, true));
    }

    public function store(): Response
    {
        return $this->run(fn () => (new CatalogService())->saveProduct($this->payload()), 201);
    }

    public function update(int $id): Response
    {
        return $this->run(fn () => (new CatalogService())->saveProduct($this->payload(), $id));
    }

}
