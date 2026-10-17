<?php

declare(strict_types=1);

namespace app\controller\admin;

use app\controller\ApiController;
use app\service\AdminAcl;
use app\service\AdminAuthService;
use app\support\ApiException;
use think\facade\Db;
use think\Response;

class Admins extends ApiController
{
    public function me(): Response
    {
        return $this->run(function () {
            $admin = app('sistermart.admin');
            $data = (new AdminAuthService())->present($admin);
            $data['permissions'] = AdminAcl::permissionsForRole($data['role']);

            return $data;
        });
    }

    public function index(): Response
    {
        return $this->run(function () {
            AdminAcl::assert(app('sistermart.admin'), AdminAcl::PERM_ADMINS);
            $rows = Db::name('admins')->alias('a')
                ->leftJoin('admin_roles r', 'r.id = a.role_id')
                ->field('a.id,a.email,a.name,a.role_id,a.status,a.created_at,r.code as role_code,r.name_zh as role_name')
                ->order('a.id', 'asc')
                ->select()->toArray();

            return [
                'items' => array_map(fn ($r) => [
                    'id' => (int) $r['id'],
                    'email' => $r['email'],
                    'name' => $r['name'],
                    'role' => $r['role_code'],
                    'role_name' => $r['role_name'],
                    'status' => (int) $r['status'],
                    'created_at' => $r['created_at'],
                ], $rows),
                'roles' => Db::name('admin_roles')->order('id', 'asc')->select()->toArray(),
            ];
        });
    }

    public function store(): Response
    {
        return $this->run(function () {
            AdminAcl::assert(app('sistermart.admin'), AdminAcl::PERM_ADMINS);
            $body = $this->payload();
            $email = strtolower(trim((string) ($body['email'] ?? '')));
            $name = trim((string) ($body['name'] ?? ''));
            $password = (string) ($body['password'] ?? '');
            $roleCode = (string) ($body['role'] ?? 'ops');
            if ($email === '' || $name === '' || strlen($password) < 8) {
                throw new ApiException('INVALID', '請填寫郵箱、姓名與至少 8 位密碼', 422, 42290);
            }
            if (Db::name('admins')->where('email', $email)->find()) {
                throw new ApiException('EMAIL_TAKEN', '管理員郵箱已存在', 422, 42291);
            }
            $role = Db::name('admin_roles')->where('code', $roleCode)->find();
            if (!$role) {
                throw new ApiException('INVALID', '角色不正確', 422, 42292);
            }
            $now = date('Y-m-d H:i:s');
            $id = (int) Db::name('admins')->insertGetId([
                'email' => $email,
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                'name' => $name,
                'role_id' => (int) $role['id'],
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            return ['id' => $id, 'email' => $email, 'role' => $roleCode];
        }, 201);
    }

    public function update(int $id): Response
    {
        return $this->run(function () use ($id) {
            AdminAcl::assert(app('sistermart.admin'), AdminAcl::PERM_ADMINS);
            $row = Db::name('admins')->where('id', $id)->find();
            if (!$row) {
                throw new ApiException('NOT_FOUND', '管理員不存在', 404, 40490);
            }
            $body = $this->payload();
            $data = ['updated_at' => date('Y-m-d H:i:s')];
            if (isset($body['name'])) {
                $data['name'] = trim((string) $body['name']);
            }
            if (isset($body['status'])) {
                $data['status'] = (int) $body['status'];
            }
            if (!empty($body['password'])) {
                if (strlen((string) $body['password']) < 8) {
                    throw new ApiException('INVALID', '密碼至少 8 位', 422, 42290);
                }
                $data['password_hash'] = password_hash((string) $body['password'], PASSWORD_DEFAULT);
            }
            if (isset($body['role'])) {
                $role = Db::name('admin_roles')->where('code', (string) $body['role'])->find();
                if (!$role) {
                    throw new ApiException('INVALID', '角色不正確', 422, 42292);
                }
                $data['role_id'] = (int) $role['id'];
            }
            Db::name('admins')->where('id', $id)->update($data);

            return (new AdminAuthService())->present(Db::name('admins')->where('id', $id)->find());
        });
    }
}
