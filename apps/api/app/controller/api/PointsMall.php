<?php

declare(strict_types=1);

namespace app\controller\api;

use app\controller\ApiController;
use app\service\PointsMallService;
use think\Response;

class PointsMall extends ApiController
{
    public function index(): Response
    {
        return $this->run(fn () => (new PointsMallService())->list());
    }

    public function read(string $id): Response
    {
        return $this->run(fn () => (new PointsMallService())->detail($id));
    }
}
