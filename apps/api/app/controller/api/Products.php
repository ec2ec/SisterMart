<?php

declare(strict_types=1);

namespace app\controller\api;

use app\controller\ApiController;
use app\service\CatalogService;
use think\Response;

class Products extends ApiController
{
    public function index(): Response
    {
        return $this->run(fn () => (new CatalogService())->listProducts($this->request->get()));
    }

    public function read(string $id): Response
    {
        return $this->run(fn () => (new CatalogService())->productDetail($id));
    }
}
