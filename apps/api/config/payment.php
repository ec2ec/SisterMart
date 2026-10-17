<?php

declare(strict_types=1);

/**
 * M10 支付渠道配置。
 * PAYMENT_MODE=mock（默认）→ 全部走 MockPaymentChannel
 * PAYMENT_MODE=live → 有密钥的渠道走 Stub/实渠道，否则拒绝创建
 */
return [
    'mode' => env('PAYMENT_MODE', 'mock'),
    'callback_base' => env('PAYMENT_CALLBACK_BASE', ''),
    'channels' => [
        'fps' => [
            'enabled' => (bool) env('PAYMENT_FPS_ENABLED', false),
            'merchant_id' => env('PAYMENT_FPS_MERCHANT_ID', ''),
            'secret' => env('PAYMENT_FPS_SECRET', ''),
        ],
        'alipay_hk' => [
            'enabled' => (bool) env('PAYMENT_ALIPAY_HK_ENABLED', false),
            'app_id' => env('PAYMENT_ALIPAY_HK_APP_ID', ''),
            'private_key' => env('PAYMENT_ALIPAY_HK_PRIVATE_KEY', ''),
            'public_key' => env('PAYMENT_ALIPAY_HK_PUBLIC_KEY', ''),
        ],
        'wechat_hk' => [
            'enabled' => (bool) env('PAYMENT_WECHAT_HK_ENABLED', false),
            'mch_id' => env('PAYMENT_WECHAT_HK_MCH_ID', ''),
            'api_key' => env('PAYMENT_WECHAT_HK_API_KEY', ''),
            'app_id' => env('PAYMENT_WECHAT_HK_APP_ID', ''),
        ],
    ],
];
