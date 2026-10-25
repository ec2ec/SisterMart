<?php

declare(strict_types=1);

namespace app\service;

use app\support\ApiException;
use think\facade\Db;

class AftersaleService
{
    /** 進行中（不可對同一商品再申請） */
    public const OPEN_STATUSES = ['pending_review', 'awaiting_return', 'pending_refund'];

    public function listForMember(int $memberId): array
    {
        $rows = Db::name('aftersales')->where('member_id', $memberId)->order('id', 'desc')->select()->toArray();

        return ['items' => array_map([$this, 'present'], $rows)];
    }

    /** @return list<array<string, mixed>> */
    public function listForOrder(int $orderId): array
    {
        $rows = Db::name('aftersales')->where('order_id', $orderId)->order('id', 'desc')->select()->toArray();

        return array_map(fn (array $row) => $this->present($row, true), $rows);
    }

    public function hasOpenForItem(int $orderItemId): bool
    {
        return (int) Db::name('aftersales')
            ->where('order_item_id', $orderItemId)
            ->whereIn('status', self::OPEN_STATUSES)
            ->count() > 0;
    }

    /**
     * 訂單是否還可發起售後（未過期、有可退數量、且該商品無進行中售後；駁回／撤銷後可再申請）。
     *
     * @param array<string, mixed> $order
     */
    public function canApplyForOrder(array $order): bool
    {
        $elig = $this->eligibilityForOrder($order);
        if (!$elig['aftersale_eligible'] || $elig['aftersale_expired']) {
            return false;
        }
        $items = Db::name('order_items')->where('order_id', (int) $order['id'])->select()->toArray();
        foreach ($items as $item) {
            $remain = (int) $item['qty'] - (int) ($item['refunded_qty'] ?? 0);
            if ($remain > 0 && !$this->hasOpenForItem((int) $item['id'])) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array{status?: ?string, has_evidence?: ?string|int|bool} $filters
     */
    public function listAdmin(array $filters = []): array
    {
        $q = Db::name('aftersales')->order('id', 'desc');
        $status = isset($filters['status']) ? trim((string) $filters['status']) : '';
        if ($status !== '') {
            $q->where('status', $status);
        }
        $rows = $q->limit(200)->select()->toArray();
        $items = array_map([$this, 'present'], $rows);
        $hasEvidence = $filters['has_evidence'] ?? null;
        if ($hasEvidence !== null && $hasEvidence !== '') {
            $want = filter_var($hasEvidence, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($want === true) {
                $items = array_values(array_filter($items, fn ($i) => ($i['evidence_count'] ?? 0) > 0));
            } elseif ($want === false) {
                $items = array_values(array_filter($items, fn ($i) => ($i['evidence_count'] ?? 0) === 0));
            }
        }

        return ['items' => $items];
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

    /**
     * @param array<string, mixed> $order
     * @return array{aftersale_eligible: bool, aftersale_expired: bool, aftersale_window_days: int, aftersale_deadline_at: ?string, cs_whatsapp: string}
     */
    public function eligibilityForOrder(array $order): array
    {
        $window = TradeSettings::aftersaleWindowDays();
        $whatsapp = TradeSettings::csWhatsapp();
        $status = (string) ($order['status'] ?? '');
        $completedAt = $order['completed_at'] ?? null;
        $deadline = null;
        $expired = false;
        $eligible = in_array($status, ['pending_shipment', 'pending_receipt', 'completed'], true);

        if ($status === 'completed' && $completedAt) {
            $deadlineTs = strtotime((string) $completedAt) + $window * 86400;
            $deadline = date('Y-m-d H:i:s', $deadlineTs);
            if (time() > $deadlineTs) {
                $expired = true;
                $eligible = false;
            }
        }

        return [
            'aftersale_eligible' => $eligible,
            'aftersale_expired' => $expired,
            'aftersale_window_days' => $window,
            'aftersale_deadline_at' => $deadline,
            'cs_whatsapp' => $whatsapp,
        ];
    }

    public function apply(int $memberId, array $body): array
    {
        $orderId = (int) ($body['order_id'] ?? 0);
        $itemId = (int) ($body['order_item_id'] ?? 0);
        $order = Db::name('orders')->where('id', $orderId)->where('member_id', $memberId)->find();
        if (!$order) {
            throw new ApiException('NOT_FOUND', '訂單不存在', 404, 40440);
        }
        // M16：已完成訂單僅允許退貨退款；M17：完成後超窗不可申請
        if (!in_array($order['status'], ['pending_shipment', 'pending_receipt', 'completed'], true)) {
            throw new ApiException('INVALID_STATUS', '僅已支付且未全退的訂單可申請售後', 422, 42260);
        }
        $elig = $this->eligibilityForOrder($order);
        if ($elig['aftersale_expired']) {
            throw new ApiException('AFTERSALE_EXPIRED', '抱歉，已超過售後有效期，暫時無法申請售後', 422, 42286);
        }
        $item = Db::name('order_items')->where(['id' => $itemId, 'order_id' => $orderId])->find();
        if (!$item) {
            throw new ApiException('NOT_FOUND', '訂單商品不存在', 404, 40461);
        }
        if ($this->hasOpenForItem($itemId)) {
            throw new ApiException('INVALID', '該商品已有進行中的售後單，請待處理完成或撤銷後再申請', 422, 42291);
        }
        $remainQty = (int) $item['qty'] - (int) ($item['refunded_qty'] ?? 0);
        $qty = max(1, min($remainQty, (int) ($body['qty'] ?? $remainQty)));
        if ($remainQty <= 0) {
            throw new ApiException('INVALID', '該商品已全部退款', 422, 42261);
        }
        $type = (string) ($body['type'] ?? '');
        if ($order['status'] === 'pending_shipment') {
            $type = 'refund_only';
        } elseif ($order['status'] === 'completed') {
            $type = 'return_refund';
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
        $description = trim((string) ($body['description'] ?? ($body['reason'] ?? '')));
        if ($description === '') {
            throw new ApiException('INVALID', '請詳細描述你遇到的問題', 422, 42201);
        }
        $reason = mb_substr($description, 0, 128);
        $evidence = $this->normalizeEvidence($memberId, $body['evidence'] ?? []);
        $now = date('Y-m-d H:i:s');
        $id = 0;
        Db::transaction(function () use ($memberId, $order, $item, $type, $reason, $description, $amount, $qty, $refundTo, $evidence, $now, &$id) {
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
                'description' => $description,
                'evidence_json' => json_encode($evidence, JSON_UNESCAPED_UNICODE),
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

    /** @param mixed $raw @return list<string> */
    private function normalizeEvidence(int $memberId, mixed $raw): array
    {
        if (!is_array($raw)) {
            return [];
        }
        $urls = [];
        foreach ($raw as $item) {
            $url = trim((string) $item);
            if ($url === '') {
                continue;
            }
            $prefix = '/storage/aftersale/' . $memberId . '/';
            if (!str_starts_with($url, $prefix)) {
                throw new ApiException('INVALID', '證據圖片無效', 422, 42287);
            }
            $urls[] = $url;
            if (count($urls) > 3) {
                throw new ApiException('INVALID', '最多上傳 3 張證據圖片', 422, 42288);
            }
        }

        return $urls;
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
            if ($note === '') {
                throw new ApiException('INVALID', '駁回請填寫審核備註', 422, 42201);
            }
            Db::name('aftersales')->where('id', $id)->update([
                'status' => 'rejected',
                'admin_note' => $note,
                'updated_at' => $now,
            ]);
            $row['status'] = 'rejected';
            (new MessageService())->notifyAftersaleEvent($row, 'rejected');

            return $this->detail($id);
        }
        if ($action !== 'approve') {
            throw new ApiException('INVALID', 'action 須為 approve 或 reject', 422, 42201);
        }

        // M22：退貨退款須有證據才可通過（避免無圖盲批）
        if ($row['type'] === 'return_refund' && $this->evidenceCount($row) === 0) {
            throw new ApiException('EVIDENCE_REQUIRED', '退貨退款需會員上傳證據後再審核通過', 422, 42287);
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

            $refundQty = (int) $row['qty'];
            Db::name('skus')->where('id', $item['sku_id'])->inc('stock', $refundQty)->update(['updated_at' => $now]);
            Db::name('order_items')->where('id', $item['id'])->inc('refunded_qty', $refundQty)->update([]);
            $pid = (int) $item['product_id'];
            if ($pid > 0 && $refundQty > 0) {
                $cur = (int) Db::name('products')->where('id', $pid)->value('sold_count');
                Db::name('products')->where('id', $pid)->update([
                    'sold_count' => max(0, $cur - $refundQty),
                    'updated_at' => $now,
                ]);
            }

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

            // M16：售後退款完成後按明細沖回佣金（pending void / settled clawback）
            (new DistributionService())->clawbackForAftersale((int) $row['id']);

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
                (new DistributionService())->voidForOrder((int) $order['id']);
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
        $evidence = [];
        if (!empty($row['evidence_json'])) {
            $decoded = json_decode((string) $row['evidence_json'], true);
            if (is_array($decoded)) {
                $evidence = array_values(array_filter(array_map('strval', $decoded)));
            }
        }
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
            'evidence' => $evidence,
            'evidence_count' => count($evidence),
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

    /** @param array<string, mixed> $row */
    private function evidenceCount(array $row): int
    {
        if (empty($row['evidence_json'])) {
            return 0;
        }
        $decoded = json_decode((string) $row['evidence_json'], true);

        return is_array($decoded) ? count(array_filter(array_map('strval', $decoded))) : 0;
    }
}
