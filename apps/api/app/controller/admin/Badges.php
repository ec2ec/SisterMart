<?php

declare(strict_types=1);

namespace app\controller\admin;

use app\controller\ApiController;
use app\service\CatalogMetaService;
use think\Response;

class Badges extends ApiController
{
    public function index(): Response
    {
        $activeOnly = (string) $this->request->get('active_only', '') === '1';

        return $this->run(fn () => ['items' => (new CatalogMetaService())->listBadges($activeOnly)]);
    }

    public function store(): Response
    {
        return $this->run(fn () => (new CatalogMetaService())->saveBadge($this->payload()), 201);
    }

    public function update(int $id): Response
    {
        return $this->run(fn () => (new CatalogMetaService())->saveBadge($this->payload(), $id));
    }
}
