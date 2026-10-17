<?php

declare(strict_types=1);

namespace app\service;

use app\support\ApiException;
use think\facade\Db;

/**
 * M13 一級分銷：待入帳 / 已入帳佣金、提現、下級與排行。
 */
class DistributionService
{
    /**
     * 訂單進入待發貨時建立 pending 佣金（冪等）。
     */
    public function createPendingForOrder(int $orderId): void
    {
        $order = Db::name('orders')->where('id', $orderId)->find();
        if (!$order) {
            return;
        }
        // 積分商城不計佣金
        if (!empty($order['points_mall_item_id'])) {
            return;
        }
        $exists = Db::name('commissions')->where('order_id', $orderId)->count();
        if ($exists > 0) {
            return;
        }
        $buyer = Db::name('members')->where('id', $order['member_id'])->find();
        if (!$buyer || empty($buyer['parent_id'])) {
            return;
        }
        $parentId = (int) $buyer['parent_id'];
        if ($parentId === (int) $buyer['id']) {
            return;
        }
        $parent = Db::name('members')->where('id', $parentId)->find();
        if (!$parent || (int) $parent['status'] !== 1) {
            return;
        }

        $items = Db::name('order_items')->where('order_id', $orderId)->select()->toArray();
        if (!$items) {
            return;
        }
        $goodsAmount = (float) $order['goods_amount'];
        $goodsNet = AssetService::goodsNetPay($order);
        $now = date('Y-m-d H:i:s');

        foreach ($items as $item) {
            $product = Db::name('products')->where('id', $item['product_id'])->find();
            $amount = $this->lineCommission($product, $item, $goodsAmount, $goodsNet);
            if ($amount <= 0) {
                continue;
            }
            Db::name('commissions')->insert([
                'beneficiary_id' => $parentId,
                'buyer_id' => (int) $buyer['id'],
                'order_id' => $orderId,
                'order_item_id' => (int) $item['id'],
                'product_id' => (int) $item['product_id'],
                'amount' => $amount,
                'original_amount' => $amount,
                'status' => 'pending',
                'created_at' => $now,
                'settled_at' => null,
                'updated_at' => $now,
            ]);
        }
    }

    /** 訂單完成：pending → settled，增加可提現（已沖回部分只入帳淨額） */
    public function settleForOrder(int $orderId): void
    {
        $now = date('Y-m-d H:i:s');
        $rows = Db::name('commissions')->where('order_id', $orderId)->where('status', 'pending')->lock(true)->select()->toArray();
        $byBeneficiary = [];
        foreach ($rows as $row) {
            $net = $this->netCommissionAmount($row);
            if ($net <= 0) {
                Db::name('commissions')->where('id', $row['id'])->where('status', 'pending')->update([
                    'status' => 'void',
                    'amount' => 0,
                    'updated_at' => $now,
                ]);
                continue;
            }
            $ok = Db::name('commissions')->where('id', $row['id'])->where('status', 'pending')->update([
                'status' => 'settled',
                'amount' => $net,
                'settled_at' => $now,
                'updated_at' => $now,
            ]);
            if ($ok) {
                $bid = (int) $row['beneficiary_id'];
                $byBeneficiary[$bid] = ($byBeneficiary[$bid] ?? 0) + $net;
            }
        }
        foreach ($byBeneficiary as $bid => $sum) {
            $m = Db::name('members')->where('id', $bid)->lock(true)->find();
            if (!$m) {
                continue;
            }
            Db::name('members')->where('id', $bid)->update([
                'commission_available' => round((float) ($m['commission_available'] ?? 0) + $sum, 2),
                'updated_at' => $now,
            ]);
        }
    }

    /** 關單 / 整單作廢：pending → void */
    public function voidForOrder(int $orderId): void
    {
        $now = date('Y-m-d H:i:s');
        Db::name('commissions')->where('order_id', $orderId)->where('status', 'pending')->update([
            'status' => 'void',
            'updated_at' => $now,
        ]);
    }

    /**
     * M16 售後退款完成：按售後數量比例沖回佣金（冪等：aftersale_id 唯一）。
     */
    public function clawbackForAftersale(int $aftersaleId): void
    {
        $aftersale = Db::name('aftersales')->where('id', $aftersaleId)->lock(true)->find();
        if (!$aftersale || $aftersale['status'] !== 'completed') {
            return;
        }
        if (Db::name('commission_clawbacks')->where('aftersale_id', $aftersaleId)->find()) {
            return;
        }

        $comm = Db::name('commissions')
            ->where('order_item_id', (int) $aftersale['order_item_id'])
            ->lock(true)
            ->find();
        if (!$comm || in_array($comm['status'], ['void', 'clawed_back'], true)) {
            return;
        }

        $item = Db::name('order_items')->where('id', (int) $aftersale['order_item_id'])->find();
        if (!$item) {
            return;
        }

        $original = (float) ($comm['original_amount'] ?? $comm['amount']);
        if ($original <= 0) {
            return;
        }
        $itemQty = max(1, (int) $item['qty']);
        $claw = round($original * ((int) $aftersale['qty'] / $itemQty), 2);
        $already = (float) Db::name('commission_clawbacks')->where('commission_id', $comm['id'])->sum('amount');
        $remain = round($original - $already, 2);
        $claw = min($claw, max(0, $remain));
        if ($claw <= 0) {
            return;
        }

        $now = date('Y-m-d H:i:s');
        Db::name('commission_clawbacks')->insert([
            'aftersale_id' => $aftersaleId,
            'commission_id' => (int) $comm['id'],
            'amount' => $claw,
            'created_at' => $now,
        ]);

        $clawedTotal = round($already + $claw, 2);
        $net = round($original - $clawedTotal, 2);

        if ($comm['status'] === 'pending') {
            $update = [
                'amount' => max(0, $net),
                'updated_at' => $now,
            ];
            if ($net <= 0) {
                $update['status'] = 'void';
                $update['amount'] = 0;
            }
            Db::name('commissions')->where('id', $comm['id'])->where('status', 'pending')->update($update);

            return;
        }

        if ($comm['status'] === 'settled') {
            $m = Db::name('members')->where('id', (int) $comm['beneficiary_id'])->lock(true)->find();
            if ($m) {
                $available = round((float) ($m['commission_available'] ?? 0) - $claw, 2);
                Db::name('members')->where('id', (int) $m['id'])->update([
                    'commission_available' => max(0, $available),
                    'updated_at' => $now,
                ]);
            }
            $update = [
                'amount' => max(0, $net),
                'updated_at' => $now,
            ];
            if ($net <= 0) {
                $update['status'] = 'clawed_back';
                $update['amount'] = 0;
            }
            Db::name('commissions')->where('id', $comm['id'])->where('status', 'settled')->update($update);
        }
    }

    /** @param array<string, mixed> $row */
    private function netCommissionAmount(array $row): float
    {
        $original = (float) ($row['original_amount'] ?? $row['amount']);
        $clawed = (float) Db::name('commission_clawbacks')->where('commission_id', (int) $row['id'])->sum('amount');

        return round(max(0, $original - $clawed), 2);
    }

    private function mapCommissionStatus(string $status): string
    {
        return match ($status) {
            'settled' => 'settled',
            'void' => 'void',
            'clawed_back' => 'clawed_back',
            default => 'pending',
        };
    }

    public function overview(int $memberId): array
    {
        $member = Db::name('members')->where('id', $memberId)->find();
        if (!$member) {
            throw new ApiException('NOT_FOUND', '會員不存在', 404, 40410);
        }
        $promo = (string) ($member['promo_code'] ?? '');
        $available = round((float) ($member['commission_available'] ?? 0), 2);
        $pending = (float) Db::name('commissions')->where('beneficiary_id', $memberId)->where('status', 'pending')->sum('amount');
        $total = (float) Db::name('commissions')->where('beneficiary_id', $memberId)->where('status', 'settled')->sum('amount');
        $downlineCount = (int) Db::name('members')->where('parent_id', $memberId)->count();

        return [
            'mock' => false,
            'promo_code' => $promo,
            'available_commission' => $available,
            'pending_commission' => round($pending, 2),
            'total_commission' => round($total, 2),
            'downline_count' => $downlineCount,
            'share_link' => '/?ref=' . $promo,
            'withdraw_min' => TradeSettings::withdrawMin(),
        ];
    }

    public function downlines(int $memberId): array
    {
        $rows = Db::name('members')->where('parent_id', $memberId)->order('id', 'desc')->limit(50)->select()->toArray();
        $items = [];
        foreach ($rows as $r) {
            $id = (int) $r['id'];
            $spent = $this->downlineSpent($id);
            $earned = (float) Db::name('commissions')
                ->where('beneficiary_id', $memberId)
                ->where('buyer_id', $id)
                ->where('status', 'settled')
                ->sum('amount');
            $items[] = [
                'id' => $id,
                'nickname' => $this->nickname($r),
                'avatar' => '',
                'registered_at' => $r['created_at'],
                'spent' => round($spent, 2),
                'commission_earned' => round($earned, 2),
            ];
        }

        return ['mock' => false, 'items' => $items];
    }

    public function commissions(int $memberId): array
    {
        $rows = Db::name('commissions')->alias('c')
            ->leftJoin('orders o', 'o.id = c.order_id')
            ->where('c.beneficiary_id', $memberId)
            ->order('c.id', 'desc')
            ->limit(50)
            ->field('c.id, c.amount, c.status, c.created_at, c.settled_at, o.order_no')
            ->select()
            ->toArray();
        $items = array_map(fn ($r) => [
            'id' => (int) $r['id'],
            'order_no' => (string) ($r['order_no'] ?? ''),
            'amount' => (float) $r['amount'],
            'status' => $this->mapCommissionStatus((string) $r['status']),
            'created_at' => $r['created_at'],
        ], $rows);

        return ['mock' => false, 'items' => $items];
    }

    public function promoOrders(int $memberId): array
    {
        $rows = Db::name('commissions')->alias('c')
            ->leftJoin('orders o', 'o.id = c.order_id')
            ->leftJoin('order_items i', 'i.id = c.order_item_id')
            ->where('c.beneficiary_id', $memberId)
            ->order('c.id', 'desc')
            ->limit(50)
            ->field('o.order_no, i.name_zh, i.name_en, c.amount, c.status')
            ->select()
            ->toArray();
        $items = array_map(fn ($r) => [
            'order_no' => (string) ($r['order_no'] ?? ''),
            'title' => (string) ($r['name_zh'] ?: $r['name_en'] ?: ''),
            'commission' => (float) $r['amount'],
            'status' => $this->mapCommissionStatus((string) $r['status']),
        ], $rows);

        return ['mock' => false, 'items' => $items];
    }

    public function ranking(string $period = 'month'): array
    {
        $period = $period === 'total' ? 'total' : 'month';
        $q = Db::name('commissions')->alias('c')
            ->leftJoin('members m', 'm.id = c.beneficiary_id')
            ->where('c.status', 'settled')
            ->field('c.beneficiary_id, m.email, m.phone, SUM(c.amount) AS total')
            ->group('c.beneficiary_id')
            ->order('total', 'desc')
            ->limit(20);
        if ($period === 'month') {
            $q->where('c.settled_at', '>=', date('Y-m-01 00:00:00'));
        }
        $rows = $q->select()->toArray();
        $items = [];
        $rank = 1;
        foreach ($rows as $r) {
            $items[] = [
                'rank' => $rank++,
                'nickname' => $this->nickname($r),
                'commission' => round((float) $r['total'], 2),
            ];
        }

        return ['mock' => false, 'period' => $period, 'items' => $items];
    }

    public function withdraw(int $memberId, array $body): array
    {
        $channel = (string) ($body['channel'] ?? 'balance');
        $amount = round((float) ($body['amount'] ?? 0), 2);
        if ($amount <= 0) {
            throw new ApiException('INVALID', '請填寫提現金額', 422, 42281);
        }
        $min = TradeSettings::withdrawMin();
        if ($amount < $min) {
            throw new ApiException('WITHDRAW_MIN', '低於最低提現額 HK$' . $min, 422, 42284);
        }
        $now = date('Y-m-d H:i:s');
        $result = [];
        Db::transaction(function () use ($memberId, $channel, $amount, $body, $now, &$result) {
            $member = Db::name('members')->where('id', $memberId)->lock(true)->find();
            if (!$member) {
                throw new ApiException('NOT_FOUND', '會員不存在', 404, 40410);
            }
            $available = (float) ($member['commission_available'] ?? 0);
            if ($available + 1e-9 < $amount) {
                throw new ApiException('COMMISSION_SHORT', '可提現佣金不足', 422, 42285);
            }
            Db::name('members')->where('id', $memberId)->dec('commission_available', $amount)->update(['updated_at' => $now]);

            if ($channel === 'balance') {
                $wid = (int) Db::name('withdrawals')->insertGetId([
                    'member_id' => $memberId,
                    'channel' => 'balance',
                    'amount' => $amount,
                    'status' => 'done',
                    'fps_name' => null,
                    'fps_id' => null,
                    'review_note' => null,
                    'created_at' => $now,
                    'reviewed_at' => $now,
                    'updated_at' => $now,
                ]);
                (new AssetService())->changeBalance(
                    $memberId,
                    $amount,
                    'commission_to_balance',
                    'withdraw',
                    $wid,
                    '佣金轉入餘額',
                );
                $result = ['mock' => false, 'channel' => 'balance', 'amount' => $amount, 'status' => 'done', 'withdrawal_id' => $wid];

                return;
            }
            if ($channel !== 'fps') {
                throw new ApiException('INVALID', '提現渠道不正確', 422, 42282);
            }
            $name = trim((string) ($body['fps_name'] ?? ''));
            $fpsId = trim((string) ($body['fps_id'] ?? ''));
            if ($name === '' || $fpsId === '') {
                throw new ApiException('INVALID', '請填寫轉數快資料', 422, 42283);
            }
            $wid = (int) Db::name('withdrawals')->insertGetId([
                'member_id' => $memberId,
                'channel' => 'fps',
                'amount' => $amount,
                'status' => 'pending_review',
                'fps_name' => $name,
                'fps_id' => $fpsId,
                'review_note' => null,
                'created_at' => $now,
                'reviewed_at' => null,
                'updated_at' => $now,
            ]);
            $result = [
                'mock' => false,
                'channel' => 'fps',
                'amount' => $amount,
                'status' => 'pending_review',
                'fps_name' => $name,
                'fps_id' => $fpsId,
                'withdrawal_id' => $wid,
            ];
        });

        return $result;
    }

    public function reviewWithdrawal(int $withdrawalId, string $action, string $note = ''): array
    {
        $action = strtolower($action);
        if (!in_array($action, ['approve', 'reject'], true)) {
            throw new ApiException('INVALID', '審核動作無效', 422, 42201);
        }
        $now = date('Y-m-d H:i:s');
        Db::transaction(function () use ($withdrawalId, $action, $note, $now) {
            $row = Db::name('withdrawals')->where('id', $withdrawalId)->lock(true)->find();
            if (!$row) {
                throw new ApiException('NOT_FOUND', '提現單不存在', 404, 40490);
            }
            if ($row['status'] !== 'pending_review') {
                throw new ApiException('INVALID_STATUS', '提現單不可審核', 422, 42291);
            }
            if ($action === 'approve') {
                Db::name('withdrawals')->where('id', $withdrawalId)->update([
                    'status' => 'paid',
                    'review_note' => $note,
                    'reviewed_at' => $now,
                    'updated_at' => $now,
                ]);

                return;
            }
            Db::name('withdrawals')->where('id', $withdrawalId)->update([
                'status' => 'rejected',
                'review_note' => $note,
                'reviewed_at' => $now,
                'updated_at' => $now,
            ]);
            Db::name('members')->where('id', $row['member_id'])->inc('commission_available', (float) $row['amount'])->update([
                'updated_at' => $now,
            ]);
        });

        return Db::name('withdrawals')->where('id', $withdrawalId)->find() ?: [];
    }

    public function poster(int $memberId, ?string $productId = null): array
    {
        $promo = (string) Db::name('members')->where('id', $memberId)->value('promo_code');
        $productId = $productId ?: 'p1';
        $product = Db::name('products')->where('code', $productId)->where('status', 1)->find();
        if (!$product) {
            $product = Db::name('products')->where('status', 1)->order('id', 'asc')->find();
        }
        if (!$product) {
            throw new ApiException('NOT_FOUND', '商品不存在', 404, 40402);
        }
        $names = Db::name('product_i18n')->where('product_id', $product['id'])->select()->toArray();
        $name = ['zh-HK' => '', 'en' => ''];
        foreach ($names as $n) {
            $name[$n['locale']] = $n['name'];
        }
        $sku = Db::name('skus')->where('product_id', $product['id'])->where('status', 1)->order('id', 'asc')->find();
        $code = (string) $product['code'];

        return [
            'mock' => false,
            'product_id' => $code,
            'name' => $name,
            'price' => (float) ($sku['price'] ?? 0),
            'image' => (string) ($product['cover_url'] ?: ($sku['image_url'] ?? '')),
            'qr_payload' => '/product/' . $code . '?ref=' . $promo,
            'promo_code' => $promo,
        ];
    }

    private function lineCommission(?array $product, array $item, float $goodsAmount, float $goodsNet): float
    {
        if (!$product) {
            return 0.0;
        }
        $type = $product['commission_type'] ?? null;
        $value = (float) ($product['commission_value'] ?? 0);
        if ($type === null || $type === '' || $value <= 0) {
            return 0.0;
        }
        $qty = max(1, (int) $item['qty']);
        if ($type === 'fixed') {
            return round($value * $qty, 2);
        }
        if ($type === 'percent') {
            $line = (float) $item['line_amount'];
            $basis = $goodsAmount > 0 ? ($line / $goodsAmount) * $goodsNet : 0.0;

            return round(max(0, $basis) * $value / 100, 2);
        }

        return 0.0;
    }

    private function downlineSpent(int $memberId): float
    {
        $orders = Db::name('orders')
            ->where('member_id', $memberId)
            ->where('status', 'completed')
            ->whereNull('points_mall_item_id')
            ->select()
            ->toArray();
        $sum = 0.0;
        foreach ($orders as $o) {
            $sum += AssetService::goodsNetPay($o);
        }

        return $sum;
    }

    private function nickname(array $row): string
    {
        return (string) ($row['email'] ?: $row['phone'] ?: ('M' . ($row['beneficiary_id'] ?? $row['id'] ?? '')));
    }
}
