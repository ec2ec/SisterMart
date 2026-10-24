<?php

declare(strict_types=1);

namespace app\service;

use app\support\ApiException;
use think\facade\Db;

/**
 * 新用戶註冊配置（存 shop_settings JSON：member.registration）。
 * 默認：開放註冊；不贈餘額／積分；不自動發券。
 */
class RegistrationConfigService
{
    public const SETTING_KEY = 'member.registration';

    /** @return array<string, mixed> */
    public function get(): array
    {
        $raw = TradeSettings::get(self::SETTING_KEY, '');
        $decoded = $raw !== '' ? json_decode($raw, true) : null;
        if (!is_array($decoded)) {
            $decoded = [];
        }

        return $this->normalize($decoded);
    }

    /** 前台公開：是否開放註冊 */
    public function publicStatus(): array
    {
        $cfg = $this->get();

        return [
            'registration_enabled' => (bool) $cfg['registration_enabled'],
        ];
    }

    public function assertRegistrationOpen(): void
    {
        if (!(bool) $this->get()['registration_enabled']) {
            throw new ApiException('REGISTRATION_CLOSED', '暫時不開放註冊', 403, 40310);
        }
    }

    /** @param array<string, mixed> $body */
    public function save(array $body): array
    {
        $current = $this->get();

        $registrationEnabled = array_key_exists('registration_enabled', $body)
            ? in_array($body['registration_enabled'], [true, 1, '1', 'true'], true)
            : (bool) $current['registration_enabled'];

        $autoClaim = array_key_exists('auto_claim_coupons', $body)
            ? in_array($body['auto_claim_coupons'], [true, 1, '1', 'true'], true)
            : (bool) $current['auto_claim_coupons'];

        $couponIds = $current['coupon_ids'];
        if (array_key_exists('coupon_ids', $body)) {
            $couponIds = $this->normalizeCouponIds($body['coupon_ids']);
        }
        if (!$autoClaim) {
            $couponIds = [];
        } elseif ($couponIds !== []) {
            $this->assertCouponsExist($couponIds);
        }

        $giftBalance = array_key_exists('gift_balance', $body)
            ? in_array($body['gift_balance'], [true, 1, '1', 'true'], true)
            : (bool) $current['gift_balance'];
        $giftBalanceAmount = array_key_exists('gift_balance_amount', $body)
            ? round((float) $body['gift_balance_amount'], 2)
            : (float) $current['gift_balance_amount'];
        if ($giftBalanceAmount < 0 || $giftBalanceAmount > 999999) {
            throw new ApiException('INVALID', '贈送餘額須為 0–999999', 422, 42201);
        }
        if (!$giftBalance) {
            $giftBalanceAmount = 0.0;
        }

        $giftPoints = array_key_exists('gift_points', $body)
            ? in_array($body['gift_points'], [true, 1, '1', 'true'], true)
            : (bool) $current['gift_points'];
        $giftPointsAmount = array_key_exists('gift_points_amount', $body)
            ? (int) $body['gift_points_amount']
            : (int) $current['gift_points_amount'];
        if ($giftPointsAmount < 0 || $giftPointsAmount > 99999999) {
            throw new ApiException('INVALID', '贈送積分須為 0–99999999', 422, 42201);
        }
        if (!$giftPoints) {
            $giftPointsAmount = 0;
        }

        $payload = [
            'registration_enabled' => $registrationEnabled,
            'auto_claim_coupons' => $autoClaim,
            'coupon_ids' => $couponIds,
            'gift_balance' => $giftBalance,
            'gift_balance_amount' => $giftBalanceAmount,
            'gift_points' => $giftPoints,
            'gift_points_amount' => $giftPointsAmount,
        ];
        TradeSettings::set(self::SETTING_KEY, json_encode($payload, JSON_UNESCAPED_UNICODE));

        return $this->get();
    }

    /**
     * 註冊成功後套用贈送（須在已寫入 members 之後呼叫；可與外層事務並存）。
     *
     * @return array{balance: float, points: int, coupon_ids: list<int>}
     */
    public function applyWelcomeGifts(int $memberId, string $now): array
    {
        $cfg = $this->get();
        $balance = 0.0;
        $points = 0;
        $grantedCouponIds = [];

        if ($cfg['gift_balance'] && (float) $cfg['gift_balance_amount'] > 0) {
            $balance = round((float) $cfg['gift_balance_amount'], 2);
            Db::name('members')->where('id', $memberId)->update([
                'balance' => $balance,
                'updated_at' => $now,
            ]);
            Db::name('balance_logs')->insert([
                'member_id' => $memberId,
                'delta' => $balance,
                'balance_after' => $balance,
                'type' => 'register_gift',
                'ref_type' => 'registration',
                'ref_id' => null,
                'remark' => '新用戶註冊贈送餘額',
                'created_at' => $now,
            ]);
        }

        if ($cfg['gift_points'] && (int) $cfg['gift_points_amount'] > 0) {
            $points = (int) $cfg['gift_points_amount'];
            Db::name('members')->where('id', $memberId)->update([
                'points' => $points,
                'updated_at' => $now,
            ]);
            Db::name('point_logs')->insert([
                'member_id' => $memberId,
                'delta' => $points,
                'balance_after' => $points,
                'type' => 'register_gift',
                'ref_type' => 'registration',
                'ref_id' => null,
                'remark' => '新用戶註冊贈送積分',
                'created_at' => $now,
            ]);
        }

        if ($cfg['auto_claim_coupons'] && $cfg['coupon_ids'] !== []) {
            foreach ($cfg['coupon_ids'] as $couponId) {
                $coupon = Db::name('coupons')->where('id', $couponId)->lock(true)->find();
                if (!$coupon || (int) $coupon['status'] !== 1) {
                    continue;
                }
                if ($coupon['starts_at'] && $coupon['starts_at'] > $now) {
                    continue;
                }
                if ($coupon['ends_at'] && $coupon['ends_at'] < $now) {
                    continue;
                }
                $stock = (int) ($coupon['stock'] ?? 0);
                $claimed = (int) ($coupon['claimed'] ?? 0);
                if ($claimed >= $stock) {
                    continue;
                }
                $limit = max(1, (int) ($coupon['per_member_limit'] ?? 1));
                $mine = (int) Db::name('coupon_grants')
                    ->where(['member_id' => $memberId, 'coupon_id' => $couponId])
                    ->count();
                if ($mine >= $limit) {
                    continue;
                }
                Db::name('coupon_grants')->insert([
                    'coupon_id' => $couponId,
                    'member_id' => $memberId,
                    'status' => 'unused',
                    'order_id' => null,
                    'expires_at' => $coupon['ends_at'] ?: null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                Db::name('coupons')->where('id', $couponId)->inc('claimed', 1)->update([]);
                $grantedCouponIds[] = $couponId;
            }
        }

        return [
            'balance' => $balance,
            'points' => $points,
            'coupon_ids' => $grantedCouponIds,
        ];
    }

    /**
     * @param array<string, mixed> $raw
     * @return array{
     *   registration_enabled: bool,
     *   auto_claim_coupons: bool,
     *   coupon_ids: list<int>,
     *   coupons: list<array<string, mixed>>,
     *   gift_balance: bool,
     *   gift_balance_amount: float,
     *   gift_points: bool,
     *   gift_points_amount: int
     * }
     */
    private function normalize(array $raw): array
    {
        $defaults = $this->defaults();
        $couponIds = $this->normalizeCouponIds($raw['coupon_ids'] ?? $defaults['coupon_ids']);

        return [
            'registration_enabled' => array_key_exists('registration_enabled', $raw)
                ? in_array($raw['registration_enabled'], [true, 1, '1', 'true'], true)
                : $defaults['registration_enabled'],
            'auto_claim_coupons' => in_array($raw['auto_claim_coupons'] ?? false, [true, 1, '1', 'true'], true),
            'coupon_ids' => $couponIds,
            'coupons' => $this->presentCoupons($couponIds),
            'gift_balance' => in_array($raw['gift_balance'] ?? false, [true, 1, '1', 'true'], true),
            'gift_balance_amount' => max(0, round((float) ($raw['gift_balance_amount'] ?? 0), 2)),
            'gift_points' => in_array($raw['gift_points'] ?? false, [true, 1, '1', 'true'], true),
            'gift_points_amount' => max(0, (int) ($raw['gift_points_amount'] ?? 0)),
        ];
    }

    /** @return array<string, mixed> */
    private function defaults(): array
    {
        return [
            'registration_enabled' => true,
            'auto_claim_coupons' => false,
            'coupon_ids' => [],
            'gift_balance' => false,
            'gift_balance_amount' => 0.0,
            'gift_points' => false,
            'gift_points_amount' => 0,
        ];
    }

    /** @return list<int> */
    private function normalizeCouponIds(mixed $raw): array
    {
        if (!is_array($raw)) {
            return [];
        }
        $ids = [];
        foreach ($raw as $v) {
            $id = (int) $v;
            if ($id > 0) {
                $ids[$id] = $id;
            }
        }

        return array_values($ids);
    }

    /** @param list<int> $ids */
    private function assertCouponsExist(array $ids): void
    {
        $found = Db::name('coupons')->whereIn('id', $ids)->column('id');
        $found = array_map('intval', $found ?: []);
        $missing = array_values(array_diff($ids, $found));
        if ($missing !== []) {
            throw new ApiException('INVALID', '優惠券不存在：' . implode(',', $missing), 422, 42201);
        }
    }

    /**
     * @param list<int> $ids
     * @return list<array<string, mixed>>
     */
    private function presentCoupons(array $ids): array
    {
        if ($ids === []) {
            return [];
        }
        $rows = Db::name('coupons')->whereIn('id', $ids)->select()->toArray();
        $byId = [];
        foreach ($rows as $r) {
            $byId[(int) $r['id']] = $r;
        }
        $out = [];
        foreach ($ids as $id) {
            $r = $byId[$id] ?? null;
            if (!$r) {
                continue;
            }
            $out[] = [
                'id' => (int) $r['id'],
                'code' => (string) $r['code'],
                'name' => [
                    'zh-HK' => (string) ($r['name_zh'] ?? $r['code']),
                    'en' => (string) ($r['name_en'] ?? $r['code']),
                ],
                'status' => (int) $r['status'],
            ];
        }

        return $out;
    }
}
