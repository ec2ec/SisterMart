<?php

declare(strict_types=1);

namespace app\controller\api;

use app\controller\ApiController;
use app\service\CampaignService;
use think\Response;

class Campaigns extends ApiController
{
    public function read(string $code): Response
    {
        return $this->run(fn () => (new CampaignService())->getPublicByCode($code));
    }

    public function products(string $code): Response
    {
        $offset = (int) $this->request->get('offset', 0);
        $limit = (int) $this->request->get('limit', 30);

        return $this->run(fn () => (new CampaignService())->productsPublic($code, $offset, $limit));
    }

    public function rail(string $code): Response
    {
        return $this->run(fn () => (new CampaignService())->railProducts($code));
    }
}
