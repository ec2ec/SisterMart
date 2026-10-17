<?php

declare(strict_types=1);

/**
 * M17：售後窗口天數、客服 WhatsApp。
 */

require dirname(__DIR__) . '/vendor/autoload.php';

$app = new think\App(dirname(__DIR__));
$app->initialize();

use think\facade\Db;

$now = date('Y-m-d H:i:s');
$defaults = [
    'aftersale.window_days' => '30',
    'cs.whatsapp' => '85290001234',
];
foreach ($defaults as $key => $value) {
    $exists = Db::name('shop_settings')->where('setting_key', $key)->find();
    if (!$exists) {
        Db::name('shop_settings')->insert([
            'setting_key' => $key,
            'setting_value' => $value,
            'updated_at' => $now,
        ]);
    }
}

$dir = dirname(__DIR__) . '/public/storage/aftersale';
if (!is_dir($dir)) {
    mkdir($dir, 0775, true);
}

echo "m17 seed ok (aftersale.window_days + cs.whatsapp + storage)\n";
