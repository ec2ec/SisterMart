<?php

declare(strict_types=1);

namespace app\controller\api;

use app\controller\ApiController;
use app\service\MemberAuthService;
use think\Response;

class VerificationCodes extends ApiController
{
    public function store(): Response
    {
        return $this->run(fn () => (new MemberAuthService())->sendCode($this->payload()), 201);
    }
}
