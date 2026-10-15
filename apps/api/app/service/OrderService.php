<?php

declare(strict_types=1);

namespace app\service;

use app\support\ApiException;
use think\facade\Db;

class OrderService
{
    public function __construct(
        private CartService $cart = new CartService(),
        private AddressService $addresses = new AddressService(),
    ) {
    }

    public function quote(int $memberId, array $body): array
    {
        $lines = $this->resolveLines($memberId, $body);
        $member = Db::name('members')->where('id', $memberId)->find();

        return $this->compute($member, $lines, $body);
    }

    public function create(int $memberId, array $body): array
    {
        $addressId = (int) ($body['address_id'] ?? 0);
        $address = $this->addresses->getOwned($memberId, $addressId);
        $lines = $this->resolveLines($memberId, $body);
        $member = Db::name('members')->where('id', $memberId)->find();
        $quote = $this->compute($member, $lines, $body);
        $now = date('Y-m-d H:i:s');
        $timeout = TradeSettings::unpaidTimeoutMinutes();
        $expireAt = date('Y-m-d H:i:s', time() + $timeout * 60);

        $orderId = 0;
        Db::transaction(function () use ($memberId, $address, $lines, $quote, $body, $now, $expireAt, &$orderId) {
            foreach ($lines as $line) {
                $affected = Db::name('skus')->where('id', $line['sku_id'])->where('stock', '>=', $line['qty'])
                    ->dec('stock', $line['qty'])->update(['updated_at' => $now]);
                if (!$affected) {
                    throw new ApiException('OUT_OF_STOCK', '庫存不足，無法下單', 422, 42220);
                }
            }

            $pointsUsed = (int) $quote['points_used'];
            $balanceUsed = (float) $quote['balance_amount'];
            if ($pointsUsed > 0) {
                $ok = Db::name('members')->where('id', $memberId)->where('points', '>=', $pointsUsed)
                    ->dec('points', $pointsUsed)->update(['updated_at' => $now]);
                if (!$ok) {
                    throw new ApiException('POINTS_SHORT', '積分不足', 422, 42230);
                }
            }
            if ($balanceUsed > 0) {
                $ok = Db::name('members')->where('id', $memberId)->where('balance', '>=', $balanceUsed)
                    ->dec('balance', $balanceUsed)->update(['updated_at' => $now]);
                if (!$ok) {
                    throw new ApiException('BALANCE_SHORT', '餘額不足', 422, 42231);
                }
            }
            if ($quote['coupon_grant_id']) {
                $ok = Db::name('coupon_grants')->where([
                    'id' => $quote['coupon_grant_id'],
                    'member_id' => $memberId,
                    'status' => 'unused',
                ])->update(['status' => 'frozen', 'updated_at' => $now]);
                if (!$ok) {
                    throw new ApiException('COUPON_INVALID', '優惠券不可用', 422, 42232);
                }
            }

            $payAmount = (float) $quote['pay_amount'];
            $status = $payAmount <= 0 ? 'pending_shipment' : 'pending_payment';
            $orderId = (int) Db::name('orders')->insertGetId([
                'order_no' => 'TMP',
                'member_id' => $memberId,
                'status' => $status,
                'address_snapshot' => json_encode([
                    'receiver' => $address['receiver'],
                    'phone' => $address['phone'],
                    'region' => $address['region'],
                    'district' => $address['district'],
                    'detail' => $address['detail'],
                ], JSON_UNESCAPED_UNICODE),
                'remark' => trim((string) ($body['remark'] ?? '')),
                'goods_amount' => $quote['goods_amount'],
                'coupon_amount' => $quote['coupon_amount'],
                'points_amount' => $quote['points_amount'],
                'points_used' => $pointsUsed,
                'balance_amount' => $balanceUsed,
                'shipping_amount' => $quote['shipping_amount'],
                'pay_amount' => $payAmount,
                'use_points' => !empty($body['use_points']) ? 1 : 0,
                'use_balance' => !empty($body['use_balance']) ? 1 : 0,
                'coupon_grant_id' => $quote['coupon_grant_id'],
                'pay_channel' => $payAmount <= 0 ? 'zero' : null,
                'paid_at' => $payAmount <= 0 ? $now : null,
                'expire_at' => $payAmount <= 0 ? null : $expireAt,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $orderNo = 'SM' . date('Ymd') . str_pad((string) $orderId, 6, '0', STR_PAD_LEFT);
            Db::name('orders')->where('id', $orderId)->update(['order_no' => $orderNo]);

            foreach ($lines as $line) {
                Db::name('order_items')->insert([
                    'order_id' => $orderId,
                    'product_id' => $line['product_db_id'],
                    'sku_id' => $line['sku_id'],
                    'product_code' => $line['product_id'],
                    'sku_code' => $line['sku_code'],
                    'name_zh' => $line['name']['zh-HK'],
                    'name_en' => $line['name']['en'],
                    'spec_zh' => $line['spec']['zh-HK'],
                    'spec_en' => $line['spec']['en'],
                    'image_url' => $line['image'],
                    'unit_price' => $line['price'],
                    'qty' => $line['qty'],
                    'line_amount' => round($line['price'] * $line['qty'], 2),
                ]);
            }

            if ($quote['coupon_grant_id']) {
                Db::name('coupon_grants')->where('id', $quote['coupon_grant_id'])->update([
                    'order_id' => $orderId,
                    'status' => $payAmount <= 0 ? 'used' : 'frozen',
                    'updated_at' => $now,
                ]);
            }

            if (empty($body['buy_now'])) {
                $this->cart->clearChecked($memberId);
            }
        });

        return $this->detail($memberId, $orderId);
    }

    public function listForMember(int $memberId, ?string $status = null): array
    {
        $this->closeExpired();
        $q = Db::name('orders')->where('member_id', $memberId)->order('id', 'desc');
        if ($status) {
            $q->where('status', $status);
        }
        $rows = $q->select()->toArray();

        return ['items' => array_map(fn ($r) => $this->presentSummary($r), $rows)];
    }

    public function listAdmin(?string $status = null): array
    {
        $this->closeExpired();
        $q = Db::name('orders')->order('id', 'desc');
        if ($status) {
            $q->where('status', $status);
        }
        $rows = $q->limit(200)->select()->toArray();

        return ['items' => array_map(fn ($r) => $this->presentSummary($r, true), $rows)];
    }

    public function detail(int $memberId, int|string $idOrNo, bool $admin = false): array
    {
        $this->closeExpired();
        $row = $this->findOrder($idOrNo);
        if (!$admin && (int) $row['member_id'] !== $memberId) {
            throw new ApiException('NOT_FOUND', '訂單不存在', 404, 40440);
        }

        return $this->presentDetail($row);
    }

    public function cancel(int $memberId, int $orderId): array
    {
        $row = $this->findOrder($orderId);
        if ((int) $row['member_id'] !== $memberId) {
            throw new ApiException('NOT_FOUND', '訂單不存在', 404, 40440);
        }
        if ($row['status'] !== 'pending_payment') {
            throw new ApiException('INVALID_STATUS', '僅待付款訂單可取消', 422, 42240);
        }
        $this->releaseOrder($row, 'user_cancel');

        return $this->detail($memberId, $orderId);
    }

    public function createPayment(int $memberId, int $orderId, string $channel): array
    {
        $allowed = ['fps', 'alipay_hk', 'wechat_hk'];
        if (!in_array($channel, $allowed, true)) {
            throw new ApiException('INVALID', '支付渠道無效', 422, 42201);
        }
        $row = $this->findOrder($orderId);
        if ((int) $row['member_id'] !== $memberId) {
            throw new ApiException('NOT_FOUND', '訂單不存在', 404, 40440);
        }
        if ($row['status'] !== 'pending_payment') {
            throw new ApiException('INVALID_STATUS', '訂單狀態不可支付', 422, 42241);
        }
        if ($row['expire_at'] && strtotime((string) $row['expire_at']) < time()) {
            $this->releaseOrder($row, 'timeout');
            throw new ApiException('ORDER_EXPIRED', '訂單已超時關閉', 422, 42242);
        }
        $now = date('Y-m-d H:i:s');
        $payId = (int) Db::name('payments')->insertGetId([
            'order_id' => $orderId,
            'channel' => $channel,
            'amount' => $row['pay_amount'],
            'status' => 'pending',
            'mock_ref' => 'MOCK-' . strtoupper($channel) . '-' . $orderId . '-' . time(),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return [
            'payment_id' => $payId,
            'channel' => $channel,
            'amount' => (float) $row['pay_amount'],
            'mock_ref' => Db::name('payments')->where('id', $payId)->value('mock_ref'),
            'order' => $this->presentSummary($row),
        ];
    }

    public function mockConfirmPayment(int $paymentId): array
    {
        $pay = Db::name('payments')->where('id', $paymentId)->find();
        if (!$pay) {
            throw new ApiException('NOT_FOUND', '支付單不存在', 404, 40441);
        }
        if ($pay['status'] === 'paid') {
            return $this->presentDetail($this->findOrder((int) $pay['order_id']));
        }
        $order = $this->findOrder((int) $pay['order_id']);
        if ($order['status'] !== 'pending_payment') {
            throw new ApiException('INVALID_STATUS', '訂單已不可支付', 422, 42241);
        }
        $now = date('Y-m-d H:i:s');
        Db::transaction(function () use ($pay, $order, $now) {
            Db::name('payments')->where('id', $pay['id'])->update([
                'status' => 'paid', 'paid_at' => $now, 'updated_at' => $now,
            ]);
            Db::name('orders')->where('id', $order['id'])->update([
                'status' => 'pending_shipment',
                'pay_channel' => $pay['channel'],
                'paid_at' => $now,
                'expire_at' => null,
                'updated_at' => $now,
            ]);
            if ($order['coupon_grant_id']) {
                Db::name('coupon_grants')->where('id', $order['coupon_grant_id'])->update([
                    'status' => 'used', 'updated_at' => $now,
                ]);
            }
        });

        return $this->presentDetail($this->findOrder((int) $order['id']));
    }

    public function closeExpired(): array
    {
        $rows = Db::name('orders')->where('status', 'pending_payment')
            ->where('expire_at', '<=', date('Y-m-d H:i:s'))
            ->select()->toArray();
        $closed = 0;
        foreach ($rows as $row) {
            $this->releaseOrder($row, 'timeout');
            $closed++;
        }

        return ['closed' => $closed];
    }

    private function resolveLines(int $memberId, array $body): array
    {
        if (!empty($body['buy_now'])) {
            $skuId = (int) ($body['sku_id'] ?? 0);
            $qty = max(1, (int) ($body['qty'] ?? 1));
            $cart = new CartService();
            // reuse present via temporary add? better inline
            $sku = Db::name('skus')->where('id', $skuId)->where('status', 1)->find();
            if (!$sku || (int) $sku['stock'] < $qty) {
                throw new ApiException('OUT_OF_STOCK', '庫存不足', 422, 42220);
            }
            $product = Db::name('products')->where('id', $sku['product_id'])->where('status', 1)->find();
            if (!$product) {
                throw new ApiException('NOT_FOUND', '商品已下架', 404, 40402);
            }
            $names = Db::name('product_i18n')->where('product_id', $product['id'])->select()->toArray();
            $name = ['zh-HK' => '', 'en' => ''];
            foreach ($names as $n) {
                $name[$n['locale']] = $n['name'];
            }
            $spec = ['zh-HK' => '', 'en' => ''];
            $optionIds = Db::name('sku_options')->where('sku_id', $skuId)->column('spec_option_id');
            if ($optionIds) {
                $rows = Db::name('spec_options')->alias('o')
                    ->join('spec_option_i18n i', 'i.spec_option_id = o.id')
                    ->whereIn('o.id', $optionIds)->field('i.locale,i.name')->select()->toArray();
                $zh = [];
                $en = [];
                foreach ($rows as $r) {
                    if ($r['locale'] === 'en') {
                        $en[] = $r['name'];
                    } else {
                        $zh[] = $r['name'];
                    }
                }
                $spec = ['zh-HK' => implode(' / ', $zh), 'en' => implode(' / ', $en)];
            }

            return [[
                'id' => 0,
                'product_id' => (string) $product['code'],
                'product_db_id' => (int) $product['id'],
                'sku_id' => $skuId,
                'sku_code' => (string) $sku['code'],
                'name' => $name,
                'spec' => $spec,
                'price' => (float) $sku['price'],
                'qty' => $qty,
                'image' => $sku['image_url'] ?: $product['cover_url'],
                'available' => true,
                'checked' => true,
            ]];
        }

        return $this->cart->checkedLines($memberId);
    }

    private function compute(array $member, array $lines, array $body): array
    {
        $goods = 0.0;
        foreach ($lines as $line) {
            $goods += $line['price'] * $line['qty'];
        }
        $goods = round($goods, 2);

        $couponAmount = 0.0;
        $couponGrantId = null;
        $grantId = isset($body['coupon_grant_id']) ? (int) $body['coupon_grant_id'] : 0;
        $coupons = $this->availableCoupons((int) $member['id'], $goods);
        if ($grantId) {
            $hit = null;
            foreach ($coupons as $c) {
                if ($c['grant_id'] === $grantId && $c['usable']) {
                    $hit = $c;
                    break;
                }
            }
            if (!$hit) {
                throw new ApiException('COUPON_INVALID', '優惠券不可用', 422, 42232);
            }
            $couponAmount = (float) $hit['amount'];
            $couponGrantId = $grantId;
        }

        $afterCoupon = max(0, round($goods - $couponAmount, 2));

        $pointsAmount = 0.0;
        $pointsUsed = 0;
        $per = TradeSettings::pointsPerHkd();
        $maxPercent = TradeSettings::pointsMaxPercent();
        $maxByPercent = round($afterCoupon * $maxPercent / 100, 2);
        $memberPoints = (int) ($member['points'] ?? 0);
        $maxByPoints = round($memberPoints / $per, 2);
        $pointsCap = min($maxByPercent, $maxByPoints);
        if (!empty($body['use_points']) && $pointsCap > 0) {
            $pointsAmount = $pointsCap;
            $pointsUsed = (int) ceil($pointsAmount * $per);
            if ($pointsUsed > $memberPoints) {
                $pointsUsed = $memberPoints;
                $pointsAmount = round($pointsUsed / $per, 2);
            }
        }

        $afterPoints = max(0, round($afterCoupon - $pointsAmount, 2));
        $shipping = TradeSettings::shippingFor($afterCoupon);

        $balanceAmount = 0.0;
        $memberBalance = (float) ($member['balance'] ?? 0);
        $needCash = round($afterPoints + $shipping, 2);
        if (!empty($body['use_balance']) && $memberBalance > 0 && $needCash > 0) {
            $balanceAmount = min($memberBalance, $needCash);
        }
        $payAmount = max(0, round($needCash - $balanceAmount, 2));

        return [
            'items' => $lines,
            'goods_amount' => $goods,
            'coupon_amount' => $couponAmount,
            'coupon_grant_id' => $couponGrantId,
            'coupons' => $coupons,
            'points_amount' => $pointsAmount,
            'points_used' => $pointsUsed,
            'points_available' => $memberPoints,
            'points_cap_amount' => $pointsCap,
            'balance_amount' => $balanceAmount,
            'balance_available' => $memberBalance,
            'shipping_amount' => $shipping,
            'free_threshold' => TradeSettings::freeThreshold(),
            'shipping_fee' => TradeSettings::shippingFee(),
            'pay_amount' => $payAmount,
            'zero_pay' => $payAmount <= 0,
        ];
    }

    private function availableCoupons(int $memberId, float $goods): array
    {
        $rows = Db::name('coupon_grants')->alias('g')
            ->join('coupons c', 'c.id = g.coupon_id')
            ->where('g.member_id', $memberId)
            ->where('g.status', 'unused')
            ->where('c.status', 1)
            ->field('g.id as grant_id, c.code, c.name_zh, c.name_en, c.threshold, c.amount')
            ->select()->toArray();
        $out = [];
        foreach ($rows as $r) {
            $usable = $goods >= (float) $r['threshold'];
            $out[] = [
                'grant_id' => (int) $r['grant_id'],
                'code' => $r['code'],
                'name' => ['zh-HK' => $r['name_zh'], 'en' => $r['name_en']],
                'threshold' => (float) $r['threshold'],
                'amount' => (float) $r['amount'],
                'usable' => $usable,
                'reason' => $usable ? '' : 'below_threshold',
            ];
        }

        return $out;
    }

    private function findOrder(int|string $idOrNo): array
    {
        $row = is_numeric($idOrNo)
            ? Db::name('orders')->where('id', (int) $idOrNo)->find()
            : Db::name('orders')->where('order_no', (string) $idOrNo)->find();
        if (!$row) {
            throw new ApiException('NOT_FOUND', '訂單不存在', 404, 40440);
        }

        return $row;
    }

    private function releaseOrder(array $row, string $reason): void
    {
        if ($row['status'] !== 'pending_payment') {
            return;
        }
        $now = date('Y-m-d H:i:s');
        Db::transaction(function () use ($row, $reason, $now) {
            $locked = Db::name('orders')->where('id', $row['id'])->where('status', 'pending_payment')->find();
            if (!$locked) {
                return;
            }
            $items = Db::name('order_items')->where('order_id', $row['id'])->select()->toArray();
            foreach ($items as $item) {
                Db::name('skus')->where('id', $item['sku_id'])->inc('stock', (int) $item['qty'])->update(['updated_at' => $now]);
            }
            if ((int) $row['points_used'] > 0) {
                Db::name('members')->where('id', $row['member_id'])->inc('points', (int) $row['points_used'])->update(['updated_at' => $now]);
            }
            if ((float) $row['balance_amount'] > 0) {
                Db::name('members')->where('id', $row['member_id'])->inc('balance', (float) $row['balance_amount'])->update(['updated_at' => $now]);
            }
            if ($row['coupon_grant_id']) {
                Db::name('coupon_grants')->where('id', $row['coupon_grant_id'])->where('status', 'frozen')->update([
                    'status' => 'unused', 'order_id' => null, 'updated_at' => $now,
                ]);
            }
            Db::name('orders')->where('id', $row['id'])->update([
                'status' => 'cancelled',
                'cancel_reason' => $reason,
                'updated_at' => $now,
            ]);
            Db::name('payments')->where('order_id', $row['id'])->where('status', 'pending')->update([
                'status' => 'closed', 'updated_at' => $now,
            ]);
        });
    }

    private function presentSummary(array $row, bool $withMember = false): array
    {
        $items = Db::name('order_items')->where('order_id', $row['id'])->select()->toArray();
        $data = [
            'id' => (int) $row['id'],
            'order_no' => $row['order_no'],
            'status' => $row['status'],
            'pay_amount' => (float) $row['pay_amount'],
            'goods_amount' => (float) $row['goods_amount'],
            'shipping_amount' => (float) $row['shipping_amount'],
            'created_at' => $row['created_at'],
            'paid_at' => $row['paid_at'],
            'expire_at' => $row['expire_at'],
            'item_count' => count($items),
            'cover' => $items[0]['image_url'] ?? '',
            'title' => $items[0]['name_zh'] ?? '',
        ];
        if ($withMember) {
            $data['member_id'] = (int) $row['member_id'];
        }

        return $data;
    }

    private function presentDetail(array $row): array
    {
        $items = Db::name('order_items')->where('order_id', $row['id'])->select()->toArray();
        $addr = json_decode((string) $row['address_snapshot'], true) ?: [];

        return [
            'id' => (int) $row['id'],
            'order_no' => $row['order_no'],
            'status' => $row['status'],
            'remark' => $row['remark'],
            'address' => $addr,
            'goods_amount' => (float) $row['goods_amount'],
            'coupon_amount' => (float) $row['coupon_amount'],
            'points_amount' => (float) $row['points_amount'],
            'points_used' => (int) $row['points_used'],
            'balance_amount' => (float) $row['balance_amount'],
            'shipping_amount' => (float) $row['shipping_amount'],
            'pay_amount' => (float) $row['pay_amount'],
            'pay_channel' => $row['pay_channel'],
            'paid_at' => $row['paid_at'],
            'expire_at' => $row['expire_at'],
            'cancel_reason' => $row['cancel_reason'],
            'created_at' => $row['created_at'],
            'items' => array_map(fn ($i) => [
                'product_code' => $i['product_code'],
                'sku_code' => $i['sku_code'],
                'name' => ['zh-HK' => $i['name_zh'], 'en' => $i['name_en']],
                'spec' => ['zh-HK' => $i['spec_zh'], 'en' => $i['spec_en']],
                'image' => $i['image_url'],
                'unit_price' => (float) $i['unit_price'],
                'qty' => (int) $i['qty'],
                'line_amount' => (float) $i['line_amount'],
            ], $items),
        ];
    }
}
