<?php

declare(strict_types=1);

namespace app\middleware;

use app\service\TokenService;
use app\support\ApiResponse;
use Closure;
use think\facade\Db;
use think\Request;
use think\Response;

class MemberAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $id = TokenService::ownerId('member_tokens', 'member_id', TokenService::bearer($request));
        $member = $id ? Db::name('members')->where('id', $id)->where('status', 1)->find() : null;
        if (!$member) {
            return ApiResponse::fail('UNAUTHORIZED', '請先登錄', 401);
        }
        app()->instance('sistermart.member', $member);

        return $next($request);
    }
}
