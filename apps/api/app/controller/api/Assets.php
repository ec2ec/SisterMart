<?php

declare(strict_types=1);

namespace app\controller\api;

use app\controller\ApiController;
use app\service\AssetService;
use think\Response;

class Assets extends ApiController
{
    public function pointLogs(): Response
    {
        return $this->run(function () {
            $member = app('sistermart.member');
            $limit = (int) $this->request->get('limit', 50);

            return (new AssetService())->pointLogs((int) $member['id'], $limit);
        });
    }

    public function balanceLogs(): Response
    {
        return $this->run(function () {
            $member = app('sistermart.member');
            $limit = (int) $this->request->get('limit', 50);

            return (new AssetService())->balanceLogs((int) $member['id'], $limit);
        });
    }

    public function level(): Response
    {
        return $this->run(function () {
            $member = app('sistermart.member');
            $assets = new AssetService();
            $level = $assets->presentLevel($assets->findLevel((int) ($member['level_id'] ?? 1)));
            $levels = $assets->listLevels();

            return [
                'total_spent' => (float) ($member['total_spent'] ?? 0),
                'level' => $level,
                'levels' => $levels['items'],
            ];
        });
    }

    public function levelsPublic(): Response
    {
        return $this->run(fn () => (new AssetService())->listLevels());
    }
}
