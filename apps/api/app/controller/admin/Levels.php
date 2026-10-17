<?php

declare(strict_types=1);

namespace app\controller\admin;

use app\controller\ApiController;
use app\service\AssetService;
use think\Response;

class Levels extends ApiController
{
    public function index(): Response
    {
        return $this->run(fn () => (new AssetService())->listLevels());
    }

    public function update(int $id): Response
    {
        return $this->run(fn () => (new AssetService())->updateLevel($id, $this->payload()));
    }
}
