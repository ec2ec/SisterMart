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
    private static bool $schemaReady = false;

    public function list(): array
    {
        $this->ensureSchema();
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
        $this->ensureSchema();
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
        $this->ensureSchema();
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
        $this->ensureSchema();
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
        $this->ensureSchema();
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
        $this->ensureSchema();
        $rows = Db::name('points_mall_items')->order('id', 'desc')->select()->toArray();

        return [
            'items' => array_map(fn ($r) => $this->presentAdmin($r), $rows),
        ];
    }

    public function saveAdmin(array $body, ?int $id = null): array
    {
        $this->ensureSchema();
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
        $this->ensureSchema();
        $row = Db::name('points_mall_items')->where('id', $id)->find();
        if (!$row) {
            throw new ApiException('NOT_FOUND', '積分商品不存在', 404, 40482);
        }
        Db::name('points_mall_items')->where('id', $id)->delete();

        return ['id' => $id, 'deleted' => true];
    }

    /**
     * 生產庫若未跑 m12，列表會 500（表不存在 →「服務暫時不可用」）。
     * 冪等建表；若表為空則種子 pm1/pm2（依賴商品 p3/p4）。
     */
    private function ensureSchema(): void
    {
        if (self::$schemaReady) {
            return;
        }
        $prefix = (string) (Db::getConnection()->getConfig('prefix') ?: '');
        $table = $prefix . 'points_mall_items';
        try {
            Db::execute(
                "CREATE TABLE IF NOT EXISTS `{$table}` (
                  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                  `code` VARCHAR(32) NOT NULL,
                  `product_id` BIGINT UNSIGNED NOT NULL,
                  `product_code` VARCHAR(64) NOT NULL,
                  `sku_id` BIGINT UNSIGNED NOT NULL,
                  `points_cost` INT NOT NULL,
                  `stock` INT NOT NULL DEFAULT 0,
                  `sold` INT NOT NULL DEFAULT 0,
                  `status` TINYINT NOT NULL DEFAULT 1,
                  `image_url` VARCHAR(512) NULL,
                  `name_zh` VARCHAR(255) NOT NULL,
                  `name_en` VARCHAR(255) NOT NULL,
                  `detail_zh` TEXT NULL,
                  `detail_en` TEXT NULL,
                  `created_at` DATETIME NOT NULL,
                  `updated_at` DATETIME NOT NULL,
                  UNIQUE KEY `uk_points_mall_items_code` (`code`),
                  KEY `idx_points_mall_items_product` (`product_code`),
                  KEY `idx_points_mall_items_status` (`status`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            );
            $this->ensureOrdersColumn($prefix);
            $this->ensureSeedIfEmpty();
            self::$schemaReady = true;
        } catch (ApiException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new ApiException(
                'SERVER',
                '積分商城表未就緒，請執行 m12_points_mall.sql',
                500,
                50012,
            );
        }
    }

    private function ensureOrdersColumn(string $prefix): void
    {
        $orders = $prefix . 'orders';
        try {
            $rows = Db::query("SHOW COLUMNS FROM `{$orders}` LIKE 'points_mall_item_id'");
            if ($rows) {
                return;
            }
            Db::execute(
                "ALTER TABLE `{$orders}` ADD COLUMN `points_mall_item_id` BIGINT UNSIGNED NULL AFTER `seckill_item_id`",
            );
        } catch (\Throwable $e) {
            // 兌換下單時再暴露；列表不依賴此欄
        }
    }

    /** 表為空時寫入 pm1/pm2；商品/SKU 缺失則跳過。 */
    private function ensureSeedIfEmpty(): void
    {
        $count = (int) Db::name('points_mall_items')->count();
        if ($count > 0) {
            return;
        }
        $catalog = [
            'pm1' => [
                'product' => 'p3',
                'points' => 800,
                'stock' => 20,
                'name_zh' => '四葉草手鏈（積分兌）',
                'name_en' => 'Clover (Points)',
                'detail_zh' => '積分商城兌換商品。結算扣積分；運費按現金規則（未滿包郵門檻需支付運費）。',
                'detail_en' => 'Points-mall item. Redeem with points; shipping follows cash rules.',
            ],
            'pm2' => [
                'product' => 'p4',
                'points' => 1200,
                'stock' => 8,
                'name_zh' => '紅心手鏈（積分兌）',
                'name_en' => 'Heart (Points)',
                'detail_zh' => '積分商城兌換商品。結算扣積分；運費按現金規則（未滿包郵門檻需支付運費）。',
                'detail_en' => 'Points-mall item. Redeem with points; shipping follows cash rules.',
            ],
        ];
        $now = date('Y-m-d H:i:s');
        foreach ($catalog as $code => $cfg) {
            $product = Db::name('products')->where('code', $cfg['product'])->where('status', 1)->find();
            if (!$product) {
                continue;
            }
            $sku = Db::name('skus')->where('product_id', $product['id'])->where('status', 1)->order('id', 'asc')->find();
            if (!$sku) {
                continue;
            }
            Db::name('points_mall_items')->insert([
                'code' => $code,
                'product_id' => (int) $product['id'],
                'product_code' => $cfg['product'],
                'sku_id' => (int) $sku['id'],
                'points_cost' => $cfg['points'],
                'stock' => $cfg['stock'],
                'sold' => 0,
                'status' => 1,
                'image_url' => $sku['image_url'] ?: ($product['cover_url'] ?? null),
                'name_zh' => $cfg['name_zh'],
                'name_en' => $cfg['name_en'],
                'detail_zh' => $cfg['detail_zh'],
                'detail_en' => $cfg['detail_en'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
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
