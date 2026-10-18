<?php

declare(strict_types=1);

namespace app\controller\api;

use app\controller\ApiController;
use app\service\BrowseHistoryService;
use think\Response;

/**
 * M26 瀏覽足跡。
 */
class BrowseHistory extends ApiController
{
    public function index(): Response
    {
        return $this->run(function () {
            $member = app('sistermart.member');
            $limit = (int) $this->request->get('limit', 50);

            return (new BrowseHistoryService())->list((int) $member['id'], $limit);
        });
    }

    public function clear(): Response
    {
        return $this->run(function () {
            $member = app('sistermart.member');

            return (new BrowseHistoryService())->clear((int) $member['id']);
        });
    }

    public function destroy(string $id): Response
    {
        return $this->run(function () use ($id) {
            $member = app('sistermart.member');

            return (new BrowseHistoryService())->remove((int) $member['id'], $id);
        });
    }
}
