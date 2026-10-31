<?php

declare(strict_types=1);

/**
 * M46：供應商表 + products.supplier_id 回填默認供應商（冪等）。
 *
 *   php database/m46_seed.php
 */

require __DIR__ . '/../vendor/autoload.php';

$app = new think\App(dirname(__DIR__));
$app->initialize();

use think\facade\Db;

function m46_has_table(string $table): bool
{
    $row = Db::query(
        'SELECT COUNT(*) AS c FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
        [$table],
    );

    return (int) ($row[0]['c'] ?? 0) > 0;
}

function m46_has_column(string $table, string $column): bool
{
    $row = Db::query(
        'SELECT COUNT(*) AS c FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
        [$table, $column],
    );

    return (int) ($row[0]['c'] ?? 0) > 0;
}

if (!m46_has_table('suppliers')) {
    Db::execute(<<<'SQL'
CREATE TABLE `suppliers` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code` VARCHAR(16) NOT NULL COMMENT '人工簡碼，如 S001',
  `name` VARCHAR(120) NOT NULL COMMENT '供應商全名',
  `type` VARCHAR(32) NOT NULL DEFAULT 'factory' COMMENT 'factory|wholesaler|dropship|bulk|local_stall',
  `status` TINYINT NOT NULL DEFAULT 1 COMMENT '1啟用 0停用（停用後不可新綁）',
  `remark` VARCHAR(500) NULL,
  `contact_person` VARCHAR(80) NULL,
  `phone` VARCHAR(40) NULL,
  `whatsapp` VARCHAR(40) NULL,
  `email` VARCHAR(120) NULL,
  `address` VARCHAR(255) NULL,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_suppliers_code` (`code`),
  KEY `idx_suppliers_status` (`status`),
  KEY `idx_suppliers_type` (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    echo "created suppliers\n";
}

$now = date('Y-m-d H:i:s');
$exists = Db::name('suppliers')->where('code', 'S001')->find();
if (!$exists) {
    Db::name('suppliers')->insert([
        'code' => 'S001',
        'name' => '默認供應商',
        'type' => 'factory',
        'status' => 1,
        'remark' => '系統預置；舊測試商品已綁定此供應商',
        'contact_person' => null,
        'phone' => null,
        'whatsapp' => null,
        'email' => null,
        'address' => null,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    echo "seeded S001\n";
}

if (!m46_has_column('products', 'supplier_id')) {
    Db::execute(
        "ALTER TABLE `products` ADD COLUMN `supplier_id` BIGINT UNSIGNED NULL COMMENT '供應商' AFTER `category_id`, ADD KEY `idx_products_supplier` (`supplier_id`)"
    );
    echo "added products.supplier_id\n";
}

$defaultId = (int) (Db::name('suppliers')->order('id', 'asc')->value('id') ?: 0);
if ($defaultId <= 0) {
    fwrite(STDERR, "no suppliers row after seed\n");
    exit(1);
}

$n = Db::name('products')
    ->where(function ($q) {
        $q->whereNull('supplier_id')->whereOr('supplier_id', 0);
    })
    ->update(['supplier_id' => $defaultId]);
echo "backfilled products.supplier_id={$defaultId} rows={$n}\n";

$left = (int) Db::name('products')->whereNull('supplier_id')->count();
if ($left === 0) {
    try {
        Db::execute('ALTER TABLE `products` MODIFY COLUMN `supplier_id` BIGINT UNSIGNED NOT NULL');
        echo "products.supplier_id set NOT NULL\n";
    } catch (Throwable $e) {
        echo 'modify NOT NULL skipped: ' . $e->getMessage() . "\n";
    }
} else {
    echo "skip NOT NULL, still null rows={$left}\n";
}

echo "m46 suppliers seed ok\n";
