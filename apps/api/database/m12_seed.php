<?php

declare(strict_types=1);

/**
 * M12：積分商城表、orders.points_mall_item_id、種子 pm1/pm2（p3/p4）。
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

Db::execute("CREATE TABLE IF NOT EXISTS points_mall_items (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(32) NOT NULL,
  product_id BIGINT UNSIGNED NOT NULL,
  product_code VARCHAR(64) NOT NULL,
  sku_id BIGINT UNSIGNED NOT NULL,
  points_cost INT NOT NULL,
  stock INT NOT NULL DEFAULT 0,
  sold INT NOT NULL DEFAULT 0,
  status TINYINT NOT NULL DEFAULT 1,
  image_url VARCHAR(512) NULL,
  name_zh VARCHAR(255) NOT NULL,
  name_en VARCHAR(255) NOT NULL,
  detail_zh TEXT NULL,
  detail_en TEXT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  UNIQUE KEY uk_points_mall_items_code (code),
  KEY idx_points_mall_items_product (product_code),
  KEY idx_points_mall_items_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

ensureColumn('orders', 'points_mall_item_id', 'points_mall_item_id BIGINT UNSIGNED NULL AFTER seckill_item_id');

$catalog = [
    'pm1' => [
        'product' => 'p3',
        'points' => 800,
        'stock' => 20,
        'name_zh' => '四葉草手鏈（積分兌）',
        'name_en' => 'Clover (Points)',
        'detail_zh' => '積分商城兌換商品。結算扣積分；運費按現金規則（未滿包郵門檻需支付運費）。',
        'detail_en' => 'Points-mall item. Redeem with points; shipping follows cash rules.',
    ],
    'pm2' => [
        'product' => 'p4',
        'points' => 1200,
        'stock' => 8,
        'name_zh' => '紅心手鏈（積分兌）',
        'name_en' => 'Heart (Points)',
        'detail_zh' => '積分商城兌換商品。結算扣積分；運費按現金規則（未滿包郵門檻需支付運費）。',
        'detail_en' => 'Points-mall item. Redeem with points; shipping follows cash rules.',
    ],
];

$now = date('Y-m-d H:i:s');
foreach ($catalog as $code => $cfg) {
    $product = Db::name('products')->where('code', $cfg['product'])->where('status', 1)->find();
    if (!$product) {
        echo "skip {$code}: product missing\n";
        continue;
    }
    $sku = Db::name('skus')->where('product_id', $product['id'])->where('status', 1)->order('id', 'asc')->find();
    if (!$sku) {
        echo "skip {$code}: sku missing\n";
        continue;
    }
    $payload = [
        'product_id' => (int) $product['id'],
        'product_code' => $cfg['product'],
        'sku_id' => (int) $sku['id'],
        'points_cost' => $cfg['points'],
        'stock' => $cfg['stock'],
        'status' => 1,
        'image_url' => $sku['image_url'] ?: $product['cover_url'],
        'name_zh' => $cfg['name_zh'],
        'name_en' => $cfg['name_en'],
        'detail_zh' => $cfg['detail_zh'],
        'detail_en' => $cfg['detail_en'],
        'updated_at' => $now,
    ];
    $existing = Db::name('points_mall_items')->where('code', $code)->find();
    if ($existing) {
        Db::name('points_mall_items')->where('id', $existing['id'])->update($payload);
    } else {
        $payload['code'] = $code;
        $payload['sold'] = 0;
        $payload['created_at'] = $now;
        Db::name('points_mall_items')->insert($payload);
    }
}

echo "m12 seed ok (pm1 / pm2)\n";
