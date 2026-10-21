<?php

declare(strict_types=1);

namespace app\controller\api;

use app\controller\ApiController;
use app\service\ShopSettingsService;
use think\Response;

/** 公開：客服聯絡方式（H5 聯繫客服 / 售後 WhatsApp） */
class ShopContact extends ApiController
{
    public function index(): Response
    {
        return $this->run(fn () => (new ShopSettingsService())->publicContact());
    }
}
