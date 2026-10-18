<?php

declare(strict_types=1);

/**
 * M28：收藏表 + 會員頭像欄。
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

if (!tableExists('member_favorites')) {
    Db::execute(<<<'SQL'
CREATE TABLE member_favorites (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  member_id BIGINT UNSIGNED NOT NULL,
  product_id BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL,
  UNIQUE KEY uk_fav_member_product (member_id, product_id),
  KEY idx_fav_member_created (member_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
}

ensureColumn('members', 'avatar_url', "avatar_url VARCHAR(512) NULL COMMENT '頭像路徑' AFTER locale");

echo "m28 seed ok (member_favorites + members.avatar_url)\n";
