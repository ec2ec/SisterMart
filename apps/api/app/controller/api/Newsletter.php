<?php

declare(strict_types=1);

namespace app\controller\api;

use app\controller\ApiController;
use app\service\NewsletterService;
use app\service\TokenService;
use think\Response;

class Newsletter extends ApiController
{
    public function subscribe(): Response
    {
        return $this->run(function () {
            $memberId = null;
            $token = TokenService::bearer($this->request);
            $owner = TokenService::ownerId('member_tokens', 'member_id', $token);
            if ($owner) {
                $memberId = (int) $owner;
            }
            $body = $this->payload();
            $email = (string) ($body['email'] ?? '');
            if ($email === '' && $memberId) {
                $member = \think\facade\Db::name('members')->where('id', $memberId)->find();
                $email = (string) ($member['email'] ?? '');
            }

            return (new NewsletterService())->subscribe($email, $memberId);
        }, 201);
    }

    public function me(): Response
    {
        return $this->run(function () {
            $member = app('sistermart.member');

            return (new NewsletterService())->statusForMember($member);
        });
    }
}
