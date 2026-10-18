<?php

declare(strict_types=1);

/**
 * M19：首頁輪播表 + 默認兩張 mock 圖。
 */

require dirname(__DIR__) . '/vendor/autoload.php';

$app = new think\App(dirname(__DIR__));
$app->initialize();

use think\facade\Db;

$sqlFile = __DIR__ . '/m19_home_banners.sql';
$raw = file_get_contents($sqlFile);
if ($raw === false) {
    fwrite(STDERR, "cannot read m19_home_banners.sql\n");
    exit(1);
}
$lines = [];
foreach (explode("\n", $raw) as $line) {
    $trim = ltrim($line);
    if ($trim === '' || str_starts_with($trim, '--')) {
        continue;
    }
    $lines[] = $line;
}
foreach (array_filter(array_map('trim', explode(';', implode("\n", $lines)))) as $stmt) {
    if ($stmt !== '') {
        Db::execute($stmt);
    }
}

$dir = dirname(__DIR__) . '/public/storage/banner';
if (!is_dir($dir)) {
    mkdir($dir, 0775, true);
}

$now = date('Y-m-d H:i:s');
$count = (int) Db::name('home_banners')->count();
if ($count === 0) {
    $seeds = [
        [
            'image_url' => '/mock/home-banner-1.png',
            'title_zh' => '精緻珠寶',
            'title_en' => 'Fine jewelry',
            'link_type' => 'product',
            'link_value' => 'p4',
            'sort_order' => 10,
        ],
        [
            'image_url' => '/mock/home-banner-2.png',
            'title_zh' => '新品上市',
            'title_en' => 'New arrival',
            'link_type' => 'category',
            'link_value' => 'bracelets',
            'sort_order' => 20,
        ],
    ];
    foreach ($seeds as $row) {
        Db::name('home_banners')->insert(array_merge($row, [
            'status' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]));
    }
}

echo "m19 seed ok (home_banners + storage/banner)\n";
