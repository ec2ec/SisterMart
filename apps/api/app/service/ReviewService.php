<?php

declare(strict_types=1);

namespace app\service;

use app\support\ApiException;
use think\facade\Db;

/**
 * M18 商品評價：主評一次 + 追評一次。
 * M23：後台隱藏 / 商家回覆。
 * M25：審核（pending / approved / rejected）；本人可見，他人僅 approved。
 * M44：管理員 30 天內可發起重評；用戶以首評同表單覆蓋舊評價。
 */
class ReviewService
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    /** 自首次評價 created_at 起可發起重評／完成重評的天數 */
    public const RE_REVIEW_DAYS = 30;

    /**
     * 商品詳情評價列表。
     * - 他人：僅 approved 且未隱藏
     * - 本人：無論審核狀態均可見（仍尊重 is_hidden=隱藏則不對外；本人仍可見自己的）
     */
    public function listForProduct(int $productId, ?int $viewerMemberId = null, int $limit = 50): array
    {
        $q = Db::name('product_reviews')
            ->where('product_id', $productId)
            ->order('id', 'desc')
            ->limit($limit);

        if ($viewerMemberId !== null && $viewerMemberId > 0) {
            $viewerId = $viewerMemberId;
            $q->where(function ($w) use ($viewerId) {
                $w->where(function ($pub) {
                    $pub->where('moderation_status', self::STATUS_APPROVED)->where('is_hidden', 0);
                })->whereOr(function ($own) use ($viewerId) {
                    $own->where('member_id', $viewerId);
                });
            });
        } else {
            $q->where('moderation_status', self::STATUS_APPROVED)->where('is_hidden', 0);
        }

        $rows = $q->select()->toArray();

        return $this->presentMany($rows, false, $viewerMemberId);
    }

    /**
     * @param array{is_hidden?: string|int|null, moderation_status?: string|null, product_code?: string, q?: string} $filters
     */
    public function adminList(array $filters = []): array
    {
        $this->ensureReReviewColumns();
        $q = Db::name('product_reviews')->order('id', 'desc');
        if (array_key_exists('is_hidden', $filters) && $filters['is_hidden'] !== '' && $filters['is_hidden'] !== null) {
            $q->where('is_hidden', (int) $filters['is_hidden'] ? 1 : 0);
        }
        $mod = trim((string) ($filters['moderation_status'] ?? ''));
        if ($mod !== '' && in_array($mod, [self::STATUS_PENDING, self::STATUS_APPROVED, self::STATUS_REJECTED], true)) {
            $q->where('moderation_status', $mod);
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
        self::refreshProductRating((int) $row['product_id']);

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
     * 審核通過 / 拒絕。
     *
     * @param array{status?: string, moderation_status?: string} $body
     */
    public function moderate(int $id, array $body): array
    {
        $row = Db::name('product_reviews')->where('id', $id)->find();
        if (!$row) {
            throw new ApiException('NOT_FOUND', '評價不存在', 404, 40492);
        }
        $status = trim((string) ($body['status'] ?? $body['moderation_status'] ?? ''));
        if (!in_array($status, [self::STATUS_APPROVED, self::STATUS_REJECTED], true)) {
            throw new ApiException('INVALID', '審核狀態須為 approved 或 rejected', 422, 42201);
        }
        Db::name('product_reviews')->where('id', $id)->update([
            'moderation_status' => $status,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        self::refreshProductRating((int) $row['product_id']);

        return $this->adminRead($id);
    }

    /**
     * 管理員發起重評（評價創建起 30 天內；同一時間僅一筆待完成重評）。
     */
    public function adminRequestReReview(int $id): array
    {
        $this->ensureReReviewColumns();
        $row = Db::name('product_reviews')->where('id', $id)->find();
        if (!$row) {
            throw new ApiException('NOT_FOUND', '評價不存在', 404, 40492);
        }
        if (!$this->withinReReviewWindow($row)) {
            throw new ApiException('INVALID', '已超過 ' . self::RE_REVIEW_DAYS . ' 天，無法發起重評', 422, 42310);
        }
        if (!empty($row['re_review_requested_at'])) {
            throw new ApiException('INVALID', '已發起重評，等待用戶提交', 422, 42311);
        }
        $now = date('Y-m-d H:i:s');
        Db::name('product_reviews')->where('id', $id)->update([
            're_review_requested_at' => $now,
            'updated_at' => $now,
        ]);

        return $this->adminRead($id);
    }

    /** 依已通過且未隱藏評價重算商品評分 */
    public static function refreshProductRating(int $productId): void
    {
        if ($productId < 1) {
            return;
        }
        $agg = Db::name('product_reviews')
            ->where('product_id', $productId)
            ->where('moderation_status', self::STATUS_APPROVED)
            ->where('is_hidden', 0)
            ->fieldRaw('ROUND(AVG(stars), 1) AS avg_stars, COUNT(*) AS cnt')
            ->find();
        $avg = $agg && $agg['avg_stars'] !== null ? round((float) $agg['avg_stars'], 1) : 0.0;
        $cnt = $agg ? (int) $agg['cnt'] : 0;
        Db::name('products')->where('id', $productId)->update([
            'rating_avg' => $avg,
            'rating_count' => $cnt,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<array<string,mixed>>
     */
    private function presentMany(array $rows, bool $admin = false, ?int $viewerMemberId = null): array
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

        return array_map(
            fn ($r) => $this->present($r, $byId[(int) $r['member_id']] ?? null, $admin, $viewerMemberId),
            $rows,
        );
    }

    /**
     * @param list<array<string,mixed>> $orderItems
     * @return array<int, array{
     *   review_id: ?int,
     *   can_review: bool,
     *   can_follow: bool,
     *   can_re_review: bool,
     *   reviewed: bool,
     *   review: ?array{
     *     id: int,
     *     stars: int,
     *     body: string,
     *     images: list<string>,
     *     follow: ?string,
     *     follow_at: mixed,
     *     reply: ?string,
     *     replied_at: mixed,
     *     created_at: mixed,
     *     moderation_status: string,
     *     re_review_requested_at: mixed,
     *     re_reviewed_at: mixed
     *   }
     * }>
     */
    public function flagsForOrderItems(string $orderStatus, array $orderItems): array
    {
        $this->ensureReReviewColumns();
        $itemIds = array_map(fn ($i) => (int) $i['id'], $orderItems);
        $reviews = [];
        if ($itemIds) {
            try {
                foreach (Db::name('product_reviews')->whereIn('order_item_id', $itemIds)->select()->toArray() as $r) {
                    $reviews[(int) $r['order_item_id']] = $r;
                }
            } catch (\Throwable) {
                // 生產庫尚未建 product_reviews 時，詳情仍可打開
                $reviews = [];
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
            $canFollow = $review !== null
                && ($review['follow_body'] === null || $review['follow_body'] === '')
                && !$this->canMemberReReview($review);
            $canReReview = $review !== null && $this->canMemberReReview($review);
            $out[$id] = [
                'review_id' => $review ? (int) $review['id'] : null,
                'can_review' => $canReview,
                'can_follow' => $canFollow,
                'can_re_review' => $canReReview,
                'reviewed' => $review !== null,
                'review' => $review ? $this->summaryForOrderItem($review) : null,
            ];
        }

        return $out;
    }

    /**
     * 訂單詳情用的評價摘要（買家自見，含審核中／未通過）。
     *
     * @param array<string, mixed> $row
     * @return array{
     *   id: int,
     *   stars: int,
     *   body: string,
     *   images: list<string>,
     *   follow: ?string,
     *   follow_at: mixed,
     *   reply: ?string,
     *   replied_at: mixed,
     *   created_at: mixed,
     *   moderation_status: string
     * }
     */
    private function summaryForOrderItem(array $row): array
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

        return [
            'id' => (int) $row['id'],
            'stars' => (int) $row['stars'],
            'body' => (string) $row['body'],
            'images' => $images,
            'follow' => $follow,
            'follow_at' => $row['follow_at'] ?? null,
            'reply' => $reply,
            'replied_at' => $row['replied_at'] ?? null,
            'created_at' => $row['created_at'] ?? null,
            'moderation_status' => (string) ($row['moderation_status'] ?? self::STATUS_APPROVED),
            're_review_requested_at' => $row['re_review_requested_at'] ?? null,
            're_reviewed_at' => $row['re_reviewed_at'] ?? null,
        ];
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
        if (count($images) > 5) {
            throw new ApiException('INVALID', '最多上傳 5 張圖片', 422, 42295);
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
            'moderation_status' => self::STATUS_PENDING,
            'admin_reply' => null,
            'replied_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $row = Db::name('product_reviews')->where('id', $id)->find();
        $member = Db::name('members')->where('id', $memberId)->find();

        return $this->present($row, $member, false, $memberId);
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
        // 追評後重新進入待審；他人在通過前看不到整條評價（含主評）。
        Db::name('product_reviews')->where('id', $reviewId)->update([
            'follow_body' => $text,
            'follow_at' => $now,
            'moderation_status' => self::STATUS_PENDING,
            'updated_at' => $now,
        ]);
        $row = Db::name('product_reviews')->where('id', $reviewId)->find();
        $member = Db::name('members')->where('id', $memberId)->find();

        return $this->present($row, $member, false, $memberId);
    }

    /**
     * 用戶重評：與首評相同字段，覆蓋舊評價（清追評／商家回覆，重回待審）。
     */
    public function rereview(int $memberId, int $reviewId, array $body): array
    {
        $this->ensureReReviewColumns();
        $stars = (int) ($body['stars'] ?? 0);
        $text = trim((string) ($body['body'] ?? ''));
        $images = $body['images'] ?? [];
        if (!is_array($images)) {
            $images = [];
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
        if (count($images) > 5) {
            throw new ApiException('INVALID', '最多上傳 5 張圖片', 422, 42295);
        }
        $images = $this->normalizeImages($memberId, $images);

        $row = Db::name('product_reviews')->where('id', $reviewId)->find();
        if (!$row || (int) $row['member_id'] !== $memberId) {
            throw new ApiException('NOT_FOUND', '評價不存在', 404, 40492);
        }
        if (!$this->canMemberReReview($row)) {
            throw new ApiException('INVALID', '當前不可重新評價', 422, 42312);
        }

        $now = date('Y-m-d H:i:s');
        Db::name('product_reviews')->where('id', $reviewId)->update([
            'stars' => $stars,
            'body' => $text,
            'images_json' => $images ? json_encode(array_values($images), JSON_UNESCAPED_UNICODE) : null,
            'follow_body' => null,
            'follow_at' => null,
            'admin_reply' => null,
            'replied_at' => null,
            'moderation_status' => self::STATUS_PENDING,
            're_review_requested_at' => null,
            're_reviewed_at' => $now,
            'updated_at' => $now,
        ]);
        self::refreshProductRating((int) $row['product_id']);

        $row = Db::name('product_reviews')->where('id', $reviewId)->find();
        $member = Db::name('members')->where('id', $memberId)->find();

        return $this->present($row, $member, false, $memberId);
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

    private function present(array $row, ?array $member, bool $admin = false, ?int $viewerMemberId = null): array
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
        $moderation = (string) ($row['moderation_status'] ?? self::STATUS_APPROVED);
        $isOwner = $viewerMemberId !== null && $viewerMemberId > 0 && (int) $row['member_id'] === $viewerMemberId;

        $data = [
            'id' => (int) $row['id'],
            'order_item_id' => (int) $row['order_item_id'],
            'product_code' => (string) $row['product_code'],
            'stars' => (int) $row['stars'],
            'author' => $admin
                ? $this->authorNameRaw($member, (int) $row['member_id'])
                : $this->authorName($member, (int) $row['member_id']),
            'body' => (string) $row['body'],
            'images' => $images,
            'follow' => $follow,
            'follow_at' => $row['follow_at'],
            'reply' => $reply,
            'replied_at' => $row['replied_at'] ?? null,
            'created_at' => $row['created_at'],
            'can_follow' => $follow === null && $isOwner && !$this->canMemberReReview($row),
            'can_re_review' => $isOwner && $this->canMemberReReview($row),
            'moderation_status' => $moderation,
            'is_mine' => $isOwner,
            're_review_requested_at' => $row['re_review_requested_at'] ?? null,
            're_reviewed_at' => $row['re_reviewed_at'] ?? null,
        ];
        if ($admin) {
            $data['is_hidden'] = (int) ($row['is_hidden'] ?? 0);
            $data['member_id'] = (int) $row['member_id'];
            $data['order_id'] = (int) $row['order_id'];
            $data['admin_reply'] = $reply;
            $data['can_follow'] = $follow === null;
            $data['can_request_re_review'] = $this->canAdminRequestReReview($row);
            $data['within_re_review_window'] = $this->withinReReviewWindow($row);
            $data['re_review_days'] = self::RE_REVIEW_DAYS;
        }

        return $data;
    }

    /** @param array<string, mixed> $row */
    private function withinReReviewWindow(array $row): bool
    {
        $created = strtotime((string) ($row['created_at'] ?? ''));
        if ($created === false) {
            return false;
        }

        return time() <= $created + self::RE_REVIEW_DAYS * 86400;
    }

    /** @param array<string, mixed> $row */
    private function canAdminRequestReReview(array $row): bool
    {
        if (!$this->withinReReviewWindow($row)) {
            return false;
        }

        return empty($row['re_review_requested_at']);
    }

    /** @param array<string, mixed> $row */
    private function canMemberReReview(array $row): bool
    {
        if (!$this->withinReReviewWindow($row)) {
            return false;
        }

        return !empty($row['re_review_requested_at']);
    }

    private function ensureReReviewColumns(): void
    {
        static $ready = false;
        if ($ready) {
            return;
        }
        $prefix = (string) config('database.connections.mysql.prefix', '');
        $table = $prefix . 'product_reviews';
        try {
            foreach (['re_review_requested_at' => 'DATETIME NULL', 're_reviewed_at' => 'DATETIME NULL'] as $col => $def) {
                $rows = Db::query(
                    "SELECT COUNT(*) AS c FROM information_schema.COLUMNS
                     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?",
                    [$table, $col],
                );
                if ((int) ($rows[0]['c'] ?? 0) === 0) {
                    Db::execute("ALTER TABLE `{$table}` ADD COLUMN `{$col}` {$def}");
                }
            }
            $ready = true;
        } catch (\Throwable $e) {
            // 列表在缺列時仍盡量工作；寫入路徑會再拋錯
            $ready = true;
        }
    }

    /** 前台展示：脫敏作者名 */
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

    /** 後台管理：完整郵箱／手機／暱稱，不脫敏 */
    private function authorNameRaw(?array $member, int $memberId): string
    {
        if (!$member) {
            return 'M' . $memberId;
        }
        $email = trim((string) ($member['email'] ?? ''));
        if ($email !== '') {
            return $email;
        }
        $phone = trim((string) ($member['phone'] ?? ''));
        if ($phone !== '') {
            return $phone;
        }
        $nick = trim((string) ($member['nickname'] ?? ''));
        if ($nick !== '') {
            return $nick;
        }

        return 'M' . $memberId;
    }
}
