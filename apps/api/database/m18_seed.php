<?php

declare(strict_types=1);

/**
 * M18：評價表 + storage 目錄。
 */

require dirname(__DIR__) . '/vendor/autoload.php';

$app = new think\App(dirname(__DIR__));
$app->initialize();

use think\facade\Db;

$sqlFile = __DIR__ . '/m18_reviews.sql';
$raw = file_get_contents($sqlFile);
if ($raw === false) {
    fwrite(STDERR, "cannot read m18_reviews.sql\n");
    exit(1);
}

// Drop line comments, then run statements.
$lines = [];
foreach (explode("\n", $raw) as $line) {
    $trim = ltrim($line);
    if ($trim === '' || str_starts_with($trim, '--')) {
        continue;
    }
    $lines[] = $line;
}
$sql = implode("\n", $lines);

foreach (array_filter(array_map('trim', explode(';', $sql))) as $stmt) {
    if ($stmt === '') {
        continue;
    }
    Db::execute($stmt);
}

$dir = dirname(__DIR__) . '/public/storage/review';
if (!is_dir($dir)) {
    mkdir($dir, 0775, true);
}

echo "m18 seed ok (product_reviews + storage/review)\n";
