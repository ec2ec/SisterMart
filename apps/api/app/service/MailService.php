<?php

declare(strict_types=1);

namespace app\service;

use think\facade\Db;

/**
 * 郵件出站：優先 SES/SMTP（MAIL_HOST + 憑證）；否則有 MAIL_FROM 時回退 mail()；皆無則 mock。
 *
 * SES 沙盒期間可用 MAIL_FORCE_MOCK=true（預設 true）強制 mock，出沙盒後設為 false。
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

        $outbox = new NotifyOutboxService();
        $providerHint = $this->smtpConfigured() ? 'ses_smtp' : 'php_mail';
        $notifyId = $outbox->record([
            'channel' => 'email',
            'purpose' => $refType ?: 'mail',
            'to_target' => $to,
            'subject' => $subject,
            'body_preview' => NotifyOutboxService::previewHtml($bodyHtml),
            'status' => 'queued',
            'provider' => $providerHint,
            'ref_type' => $refType,
            'ref_id' => $refId,
            'member_id' => ($refType !== null && str_starts_with($refType, 'otp_') && $refId !== null && ctype_digit($refId))
                ? (int) $refId
                : null,
        ]);

        $result = $this->dispatch($id);
        $outbox->syncMailRow($id, $notifyId);

        return $result;
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

        $to = (string) $row['to_email'];
        $subject = (string) $row['subject'];
        $bodyHtml = (string) $row['body_html'];
        $fromName = trim((string) env('MAIL_FROM_NAME', 'SisterMart'));

        if ($this->smtpConfigured()) {
            try {
                $this->sendSmtp($to, $subject, $bodyHtml, $from, $fromName);
                Db::name('mail_outbox')->where('id', $id)->update([
                    'status' => 'sent',
                    'sent_at' => $now,
                    'error_message' => null,
                ]);

                return ['id' => $id, 'status' => 'sent'];
            } catch (\Throwable $e) {
                Db::name('mail_outbox')->where('id', $id)->update([
                    'status' => 'failed',
                    'error_message' => mb_substr('smtp: ' . $e->getMessage(), 0, 500),
                ]);

                return ['id' => $id, 'status' => 'failed'];
            }
        }

        $headers = [
            'MIME-Version: 1.0',
            'Content-type: text/html; charset=UTF-8',
            'From: ' . $this->formatAddress($from, $fromName),
        ];
        $ok = @mail($to, $subject, $bodyHtml, implode("\r\n", $headers));
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
            'error_message' => 'mail() returned false; set MAIL_HOST/USERNAME/PASSWORD for SMTP',
        ]);

        return ['id' => $id, 'status' => 'failed'];
    }

    /** 供後台診斷：是否具備真實 SMTP 出站條件（不回傳密文）。 */
    public function diagnostics(): array
    {
        return [
            'mail_from_set' => trim((string) env('MAIL_FROM', '')) !== '',
            'mail_from_name_set' => trim((string) env('MAIL_FROM_NAME', '')) !== '',
            'smtp_host_set' => trim((string) env('MAIL_HOST', '')) !== '',
            'smtp_port' => (int) env('MAIL_PORT', 587),
            'smtp_user_set' => trim((string) env('MAIL_USERNAME', env('MAIL_USER', ''))) !== '',
            'smtp_pass_set' => trim((string) env('MAIL_PASSWORD', env('MAIL_PASS', ''))) !== '',
            'smtp_configured' => $this->smtpConfigured(),
            'driver' => $this->smtpConfigured()
                ? 'smtp'
                : (trim((string) env('MAIL_FROM', '')) !== '' ? 'php_mail' : 'mock'),
        ];
    }

    private function smtpConfigured(): bool
    {
        $host = trim((string) env('MAIL_HOST', ''));
        $user = trim((string) env('MAIL_USERNAME', env('MAIL_USER', '')));
        $pass = (string) env('MAIL_PASSWORD', env('MAIL_PASS', ''));

        return $host !== '' && $user !== '' && $pass !== '';
    }

    private function sendSmtp(
        string $to,
        string $subject,
        string $bodyHtml,
        string $from,
        string $fromName,
    ): void {
        $host = trim((string) env('MAIL_HOST', ''));
        $port = (int) env('MAIL_PORT', 587);
        $user = trim((string) env('MAIL_USERNAME', env('MAIL_USER', '')));
        $pass = (string) env('MAIL_PASSWORD', env('MAIL_PASS', ''));
        $encryption = strtolower(trim((string) env('MAIL_ENCRYPTION', 'tls')));

        $remote = ($encryption === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . ($port > 0 ? $port : 587);
        $errno = 0;
        $errstr = '';
        $fp = @stream_socket_client($remote, $errno, $errstr, 20, STREAM_CLIENT_CONNECT);
        if (!$fp) {
            throw new \RuntimeException("connect failed ({$errno}) {$errstr}");
        }
        stream_set_timeout($fp, 20);

        try {
            $this->expect($fp, 220);
            $this->cmd($fp, 'EHLO sistermart.vip', 250);
            if ($encryption === 'tls' || $encryption === 'starttls') {
                $this->cmd($fp, 'STARTTLS', 220);
                $crypto = @stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
                if (!$crypto) {
                    throw new \RuntimeException('STARTTLS crypto failed');
                }
                $this->cmd($fp, 'EHLO sistermart.vip', 250);
            }
            $this->cmd($fp, 'AUTH LOGIN', 334);
            $this->cmd($fp, base64_encode($user), 334);
            $this->cmd($fp, base64_encode($pass), 235);

            $this->cmd($fp, 'MAIL FROM:<' . $from . '>', 250);
            $this->cmd($fp, 'RCPT TO:<' . $to . '>', 250);
            $this->cmd($fp, 'DATA', 354);

            $fromHeader = $this->formatAddress($from, $fromName);
            $payload = [
                'Date: ' . gmdate('D, d M Y H:i:s O'),
                'From: ' . $fromHeader,
                'To: <' . $to . '>',
                'Subject: ' . $this->encodeHeader($subject),
                'MIME-Version: 1.0',
                'Content-Type: text/html; charset=UTF-8',
                'Content-Transfer-Encoding: base64',
                '',
                chunk_split(base64_encode($bodyHtml)),
                '.',
            ];
            fwrite($fp, implode("\r\n", $payload) . "\r\n");
            $this->expect($fp, 250);
            $this->cmd($fp, 'QUIT', 221);
        } finally {
            fclose($fp);
        }
    }

    /** @param resource $fp */
    private function cmd($fp, string $line, int $expectCode): string
    {
        fwrite($fp, $line . "\r\n");

        return $this->expect($fp, $expectCode);
    }

    /** @param resource $fp */
    private function expect($fp, int $expectCode): string
    {
        $resp = '';
        while (($line = fgets($fp, 515)) !== false) {
            $resp .= $line;
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }
        $code = (int) substr($resp, 0, 3);
        if ($code !== $expectCode) {
            throw new \RuntimeException('SMTP expected ' . $expectCode . ', got: ' . trim($resp));
        }

        return trim($resp);
    }

    private function formatAddress(string $email, string $name): string
    {
        $name = trim($name);
        if ($name === '') {
            return '<' . $email . '>';
        }

        return $this->encodeHeader($name) . ' <' . $email . '>';
    }

    private function encodeHeader(string $text): string
    {
        if (preg_match('/^[\x20-\x7E]+$/', $text)) {
            return $text;
        }

        return '=?UTF-8?B?' . base64_encode($text) . '?=';
    }
}
