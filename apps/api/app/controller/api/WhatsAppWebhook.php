<?php

declare(strict_types=1);

namespace app\controller\api;

use app\BaseController;
use app\service\WhatsAppWebhookService;
use think\Response;

/**
 * WhatsApp Cloud API Webhook（公開，無會員令牌）。
 * GET：Meta 訂閱驗證；POST：消息／狀態事件。
 */
class WhatsAppWebhook extends BaseController
{
    /** Meta 訂閱驗證：須回傳 challenge 純文字 */
    public function verify(): Response
    {
        $svc = new WhatsAppWebhookService();
        $challenge = $svc->handleVerify($this->request->get());
        if ($challenge === null) {
            return response('Forbidden', 403);
        }

        return response($challenge, 200)->contentType('text/plain; charset=utf-8');
    }

    /** 接收 Webhook 事件 */
    public function receive(): Response
    {
        $raw = (string) $this->request->getContent();
        $sig = $this->request->header('X-Hub-Signature-256');
        $svc = new WhatsAppWebhookService();
        if (!$svc->signatureValid($raw, $sig !== null && $sig !== '' ? (string) $sig : null)) {
            return response('Invalid signature', 403);
        }
        $payload = json_decode($raw, true);
        if (!is_array($payload)) {
            $payload = [];
        }
        $svc->handleEvent($payload);

        // Meta 要求儘快 200
        return response('EVENT_RECEIVED', 200)->contentType('text/plain; charset=utf-8');
    }
}
