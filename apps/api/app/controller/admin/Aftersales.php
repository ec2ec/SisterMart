<?php

declare(strict_types=1);

namespace app\controller\admin;

use app\controller\ApiController;
use app\service\AftersaleService;
use think\Response;

class Aftersales extends ApiController
{
    public function index(): Response
    {
        $status = $this->request->get('status');

        return $this->run(fn () => (new AftersaleService())->listAdmin($status ?: null));
    }

    public function read(int $id): Response
    {
        return $this->run(fn () => (new AftersaleService())->detail($id));
    }

    public function review(int $id): Response
    {
        return $this->run(fn () => (new AftersaleService())->review($id, $this->payload()));
    }

    public function markReturned(int $id): Response
    {
        return $this->run(fn () => (new AftersaleService())->markReturned($id));
    }

    public function forceBalance(int $id): Response
    {
        return $this->run(fn () => (new AftersaleService())->executeRefund($id, (string) ($this->payload()['admin_note'] ?? ''), true));
    }
}
