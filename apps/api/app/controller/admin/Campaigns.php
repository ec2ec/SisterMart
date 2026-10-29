<?php

declare(strict_types=1);

namespace app\controller\admin;

use app\controller\ApiController;
use app\service\CampaignService;
use think\Response;

class Campaigns extends ApiController
{
    public function index(): Response
    {
        return $this->run(fn () => (new CampaignService())->listAdmin());
    }

    public function store(): Response
    {
        return $this->run(fn () => (new CampaignService())->save($this->payload()), 201);
    }

    public function update(string $id): Response
    {
        return $this->run(fn () => (new CampaignService())->save($this->payload(), (int) $id));
    }

    public function destroy(string $id): Response
    {
        return $this->run(fn () => (new CampaignService())->delete((int) $id));
    }
}
