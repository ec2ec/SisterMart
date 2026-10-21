<?php

declare(strict_types=1);

namespace app\controller\api;

use app\controller\ApiController;
use app\service\CartTrustTagService;
use think\Response;

class CartTrustTags extends ApiController
{
    public function index(): Response
    {
        return $this->run(fn () => (new CartTrustTagService())->listPublic());
    }
}
