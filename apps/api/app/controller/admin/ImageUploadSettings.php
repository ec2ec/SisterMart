<?php

declare(strict_types=1);

namespace app\controller\admin;

use app\controller\ApiController;
use app\service\ImageUploadSceneService;
use app\service\UploadAssetService;
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
}
