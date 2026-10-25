<?php

declare(strict_types=1);

namespace app\middleware;

use app\service\MemberActivityService;
use app\service\TokenService;
use Closure;
use think\Request;
use think\Response;

/**
 * 可選 Bearer：公開瀏覽接口有合法會員令牌時節流更新 last_active_at。
 * 無令牌／無效令牌不攔截，當作訪客。
 */
class MemberTouch
{
    public function handle(Request $request, Closure $next): Response
    {
        $id = TokenService::ownerId('member_tokens', 'member_id', TokenService::bearer($request));
        if ($id) {
            MemberActivityService::touch($id);
        }

        return $next($request);
    }
}
