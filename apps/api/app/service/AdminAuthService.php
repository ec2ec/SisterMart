<?php

declare(strict_types=1);

namespace app\service;

use app\support\ApiException;
use think\facade\Db;

class AdminAuthService
{
    public function login(array $body): array
    {
        $email = strtolower(trim((string) ($body['email'] ?? '')));
        $admin = Db::name('admins')->where('email', $email)->find();
        if (!$admin || !password_verify((string) ($body['password'] ?? ''), (string) $admin['password_hash'])) {
            throw new ApiException('BAD_PASSWORD', '帳號或密碼不正確', 401);
        }
        if ((int) $admin['status'] !== 1) {
            throw new ApiException('DISABLED', '這個帳號已停用', 403);
        }

        return [
            'token' => TokenService::issue('admin_tokens', 'admin_id', (int) $admin['id']),
            'admin' => $this->present($admin),
        ];
    }

    public function present(?array $admin): array
    {
        if (!$admin) {
            throw new ApiException('UNAUTHORIZED', '請先登錄', 401);
        }
        $role = Db::name('admin_roles')->where('id', $admin['role_id'])->find();
        $code = (string) ($role['code'] ?? 'ops');

        return [
            'id'    => (int) $admin['id'],
            'email' => (string) $admin['email'],
            'name'  => (string) $admin['name'],
            'role'  => $code,
            'permissions' => AdminAcl::permissionsForRole($code),
        ];
    }
}
