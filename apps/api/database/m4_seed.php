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

ensureColumn('members', 'balance', 'balance DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER status');
ensureColumn('members', 'points', 'points INT NOT NULL DEFAULT 0 AFTER balance');

$now = date('Y-m-d H:i:s');
$coupon = Db::name('coupons')->where('code', 'WELCOME20')->find();
if (!$coupon) {
    $cid = (int) Db::name('coupons')->insertGetId([
        'code' => 'WELCOME20',
        'name_zh' => '滿 100 減 20',
        'name_en' => 'HK$20 off over 100',
        'type' => 'fixed',
        'threshold' => 100,
        'amount' => 20,
        'status' => 1,
        'created_at' => $now,
    ]);
} else {
    $cid = (int) $coupon['id'];
}

// 給已有會員補一點餘額/積分與歡迎券，便於驗收「實付為 0」
$members = Db::name('members')->select()->toArray();
foreach ($members as $m) {
    $id = (int) $m['id'];
    if ((float) ($m['balance'] ?? 0) < 1) {
        Db::name('members')->where('id', $id)->update(['balance' => 50, 'points' => 5000, 'updated_at' => $now]);
    }
    $grant = Db::name('coupon_grants')->where(['member_id' => $id, 'coupon_id' => $cid])->find();
    if (!$grant) {
        Db::name('coupon_grants')->insert([
            'coupon_id' => $cid,
            'member_id' => $id,
            'status' => 'unused',
            'order_id' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
}

echo "m4 seed ok\n";
