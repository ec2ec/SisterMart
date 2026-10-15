<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

$app = new think\App(dirname(__DIR__));
$app->initialize();

use think\facade\Db;

function ensureColumn(string $table, string $column, string $ddl): void
{
    $db = (string) env('database.database', 'sistermart');
    $exists = Db::query(
        'SELECT COUNT(*) AS c FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?',
        [$db, $table, $column],
    );
    if ((int) ($exists[0]['c'] ?? 0) === 0) {
        Db::execute("ALTER TABLE `{$table}` ADD COLUMN {$ddl}");
    }
}

ensureColumn('orders', 'shipped_at', 'shipped_at DATETIME NULL AFTER paid_at');
ensureColumn('orders', 'completed_at', 'completed_at DATETIME NULL AFTER shipped_at');
ensureColumn('orders', 'aftersale_touched', 'aftersale_touched TINYINT NOT NULL DEFAULT 0 AFTER completed_at');
ensureColumn('orders', 'points_awarded', 'points_awarded INT NOT NULL DEFAULT 0 AFTER aftersale_touched');
ensureColumn('order_items', 'refunded_qty', 'refunded_qty INT NOT NULL DEFAULT 0 AFTER qty');

echo "m5 seed ok\n";
