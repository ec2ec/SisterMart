<?php

declare(strict_types=1);

namespace app\controller\admin;

use app\controller\ApiController;
use app\service\ReleaseService;
use app\support\ApiException;
use think\Response;

/**
 * 窄版發佈通道（僅超管）。
 *
 * POST   /api/v1/admin/releases
 * PUT    /api/v1/admin/releases/:id/chunks/:n
 * POST   /api/v1/admin/releases/:id/complete
 * GET    /api/v1/admin/releases
 * GET    /api/v1/admin/releases/state
 * GET    /api/v1/admin/releases/audit
 * GET    /api/v1/admin/releases/:id
 * POST   /api/v1/admin/releases/:id/publish
 * DELETE /api/v1/admin/releases/:id
 * POST   /api/v1/admin/releases/rollback
 */
class Releases extends ApiController
{
    private function svc(): ReleaseService
    {
        return new ReleaseService();
    }

    private function adminId(): int
    {
        $admin = app('sistermart.admin');

        return (int) ($admin['id'] ?? 0);
    }

    private function guard(): ReleaseService
    {
        $svc = $this->svc();
        $svc->assertSuper(app('sistermart.admin'));
        $svc->assertEnabled();

        return $svc;
    }

    public function state(): Response
    {
        return $this->run(function () {
            $svc = $this->guard();

            return $svc->state();
        });
    }

    public function audit(): Response
    {
        return $this->run(function () {
            $svc = $this->guard();
            $limit = (int) ($this->request->get('limit') ?: 50);

            return ['items' => $svc->auditTail($limit)];
        });
    }

    public function index(): Response
    {
        return $this->run(function () {
            return $this->guard()->list();
        });
    }

    public function read(string $id): Response
    {
        return $this->run(function () use ($id) {
            return $this->guard()->read($id);
        });
    }

    public function store(): Response
    {
        return $this->run(function () {
            return $this->guard()->initUpload($this->payload(), $this->adminId());
        }, 201);
    }

    public function chunk(string $id, string $n): Response
    {
        return $this->run(function () use ($id, $n) {
            $svc = $this->guard();
            $bytes = (string) $this->request->getContent();
            // 兼容 multipart: file 字段
            if ($bytes === '') {
                $file = $this->request->file('chunk') ?: $this->request->file('file');
                if ($file) {
                    $path = method_exists($file, 'getRealPath') ? (string) $file->getRealPath() : '';
                    if ($path === '' || !is_file($path)) {
                        throw new ApiException('INVALID', '無法讀取分片', 422, 42201);
                    }
                    $bytes = (string) file_get_contents($path);
                }
            }
            if ($bytes === '') {
                throw new ApiException('INVALID', '分片內容為空', 422, 42201);
            }

            return $svc->putChunk($id, (int) $n, $bytes, $this->adminId());
        });
    }

    public function complete(string $id): Response
    {
        return $this->run(function () use ($id) {
            return $this->guard()->complete($id, $this->adminId());
        });
    }

    public function publish(string $id): Response
    {
        return $this->run(function () use ($id) {
            return $this->guard()->publish($id, $this->adminId());
        });
    }

    public function destroy(string $id): Response
    {
        return $this->run(function () use ($id) {
            return $this->guard()->destroy($id, $this->adminId());
        });
    }

    public function rollback(): Response
    {
        return $this->run(function () {
            return $this->guard()->rollback($this->payload(), $this->adminId());
        });
    }
}
