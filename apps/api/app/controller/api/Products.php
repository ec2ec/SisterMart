<?php

declare(strict_types=1);

namespace app\controller\api;

use app\controller\ApiController;
use app\service\BrowseHistoryService;
use app\service\CatalogService;
use app\service\TokenService;
use think\Response;

class Products extends ApiController
{
    public function index(): Response
    {
        return $this->run(fn () => (new CatalogService())->listProducts($this->request->get()));
    }

    public function read(string $id): Response
    {
        return $this->run(function () use ($id) {
            $viewerId = null;
            $token = TokenService::bearer($this->request);
            $owner = TokenService::ownerId('member_tokens', 'member_id', $token);
            if ($owner) {
                $viewerId = (int) $owner;
                // M26：登錄瀏覽詳情寫入足跡（失敗不影響詳情）
                try {
                    (new BrowseHistoryService())->touchByCode($viewerId, $id);
                } catch (\Throwable) {
                    // ignore
                }
            }

            return (new CatalogService())->productDetail($id, false, $viewerId);
        });
    }
}
