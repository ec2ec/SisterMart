<?php

declare(strict_types=1);

namespace app\controller\api;

use app\controller\ApiController;
use app\service\MockMarketingService;
use think\Response;

class Messages extends ApiController
{
    public function index(): Response
    {
        return $this->run(function () {
            $member = app('sistermart.member');
            $type = $this->request->get('type');

            return (new MockMarketingService())->messages((int) $member['id'], $type ? (string) $type : null);
        });
    }

    public function unread(): Response
    {
        return $this->run(function () {
            $member = app('sistermart.member');

            return (new MockMarketingService())->unreadCount((int) $member['id']);
        });
    }

    public function read(): Response
    {
        return $this->run(function () {
            $member = app('sistermart.member');
            $id = (string) ($this->payload()['id'] ?? $this->request->param('id', ''));
            if ($id === '') {
                throw new \app\support\ApiException('INVALID', '缺少消息 ID', 422, 42285);
            }

            return (new MockMarketingService())->markRead((int) $member['id'], $id);
        });
    }
}
