<?php

declare(strict_types=1);

namespace app\service;

use app\support\ApiException;
use think\facade\Db;

/**
 * M12 積分商城（真實扣積分 + 活動/SKU 庫存；商品現金額為 0，運費可走 mock 現金）。
 */
class PointsMallService
{
    public function list(): array
    {
        $rows = Db::name('points_mall_items')
            ->where('status', 1)
            ->where('stock', '>', 0)
            ->order('id', 'asc')
            ->select()
            ->toArray();

        return [
            'items' => array_map(fn ($r) => $this->present($r), $rows),
            'mock' => false,
        ];
    }

    public function detail(string $id): array
    {
        $row = $this->findItem($id);
        if ((int) $row['status'] !== 1) {
            throw new ApiException('NOT_FOUND', '積分商品不存在', 404, 40482);
        }

        return array_merge($this->present($row), [
            'mock' => false,
            'detail' => [
                'zh-HK' => (string) ($row['detail_zh'] ?: ''),
                'en' => (string) ($row['detail_en'] ?: ''),
            ],
        ]);
    }

    /**
     * @return array{item: array}
     */
    public function assertAvailable(int $itemId, int $qty = 1, bool $forUpdate = false): array
    {
        $q = Db::name('points_mall_items')->where('id', $itemId)->where('status', 1);
        $item = $forUpdate ? $q->lock(true)->find() : $q->find();
        if (!$item) {
            throw new ApiException('NOT_FOUND', '積分商品不存在', 404, 40482);
        }
        if ((int) $item['stock'] < $qty) {
            throw new ApiException('OUT_OF_STOCK', '積分商品庫存不足', 422, 42282);
        }

        return ['item' => $item];
    }

    public function deduct(int $itemId, int $qty, string $now): void
    {
        $affected = Db::name('points_mall_items')
            ->where('id', $itemId)
            ->where('stock', '>=', $qty)
            ->dec('stock', $qty)
            ->inc('sold', $qty)
            ->update(['updated_at' => $now]);
        if (!$affected) {
            throw new ApiException('OUT_OF_STOCK', '積分商品庫存不足', 422, 42282);
        }
    }

    public function release(int $itemId, int $qty, string $now): void
    {
        $row = Db::name('points_mall_items')->where('id', $itemId)->find();
        if (!$row) {
            return;
        }
        Db::name('points_mall_items')->where('id', $itemId)->update([
            'stock' => (int) $row['stock'] + $qty,
            'sold' => max(0, (int) $row['sold'] - $qty),
            'updated_at' => $now,
        ]);
    }

    public function listAdmin(): array
    {
        $rows = Db::name('points_mall_items')->order('id', 'desc')->select()->toArray();

        return [
            'items' => array_map(fn ($r) => $this->presentAdmin($r), $rows),
        ];
    }

    public function saveAdmin(array $body, ?int $id = null): array
    {
        $code = trim((string) ($body['code'] ?? ''));
        $productCode = trim((string) ($body['product_code'] ?? $body['product_id'] ?? ''));
        $skuId = (int) ($body['sku_id'] ?? 0);
        $points = (int) ($body['points_cost'] ?? 0);
        $stock = (int) ($body['stock'] ?? 0);
        $nameZh = trim((string) ($body['name']['zh-HK'] ?? $body['name_zh'] ?? ''));
        $nameEn = trim((string) ($body['name']['en'] ?? $body['name_en'] ?? ''));
        if ($productCode === '' || $skuId <= 0) {
            throw new ApiException('INVALID', '請指定商品與 SKU', 422, 42201);
        }
        if ($points <= 0) {
            throw new ApiException('INVALID', '積分價須大於 0', 422, 42201);
        }
        if ($nameZh === '' && $nameEn === '') {
            throw new ApiException('INVALID', '請填寫名稱', 422, 42201);
        }
        $product = Db::name('products')->where('code', $productCode)->find();
        if (!$product) {
            throw new ApiException('NOT_FOUND', '商品不存在', 404, 40402);
        }
        $sku = Db::name('skus')->where('id', $skuId)->where('product_id', (int) $product['id'])->find();
        if (!$sku) {
            throw new ApiException('INVALID', 'SKU 不屬於該商品', 422, 42201);
        }
        $now = date('Y-m-d H:i:s');
        if ($id) {
            $row = Db::name('points_mall_items')->where('id', $id)->find();
            if (!$row) {
                throw new ApiException('NOT_FOUND', '積分商品不存在', 404, 40482);
            }
            if ($code === '') {
                $code = (string) $row['code'];
            }
        } else {
            if ($code === '') {
                $code = 'pm_' . bin2hex(random_bytes(3));
            }
        }
        $dup = Db::name('points_mall_items')->where('code', $code);
        if ($id) {
            $dup->where('id', '<>', $id);
        }
        if ($dup->find()) {
            throw new ApiException('INVALID', 'code 已存在', 422, 42201);
        }
        $data = [
            'code' => $code,
            'product_id' => (int) $product['id'],
            'product_code' => (string) $product['code'],
            'sku_id' => $skuId,
            'points_cost' => $points,
            'stock' => max(0, $stock),
            'status' => (int) ($body['status'] ?? 1) === 0 ? 0 : 1,
            'image_url' => trim((string) ($body['image_url'] ?? $body['image'] ?? '')) ?: null,
            'name_zh' => $nameZh !== '' ? $nameZh : $nameEn,
            'name_en' => $nameEn !== '' ? $nameEn : $nameZh,
            'detail_zh' => trim((string) ($body['detail']['zh-HK'] ?? $body['detail_zh'] ?? '')) ?: null,
            'detail_en' => trim((string) ($body['detail']['en'] ?? $body['detail_en'] ?? '')) ?: null,
            'updated_at' => $now,
        ];
        if ($id) {
            Db::name('points_mall_items')->where('id', $id)->update($data);
        } else {
            $data['sold'] = 0;
            $data['created_at'] = $now;
            $id = (int) Db::name('points_mall_items')->insertGetId($data);
        }
        $row = Db::name('points_mall_items')->where('id', $id)->find();

        return $this->presentAdmin($row);
    }

    public function deleteAdmin(int $id): array
    {
        $row = Db::name('points_mall_items')->where('id', $id)->find();
        if (!$row) {
            throw new ApiException('NOT_FOUND', '積分商品不存在', 404, 40482);
        }
        Db::name('points_mall_items')->where('id', $id)->delete();

        return ['id' => $id, 'deleted' => true];
    }

    private function findItem(string $id): array
    {
        if (str_starts_with($id, 'pm-') && is_numeric(substr($id, 3))) {
            $row = Db::name('points_mall_items')->where('id', (int) substr($id, 3))->find();
        } elseif (is_numeric($id)) {
            $row = Db::name('points_mall_items')->where('id', (int) $id)->find();
        } else {
            $row = Db::name('points_mall_items')->where('code', $id)->find();
        }
        if (!$row) {
            throw new ApiException('NOT_FOUND', '積分商品不存在', 404, 40482);
        }

        return $row;
    }

    private function present(array $row): array
    {
        return [
            'id' => (string) $row['code'],
            'points_mall_item_id' => (int) $row['id'],
            'product_id' => (string) $row['product_code'],
            'sku_id' => (int) $row['sku_id'],
            'name' => ['zh-HK' => $row['name_zh'], 'en' => $row['name_en']],
            'image' => (string) ($row['image_url'] ?: ''),
            'points_cost' => (int) $row['points_cost'],
            'stock' => (int) $row['stock'],
            'sold' => (int) ($row['sold'] ?? 0),
        ];
    }

    private function presentAdmin(array $row): array
    {
        return array_merge($this->present($row), [
            'status' => (int) $row['status'],
            'detail' => [
                'zh-HK' => (string) ($row['detail_zh'] ?: ''),
                'en' => (string) ($row['detail_en'] ?: ''),
            ],
            'created_at' => $row['created_at'] ?? null,
            'updated_at' => $row['updated_at'] ?? null,
        ]);
    }
}
