<?php

declare(strict_types=1);

namespace app\controller\admin;

use app\controller\ApiController;
use app\service\CatalogMetaService;
use think\Response;

class SpecTemplates extends ApiController
{
    public function index(): Response
    {
        $activeOnly = (string) $this->request->get('active_only', '') === '1';

        return $this->run(fn () => ['items' => (new CatalogMetaService())->listSpecTemplates($activeOnly)]);
    }

    public function store(): Response
    {
        return $this->run(fn () => (new CatalogMetaService())->saveSpecTemplate($this->payload()), 201);
    }

    public function update(int $id): Response
    {
        return $this->run(fn () => (new CatalogMetaService())->saveSpecTemplate($this->payload(), $id));
    }

    public function destroy(int $id): Response
    {
        return $this->run(fn () => (new CatalogMetaService())->deleteSpecTemplate($id));
    }
}
