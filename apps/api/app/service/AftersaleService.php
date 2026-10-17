<?php

declare(strict_types=1);

namespace app\service;

use app\support\ApiException;
use think\facade\Db;

class AftersaleService
{
    public function listForMember(int $memberId): array
    {
        $rows = Db::name('aftersales')->where('member_id', $memberId)->order('id', 'desc')->select()->toArray();

        return ['items' => array_map([$this, 'present'], $rows)];
    }

    public function listAdmin(?string $status = null): array
    {
        $q = Db::name('aftersales')->order('id', 'desc');
        if ($status) {
            $q->where('status', $status);
        }
        $rows = $q->limit(200)->select()->toArray();

        return ['items' => array_map([$this, 'present'], $rows)];
    }

    public function detail(int $id, ?int $memberId = null): array
    {
        $row = Db::name('aftersales')->where('id', $id)->find();
        if (!$row) {
            throw new ApiException('NOT_FOUND', '售後單不存在', 404, 40460);
        }
        if ($memberId !== null && (int) $row['member_id'] !== $memberId) {
            throw new ApiException('NOT_FOUND', '售後單不存在', 404, 40460);
        }

        return $this->present($row, true);
    }

    public function apply(int $memberId, array $body): array
    {
        $orderId = (int) ($body['order_id'] ?? 0);
        $itemId = (int) ($body['order_item_id'] ?? 0);
        $order = Db::name('orders')->where('id', $orderId)->where('member_id', $memberId)->find();
        if (!$order) {
            throw new ApiException('NOT_FOUND', '訂單不存在', 404, 40440);
        }
        if (!in_array($order['status'], ['pending_shipment', 'pending_receipt'], true)) {
            throw new ApiException('INVALID_STATUS', '僅未完成的已支付訂單可申請售後', 422, 42260);
        }
        $item = Db::name('order_items')->where(['id' => $itemId, 'order_id' => $orderId])->find();
        if (!$item) {
            throw new ApiException('NOT_FOUND', '訂單商品不存在', 404, 40461);
        }
        $remainQty = (int) $item['qty'] - (int) ($item['refunded_qty'] ?? 0);
        $qty = max(1, min($remainQty, (int) ($body['qty'] ?? $remainQty)));
        if ($remainQty <= 0) {
            throw new ApiException('INVALID', '該商品已全部退款', 422, 42261);
        }
        $type = (string) ($body['type'] ?? '');
        if ($order['status'] === 'pending_shipment') {
            $type = 'refund_only';
        } elseif (!in_array($type, ['refund_only', 'return_refund'], true)) {
            throw new ApiException('INVALID', '售後類型不正確', 422, 42201);
        }
        $refundTo = (string) ($body['refund_to'] ?? 'balance');
        if (!in_array($refundTo, ['original', 'balance'], true)) {
            throw new ApiException('INVALID', '退款去向不正確', 422, 42201);
        }
        $maxAmount = round(((float) $item['line_amount'] / max(1, (int) $item['qty'])) * $qty, 2);
        $amount = isset($body['amount']) ? round((float) $body['amount'], 2) : $maxAmount;
        if ($amount <= 0 || $amount > $maxAmount + 0.001) {
            throw new ApiException('INVALID', '退款金額不可超過該商品實付', 422, 42262);
        }
        $reason = trim((string) ($body['reason'] ?? ''));
        if ($reason === '') {
            throw new ApiException('INVALID', '請填寫原因', 422, 42201);
        }
        $now = date('Y-m-d H:i:s');
        $id = 0;
        Db::transaction(function () use ($memberId, $order, $item, $type, $reason, $amount, $qty, $refundTo, $body, $now, &$id) {
            Db::name('orders')->where('id', $order['id'])->update([
                'aftersale_touched' => 1,
                'updated_at' => $now,
            ]);
            $id = (int) Db::name('aftersales')->insertGetId([
                'aftersale_no' => 'TMP',
                'order_id' => $order['id'],
                'order_item_id' => $item['id'],
                'member_id' => $memberId,
                'type' => $type,
                'reason' => $reason,
                'amount' => $amount,
                'qty' => $qty,
                'description' => trim((string) ($body['description'] ?? '')),
                'evidence_json' => json_encode($body['evidence'] ?? [], JSON_UNESCAPED_UNICODE),
                'refund_to' => $refundTo,
                'status' => 'pending_review',
                'admin_note' => '',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $no = 'AS' . date('Ymd') . str_pad((string) $id, 6, '0', STR_PAD_LEFT);
            Db::name('aftersales')->where('id', $id)->update(['aftersale_no' => $no]);
        });

        return $this->detail($id, $memberId);
    }

    public function cancel(int $memberId, int $id): array
    {
        $row = Db::name('aftersales')->where(['id' => $id, 'member_id' => $memberId])->find();
        if (!$row) {
            throw new ApiException('NOT_FOUND', '售後單不存在', 404, 40460);
        }
        if ($row['status'] !== 'pending_review') {
            throw new ApiException('INVALID_STATUS', '僅待審核可撤銷', 422, 42263);
        }
        Db::name('aftersales')->where('id', $id)->update([
            'status' => 'cancelled',
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return $this->detail($id, $memberId);
    }

    public function review(int $id, array $body): array
    {
        $row = Db::name('aftersales')->where('id', $id)->find();
        if (!$row) {
            throw new ApiException('NOT_FOUND', '售後單不存在', 404, 40460);
        }
        if ($row['status'] !== 'pending_review') {
            throw new ApiException('INVALID_STATUS', '售後單狀態不可審核', 422, 42264);
        }
        $action = (string) ($body['action'] ?? '');
        $now = date('Y-m-d H:i:s');
        $note = trim((string) ($body['admin_note'] ?? ''));
        if ($action === 'reject') {
            Db::name('aftersales')->where('id', $id)->update([
                'status' => 'rejected',
                'admin_note' => $note ?: '已駁回',
                'updated_at' => $now,
            ]);
            $row['status'] = 'rejected';
            (new MessageService())->notifyAftersaleEvent($row, 'rejected');

            return $this->detail($id);
        }
        if ($action !== 'approve') {
            throw new ApiException('INVALID', 'action 須為 approve 或 reject', 422, 42201);
        }

        // 退貨退款：先待退貨；僅退款：直接待退款並執行
        if ($row['type'] === 'return_refund') {
            Db::name('aftersales')->where('id', $id)->update([
                'status' => 'awaiting_return',
                'admin_note' => $note,
                'updated_at' => $now,
            ]);
            $row['status'] = 'awaiting_return';
            (new MessageService())->notifyAftersaleEvent($row, 'approved');

            return $this->detail($id);
        }

        $detail = $this->executeRefund($id, $note, false);
        (new MessageService())->notifyAftersaleEvent(
            array_merge($row, ['status' => 'completed']),
            'approved',
        );
        (new MessageService())->notifyAftersaleEvent(
            array_merge($row, ['status' => 'completed']),
            'completed',
        );

        return $detail;
    }

    /** 買家已退回 / 後台確認收貨後退款 */
    public function markReturned(int $id): array
    {
        $row = Db::name('aftersales')->where('id', $id)->find();
        if (!$row || $row['status'] !== 'awaiting_return') {
            throw new ApiException('INVALID_STATUS', '當前不是待退貨狀態', 422, 42265);
        }
        Db::name('aftersales')->where('id', $id)->update([
            'status' => 'pending_refund',
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return $this->executeRefund($id, '', false);
    }

    public function executeRefund(int $id, string $adminNote = '', bool $forceBalance = false): array
    {
        $row = Db::name('aftersales')->where('id', $id)->find();
        if (!$row) {
            throw new ApiException('NOT_FOUND', '售後單不存在', 404, 40460);
        }
        if (!in_array($row['status'], ['pending_review', 'pending_refund', 'awaiting_return'], true) && $row['status'] !== 'completed') {
            // allow from pending_review for refund_only after approve path
        }
        if ($row['status'] === 'completed') {
            return $this->detail($id);
        }

        $now = date('Y-m-d H:i:s');
        Db::transaction(function () use ($row, $adminNote, $forceBalance, $now) {
            $order = Db::name('orders')->where('id', $row['order_id'])->lock(true)->find();
            $item = Db::name('order_items')->where('id', $row['order_item_id'])->find();
            if (!$order || !$item) {
                throw new ApiException('NOT_FOUND', '訂單或明細不存在', 404, 40440);
            }

            Db::name('skus')->where('id', $item['sku_id'])->inc('stock', (int) $row['qty'])->update(['updated_at' => $now]);
            Db::name('order_items')->where('id', $item['id'])->inc('refunded_qty', (int) $row['qty'])->update([]);

            $refundTo = $forceBalance ? 'balance' : $row['refund_to'];
            $amount = (float) $row['amount'];
            $assets = new AssetService();
            if ($refundTo === 'balance') {
                $assets->changeBalance(
                    (int) $row['member_id'],
                    $amount,
                    'aftersale_refund',
                    'aftersale',
                    (int) $row['id'],
                    '售後退餘額',
                );
            }
            // original：模擬原路成功（不寫渠道流水，僅標記）

            $items = Db::name('order_items')->where('order_id', $order['id'])->select()->toArray();
            $allRefunded = true;
            foreach ($items as $it) {
                if ((int) $it['refunded_qty'] < (int) $it['qty']) {
                    $allRefunded = false;
                    break;
                }
            }

            $note = $adminNote;
            if ($forceBalance && $row['refund_to'] === 'original') {
                $note = trim($note . ' 原路失敗已改退餘額');
            }

            Db::name('aftersales')->where('id', $row['id'])->update([
                'status' => 'completed',
                'refund_to' => $refundTo,
                'admin_note' => $note,
                'completed_at' => $now,
                'updated_at' => $now,
            ]);

            if ($allRefunded) {
                // 整單退完：退回下單凍結的積分/餘額差額不在此重算（支付時已扣）；券若仍 frozen 改 unused 無意義已 used
                // 退回下單時扣的積分與餘額中尚未被本售後覆蓋的部分 — 簡化：整單退完將訂單標為已退款，並退回 points_used / balance_amount（若售後金額未覆蓋）
                if ((int) $order['points_used'] > 0) {
                    $assets->changePoints(
                        (int) $order['member_id'],
                        (int) $order['points_used'],
                        'aftersale_restore',
                        'aftersale',
                        (int) $row['id'],
                        '整單退回下單積分',
                    );
                }
                // balance_amount already spent; aftersale refunds via refund_to. Don't double-add full balance_amount.
                if ($order['coupon_grant_id']) {
                    Db::name('coupon_grants')->where('id', $order['coupon_grant_id'])->whereIn('status', ['used', 'frozen'])->update([
                        'status' => 'unused', 'order_id' => null, 'updated_at' => $now,
                    ]);
                }
                Db::name('orders')->where('id', $order['id'])->update([
                    'status' => 'refunded',
                    'updated_at' => $now,
                ]);
                (new MessageService())->notifyOrderEvent($order, 'refunded');
            }
        });

        $detail = $this->detail($id);
        if (($detail['status'] ?? '') === 'completed') {
            (new MessageService())->notifyAftersaleEvent($detail, 'completed');
        }

        return $detail;
    }

    private function present(array $row, bool $withItem = false): array
    {
        $data = [
            'id' => (int) $row['id'],
            'aftersale_no' => $row['aftersale_no'],
            'order_id' => (int) $row['order_id'],
            'order_item_id' => (int) $row['order_item_id'],
            'member_id' => (int) $row['member_id'],
            'type' => $row['type'],
            'reason' => $row['reason'],
            'amount' => (float) $row['amount'],
            'qty' => (int) $row['qty'],
            'description' => $row['description'],
            'refund_to' => $row['refund_to'],
            'status' => $row['status'],
            'admin_note' => $row['admin_note'],
            'created_at' => $row['created_at'],
            'completed_at' => $row['completed_at'],
        ];
        if ($withItem) {
            $item = Db::name('order_items')->where('id', $row['order_item_id'])->find();
            $order = Db::name('orders')->where('id', $row['order_id'])->find();
            $data['order_no'] = $order['order_no'] ?? '';
            $data['order_status'] = $order['status'] ?? '';
            $data['item'] = $item ? [
                'name' => ['zh-HK' => $item['name_zh'], 'en' => $item['name_en']],
                'spec' => ['zh-HK' => $item['spec_zh'], 'en' => $item['spec_en']],
                'image' => $item['image_url'],
                'unit_price' => (float) $item['unit_price'],
            ] : null;
        }

        return $data;
    }
}
