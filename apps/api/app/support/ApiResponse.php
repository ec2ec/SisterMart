<?php

declare(strict_types=1);

namespace app\support;

use think\Response;

class ApiResponse
{
    /**
     * @param array<string, mixed>|list<mixed>|null $data
     */
    public static function ok(array|null $data = [], int $status = 200, string $message = 'ok'): Response
    {
        return json([
            'code'    => 0,
            'message' => $message,
            'data'    => $data,
        ])->code($status);
    }

    /**
     * @param array<string, mixed> $details
     */
    public static function fail(
        string $errorName,
        string $message,
        int $status = 422,
        int $businessCode = 0,
        array $details = [],
    ): Response {
        if ($businessCode === 0) {
            $businessCode = self::defaultBusinessCode($status);
        }

        $body = [
            'code'    => $businessCode,
            'message' => $message,
            'data'    => null,
            'error'   => [
                'name'    => $errorName,
                'details' => (object) $details,
            ],
        ];

        return json($body)->code($status);
    }

    public static function fromException(ApiException $e): Response
    {
        return self::fail($e->errorName, $e->getMessage(), $e->status, $e->businessCode, $e->details);
    }

    private static function defaultBusinessCode(int $status): int
    {
        return match ($status) {
            400 => 40000,
            401 => 40100,
            403 => 40300,
            404 => 40400,
            429 => 42900,
            500, 503 => 50000,
            default => 42200,
        };
    }
}
