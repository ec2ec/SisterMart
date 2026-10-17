<?php

declare(strict_types=1);

namespace app\service;

use app\support\ApiException;
use think\facade\Db;

class FulfillmentService
{
    public function ship(int $orderId, array $body): array
    {
        $order = Db::name('orders')->where('id', $orderId)->find();
        if (!$order) {
            throw new ApiException('NOT_FOUND', '訂單不存在', 404, 40440);
        }
        if ($order['status'] !== 'pending_shipment') {
            throw new ApiException('INVALID_STATUS', '僅待發貨訂單可發貨', 422, 42250);
        }
        $company = trim((string) ($body['company'] ?? ''));
        $tracking = trim((string) ($body['tracking_no'] ?? ''));
        if ($company === '' || $tracking === '') {
            throw new ApiException('INVALID', '請填寫物流公司與運單號', 422, 42201);
        }
        $now = date('Y-m-d H:i:s');
        Db::transaction(function () use ($order, $company, $tracking, $now, $body) {
            $sid = (int) Db::name('shipments')->insertGetId([
                'order_id' => $order['id'],
                'company' => $company,
                'tracking_no' => $tracking,
                'shipped_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            Db::name('shipment_nodes')->insert([
                'shipment_id' => $sid,
                'occurred_at' => $now,
                'note' => trim((string) ($body['note'] ?? '商家已發貨')),
                'created_at' => $now,
            ]);
            Db::name('orders')->where('id', $order['id'])->update([
                'status' => 'pending_receipt',
                'shipped_at' => $now,
                'updated_at' => $now,
            ]);
        });

        return $this->logistics((int) $order['id']);
    }

    public function addNode(int $orderId, array $body): array
    {
        $shipment = Db::name('shipments')->where('order_id', $orderId)->find();
        if (!$shipment) {
            throw new ApiException('NOT_FOUND', '尚未發貨', 404, 40451);
        }
        $note = trim((string) ($body['note'] ?? ''));
        if ($note === '') {
            throw new ApiException('INVALID', '請填寫節點說明', 422, 42201);
        }
        $occurred = trim((string) ($body['occurred_at'] ?? '')) ?: date('Y-m-d H:i:s');
        $now = date('Y-m-d H:i:s');
        Db::name('shipment_nodes')->insert([
            'shipment_id' => $shipment['id'],
            'occurred_at' => $occurred,
            'note' => $note,
            'created_at' => $now,
        ]);
        Db::name('shipments')->where('id', $shipment['id'])->update(['updated_at' => $now]);

        return $this->logistics($orderId);
    }

    public function logistics(int $orderId, ?int $memberId = null): array
    {
        $order = Db::name('orders')->where('id', $orderId)->find();
        if (!$order) {
            throw new ApiException('NOT_FOUND', '訂單不存在', 404, 40440);
        }
        if ($memberId !== null && (int) $order['member_id'] !== $memberId) {
            throw new ApiException('NOT_FOUND', '訂單不存在', 404, 40440);
        }
        $shipment = Db::name('shipments')->where('order_id', $orderId)->find();
        if (!$shipment) {
            throw new ApiException('NOT_FOUND', '暫無物流資訊', 404, 40451);
        }
        $nodes = Db::name('shipment_nodes')->where('shipment_id', $shipment['id'])
            ->order('occurred_at', 'desc')->order('id', 'desc')->select()->toArray();

        return [
            'order_id' => (int) $order['id'],
            'order_no' => $order['order_no'],
            'order_status' => $order['status'],
            'company' => $shipment['company'],
            'tracking_no' => $shipment['tracking_no'],
            'shipped_at' => $shipment['shipped_at'],
            'nodes' => array_map(fn ($n) => [
                'id' => (int) $n['id'],
                'occurred_at' => $n['occurred_at'],
                'note' => $n['note'],
            ], $nodes),
        ];
    }

    public function confirmReceipt(int $orderId, ?int $memberId = null, string $source = 'manual'): array
    {
        $order = Db::name('orders')->where('id', $orderId)->find();
        if (!$order) {
            throw new ApiException('NOT_FOUND', '訂單不存在', 404, 40440);
        }
        if ($memberId !== null && (int) $order['member_id'] !== $memberId) {
            throw new ApiException('NOT_FOUND', '訂單不存在', 404, 40440);
        }
        // 冪等：已完成直接返回，避免雙擊/任務重跑重複發積分
        if ($order['status'] === 'completed') {
            return (new OrderService())->detail($memberId ?? (int) $order['member_id'], $orderId, $memberId === null);
        }
        if ($order['status'] !== 'pending_receipt') {
            throw new ApiException('INVALID_STATUS', '僅待收貨訂單可確認收貨', 422, 42252);
        }
        $now = date('Y-m-d H:i:s');
        Db::transaction(function () use ($order, $now, $source) {
            $locked = Db::name('orders')->where('id', $order['id'])->where('status', 'pending_receipt')->lock(true)->find();
            if (!$locked) {
                return;
            }
            $points = $this->earnPointsForOrder($locked);
            $assets = new AssetService();
            if ($points > 0) {
                $assets->changePoints(
                    (int) $locked['member_id'],
                    $points,
                    'order_earn',
                    'order',
                    (int) $locked['id'],
                    '訂單完成發放',
                );
            }
            $goodsNet = AssetService::goodsNetPay($locked);
            $assets->addSpentAndRefreshLevel((int) $locked['member_id'], $goodsNet);
            (new DistributionService())->settleForOrder((int) $locked['id']);
            Db::name('orders')->where('id', $locked['id'])->update([
                'status' => 'completed',
                'completed_at' => $now,
                'points_awarded' => $points,
                'cancel_reason' => $source === 'auto' ? 'auto_receive' : null,
                'updated_at' => $now,
            ]);
        });

        return (new OrderService())->detail($memberId ?? (int) $order['member_id'], $orderId, $memberId === null);
    }

    public function autoReceive(): array
    {
        $days = TradeSettings::autoReceiveDays();
        $deadline = date('Y-m-d H:i:s', time() - $days * 86400);
        $rows = Db::name('orders')
            ->where('status', 'pending_receipt')
            ->where('aftersale_touched', 0)
            ->where('paid_at', '<=', $deadline)
            ->whereNotNull('shipped_at')
            ->select()
            ->toArray();
        $done = 0;
        foreach ($rows as $row) {
            try {
                $this->confirmReceipt((int) $row['id'], null, 'auto');
                $done++;
            } catch (ApiException) {
                /* skip race */
            }
        }

        return ['auto_received' => $done, 'days' => $days];
    }

    /** 商品實付（不含運費）按設定發積分；已退款整單不應走到這裡；積分商城訂單不發下單積分 */
    private function earnPointsForOrder(array $order): int
    {
        if ((int) ($order['points_awarded'] ?? 0) > 0) {
            return 0;
        }
        if (!empty($order['points_mall_item_id'])) {
            return 0;
        }
        $per = TradeSettings::orderEarnPerHkd();
        if ($per <= 0) {
            return 0;
        }
        $basis = AssetService::goodsNetPay($order);

        return (int) floor($basis * $per);
    }
}
