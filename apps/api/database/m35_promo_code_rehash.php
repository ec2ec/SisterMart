<?php

declare(strict_types=1);

/**
 * 一次性：按 member id 重算帶 HMAC 校驗位的 promo_code。
 * 用法（倉庫根或 api 目錄）：
 *   cd apps/api && php database/m35_promo_code_rehash.php
 */

require __DIR__ . '/../vendor/autoload.php';

$app = new think\App(__DIR__ . '/..');
$app->initialize();

use app\service\PromoCodeService;
use think\facade\Db;

$rows = Db::name('members')->field('id,promo_code')->select()->toArray();
$updated = 0;
foreach ($rows as $row) {
    $id = (int) $row['id'];
    $next = PromoCodeService::fromMemberId($id);
    if ((string) $row['promo_code'] === $next) {
        continue;
    }
    Db::name('members')->where('id', $id)->update([
        'promo_code' => $next,
        'updated_at' => date('Y-m-d H:i:s'),
    ]);
    $updated++;
    echo "id={$id} {$row['promo_code']} -> {$next}\n";
}

echo "done updated={$updated} total=" . count($rows) . "\n";
