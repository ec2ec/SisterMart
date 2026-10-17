<?php

declare(strict_types=1);

namespace app\controller;

use app\BaseController;
use app\support\ApiResponse;
use think\facade\Db;
use think\Response;

class Health extends BaseController
{
    /**
     * 健康检查：进程可用 + MySQL 可连通。
     */
    public function index(): Response
    {
        $db = [
            'connected' => false,
            'driver'    => (string) env('DB_TYPE', 'mysql'),
            'host'      => (string) env('DB_HOST', '127.0.0.1'),
            'name'      => (string) env('DB_NAME', ''),
            'error'     => null,
        ];

        try {
            Db::query('SELECT 1 AS ok');
            $db['connected'] = true;
        } catch (\Throwable $e) {
            $db['error'] = $e->getMessage();
        }

        $data = [
            'service'   => 'sistermart-api',
            'milestone' => 'M2',
            'php'       => PHP_VERSION,
            'thinkphp'  => \think\facade\App::version(),
            'time'      => date('c'),
            'db'        => $db,
        ];

        if (!$db['connected']) {
            return ApiResponse::fail('DB_UNAVAILABLE', '數據庫不可用', 503, 50300, ['db' => $db]);
        }

        return ApiResponse::ok($data);
    }
}
