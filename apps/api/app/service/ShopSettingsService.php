<?php

declare(strict_types=1);

namespace app\service;

use app\support\ApiException;

class ShopSettingsService
{
    public function aftersaleAndCs(?array $body = null): array
    {
        if ($body !== null) {
            if (array_key_exists('aftersale_window_days', $body)) {
                $days = (int) $body['aftersale_window_days'];
                if ($days < 1 || $days > 365) {
                    throw new ApiException('INVALID', '售後窗口須為 1–365 天', 422, 42201);
                }
                TradeSettings::set('aftersale.window_days', (string) $days);
            }
            if (array_key_exists('cs_whatsapp', $body)) {
                $wa = preg_replace('/[^\d+]/', '', (string) $body['cs_whatsapp']) ?? '';
                TradeSettings::set('cs.whatsapp', $wa);
            }
        }

        return [
            'sold_display_mode' => SoldDisplay::mode(),
            'aftersale_window_days' => TradeSettings::aftersaleWindowDays(),
            'cs_whatsapp' => TradeSettings::csWhatsapp(),
        ];
    }
}
