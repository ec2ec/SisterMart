<?php

declare(strict_types=1);

namespace app\service;

use think\facade\Db;

/**
 * 簡易郵件出站：有 MAIL_FROM 則嘗試 mail()；否則記為 mock（開發/無 SMTP）。
 */
class MailService
{
    /**
     * @return array{id:int,status:string}
     */
    public function enqueue(
        string $to,
        string $subject,
        string $bodyHtml,
        ?string $refType = null,
        ?string $refId = null,
    ): array {
        $to = strtolower(trim($to));
        if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return ['id' => 0, 'status' => 'skipped'];
        }
        $now = date('Y-m-d H:i:s');
        $id = (int) Db::name('mail_outbox')->insertGetId([
            'to_email' => $to,
            'subject' => mb_substr($subject, 0, 255),
            'body_html' => $bodyHtml,
            'status' => 'queued',
            'ref_type' => $refType,
            'ref_id' => $refId,
            'error_message' => null,
            'created_at' => $now,
            'sent_at' => null,
        ]);

        return $this->dispatch($id);
    }

    /**
     * @return array{id:int,status:string}
     */
    public function dispatch(int $id): array
    {
        $row = Db::name('mail_outbox')->where('id', $id)->find();
        if (!$row) {
            return ['id' => $id, 'status' => 'missing'];
        }
        $from = trim((string) env('MAIL_FROM', ''));
        $now = date('Y-m-d H:i:s');
        if ($from === '') {
            Db::name('mail_outbox')->where('id', $id)->update([
                'status' => 'mock',
                'sent_at' => $now,
                'error_message' => 'MAIL_FROM not set; logged as mock',
            ]);

            return ['id' => $id, 'status' => 'mock'];
        }
        $headers = [
            'MIME-Version: 1.0',
            'Content-type: text/html; charset=UTF-8',
            'From: ' . $from,
        ];
        $ok = @mail(
            (string) $row['to_email'],
            (string) $row['subject'],
            (string) $row['body_html'],
            implode("\r\n", $headers),
        );
        if ($ok) {
            Db::name('mail_outbox')->where('id', $id)->update([
                'status' => 'sent',
                'sent_at' => $now,
                'error_message' => null,
            ]);

            return ['id' => $id, 'status' => 'sent'];
        }
        Db::name('mail_outbox')->where('id', $id)->update([
            'status' => 'failed',
            'error_message' => 'mail() returned false',
        ]);

        return ['id' => $id, 'status' => 'failed'];
    }
}
