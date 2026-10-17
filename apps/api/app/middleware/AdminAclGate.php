<?php

declare(strict_types=1);

namespace app\middleware;

use app\service\AdminAcl;
use app\support\ApiResponse;
use Closure;
use think\Request;
use think\Response;

class AdminAclGate
{
    public function handle(Request $request, Closure $next): Response
    {
        $admin = null;
        try {
            $admin = app('sistermart.admin');
        } catch (\Throwable) {
            return ApiResponse::fail('UNAUTHORIZED', '請先登錄', 401, 40100);
        }

        $path = (string) (parse_url($request->url(true), PHP_URL_PATH) ?: $request->pathinfo());
        if ($path !== '' && $path[0] !== '/') {
            $path = '/' . $path;
        }
        $perm = AdminAcl::permissionForPath($path);
        if ($perm !== null && !AdminAcl::can(is_array($admin) ? $admin : null, $perm)) {
            return ApiResponse::fail('FORBIDDEN', '權限不足', 403, 40301);
        }

        return $next($request);
    }
}
