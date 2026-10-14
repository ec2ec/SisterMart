<?php

use app\middleware\AdminAuth;
use app\middleware\MemberAuth;
use think\facade\Route;

Route::get('think', function () {
    return 'hello,ThinkPHP8!';
});

Route::get('hello/:name', 'index/hello');

/** M1 健康检查 */
Route::get('health', 'Health/index');
Route::get('api/health', 'Health/index');

/** M2 會員登錄與公開底欄 */
Route::post('api/auth/code', 'api.Auth/code');
Route::post('api/auth/register', 'api.Auth/register');
Route::post('api/auth/login', 'api.Auth/login');
Route::post('api/auth/reset', 'api.Auth/reset');
Route::get('api/tabbar', 'api.Tabbar/index');

Route::group('api/auth', function () {
    Route::post('logout', 'api.Auth/logout');
    Route::get('me', 'api.Auth/me');
    Route::patch('locale', 'api.Auth/locale');
})->middleware(MemberAuth::class);

/** M2 後台登錄與底欄配置 */
Route::post('api/admin/login', 'admin.Auth/login');
Route::group('api/admin', function () {
    Route::post('logout', 'admin.Auth/logout');
    Route::get('me', 'admin.Auth/me');
    Route::get('tabbar', 'admin.Tabbar/index');
    Route::put('tabbar', 'admin.Tabbar/save');
})->middleware(AdminAuth::class);
