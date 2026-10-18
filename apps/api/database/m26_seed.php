<?php

declare(strict_types=1);

/**
 * M26：瀏覽足跡 browse_history（同一會員同一商品只保留最近一次）。
 */

require dirname(__DIR__) . '/vendor/autoload.php';

$app = new think\App(dirname(__DIR__));
$app->initialize();

use think\facade\Db;

$db = (string) env('DB_NAME', 'sistermart');
$exists = Db::query(
    'SELECT COUNT(*) AS c FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?',
    [$db, 'browse_history'],
);
if ((int) ($exists[0]['c'] ?? 0) === 0) {
    Db::execute(<<<'SQL'
CREATE TABLE browse_history (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  member_id BIGINT UNSIGNED NOT NULL,
  product_id BIGINT UNSIGNED NOT NULL,
  viewed_at DATETIME NOT NULL,
  created_at DATETIME NOT NULL,
  UNIQUE KEY uk_browse_member_product (member_id, product_id),
  KEY idx_browse_member_viewed (member_id, viewed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
}

echo "m26 seed ok (browse_history)\n";
