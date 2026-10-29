<?php

declare(strict_types=1);

/**
 * M45：活動專區 + 首頁版面模組（可重複執行）
 */

require dirname(__DIR__) . '/vendor/autoload.php';

$app = new think\App(dirname(__DIR__));
$app->initialize();

use app\service\CampaignService;
use app\service\HomeLayoutService;
use think\facade\Db;

(new CampaignService())->ensureSchema();
(new HomeLayoutService())->ensureSchema();

$now = date('Y-m-d H:i:s');

// 新人角標
$badge = Db::name('product_badge_defs')->where('code', 'newcomer')->find();
if (!$badge) {
    $bid = (int) Db::name('product_badge_defs')->insertGetId([
        'code' => 'newcomer',
        'color' => '#c45c26',
        'sort_order' => 5,
        'status' => 1,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
} else {
    $bid = (int) $badge['id'];
}
foreach ([['zh-HK', '新人專屬'], ['en', 'Newcomer']] as [$locale, $name]) {
    $exists = Db::name('product_badge_def_i18n')
        ->where('badge_def_id', $bid)
        ->where('locale', $locale)
        ->find();
    if (!$exists) {
        Db::name('product_badge_def_i18n')->insert([
            'badge_def_id' => $bid,
            'locale' => $locale,
            'name' => $name,
        ]);
    }
}

// 預置新人活動
$camp = Db::name('campaigns')->where('code', 'newcomer')->find();
if (!$camp) {
    $cid = (int) Db::name('campaigns')->insertGetId([
        'code' => 'newcomer',
        'title_zh' => '新人專區',
        'title_en' => 'Newcomer Zone',
        'status' => 1,
        'starts_at' => null,
        'ends_at' => null,
        'audience_json' => json_encode(['registered_within_days' => 30], JSON_UNESCAPED_UNICODE),
        'product_source' => 'badge',
        'product_badge' => 'newcomer',
        'banner_image_url' => null,
        'intro_html_zh' => '<p>這裡的商品專門為新會員精心挑選。</p>',
        'intro_html_en' => '<p>Products curated for new members.</p>',
        'show_on_home' => 1,
        'home_rail_limit' => 10,
        'sort_order' => 0,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $camp = Db::name('campaigns')->where('id', $cid)->find();
}

$defaults = [
    ['banner_swipe', 'banner_swipe', null, null, '滑動廣告位', 'Banner carousel', 1, 10, null],
    ['text_marquee', 'text_marquee', null, null, '文本廣告', 'Text marquee', 1, 20, null],
    ['category_row', 'category_row', null, null, '商品分類', 'Categories', 1, 30, null],
    ['campaign:newcomer', 'campaign_rail', 'campaign', 'newcomer', '新人專區', 'Newcomer Zone', 1, 40, json_encode(['rail_limit' => 10])],
    ['seckill_rail', 'seckill_rail', null, null, '秒殺專區', 'Flash sale', 1, 50, null],
    ['recommend_feed', 'recommend_feed', null, null, '為你推薦', 'For you', 1, 900, null],
];

foreach ($defaults as [$key, $type, $refType, $refCode, $zh, $en, $enabled, $sort, $cfg]) {
    if (Db::name('home_layout_modules')->where('module_key', $key)->find()) {
        continue;
    }
    Db::name('home_layout_modules')->insert([
        'module_key' => $key,
        'module_type' => $type,
        'ref_type' => $refType,
        'ref_code' => $refCode,
        'title_zh' => $zh,
        'title_en' => $en,
        'enabled' => $enabled,
        'sort_order' => $sort,
        'config_json' => $cfg,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
}

(new HomeLayoutService())->syncCampaignModule($camp);

// 既有 home_sections → product_showcase
$dbName = (string) env('DB_NAME', 'sistermart');
$hasSections = (int) (Db::query(
    'SELECT COUNT(*) AS c FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?',
    [$dbName, 'home_sections'],
)[0]['c'] ?? 0) > 0;

if ($hasSections) {
    $sections = Db::name('home_sections')->order('sort_order', 'asc')->order('id', 'asc')->select()->toArray();
    $base = 60;
    foreach ($sections as $i => $sec) {
        $code = (string) $sec['code'];
        $key = 'section:' . $code;
        if (Db::name('home_layout_modules')->where('module_key', $key)->find()) {
            continue;
        }
        Db::name('home_layout_modules')->insert([
            'module_key' => $key,
            'module_type' => 'product_showcase',
            'ref_type' => 'home_section',
            'ref_code' => $code,
            'title_zh' => (string) ($sec['title_zh'] ?? ''),
            'title_en' => (string) ($sec['title_en'] ?? ''),
            'enabled' => (int) ($sec['status'] ?? 1) === 1 ? 1 : 0,
            'sort_order' => $base + $i * 10,
            'config_json' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
}

echo "m45 seed ok (campaigns + home_layout_modules)\n";
