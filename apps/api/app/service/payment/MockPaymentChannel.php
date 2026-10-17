<?php

declare(strict_types=1);

namespace app\service\payment;

/**
 * 一期模拟渠道：不请求外部网关；前端继续用 mock-confirm 落账。
 */
class MockPaymentChannel implements PaymentChannelInterface
{
    public function __construct(private readonly string $channelCode)
    {
    }

    public function code(): string
    {
        return $this->channelCode;
    }

    public function create(array $intent): array
    {
        $ref = 'MOCK-' . strtoupper($this->channelCode) . '-' . $intent['order_id'] . '-' . time();

        return [
            'mode' => 'mock',
            'channel' => $this->channelCode,
            'payment_id' => $intent['payment_id'],
            'mock_ref' => $ref,
            'next' => [
                'action' => 'mock_confirm',
                'path' => '/api/v1/payments/' . $intent['payment_id'] . '/mock-confirm',
            ],
        ];
    }

    public function verifyCallback(array $payload, array $headers = []): array
    {
        $paymentId = (int) ($payload['payment_id'] ?? 0);
        if ($paymentId <= 0) {
            throw new \InvalidArgumentException('mock callback missing payment_id');
        }

        return [
            'payment_id' => $paymentId,
            'channel_ref' => (string) ($payload['mock_ref'] ?? ''),
            'raw' => $payload,
        ];
    }
}
