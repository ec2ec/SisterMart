<?php

declare(strict_types=1);

/**
 * M13：分銷表、商品佣金、demo 下級、最低提現額設定。
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

Db::execute("CREATE TABLE IF NOT EXISTS commissions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  beneficiary_id BIGINT UNSIGNED NOT NULL,
  buyer_id BIGINT UNSIGNED NOT NULL,
  order_id BIGINT UNSIGNED NOT NULL,
  order_item_id BIGINT UNSIGNED NOT NULL,
  product_id BIGINT UNSIGNED NOT NULL,
  amount DECIMAL(12,2) NOT NULL,
  status VARCHAR(16) NOT NULL,
  created_at DATETIME NOT NULL,
  settled_at DATETIME NULL,
  updated_at DATETIME NOT NULL,
  KEY idx_commissions_beneficiary (beneficiary_id, status),
  KEY idx_commissions_order (order_id),
  KEY idx_commissions_buyer (buyer_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

Db::execute("CREATE TABLE IF NOT EXISTS withdrawals (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  member_id BIGINT UNSIGNED NOT NULL,
  channel VARCHAR(16) NOT NULL,
  amount DECIMAL(12,2) NOT NULL,
  status VARCHAR(16) NOT NULL,
  fps_name VARCHAR(128) NULL,
  fps_id VARCHAR(128) NULL,
  review_note VARCHAR(255) NULL,
  created_at DATETIME NOT NULL,
  reviewed_at DATETIME NULL,
  updated_at DATETIME NOT NULL,
  KEY idx_withdrawals_member (member_id, status),
  KEY idx_withdrawals_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

ensureColumn('products', 'commission_type', "commission_type VARCHAR(16) NULL COMMENT 'percent|fixed' AFTER cover_url");
ensureColumn('products', 'commission_value', 'commission_value DECIMAL(12,2) NULL AFTER commission_type');
ensureColumn('members', 'commission_available', 'commission_available DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER points');

$now = date('Y-m-d H:i:s');
Db::name('products')->where('code', 'p1')->update([
    'commission_type' => 'percent',
    'commission_value' => 10,
    'updated_at' => $now,
]);
Db::name('products')->where('code', 'p4')->update([
    'commission_type' => 'fixed',
    'commission_value' => 5,
    'updated_at' => $now,
]);

$setting = Db::name('shop_settings')->where('setting_key', 'distribution.withdraw_min')->find();
if (!$setting) {
    Db::name('shop_settings')->insert([
        'setting_key' => 'distribution.withdraw_min',
        'setting_value' => '100',
        'updated_at' => $now,
    ]);
}

$demo = Db::name('members')->where('email', 'demo@sistermart.local')->find();
if ($demo) {
    $childEmail = 'downline@sistermart.local';
    $child = Db::name('members')->where('email', $childEmail)->find();
    if (!$child) {
        $cid = (int) Db::name('members')->insertGetId([
            'email' => $childEmail,
            'phone' => null,
            'password_hash' => password_hash('demo123456', PASSWORD_DEFAULT),
            'locale' => 'zh-HK',
            'promo_code' => 'TMPCHILD',
            'parent_id' => (int) $demo['id'],
            'status' => 1,
            'balance' => 50,
            'points' => 2000,
            'commission_available' => 0,
            'total_spent' => 0,
            'level_id' => 1,
            'agreed_terms_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        Db::name('members')->where('id', $cid)->update([
            'promo_code' => 'SM' . str_pad((string) $cid, 4, '0', STR_PAD_LEFT),
        ]);
        // 複製 demo 默認地址便於下單
        $addr = Db::name('addresses')->where('member_id', $demo['id'])->order('id', 'asc')->find();
        if ($addr) {
            Db::name('addresses')->insert([
                'member_id' => $cid,
                'receiver' => 'Downline',
                'phone' => $addr['phone'],
                'region' => $addr['region'],
                'district' => $addr['district'],
                'detail' => $addr['detail'],
                'is_default' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
        echo "created downline {$childEmail} under demo id={$demo['id']}\n";
    } else {
        Db::name('members')->where('id', $child['id'])->update([
            'parent_id' => (int) $demo['id'],
            'updated_at' => $now,
        ]);
        echo "linked existing downline to demo\n";
    }
}

echo "m13 seed ok (p1 10% / p4 fixed 5 / withdraw_min 100)\n";
