<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

$app = new think\App(dirname(__DIR__));
$app->initialize();

use think\facade\Db;

$now = date('Y-m-d H:i:s');
$email = 'demo@sistermart.local';
$row = Db::name('members')->where('email', $email)->find();
if (!$row) {
    $id = (int) Db::name('members')->insertGetId([
        'email' => $email,
        'phone' => null,
        'password_hash' => password_hash('demo123456', PASSWORD_DEFAULT),
        'locale' => 'zh-HK',
        'promo_code' => 'TMPDEMO',
        'parent_id' => null,
        'status' => 1,
        'balance' => 100,
        'points' => 8000,
        'total_spent' => 0,
        'level_id' => 1,
        'agreed_terms_at' => $now,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    Db::name('members')->where('id', $id)->update([
        'promo_code' => \app\service\PromoCodeService::fromMemberId((int) $id),
    ]);
    $welcome = Db::name('coupons')->where('code', 'WELCOME20')->find();
    if ($welcome) {
        Db::name('coupon_grants')->insert([
            'coupon_id' => (int) $welcome['id'],
            'member_id' => $id,
            'status' => 'unused',
            'order_id' => null,
            'expires_at' => $welcome['ends_at'] ?? null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
    echo "demo member created id={$id}\n";
} else {
    Db::name('members')->where('id', $row['id'])->update([
        'password_hash' => password_hash('demo123456', PASSWORD_DEFAULT),
        'status' => 1,
        'updated_at' => $now,
    ]);
    echo "demo member reset password id={$row['id']}\n";
}

echo "m9 seed ok\n";
