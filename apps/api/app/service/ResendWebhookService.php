<?php

declare(strict_types=1);

namespace app\service;

use think\facade\Db;
use think\facade\Log;

/**
 * Resend Webhook（Svix 簽名）：郵件送達／退信等事件回寫 outbox。
 */
class ResendWebhookService
{
    private const TOLERANCE_SEC = 300;

    public function webhookSecret(): string
    {
        return trim((string) env('RESEND_WEBHOOK_SECRET', ''));
    }

    /**
     * 校驗 Svix 簽名。未配置密鑰時僅記錄警告並放行（方便先掛 URL），上線務必配置。
     */
    public function signatureValid(string $rawBody, ?string $svixId, ?string $svixTimestamp, ?string $svixSignature): bool
    {
        $secret = $this->webhookSecret();
        if ($secret === '') {
            Log::warning('resend.webhook missing RESEND_WEBHOOK_SECRET; accepting unverified');

            return true;
        }
        if ($svixId === null || $svixId === ''
            || $svixTimestamp === null || $svixTimestamp === ''
            || $svixSignature === null || $svixSignature === '') {
            return false;
        }
        if (!ctype_digit($svixTimestamp)) {
            return false;
        }
        $ts = (int) $svixTimestamp;
        if (abs(time() - $ts) > self::TOLERANCE_SEC) {
            return false;
        }

        $key = $secret;
        if (str_starts_with($key, 'whsec_')) {
            $decoded = base64_decode(substr($key, 6), true);
            if ($decoded === false) {
                return false;
            }
            $key = $decoded;
        }

        $toSign = $svixId . '.' . $svixTimestamp . '.' . $rawBody;
        $expected = base64_encode(hash_hmac('sha256', $toSign, $key, true));

        foreach (preg_split('/\s+/', trim($svixSignature)) ?: [] as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }
            // v1,<base64>
            $sig = $part;
            if (str_contains($part, ',')) {
                [, $sig] = array_pad(explode(',', $part, 2), 2, '');
            }
            if ($sig !== '' && hash_equals($expected, $sig)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{ok: bool, type: string, updated: int}
     */
    public function handleEvent(array $payload): array
    {
        $type = (string) ($payload['type'] ?? '');
        $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];
        $emailId = (string) ($data['email_id'] ?? $data['id'] ?? '');
        $toList = $data['to'] ?? [];
        if (!is_array($toList)) {
            $toList = $toList !== null && $toList !== '' ? [(string) $toList] : [];
        }
        $toList = array_values(array_filter(array_map(
            static fn ($v) => strtolower(trim((string) $v)),
            $toList,
        )));
        $subject = isset($data['subject']) ? (string) $data['subject'] : null;

        Log::info('resend.webhook', [
            'type' => $type,
            'email_id' => $emailId !== '' ? $emailId : null,
            'to' => $toList,
            'subject' => $subject,
        ]);

        $status = $this->mapStatus($type);
        if ($status === null) {
            return ['ok' => true, 'type' => $type, 'updated' => 0];
        }

        $this->ensureProviderMessageIdColumn();
        $updated = 0;
        $err = $this->eventErrorMessage($type, $data);

        if ($emailId !== '') {
            $updated += $this->updateByProviderId($emailId, $status, $err);
        }
        if ($updated === 0 && $toList !== []) {
            foreach ($toList as $to) {
                $updated += $this->updateRecentByRecipient($to, $subject, $status, $err, $emailId);
            }
        }

        return ['ok' => true, 'type' => $type, 'updated' => $updated];
    }

    private function mapStatus(string $type): ?string
    {
        return match ($type) {
            'email.sent' => 'sent',
            'email.delivered' => 'delivered',
            'email.delivery_delayed' => 'delayed',
            'email.bounced', 'email.failed', 'email.suppressed' => 'failed',
            'email.complained' => 'complained',
            default => null,
        };
    }

    /** @param array<string, mixed> $data */
    private function eventErrorMessage(string $type, array $data): ?string
    {
        if (!in_array($type, ['email.bounced', 'email.failed', 'email.complained', 'email.suppressed', 'email.delivery_delayed'], true)) {
            return null;
        }
        $bits = [$type];
        foreach (['bounce', 'bounce_type', 'error', 'message', 'reason'] as $k) {
            if (!empty($data[$k]) && is_scalar($data[$k])) {
                $bits[] = (string) $data[$k];
            }
        }

        return mb_substr(implode(' | ', $bits), 0, 500);
    }

    private function updateByProviderId(string $emailId, string $status, ?string $err): int
    {
        $row = Db::name('mail_outbox')->where('provider_message_id', $emailId)->find();
        if (!$row) {
            return 0;
        }
        $this->applyMailStatus((int) $row['id'], $status, $err, $emailId);

        return 1;
    }

    private function updateRecentByRecipient(
        string $to,
        ?string $subject,
        string $status,
        ?string $err,
        string $emailId,
    ): int {
        $q = Db::name('mail_outbox')
            ->where('to_email', $to)
            ->whereIn('status', ['queued', 'sent', 'mock', 'delayed', 'delivered'])
            ->where('created_at', '>=', date('Y-m-d H:i:s', time() - 86400))
            ->order('id', 'desc');
        if ($subject !== null && $subject !== '') {
            $q->where('subject', mb_substr($subject, 0, 255));
        }
        $row = $q->find();
        if (!$row) {
            // 仍嘗試 notify_outbox（無 mail 行時）
            return $this->updateNotifyOnly($to, $subject, $status, $err);
        }
        $this->applyMailStatus((int) $row['id'], $status, $err, $emailId !== '' ? $emailId : null);

        return 1;
    }

    private function updateNotifyOnly(string $to, ?string $subject, string $status, ?string $err): int
    {
        try {
            $q = Db::name('notify_outbox')
                ->where('channel', 'email')
                ->where('to_target', $to)
                ->where('created_at', '>=', date('Y-m-d H:i:s', time() - 86400))
                ->order('id', 'desc');
            if ($subject !== null && $subject !== '') {
                $q->where('subject', mb_substr($subject, 0, 255));
            }
            $row = $q->find();
            if (!$row) {
                return 0;
            }
            (new NotifyOutboxService())->updateStatus(
                (int) $row['id'],
                $status,
                $err,
                'resend',
                in_array($status, ['sent', 'delivered'], true) ? date('Y-m-d H:i:s') : null,
            );

            return 1;
        } catch (\Throwable $e) {
            return 0;
        }
    }

    private function applyMailStatus(int $mailId, string $status, ?string $err, ?string $providerId): void
    {
        $patch = [
            'status' => $status,
            'error_message' => $err,
        ];
        if (in_array($status, ['sent', 'delivered'], true)) {
            $patch['sent_at'] = date('Y-m-d H:i:s');
        }
        if ($providerId !== null && $providerId !== '') {
            $patch['provider_message_id'] = mb_substr($providerId, 0, 64);
        }
        try {
            Db::name('mail_outbox')->where('id', $mailId)->update($patch);
        } catch (\Throwable $e) {
            unset($patch['provider_message_id']);
            Db::name('mail_outbox')->where('id', $mailId)->update($patch);
        }

        // 同步最近一條同 ref 的 notify（若有）
        $mail = Db::name('mail_outbox')->where('id', $mailId)->find();
        if (!$mail) {
            return;
        }
        try {
            $notify = Db::name('notify_outbox')
                ->where('channel', 'email')
                ->where('to_target', (string) $mail['to_email'])
                ->where('ref_type', (string) ($mail['ref_type'] ?? ''))
                ->where('ref_id', (string) ($mail['ref_id'] ?? ''))
                ->order('id', 'desc')
                ->find();
            if ($notify) {
                (new NotifyOutboxService())->updateStatus(
                    (int) $notify['id'],
                    $status,
                    $err,
                    'resend',
                    $mail['sent_at'] !== null ? (string) $mail['sent_at'] : null,
                );
            }
        } catch (\Throwable $e) {
            // ignore
        }
    }

    private function ensureProviderMessageIdColumn(): void
    {
        static $ready = false;
        if ($ready) {
            return;
        }
        $prefix = (string) config('database.connections.mysql.prefix', '');
        $table = $prefix . 'mail_outbox';
        try {
            $rows = Db::query(
                "SELECT COUNT(*) AS c FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = 'provider_message_id'",
                [$table],
            );
            if ((int) ($rows[0]['c'] ?? 0) === 0) {
                Db::execute(
                    "ALTER TABLE `{$table}` ADD COLUMN `provider_message_id` VARCHAR(64) NULL DEFAULT NULL AFTER `ref_id`, ADD KEY `idx_mail_outbox_provider_msg` (`provider_message_id`)"
                );
            }
            $ready = true;
        } catch (\Throwable $e) {
            $ready = true;
        }
    }
}
