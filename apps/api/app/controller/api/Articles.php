<?php

declare(strict_types=1);

namespace app\controller\api;

use app\controller\ApiController;
use app\service\ArticleService;
use think\Response;

class Articles extends ApiController
{
    public function read(string $code): Response
    {
        return $this->run(fn () => (new ArticleService())->byCode($code, true));
    }
}
