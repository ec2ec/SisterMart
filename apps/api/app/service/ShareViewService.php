<?php

declare(strict_types=1);

namespace app\service;

use app\support\ApiException;
use think\facade\Db;
use Throwable;

/**
 * M16 分享瀏覽送積分（真實表去重；自看 / 未知 ref 不计）。
 */
class ShareViewService
{
    public function record(array $body, ?int $viewerId): array
    {
        $ref = trim((string) ($body['ref'] ?? ''));
        $productCode = trim((string) ($body['product_id'] ?? ''));
        if ($ref === '' || $productCode === '') {
            throw new ApiException('INVALID', '缺少 ref 或商品', 422, 42284);
        }

        $parsed = PromoCodeService::parse($ref);
        if (!$parsed['ok']) {
            return ['mock' => false, 'credited' => false, 'reason' => 'unknown_ref'];
        }
        $owner = Db::name('members')->where('promo_code', $parsed['code'])->find();
        if (!$owner || (int) ($owner['status'] ?? 0) !== 1) {
            return ['mock' => false, 'credited' => false, 'reason' => 'unknown_ref'];
        }
        if ($viewerId && (int) $owner['id'] === $viewerId) {
            return ['mock' => false, 'credited' => false, 'reason' => 'self'];
        }

        $product = Db::name('products')->where('code', $productCode)->find();
        if (!$product) {
            return ['mock' => false, 'credited' => false, 'reason' => 'unknown_product'];
        }

        $ip = trim((string) ($body['ip'] ?? '0.0.0.0'));
        if ($ip === '') {
            $ip = '0.0.0.0';
        }
        $pts = TradeSettings::shareViewPoints();
        $now = date('Y-m-d H:i:s');

        try {
            Db::name('share_views')->insert([
                'owner_id' => (int) $owner['id'],
                'product_code' => $productCode,
                'viewer_ip' => $ip,
                'viewer_member_id' => $viewerId,
                'points' => $pts,
                'created_at' => $now,
            ]);
        } catch (Throwable) {
            return ['mock' => false, 'credited' => false, 'reason' => 'duplicate'];
        }

        if ($pts > 0) {
            (new AssetService())->changePoints(
                (int) $owner['id'],
                $pts,
                'share_view',
                'product',
                (int) $product['id'],
                '分享被瀏覽',
            );
        }

        return [
            'mock' => false,
            'credited' => $pts > 0,
            'points' => $pts,
            'owner_id' => (int) $owner['id'],
        ];
    }
}
