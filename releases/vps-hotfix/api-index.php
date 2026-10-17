<?php
namespace think;

$backend = __DIR__ . '/../sm-backend';
$pc = $backend . '/vendor/composer/platform_check.php';
if (is_file($pc)) {
    $src = (string) file_get_contents($pc);
    if (str_contains($src, '80200') || str_contains($src, '8.2.0')) {
        file_put_contents($pc, "<?php\n// Disabled for shared-host PHP 8.1; prefer panel PHP >= 8.2\n");
    }
}
$env = $backend . '/.env';
if (!is_file($env) || filesize($env) < 10) {
    file_put_contents($env, <<<'ENV'
APP_DEBUG = false

DB_TYPE = mysql
DB_HOST = localhost
DB_NAME = gao6154221
DB_USER = gao6154221
DB_PASS = zf3ea5gee
DB_PORT = 3306
DB_CHARSET = utf8mb4
DB_PREFIX =

DEFAULT_LANG = zh-cn
ENV);
}

require $backend . '/vendor/autoload.php';
$http = (new App($backend . '/'))->http;
$response = $http->run();
$response->send();
$http->end($response);
