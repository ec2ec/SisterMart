<?php

declare(strict_types=1);

/**
 * M23：評價後台隱藏 / 商家回覆字段。
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

ensureColumn('product_reviews', 'is_hidden', 'is_hidden TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT \'1=後台隱藏\' AFTER follow_at');
ensureColumn('product_reviews', 'admin_reply', 'admin_reply TEXT NULL AFTER is_hidden');
ensureColumn('product_reviews', 'replied_at', 'replied_at DATETIME NULL AFTER admin_reply');

echo "m23 seed ok (product_reviews is_hidden / admin_reply)\n";
