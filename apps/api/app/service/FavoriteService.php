<?php

declare(strict_types=1);

namespace app\service;

use app\support\ApiException;
use think\facade\Db;

/**
 * M28 會員收藏（服務端）。
 */
class FavoriteService
{
    public function list(int $memberId): array
    {
        $rows = Db::name('member_favorites')
            ->where('member_id', $memberId)
            ->order('id', 'desc')
            ->limit(200)
            ->select()
            ->toArray();
        if (!$rows) {
            return ['items' => [], 'product_codes' => []];
        }
        $productIds = array_map(fn ($r) => (int) $r['product_id'], $rows);
        $products = Db::name('products')->whereIn('id', $productIds)->select()->toArray();
        $byId = [];
        foreach ($products as $p) {
            $byId[(int) $p['id']] = $p;
        }
        $catalog = new CatalogService();
        $mode = SoldDisplay::mode();
        $items = [];
        $codes = [];
        foreach ($rows as $r) {
            $p = $byId[(int) $r['product_id']] ?? null;
            if (!$p) {
                continue;
            }
            $card = $catalog->presentCardPublic($p, $mode);
            $card['favorited_at'] = $r['created_at'];
            $card['invalid'] = (int) ($p['status'] ?? 0) !== 1;
            $items[] = $card;
            $codes[] = (string) $p['code'];
        }

        return ['items' => $items, 'product_codes' => $codes];
    }

    public function codes(int $memberId): array
    {
        $ids = Db::name('member_favorites')->where('member_id', $memberId)->column('product_id');
        if (!$ids) {
            return ['product_codes' => []];
        }
        $codes = Db::name('products')->whereIn('id', $ids)->column('code');

        return ['product_codes' => array_values(array_map('strval', $codes ?: []))];
    }

    public function add(int $memberId, string $idOrCode): array
    {
        $product = $this->findProduct($idOrCode);
        $now = date('Y-m-d H:i:s');
        $exists = Db::name('member_favorites')
            ->where(['member_id' => $memberId, 'product_id' => (int) $product['id']])
            ->find();
        if (!$exists) {
            Db::name('member_favorites')->insert([
                'member_id' => $memberId,
                'product_id' => (int) $product['id'],
                'created_at' => $now,
            ]);
        }

        return [
            'favorited' => true,
            'product_code' => (string) $product['code'],
        ];
    }

    public function remove(int $memberId, string $idOrCode): array
    {
        $product = $this->findProduct($idOrCode);
        Db::name('member_favorites')
            ->where(['member_id' => $memberId, 'product_id' => (int) $product['id']])
            ->delete();

        return [
            'favorited' => false,
            'product_code' => (string) $product['code'],
        ];
    }

    public function toggle(int $memberId, string $idOrCode): array
    {
        $product = $this->findProduct($idOrCode);
        $exists = Db::name('member_favorites')
            ->where(['member_id' => $memberId, 'product_id' => (int) $product['id']])
            ->find();
        if ($exists) {
            return $this->remove($memberId, (string) $product['code']);
        }

        return $this->add($memberId, (string) $product['code']);
    }

    private function findProduct(string $idOrCode): array
    {
        $idOrCode = trim($idOrCode);
        if ($idOrCode === '') {
            throw new ApiException('INVALID', '缺少商品', 422, 42201);
        }
        $row = ctype_digit($idOrCode)
            ? Db::name('products')->where('id', (int) $idOrCode)->find()
            : Db::name('products')->where('code', $idOrCode)->find();
        if (!$row) {
            throw new ApiException('NOT_FOUND', '商品不存在', 404, 40402);
        }

        return $row;
    }
}
