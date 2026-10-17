<?php

declare(strict_types=1);

namespace app\controller\admin;

use app\controller\ApiController;
use app\service\CatalogService;
use app\service\ShopSettingsService;
use think\Response;

class ShopSettings extends ApiController
{
    public function soldDisplay(): Response
    {
        return $this->run(fn () => (new CatalogService())->soldMode());
    }

    public function updateSoldDisplay(): Response
    {
        $mode = (string) ($this->payload()['sold_display_mode'] ?? '');

        return $this->run(fn () => (new CatalogService())->soldMode($mode));
    }

    public function shop(): Response
    {
        return $this->run(fn () => (new ShopSettingsService())->aftersaleAndCs());
    }

    public function updateShop(): Response
    {
        return $this->run(fn () => (new ShopSettingsService())->aftersaleAndCs($this->payload()));
    }
}
