<?php

declare(strict_types=1);

/**
 * M11：建秒殺表、orders.seckill_item_id、種子場次（p1 / p4）。
 * 用法：php database/m11_seed.php（cwd 任意，相對本文件定位 API 根）
 */

require dirname(__DIR__) . '/vendor/autoload.php';

$app = new think\App(dirname(__DIR__));
$app->initialize();

use think\facade\Db;

function ensureColumn(string $table, string $column, string $ddl): void
{
    $db = (string) env('DB_NAME', 'sistermart');
    $exists = Db::query(
        'SELECT COUNT(*) AS c FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?',
        [$db, $table, $column],
    );
    if ((int) ($exists[0]['c'] ?? 0) === 0) {
        Db::execute("ALTER TABLE `{$table}` ADD COLUMN {$ddl}");
    }
}

Db::execute("CREATE TABLE IF NOT EXISTS seckill_sessions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(32) NOT NULL,
  name_zh VARCHAR(128) NOT NULL,
  name_en VARCHAR(128) NOT NULL,
  starts_at DATETIME NOT NULL,
  ends_at DATETIME NOT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  UNIQUE KEY uk_seckill_sessions_code (code),
  KEY idx_seckill_sessions_window (starts_at, ends_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

Db::execute("CREATE TABLE IF NOT EXISTS seckill_items (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  session_id BIGINT UNSIGNED NOT NULL,
  product_id BIGINT UNSIGNED NOT NULL,
  product_code VARCHAR(64) NOT NULL,
  sku_id BIGINT UNSIGNED NOT NULL,
  seckill_price DECIMAL(12,2) NOT NULL,
  origin_price DECIMAL(12,2) NOT NULL,
  stock INT NOT NULL DEFAULT 0,
  sold INT NOT NULL DEFAULT 0,
  image_url VARCHAR(512) NULL,
  name_zh VARCHAR(255) NOT NULL,
  name_en VARCHAR(255) NOT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  KEY idx_seckill_items_session (session_id),
  KEY idx_seckill_items_product_code (product_code),
  CONSTRAINT fk_seckill_items_session FOREIGN KEY (session_id) REFERENCES seckill_sessions (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

ensureColumn('orders', 'seckill_item_id', 'seckill_item_id BIGINT UNSIGNED NULL AFTER coupon_grant_id');

$now = date('Y-m-d H:i:s');
$products = [
    'p1' => ['price' => 88.0, 'origin' => 128.0, 'stock_live' => 15, 'stock_soon' => 20],
    'p4' => ['price' => 49.0, 'origin' => 69.0, 'stock_live' => 8, 'stock_soon' => 12],
];

$live = Db::name('seckill_sessions')->where('code', 'sk-live')->find();
if (!$live) {
    $liveId = (int) Db::name('seckill_sessions')->insertGetId([
        'code' => 'sk-live',
        'name_zh' => '午間閃購',
        'name_en' => 'Noon Flash',
        'starts_at' => date('Y-m-d H:i:s', time() - 1800),
        'ends_at' => date('Y-m-d H:i:s', time() + 7 * 86400),
        'created_at' => $now,
        'updated_at' => $now,
    ]);
} else {
    $liveId = (int) $live['id'];
    Db::name('seckill_sessions')->where('id', $liveId)->update([
        'starts_at' => date('Y-m-d H:i:s', time() - 1800),
        'ends_at' => date('Y-m-d H:i:s', time() + 7 * 86400),
        'updated_at' => $now,
    ]);
}

$soon = Db::name('seckill_sessions')->where('code', 'sk-soon')->find();
if (!$soon) {
    $soonId = (int) Db::name('seckill_sessions')->insertGetId([
        'code' => 'sk-soon',
        'name_zh' => '晚間預告',
        'name_en' => 'Evening Soon',
        'starts_at' => date('Y-m-d H:i:s', time() + 3600),
        'ends_at' => date('Y-m-d H:i:s', time() + 7 * 86400 + 10800),
        'created_at' => $now,
        'updated_at' => $now,
    ]);
} else {
    $soonId = (int) $soon['id'];
    Db::name('seckill_sessions')->where('id', $soonId)->update([
        'starts_at' => date('Y-m-d H:i:s', time() + 3600),
        'ends_at' => date('Y-m-d H:i:s', time() + 7 * 86400 + 10800),
        'updated_at' => $now,
    ]);
}

function upsertSeckillItem(int $sessionId, string $code, float $price, float $origin, int $stock): void
{
    $product = Db::name('products')->where('code', $code)->where('status', 1)->find();
    if (!$product) {
        echo "skip {$code}: product missing\n";

        return;
    }
    $sku = Db::name('skus')->where('product_id', $product['id'])->where('status', 1)->order('id', 'asc')->find();
    if (!$sku) {
        echo "skip {$code}: sku missing\n";

        return;
    }
    $names = Db::name('product_i18n')->where('product_id', $product['id'])->select()->toArray();
    $zh = '';
    $en = '';
    foreach ($names as $n) {
        if ($n['locale'] === 'en') {
            $en = $n['name'];
        } else {
            $zh = $n['name'];
        }
    }
    $now = date('Y-m-d H:i:s');
    $existing = Db::name('seckill_items')->where([
        'session_id' => $sessionId,
        'product_code' => $code,
    ])->find();
    $payload = [
        'product_id' => (int) $product['id'],
        'product_code' => $code,
        'sku_id' => (int) $sku['id'],
        'seckill_price' => $price,
        'origin_price' => $origin,
        'stock' => $stock,
        'image_url' => $sku['image_url'] ?: $product['cover_url'],
        'name_zh' => $zh ?: $code,
        'name_en' => $en ?: $code,
        'updated_at' => $now,
    ];
    if ($existing) {
        // 不覆蓋 sold，僅刷新價與剩餘庫存顯示基準時重置 stock（驗收用）
        Db::name('seckill_items')->where('id', $existing['id'])->update($payload);
    } else {
        $payload['session_id'] = $sessionId;
        $payload['sold'] = 0;
        $payload['created_at'] = $now;
        Db::name('seckill_items')->insert($payload);
    }
}

foreach ($products as $code => $cfg) {
    upsertSeckillItem($liveId, $code, $cfg['price'], $cfg['origin'], $cfg['stock_live']);
    upsertSeckillItem($soonId, $code, $cfg['price'], $cfg['origin'], $cfg['stock_soon']);
}

echo "m11 seed ok (sessions sk-live / sk-soon)\n";
