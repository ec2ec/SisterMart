<?php

declare(strict_types=1);

namespace app\controller\admin;

use app\controller\ApiController;
use app\service\CartTrustTagService;
use think\Response;

class CartTrustTags extends ApiController
{
    public function index(): Response
    {
        return $this->run(fn () => (new CartTrustTagService())->listAdmin());
    }

    public function store(): Response
    {
        return $this->run(fn () => (new CartTrustTagService())->saveAdmin($this->payload()), 201);
    }

    public function update(int $id): Response
    {
        return $this->run(fn () => (new CartTrustTagService())->saveAdmin($this->payload(), $id));
    }

    public function destroy(int $id): Response
    {
        return $this->run(fn () => (new CartTrustTagService())->deleteAdmin($id));
    }
}
