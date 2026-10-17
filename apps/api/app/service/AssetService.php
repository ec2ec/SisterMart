<?php

declare(strict_types=1);

namespace app\service;

use app\support\ApiException;
use think\facade\Db;

class AssetService
{
    public function changePoints(
        int $memberId,
        int $delta,
        string $type,
        ?string $refType = null,
        ?int $refId = null,
        string $remark = '',
    ): array {
        if ($delta === 0) {
            $m = Db::name('members')->where('id', $memberId)->find();

            return ['points' => (int) ($m['points'] ?? 0)];
        }
        $now = date('Y-m-d H:i:s');
        $after = 0;
        Db::transaction(function () use ($memberId, $delta, $type, $refType, $refId, $remark, $now, &$after) {
            $m = Db::name('members')->where('id', $memberId)->lock(true)->find();
            if (!$m) {
                throw new ApiException('NOT_FOUND', '會員不存在', 404, 40410);
            }
            $current = (int) $m['points'];
            $after = $current + $delta;
            if ($after < 0) {
                throw new ApiException('POINTS_SHORT', '積分不足', 422, 42230);
            }
            Db::name('members')->where('id', $memberId)->update([
                'points' => $after,
                'updated_at' => $now,
            ]);
            Db::name('point_logs')->insert([
                'member_id' => $memberId,
                'delta' => $delta,
                'balance_after' => $after,
                'type' => $type,
                'ref_type' => $refType,
                'ref_id' => $refId,
                'remark' => $remark,
                'created_at' => $now,
            ]);
        });

        return ['points' => $after];
    }

    public function changeBalance(
        int $memberId,
        float $delta,
        string $type,
        ?string $refType = null,
        ?int $refId = null,
        string $remark = '',
    ): array {
        $delta = round($delta, 2);
        if ($delta == 0.0) {
            $m = Db::name('members')->where('id', $memberId)->find();

            return ['balance' => (float) ($m['balance'] ?? 0)];
        }
        $now = date('Y-m-d H:i:s');
        $after = 0.0;
        Db::transaction(function () use ($memberId, $delta, $type, $refType, $refId, $remark, $now, &$after) {
            $m = Db::name('members')->where('id', $memberId)->lock(true)->find();
            if (!$m) {
                throw new ApiException('NOT_FOUND', '會員不存在', 404, 40410);
            }
            $current = (float) $m['balance'];
            $after = round($current + $delta, 2);
            if ($after < -0.0001) {
                throw new ApiException('BALANCE_SHORT', '餘額不足', 422, 42231);
            }
            if ($after < 0) {
                $after = 0.0;
            }
            Db::name('members')->where('id', $memberId)->update([
                'balance' => $after,
                'updated_at' => $now,
            ]);
            Db::name('balance_logs')->insert([
                'member_id' => $memberId,
                'delta' => $delta,
                'balance_after' => $after,
                'type' => $type,
                'ref_type' => $refType,
                'ref_id' => $refId,
                'remark' => $remark,
                'created_at' => $now,
            ]);
        });

        return ['balance' => $after];
    }

    /** 訂單完成：累計實付（不含運費）並刷新等級 */
    public function addSpentAndRefreshLevel(int $memberId, float $goodsNetPay): array
    {
        $goodsNetPay = max(0, round($goodsNetPay, 2));
        $now = date('Y-m-d H:i:s');
        $total = 0.0;
        $levelId = 1;
        Db::transaction(function () use ($memberId, $goodsNetPay, $now, &$total, &$levelId) {
            $m = Db::name('members')->where('id', $memberId)->lock(true)->find();
            if (!$m) {
                throw new ApiException('NOT_FOUND', '會員不存在', 404, 40410);
            }
            $total = round((float) ($m['total_spent'] ?? 0) + $goodsNetPay, 2);
            $level = $this->resolveLevel($total);
            $levelId = (int) $level['id'];
            Db::name('members')->where('id', $memberId)->update([
                'total_spent' => $total,
                'level_id' => $levelId,
                'updated_at' => $now,
            ]);
        });

        return ['total_spent' => $total, 'level_id' => $levelId, 'level' => $this->presentLevel($this->findLevel($levelId))];
    }

    public function resolveLevel(float $spent): array
    {
        $row = Db::name('member_levels')
            ->where('min_spent', '<=', $spent)
            ->order('min_spent', 'desc')
            ->find();
        if (!$row) {
            $row = Db::name('member_levels')->order('id', 'asc')->find();
        }
        if (!$row) {
            throw new ApiException('LEVEL_MISSING', '會員等級未配置', 500, 50060);
        }

        return $row;
    }

    public function findLevel(int $id): array
    {
        $row = Db::name('member_levels')->where('id', $id)->find();
        if (!$row) {
            $row = Db::name('member_levels')->order('id', 'asc')->find();
        }

        return $row ?: ['id' => 1, 'code' => 'l1', 'stars' => 1, 'name_zh' => '一星會員', 'name_en' => '1-Star', 'min_spent' => 0, 'max_spent' => 99.99];
    }

    public function presentLevel(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'code' => $row['code'],
            'stars' => (int) $row['stars'],
            'name' => ['zh-HK' => $row['name_zh'], 'en' => $row['name_en']],
            'min_spent' => (float) $row['min_spent'],
            'max_spent' => $row['max_spent'] === null ? null : (float) $row['max_spent'],
        ];
    }

    public function listLevels(): array
    {
        $rows = Db::name('member_levels')->order('sort_order', 'asc')->select()->toArray();

        return ['items' => array_map(fn ($r) => $this->presentLevel($r), $rows)];
    }

    public function updateLevel(int $id, array $body): array
    {
        $row = Db::name('member_levels')->where('id', $id)->find();
        if (!$row) {
            throw new ApiException('NOT_FOUND', '等級不存在', 404, 40461);
        }
        $data = [];
        if (array_key_exists('min_spent', $body)) {
            $data['min_spent'] = round((float) $body['min_spent'], 2);
        }
        if (array_key_exists('max_spent', $body)) {
            $data['max_spent'] = $body['max_spent'] === null || $body['max_spent'] === ''
                ? null
                : round((float) $body['max_spent'], 2);
        }
        if (isset($body['name_zh'])) {
            $data['name_zh'] = trim((string) $body['name_zh']);
        }
        if (isset($body['name_en'])) {
            $data['name_en'] = trim((string) $body['name_en']);
        }
        if ($data) {
            Db::name('member_levels')->where('id', $id)->update($data);
        }
        // 同步所有會員等級
        $members = Db::name('members')->field('id,total_spent')->select()->toArray();
        foreach ($members as $m) {
            $level = $this->resolveLevel((float) $m['total_spent']);
            Db::name('members')->where('id', $m['id'])->update(['level_id' => $level['id']]);
        }

        return $this->presentLevel(Db::name('member_levels')->where('id', $id)->find());
    }

    public function pointLogs(int $memberId, int $limit = 50): array
    {
        $rows = Db::name('point_logs')
            ->where('member_id', $memberId)
            ->order('id', 'desc')
            ->limit(max(1, min(200, $limit)))
            ->select()->toArray();

        return [
            'items' => array_map(fn ($r) => [
                'id' => (int) $r['id'],
                'delta' => (int) $r['delta'],
                'balance_after' => (int) $r['balance_after'],
                'type' => $r['type'],
                'ref_type' => $r['ref_type'],
                'ref_id' => $r['ref_id'] ? (int) $r['ref_id'] : null,
                'remark' => $r['remark'],
                'created_at' => $r['created_at'],
            ], $rows),
        ];
    }

    public function balanceLogs(int $memberId, int $limit = 50): array
    {
        $rows = Db::name('balance_logs')
            ->where('member_id', $memberId)
            ->order('id', 'desc')
            ->limit(max(1, min(200, $limit)))
            ->select()->toArray();

        return [
            'items' => array_map(fn ($r) => [
                'id' => (int) $r['id'],
                'delta' => (float) $r['delta'],
                'balance_after' => (float) $r['balance_after'],
                'type' => $r['type'],
                'ref_type' => $r['ref_type'],
                'ref_id' => $r['ref_id'] ? (int) $r['ref_id'] : null,
                'remark' => $r['remark'],
                'created_at' => $r['created_at'],
            ], $rows),
        ];
    }

    public function adminAdjust(int $memberId, array $body): array
    {
        $reason = trim((string) ($body['reason'] ?? ''));
        if ($reason === '') {
            throw new ApiException('INVALID', '請填寫調整原因', 422, 42262);
        }
        $pointsDelta = isset($body['points_delta']) ? (int) $body['points_delta'] : 0;
        $balanceDelta = isset($body['balance_delta']) ? round((float) $body['balance_delta'], 2) : 0.0;
        if ($pointsDelta === 0 && $balanceDelta == 0.0) {
            throw new ApiException('INVALID', '請填寫積分或餘額變動', 422, 42263);
        }
        if ($pointsDelta !== 0) {
            $this->changePoints($memberId, $pointsDelta, 'admin_adjust', 'admin', null, $reason);
        }
        if ($balanceDelta != 0.0) {
            $this->changeBalance($memberId, $balanceDelta, 'admin_adjust', 'admin', null, $reason);
        }
        $member = Db::name('members')->where('id', $memberId)->find();
        if (!$member) {
            throw new ApiException('NOT_FOUND', '會員不存在', 404, 40410);
        }

        return (new MemberAuthService())->present($member);
    }

    public function pointsSettings(?array $body = null): array
    {
        if ($body !== null) {
            $now = date('Y-m-d H:i:s');
            $map = [
                'per_hkd' => 'points.per_hkd',
                'max_percent' => 'points.max_percent',
                'order_earn_per_hkd' => 'points.order_earn_per_hkd',
            ];
            foreach ($map as $field => $key) {
                if (!array_key_exists($field, $body)) {
                    continue;
                }
                $val = (string) (int) $body[$field];
                $exists = Db::name('shop_settings')->where('setting_key', $key)->find();
                if ($exists) {
                    Db::name('shop_settings')->where('setting_key', $key)->update([
                        'setting_value' => $val,
                        'updated_at' => $now,
                    ]);
                } else {
                    Db::name('shop_settings')->insert([
                        'setting_key' => $key,
                        'setting_value' => $val,
                        'updated_at' => $now,
                    ]);
                }
            }
        }

        return [
            'per_hkd' => TradeSettings::pointsPerHkd(),
            'max_percent' => TradeSettings::pointsMaxPercent(),
            'order_earn_per_hkd' => TradeSettings::orderEarnPerHkd(),
        ];
    }

    /** 商品實付淨額（不含運費），用於累計與發積分 */
    public static function goodsNetPay(array $order): float
    {
        $goodsNet = max(0, (float) $order['goods_amount'] - (float) $order['coupon_amount']);
        $ship = (float) $order['shipping_amount'];
        $pointsAmt = (float) $order['points_amount'];
        $bal = (float) $order['balance_amount'];
        $pay = (float) $order['pay_amount'];
        // 現金+餘額+積分抵扣覆蓋「商品+運費」；運費優先由現金/餘額承擔
        $totalCovered = $pay + $bal + $pointsAmt;
        $goodsCovered = max(0, min($goodsNet, $totalCovered - min($ship, $totalCovered)));
        // 積分抵扣視為已付一部分商品；餘額/現金同理。發積分基準為實際「商品淨額」中非積分抵扣部分？
        // PRD：運費不計；下單積分按商品實付。商品實付 = 售價−券−分攤到商品的積分/餘額/現金
        // 簡化：basis = goods − coupon − points_amount（積分本來就是抵商品優先）− min(balance, afterPoints)
        // 但 balance 可能覆蓋運費：balOnGoods = min(balance, afterPoints)；cashOnGoods = min(pay, afterPoints - balOnGoods)
        $afterCoupon = $goodsNet;
        $afterPoints = max(0, $afterCoupon - $pointsAmt);
        $balOnGoods = min($bal, $afterPoints);
        $cashOnGoods = min($pay, max(0, $afterPoints - $balOnGoods));

        return round($pointsAmt + $balOnGoods + $cashOnGoods, 2);
    }
}
