<?php

declare(strict_types=1);

namespace app\controller\admin;

use app\controller\ApiController;
use app\service\AssetService;
use app\service\MemberAuthService;
use app\support\ApiException;
use think\facade\Db;
use think\Response;

class Members extends ApiController
{
    public function index(): Response
    {
        return $this->run(function () {
            $q = trim((string) $this->request->get('q', ''));
            $query = Db::name('members')->order('id', 'desc')->limit(100);
            if ($q !== '') {
                $query->where(function ($w) use ($q) {
                    $w->whereLike('email', '%' . $q . '%')
                        ->whereOr('phone', 'like', '%' . $q . '%')
                        ->whereOr('promo_code', 'like', '%' . $q . '%');
                });
            }
            $rows = $query->select()->toArray();
            $auth = new MemberAuthService();

            return [
                'items' => array_map(function ($r) use ($auth) {
                    $p = $auth->present($r);
                    $p['created_at'] = $r['created_at'];
                    $p['status'] = (int) $r['status'];

                    return $p;
                }, $rows),
            ];
        });
    }

    public function read(int $id): Response
    {
        return $this->run(function () use ($id) {
            $row = Db::name('members')->where('id', $id)->find();
            if (!$row) {
                throw new ApiException('NOT_FOUND', '會員不存在', 404, 40410);
            }
            $auth = new MemberAuthService();
            $assets = new AssetService();

            return [
                'member' => $auth->present($row),
                'point_logs' => $assets->pointLogs($id, 20)['items'],
                'balance_logs' => $assets->balanceLogs($id, 20)['items'],
            ];
        });
    }

    public function adjust(int $id): Response
    {
        return $this->run(fn () => (new AssetService())->adminAdjust($id, $this->payload()));
    }
}
