<?php

declare(strict_types=1);

namespace app\controller\api;

use app\controller\ApiController;
use app\service\HomePopupService;
use app\service\TokenService;
use think\Response;

class HomePopups extends ApiController
{
    public function index(): Response
    {
        return $this->run(function () {
            $memberId = null;
            $token = TokenService::bearer($this->request);
            $id = TokenService::ownerId('member_tokens', 'member_id', $token);
            if ($id) {
                $memberId = (int) $id;
            }

            return (new HomePopupService())->listPublic($memberId);
        });
    }

    public function dismiss(int $id): Response
    {
        return $this->run(function () use ($id) {
            $member = app('sistermart.member');

            return (new HomePopupService())->dismiss((int) $member['id'], $id);
        });
    }
}
