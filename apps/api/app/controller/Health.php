<?php

declare(strict_types=1);

namespace app\controller;

use app\BaseController;
use think\facade\Db;
use think\Response;

class Health extends BaseController
{
    /**
     * M1 健康检查：进程可用 + MySQL 可连通（按当前 .env）。
     */
    public function index(): Response
    {
        $db = [
            'ok'     => false,
            'driver' => (string) env('DB_TYPE', 'mysql'),
            'host'   => (string) env('DB_HOST', '127.0.0.1'),
            'name'   => (string) env('DB_NAME', ''),
            'error'  => null,
        ];

        try {
            Db::query('SELECT 1 AS ok');
            $db['ok'] = true;
        } catch (\Throwable $e) {
            $db['error'] = $e->getMessage();
        }

        $payload = [
            'ok'        => $db['ok'],
            'service'   => 'sistermart-api',
            'milestone' => 'M1',
            'php'       => PHP_VERSION,
            'thinkphp'  => \think\facade\App::version(),
            'time'      => date('c'),
            'db'        => $db,
        ];

        return json($payload)->code($db['ok'] ? 200 : 503);
    }
}
