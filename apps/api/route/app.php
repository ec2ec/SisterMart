<?php

use think\facade\Route;

Route::get('think', function () {
    return 'hello,ThinkPHP8!';
});

Route::get('hello/:name', 'index/hello');

/** M1 健康检查 */
Route::get('health', 'Health/index');
Route::get('api/health', 'Health/index');
