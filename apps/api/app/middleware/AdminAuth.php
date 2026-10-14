<?php

declare(strict_types=1);

namespace app\middleware;

use app\service\TokenService;
use app\support\ApiResponse;
use Closure;
use think\facade\Db;
use think\Request;
use think\Response;

class AdminAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $id = TokenService::ownerId('admin_tokens', 'admin_id', TokenService::bearer($request));
        $admin = $id ? Db::name('admins')->where('id', $id)->where('status', 1)->find() : null;
        if (!$admin) {
            return ApiResponse::fail('UNAUTHORIZED', '請先登錄', 401);
        }
        app()->instance('sistermart.admin', $admin);

        return $next($request);
    }
}
