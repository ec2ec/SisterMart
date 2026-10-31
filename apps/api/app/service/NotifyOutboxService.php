<?php

declare(strict_types=1);

namespace app\service;

use think\facade\Db;

/**
 * 系統消息／郵件／短信發送流水（管理員可查）。
 */
class NotifyOutboxService
{
    private static bool $schemaReady = false;

    /**
     * @param array{
     *   channel: string,
     *   purpose?: string,
     *   to_target: string,
     *   subject?: ?string,
     *   body_preview?: ?string,
     *   status?: string,
     *   provider?: ?string,
     *   ref_type?: ?string,
     *   ref_id?: ?string,
     *   member_id?: ?int,
     *   error_message?: ?string,
     *   sent_at?: ?string,
     * } $fields
     */
    public function record(array $fields): int
    {
        $this->ensureSchema();
        $channel = strtolower(trim((string) ($fields['channel'] ?? '')));
        if (!in_array($channel, ['email', 'sms', 'whatsapp'], true)) {
            $channel = 'email';
        }
        $to = trim((string) ($fields['to_target'] ?? ''));
        if ($to === '') {
            return 0;
        }
        $now = date('Y-m-d H:i:s');
        $status = (string) ($fields['status'] ?? 'queued');
        $sentAt = $fields['sent_at'] ?? null;
        if ($sentAt === null && in_array($status, ['sent', 'mock', 'skipped'], true)) {
            $sentAt = $now;
        }

        return (int) Db::name('notify_outbox')->insertGetId([
            'channel' => $channel,
            'purpose' => mb_substr((string) ($fields['purpose'] ?? ''), 0, 48),
            'to_target' => mb_substr($to, 0, 191),
            'subject' => isset($fields['subject']) ? mb_substr((string) $fields['subject'], 0, 255) : null,
            'body_preview' => isset($fields['body_preview'])
                ? mb_substr((string) $fields['body_preview'], 0, 500)
                : null,
            'status' => mb_substr($status, 0, 16),
            'provider' => isset($fields['provider']) ? mb_substr((string) $fields['provider'], 0, 32) : null,
            'ref_type' => isset($fields['ref_type']) ? mb_substr((string) $fields['ref_type'], 0, 32) : null,
            'ref_id' => isset($fields['ref_id']) ? mb_substr((string) $fields['ref_id'], 0, 64) : null,
            'member_id' => isset($fields['member_id']) && $fields['member_id'] !== null
                ? (int) $fields['member_id']
                : null,
            'error_message' => isset($fields['error_message'])
                ? mb_substr((string) $fields['error_message'], 0, 255)
                : null,
            'created_at' => $now,
            'sent_at' => $sentAt,
        ]);
    }

    public function updateStatus(
        int $id,
        string $status,
        ?string $errorMessage = null,
        ?string $provider = null,
        ?string $sentAt = null,
    ): void {
        if ($id <= 0) {
            return;
        }
        $this->ensureSchema();
        $patch = [
            'status' => mb_substr($status, 0, 16),
            'error_message' => $errorMessage !== null ? mb_substr($errorMessage, 0, 255) : null,
        ];
        if ($provider !== null) {
            $patch['provider'] = mb_substr($provider, 0, 32);
        }
        if ($sentAt !== null) {
            $patch['sent_at'] = $sentAt;
        } elseif (in_array($status, ['sent', 'mock', 'skipped'], true)) {
            $patch['sent_at'] = date('Y-m-d H:i:s');
        }
        Db::name('notify_outbox')->where('id', $id)->update($patch);
    }

    /** 從 mail_outbox 行同步狀態到對應流水。 */
    public function syncMailRow(int $mailId, int $notifyId): void
    {
        if ($notifyId <= 0 || $mailId <= 0) {
            return;
        }
        $row = Db::name('mail_outbox')->where('id', $mailId)->find();
        if (!$row) {
            return;
        }
        $status = (string) $row['status'];
        $err = (string) ($row['error_message'] ?? '');
        $smtpish = str_starts_with($err, 'smtp:') || (new MailService())->diagnostics()['smtp_configured'];
        $provider = match ($status) {
            'mock' => 'mock',
            'sent', 'failed', 'queued' => $smtpish ? 'ses_smtp' : 'php_mail',
            default => $smtpish ? 'ses_smtp' : 'php_mail',
        };
        $this->updateStatus(
            $notifyId,
            $status,
            $row['error_message'] !== null ? (string) $row['error_message'] : null,
            $provider,
            $row['sent_at'] !== null ? (string) $row['sent_at'] : null,
        );
    }

    /**
     * @param array{channel?: string, status?: string, purpose?: string, q?: string} $filters
     * @return array{items: list<array<string, mixed>>}
     */
    public function adminList(array $filters = []): array
    {
        $this->ensureSchema();
        $q = Db::name('notify_outbox')->order('id', 'desc');
        $channel = strtolower(trim((string) ($filters['channel'] ?? '')));
        if (in_array($channel, ['email', 'sms', 'whatsapp'], true)) {
            $q->where('channel', $channel);
        }
        $status = trim((string) ($filters['status'] ?? ''));
        if ($status !== '') {
            $q->where('status', $status);
        }
        $purpose = trim((string) ($filters['purpose'] ?? ''));
        if ($purpose !== '') {
            $q->where('purpose', $purpose);
        }
        $keyword = trim((string) ($filters['q'] ?? ''));
        if ($keyword !== '') {
            $like = '%' . addcslashes($keyword, '%_\\') . '%';
            $q->where(function ($w) use ($like) {
                $w->where('to_target', 'like', $like)
                    ->whereOr('subject', 'like', $like)
                    ->whereOr('body_preview', 'like', $like)
                    ->whereOr('ref_id', 'like', $like);
            });
        }
        $rows = $q->limit(500)->select()->toArray();

        return ['items' => array_map([$this, 'present'], $rows)];
    }

    /** @return array<string, mixed> */
    private function present(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'channel' => (string) $row['channel'],
            'purpose' => (string) ($row['purpose'] ?? ''),
            'to_target' => (string) $row['to_target'],
            'subject' => $row['subject'] !== null ? (string) $row['subject'] : null,
            'body_preview' => $row['body_preview'] !== null ? (string) $row['body_preview'] : null,
            'status' => (string) $row['status'],
            'provider' => $row['provider'] !== null ? (string) $row['provider'] : null,
            'ref_type' => $row['ref_type'] !== null ? (string) $row['ref_type'] : null,
            'ref_id' => $row['ref_id'] !== null ? (string) $row['ref_id'] : null,
            'member_id' => $row['member_id'] !== null ? (int) $row['member_id'] : null,
            'error_message' => $row['error_message'] !== null ? (string) $row['error_message'] : null,
            'created_at' => (string) $row['created_at'],
            'sent_at' => $row['sent_at'] !== null ? (string) $row['sent_at'] : null,
        ];
    }

    public function ensureSchema(): void
    {
        if (self::$schemaReady) {
            return;
        }
        $prefix = (string) (Db::getConnection()->getConfig('prefix') ?: '');
        $table = $prefix . 'notify_outbox';
        try {
            Db::execute(
                "CREATE TABLE IF NOT EXISTS `{$table}` (
                  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                  `channel` VARCHAR(16) NOT NULL COMMENT 'email|sms|whatsapp',
                  `purpose` VARCHAR(48) NOT NULL DEFAULT '',
                  `to_target` VARCHAR(191) NOT NULL,
                  `subject` VARCHAR(255) NULL,
                  `body_preview` VARCHAR(500) NULL,
                  `status` VARCHAR(16) NOT NULL DEFAULT 'queued',
                  `provider` VARCHAR(32) NULL,
                  `ref_type` VARCHAR(32) NULL,
                  `ref_id` VARCHAR(64) NULL,
                  `member_id` INT UNSIGNED NULL,
                  `error_message` VARCHAR(255) NULL,
                  `created_at` DATETIME NOT NULL,
                  `sent_at` DATETIME NULL,
                  KEY `idx_notify_created` (`created_at`, `id`),
                  KEY `idx_notify_channel_created` (`channel`, `created_at`),
                  KEY `idx_notify_status` (`status`, `id`),
                  KEY `idx_notify_target` (`to_target`),
                  KEY `idx_notify_purpose` (`purpose`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            );
        } catch (\Throwable) {
            // 表已存在或無權限時後續查詢會暴露真實錯誤
        }
        self::$schemaReady = true;
    }

    public static function previewHtml(string $html, int $max = 500): string
    {
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\s+/u', ' ', trim($text)) ?? '';

        return mb_substr($text, 0, $max);
    }
}
