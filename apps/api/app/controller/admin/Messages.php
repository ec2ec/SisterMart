<?php

declare(strict_types=1);

namespace app\controller\admin;

use app\controller\ApiController;
use app\service\MessageService;
use think\Response;

class Messages extends ApiController
{
    public function index(): Response
    {
        return $this->run(function () {
            $limit = (int) $this->request->get('limit', 50);

            return (new MessageService())->adminList($limit);
        });
    }

    public function store(): Response
    {
        return $this->run(fn () => (new MessageService())->adminCreate($this->payload()), 201);
    }
}
