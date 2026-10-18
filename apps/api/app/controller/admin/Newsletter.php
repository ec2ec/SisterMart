<?php

declare(strict_types=1);

namespace app\controller\admin;

use app\controller\ApiController;
use app\service\NewsletterService;
use think\Response;

class Newsletter extends ApiController
{
    public function index(): Response
    {
        return $this->run(fn () => (new NewsletterService())->adminList([
            'status' => $this->request->get('status'),
            'q' => $this->request->get('q'),
        ]));
    }

    public function status(int $id): Response
    {
        return $this->run(function () use ($id) {
            $body = $this->payload();
            if (!array_key_exists('status', $body)) {
                throw new \app\support\ApiException('INVALID', '缺少 status', 422, 42201);
            }

            return (new NewsletterService())->setStatus($id, (int) $body['status'] ? 1 : 0);
        });
    }

    public function delete(int $id): Response
    {
        return $this->run(fn () => (new NewsletterService())->delete($id));
    }
}
