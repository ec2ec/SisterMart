<?php

declare(strict_types=1);

namespace app\controller\admin;

use app\controller\ApiController;
use app\service\HomeSectionService;
use think\Response;

class HomeSections extends ApiController
{
    public function index(): Response
    {
        return $this->run(fn () => (new HomeSectionService())->listAdmin());
    }

    public function store(): Response
    {
        return $this->run(fn () => (new HomeSectionService())->save($this->payload()), 201);
    }

    public function update(string $id): Response
    {
        return $this->run(fn () => (new HomeSectionService())->save($this->payload(), (int) $id));
    }

    public function destroy(string $id): Response
    {
        return $this->run(fn () => (new HomeSectionService())->delete((int) $id));
    }

    public function products(string $id): Response
    {
        return $this->run(fn () => (new HomeSectionService())->setProducts((int) $id, $this->payload()));
    }
}
