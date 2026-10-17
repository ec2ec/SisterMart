<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

$app = new think\App(dirname(__DIR__));
$app->initialize();

use think\facade\Db;

$now = date('Y-m-d H:i:s');
$ops = Db::name('admins')->where('email', 'ops@sistermart.local')->find();
if (!$ops) {
    Db::name('admins')->insert([
        'email' => 'ops@sistermart.local',
        'password_hash' => password_hash('ops123456', PASSWORD_DEFAULT),
        'name' => '運營帳號',
        'role_id' => 2,
        'status' => 1,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    echo "ops admin created\n";
} else {
    echo "ops admin exists\n";
}

echo "m8 seed ok\n";
