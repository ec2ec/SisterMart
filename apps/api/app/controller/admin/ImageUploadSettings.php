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

    /** 新上傳的張數與體積。不含此功能上線前的舊圖。 */
    public function assets(): Response
    {
        return $this->run(fn () => (new UploadAssetService())->overview());
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

    /** 為缺少縮略圖的已索引原圖補產 64×64 cover。 */
    public function backfillThumbs(): Response
    {
        return $this->run(function () {
            $limit = (int) ($this->payload()['limit'] ?? $this->request->get('limit', 80));

            return (new UploadAssetService())->backfillThumbs($limit);
        });
    }
}
