<?php

declare(strict_types=1);

/**
 * M16：佣金沖回表、original_amount、分享瀏覽表與分值設定。
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

Db::execute("CREATE TABLE IF NOT EXISTS commission_clawbacks (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  aftersale_id BIGINT UNSIGNED NOT NULL,
  commission_id BIGINT UNSIGNED NOT NULL,
  amount DECIMAL(12,2) NOT NULL,
  created_at DATETIME NOT NULL,
  UNIQUE KEY uk_clawback_aftersale (aftersale_id),
  KEY idx_clawback_commission (commission_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

Db::execute("CREATE TABLE IF NOT EXISTS share_views (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  owner_id BIGINT UNSIGNED NOT NULL,
  product_code VARCHAR(64) NOT NULL,
  viewer_ip VARCHAR(64) NOT NULL,
  viewer_member_id BIGINT UNSIGNED NULL,
  points INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  UNIQUE KEY uk_share_views_dedupe (owner_id, product_code, viewer_ip),
  KEY idx_share_views_owner (owner_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

ensureColumn('commissions', 'original_amount', 'original_amount DECIMAL(12,2) NULL AFTER amount');
ensureColumn('order_items', 'compare_at', 'compare_at DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER unit_price');

Db::execute('UPDATE commissions SET original_amount = amount WHERE original_amount IS NULL');
Db::execute('UPDATE order_items oi
  JOIN skus s ON s.id = oi.sku_id
  SET oi.compare_at = GREATEST(s.compare_at, oi.unit_price)
  WHERE oi.compare_at = 0 OR oi.compare_at < oi.unit_price');

$now = date('Y-m-d H:i:s');
$key = 'points.share_view';
$setting = Db::name('shop_settings')->where('setting_key', $key)->find();
if (!$setting) {
    Db::name('shop_settings')->insert([
        'setting_key' => $key,
        'setting_value' => '2',
        'updated_at' => $now,
    ]);
}

echo "m16 seed ok (clawback + share_views + points.share_view + order_items.compare_at)\n";
