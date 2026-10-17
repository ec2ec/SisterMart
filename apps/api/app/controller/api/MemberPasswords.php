<?php

declare(strict_types=1);

namespace app\controller\api;

use app\controller\ApiController;
use app\service\MemberAuthService;
use think\Response;

class MemberPasswords extends ApiController
{
    public function update(): Response
    {
        return $this->run(fn () => (new MemberAuthService())->reset($this->payload()));
    }
}
