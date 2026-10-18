<?php

declare(strict_types=1);

namespace app\service;

use app\support\ApiException;
use think\facade\Db;

/**
 * M18 商品評價：主評一次 + 追評一次。
 * M23：後台隱藏 / 商家回覆。
 */
class ReviewService
{
    public function listForProduct(int $productId, int $limit = 50): array
    {
        $rows = Db::name('product_reviews')
            ->where('product_id', $productId)
            ->where('is_hidden', 0)
            ->order('id', 'desc')
            ->limit($limit)
            ->select()
            ->toArray();

        return $this->presentMany($rows);
    }

    /**
     * @param array{is_hidden?: string|int|null, product_code?: string, q?: string} $filters
     */
    public function adminList(array $filters = []): array
    {
        $q = Db::name('product_reviews')->order('id', 'desc');
        if (array_key_exists('is_hidden', $filters) && $filters['is_hidden'] !== '' && $filters['is_hidden'] !== null) {
            $q->where('is_hidden', (int) $filters['is_hidden'] ? 1 : 0);
        }
        $code = trim((string) ($filters['product_code'] ?? ''));
        if ($code !== '') {
            $q->where('product_code', $code);
        }
        $keyword = trim((string) ($filters['q'] ?? ''));
        if ($keyword !== '') {
            $like = '%' . addcslashes($keyword, '%_\\') . '%';
            $q->where(function ($w) use ($like) {
                $w->where('body', 'like', $like)->whereOr('admin_reply', 'like', $like);
            });
        }
        $rows = $q->limit(200)->select()->toArray();

        return ['items' => $this->presentMany($rows, true)];
    }

    public function adminRead(int $id): array
    {
        $row = Db::name('product_reviews')->where('id', $id)->find();
        if (!$row) {
            throw new ApiException('NOT_FOUND', '評價不存在', 404, 40492);
        }
        $member = Db::name('members')->where('id', $row['member_id'])->find();

        return $this->present($row, $member, true);
    }

    public function setVisibility(int $id, array $body): array
    {
        $row = Db::name('product_reviews')->where('id', $id)->find();
        if (!$row) {
            throw new ApiException('NOT_FOUND', '評價不存在', 404, 40492);
        }
        if (!array_key_exists('is_hidden', $body)) {
            throw new ApiException('INVALID', '缺少 is_hidden', 422, 42201);
        }
        $hidden = (int) $body['is_hidden'] ? 1 : 0;
        Db::name('product_reviews')->where('id', $id)->update([
            'is_hidden' => $hidden,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return $this->adminRead($id);
    }

    public function reply(int $id, array $body): array
    {
        $row = Db::name('product_reviews')->where('id', $id)->find();
        if (!$row) {
            throw new ApiException('NOT_FOUND', '評價不存在', 404, 40492);
        }
        $text = trim((string) ($body['reply'] ?? $body['admin_reply'] ?? ''));
        if ($text === '') {
            throw new ApiException('INVALID', '請填寫商家回覆', 422, 42201);
        }
        if (mb_strlen($text) > 1000) {
            throw new ApiException('INVALID', '回覆過長', 422, 42294);
        }
        $now = date('Y-m-d H:i:s');
        Db::name('product_reviews')->where('id', $id)->update([
            'admin_reply' => $text,
            'replied_at' => $now,
            'updated_at' => $now,
        ]);

        return $this->adminRead($id);
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<array<string,mixed>>
     */
    private function presentMany(array $rows, bool $admin = false): array
    {
        if (!$rows) {
            return [];
        }
        $memberIds = array_values(array_unique(array_map(fn ($r) => (int) $r['member_id'], $rows)));
        $members = Db::name('members')->whereIn('id', $memberIds)->select()->toArray();
        $byId = [];
        foreach ($members as $m) {
            $byId[(int) $m['id']] = $m;
        }

        return array_map(fn ($r) => $this->present($r, $byId[(int) $r['member_id']] ?? null, $admin), $rows);
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
            'is_hidden' => 0,
            'admin_reply' => null,
            'replied_at' => null,
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

    private function present(array $row, ?array $member, bool $admin = false): array
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
        $reply = !empty($row['admin_reply']) ? (string) $row['admin_reply'] : null;

        $data = [
            'id' => (int) $row['id'],
            'order_item_id' => (int) $row['order_item_id'],
            'product_code' => (string) $row['product_code'],
            'stars' => (int) $row['stars'],
            'author' => $this->authorName($member, (int) $row['member_id']),
            'body' => (string) $row['body'],
            'images' => $images,
            'follow' => $follow,
            'follow_at' => $row['follow_at'],
            'reply' => $reply,
            'replied_at' => $row['replied_at'] ?? null,
            'created_at' => $row['created_at'],
            'can_follow' => $follow === null,
        ];
        if ($admin) {
            $data['is_hidden'] = (int) ($row['is_hidden'] ?? 0);
            $data['member_id'] = (int) $row['member_id'];
            $data['order_id'] = (int) $row['order_id'];
            $data['admin_reply'] = $reply;
        }

        return $data;
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
