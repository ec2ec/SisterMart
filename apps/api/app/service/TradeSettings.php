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

    public static function shippingFor(float $goodsAfterDiscount): float
    {
        if ($goodsAfterDiscount >= self::freeThreshold()) {
            return 0.0;
        }

        return self::shippingFee();
    }
}
