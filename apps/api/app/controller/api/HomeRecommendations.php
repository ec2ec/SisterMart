<?php

declare(strict_types=1);

namespace app\controller\api;

use app\controller\ApiController;
use app\service\CatalogService;
use app\service\TokenService;
use think\Response;

class HomeRecommendations extends ApiController
{
    public function index(): Response
    {
        return $this->run(function () {
            $tab = (string) $this->request->get('tab', 'new');
            $limit = (int) $this->request->get('limit', 16);
            $offset = (int) $this->request->get('offset', 0);
            $viewerId = null;
            $token = TokenService::bearer($this->request);
            $owner = TokenService::ownerId('member_tokens', 'member_id', $token);
            if ($owner) {
                $viewerId = (int) $owner;
            }

            return (new CatalogService())->homeRecommendations($tab, $viewerId, $limit, $offset);
        });
    }
}
