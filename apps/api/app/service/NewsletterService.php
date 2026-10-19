<?php

declare(strict_types=1);

namespace app\service;

use app\support\ApiException;
use think\facade\Db;

/**
 * M27 訂閱郵箱名單。
 */
class NewsletterService
{
    private static bool $schemaReady = false;

    public function subscribe(string $email, ?int $memberId = null): array
    {
        $this->ensureSchema();
        $email = $this->normalizeEmail($email);
        $now = date('Y-m-d H:i:s');
        $row = Db::name('newsletter_subscribers')->where('email', $email)->find();
        if ($row) {
            $patch = [
                'status' => 1,
                'updated_at' => $now,
            ];
            if ($memberId && empty($row['member_id'])) {
                $patch['member_id'] = $memberId;
            }
            Db::name('newsletter_subscribers')->where('id', $row['id'])->update($patch);
            $row = Db::name('newsletter_subscribers')->where('id', $row['id'])->find();

            return $this->present($row);
        }
        $id = (int) Db::name('newsletter_subscribers')->insertGetId([
            'email' => $email,
            'member_id' => $memberId,
            'status' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $this->present(Db::name('newsletter_subscribers')->where('id', $id)->find());
    }

    /**
     * 登錄會員：是否已有有效訂閱（本帳號或同郵箱）。
     */
    public function statusForMember(array $member): array
    {
        $this->ensureSchema();
        $memberId = (int) $member['id'];
        $email = strtolower(trim((string) ($member['email'] ?? '')));
        $q = Db::name('newsletter_subscribers')->where('status', 1);
        $row = $q->where(function ($w) use ($memberId, $email) {
            $w->where('member_id', $memberId);
            if ($email !== '') {
                $w->whereOr('email', $email);
            }
        })->order('id', 'desc')->find();

        return [
            'subscribed' => (bool) $row,
            'email' => $row ? (string) $row['email'] : ($email !== '' ? $email : null),
        ];
    }

    /**
     * @param array{status?: string|int|null, q?: string} $filters
     */
    public function adminList(array $filters = []): array
    {
        $this->ensureSchema();
        $q = Db::name('newsletter_subscribers')->order('id', 'desc');
        if (array_key_exists('status', $filters) && $filters['status'] !== '' && $filters['status'] !== null) {
            $q->where('status', (int) $filters['status'] ? 1 : 0);
        }
        $keyword = trim((string) ($filters['q'] ?? ''));
        if ($keyword !== '') {
            $like = '%' . addcslashes($keyword, '%_\\') . '%';
            $q->where('email', 'like', $like);
        }
        $rows = $q->limit(500)->select()->toArray();

        return ['items' => array_map([$this, 'present'], $rows)];
    }

    public function setStatus(int $id, int $status): array
    {
        $this->ensureSchema();
        $row = Db::name('newsletter_subscribers')->where('id', $id)->find();
        if (!$row) {
            throw new ApiException('NOT_FOUND', '訂閱記錄不存在', 404, 40493);
        }
        $status = $status ? 1 : 0;
        Db::name('newsletter_subscribers')->where('id', $id)->update([
            'status' => $status,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return $this->present(Db::name('newsletter_subscribers')->where('id', $id)->find());
    }

    public function delete(int $id): array
    {
        $this->ensureSchema();
        $row = Db::name('newsletter_subscribers')->where('id', $id)->find();
        if (!$row) {
            throw new ApiException('NOT_FOUND', '訂閱記錄不存在', 404, 40493);
        }
        Db::name('newsletter_subscribers')->where('id', $id)->delete();

        return ['deleted' => true, 'id' => $id];
    }

    private function normalizeEmail(string $raw): string
    {
        $email = strtolower(trim($raw));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new ApiException('EMAIL_INVALID', '請輸入有效的電子郵件', 422, 42202);
        }

        return $email;
    }

    private function present(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'email' => (string) $row['email'],
            'member_id' => $row['member_id'] !== null ? (int) $row['member_id'] : null,
            'status' => (int) $row['status'],
            'created_at' => $row['created_at'],
            'updated_at' => $row['updated_at'],
        ];
    }

    /** 生產若未跑 m27，/newsletter/me 會 500；冪等建表。 */
    private function ensureSchema(): void
    {
        if (self::$schemaReady) {
            return;
        }
        $prefix = (string) (Db::getConnection()->getConfig('prefix') ?: '');
        $table = $prefix . 'newsletter_subscribers';
        try {
            Db::execute(
                "CREATE TABLE IF NOT EXISTS `{$table}` (
                  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                  `email` VARCHAR(191) NOT NULL,
                  `member_id` BIGINT UNSIGNED NULL,
                  `status` TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '1=有效 0=已關閉',
                  `created_at` DATETIME NOT NULL,
                  `updated_at` DATETIME NOT NULL,
                  UNIQUE KEY `uk_newsletter_email` (`email`),
                  KEY `idx_newsletter_member` (`member_id`),
                  KEY `idx_newsletter_status` (`status`, `id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            );
        } catch (\Throwable $e) {
            throw new ApiException(
                'SERVER',
                '訂閱表未就緒，請執行 m27_newsletter.sql（releases/sql）',
                500,
                50027,
            );
        }
        self::$schemaReady = true;
    }
}
