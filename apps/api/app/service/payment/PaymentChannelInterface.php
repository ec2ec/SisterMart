<?php

declare(strict_types=1);

namespace app\service\payment;

/**
 * 可插拔支付渠道（M10）。
 * create：向渠道登记支付意图，返回前端拉起/跳转所需字段。
 * verifyCallback：校验异步通知，成功则返回本系统 payment_id。
 */
interface PaymentChannelInterface
{
    public function code(): string;

    /**
     * @param array{payment_id:int,order_id:int,order_no:string,amount:float,currency?:string} $intent
     * @return array<string, mixed>
     */
    public function create(array $intent): array;

    /**
     * @param array<string, mixed> $payload 原始回调体（已解析 JSON / form）
     * @return array{payment_id:int,channel_ref?:string,raw?:array}
     */
    public function verifyCallback(array $payload, array $headers = []): array;
}
