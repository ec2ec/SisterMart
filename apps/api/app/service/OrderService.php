<?php

declare(strict_types=1);

namespace app\service;

use app\service\payment\PaymentChannelFactory;
use app\support\ApiException;
use think\facade\Db;

class OrderService
{
    public function __construct(
        private CartService $cart = new CartService(),
        private AddressService $addresses = new AddressService(),
        private AssetService $assets = new AssetService(),
        private CouponService $coupons = new CouponService(),
        private SeckillService $seckill = new SeckillService(),
        private PointsMallService $pointsMall = new PointsMallService(),
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

        $seckillItemId = !empty($body['seckill_item_id']) ? (int) $body['seckill_item_id'] : 0;
        $pointsMallItemId = !empty($body['points_mall_item_id']) ? (int) $body['points_mall_item_id'] : 0;
        if ($seckillItemId > 0 && $pointsMallItemId > 0) {
            throw new ApiException('INVALID_ORDER', '秒殺與積分兌換不可同單', 422, 42283);
        }
        $orderId = 0;
        Db::transaction(function () use ($memberId, $address, $lines, $quote, $body, $now, $expireAt, $seckillItemId, $pointsMallItemId, &$orderId) {
            if ($seckillItemId > 0) {
                $qty = (int) ($lines[0]['qty'] ?? 1);
                $this->seckill->assertLiveItem($seckillItemId, $qty, true);
                $this->seckill->deduct($seckillItemId, $qty, $now);
            }
            if ($pointsMallItemId > 0) {
                $qty = (int) ($lines[0]['qty'] ?? 1);
                $this->pointsMall->assertAvailable($pointsMallItemId, $qty, true);
                $this->pointsMall->deduct($pointsMallItemId, $qty, $now);
            }
            foreach ($lines as $line) {
                $affected = Db::name('skus')->where('id', $line['sku_id'])->where('stock', '>=', $line['qty'])
                    ->dec('stock', $line['qty'])->update(['updated_at' => $now]);
                if (!$affected) {
                    throw new ApiException('OUT_OF_STOCK', '庫存不足，無法下單', 422, 42220);
                }
            }

            $pointsUsed = (int) $quote['points_used'];
            $balanceUsed = (float) $quote['balance_amount'];
            // 先佔位寫入訂單，流水 ref_id 於插入後回填；此處扣減仍先執行
            if ($pointsUsed > 0) {
                $type = $pointsMallItemId > 0 ? 'points_mall_redeem' : 'order_deduct';
                $remark = $pointsMallItemId > 0 ? '積分商城兌換' : '下單抵扣';
                $this->assets->changePoints($memberId, -$pointsUsed, $type, 'order', null, $remark);
            }
            if ($balanceUsed > 0) {
                $this->assets->changeBalance($memberId, -$balanceUsed, 'order_deduct', 'order', null, '下單抵扣');
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
                'use_points' => $pointsMallItemId > 0 ? 1 : (!empty($body['use_points']) ? 1 : 0),
                'use_balance' => !empty($body['use_balance']) ? 1 : 0,
                'coupon_grant_id' => $quote['coupon_grant_id'],
                'seckill_item_id' => $seckillItemId > 0 ? $seckillItemId : null,
                'points_mall_item_id' => $pointsMallItemId > 0 ? $pointsMallItemId : null,
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

            if ($pointsUsed > 0) {
                $logType = $pointsMallItemId > 0 ? 'points_mall_redeem' : 'order_deduct';
                Db::name('point_logs')->where([
                    'member_id' => $memberId,
                    'type' => $logType,
                    'ref_type' => 'order',
                ])->whereNull('ref_id')->order('id', 'desc')->limit(1)->update(['ref_id' => $orderId]);
            }
            if ($balanceUsed > 0) {
                Db::name('balance_logs')->where([
                    'member_id' => $memberId,
                    'type' => 'order_deduct',
                    'ref_type' => 'order',
                ])->whereNull('ref_id')->order('id', 'desc')->limit(1)->update(['ref_id' => $orderId]);
            }

            if (empty($body['buy_now']) && $seckillItemId <= 0 && $pointsMallItemId <= 0) {
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
        $factory = new PaymentChannelFactory();
        $driver = $factory->make($channel);
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
            'mock_ref' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $created = $driver->create([
            'payment_id' => $payId,
            'order_id' => $orderId,
            'order_no' => (string) $row['order_no'],
            'amount' => (float) $row['pay_amount'],
            'currency' => 'HKD',
        ]);

        if (!empty($created['mock_ref'])) {
            Db::name('payments')->where('id', $payId)->update([
                'mock_ref' => $created['mock_ref'],
                'updated_at' => $now,
            ]);
        }

        return [
            'payment_id' => $payId,
            'channel' => $channel,
            'amount' => (float) $row['pay_amount'],
            'mode' => $created['mode'] ?? $factory->mode(),
            'mock_ref' => $created['mock_ref'] ?? Db::name('payments')->where('id', $payId)->value('mock_ref'),
            'next' => $created['next'] ?? null,
            'order' => $this->presentSummary($row),
        ];
    }

    public function mockConfirmPayment(int $paymentId): array
    {
        $now = date('Y-m-d H:i:s');
        $orderId = 0;
        Db::transaction(function () use ($paymentId, $now, &$orderId) {
            $pay = Db::name('payments')->where('id', $paymentId)->lock(true)->find();
            if (!$pay) {
                throw new ApiException('NOT_FOUND', '支付單不存在', 404, 40441);
            }
            $orderId = (int) $pay['order_id'];
            // 冪等：已支付直接返回
            if ($pay['status'] === 'paid') {
                return;
            }
            $order = Db::name('orders')->where('id', $orderId)->lock(true)->find();
            if (!$order) {
                throw new ApiException('NOT_FOUND', '訂單不存在', 404, 40440);
            }
            if ($order['status'] !== 'pending_payment') {
                throw new ApiException('INVALID_STATUS', '訂單已不可支付', 422, 42241);
            }
            Db::name('payments')->where('id', $pay['id'])->where('status', 'pending')->update([
                'status' => 'paid', 'paid_at' => $now, 'updated_at' => $now,
            ]);
            Db::name('orders')->where('id', $order['id'])->where('status', 'pending_payment')->update([
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

        return $this->presentDetail($this->findOrder($orderId));
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
        $pointsMallItemId = (int) ($body['points_mall_item_id'] ?? 0);
        if ($pointsMallItemId > 0) {
            if (!empty($body['seckill_item_id']) || !empty($body['buy_now']) || !empty($body['sku_id'])) {
                throw new ApiException('INVALID_ORDER', '積分兌換須單獨下單', 422, 42283);
            }
            $qty = max(1, min(1, (int) ($body['qty'] ?? 1)));
            $locked = $this->pointsMall->assertAvailable($pointsMallItemId, $qty);
            $item = $locked['item'];
            $sku = Db::name('skus')->where('id', $item['sku_id'])->where('status', 1)->find();
            if (!$sku || (int) $sku['stock'] < $qty) {
                throw new ApiException('OUT_OF_STOCK', '庫存不足', 422, 42220);
            }
            $product = Db::name('products')->where('id', $item['product_id'])->where('status', 1)->find();
            if (!$product) {
                throw new ApiException('NOT_FOUND', '商品已下架', 404, 40402);
            }
            $spec = $this->skuSpecText((int) $item['sku_id']);

            return [[
                'id' => 0,
                'product_id' => (string) $item['product_code'],
                'product_db_id' => (int) $item['product_id'],
                'sku_id' => (int) $item['sku_id'],
                'sku_code' => (string) $sku['code'],
                'name' => ['zh-HK' => $item['name_zh'], 'en' => $item['name_en']],
                'spec' => $spec,
                'price' => 0.0,
                'qty' => $qty,
                'image' => $item['image_url'] ?: ($sku['image_url'] ?: $product['cover_url']),
                'available' => true,
                'checked' => true,
                'points_mall_item_id' => $pointsMallItemId,
                'points_cost' => (int) $item['points_cost'],
            ]];
        }

        $seckillItemId = (int) ($body['seckill_item_id'] ?? 0);
        if ($seckillItemId > 0) {
            $qty = 1; // 秒殺限購 1
            $locked = $this->seckill->assertLiveItem($seckillItemId, $qty);
            $item = $locked['item'];
            $sku = Db::name('skus')->where('id', $item['sku_id'])->where('status', 1)->find();
            if (!$sku || (int) $sku['stock'] < $qty) {
                throw new ApiException('OUT_OF_STOCK', '庫存不足', 422, 42220);
            }
            $product = Db::name('products')->where('id', $item['product_id'])->where('status', 1)->find();
            if (!$product) {
                throw new ApiException('NOT_FOUND', '商品已下架', 404, 40402);
            }
            $spec = $this->skuSpecText((int) $item['sku_id']);

            return [[
                'id' => 0,
                'product_id' => (string) $item['product_code'],
                'product_db_id' => (int) $item['product_id'],
                'sku_id' => (int) $item['sku_id'],
                'sku_code' => (string) $sku['code'],
                'name' => ['zh-HK' => $item['name_zh'], 'en' => $item['name_en']],
                'spec' => $spec,
                'price' => (float) $item['seckill_price'],
                'qty' => $qty,
                'image' => $item['image_url'] ?: ($sku['image_url'] ?: $product['cover_url']),
                'available' => true,
                'checked' => true,
                'seckill_item_id' => $seckillItemId,
            ]];
        }

        if (!empty($body['buy_now'])) {
            $skuId = (int) ($body['sku_id'] ?? 0);
            $qty = max(1, (int) ($body['qty'] ?? 1));
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
            $spec = $this->skuSpecText($skuId);

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

    private function skuSpecText(int $skuId): array
    {
        $spec = ['zh-HK' => '', 'en' => ''];
        $optionIds = Db::name('sku_options')->where('sku_id', $skuId)->column('spec_option_id');
        if (!$optionIds) {
            return $spec;
        }
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

        return ['zh-HK' => implode(' / ', $zh), 'en' => implode(' / ', $en)];
    }

    private function compute(array $member, array $lines, array $body): array
    {
        $pointsMallItemId = (int) ($body['points_mall_item_id'] ?? 0);
        $memberPoints = (int) ($member['points'] ?? 0);
        $memberBalance = (float) ($member['balance'] ?? 0);

        // M12 積分商城：商品用積分兌換，不用券/餘額/現金購貨；運費仍按現金規則（可 mock 支付）
        if ($pointsMallItemId > 0) {
            $pointsCost = 0;
            foreach ($lines as $line) {
                $pointsCost += (int) ($line['points_cost'] ?? 0) * (int) $line['qty'];
            }
            if ($memberPoints < $pointsCost) {
                throw new ApiException('POINTS_SHORT', '積分不足', 422, 42230);
            }
            $shipping = TradeSettings::shippingFor(0.0);
            $payAmount = $shipping;

            return [
                'items' => $lines,
                'goods_amount' => 0.0,
                'coupon_amount' => 0.0,
                'coupon_grant_id' => null,
                'coupon_type' => null,
                'coupons' => [],
                'points_amount' => 0.0,
                'points_used' => $pointsCost,
                'points_available' => $memberPoints,
                'points_cap_amount' => 0.0,
                'points_mall' => true,
                'points_cost' => $pointsCost,
                'balance_amount' => 0.0,
                'balance_available' => $memberBalance,
                'shipping_amount' => $shipping,
                'free_threshold' => TradeSettings::freeThreshold(),
                'shipping_fee' => TradeSettings::shippingFee(),
                'pay_amount' => $payAmount,
                'zero_pay' => $payAmount <= 0,
            ];
        }

        $goods = 0.0;
        foreach ($lines as $line) {
            $goods += $line['price'] * $line['qty'];
        }
        $goods = round($goods, 2);

        $couponAmount = 0.0;
        $couponGrantId = null;
        $couponType = null;
        $freeShipping = false;
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
            $couponAmount = (float) $hit['discount_amount'];
            $couponGrantId = $grantId;
            $couponType = $hit['type'];
            $freeShipping = $hit['type'] === 'free_shipping';
        }

        $afterCoupon = max(0, round($goods - $couponAmount, 2));

        $pointsAmount = 0.0;
        $pointsUsed = 0;
        $per = TradeSettings::pointsPerHkd();
        $maxPercent = TradeSettings::pointsMaxPercent();
        $maxByPercent = round($afterCoupon * $maxPercent / 100, 2);
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
        // 免運券：運費歸零，coupon_amount 僅記商品減免（免運為 0），避免影響累計實付/發積分基數
        $shipping = $freeShipping ? 0.0 : TradeSettings::shippingFor($afterCoupon);

        $balanceAmount = 0.0;
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
            'coupon_type' => $couponType,
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
        $now = date('Y-m-d H:i:s');
        $rows = Db::name('coupon_grants')->alias('g')
            ->join('coupons c', 'c.id = g.coupon_id')
            ->where('g.member_id', $memberId)
            ->where('g.status', 'unused')
            ->where('c.status', 1)
            ->whereRaw('(g.expires_at IS NULL OR g.expires_at >= ?)', [$now])
            ->field('g.id as grant_id, c.code, c.name_zh, c.name_en, c.type, c.threshold, c.amount')
            ->select()->toArray();
        $out = [];
        foreach ($rows as $r) {
            $usable = $goods >= (float) $r['threshold'];
            $discount = $usable ? $this->coupons->discountForGoods($r, $goods) : 0.0;
            $display = $discount;
            if ($usable && $r['type'] === 'free_shipping') {
                $display = TradeSettings::shippingFor($goods);
            }
            $out[] = [
                'grant_id' => (int) $r['grant_id'],
                'code' => $r['code'],
                'name' => ['zh-HK' => $r['name_zh'], 'en' => $r['name_en']],
                'type' => $r['type'],
                'threshold' => (float) $r['threshold'],
                'amount' => (float) $r['amount'],
                'discount_amount' => $discount,
                'display_amount' => $display,
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
            $seckillItemId = (int) ($row['seckill_item_id'] ?? 0);
            if ($seckillItemId > 0) {
                $qty = (int) ($items[0]['qty'] ?? 1);
                $this->seckill->release($seckillItemId, $qty, $now);
            }
            $pointsMallItemId = (int) ($row['points_mall_item_id'] ?? 0);
            if ($pointsMallItemId > 0) {
                $qty = (int) ($items[0]['qty'] ?? 1);
                $this->pointsMall->release($pointsMallItemId, $qty, $now);
            }
            if ((int) $row['points_used'] > 0) {
                $this->assets->changePoints(
                    (int) $row['member_id'],
                    (int) $row['points_used'],
                    'order_release',
                    'order',
                    (int) $row['id'],
                    '關單退回積分',
                );
            }
            if ((float) $row['balance_amount'] > 0) {
                $this->assets->changeBalance(
                    (int) $row['member_id'],
                    (float) $row['balance_amount'],
                    'order_release',
                    'order',
                    (int) $row['id'],
                    '關單退回餘額',
                );
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
            'shipped_at' => $row['shipped_at'] ?? null,
            'completed_at' => $row['completed_at'] ?? null,
            'aftersale_touched' => (int) ($row['aftersale_touched'] ?? 0) === 1,
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
            'shipped_at' => $row['shipped_at'] ?? null,
            'completed_at' => $row['completed_at'] ?? null,
            'aftersale_touched' => (int) ($row['aftersale_touched'] ?? 0) === 1,
            'points_awarded' => (int) ($row['points_awarded'] ?? 0),
            'seckill_item_id' => $row['seckill_item_id'] !== null ? (int) $row['seckill_item_id'] : null,
            'points_mall_item_id' => isset($row['points_mall_item_id']) && $row['points_mall_item_id'] !== null
                ? (int) $row['points_mall_item_id']
                : null,
            'expire_at' => $row['expire_at'],
            'cancel_reason' => $row['cancel_reason'],
            'created_at' => $row['created_at'],
            'items' => array_map(fn ($i) => [
                'id' => (int) $i['id'],
                'product_code' => $i['product_code'],
                'sku_code' => $i['sku_code'],
                'name' => ['zh-HK' => $i['name_zh'], 'en' => $i['name_en']],
                'spec' => ['zh-HK' => $i['spec_zh'], 'en' => $i['spec_en']],
                'image' => $i['image_url'],
                'unit_price' => (float) $i['unit_price'],
                'qty' => (int) $i['qty'],
                'refunded_qty' => (int) ($i['refunded_qty'] ?? 0),
                'line_amount' => (float) $i['line_amount'],
            ], $items),
        ];
    }
}
