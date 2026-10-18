<?php

declare(strict_types=1);

namespace app\controller\admin;

use app\controller\ApiController;
use app\service\DistributionService;
use think\facade\Db;
use think\Response;

class Distribution extends ApiController
{
    public function ranking(): Response
    {
        return $this->run(function () {
            $period = (string) $this->request->get('period', 'month');

            return (new DistributionService())->ranking($period);
        });
    }

    public function commissions(): Response
    {
        return $this->run(function () {
            $rows = Db::name('commissions')->alias('c')
                ->leftJoin('orders o', 'o.id = c.order_id')
                ->leftJoin('members m', 'm.id = c.beneficiary_id')
                ->order('c.id', 'desc')
                ->limit(100)
                ->field('c.id, c.amount, c.status, c.created_at, c.settled_at, c.beneficiary_id, o.order_no, m.email, m.phone')
                ->select()
                ->toArray();

            return [
                'items' => array_map(fn ($r) => [
                    'id' => (int) $r['id'],
                    'order_no' => (string) ($r['order_no'] ?? ''),
                    'beneficiary_id' => (int) $r['beneficiary_id'],
                    'beneficiary' => (string) ($r['email'] ?: $r['phone'] ?: ('M' . $r['beneficiary_id'])),
                    'amount' => (float) $r['amount'],
                    'status' => (string) $r['status'],
                    'created_at' => $r['created_at'],
                    'settled_at' => $r['settled_at'],
                ], $rows),
            ];
        });
    }

    public function products(): Response
    {
        return $this->run(function () {
            $rows = Db::name('products')->alias('p')
                ->leftJoin('product_i18n i', "i.product_id = p.id AND i.locale = 'zh-HK'")
                ->order('p.id', 'desc')
                ->limit(200)
                ->field('p.id, p.code, p.status, p.commission_type, p.commission_value, i.name')
                ->select()
                ->toArray();

            return [
                'items' => array_map(fn ($r) => [
                    'id' => (int) $r['id'],
                    'code' => (string) $r['code'],
                    'name' => (string) ($r['name'] ?: $r['code']),
                    'status' => (int) $r['status'],
                    'commission_type' => $r['commission_type'],
                    'commission_value' => $r['commission_value'] !== null ? (float) $r['commission_value'] : null,
                ], $rows),
            ];
        });
    }
}
