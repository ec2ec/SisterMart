<?php

declare(strict_types=1);

namespace app\controller\api;

use app\controller\ApiController;
use app\service\PaymentService;
use app\service\payment\PaymentChannelFactory;
use think\Response;

/**
 * 渠道异步通知入口（公开，不走会员鉴权；由渠道侧签名保护）。
 */
class PaymentCallbacks extends ApiController
{
    public function store(string $channel): Response
    {
        return $this->run(function () use ($channel) {
            if (!in_array($channel, PaymentChannelFactory::CHANNELS, true)) {
                throw new \app\support\ApiException('INVALID', '支付渠道無效', 422, 42201);
            }
            $headers = [];
            foreach (['X-Signature', 'X-Pay-Signature', 'Authorization'] as $name) {
                $val = $this->request->header($name);
                if ($val !== null && $val !== '') {
                    $headers[$name] = $val;
                }
            }

            return (new PaymentService())->handleCallback($channel, $this->payload(), $headers);
        });
    }
}
