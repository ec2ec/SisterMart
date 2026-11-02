<?php

declare(strict_types=1);

namespace app\controller\api;

use app\BaseController;
use app\service\ResendWebhookService;
use think\Response;

/**
 * Resend Webhook（公開，無會員令牌）。
 * POST：email.sent / delivered / bounced / failed 等；Svix 簽名校驗。
 */
class ResendWebhook extends BaseController
{
    public function receive(): Response
    {
        $raw = (string) $this->request->getContent();
        $svc = new ResendWebhookService();
        $ok = $svc->signatureValid(
            $raw,
            $this->headerFirst('svix-id'),
            $this->headerFirst('svix-timestamp'),
            $this->headerFirst('svix-signature'),
        );
        if (!$ok) {
            return response('Invalid signature', 400)->contentType('text/plain; charset=utf-8');
        }

        $payload = json_decode($raw, true);
        if (!is_array($payload)) {
            $payload = [];
        }
        $svc->handleEvent($payload);

        return response('ok', 200)->contentType('text/plain; charset=utf-8');
    }

    private function headerFirst(string $name): ?string
    {
        $v = $this->request->header($name);
        if ($v === null || $v === '') {
            return null;
        }

        return is_array($v) ? (string) ($v[0] ?? '') : (string) $v;
    }
}
