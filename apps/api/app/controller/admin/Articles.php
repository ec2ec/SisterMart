<?php

declare(strict_types=1);

namespace app\controller\admin;

use app\controller\ApiController;
use app\service\ArticleService;
use think\Response;

class Articles extends ApiController
{
    public function index(): Response
    {
        // 列表不帶正文，避免九篇 HTML 塞進雙富文本導致後台卡死／誤顯示未建檔
        return $this->run(fn () => ['items' => (new ArticleService())->list(false, false)]);
    }

    public function read(string $code): Response
    {
        return $this->run(fn () => (new ArticleService())->byCode($code, false));
    }

    public function store(): Response
    {
        return $this->run(fn () => (new ArticleService())->save($this->payload()), 201);
    }

    public function update(int $id): Response
    {
        return $this->run(fn () => (new ArticleService())->save($this->payload(), $id));
    }
}
