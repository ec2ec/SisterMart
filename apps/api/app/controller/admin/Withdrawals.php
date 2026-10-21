<?php

declare(strict_types=1);

namespace app\controller\admin;

use app\controller\ApiController;
use app\service\DistributionService;
use think\facade\Db;
use think\Response;

class Withdrawals extends ApiController
{
    private static bool $reviewerColumnReady = false;

    public function index(): Response
    {
        return $this->run(function () {
            self::ensureReviewerColumn();

            $status = (string) $this->request->get('status', '');
            $channel = (string) $this->request->get('channel', '');
            $q = (string) $this->request->get('q', '');
            $page = max(1, (int) $this->request->get('page', 1));
            $pageSize = max(1, min(100, (int) $this->request->get('page_size', 30)));
            // 兼容舊客戶端 limit；有 page 時以 page_size 為準
            if ($this->request->get('limit') !== null && $this->request->get('page') === null) {
                $pageSize = max(1, min(500, (int) $this->request->get('limit', 200)));
                $page = 1;
            }

            $filters = [
                'status' => $status,
                'channel' => $channel,
                'q' => $q,
                'created_from' => self::parseDayBound((string) $this->request->get('created_from', ''), false),
                'created_to' => self::parseDayBound((string) $this->request->get('created_to', ''), true),
                'reviewed_from' => self::parseDayBound((string) $this->request->get('reviewed_from', ''), false),
                'reviewed_to' => self::parseDayBound((string) $this->request->get('reviewed_to', ''), true),
            ];

            $total = (int) $this->buildListQuery($filters)->count();
            $rows = $this->buildListQuery($filters)
                ->field(
                    'w.*, m.nickname AS member_nickname, m.phone AS member_phone, m.email AS member_email,'
                    . ' m.commission_available, a.name AS reviewer_name, a.email AS reviewer_email',
                )
                ->order('w.id', 'desc')
                ->page($page, $pageSize)
                ->select()
                ->toArray();

            $pendingCount = (int) Db::name('withdrawals')->where('status', 'pending_review')->count();

            return [
                'pending_count' => $pendingCount,
                'page' => $page,
                'page_size' => $pageSize,
                'total' => $total,
                'items' => array_map(fn ($r) => [
                    'id' => (int) $r['id'],
                    'member_id' => (int) $r['member_id'],
                    'member_nickname' => (string) ($r['member_nickname'] ?? ''),
                    'member_phone' => (string) ($r['member_phone'] ?? ''),
                    'member_email' => (string) ($r['member_email'] ?? ''),
                    'commission_available' => (float) ($r['commission_available'] ?? 0),
                    'channel' => $r['channel'],
                    'amount' => (float) $r['amount'],
                    'status' => $r['status'],
                    'fps_name' => $r['fps_name'],
                    'fps_id' => $r['fps_id'],
                    'review_note' => $r['review_note'],
                    'created_at' => $r['created_at'],
                    'reviewed_at' => $r['reviewed_at'],
                    'reviewed_by_admin_id' => isset($r['reviewed_by_admin_id']) && $r['reviewed_by_admin_id'] !== null
                        ? (int) $r['reviewed_by_admin_id']
                        : null,
                    'reviewer_name' => (string) ($r['reviewer_name'] ?? ''),
                    'reviewer_email' => (string) ($r['reviewer_email'] ?? ''),
                ], $rows),
            ];
        });
    }

    public function review(int $id): Response
    {
        return $this->run(function () use ($id) {
            self::ensureReviewerColumn();
            $body = $this->payload();
            $action = (string) ($body['action'] ?? '');
            $note = trim((string) ($body['note'] ?? ''));
            $admin = app('sistermart.admin') ?? [];
            $adminId = (int) ($admin['id'] ?? 0);
            $row = (new DistributionService())->reviewWithdrawal($id, $action, $note, $adminId);

            return [
                'id' => (int) $row['id'],
                'status' => $row['status'],
                'amount' => (float) $row['amount'],
                'channel' => $row['channel'],
                'review_note' => $row['review_note'] ?? null,
                'reviewed_at' => $row['reviewed_at'],
                'reviewed_by_admin_id' => isset($row['reviewed_by_admin_id']) && $row['reviewed_by_admin_id'] !== null
                    ? (int) $row['reviewed_by_admin_id']
                    : null,
            ];
        });
    }

    /** @param array<string, mixed> $filters */
    private function buildListQuery(array $filters)
    {
        $query = Db::name('withdrawals')->alias('w')
            ->leftJoin('members m', 'm.id = w.member_id')
            ->leftJoin('admins a', 'a.id = w.reviewed_by_admin_id');

        $status = (string) ($filters['status'] ?? '');
        $channel = (string) ($filters['channel'] ?? '');
        $q = (string) ($filters['q'] ?? '');
        $createdFrom = $filters['created_from'] ?? null;
        $createdTo = $filters['created_to'] ?? null;
        $reviewedFrom = $filters['reviewed_from'] ?? null;
        $reviewedTo = $filters['reviewed_to'] ?? null;

        if ($status !== '') {
            $query->where('w.status', $status);
        }
        if ($channel !== '' && in_array($channel, ['fps', 'balance'], true)) {
            $query->where('w.channel', $channel);
        }
        if (is_string($createdFrom) && $createdFrom !== '') {
            $query->where('w.created_at', '>=', $createdFrom);
        }
        if (is_string($createdTo) && $createdTo !== '') {
            $query->where('w.created_at', '<=', $createdTo);
        }
        if (is_string($reviewedFrom) && $reviewedFrom !== '') {
            $query->where('w.reviewed_at', '>=', $reviewedFrom);
        }
        if (is_string($reviewedTo) && $reviewedTo !== '') {
            $query->where('w.reviewed_at', '<=', $reviewedTo);
        }
        if ($q !== '') {
            $like = '%' . addcslashes($q, '%_\\') . '%';
            $query->where(function ($w) use ($like, $q) {
                $w->whereLike('w.fps_id', $like)
                    ->whereOr('w.fps_name', 'like', $like)
                    ->whereOr('m.nickname', 'like', $like)
                    ->whereOr('m.phone', 'like', $like)
                    ->whereOr('m.email', 'like', $like);
                if (ctype_digit($q)) {
                    $w->whereOr('w.member_id', (int) $q)->whereOr('w.id', (int) $q);
                }
            });
        }

        return $query;
    }

    /** @return string|null Y-m-d H:i:s */
    private static function parseDayBound(string $raw, bool $endOfDay): ?string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) {
            return $endOfDay ? $raw . ' 23:59:59' : $raw . ' 00:00:00';
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}(:\d{2})?$/', $raw)) {
            $norm = str_replace('T', ' ', $raw);

            return strlen($norm) === 16 ? $norm . ':00' : $norm;
        }

        return null;
    }

    private static function ensureReviewerColumn(): void
    {
        if (self::$reviewerColumnReady) {
            return;
        }
        try {
            $prefix = (string) (Db::getConnection()->getConfig('prefix') ?: '');
            $table = $prefix . 'withdrawals';
            $rows = Db::query("SHOW COLUMNS FROM `{$table}` LIKE 'reviewed_by_admin_id'");
            if (!$rows) {
                Db::execute(
                    "ALTER TABLE `{$table}` ADD COLUMN `reviewed_by_admin_id` BIGINT UNSIGNED NULL AFTER `reviewed_at`",
                );
            }
        } catch (\Throwable) {
            /* 下次請求再試；join 對 NULL 欄失敗時由 run() 暴露 */
        }
        self::$reviewerColumnReady = true;
    }
}
