<?php

declare(strict_types=1);

namespace app\controller\admin;

use app\controller\ApiController;
use app\service\NotifyOutboxService;
use think\Response;

class NotifyOutbox extends ApiController
{
    public function index(): Response
    {
        return $this->run(fn () => (new NotifyOutboxService())->adminList([
            'channel' => $this->request->get('channel'),
            'status' => $this->request->get('status'),
            'purpose' => $this->request->get('purpose'),
            'q' => $this->request->get('q'),
        ]));
    }
}
