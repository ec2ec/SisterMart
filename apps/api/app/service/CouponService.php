<?php

declare(strict_types=1);

namespace app\service;

use app\support\ApiException;
use think\facade\Db;

class CouponService
{
    public function claimable(?int $memberId): array
    {
        $now = date('Y-m-d H:i:s');
        $rows = Db::name('coupons')
            ->where('status', 1)
            ->whereRaw('(starts_at IS NULL OR starts_at <= ?)', [$now])
            ->whereRaw('(ends_at IS NULL OR ends_at >= ?)', [$now])
            ->order('id', 'desc')
            ->select()->toArray();

        $claimedMap = [];
        if ($memberId) {
            $counts = Db::name('coupon_grants')
                ->where('member_id', $memberId)
                ->whereIn('coupon_id', array_column($rows, 'id') ?: [0])
                ->field('coupon_id, COUNT(*) AS c')
                ->group('coupon_id')
                ->select()->toArray();
            foreach ($counts as $c) {
                $claimedMap[(int) $c['coupon_id']] = (int) $c['c'];
            }
        }

        $items = [];
        foreach ($rows as $r) {
            $stock = (int) ($r['stock'] ?? 0);
            $claimed = (int) ($r['claimed'] ?? 0);
            $limit = max(1, (int) ($r['per_member_limit'] ?? 1));
            $mine = $claimedMap[(int) $r['id']] ?? 0;
            $left = max(0, $stock - $claimed);
            $canClaim = $left > 0 && $mine < $limit;
            $items[] = array_merge($this->presentCoupon($r), [
                'stock_left' => $left,
                'claimed_by_me' => $mine,
                'per_member_limit' => $limit,
                'can_claim' => $canClaim,
                'claim_blocked_reason' => $canClaim ? '' : ($left <= 0 ? 'sold_out' : 'limit_reached'),
            ]);
        }

        return ['items' => $items];
    }

    public function claim(int $memberId, int $couponId): array
    {
        $now = date('Y-m-d H:i:s');
        $grantId = 0;
        Db::transaction(function () use ($memberId, $couponId, $now, &$grantId) {
            $c = Db::name('coupons')->where('id', $couponId)->lock(true)->find();
            if (!$c || (int) $c['status'] !== 1) {
                throw new ApiException('COUPON_INVALID', '優惠券不可領取', 422, 42270);
            }
            if ($c['starts_at'] && $c['starts_at'] > $now) {
                throw new ApiException('COUPON_NOT_STARTED', '優惠券尚未開始', 422, 42271);
            }
            if ($c['ends_at'] && $c['ends_at'] < $now) {
                throw new ApiException('COUPON_ENDED', '優惠券已結束', 422, 42272);
            }
            $stock = (int) ($c['stock'] ?? 0);
            $claimed = (int) ($c['claimed'] ?? 0);
            if ($claimed >= $stock) {
                throw new ApiException('COUPON_SOLD_OUT', '已領完', 422, 42273);
            }
            $limit = max(1, (int) ($c['per_member_limit'] ?? 1));
            $mine = (int) Db::name('coupon_grants')->where(['member_id' => $memberId, 'coupon_id' => $couponId])->count();
            if ($mine >= $limit) {
                throw new ApiException('COUPON_LIMIT', '已達個人限領', 422, 42274);
            }
            $grantId = (int) Db::name('coupon_grants')->insertGetId([
                'coupon_id' => $couponId,
                'member_id' => $memberId,
                'status' => 'unused',
                'order_id' => null,
                'expires_at' => self::expiresAtAfterClaim($c, $now),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            Db::name('coupons')->where('id', $couponId)->inc('claimed', 1)->update([]);
        });

        return $this->grantDetail($grantId);
    }

    /**
     * 領取後有效期：valid_days>0 → 領取時刻 + N 天（當日 23:59:59）；0 → 永久。
     *
     * @param array<string, mixed> $coupon
     */
    public static function expiresAtAfterClaim(array $coupon, ?string $from = null): ?string
    {
        $days = max(0, (int) ($coupon['valid_days'] ?? 0));
        if ($days <= 0) {
            return null;
        }
        $base = $from ? strtotime($from) : time();
        if ($base === false) {
            $base = time();
        }

        return date('Y-m-d 23:59:59', strtotime('+' . $days . ' days', $base));
    }

    /** @return array{0:?string,1:?string} [starts_at, ends_at] */
    private function normalizeClaimWindow(array $body, bool $forCreate = false): array
    {
        $permanent = in_array($body['claim_permanent'] ?? false, [true, 1, '1', 'true'], true);
        if ($permanent) {
            $starts = array_key_exists('starts_at', $body)
                ? $this->normalizeDateTime($body['starts_at'] ?? null)
                : ($forCreate ? null : null);

            return [$starts, null];
        }
        $starts = $this->normalizeDateTime($body['starts_at'] ?? null);
        $ends = $this->normalizeDateTime($body['ends_at'] ?? null);
        if ($forCreate && ($starts === null || $ends === null)) {
            throw new ApiException('INVALID', '請設置發放時段起止，或選擇永久', 422, 42277);
        }
        if ($starts !== null && $ends !== null && $starts > $ends) {
            throw new ApiException('INVALID', '發放開始時間不可晚於結束時間', 422, 42278);
        }

        return [$starts, $ends];
    }

    private function normalizeDateTime(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        // 毫秒／秒時間戳（避免前端誤傳 number 導致 500）
        if (is_int($value) || is_float($value)) {
            $n = (float) $value;
            if ($n > 1e12) {
                $n = $n / 1000.0;
            }
            if ($n <= 0) {
                return null;
            }

            return date('Y-m-d H:i:s', (int) $n);
        }
        if (!is_string($value)) {
            // 陣列／物件不可轉字串（PHP 8 會 TypeError → 500）
            return null;
        }
        $raw = trim($value);
        if ($raw === '' || strcasecmp($raw, 'null') === 0) {
            return null;
        }
        // 允許僅日期：補全時間
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) {
            return $raw . ' 00:00:00';
        }
        $ts = strtotime($raw);

        return $ts === false ? null : date('Y-m-d H:i:s', $ts);
    }

    public function mine(int $memberId, ?string $status = null): array
    {
        $now = date('Y-m-d H:i:s');
        // 過期自動標記
        Db::name('coupon_grants')->alias('g')
            ->where('g.member_id', $memberId)
            ->where('g.status', 'unused')
            ->whereNotNull('g.expires_at')
            ->where('g.expires_at', '<', $now)
            ->update(['g.status' => 'expired', 'g.updated_at' => $now]);

        $q = Db::name('coupon_grants')->alias('g')
            ->join('coupons c', 'c.id = g.coupon_id')
            ->where('g.member_id', $memberId)
            ->field('g.*, c.code, c.name_zh, c.name_en, c.rules_zh, c.rules_en, c.type, c.threshold, c.amount')
            ->order('g.id', 'desc');
        if ($status) {
            $q->where('g.status', $status);
        }
        $rows = $q->select()->toArray();

        return [
            'items' => array_map(fn ($r) => [
                'grant_id' => (int) $r['id'],
                'status' => $r['status'],
                'expires_at' => $r['expires_at'] ?? null,
                'order_id' => $r['order_id'] ? (int) $r['order_id'] : null,
                'coupon' => [
                    'id' => (int) $r['coupon_id'],
                    'code' => $r['code'],
                    'name' => ['zh-HK' => $r['name_zh'], 'en' => $r['name_en']],
                    'rules' => [
                        'zh-HK' => (string) ($r['rules_zh'] ?? ''),
                        'en' => (string) ($r['rules_en'] ?? ''),
                    ],
                    'type' => $r['type'],
                    'threshold' => (float) $r['threshold'],
                    'amount' => (float) $r['amount'],
                ],
                'created_at' => $r['created_at'],
            ], $rows),
            'unused_count' => (int) Db::name('coupon_grants')->where(['member_id' => $memberId, 'status' => 'unused'])->count(),
        ];
    }

    public function adminList(): array
    {
        $rows = Db::name('coupons')->order('id', 'desc')->select()->toArray();

        return ['items' => array_map(fn ($r) => $this->presentCoupon($r, true), $rows)];
    }

    public function adminCreate(array $body): array
    {
        $code = strtoupper(trim((string) ($body['code'] ?? '')));
        $type = (string) ($body['type'] ?? 'fixed');
        if ($code === '' || !in_array($type, ['fixed', 'discount', 'free_shipping'], true)) {
            throw new ApiException('INVALID', '券碼或類型不正確', 422, 42275);
        }
        if (Db::name('coupons')->where('code', $code)->find()) {
            throw new ApiException('CODE_TAKEN', '券碼已存在', 422, 42276);
        }
        $now = date('Y-m-d H:i:s');
        [$startsAt, $endsAt] = $this->normalizeClaimWindow($body, true);
        $validDays = max(0, (int) ($body['valid_days'] ?? 30));
        $id = (int) Db::name('coupons')->insertGetId([
            'code' => $code,
            'name_zh' => trim((string) ($body['name_zh'] ?? $code)),
            'name_en' => trim((string) ($body['name_en'] ?? $code)),
            'rules_zh' => (string) ($body['rules_zh'] ?? ''),
            'rules_en' => (string) ($body['rules_en'] ?? ''),
            'type' => $type,
            'threshold' => round((float) ($body['threshold'] ?? 0), 2),
            'amount' => round((float) ($body['amount'] ?? 0), 4),
            'status' => (int) ($body['status'] ?? 1),
            'stock' => max(0, (int) ($body['stock'] ?? 0)),
            'claimed' => 0,
            'per_member_limit' => max(1, (int) ($body['per_member_limit'] ?? 1)),
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'valid_days' => $validDays,
            'scope' => (string) ($body['scope'] ?? 'all'),
            'created_at' => $now,
        ]);

        return $this->presentCoupon(Db::name('coupons')->where('id', $id)->find(), true);
    }

    public function adminUpdate(int $id, array $body): array
    {
        $row = Db::name('coupons')->where('id', $id)->find();
        if (!$row) {
            throw new ApiException('NOT_FOUND', '優惠券不存在', 404, 40470);
        }
        $data = [];
        foreach (['name_zh', 'name_en', 'rules_zh', 'rules_en', 'type', 'scope'] as $k) {
            if (array_key_exists($k, $body)) {
                $data[$k] = $body[$k];
            }
        }
        foreach (['threshold', 'amount'] as $k) {
            if (array_key_exists($k, $body)) {
                $data[$k] = round((float) $body[$k], $k === 'amount' ? 4 : 2);
            }
        }
        foreach (['status', 'stock', 'per_member_limit', 'valid_days'] as $k) {
            if (array_key_exists($k, $body)) {
                $data[$k] = (int) $body[$k];
                if ($k === 'valid_days') {
                    $data[$k] = max(0, $data[$k]);
                }
            }
        }
        if (array_key_exists('claim_permanent', $body)
            || array_key_exists('starts_at', $body)
            || array_key_exists('ends_at', $body)) {
            [$startsAt, $endsAt] = $this->normalizeClaimWindow(array_merge([
                'starts_at' => $row['starts_at'] ?? null,
                'ends_at' => $row['ends_at'] ?? null,
                'claim_permanent' => ($row['ends_at'] ?? null) === null,
            ], $body), false);
            $data['starts_at'] = $startsAt;
            $data['ends_at'] = $endsAt;
        }
        if ($data) {
            Db::name('coupons')->where('id', $id)->update($data);
        }

        return $this->presentCoupon(Db::name('coupons')->where('id', $id)->find(), true);
    }

    /** 計算券對商品金額的減免（不含免運；免運由 OrderService 處理運費） */
    public function discountForGoods(array $coupon, float $goods): float
    {
        $threshold = (float) $coupon['threshold'];
        if ($goods < $threshold) {
            return 0.0;
        }
        $type = (string) $coupon['type'];
        $amount = (float) $coupon['amount'];
        if ($type === 'fixed') {
            return min($goods, round($amount, 2));
        }
        if ($type === 'discount') {
            // amount=0.9 → 九折，減免 10%
            if ($amount > 0 && $amount < 1) {
                return round($goods * (1 - $amount), 2);
            }
            // amount=10 → 減 10%
            if ($amount >= 1 && $amount <= 100) {
                return round($goods * ($amount / 100), 2);
            }

            return 0.0;
        }
        if ($type === 'free_shipping') {
            return 0.0;
        }

        return min($goods, round($amount, 2));
    }

    private function grantDetail(int $grantId): array
    {
        $r = Db::name('coupon_grants')->alias('g')
            ->join('coupons c', 'c.id = g.coupon_id')
            ->where('g.id', $grantId)
            ->field('g.*, c.code, c.name_zh, c.name_en, c.rules_zh, c.rules_en, c.type, c.threshold, c.amount')
            ->find();

        return [
            'grant_id' => (int) $r['id'],
            'status' => $r['status'],
            'expires_at' => $r['expires_at'] ?? null,
            'coupon' => [
                'id' => (int) $r['coupon_id'],
                'code' => $r['code'],
                'name' => ['zh-HK' => $r['name_zh'], 'en' => $r['name_en']],
                'rules' => [
                    'zh-HK' => (string) ($r['rules_zh'] ?? ''),
                    'en' => (string) ($r['rules_en'] ?? ''),
                ],
                'type' => $r['type'],
                'threshold' => (float) $r['threshold'],
                'amount' => (float) $r['amount'],
            ],
        ];
    }

    private function presentCoupon(array $r, bool $admin = false): array
    {
        $rulesZh = (string) ($r['rules_zh'] ?? '');
        $rulesEn = (string) ($r['rules_en'] ?? '');
        $data = [
            'id' => (int) $r['id'],
            'code' => $r['code'],
            'name' => ['zh-HK' => $r['name_zh'], 'en' => $r['name_en']],
            'rules' => ['zh-HK' => $rulesZh, 'en' => $rulesEn],
            'type' => $r['type'],
            'threshold' => (float) $r['threshold'],
            'amount' => (float) $r['amount'],
            'status' => (int) $r['status'],
            'starts_at' => $r['starts_at'] ?? null,
            'ends_at' => $r['ends_at'] ?? null,
            'claim_permanent' => ($r['ends_at'] ?? null) === null,
            'valid_days' => max(0, (int) ($r['valid_days'] ?? 0)),
            'scope' => $r['scope'] ?? 'all',
        ];
        if ($admin) {
            $data['name_zh'] = (string) $r['name_zh'];
            $data['name_en'] = (string) $r['name_en'];
            $data['rules_zh'] = $rulesZh;
            $data['rules_en'] = $rulesEn;
        }
        if ($admin || isset($r['stock'])) {
            $data['stock'] = (int) ($r['stock'] ?? 0);
            $data['claimed'] = (int) ($r['claimed'] ?? 0);
            $data['per_member_limit'] = (int) ($r['per_member_limit'] ?? 1);
        }

        return $data;
    }
}
