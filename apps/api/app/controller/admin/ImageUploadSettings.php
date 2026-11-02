<?php

declare(strict_types=1);

namespace app\controller\admin;

use app\controller\ApiController;
use app\service\ImageUploadSceneService;
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
}
