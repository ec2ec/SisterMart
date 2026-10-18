<?php

declare(strict_types=1);

namespace app\controller\admin;

use app\controller\ApiController;
use app\service\PointsMallService;
use think\Response;

class PointsMall extends ApiController
{
    public function index(): Response
    {
        return $this->run(fn () => (new PointsMallService())->listAdmin());
    }

    public function store(): Response
    {
        return $this->run(fn () => (new PointsMallService())->saveAdmin($this->payload()), 201);
    }

    public function update(string $id): Response
    {
        return $this->run(fn () => (new PointsMallService())->saveAdmin($this->payload(), (int) $id));
    }

    public function destroy(string $id): Response
    {
        return $this->run(fn () => (new PointsMallService())->deleteAdmin((int) $id));
    }
}
