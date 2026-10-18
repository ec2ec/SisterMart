<?php

declare(strict_types=1);

/**
 * M24：首頁字幕 + 頁腳標語預設（需已跑 m19_seed）。
 */

require dirname(__DIR__) . '/vendor/autoload.php';

$app = new think\App(dirname(__DIR__));
$app->initialize();

use app\service\HomeOpsService;
use app\service\TradeSettings;

$now = date('Y-m-d H:i:s');

if (TradeSettings::get(HomeOpsService::KEY_MARQUEE, '') === '') {
    TradeSettings::set(HomeOpsService::KEY_MARQUEE, json_encode([
        'text_zh' => '首次下單最高享 4 折優惠 · 立即選購',
        'text_en' => 'Get up to 40% off on your 1st order · Shop now',
        'link_type' => 'none',
        'link_value' => '',
        'status' => 1,
    ], JSON_UNESCAPED_UNICODE));
}

if (TradeSettings::get(HomeOpsService::KEY_SLOGAN, '') === '') {
    TradeSettings::set(HomeOpsService::KEY_SLOGAN, json_encode([
        'zh-HK' => '* 我們守護您的美麗 *',
        'en' => '* We guard your beauty *',
    ], JSON_UNESCAPED_UNICODE));
}

echo "m24 seed ok (home.marquee + home.footer_slogan) {$now}\n";
