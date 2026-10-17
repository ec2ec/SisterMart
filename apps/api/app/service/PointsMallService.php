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
}
