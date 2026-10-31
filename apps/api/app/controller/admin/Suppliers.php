<?php

declare(strict_types=1);

namespace app\controller\admin;

use app\controller\ApiController;
use app\service\SupplierService;
use think\Response;

class Suppliers extends ApiController
{
    public function index(): Response
    {
        $activeOnly = (string) $this->request->get('active_only', '') === '1';

        return $this->run(function () use ($activeOnly) {
            $svc = new SupplierService();

            return [
                'items' => $svc->list($activeOnly),
                'types' => $svc->typeLabels(),
            ];
        });
    }

    public function read(int $id): Response
    {
        return $this->run(fn () => (new SupplierService())->get($id));
    }

    public function store(): Response
    {
        return $this->run(fn () => (new SupplierService())->save($this->payload()), 201);
    }

    public function update(int $id): Response
    {
        return $this->run(fn () => (new SupplierService())->save($this->payload(), $id));
    }
}
