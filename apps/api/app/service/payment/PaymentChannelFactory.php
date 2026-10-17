<?php

declare(strict_types=1);

namespace app\service\payment;

use app\support\ApiException;

class PaymentChannelFactory
{
    public const CHANNELS = ['fps', 'alipay_hk', 'wechat_hk'];

    public function make(string $channel): PaymentChannelInterface
    {
        if (!in_array($channel, self::CHANNELS, true)) {
            throw new ApiException('INVALID', '支付渠道無效', 422, 42201);
        }

        $mode = strtolower((string) config('payment.mode', 'mock'));
        if ($mode !== 'live') {
            return new MockPaymentChannel($channel);
        }

        $cfg = (array) config('payment.channels.' . $channel, []);

        return new StubRealPaymentChannel($channel, $cfg);
    }

    public function mode(): string
    {
        $mode = strtolower((string) config('payment.mode', 'mock'));

        return $mode === 'live' ? 'live' : 'mock';
    }
}
