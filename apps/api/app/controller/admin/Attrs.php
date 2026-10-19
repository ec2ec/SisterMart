<?php

declare(strict_types=1);

namespace app\controller\admin;

use app\controller\ApiController;
use app\service\CatalogMetaService;
use think\Response;

class Attrs extends ApiController
{
    public function index(): Response
    {
        $activeOnly = (string) $this->request->get('active_only', '') === '1';

        return $this->run(fn () => ['items' => (new CatalogMetaService())->listAttrs($activeOnly)]);
    }

    public function store(): Response
    {
        return $this->run(fn () => (new CatalogMetaService())->saveAttr($this->payload()), 201);
    }

    public function update(int $id): Response
    {
        return $this->run(fn () => (new CatalogMetaService())->saveAttr($this->payload(), $id));
    }

    public function storeValue(int $id): Response
    {
        return $this->run(fn () => (new CatalogMetaService())->saveAttrValue($id, $this->payload()), 201);
    }

    public function updateValue(int $id, int $valueId): Response
    {
        return $this->run(fn () => (new CatalogMetaService())->saveAttrValue($id, $this->payload(), $valueId));
    }

    public function destroyValue(int $id, int $valueId): Response
    {
        return $this->run(fn () => (new CatalogMetaService())->deleteAttrValue($id, $valueId));
    }
}
