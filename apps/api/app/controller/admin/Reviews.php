<?php

declare(strict_types=1);

namespace app\controller\admin;

use app\controller\ApiController;
use app\service\ReviewService;
use think\Response;

/**
 * M23 評價後台：列表 / 隱藏 / 商家回覆。
 * M25：審核通過 / 拒絕。
 */
class Reviews extends ApiController
{
    public function index(): Response
    {
        return $this->run(fn () => (new ReviewService())->adminList([
            'is_hidden' => $this->request->get('is_hidden'),
            'moderation_status' => $this->request->get('moderation_status'),
            'product_code' => $this->request->get('product_code'),
            'q' => $this->request->get('q'),
        ]));
    }

    public function read(int $id): Response
    {
        return $this->run(fn () => (new ReviewService())->adminRead($id));
    }

    public function visibility(int $id): Response
    {
        return $this->run(fn () => (new ReviewService())->setVisibility($id, $this->payload()));
    }

    public function reply(int $id): Response
    {
        return $this->run(fn () => (new ReviewService())->reply($id, $this->payload()));
    }

    public function moderate(int $id): Response
    {
        return $this->run(fn () => (new ReviewService())->moderate($id, $this->payload()));
    }
}
