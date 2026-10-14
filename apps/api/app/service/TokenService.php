<?php

declare(strict_types=1);

namespace app\service;

use think\facade\Db;

class TokenService
{
    public static function issue(string $table, string $ownerColumn, int $ownerId): string
    {
        $plain = bin2hex(random_bytes(32));
        $now = date('Y-m-d H:i:s');
        Db::name($table)->insert([
            $ownerColumn => $ownerId,
            'token_hash' => hash('sha256', $plain),
            'expires_at' => date('Y-m-d H:i:s', time() + 30 * 86400),
            'created_at' => $now,
        ]);

        return $plain;
    }

    public static function ownerId(string $table, string $ownerColumn, string $plain): ?int
    {
        if ($plain === '') {
            return null;
        }
        $row = Db::name($table)->where('token_hash', hash('sha256', $plain))->find();
        if (!$row) {
            return null;
        }
        if (strtotime((string) $row['expires_at']) < time()) {
            Db::name($table)->where('id', $row['id'])->delete();

            return null;
        }

        return (int) $row[$ownerColumn];
    }

    public static function revoke(string $table, string $plain): void
    {
        if ($plain === '') {
            return;
        }
        Db::name($table)->where('token_hash', hash('sha256', $plain))->delete();
    }

    public static function bearer(\think\Request $request): string
    {
        $header = (string) $request->header('authorization', '');
        if (preg_match('/^Bearer\s+(\S+)$/i', $header, $matches)) {
            return $matches[1];
        }

        return '';
    }
}
