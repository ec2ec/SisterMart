<?php

declare(strict_types=1);

namespace app\controller\api;

use app\controller\ApiController;
use app\service\MockMarketingService;
use app\service\TokenService;
use think\Response;

class ShareViews extends ApiController
{
    public function store(): Response
    {
        return $this->run(function () {
            $viewerId = null;
            $token = TokenService::bearer($this->request);
            $id = TokenService::ownerId('member_tokens', 'member_id', $token);
            if ($id) {
                $viewerId = (int) $id;
            }
            $body = $this->payload();
            $body['ip'] = $this->request->ip();

            return (new MockMarketingService())->shareView($body, $viewerId);
        }, 201);
    }
}
