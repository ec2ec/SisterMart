<?php

declare(strict_types=1);

namespace app\controller\admin;

use app\controller\ApiController;
use app\service\TabbarService;
use think\Response;

class Tabbar extends ApiController
{
    public function index(): Response
    {
        return $this->run(fn () => (new TabbarService())->read());
    }

    public function update(): Response
    {
        return $this->run(fn () => (new TabbarService())->save($this->payload()));
    }
}
