<?php

declare(strict_types=1);

namespace app\middleware;

use Closure;
use think\Request;
use think\Response;

/**
 * M1 开发期 CORS：允许本地 H5/Admin 跨端口访问 API。
 */
class Cors
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->method(true) === 'OPTIONS') {
            $response = Response::create('', 'html', 204);
        } else {
            $response = $next($request);
        }

        $origin = $request->header('origin', '*');

        $response->header([
            'Access-Control-Allow-Origin'      => $origin ?: '*',
            'Access-Control-Allow-Methods'     => 'GET, POST, PUT, PATCH, DELETE, OPTIONS',
            'Access-Control-Allow-Headers'     => 'Authorization, Content-Type, X-Requested-With, Accept, Origin',
            'Access-Control-Allow-Credentials' => 'true',
            'Access-Control-Max-Age'           => '86400',
            // 禁止搜索引擎／爬蟲索引 API 響應
            'X-Robots-Tag'                     => 'noindex, nofollow, noarchive, nosnippet, noimageindex',
        ]);

        return $response;
    }
}
