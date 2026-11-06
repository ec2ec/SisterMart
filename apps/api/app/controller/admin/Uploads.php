<?php

declare(strict_types=1);

namespace app\controller\admin;

use app\controller\ApiController;
use app\service\ImageUploadSceneService;
use app\service\ImageUploadService;
use app\support\ApiException;
use think\Response;

/**
 * 後台通用圖片上傳。壓縮只讀「圖片上傳配置」，請求不能覆蓋。
 *
 * POST /api/v1/admin/uploads/image
 * multipart: file
 * 字段：scene=catalog.product|marketing.banner|...（或舊 preset）
 */
class Uploads extends ApiController
{
    public function image(): Response
    {
        return $this->run(function () {
            $file = $this->request->file('file');
            if (!$file) {
                throw new ApiException('INVALID', '請選擇圖片', 422, 42201);
            }
            $admin = app('sistermart.admin') ?? [];
            $adminId = (int) ($admin['id'] ?? 0);
            $input = array_merge($this->request->get(), $this->request->post(), $this->payload());
            $opts = [
                'owner_id' => $adminId > 0 ? 'draft-' . $adminId : 'shared',
            ];
            $scene = trim((string) ($input['scene'] ?? ''));
            if ($scene !== '') {
                $opts['scene'] = $scene;
            } else {
                $opts['preset'] = (string) ($input['preset'] ?? 'admin');
            }
            // 公共圖庫用 shared 目錄，避免 draft claim 搬移。
            if ($scene === 'library.public') {
                $opts['owner_id'] = 'shared';
            }
            if (isset($input['tags'])) {
                $opts['tags'] = $input['tags'];
            }

            return (new ImageUploadService())->store($file, $opts);
        }, 201);
    }

    /** GET /api/v1/admin/uploads/requirements 各場景當前上傳限制（只讀） */
    public function requirements(): Response
    {
        return $this->run(fn () => (new ImageUploadSceneService())->list());
    }

    /** GET /api/v1/admin/uploads/library?scene=&source=scene|public&tag=&page=&page_size= */
    public function library(): Response
    {
        return $this->run(function () {
            $scene = trim((string) $this->request->get('scene', ''));
            $source = trim((string) $this->request->get('source', 'scene'));
            $tag = trim((string) $this->request->get('tag', ''));
            $page = (int) $this->request->get('page', 1);
            $pageSize = (int) $this->request->get('page_size', 24);

            return (new ImageUploadSceneService())->library($scene, $page, $pageSize, $source, $tag);
        });
    }

    /** GET /api/v1/admin/uploads/public-library?tag=&status=&page=&page_size= */
    public function publicLibrary(): Response
    {
        return $this->run(function () {
            $tag = trim((string) $this->request->get('tag', ''));
            $status = trim((string) $this->request->get('status', 'all'));
            $page = (int) $this->request->get('page', 1);
            $pageSize = (int) $this->request->get('page_size', 24);

            return (new \app\service\UploadAssetService())->publicLibrary($page, $pageSize, $tag, $status);
        });
    }

    /** PUT /api/v1/admin/uploads/public-library/:id */
    public function updatePublicLibrary(int|string $id): Response
    {
        return $this->run(function () use ($id) {
            return (new \app\service\UploadAssetService())->updatePublicLibraryItem((int) $id, $this->payload());
        });
    }
}
