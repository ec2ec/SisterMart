<?php

declare(strict_types=1);

namespace app\controller\api;

use app\controller\ApiController;
use app\service\MessageService;
use think\Response;

class Messages extends ApiController
{
    public function index(): Response
    {
        return $this->run(function () {
            $member = app('sistermart.member');
            $type = $this->request->get('type');

            return (new MessageService())->listForMember(
                (int) $member['id'],
                $type ? (string) $type : null,
            );
        });
    }

    public function unread(): Response
    {
        return $this->run(function () {
            $member = app('sistermart.member');

            return (new MessageService())->unreadCount((int) $member['id']);
        });
    }

    public function read(): Response
    {
        return $this->run(function () {
            $member = app('sistermart.member');

            return (new MessageService())->markRead((int) $member['id'], $this->payload());
        });
    }
}
