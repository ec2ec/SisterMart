<?php

declare(strict_types=1);

/**
 * M30：會員暱稱欄。
 */

require dirname(__DIR__) . '/vendor/autoload.php';

$app = new think\App(dirname(__DIR__));
$app->initialize();

use think\facade\Db;

$db = (string) env('DB_NAME', 'sistermart');
$exists = Db::query(
    'SELECT COUNT(*) AS c FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?',
    [$db, 'members', 'nickname'],
);
if ((int) ($exists[0]['c'] ?? 0) === 0) {
    Db::execute("ALTER TABLE `members` ADD COLUMN nickname VARCHAR(64) NULL COMMENT '暱稱' AFTER locale");
}

echo "m30 seed ok (members.nickname)\n";
