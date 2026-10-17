<?php

declare(strict_types=1);

namespace app\service;

use app\service\payment\PaymentChannelFactory;
use app\support\ApiException;
use think\facade\Db;

/**
 * 支付回调编排（M10）。建单仍由 OrderService::createPayment 走渠道适配层。
 */
class PaymentService
{
    public function __construct(
        private readonly ?PaymentChannelFactory $factory = null,
        private readonly ?OrderService $orders = null,
    ) {
    }

    private function factory(): PaymentChannelFactory
    {
        return $this->factory ?? new PaymentChannelFactory();
    }

    private function orders(): OrderService
    {
        return $this->orders ?? new OrderService();
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $headers
     * @return array<string, mixed>
     */
    public function handleCallback(string $channel, array $payload, array $headers = []): array
    {
        $driver = $this->factory()->make($channel);
        try {
            $verified = $driver->verifyCallback($payload, $headers);
        } catch (\InvalidArgumentException $e) {
            throw new ApiException('INVALID_CALLBACK', $e->getMessage(), 422, 42250);
        }

        $paymentId = (int) ($verified['payment_id'] ?? 0);
        if ($paymentId <= 0) {
            throw new ApiException('INVALID_CALLBACK', '回調缺少 payment_id', 422, 42250);
        }

        $order = $this->orders()->mockConfirmPayment($paymentId);
        if (!empty($verified['channel_ref'])) {
            Db::name('payments')->where('id', $paymentId)->update([
                'mock_ref' => $verified['channel_ref'],
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }

        return [
            'accepted' => true,
            'payment_id' => $paymentId,
            'order_id' => $order['id'] ?? null,
            'order_status' => $order['status'] ?? null,
        ];
    }
}
