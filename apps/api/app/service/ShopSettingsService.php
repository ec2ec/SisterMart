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
            if (array_key_exists('cs_facebook_url', $body)) {
                $fb = trim((string) $body['cs_facebook_url']);
                if ($fb !== '' && !preg_match('#^https?://#i', $fb)) {
                    throw new ApiException('INVALID', 'Facebook 連結須以 http(s):// 開頭', 422, 42201);
                }
                TradeSettings::set('cs.facebook_url', $fb);
            }
        }

        return [
            'sold_display_mode' => SoldDisplay::mode(),
            'aftersale_window_days' => TradeSettings::aftersaleWindowDays(),
            'cs_whatsapp' => TradeSettings::csWhatsapp(),
            'cs_facebook_url' => TradeSettings::csFacebookUrl(),
        ];
    }

    /**
     * H5 公開客服入口（無需登錄）。
     *
     * @return array{cs_whatsapp: string, cs_facebook_url: string, whatsapp_digits: string}
     */
    public function publicContact(): array
    {
        $wa = TradeSettings::csWhatsapp();
        $digits = preg_replace('/\D+/', '', $wa) ?? '';

        return [
            'cs_whatsapp' => $wa,
            'cs_facebook_url' => TradeSettings::csFacebookUrl(),
            'whatsapp_digits' => $digits,
        ];
    }
}
