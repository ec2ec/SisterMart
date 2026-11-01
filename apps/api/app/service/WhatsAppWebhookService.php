<?php

declare(strict_types=1);

namespace app\service;

use think\facade\Log;

/**
 * WhatsApp Cloud API Webhook：Meta 訂閱驗證 + 事件接收（送達／回覆）。
 */
class WhatsAppWebhookService
{
    public function verifyToken(): string
    {
        $token = trim((string) env('WHATSAPP_VERIFY_TOKEN', ''));
        if ($token === '') {
            // 與 Meta 控制台已填口令一致；正式環境建議改為 .env 覆蓋
            $token = 'sm_wa_verify_7f3k9Qx2';
        }

        return $token;
    }

    /**
     * Meta GET 訂閱驗證。成功返回 challenge 明文；失敗返回 null。
     *
     * @param array<string, mixed> $query
     */
    public function handleVerify(array $query): ?string
    {
        $mode = (string) ($query['hub_mode'] ?? $query['hub.mode'] ?? '');
        $token = (string) ($query['hub_verify_token'] ?? $query['hub.verify_token'] ?? '');
        $challenge = (string) ($query['hub_challenge'] ?? $query['hub.challenge'] ?? '');
        if ($mode !== 'subscribe' || $challenge === '') {
            return null;
        }
        if (!hash_equals($this->verifyToken(), $token)) {
            return null;
        }

        return $challenge;
    }

    /**
     * 校驗 X-Hub-Signature-256（若配置了 APP_SECRET）。
     */
    public function signatureValid(string $rawBody, ?string $signatureHeader): bool
    {
        $secret = trim((string) env('WHATSAPP_APP_SECRET', ''));
        if ($secret === '') {
            // 未配置密鑰時先放行，避免阻斷訂閱；上線請補 WHATSAPP_APP_SECRET
            return true;
        }
        if ($signatureHeader === null || $signatureHeader === '') {
            return false;
        }
        $expected = 'sha256=' . hash_hmac('sha256', $rawBody, $secret);

        return hash_equals($expected, $signatureHeader);
    }

    /**
     * 處理 POST 事件（先記日誌；OTP 狀態回寫後續再接）。
     *
     * @param array<string, mixed> $payload
     * @return array{ok: bool, events: int}
     */
    public function handleEvent(array $payload): array
    {
        $events = 0;
        $entries = $payload['entry'] ?? null;
        if (!is_array($entries)) {
            return ['ok' => true, 'events' => 0];
        }
        foreach ($entries as $entry) {
            $changes = is_array($entry['changes'] ?? null) ? $entry['changes'] : [];
            foreach ($changes as $change) {
                $value = is_array($change['value'] ?? null) ? $change['value'] : [];
                $statuses = is_array($value['statuses'] ?? null) ? $value['statuses'] : [];
                $messages = is_array($value['messages'] ?? null) ? $value['messages'] : [];
                $events += count($statuses) + count($messages);
                Log::info('whatsapp.webhook', [
                    'field' => (string) ($change['field'] ?? ''),
                    'statuses' => count($statuses),
                    'messages' => count($messages),
                    'sample_status' => $statuses[0]['status'] ?? null,
                    'sample_wamid' => $statuses[0]['id'] ?? ($messages[0]['id'] ?? null),
                ]);
            }
        }

        return ['ok' => true, 'events' => $events];
    }
}
