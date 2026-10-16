<?php

declare(strict_types=1);

namespace app\service;

use app\support\ApiException;
use think\facade\Db;

/**
 * M7 輔助功能 Mock：秒殺 / 積分商城 / 簽到 / 分銷 / 消息。
 * 明確為演示用，二期做實。不影響 M4～M6 真實下單庫存。
 */
class MockMarketingService
{
    public function seckillSessions(): array
    {
        $now = time();
        $items = [
            [
                'id' => 'sk-live',
                'name' => ['zh-HK' => '午間閃購', 'en' => 'Noon Flash'],
                'status' => 'live',
                'starts_at' => date('Y-m-d H:i:s', $now - 1800),
                'ends_at' => date('Y-m-d H:i:s', $now + 7200),
                'products' => $this->seckillProducts('live'),
            ],
            [
                'id' => 'sk-soon',
                'name' => ['zh-HK' => '晚間預告', 'en' => 'Evening Soon'],
                'status' => 'upcoming',
                'starts_at' => date('Y-m-d H:i:s', $now + 3600),
                'ends_at' => date('Y-m-d H:i:s', $now + 10800),
                'products' => $this->seckillProducts('upcoming'),
            ],
        ];

        return ['items' => $items, 'mock' => true];
    }

    public function seckillDetail(string $id): array
    {
        $sessions = $this->seckillSessions()['items'];
        foreach ($sessions as $s) {
            if ($s['id'] === $id) {
                return array_merge($s, ['mock' => true]);
            }
        }
        throw new ApiException('NOT_FOUND', '秒殺場次不存在', 404, 40480);
    }

    public function seckillProduct(string $productCode): array
    {
        foreach ($this->seckillSessions()['items'] as $s) {
            foreach ($s['products'] as $p) {
                if ($p['product_id'] === $productCode && $s['status'] === 'live') {
                    return array_merge($p, [
                        'session_id' => $s['id'],
                        'session_status' => $s['status'],
                        'ends_at' => $s['ends_at'],
                        'mock' => true,
                    ]);
                }
            }
        }

        return ['in_seckill' => false, 'mock' => true];
    }

    public function pointsMall(): array
    {
        return [
            'mock' => true,
            'items' => [
                [
                    'id' => 'pm1',
                    'product_id' => 'p3',
                    'name' => ['zh-HK' => '四葉草手鏈（積分兌）', 'en' => 'Clover (Points)'],
                    'image' => '/mock/product-3.jpg',
                    'points_cost' => 800,
                    'stock' => 20,
                ],
                [
                    'id' => 'pm2',
                    'product_id' => 'p4',
                    'name' => ['zh-HK' => '紅心手鏈（積分兌）', 'en' => 'Heart (Points)'],
                    'image' => '/mock/product-4.jpg',
                    'points_cost' => 1200,
                    'stock' => 8,
                ],
            ],
        ];
    }

    public function pointsMallDetail(string $id): array
    {
        foreach ($this->pointsMall()['items'] as $item) {
            if ($item['id'] === $id) {
                return array_merge($item, [
                    'mock' => true,
                    'detail' => [
                        'zh-HK' => '積分商城 Mock 商品。結算僅扣積分，二期接真實庫存。',
                        'en' => 'Points-mall mock item. Phase-2 will debit real stock.',
                    ],
                ]);
            }
        }
        throw new ApiException('NOT_FOUND', '積分商品不存在', 404, 40481);
    }

    public function checkinCalendar(int $memberId): array
    {
        $year = (int) date('Y');
        $month = (int) date('n');
        $daysInMonth = (int) date('t');
        $today = (int) date('j');
        $key = 'mock.checkin.' . $memberId . '.' . date('Y-m');
        $signed = $this->settingJson($key, []);
        $streak = (int) ($signed['streak'] ?? 0);
        $days = [];
        for ($d = 1; $d <= $daysInMonth; $d++) {
            $days[] = [
                'day' => $d,
                'signed' => in_array($d, $signed['days'] ?? [], true),
                'today' => $d === $today,
            ];
        }

        return [
            'mock' => true,
            'year' => $year,
            'month' => $month,
            'streak' => $streak,
            'reward_today' => 10 + min(20, $streak),
            'signed_today' => in_array($today, $signed['days'] ?? [], true),
            'days' => $days,
        ];
    }

    public function checkin(int $memberId): array
    {
        $cal = $this->checkinCalendar($memberId);
        if ($cal['signed_today']) {
            throw new ApiException('ALREADY_SIGNED', '今日已簽到', 422, 42280);
        }
        $today = (int) date('j');
        $key = 'mock.checkin.' . $memberId . '.' . date('Y-m');
        $signed = $this->settingJson($key, ['days' => [], 'streak' => 0]);
        $signed['days'][] = $today;
        $signed['streak'] = ((int) ($signed['streak'] ?? 0)) + 1;
        $reward = 10 + min(20, (int) $signed['streak'] - 1);
        $this->saveSetting($key, $signed);

        // Mock：真正加積分並寫流水（可演示），標註來源為簽到
        (new AssetService())->changePoints($memberId, $reward, 'checkin', 'checkin', null, '每日簽到（Mock）');

        return [
            'mock' => true,
            'reward' => $reward,
            'streak' => (int) $signed['streak'],
            'points' => (int) Db::name('members')->where('id', $memberId)->value('points'),
        ];
    }

    public function distributionOverview(int $memberId): array
    {
        $member = Db::name('members')->where('id', $memberId)->find();
        $promo = (string) ($member['promo_code'] ?? '');

        return [
            'mock' => true,
            'promo_code' => $promo,
            'available_commission' => 128.5,
            'pending_commission' => 36.0,
            'total_commission' => 520.8,
            'downline_count' => 3,
            'share_link' => '/?ref=' . $promo,
        ];
    }

    public function downlines(int $memberId): array
    {
        $real = Db::name('members')->where('parent_id', $memberId)->order('id', 'desc')->limit(20)->select()->toArray();
        $items = array_map(fn ($r) => [
            'id' => (int) $r['id'],
            'nickname' => $r['email'] ?: $r['phone'] ?: ('M' . $r['id']),
            'avatar' => '',
            'registered_at' => $r['created_at'],
            'spent' => (float) ($r['total_spent'] ?? 0),
            'commission_earned' => round(((float) ($r['total_spent'] ?? 0)) * 0.05, 2),
            'mock' => false,
        ], $real);
        if (!$items) {
            $items = [
                ['id' => 9001, 'nickname' => '演示下級 A', 'avatar' => '', 'registered_at' => date('Y-m-d H:i:s', time() - 86400 * 12), 'spent' => 320, 'commission_earned' => 16, 'mock' => true],
                ['id' => 9002, 'nickname' => '演示下級 B', 'avatar' => '', 'registered_at' => date('Y-m-d H:i:s', time() - 86400 * 5), 'spent' => 88, 'commission_earned' => 4.4, 'mock' => true],
            ];
        }

        return ['mock' => true, 'items' => $items];
    }

    public function commissions(): array
    {
        return [
            'mock' => true,
            'items' => [
                ['id' => 1, 'order_no' => 'SMDEMO1001', 'amount' => 12.8, 'status' => 'settled', 'created_at' => date('Y-m-d H:i:s', time() - 86400)],
                ['id' => 2, 'order_no' => 'SMDEMO1002', 'amount' => 6.4, 'status' => 'pending', 'created_at' => date('Y-m-d H:i:s', time() - 3600)],
            ],
        ];
    }

    public function promoOrders(): array
    {
        return [
            'mock' => true,
            'items' => [
                ['order_no' => 'SMDEMO1001', 'title' => '方鑽項鏈', 'commission' => 12.8, 'status' => 'settled'],
                ['order_no' => 'SMDEMO1002', 'title' => '四葉草手鏈', 'commission' => 6.4, 'status' => 'pending'],
                ['order_no' => 'SMDEMO1003', 'title' => '紅心手鏈', 'commission' => 0, 'status' => 'void'],
            ],
        ];
    }

    public function ranking(string $period = 'month'): array
    {
        return [
            'mock' => true,
            'period' => $period === 'total' ? 'total' : 'month',
            'items' => [
                ['rank' => 1, 'nickname' => 'Star Seller', 'commission' => 880],
                ['rank' => 2, 'nickname' => 'Jade Club', 'commission' => 640],
                ['rank' => 3, 'nickname' => 'You (Mock)', 'commission' => 128.5],
            ],
        ];
    }

    public function withdraw(int $memberId, array $body): array
    {
        $channel = (string) ($body['channel'] ?? 'balance');
        $amount = round((float) ($body['amount'] ?? 0), 2);
        if ($amount <= 0) {
            throw new ApiException('INVALID', '請填寫提現金額', 422, 42281);
        }
        if ($channel === 'balance') {
            (new AssetService())->changeBalance($memberId, $amount, 'commission_to_balance', 'withdraw', null, '佣金轉入餘額（Mock）');

            return ['mock' => true, 'channel' => 'balance', 'amount' => $amount, 'status' => 'done'];
        }
        if ($channel !== 'fps') {
            throw new ApiException('INVALID', '提現渠道不正確', 422, 42282);
        }
        $name = trim((string) ($body['fps_name'] ?? ''));
        $fpsId = trim((string) ($body['fps_id'] ?? ''));
        if ($name === '' || $fpsId === '') {
            throw new ApiException('INVALID', '請填寫轉數快資料', 422, 42283);
        }

        return [
            'mock' => true,
            'channel' => 'fps',
            'amount' => $amount,
            'status' => 'pending_review',
            'fps_name' => $name,
            'fps_id' => $fpsId,
        ];
    }

    public function poster(int $memberId, ?string $productId = null): array
    {
        $promo = (string) Db::name('members')->where('id', $memberId)->value('promo_code');
        $productId = $productId ?: 'p1';

        return [
            'mock' => true,
            'product_id' => $productId,
            'name' => ['zh-HK' => '推廣海報商品', 'en' => 'Poster product'],
            'price' => 128,
            'image' => '/mock/product-1.jpg',
            'qr_payload' => '/product/' . $productId . '?ref=' . $promo,
            'promo_code' => $promo,
        ];
    }

    public function messages(int $memberId, ?string $type = null): array
    {
        [$orderMsgs, $systemMsgs] = $this->allMessages($memberId);
        if ($type === 'order') {
            return ['mock' => true, 'items' => $orderMsgs];
        }
        if ($type === 'system') {
            return ['mock' => true, 'items' => $systemMsgs];
        }
        $unread = 0;
        foreach (array_merge($orderMsgs, $systemMsgs) as $m) {
            if (empty($m['read'])) {
                $unread++;
            }
        }

        return [
            'mock' => true,
            'order' => $orderMsgs,
            'system' => $systemMsgs,
            'unread' => $unread,
        ];
    }

    public function markRead(int $memberId, string $id): array
    {
        $key = 'mock.msgread.' . $memberId;
        $read = $this->settingJson($key, []);
        if (!in_array($id, $read, true)) {
            $read[] = $id;
            $this->saveSetting($key, $read);
        }

        return $this->unreadCount($memberId);
    }

    public function unreadCount(int $memberId): array
    {
        [$orderMsgs, $systemMsgs] = $this->allMessages($memberId);
        $unread = 0;
        foreach (array_merge($orderMsgs, $systemMsgs) as $m) {
            if (empty($m['read'])) {
                $unread++;
            }
        }

        return ['mock' => true, 'unread' => $unread];
    }

    /** @return array{0: array, 1: array} */
    private function allMessages(int $memberId): array
    {
        $read = $this->settingJson('mock.msgread.' . $memberId, []);
        $map = function (array $items) use ($read) {
            return array_map(function ($m) use ($read) {
                $m['read'] = in_array($m['id'], $read, true);

                return $m;
            }, $items);
        };

        return [$map($this->orderMessages($memberId)), $map($this->systemMessages($memberId))];
    }

    public function shareView(array $body, ?int $viewerId): array
    {
        $ref = trim((string) ($body['ref'] ?? ''));
        $productId = trim((string) ($body['product_id'] ?? ''));
        if ($ref === '' || $productId === '') {
            throw new ApiException('INVALID', '缺少 ref 或商品', 422, 42284);
        }
        $owner = Db::name('members')->where('promo_code', $ref)->find();
        if (!$owner) {
            return ['mock' => true, 'credited' => false, 'reason' => 'unknown_ref'];
        }
        if ($viewerId && (int) $owner['id'] === $viewerId) {
            return ['mock' => true, 'credited' => false, 'reason' => 'self'];
        }
        $ip = (string) ($body['ip'] ?? '0.0.0.0');
        $dedupe = 'mock.shareview.' . $owner['id'] . '.' . $productId . '.' . md5($ip);
        if ($this->settingGet($dedupe)) {
            return ['mock' => true, 'credited' => false, 'reason' => 'duplicate'];
        }
        $this->saveSetting($dedupe, ['at' => date('Y-m-d H:i:s')]);
        $pts = 2;
        (new AssetService())->changePoints((int) $owner['id'], $pts, 'share_view', 'product', null, '分享被瀏覽（Mock）');

        return ['mock' => true, 'credited' => true, 'points' => $pts, 'owner_id' => (int) $owner['id']];
    }

    private function seckillProducts(string $status): array
    {
        $live = $status === 'live';

        return [
            [
                'id' => $live ? 'sp1' : 'sp3',
                'product_id' => 'p1',
                'name' => ['zh-HK' => '方鑽項鏈', 'en' => 'Cushion Necklace'],
                'image' => '/mock/product-1.jpg',
                'seckill_price' => 88,
                'origin_price' => 128,
                'stock' => $live ? 15 : 20,
                'in_seckill' => true,
            ],
            [
                'id' => $live ? 'sp2' : 'sp4',
                'product_id' => 'p4',
                'name' => ['zh-HK' => '紅心手鏈', 'en' => 'Heart Bracelet'],
                'image' => '/mock/product-4.jpg',
                'seckill_price' => 49,
                'origin_price' => 69,
                'stock' => $live ? 8 : 12,
                'in_seckill' => true,
            ],
        ];
    }

    private function orderMessages(int $memberId): array
    {
        $orders = Db::name('orders')->where('member_id', $memberId)->order('id', 'desc')->limit(8)->select()->toArray();
        $items = [];
        foreach ($orders as $o) {
            $statusMap = [
                'pending_payment' => '待付款',
                'pending_shipment' => '支付成功，待發貨',
                'pending_receipt' => '已發貨，待收貨',
                'completed' => '訂單已完成',
                'cancelled' => '訂單已取消',
                'refunded' => '訂單已退款',
            ];
            $title = $statusMap[$o['status']] ?? ('訂單 ' . $o['status']);
            $items[] = [
                'id' => 'ord-' . $o['id'],
                'type' => 'order',
                'title' => ['zh-HK' => $title, 'en' => $title],
                'body' => ['zh-HK' => '訂單 ' . $o['order_no'], 'en' => 'Order ' . $o['order_no']],
                'order_id' => (int) $o['id'],
                'order_no' => $o['order_no'],
                'created_at' => $o['updated_at'] ?: $o['created_at'],
            ];
        }
        if (!$items) {
            $items[] = [
                'id' => 'ord-demo',
                'type' => 'order',
                'title' => ['zh-HK' => '歡迎下單', 'en' => 'Welcome'],
                'body' => ['zh-HK' => '完成首單後這裡會出現真實訂單消息。', 'en' => 'Real order messages appear after your first order.'],
                'order_id' => null,
                'order_no' => null,
                'created_at' => date('Y-m-d H:i:s'),
            ];
        }

        return $items;
    }

    private function systemMessages(int $memberId): array
    {
        return [
            [
                'id' => 'sys-welcome-' . $memberId,
                'type' => 'system',
                'title' => ['zh-HK' => '系統：積分與等級已開通', 'en' => 'Points & levels ready'],
                'body' => ['zh-HK' => 'M6 資產能力已可用；本條為系統消息演示。', 'en' => 'M6 assets are live; this is a system demo.'],
                'created_at' => date('Y-m-d H:i:s', time() - 3600),
            ],
            [
                'id' => 'sys-mkt-1',
                'type' => 'marketing',
                'title' => ['zh-HK' => '活動：午間閃購進行中', 'en' => 'Noon flash is live'],
                'body' => ['zh-HK' => '點進秒殺專區搶購（Mock）。', 'en' => 'Open flash sale (Mock).'],
                'created_at' => date('Y-m-d H:i:s', time() - 7200),
            ],
        ];
    }

    private function settingGet(string $key): ?string
    {
        $v = Db::name('shop_settings')->where('setting_key', $key)->value('setting_value');

        return $v === null ? null : (string) $v;
    }

    private function settingJson(string $key, array $default): array
    {
        $raw = $this->settingGet($key);
        if ($raw === null || $raw === '') {
            return $default;
        }
        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : $default;
    }

    private function saveSetting(string $key, array $value): void
    {
        $now = date('Y-m-d H:i:s');
        $json = json_encode($value, JSON_UNESCAPED_UNICODE);
        $exists = Db::name('shop_settings')->where('setting_key', $key)->find();
        if ($exists) {
            Db::name('shop_settings')->where('setting_key', $key)->update([
                'setting_value' => $json,
                'updated_at' => $now,
            ]);
        } else {
            Db::name('shop_settings')->insert([
                'setting_key' => $key,
                'setting_value' => $json,
                'updated_at' => $now,
            ]);
        }
    }
}
