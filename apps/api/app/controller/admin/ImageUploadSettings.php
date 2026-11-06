<?php

declare(strict_types=1);

namespace app\controller\admin;

use app\controller\ApiController;
use app\service\ImageUploadSceneService;
use app\service\UploadAssetService;
use app\support\ApiException;
use think\Response;

class ImageUploadSettings extends ApiController
{
    public function index(): Response
    {
        return $this->run(fn () => (new ImageUploadSceneService())->list());
    }

    public function update(): Response
    {
        return $this->run(fn () => (new ImageUploadSceneService())->save($this->payload()));
    }

    /** 新上傳的張數與體積。不含此功能上線前的舊圖。最近上傳支援分頁。 */
    public function assets(): Response
    {
        return $this->run(function () {
            $page = (int) $this->request->get('recent_page', 1);
            $pageSize = (int) $this->request->get('recent_page_size', 20);

            return (new UploadAssetService())->overview($page, $pageSize);
        });
    }

    public function scanAssets(): Response
    {
        return $this->run(fn () => (new UploadAssetService())->scanOrphans());
    }

    public function purgeAssets(): Response
    {
        return $this->run(function () {
            $confirm = $this->payload()['confirm'] ?? false;
            if ($confirm !== true && $confirm !== 1 && $confirm !== '1') {
                throw new ApiException('INVALID', '請確認後再刪除', 422, 42201);
            }

            return (new UploadAssetService())->purgeOrphans();
        });
    }

    /** 把仍被引用的舊目錄圖片原樣搬入場景目錄。不重新壓縮。 */
    public function migrateAssets(): Response
    {
        return $this->run(function () {
            $confirm = $this->payload()['confirm'] ?? false;
            if ($confirm !== true && $confirm !== 1 && $confirm !== '1') {
                throw new ApiException('INVALID', '請確認後再整理', 422, 42201);
            }

            return (new UploadAssetService())->migrateLegacy();
        });
    }

    /** 為已索引原圖補產 cover 縮略圖；payload.force=true 時覆蓋重刷。 */
    public function backfillThumbs(): Response
    {
        return $this->run(function () {
            $payload = $this->payload();
            $limit = (int) ($payload['limit'] ?? $this->request->get('limit', 80));
            $force = filter_var($payload['force'] ?? $this->request->get('force', false), FILTER_VALIDATE_BOOLEAN);
            $afterId = (int) ($payload['after_id'] ?? $this->request->get('after_id', 0));

            return (new UploadAssetService())->backfillThumbs($limit, $force, $afterId);
        });
    }
}
