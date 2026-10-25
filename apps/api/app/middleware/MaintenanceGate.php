<?php

declare(strict_types=1);

namespace app\middleware;

use app\service\MaintenanceService;
use app\service\TokenService;
use app\support\ApiResponse;
use Closure;
use think\Request;
use think\Response;

/**
 * 維護模式：H5 業務接口統一回 MAINTENANCE；後台、路徑白名單、用戶白名單放行。
 */
class MaintenanceGate
{
    public function handle(Request $request, Closure $next): Response
    {
        if (strtoupper($request->method(true)) === 'OPTIONS') {
            return $next($request);
        }

        $svc = new MaintenanceService();
        if (!$svc->isEnabled()) {
            return $next($request);
        }

        $path = '/' . ltrim((string) $request->pathinfo(), '/');
        if ($svc->isPathAllowed($path)) {
            return $next($request);
        }

        $memberId = TokenService::ownerId('member_tokens', 'member_id', TokenService::bearer($request));
        if ($memberId !== null && $svc->isMemberAllowed($memberId)) {
            return $next($request);
        }

        $public = $svc->publicStatus();

        return ApiResponse::fail(
            'MAINTENANCE',
            '商店維護中',
            503,
            50301,
            [
                'enabled' => true,
                'member_bypass' => false,
                'title' => $public['title'],
                'message' => $public['message'],
                'starts_at' => $public['starts_at'],
                'ends_at' => $public['ends_at'],
            ],
        );
    }
}
