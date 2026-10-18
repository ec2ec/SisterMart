<?php

declare(strict_types=1);

/**
 * M25：評價審核狀態 moderation_status（pending / approved / rejected）。
 * 既有評價視為已通過；新建 / 追評後進入待審。
 */

require dirname(__DIR__) . '/vendor/autoload.php';

$app = new think\App(dirname(__DIR__));
$app->initialize();

use think\facade\Db;

$db = (string) env('DB_NAME', 'sistermart');
$exists = Db::query(
    'SELECT COUNT(*) AS c FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?',
    [$db, 'product_reviews', 'moderation_status'],
);
$added = (int) ($exists[0]['c'] ?? 0) === 0;
if ($added) {
    Db::execute(
        "ALTER TABLE `product_reviews` ADD COLUMN moderation_status VARCHAR(16) NOT NULL DEFAULT 'pending' COMMENT 'pending|approved|rejected' AFTER is_hidden",
    );
    // 歷史行：列默認 pending，回填為已通過，避免前台突然空白。
    Db::execute("UPDATE product_reviews SET moderation_status = 'approved'");
}

echo "m25 seed ok (product_reviews.moderation_status)\n";
