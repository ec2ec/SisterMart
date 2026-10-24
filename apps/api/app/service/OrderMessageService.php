<?php

declare(strict_types=1);

namespace app\service;

use app\support\ApiException;
use think\facade\Db;

/**
 * 訂單詳情內買賣雙方留言（與訂單消息 Tab 的站內信並存）。
 */
class OrderMessageService
{
    public const SENDER_SELLER = 'seller';
    public const SENDER_BUYER = 'buyer';

    /**
     * @return list<array{id:int,sender:string,body:string,created_at:string}>
     */
    public function listForOrder(int $orderId, int $memberId = 0, bool $admin = false): array
    {
        $this->assertOrderAccess($orderId, $memberId, $admin);
        $rows = Db::name('order_messages')
            ->where('order_id', $orderId)
            ->order('id', 'asc')
            ->select()
            ->toArray();

        return array_map(fn ($r) => $this->present($r), $rows);
    }

    /**
     * @return array{id:int,sender:string,body:string,created_at:string}
     */
    public function buyerPost(int $memberId, int $orderId, array $body): array
    {
        $order = $this->assertOrderAccess($orderId, $memberId, false);
        $text = $this->normalizeBody($body);
        $now = date('Y-m-d H:i:s');
        $id = (int) Db::name('order_messages')->insertGetId([
            'order_id' => $orderId,
            'sender' => self::SENDER_BUYER,
            'body' => $text,
            'member_id' => $memberId,
            'admin_id' => null,
            'created_at' => $now,
        ]);
        $row = Db::name('order_messages')->where('id', $id)->find();

        return $this->present($row ?: [
            'id' => $id,
            'sender' => self::SENDER_BUYER,
            'body' => $text,
            'created_at' => $now,
        ]);
    }

    /**
     * 賣家留言：寫入對話表，並同步一條訂單消息通知買家。
     *
     * @return array{message: array{id:int,sender:string,body:string,created_at:string}, notify: array<string,mixed>}
     */
    public function sellerPost(int $orderId, array $body, ?int $adminId = null): array
    {
        $order = $this->assertOrderAccess($orderId, 0, true);
        $text = $this->normalizeBody($body);
        $now = date('Y-m-d H:i:s');
        $id = (int) Db::name('order_messages')->insertGetId([
            'order_id' => $orderId,
            'sender' => self::SENDER_SELLER,
            'body' => $text,
            'member_id' => null,
            'admin_id' => $adminId,
            'created_at' => $now,
        ]);
        $row = Db::name('order_messages')->where('id', $id)->find();

        // 兼容原 M31：同步站內「訂單消息」
        $notify = (new MessageService())->adminLeaveOrderNote($orderId, [
            'body_zh' => $text,
            'body_en' => $text,
        ]);

        return [
            'message' => $this->present($row ?: [
                'id' => $id,
                'sender' => self::SENDER_SELLER,
                'body' => $text,
                'created_at' => $now,
            ]),
            'notify' => $notify,
            'order_no' => (string) ($order['order_no'] ?? ''),
        ];
    }

    /**
     * @param array<string,mixed> $body
     */
    private function normalizeBody(array $body): string
    {
        $text = trim((string) ($body['body'] ?? $body['body_zh'] ?? ''));
        if ($text === '') {
            throw new ApiException('INVALID', '請填寫留言', 422, 42201);
        }
        if (mb_strlen($text) > 256) {
            throw new ApiException('INVALID', '留言最多 256 字', 422, 42202);
        }

        return $text;
    }

    /**
     * @return array<string,mixed>
     */
    private function assertOrderAccess(int $orderId, int $memberId, bool $admin): array
    {
        $order = Db::name('orders')->where('id', $orderId)->find();
        if (!$order) {
            throw new ApiException('NOT_FOUND', '訂單不存在', 404, 40401);
        }
        if (!$admin && (int) ($order['member_id'] ?? 0) !== $memberId) {
            throw new ApiException('NOT_FOUND', '訂單不存在', 404, 40401);
        }

        return $order;
    }

    /**
     * @param array<string,mixed> $row
     * @return array{id:int,sender:string,body:string,created_at:string}
     */
    private function present(array $row): array
    {
        $sender = (string) ($row['sender'] ?? '');
        if (!in_array($sender, [self::SENDER_SELLER, self::SENDER_BUYER], true)) {
            $sender = self::SENDER_SELLER;
        }

        return [
            'id' => (int) ($row['id'] ?? 0),
            'sender' => $sender,
            'body' => (string) ($row['body'] ?? ''),
            'created_at' => (string) ($row['created_at'] ?? ''),
        ];
    }
}
