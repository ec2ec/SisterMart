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
})->middleware(AdminAuth::class);
