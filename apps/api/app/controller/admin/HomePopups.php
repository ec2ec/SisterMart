<?php

declare(strict_types=1);

namespace app\controller\admin;

use app\controller\ApiController;
use app\service\HomePopupService;
use think\Response;

class HomePopups extends ApiController
{
    public function index(): Response
    {
        return $this->run(fn () => (new HomePopupService())->listAdmin());
    }

    public function store(): Response
    {
        return $this->run(fn () => (new HomePopupService())->save($this->payload()), 201);
    }

    public function update(int $id): Response
    {
        return $this->run(fn () => (new HomePopupService())->save($this->payload(), $id));
    }

    public function destroy(int $id): Response
    {
        return $this->run(fn () => (new HomePopupService())->delete($id));
    }
}
