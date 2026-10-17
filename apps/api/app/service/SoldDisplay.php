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
}
