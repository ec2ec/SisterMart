<?php

declare(strict_types=1);

namespace app\controller\admin;

use app\controller\ApiController;
use app\service\AssetService;
use think\Response;

class Points extends ApiController
{
    public function settings(): Response
    {
        return $this->run(fn () => (new AssetService())->pointsSettings());
    }

    public function updateSettings(): Response
    {
        return $this->run(fn () => (new AssetService())->pointsSettings($this->payload()));
    }
}
