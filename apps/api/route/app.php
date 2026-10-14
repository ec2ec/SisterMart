<?php

use app\middleware\AdminAuth;
use app\middleware\MemberAuth;
use think\facade\Route;

Route::get('think', function () {
    return 'hello,ThinkPHP8!';
});

Route::get('hello/:name', 'index/hello');

/** 健康检查：规范路径 + 运维兼容别名 */
Route::get('api/v1/health', 'Health/index');
Route::get('health', 'Health/index');
Route::get('api/health', 'Health/index');

/** M2 会员资源 */
Route::post('api/v1/verification-codes', 'api.VerificationCodes/store');
Route::post('api/v1/members', 'api.Members/store');
Route::patch('api/v1/member-passwords', 'api.MemberPasswords/update');
Route::post('api/v1/member-sessions', 'api.MemberSessions/store');
Route::get('api/v1/tabbar', 'api.Tabbar/index');

/** M3 公開商品與文章（帶參數路由須排在集合路由之前，避免前綴誤匹配） */
Route::get('api/v1/categories', 'api.Categories/index');
Route::get('api/v1/products/:id', 'api.Products/read');
Route::get('api/v1/products', 'api.Products/index');
Route::get('api/v1/articles/:code', 'api.Articles/read');

Route::group('api/v1', function () {
    Route::get('members/me', 'api.Members/me');
    Route::patch('members/me', 'api.Members/updateMe');
    Route::delete('member-sessions/current', 'api.MemberSessions/destroy');
})->middleware(MemberAuth::class);

/** M2 后台资源 */
Route::post('api/v1/admin/sessions', 'admin.Sessions/store');

Route::group('api/v1/admin', function () {
    Route::delete('sessions/current', 'admin.Sessions/destroy');
    Route::get('admins/me', 'admin.Admins/me');
    Route::get('tabbar', 'admin.Tabbar/index');
    Route::put('tabbar', 'admin.Tabbar/update');

    Route::get('categories', 'admin.Categories/index');
    Route::post('categories', 'admin.Categories/store');
    Route::put('categories/:id', 'admin.Categories/update');

    Route::get('products/:id', 'admin.Products/read');
    Route::put('products/:id', 'admin.Products/update');
    Route::get('products', 'admin.Products/index');
    Route::post('products', 'admin.Products/store');
    Route::get('service-tags', 'admin.Products/tags');

    Route::get('articles/:code', 'admin.Articles/read');
    Route::put('articles/:id', 'admin.Articles/update');
    Route::get('articles', 'admin.Articles/index');
    Route::post('articles', 'admin.Articles/store');

    Route::get('settings/sold-display', 'admin.ShopSettings/soldDisplay');
    Route::put('settings/sold-display', 'admin.ShopSettings/updateSoldDisplay');
})->middleware(AdminAuth::class);
