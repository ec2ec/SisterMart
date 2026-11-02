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
            if (array_key_exists('cs_email', $body)) {
                $email = trim((string) $body['cs_email']);
                if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                    throw new ApiException('INVALID', '客服郵箱格式不正確', 422, 42201);
                }
                TradeSettings::set('cs.email', $email);
            }
        }

        return [
            'sold_display_mode' => SoldDisplay::mode(),
            'aftersale_window_days' => TradeSettings::aftersaleWindowDays(),
            'cs_whatsapp' => TradeSettings::csWhatsapp(),
            // 後台展示已保存值（空＝H5 回退 MAIL_FROM / 默認）
            'cs_email' => TradeSettings::get('cs.email', ''),
        ];
    }
}
