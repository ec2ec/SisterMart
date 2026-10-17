<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

$app = new think\App(dirname(__DIR__));
$app->initialize();

use think\facade\Db;

Db::execute("CREATE TABLE IF NOT EXISTS member_checkins (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  member_id BIGINT UNSIGNED NOT NULL,
  checkin_date DATE NOT NULL,
  reward INT NOT NULL DEFAULT 0,
  streak INT NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL,
  UNIQUE KEY uk_member_checkins_day (member_id, checkin_date),
  KEY idx_member_checkins_member (member_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$now = date('Y-m-d H:i:s');
$settings = [
    'checkin.base_reward' => '10',
    'checkin.streak_bonus' => '1',
    'checkin.streak_cap' => '30',
];
foreach ($settings as $k => $v) {
    $row = Db::name('shop_settings')->where('setting_key', $k)->find();
    if ($row) {
        Db::name('shop_settings')->where('setting_key', $k)->update(['setting_value' => $v, 'updated_at' => $now]);
    } else {
        Db::name('shop_settings')->insert(['setting_key' => $k, 'setting_value' => $v, 'updated_at' => $now]);
    }
}

// 補齊分類子圖標（與 m14_fix_category_icons.sql 同效）
$icons = [
    'b-gold' => '/products/sub-gold-bead.jpg',
    'n-pearl' => '/products/sub-pearl.jpg',
    'n-clavicle' => '/products/sub-clavicle.jpg',
    'n-heart' => '/products/sub-heart-neck.jpg',
];
foreach ($icons as $code => $icon) {
    Db::name('categories')->where('code', $code)->update(['icon' => $icon, 'updated_at' => $now]);
}

echo "m14 seed ok (checkin + category icons)\n";
