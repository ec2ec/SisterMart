<?php

declare(strict_types=1);

namespace app\service;

use app\support\ApiException;
use think\facade\Db;

/**
 * M8：超管 / 運營最小權限。
 * super：全部；ops：日常運營（不可管理管理員帳號）。
 */
class AdminAcl
{
    public const PERM_ADMINS = 'admins';
    public const PERM_SETTINGS = 'settings';
    public const PERM_CATALOG = 'catalog';
    public const PERM_SALES = 'sales';
    public const PERM_CUSTOMERS = 'customers';
    public const PERM_CMS = 'cms';
    public const PERM_MARKETING = 'marketing';

    /** @return list<string> */
    public static function permissionsForRole(string $roleCode): array
    {
        if ($roleCode === 'super') {
            return ['*'];
        }

        // 運營：商品/訂單售後/會員資產/內容/營銷/店鋪參數與底欄；不可管管理員
        return [
            self::PERM_CATALOG,
            self::PERM_SALES,
            self::PERM_CUSTOMERS,
            self::PERM_CMS,
            self::PERM_MARKETING,
            self::PERM_SETTINGS,
        ];
    }

    public static function roleCode(?array $admin): string
    {
        if (!$admin) {
            return '';
        }
        $role = Db::name('admin_roles')->where('id', $admin['role_id'])->find();

        return (string) ($role['code'] ?? 'ops');
    }

    public static function can(?array $admin, string $permission): bool
    {
        $perms = self::permissionsForRole(self::roleCode($admin));
        if (in_array('*', $perms, true)) {
            return true;
        }

        return in_array($permission, $perms, true);
    }

    public static function assert(?array $admin, string $permission): void
    {
        if (!self::can($admin, $permission)) {
            throw new ApiException('FORBIDDEN', '權限不足（僅超級管理員可操作）', 403, 40301);
        }
    }

    /** 依後台路由前綴推斷權限域 */
    public static function permissionForPath(string $path): ?string
    {
        $path = '/' . ltrim($path, '/');
        // 當前登錄者資訊全員可讀
        if ($path === '/api/v1/admin/admins/me' || str_starts_with($path, '/api/v1/admin/admins/me/')) {
            return null;
        }
        $map = [
            '/api/v1/admin/admins' => self::PERM_ADMINS,
            '/api/v1/admin/settings' => self::PERM_SETTINGS,
            '/api/v1/admin/tabbar' => self::PERM_SETTINGS,
            '/api/v1/admin/categories' => self::PERM_CATALOG,
            '/api/v1/admin/products' => self::PERM_CATALOG,
            '/api/v1/admin/service-tags' => self::PERM_CATALOG,
            '/api/v1/admin/orders' => self::PERM_SALES,
            '/api/v1/admin/aftersales' => self::PERM_SALES,
            '/api/v1/admin/reviews' => self::PERM_SALES,
            '/api/v1/admin/jobs' => self::PERM_SALES,
            '/api/v1/admin/members' => self::PERM_CUSTOMERS,
            '/api/v1/admin/member-levels' => self::PERM_CUSTOMERS,
            '/api/v1/admin/articles' => self::PERM_CMS,
            '/api/v1/admin/messages' => self::PERM_CMS,
            '/api/v1/admin/newsletter-subscribers' => self::PERM_CMS,
            '/api/v1/admin/home-banners' => self::PERM_CMS,
            '/api/v1/admin/home-ops' => self::PERM_CMS,
            '/api/v1/admin/home-sections' => self::PERM_CMS,
            '/api/v1/admin/uploads/banner' => self::PERM_CMS,
            '/api/v1/admin/coupons' => self::PERM_MARKETING,
            '/api/v1/admin/points' => self::PERM_MARKETING,
            '/api/v1/admin/points-mall' => self::PERM_MARKETING,
            '/api/v1/admin/seckill' => self::PERM_MARKETING,
            '/api/v1/admin/withdrawals' => self::PERM_SALES,
        ];
        foreach ($map as $prefix => $perm) {
            if (str_starts_with($path, $prefix)) {
                return $perm;
            }
        }

        return null;
    }
}
