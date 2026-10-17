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

ensureColumn('members', 'total_spent', 'total_spent DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER points');
ensureColumn('members', 'level_id', 'level_id TINYINT UNSIGNED NOT NULL DEFAULT 1 AFTER total_spent');

ensureColumn('coupons', 'stock', 'stock INT NOT NULL DEFAULT 0 AFTER amount');
ensureColumn('coupons', 'claimed', 'claimed INT NOT NULL DEFAULT 0 AFTER stock');
ensureColumn('coupons', 'per_member_limit', 'per_member_limit INT NOT NULL DEFAULT 1 AFTER claimed');
ensureColumn('coupons', 'starts_at', 'starts_at DATETIME NULL AFTER per_member_limit');
ensureColumn('coupons', 'ends_at', 'ends_at DATETIME NULL AFTER starts_at');
ensureColumn('coupons', 'scope', "scope VARCHAR(16) NOT NULL DEFAULT 'all' AFTER ends_at");

ensureColumn('coupon_grants', 'expires_at', 'expires_at DATETIME NULL AFTER order_id');

$now = date('Y-m-d H:i:s');
$welcome = Db::name('coupons')->where('code', 'WELCOME20')->find();
if ($welcome) {
    Db::name('coupons')->where('id', $welcome['id'])->update([
        'stock' => max(1000, (int) ($welcome['stock'] ?? 0)),
        'per_member_limit' => 1,
        'starts_at' => $welcome['starts_at'] ?: date('Y-m-d 00:00:00', strtotime('-30 days')),
        'ends_at' => $welcome['ends_at'] ?: date('Y-m-d 23:59:59', strtotime('+365 days')),
        'scope' => 'all',
        'type' => $welcome['type'] ?: 'fixed',
    ]);
}

$ship = Db::name('coupons')->where('code', 'FREESHIP')->find();
if (!$ship) {
    Db::name('coupons')->insert([
        'code' => 'FREESHIP',
        'name_zh' => '免運券',
        'name_en' => 'Free shipping',
        'type' => 'free_shipping',
        'threshold' => 0,
        'amount' => 0,
        'status' => 1,
        'stock' => 500,
        'claimed' => 0,
        'per_member_limit' => 2,
        'starts_at' => date('Y-m-d 00:00:00', strtotime('-7 days')),
        'ends_at' => date('Y-m-d 23:59:59', strtotime('+180 days')),
        'scope' => 'all',
        'created_at' => $now,
    ]);
}

$off10 = Db::name('coupons')->where('code', 'OFF10PCT')->find();
if (!$off10) {
    Db::name('coupons')->insert([
        'code' => 'OFF10PCT',
        'name_zh' => '全場 9 折',
        'name_en' => '10% off',
        'type' => 'discount',
        'threshold' => 80,
        'amount' => 0.9,
        'status' => 1,
        'stock' => 300,
        'claimed' => 0,
        'per_member_limit' => 1,
        'starts_at' => date('Y-m-d 00:00:00', strtotime('-7 days')),
        'ends_at' => date('Y-m-d 23:59:59', strtotime('+180 days')),
        'scope' => 'all',
        'created_at' => $now,
    ]);
}

// 同步現有會員等級
$members = Db::name('members')->field('id,total_spent')->select()->toArray();
foreach ($members as $m) {
    $spent = (float) ($m['total_spent'] ?? 0);
    $level = Db::name('member_levels')
        ->where('min_spent', '<=', $spent)
        ->order('min_spent', 'desc')
        ->find();
    if ($level) {
        Db::name('members')->where('id', $m['id'])->update(['level_id' => $level['id']]);
    }
}

echo "m6 seed ok\n";
