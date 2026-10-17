<?php

declare(strict_types=1);

namespace app\service;

use think\facade\Db;

class TradeSettings
{
    public static function get(string $key, string $default = ''): string
    {
        $value = Db::name('shop_settings')->where('setting_key', $key)->value('setting_value');

        return $value === null || $value === '' ? $default : (string) $value;
    }

    public static function freeThreshold(): float
    {
        return (float) self::get('shipping.free_threshold', '200');
    }

    public static function shippingFee(): float
    {
        return (float) self::get('shipping.fee', '25');
    }

    public static function unpaidTimeoutMinutes(): int
    {
        return max(1, (int) self::get('order.unpaid_timeout_minutes', '30'));
    }

    public static function pointsPerHkd(): int
    {
        return max(1, (int) self::get('points.per_hkd', '100'));
    }

    public static function pointsMaxPercent(): int
    {
        return max(0, min(100, (int) self::get('points.max_percent', '50')));
    }

    public static function autoReceiveDays(): int
    {
        return max(1, (int) self::get('order.auto_receive_days', '7'));
    }

    /** 訂單完成時，商品實付每 HKD 發多少積分（運費不計） */
    public static function orderEarnPerHkd(): int
    {
        return max(0, (int) self::get('points.order_earn_per_hkd', '1'));
    }

    /** 最低提現額（HKD） */
    public static function withdrawMin(): float
    {
        return max(0, (float) self::get('distribution.withdraw_min', '100'));
    }

    /** 分享連結被瀏覽時發給推廣人的積分 */
    public static function shareViewPoints(): int
    {
        return max(0, (int) self::get('points.share_view', '2'));
    }

    /** 訂單完成後可申請售後的天數窗口 */
    public static function aftersaleWindowDays(): int
    {
        return max(1, (int) self::get('aftersale.window_days', '30'));
    }

    /** 客服 WhatsApp 號碼（可含空格/+，前端去非數字） */
    public static function csWhatsapp(): string
    {
        return trim(self::get('cs.whatsapp', ''));
    }

    public static function set(string $key, string $value): void
    {
        $now = date('Y-m-d H:i:s');
        $exists = Db::name('shop_settings')->where('setting_key', $key)->find();
        if ($exists) {
            Db::name('shop_settings')->where('setting_key', $key)->update([
                'setting_value' => $value,
                'updated_at' => $now,
            ]);
        } else {
            Db::name('shop_settings')->insert([
                'setting_key' => $key,
                'setting_value' => $value,
                'updated_at' => $now,
            ]);
        }
    }

    public static function shippingFor(float $goodsAfterDiscount): float
    {
        if ($goodsAfterDiscount >= self::freeThreshold()) {
            return 0.0;
        }

        return self::shippingFee();
    }
}
