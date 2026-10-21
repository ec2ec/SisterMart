<?php

declare(strict_types=1);

namespace app\service;

use think\facade\Db;

/**
 * 後台概覽：待辦（P0）+ 今日業績與昨日對比（P1）+ 店況（P2/P3）。
 */
class DashboardService
{
    private const LOW_STOCK_THRESHOLD = 5;

    /** @return array<string, mixed> */
    public function summary(): array
    {
        $todayStart = date('Y-m-d 00:00:00');
        $todayEnd = date('Y-m-d 23:59:59');
        $yesterdayStart = date('Y-m-d 00:00:00', strtotime('-1 day'));
        $yesterdayEnd = date('Y-m-d 23:59:59', strtotime('-1 day'));

        $todayOrders = $this->orderCountBetween($todayStart, $todayEnd);
        $todayPaid = $this->paidAmountBetween($todayStart, $todayEnd);
        $todayMembers = $this->memberCountBetween($todayStart, $todayEnd);

        $yOrders = $this->orderCountBetween($yesterdayStart, $yesterdayEnd);
        $yPaid = $this->paidAmountBetween($yesterdayStart, $yesterdayEnd);
        $yMembers = $this->memberCountBetween($yesterdayStart, $yesterdayEnd);

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

        $onSaleProducts = (int) Db::name('products')->where('status', 1)->count();

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
                'order_count' => $todayOrders,
                'paid_amount' => round($todayPaid, 2),
                'new_members' => $todayMembers,
            ],
            'yesterday' => [
                'order_count' => $yOrders,
                'paid_amount' => round($yPaid, 2),
                'new_members' => $yMembers,
            ],
            'compare' => [
                'order_count' => $this->compareInt($todayOrders, $yOrders),
                'paid_amount' => $this->compareFloat($todayPaid, $yPaid),
                'new_members' => $this->compareInt($todayMembers, $yMembers),
            ],
            'todo' => [
                'pending_shipment' => $pendingShipment,
                'pending_aftersale' => $pendingAftersale,
                'pending_withdrawal' => $pendingWithdrawal,
                'pending_review' => $pendingReview,
            ],
            'shop' => [
                'on_sale_products' => $onSaleProducts,
                'low_stock_skus' => $lowStockSkus,
                'low_stock_threshold' => self::LOW_STOCK_THRESHOLD,
                'maintenance_enabled' => (bool) ($maintenance['enabled'] ?? false),
            ],
            /** @deprecated 兼容舊前端；新 UI 讀 shop */
            'alerts' => [
                'low_stock_skus' => $lowStockSkus,
                'low_stock_threshold' => self::LOW_STOCK_THRESHOLD,
                'maintenance_enabled' => (bool) ($maintenance['enabled'] ?? false),
            ],
            'generated_at' => date('Y-m-d H:i:s'),
        ];
    }

    private function orderCountBetween(string $start, string $end): int
    {
        return (int) Db::name('orders')
            ->whereBetween('created_at', [$start, $end])
            ->count();
    }

    private function paidAmountBetween(string $start, string $end): float
    {
        return (float) Db::name('orders')
            ->whereBetween('paid_at', [$start, $end])
            ->whereNotNull('paid_at')
            ->sum('pay_amount');
    }

    private function memberCountBetween(string $start, string $end): int
    {
        return (int) Db::name('members')
            ->whereBetween('created_at', [$start, $end])
            ->count();
    }

    /** @return array{delta: int, pct: ?float} */
    private function compareInt(int $today, int $yesterday): array
    {
        $delta = $today - $yesterday;
        $pct = $yesterday === 0 ? null : round(($delta / $yesterday) * 100, 1);

        return ['delta' => $delta, 'pct' => $pct];
    }

    /** @return array{delta: float, pct: ?float} */
    private function compareFloat(float $today, float $yesterday): array
    {
        $delta = round($today - $yesterday, 2);
        $pct = abs($yesterday) < 0.00001 ? null : round(($delta / $yesterday) * 100, 1);

        return ['delta' => $delta, 'pct' => $pct];
    }
}
