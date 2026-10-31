<?php

declare(strict_types=1);

namespace app\controller\admin;

use app\controller\ApiController;
use app\service\HomeLayoutService;
use think\Response;

class HomeLayout extends ApiController
{
    public function index(): Response
    {
        return $this->run(fn () => (new HomeLayoutService())->listAdmin());
    }

    public function reorder(): Response
    {
        $body = $this->payload();
        $modules = $body['modules'] ?? $body['ids'] ?? [];
        if (!is_array($modules)) {
            $modules = [];
        }

        return $this->run(fn () => (new HomeLayoutService())->reorder($modules));
    }

    public function update(string $id): Response
    {
        return $this->run(fn () => (new HomeLayoutService())->updateModule((int) $id, $this->payload()));
    }
}
