<?php

declare(strict_types=1);

namespace app\service;

use app\support\ApiException;
use think\facade\Db;

/**
 * M18 商品評價：主評一次 + 追評一次。
 */
class ReviewService
{
    public function listForProduct(int $productId, int $limit = 50): array
    {
        $rows = Db::name('product_reviews')
            ->where('product_id', $productId)
            ->order('id', 'desc')
            ->limit($limit)
            ->select()
            ->toArray();
        if (!$rows) {
            return [];
        }
        $memberIds = array_values(array_unique(array_map(fn ($r) => (int) $r['member_id'], $rows)));
        $members = Db::name('members')->whereIn('id', $memberIds)->select()->toArray();
        $byId = [];
        foreach ($members as $m) {
            $byId[(int) $m['id']] = $m;
        }

        return array_map(fn ($r) => $this->present($r, $byId[(int) $r['member_id']] ?? null), $rows);
    }

    /**
     * @param list<array<string,mixed>> $orderItems
     * @return array<int, array{review_id: ?int, can_review: bool, can_follow: bool, reviewed: bool}>
     */
    public function flagsForOrderItems(string $orderStatus, array $orderItems): array
    {
        $itemIds = array_map(fn ($i) => (int) $i['id'], $orderItems);
        $reviews = [];
        if ($itemIds) {
            foreach (Db::name('product_reviews')->whereIn('order_item_id', $itemIds)->select()->toArray() as $r) {
                $reviews[(int) $r['order_item_id']] = $r;
            }
        }
        $out = [];
        foreach ($orderItems as $item) {
            $id = (int) $item['id'];
            $review = $reviews[$id] ?? null;
            $refunded = (int) ($item['refunded_qty'] ?? 0);
            $qty = (int) ($item['qty'] ?? 0);
            $fullyRefunded = $qty > 0 && $refunded >= $qty;
            $canReview = $orderStatus === 'completed' && !$fullyRefunded && $review === null;
            $canFollow = $review !== null && ($review['follow_body'] === null || $review['follow_body'] === '');
            $out[$id] = [
                'review_id' => $review ? (int) $review['id'] : null,
                'can_review' => $canReview,
                'can_follow' => $canFollow,
                'reviewed' => $review !== null,
            ];
        }

        return $out;
    }

    public function create(int $memberId, array $body): array
    {
        $orderItemId = (int) ($body['order_item_id'] ?? 0);
        $stars = (int) ($body['stars'] ?? 0);
        $text = trim((string) ($body['body'] ?? ''));
        $images = $body['images'] ?? [];
        if (!is_array($images)) {
            $images = [];
        }
        if ($orderItemId <= 0) {
            throw new ApiException('INVALID', '缺少訂單商品', 422, 42291);
        }
        if ($stars < 1 || $stars > 5) {
            throw new ApiException('INVALID', '星級須為 1–5', 422, 42292);
        }
        if ($text === '') {
            throw new ApiException('INVALID', '請填寫評價內容', 422, 42293);
        }
        if (mb_strlen($text) > 1000) {
            throw new ApiException('INVALID', '評價內容過長', 422, 42294);
        }
        if (count($images) > 3) {
            throw new ApiException('INVALID', '最多上傳 3 張圖片', 422, 42295);
        }
        $images = $this->normalizeImages($memberId, $images);

        $item = Db::name('order_items')->where('id', $orderItemId)->find();
        if (!$item) {
            throw new ApiException('NOT_FOUND', '訂單商品不存在', 404, 40491);
        }
        $order = Db::name('orders')->where('id', $item['order_id'])->find();
        if (!$order || (int) $order['member_id'] !== $memberId) {
            throw new ApiException('NOT_FOUND', '訂單不存在', 404, 40440);
        }
        if ($order['status'] !== 'completed') {
            throw new ApiException('INVALID_STATUS', '僅已完成訂單可評價', 422, 42296);
        }
        $qty = (int) $item['qty'];
        $refunded = (int) ($item['refunded_qty'] ?? 0);
        if ($qty > 0 && $refunded >= $qty) {
            throw new ApiException('INVALID_STATUS', '已退款商品不可評價', 422, 42297);
        }
        $exists = Db::name('product_reviews')->where('order_item_id', $orderItemId)->find();
        if ($exists) {
            throw new ApiException('ALREADY_REVIEWED', '該商品已評價', 422, 42298);
        }

        $now = date('Y-m-d H:i:s');
        $id = (int) Db::name('product_reviews')->insertGetId([
            'member_id' => $memberId,
            'order_id' => (int) $order['id'],
            'order_item_id' => $orderItemId,
            'product_id' => (int) $item['product_id'],
            'product_code' => (string) $item['product_code'],
            'stars' => $stars,
            'body' => $text,
            'images_json' => $images ? json_encode(array_values($images), JSON_UNESCAPED_UNICODE) : null,
            'follow_body' => null,
            'follow_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $row = Db::name('product_reviews')->where('id', $id)->find();
        $member = Db::name('members')->where('id', $memberId)->find();

        return $this->present($row, $member);
    }

    public function follow(int $memberId, int $reviewId, array $body): array
    {
        $text = trim((string) ($body['body'] ?? ''));
        if ($text === '') {
            throw new ApiException('INVALID', '請填寫追評內容', 422, 42299);
        }
        if (mb_strlen($text) > 1000) {
            throw new ApiException('INVALID', '追評內容過長', 422, 42294);
        }
        $row = Db::name('product_reviews')->where('id', $reviewId)->find();
        if (!$row || (int) $row['member_id'] !== $memberId) {
            throw new ApiException('NOT_FOUND', '評價不存在', 404, 40492);
        }
        if ($row['follow_body'] !== null && $row['follow_body'] !== '') {
            throw new ApiException('ALREADY_FOLLOWED', '已追評過', 422, 42300);
        }
        $now = date('Y-m-d H:i:s');
        Db::name('product_reviews')->where('id', $reviewId)->update([
            'follow_body' => $text,
            'follow_at' => $now,
            'updated_at' => $now,
        ]);
        $row = Db::name('product_reviews')->where('id', $reviewId)->find();
        $member = Db::name('members')->where('id', $memberId)->find();

        return $this->present($row, $member);
    }

    /**
     * 訂單是否仍有待評價行項。
     */
    public function orderNeedsReview(array $order, ?array $items = null): bool
    {
        if (($order['status'] ?? '') !== 'completed') {
            return false;
        }
        $items = $items ?? Db::name('order_items')->where('order_id', $order['id'])->select()->toArray();
        $flags = $this->flagsForOrderItems('completed', $items);
        foreach ($flags as $f) {
            if ($f['can_review']) {
                return true;
            }
        }

        return false;
    }

    private function normalizeImages(int $memberId, array $images): array
    {
        $out = [];
        $prefix = '/storage/review/' . $memberId . '/';
        foreach ($images as $url) {
            $url = trim((string) $url);
            if ($url === '') {
                continue;
            }
            if (!str_starts_with($url, $prefix)) {
                throw new ApiException('INVALID', '評價圖片無效', 422, 42301);
            }
            if (preg_match('#\.\.|[^\w./\-]#', $url)) {
                throw new ApiException('INVALID', '評價圖片無效', 422, 42301);
            }
            $out[] = $url;
        }

        return $out;
    }

    private function present(array $row, ?array $member): array
    {
        $images = [];
        if (!empty($row['images_json'])) {
            $decoded = json_decode((string) $row['images_json'], true);
            if (is_array($decoded)) {
                $images = array_values(array_filter(array_map('strval', $decoded)));
            }
        }
        $follow = $row['follow_body'] !== null && $row['follow_body'] !== ''
            ? (string) $row['follow_body']
            : null;

        return [
            'id' => (int) $row['id'],
            'order_item_id' => (int) $row['order_item_id'],
            'product_code' => (string) $row['product_code'],
            'stars' => (int) $row['stars'],
            'author' => $this->authorName($member, (int) $row['member_id']),
            'body' => (string) $row['body'],
            'images' => $images,
            'follow' => $follow,
            'follow_at' => $row['follow_at'],
            'created_at' => $row['created_at'],
            'can_follow' => $follow === null,
        ];
    }

    private function authorName(?array $member, int $memberId): string
    {
        if (!$member) {
            return 'M' . $memberId;
        }
        $email = trim((string) ($member['email'] ?? ''));
        if ($email !== '' && str_contains($email, '@')) {
            $local = explode('@', $email)[0];
            if (mb_strlen($local) <= 2) {
                return $local . '***';
            }

            return mb_substr($local, 0, 2) . '***';
        }
        $phone = trim((string) ($member['phone'] ?? ''));
        if ($phone !== '') {
            $len = strlen($phone);

            return $len > 4 ? ('***' . substr($phone, -4)) : ('***' . $phone);
        }

        return 'M' . $memberId;
    }
}
