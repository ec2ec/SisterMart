<?php

declare(strict_types=1);

/**
 * M29：首頁商品展示區。
 */

require dirname(__DIR__) . '/vendor/autoload.php';

$app = new think\App(dirname(__DIR__));
$app->initialize();

use think\facade\Db;

function tableExists(string $table): bool
{
    $db = (string) env('DB_NAME', 'sistermart');
    $rows = Db::query(
        'SELECT COUNT(*) AS c FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?',
        [$db, $table],
    );

    return (int) ($rows[0]['c'] ?? 0) > 0;
}

if (!tableExists('home_sections')) {
    Db::execute(<<<'SQL'
CREATE TABLE home_sections (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(64) NOT NULL,
  title_zh VARCHAR(128) NOT NULL DEFAULT '',
  title_en VARCHAR(128) NOT NULL DEFAULT '',
  view_all_type VARCHAR(16) NOT NULL DEFAULT 'list',
  view_all_value VARCHAR(255) NULL,
  sort_order INT NOT NULL DEFAULT 0,
  status TINYINT NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  UNIQUE KEY uk_home_sections_code (code),
  KEY idx_home_sections_sort (status, sort_order, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
}

if (!tableExists('home_section_products')) {
    Db::execute(<<<'SQL'
CREATE TABLE home_section_products (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  section_id BIGINT UNSIGNED NOT NULL,
  product_id BIGINT UNSIGNED NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  UNIQUE KEY uk_section_product (section_id, product_id),
  KEY idx_section_sort (section_id, sort_order, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
}

echo "m29 seed ok (home_sections)\n";
