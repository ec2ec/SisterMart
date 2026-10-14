<?php

declare(strict_types=1);

namespace app\support;

use think\Response;

class ApiResponse
{
    public static function ok(array $data = [], int $status = 200): Response
    {
        return json(array_merge(['ok' => true], $data))->code($status);
    }

    public static function fail(string $code, string $message, int $status = 422): Response
    {
        return json([
            'ok'      => false,
            'code'    => $code,
            'message' => $message,
        ])->code($status);
    }
}
