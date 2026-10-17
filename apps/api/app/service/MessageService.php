<?php

declare(strict_types=1);

namespace app\service;

use app\support\ApiException;
use think\facade\Db;

/**
 * M15 站內消息（真實表；訂單生命週期 + 系統廣播）。
 */
class MessageService
{
    public function listForMember(int $memberId, ?string $type = null): array
    {
        if ($type === 'order' || $type === 'system') {
            return [
                'mock' => false,
                'items' => $this->fetchItems($memberId, $type),
            ];
        }

        $order = $this->fetchItems($memberId, 'order');
        $system = $this->fetchItems($memberId, 'system');

        return [
            'mock' => false,
            'order' => $order,
            'system' => $system,
            'unread' => $this->countUnread($memberId),
        ];
    }

    public function unreadCount(int $memberId): array
    {
        return ['mock' => false, 'unread' => $this->countUnread($memberId)];
    }

    /**
     * @param array{id?: string|int, ids?: array, all?: bool|int|string, type?: string} $body
     */
    public function markRead(int $memberId, array $body): array
    {
        $now = date('Y-m-d H:i:s');
        $all = !empty($body['all']) && $body['all'] !== '0' && $body['all'] !== false;
        $type = isset($body['type']) ? trim((string) $body['type']) : '';
        $ids = [];
        if (!empty($body['ids']) && is_array($body['ids'])) {
            foreach ($body['ids'] as $id) {
                $n = (int) $id;
                if ($n > 0) {
                    $ids[] = $n;
                }
            }
        } elseif (isset($body['id']) && (string) $body['id'] !== '') {
            $n = (int) $body['id'];
            if ($n > 0) {
                $ids[] = $n;
            }
        }

        if (!$all && !$ids) {
            throw new ApiException('INVALID', '缺少消息 ID', 422, 42285);
        }

        $q = Db::name('member_messages')->where('member_id', $memberId)->whereNull('read_at');
        if ($all) {
            if ($type === 'order' || $type === 'system') {
                $q->where('type', $type);
            }
            $q->update(['read_at' => $now]);
        } else {
            $q->whereIn('id', $ids)->update(['read_at' => $now]);
        }

        return $this->unreadCount($memberId);
    }

    /**
     * 訂單生命週期寫入（冪等：同 member + event_key 不重複）。
     */
    public function notifyOrderEvent(array $order, string $event, ?string $extraZh = null, ?string $extraEn = null): void
    {
        $memberId = (int) ($order['member_id'] ?? 0);
        $orderId = (int) ($order['id'] ?? 0);
        if ($memberId <= 0 || $orderId <= 0) {
            return;
        }
        $orderNo = (string) ($order['order_no'] ?? '');
        [$titleZh, $titleEn, $bodyZh, $bodyEn] = $this->orderCopy($event, $orderNo, $extraZh, $extraEn);
        $this->insertOnce([
            'member_id' => $memberId,
            'type' => 'order',
            'title_zh' => $titleZh,
            'title_en' => $titleEn,
            'body_zh' => $bodyZh,
            'body_en' => $bodyEn,
            'ref_type' => 'order',
            'ref_id' => $orderId,
            'event_key' => 'order:' . $orderId . ':' . $event,
            'batch_id' => null,
        ]);
    }

    /**
     * 售後審核結果寫入訂單消息 Tab。
     */
    public function notifyAftersaleEvent(array $aftersale, string $event): void
    {
        $memberId = (int) ($aftersale['member_id'] ?? 0);
        $asId = (int) ($aftersale['id'] ?? 0);
        if ($memberId <= 0 || $asId <= 0) {
            return;
        }
        $no = (string) ($aftersale['aftersale_no'] ?? ('AS' . $asId));
        $map = [
            'approved' => ['售後已通過', 'After-sale approved', '售後單 ' . $no . ' 已通過審核。', 'After-sale ' . $no . ' approved.'],
            'rejected' => ['售後已駁回', 'After-sale rejected', '售後單 ' . $no . ' 未通過審核。', 'After-sale ' . $no . ' rejected.'],
            'completed' => ['售後退款完成', 'Refund completed', '售後單 ' . $no . ' 已完成退款。', 'After-sale ' . $no . ' refunded.'],
        ];
        if (!isset($map[$event])) {
            return;
        }
        [$titleZh, $titleEn, $bodyZh, $bodyEn] = $map[$event];
        $this->insertOnce([
            'member_id' => $memberId,
            'type' => 'order',
            'title_zh' => $titleZh,
            'title_en' => $titleEn,
            'body_zh' => $bodyZh,
            'body_en' => $bodyEn,
            'ref_type' => 'aftersale',
            'ref_id' => $asId,
            'event_key' => 'aftersale:' . $asId . ':' . $event,
            'batch_id' => null,
        ]);
    }

    /**
     * 後台發系統消息：可指定會員或廣播全體。
     *
     * @return array{mock: bool, batch_id: string, sent: int}
     */
    public function adminCreate(array $body): array
    {
        $titleZh = trim((string) ($body['title_zh'] ?? ''));
        $titleEn = trim((string) ($body['title_en'] ?? $titleZh));
        $bodyZh = trim((string) ($body['body_zh'] ?? ''));
        $bodyEn = trim((string) ($body['body_en'] ?? $bodyZh));
        if ($titleZh === '' || $bodyZh === '') {
            throw new ApiException('INVALID', '請填寫標題與內容', 422, 42201);
        }
        $memberId = isset($body['member_id']) ? (int) $body['member_id'] : 0;
        $batchId = bin2hex(random_bytes(8));
        $now = date('Y-m-d H:i:s');
        $targets = [];
        if ($memberId > 0) {
            $exists = Db::name('members')->where('id', $memberId)->value('id');
            if (!$exists) {
                throw new ApiException('NOT_FOUND', '會員不存在', 404, 40410);
            }
            $targets = [$memberId];
        } else {
            $targets = Db::name('members')->column('id');
        }
        $sent = 0;
        foreach ($targets as $mid) {
            $ok = $this->insertOnce([
                'member_id' => (int) $mid,
                'type' => 'system',
                'title_zh' => $titleZh,
                'title_en' => $titleEn !== '' ? $titleEn : $titleZh,
                'body_zh' => $bodyZh,
                'body_en' => $bodyEn !== '' ? $bodyEn : $bodyZh,
                'ref_type' => 'broadcast',
                'ref_id' => null,
                'event_key' => 'sys:' . $batchId . ':' . (int) $mid,
                'batch_id' => $batchId,
                'created_at' => $now,
            ]);
            if ($ok) {
                $sent++;
            }
        }

        return ['mock' => false, 'batch_id' => $batchId, 'sent' => $sent];
    }

    /** 後台：系統消息批次列表（每批一條摘要） */
    public function adminList(int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));
        $rows = Db::query(
            'SELECT batch_id,
                    ANY_VALUE(title_zh) AS title_zh,
                    ANY_VALUE(title_en) AS title_en,
                    ANY_VALUE(body_zh) AS body_zh,
                    ANY_VALUE(body_en) AS body_en,
                    MIN(created_at) AS created_at,
                    COUNT(*) AS recipient_count
             FROM member_messages
             WHERE type = ? AND batch_id IS NOT NULL
             GROUP BY batch_id
             ORDER BY created_at DESC
             LIMIT ' . (int) $limit,
            ['system']
        );

        return [
            'mock' => false,
            'items' => array_map(static function ($r) {
                return [
                    'batch_id' => $r['batch_id'],
                    'title' => ['zh-HK' => $r['title_zh'], 'en' => $r['title_en']],
                    'body' => ['zh-HK' => $r['body_zh'], 'en' => $r['body_en']],
                    'recipient_count' => (int) $r['recipient_count'],
                    'created_at' => $r['created_at'],
                ];
            }, $rows ?: []),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function fetchItems(int $memberId, string $type): array
    {
        $rows = Db::name('member_messages')
            ->where(['member_id' => $memberId, 'type' => $type])
            ->order('id', 'desc')
            ->limit(100)
            ->select()
            ->toArray();

        return array_map([$this, 'present'], $rows);
    }

    private function countUnread(int $memberId): int
    {
        return (int) Db::name('member_messages')
            ->where('member_id', $memberId)
            ->whereNull('read_at')
            ->count();
    }

    /** @param array<string, mixed> $row */
    private function present(array $row): array
    {
        $orderId = null;
        $orderNo = null;
        if (($row['ref_type'] ?? '') === 'order' && !empty($row['ref_id'])) {
            $orderId = (int) $row['ref_id'];
            $orderNo = Db::name('orders')->where('id', $orderId)->value('order_no');
        } elseif (($row['ref_type'] ?? '') === 'aftersale' && !empty($row['ref_id'])) {
            $as = Db::name('aftersales')->where('id', (int) $row['ref_id'])->find();
            if ($as) {
                $orderId = (int) $as['order_id'];
                $orderNo = Db::name('orders')->where('id', $orderId)->value('order_no');
            }
        }

        return [
            'id' => (string) $row['id'],
            'type' => $row['type'],
            'title' => ['zh-HK' => $row['title_zh'], 'en' => $row['title_en']],
            'body' => ['zh-HK' => $row['body_zh'], 'en' => $row['body_en']],
            'order_id' => $orderId,
            'order_no' => $orderNo,
            'read' => !empty($row['read_at']),
            'created_at' => $row['created_at'],
        ];
    }

    /**
     * @param array{member_id:int,type:string,title_zh:string,title_en:string,body_zh:string,body_en:string,ref_type:?string,ref_id:?int,event_key:?string,batch_id:?string,created_at?:string} $data
     */
    private function insertOnce(array $data): bool
    {
        $now = $data['created_at'] ?? date('Y-m-d H:i:s');
        try {
            Db::name('member_messages')->insert([
                'member_id' => (int) $data['member_id'],
                'type' => $data['type'],
                'title_zh' => $data['title_zh'],
                'title_en' => $data['title_en'],
                'body_zh' => $data['body_zh'],
                'body_en' => $data['body_en'],
                'ref_type' => $data['ref_type'],
                'ref_id' => $data['ref_id'],
                'event_key' => $data['event_key'],
                'batch_id' => $data['batch_id'],
                'read_at' => null,
                'created_at' => $now,
            ]);

            return true;
        } catch (\Throwable $e) {
            // 唯一鍵衝突 → 冪等忽略
            $msg = $e->getMessage();
            if (str_contains($msg, 'Duplicate') || str_contains($msg, '1062')) {
                return false;
            }
            throw $e;
        }
    }

    /** @return array{0:string,1:string,2:string,3:string} */
    private function orderCopy(string $event, string $orderNo, ?string $extraZh, ?string $extraEn): array
    {
        $tailZh = $orderNo !== '' ? ('訂單 ' . $orderNo) : '訂單';
        $tailEn = $orderNo !== '' ? ('Order ' . $orderNo) : 'Order';
        if ($extraZh) {
            $tailZh .= ' · ' . $extraZh;
        }
        if ($extraEn) {
            $tailEn .= ' · ' . $extraEn;
        }
        $map = [
            'created' => ['下單成功', 'Order placed', $tailZh . ' 已建立，請盡快付款。', $tailEn . ' created. Please pay soon.'],
            'paid' => ['支付成功，待發貨', 'Paid — awaiting shipment', $tailZh . ' 已支付。', $tailEn . ' paid.'],
            'shipped' => ['已發貨，待收貨', 'Shipped — awaiting receipt', $tailZh . ' 已發貨。', $tailEn . ' shipped.'],
            'completed' => ['訂單已完成', 'Order completed', $tailZh . ' 已確認收貨。', $tailEn . ' completed.'],
            'cancelled' => ['訂單已取消', 'Order cancelled', $tailZh . ' 已關閉。', $tailEn . ' cancelled.'],
            'refunded' => ['訂單已退款', 'Order refunded', $tailZh . ' 已退款。', $tailEn . ' refunded.'],
        ];
        if ($event === 'created_zero') {
            return ['下單成功，待發貨', 'Order placed — awaiting shipment', $tailZh . ' 無需線上付款。', $tailEn . ' — no online payment needed.'];
        }

        return $map[$event] ?? ['訂單更新', 'Order update', $tailZh, $tailEn];
    }
}
