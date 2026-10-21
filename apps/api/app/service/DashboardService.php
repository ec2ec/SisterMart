<?php

declare(strict_types=1);

namespace app\service;

use think\facade\Db;

/**
 * 後台概覽：PRD §18 今日訂單/實付 + 待辦計數；附低庫存與維護狀態。
 */
class DashboardService
{
    private const LOW_STOCK_THRESHOLD = 5;

    /** @return array<string, mixed> */
    public function summary(): array
    {
        $dayStart = date('Y-m-d 00:00:00');
        $dayEnd = date('Y-m-d 23:59:59');

        $todayOrderCount = (int) Db::name('orders')
            ->whereBetween('created_at', [$dayStart, $dayEnd])
            ->count();

        $todayPaidAmount = (float) Db::name('orders')
            ->whereBetween('paid_at', [$dayStart, $dayEnd])
            ->whereNotNull('paid_at')
            ->sum('pay_amount');

        $pendingShipment = (int) Db::name('orders')
            ->where('status', 'pending_shipment')
            ->count();

        $pendingAftersale = (int) Db::name('aftersales')
            ->where('status', 'pending_review')
            ->count();

        $pendingWithdrawal = (int) Db::name('withdrawals')
            ->where('status', 'pending_review')
            ->count();

        $pendingReview = (int) Db::name('product_reviews')
            ->where('moderation_status', ReviewService::STATUS_PENDING)
            ->count();

        $lowStockSkus = (int) Db::name('skus')
            ->alias('s')
            ->join('products p', 'p.id = s.product_id')
            ->where('s.status', 1)
            ->where('p.status', 1)
            ->where('s.stock', '<=', self::LOW_STOCK_THRESHOLD)
            ->count();

        $maintenance = (new MaintenanceService())->get();

        return [
            'today' => [
                'order_count' => $todayOrderCount,
                'paid_amount' => round($todayPaidAmount, 2),
            ],
            'todo' => [
                'pending_shipment' => $pendingShipment,
                'pending_aftersale' => $pendingAftersale,
                'pending_withdrawal' => $pendingWithdrawal,
                'pending_review' => $pendingReview,
            ],
            'alerts' => [
                'low_stock_skus' => $lowStockSkus,
                'low_stock_threshold' => self::LOW_STOCK_THRESHOLD,
                'maintenance_enabled' => (bool) ($maintenance['enabled'] ?? false),
            ],
            'generated_at' => date('Y-m-d H:i:s'),
        ];
    }
}
