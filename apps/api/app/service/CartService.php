<?php

declare(strict_types=1);

namespace app\service;

use app\support\ApiException;
use think\facade\Db;

class CartService
{
    public function ensureCart(int $memberId): int
    {
        $row = Db::name('carts')->where('member_id', $memberId)->find();
        if ($row) {
            return (int) $row['id'];
        }
        $now = date('Y-m-d H:i:s');

        return (int) Db::name('carts')->insertGetId(['member_id' => $memberId, 'updated_at' => $now]);
    }

    public function get(int $memberId): array
    {
        $cartId = $this->ensureCart($memberId);
        $items = Db::name('cart_items')->where('cart_id', $cartId)->order('id', 'desc')->select()->toArray();
        $out = [];
        $availableTotal = 0.0;
        foreach ($items as $item) {
            $presented = $this->presentItem($item, $memberId);
            $out[] = $presented;
            if ($presented['available'] && $presented['checked']) {
                $availableTotal += $presented['price'] * $presented['qty'];
            }
        }

        return [
            'items' => $out,
            'checked_total' => round($availableTotal, 2),
            'coupon_preview' => $this->couponPreview($memberId, $availableTotal),
        ];
    }

    public function add(int $memberId, int $skuId, int $qty = 1): array
    {
        $qty = max(1, $qty);
        $sku = Db::name('skus')->where('id', $skuId)->where('status', 1)->find();
        if (!$sku) {
            throw new ApiException('NOT_FOUND', 'SKU 不存在', 404, 40420);
        }
        $product = Db::name('products')->where('id', $sku['product_id'])->where('status', 1)->find();
        if (!$product) {
            throw new ApiException('NOT_FOUND', '商品已下架', 404, 40402);
        }
        if ((int) $sku['stock'] <= 0) {
            throw new ApiException('OUT_OF_STOCK', '庫存不足，無法加入購物車', 422, 42220);
        }
        $campaignBlock = (new CampaignService())->purchaseBlockReason($memberId, (int) $product['id']);
        if ($campaignBlock) {
            throw new ApiException('CAMPAIGN_EXCLUSIVE', $campaignBlock, 422, 42230);
        }
        $cartId = $this->ensureCart($memberId);
        $now = date('Y-m-d H:i:s');
        $existing = Db::name('cart_items')->where(['cart_id' => $cartId, 'sku_id' => $skuId])->find();
        if ($existing) {
            $nextQty = min((int) $sku['stock'], (int) $existing['qty'] + $qty);
            Db::name('cart_items')->where('id', $existing['id'])->update([
                'qty' => $nextQty, 'checked' => 1, 'updated_at' => $now,
            ]);
        } else {
            $nextQty = min((int) $sku['stock'], $qty);
            Db::name('cart_items')->insert([
                'cart_id' => $cartId,
                'product_id' => (int) $product['id'],
                'sku_id' => $skuId,
                'qty' => $nextQty,
                'checked' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
        Db::name('carts')->where('id', $cartId)->update(['updated_at' => $now]);

        return $this->get($memberId);
    }

    public function updateItem(int $memberId, int $itemId, array $body): array
    {
        $cartId = $this->ensureCart($memberId);
        $item = Db::name('cart_items')->where(['id' => $itemId, 'cart_id' => $cartId])->find();
        if (!$item) {
            throw new ApiException('NOT_FOUND', '購物車行不存在', 404, 40421);
        }
        $data = ['updated_at' => date('Y-m-d H:i:s')];
        if (array_key_exists('qty', $body)) {
            $sku = Db::name('skus')->where('id', $item['sku_id'])->find();
            $qty = max(1, (int) $body['qty']);
            if ($sku) {
                $qty = min($qty, max(1, (int) $sku['stock']));
            }
            $data['qty'] = $qty;
        }
        if (array_key_exists('checked', $body)) {
            $data['checked'] = !empty($body['checked']) ? 1 : 0;
        }
        Db::name('cart_items')->where('id', $itemId)->update($data);

        return $this->get($memberId);
    }

    public function removeItems(int $memberId, array $ids): array
    {
        $cartId = $this->ensureCart($memberId);
        if ($ids) {
            Db::name('cart_items')->where('cart_id', $cartId)->whereIn('id', $ids)->delete();
        }

        return $this->get($memberId);
    }

    public function setAllChecked(int $memberId, bool $checked): array
    {
        $cartId = $this->ensureCart($memberId);
        Db::name('cart_items')->where('cart_id', $cartId)->update([
            'checked' => $checked ? 1 : 0,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return $this->get($memberId);
    }

    public function checkedLines(int $memberId): array
    {
        $cart = $this->get($memberId);
        $lines = array_values(array_filter($cart['items'], fn ($i) => $i['available'] && $i['checked']));
        if (!$lines) {
            throw new ApiException('EMPTY_CART', '請先勾選可結算商品', 422, 42222);
        }

        return $lines;
    }

    public function clearChecked(int $memberId): void
    {
        $cartId = $this->ensureCart($memberId);
        $items = Db::name('cart_items')->where(['cart_id' => $cartId, 'checked' => 1])->column('id');
        if ($items) {
            Db::name('cart_items')->whereIn('id', $items)->delete();
        }
    }

    private function presentItem(array $item, int $memberId = 0): array
    {
        $sku = Db::name('skus')->where('id', $item['sku_id'])->find();
        $product = Db::name('products')->where('id', $item['product_id'])->find();
        $names = Db::name('product_i18n')->where('product_id', $item['product_id'])->select()->toArray();
        $name = ['zh-HK' => '', 'en' => ''];
        foreach ($names as $n) {
            $name[$n['locale']] = $n['name'];
        }
        $spec = $this->specText((int) $item['sku_id']);
        $stock = $sku ? (int) $sku['stock'] : 0;
        $onSale = $product && (int) $product['status'] === 1 && $sku && (int) $sku['status'] === 1;
        $reason = '';
        $invalidMessage = '';
        $available = true;
        if (!$onSale) {
            $available = false;
            $reason = 'off_sale';
        } elseif ($stock <= 0) {
            $available = false;
            $reason = 'out_of_stock';
        } elseif ((int) $item['qty'] > $stock) {
            $available = false;
            $reason = 'qty_exceed';
        } else {
            $campaignBlock = (new CampaignService())->purchaseBlockReason(
                $memberId > 0 ? $memberId : null,
                (int) $item['product_id'],
            );
            if ($campaignBlock) {
                $available = false;
                $reason = 'campaign_exclusive';
                $invalidMessage = $campaignBlock;
            }
        }

        return [
            'id' => (int) $item['id'],
            'product_id' => (string) ($product['code'] ?? $item['product_id']),
            'product_db_id' => (int) $item['product_id'],
            'sku_id' => (int) $item['sku_id'],
            'sku_code' => (string) ($sku['code'] ?? ''),
            'name' => $name,
            'spec' => $spec,
            'price' => $sku ? (float) $sku['price'] : 0,
            'compare_at' => $sku ? (float) ($sku['compare_at'] ?? 0) : 0,
            'qty' => (int) $item['qty'],
            'stock' => $stock,
            'image' => $sku['image_url'] ?? $product['cover_url'] ?? '',
            'checked' => (int) $item['checked'] === 1 && $available,
            'available' => $available,
            'invalid_reason' => $reason,
            'invalid_message' => $invalidMessage,
        ];
    }

    private function specText(int $skuId): array
    {
        $optionIds = Db::name('sku_options')->where('sku_id', $skuId)->column('spec_option_id');
        $zh = [];
        $en = [];
        if ($optionIds) {
            $rows = Db::name('spec_options')->alias('o')
                ->join('spec_option_i18n i', 'i.spec_option_id = o.id')
                ->whereIn('o.id', $optionIds)
                ->field('i.locale, i.name')
                ->select()->toArray();
            foreach ($rows as $row) {
                if ($row['locale'] === 'en') {
                    $en[] = $row['name'];
                } else {
                    $zh[] = $row['name'];
                }
            }
        }

        return ['zh-HK' => implode(' / ', $zh), 'en' => implode(' / ', $en)];
    }

    private function couponPreview(int $memberId, float $total): array
    {
        $grants = Db::name('coupon_grants')->alias('g')
            ->join('coupons c', 'c.id = g.coupon_id')
            ->where('g.member_id', $memberId)
            ->where('g.status', 'unused')
            ->where('c.status', 1)
            ->field('g.id, c.threshold, c.amount, c.name_zh, c.name_en')
            ->select()->toArray();
        $available = 0;
        $best = null;
        foreach ($grants as $g) {
            if ($total >= (float) $g['threshold']) {
                $available++;
                if (!$best || (float) $g['amount'] > (float) $best['amount']) {
                    $best = $g;
                }
            }
        }

        return [
            'available_count' => $available,
            'best' => $best ? [
                'grant_id' => (int) $best['id'],
                'amount' => (float) $best['amount'],
                'name' => ['zh-HK' => $best['name_zh'], 'en' => $best['name_en']],
            ] : null,
        ];
    }
}
