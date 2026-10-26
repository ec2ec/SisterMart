<?php

declare(strict_types=1);

namespace app\controller\admin;

use app\controller\ApiController;
use app\service\AddressService;
use app\service\AssetService;
use app\service\MemberAuthService;
use app\service\OrderService;
use app\support\ApiException;
use think\facade\Db;
use think\Response;

class Members extends ApiController
{
    public function index(): Response
    {
        return $this->run(function () {
            $q = trim((string) $this->request->get('q', ''));
            $active = trim((string) $this->request->get('active', ''));
            $sort = (string) $this->request->get('sort', 'last_active_at');
            $order = strtolower((string) $this->request->get('order', 'desc')) === 'asc' ? 'asc' : 'desc';

            $query = Db::name('members');
            if ($q !== '') {
                $query->where(function ($w) use ($q) {
                    $w->whereLike('email', '%' . $q . '%')
                        ->whereOr('phone', 'like', '%' . $q . '%')
                        ->whereOr('promo_code', 'like', '%' . $q . '%');
                    if (ctype_digit($q)) {
                        $w->whereOr('id', (int) $q);
                    }
                });
            }

            $now = time();
            switch ($active) {
                case '1d':
                    $query->where('last_active_at', '>=', date('Y-m-d H:i:s', $now - 86400));
                    break;
                case '7d':
                    $query->where('last_active_at', '>=', date('Y-m-d H:i:s', $now - 7 * 86400));
                    break;
                case '30d':
                    $query->where('last_active_at', '>=', date('Y-m-d H:i:s', $now - 30 * 86400));
                    break;
                case 'never':
                    $query->whereNull('last_active_at');
                    break;
                case 'inactive_7d':
                    $query->where(function ($w) use ($now) {
                        $w->whereNull('last_active_at')
                            ->whereOr('last_active_at', '<', date('Y-m-d H:i:s', $now - 7 * 86400));
                    });
                    break;
                case 'inactive_30d':
                    $query->where(function ($w) use ($now) {
                        $w->whereNull('last_active_at')
                            ->whereOr('last_active_at', '<', date('Y-m-d H:i:s', $now - 30 * 86400));
                    });
                    break;
            }

            if ($sort === 'last_active_at') {
                // NULLS last when desc, first when asc — MySQL puts NULL first on ASC
                $query->orderRaw('last_active_at IS NULL ' . ($order === 'desc' ? 'ASC' : 'DESC'))
                    ->order('last_active_at', $order)
                    ->order('id', 'desc');
            } else {
                $query->order('id', $order);
            }

            $rows = $query->limit(200)->select()->toArray();
            $auth = new MemberAuthService();

            return [
                'items' => array_map(function ($r) use ($auth) {
                    $p = $auth->present($r);
                    $p['created_at'] = $r['created_at'];
                    $p['status'] = (int) $r['status'];
                    $p['last_active_at'] = !empty($r['last_active_at']) ? (string) $r['last_active_at'] : null;

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
            $member = $auth->present($row);
            $member['status'] = (int) ($row['status'] ?? 1);
            $member['last_active_at'] = !empty($row['last_active_at']) ? (string) $row['last_active_at'] : null;
            $member['created_at'] = $row['created_at'] ?? null;

            $orders = (new OrderService())->listAdmin(null, $id);

            return [
                'member' => $member,
                'addresses' => (new AddressService())->list($id),
                'recent_orders' => array_slice($orders['items'] ?? [], 0, 30),
                'point_logs' => $assets->pointLogs($id, 30)['items'],
                'balance_logs' => $assets->balanceLogs($id, 30)['items'],
            ];
        });
    }

    public function adjust(int $id): Response
    {
        return $this->run(fn () => (new AssetService())->adminAdjust($id, $this->payload()));
    }

    /** 啟用 / 禁用會員帳號（status: 1=正常，0=禁用） */
    public function updateStatus(int $id): Response
    {
        return $this->run(function () use ($id) {
            $row = Db::name('members')->where('id', $id)->find();
            if (!$row) {
                throw new ApiException('NOT_FOUND', '會員不存在', 404, 40410);
            }
            $body = $this->payload();
            if (!array_key_exists('status', $body)) {
                throw new ApiException('INVALID', '缺少 status', 422, 42201);
            }
            $status = (int) $body['status'] === 1 ? 1 : 0;
            Db::name('members')->where('id', $id)->update([
                'status' => $status,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            $row = Db::name('members')->where('id', $id)->find();
            $auth = new MemberAuthService();
            $member = $auth->present($row);
            $member['status'] = (int) $row['status'];
            $member['last_active_at'] = !empty($row['last_active_at']) ? (string) $row['last_active_at'] : null;
            $member['created_at'] = $row['created_at'] ?? null;

            return ['member' => $member];
        });
    }
}
