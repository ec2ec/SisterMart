<?php

declare(strict_types=1);

namespace app\service\payment;

use app\support\ApiException;

/**
 * 真渠道占位：live 模式但未配齐密钥时拒绝，避免假成功。
 * M11 起可替换为各渠道 SDK 实现，保持同一接口。
 */
class StubRealPaymentChannel implements PaymentChannelInterface
{
    public function __construct(
        private readonly string $channelCode,
        private readonly array $config,
    ) {
    }

    public function code(): string
    {
        return $this->channelCode;
    }

    public function create(array $intent): array
    {
        if (!$this->configured()) {
            throw new ApiException(
                'PAYMENT_NOT_CONFIGURED',
                '真支付渠道未配置，請使用模擬模式或補齊商戶密鑰',
                503,
                50310,
            );
        }

        throw new ApiException(
            'PAYMENT_CHANNEL_PENDING',
            '真支付適配實作尚未接通（M11），渠道=' . $this->channelCode,
            501,
            50110,
        );
    }

    public function verifyCallback(array $payload, array $headers = []): array
    {
        if (!$this->configured()) {
            throw new ApiException(
                'PAYMENT_NOT_CONFIGURED',
                '真支付回調未配置密鑰，已拒絕',
                503,
                50311,
            );
        }

        throw new ApiException(
            'PAYMENT_CHANNEL_PENDING',
            '真支付回調驗簽尚未接通（M11）',
            501,
            50111,
        );
    }

    private function configured(): bool
    {
        if (empty($this->config['enabled'])) {
            return false;
        }

        return match ($this->channelCode) {
            'fps' => $this->filled($this->config, ['merchant_id', 'secret']),
            'alipay_hk' => $this->filled($this->config, ['app_id', 'private_key', 'public_key']),
            'wechat_hk' => $this->filled($this->config, ['mch_id', 'api_key', 'app_id']),
            default => false,
        };
    }

    private function filled(array $cfg, array $keys): bool
    {
        foreach ($keys as $key) {
            if (trim((string) ($cfg[$key] ?? '')) === '') {
                return false;
            }
        }

        return true;
    }
}
