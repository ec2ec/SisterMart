<?php

declare(strict_types=1);

namespace app\service;

use think\facade\Db;

class SoldDisplay
{
    public static function mode(): string
    {
        $value = (string) Db::name('shop_settings')->where('setting_key', 'sold.display_mode')->value('setting_value');

        return $value === 'exact' ? 'exact' : 'fuzzy';
    }

    public static function label(int $count, string $mode = ''): string
    {
        $mode = $mode ?: self::mode();
        if ($mode === 'exact' || $count < 10) {
            return (string) $count;
        }

        return (string) (intdiv($count, 10) * 10) . '+';
    }

    /** H5 展示基數 = 真實銷售 + 疊加（再套 label／50+） */
    public static function displayTotal(int $soldCount, int $soldBoost = 0): int
    {
        return max(0, $soldCount) + max(0, $soldBoost);
    }

    public static function labelFromProduct(array $row, string $mode = ''): string
    {
        return self::label(
            self::displayTotal((int) ($row['sold_count'] ?? 0), (int) ($row['sold_boost'] ?? 0)),
            $mode,
        );
    }
}
