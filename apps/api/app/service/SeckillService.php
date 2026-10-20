<?php

declare(strict_types=1);

namespace app\service;

use app\support\ApiException;
use think\facade\Db;

/**
 * M11 秒殺活動庫存（真實場次 / 扣減；支付仍走 mock）。
 */
class SeckillService
{
    private static bool $schemaReady = false;

    public function sessions(): array
    {
        $this->ensureSchema();
        $now = date('Y-m-d H:i:s');
        $rows = Db::name('seckill_sessions')
            ->where('ends_at', '>', $now)
            ->order('starts_at', 'asc')
            ->select()
            ->toArray();
        $items = [];
        foreach ($rows as $row) {
            $items[] = $this->presentSession($row);
        }

        return ['items' => $items, 'mock' => false];
    }

    public function detail(string $id): array
    {
        $this->ensureSchema();
        $row = $this->findSession($id);

        return array_merge($this->presentSession($row), ['mock' => false]);
    }

    public function product(string $productCode): array
    {
        $this->ensureSchema();
        $now = date('Y-m-d H:i:s');
        $item = Db::name('seckill_items')->alias('i')
            ->join('seckill_sessions s', 's.id = i.session_id')
            ->where('i.product_code', $productCode)
            ->where('s.starts_at', '<=', $now)
            ->where('s.ends_at', '>', $now)
            ->where('i.stock', '>', 0)
            ->field('i.*, s.code as session_code, s.starts_at, s.ends_at')
            ->order('s.starts_at', 'asc')
            ->find();
        if (!$item) {
            return ['in_seckill' => false, 'mock' => false];
        }

        return array_merge($this->presentItem($item), [
            'session_id' => (string) $item['session_code'],
            'session_status' => 'live',
            'ends_at' => $item['ends_at'],
            'mock' => false,
        ]);
    }

    /**
     * 報價 / 組行：不持鎖，僅校驗場次與庫存。
     *
     * @return array{item: array, session: array}
     */
    public function assertLiveItem(int $seckillItemId, int $qty = 1, bool $forUpdate = false): array
    {
        $itemQ = Db::name('seckill_items')->where('id', $seckillItemId);
        $item = $forUpdate ? $itemQ->lock(true)->find() : $itemQ->find();
        if (!$item) {
            throw new ApiException('NOT_FOUND', '秒殺商品不存在', 404, 40481);
        }
        $sessQ = Db::name('seckill_sessions')->where('id', $item['session_id']);
        $session = $forUpdate ? $sessQ->lock(true)->find() : $sessQ->find();
        if (!$session) {
            throw new ApiException('NOT_FOUND', '秒殺場次不存在', 404, 40480);
        }
        $status = $this->statusOf($session);
        if ($status !== 'live') {
            throw new ApiException('SECKILL_NOT_LIVE', '秒殺場次未開始或已結束', 422, 42280);
        }
        if ((int) $item['stock'] < $qty) {
            throw new ApiException('OUT_OF_STOCK', '秒殺庫存不足', 422, 42281);
        }

        return ['item' => $item, 'session' => $session];
    }

    public function deduct(int $seckillItemId, int $qty, string $now): void
    {
        $affected = Db::name('seckill_items')
            ->where('id', $seckillItemId)
            ->where('stock', '>=', $qty)
            ->dec('stock', $qty)
            ->inc('sold', $qty)
            ->update(['updated_at' => $now]);
        if (!$affected) {
            throw new ApiException('OUT_OF_STOCK', '秒殺庫存不足', 422, 42281);
        }
    }

    public function release(int $seckillItemId, int $qty, string $now): void
    {
        $row = Db::name('seckill_items')->where('id', $seckillItemId)->find();
        if (!$row) {
            return;
        }
        $sold = max(0, (int) $row['sold'] - $qty);
        Db::name('seckill_items')->where('id', $seckillItemId)->update([
            'stock' => (int) $row['stock'] + $qty,
            'sold' => $sold,
            'updated_at' => $now,
        ]);
    }

    /** 後台：全部場次（含已結束），數值 id 便於編輯。 */
    public function adminList(): array
    {
        $this->ensureSchema();
        $rows = Db::name('seckill_sessions')->order('starts_at', 'desc')->select()->toArray();
        $items = [];
        foreach ($rows as $row) {
            $items[] = $this->presentAdminSession($row);
        }

        return ['items' => $items];
    }

    public function adminRead(int $id): array
    {
        $this->ensureSchema();
        $row = Db::name('seckill_sessions')->where('id', $id)->find();
        if (!$row) {
            throw new ApiException('NOT_FOUND', '秒殺場次不存在', 404, 40480);
        }

        return $this->presentAdminSession($row);
    }

    /**
     * @param array<string, mixed> $body
     */
    public function adminCreate(array $body): array
    {
        $this->ensureSchema();
        $parsed = $this->parseSessionBody($body, requireCode: true);
        $now = date('Y-m-d H:i:s');
        $dup = Db::name('seckill_sessions')->where('code', $parsed['code'])->find();
        if ($dup) {
            throw new ApiException('INVALID', '場次代碼已存在', 422, 42201);
        }

        $id = 0;
        Db::transaction(function () use ($parsed, $now, &$id) {
            $id = (int) Db::name('seckill_sessions')->insertGetId([
                'code' => $parsed['code'],
                'name_zh' => $parsed['name_zh'],
                'name_en' => $parsed['name_en'],
                'starts_at' => $parsed['starts_at'],
                'ends_at' => $parsed['ends_at'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $this->syncItems($id, $parsed['items'], $now, replace: true);
        });

        return $this->adminRead($id);
    }

    /**
     * @param array<string, mixed> $body
     */
    public function adminUpdate(int $id, array $body): array
    {
        $this->ensureSchema();
        $row = Db::name('seckill_sessions')->where('id', $id)->find();
        if (!$row) {
            throw new ApiException('NOT_FOUND', '秒殺場次不存在', 404, 40480);
        }
        $parsed = $this->parseSessionBody($body, requireCode: false);
        $now = date('Y-m-d H:i:s');

        Db::transaction(function () use ($id, $row, $parsed, $now) {
            $patch = ['updated_at' => $now];
            if ($parsed['code'] !== null && $parsed['code'] !== (string) $row['code']) {
                $dup = Db::name('seckill_sessions')
                    ->where('code', $parsed['code'])
                    ->where('id', '<>', $id)
                    ->find();
                if ($dup) {
                    throw new ApiException('INVALID', '場次代碼已存在', 422, 42201);
                }
                $patch['code'] = $parsed['code'];
            }
            if ($parsed['name_zh'] !== null) {
                $patch['name_zh'] = $parsed['name_zh'];
            }
            if ($parsed['name_en'] !== null) {
                $patch['name_en'] = $parsed['name_en'];
            }
            if ($parsed['starts_at'] !== null) {
                $patch['starts_at'] = $parsed['starts_at'];
            }
            if ($parsed['ends_at'] !== null) {
                $patch['ends_at'] = $parsed['ends_at'];
            }
            $starts = (string) ($patch['starts_at'] ?? $row['starts_at']);
            $ends = (string) ($patch['ends_at'] ?? $row['ends_at']);
            if (strtotime($ends) <= strtotime($starts)) {
                throw new ApiException('INVALID', '結束時間須晚於開始時間', 422, 42201);
            }
            Db::name('seckill_sessions')->where('id', $id)->update($patch);
            if ($parsed['items'] !== null) {
                $this->syncItems($id, $parsed['items'], $now, replace: true);
            }
        });

        return $this->adminRead($id);
    }

    /**
     * @param array<string, mixed> $body
     * @return array{code: ?string, name_zh: ?string, name_en: ?string, starts_at: ?string, ends_at: ?string, items: ?list<array<string, mixed>>}
     */
    private function parseSessionBody(array $body, bool $requireCode): array
    {
        $code = array_key_exists('code', $body) ? trim((string) $body['code']) : null;
        if ($requireCode) {
            if ($code === null || $code === '' || !preg_match('/^[a-zA-Z0-9_-]{2,64}$/', $code)) {
                throw new ApiException('INVALID', '場次代碼格式無效', 422, 42201);
            }
        } elseif ($code !== null && $code !== '' && !preg_match('/^[a-zA-Z0-9_-]{2,64}$/', $code)) {
            throw new ApiException('INVALID', '場次代碼格式無效', 422, 42201);
        } elseif ($code === '') {
            $code = null;
        }

        $nameZh = null;
        $nameEn = null;
        if (isset($body['name']) && is_array($body['name'])) {
            $nameZh = trim((string) ($body['name']['zh-HK'] ?? $body['name']['zh'] ?? ''));
            $nameEn = trim((string) ($body['name']['en'] ?? ''));
        }
        if (array_key_exists('name_zh', $body)) {
            $nameZh = trim((string) $body['name_zh']);
        }
        if (array_key_exists('name_en', $body)) {
            $nameEn = trim((string) $body['name_en']);
        }
        if ($requireCode && ($nameZh === null || $nameZh === '')) {
            throw new ApiException('INVALID', '請填寫場次中文名', 422, 42201);
        }

        $starts = array_key_exists('starts_at', $body) ? trim((string) $body['starts_at']) : null;
        $ends = array_key_exists('ends_at', $body) ? trim((string) $body['ends_at']) : null;
        if ($requireCode) {
            if ($starts === null || $starts === '' || $ends === null || $ends === '') {
                throw new ApiException('INVALID', '請填寫開始/結束時間', 422, 42201);
            }
            if (strtotime($ends) <= strtotime($starts)) {
                throw new ApiException('INVALID', '結束時間須晚於開始時間', 422, 42201);
            }
        }

        $items = null;
        if (array_key_exists('items', $body)) {
            if (!is_array($body['items'])) {
                throw new ApiException('INVALID', 'items 須為陣列', 422, 42201);
            }
            $items = array_values($body['items']);
        }

        return [
            'code' => $code,
            'name_zh' => $nameZh,
            'name_en' => $nameEn !== null ? ($nameEn !== '' ? $nameEn : ($nameZh ?? '')) : null,
            'starts_at' => $starts !== '' ? $starts : null,
            'ends_at' => $ends !== '' ? $ends : null,
            'items' => $items,
        ];
    }

    /**
     * @param list<array<string, mixed>> $items
     */
    private function syncItems(int $sessionId, array $items, string $now, bool $replace): void
    {
        $keepIds = [];
        foreach ($items as $raw) {
            if (!is_array($raw)) {
                continue;
            }
            $itemId = isset($raw['id']) ? (int) $raw['id'] : 0;
            $productCode = trim((string) ($raw['product_code'] ?? $raw['product_id'] ?? ''));
            $skuId = (int) ($raw['sku_id'] ?? 0);
            if ($productCode === '' || $skuId <= 0) {
                throw new ApiException('INVALID', '秒殺商品須含 product_code 與 sku_id', 422, 42201);
            }
            $product = Db::name('products')->where('code', $productCode)->find();
            if (!$product) {
                throw new ApiException('INVALID', '商品不存在：' . $productCode, 422, 42201);
            }
            $sku = Db::name('skus')->where('id', $skuId)->where('product_id', $product['id'])->find();
            if (!$sku) {
                throw new ApiException('INVALID', 'SKU 不屬於該商品', 422, 42201);
            }
            $names = Db::name('product_i18n')->where('product_id', $product['id'])->select()->toArray();
            $zh = $productCode;
            $en = $productCode;
            foreach ($names as $n) {
                if (($n['locale'] ?? '') === 'en') {
                    $en = (string) ($n['name'] ?: $en);
                } else {
                    $zh = (string) ($n['name'] ?: $zh);
                }
            }
            $payload = [
                'product_id' => (int) $product['id'],
                'product_code' => $productCode,
                'sku_id' => $skuId,
                'seckill_price' => round((float) ($raw['seckill_price'] ?? 0), 2),
                'origin_price' => round((float) ($raw['origin_price'] ?? $sku['compare_at'] ?? $sku['price'] ?? 0), 2),
                'stock' => max(0, (int) ($raw['stock'] ?? 0)),
                'image_url' => (string) ($raw['image_url'] ?? '')
                    ?: (string) ($sku['image_url'] ?? '')
                    ?: (string) ($product['cover_url'] ?? ''),
                'name_zh' => trim((string) ($raw['name_zh'] ?? $zh)),
                'name_en' => trim((string) ($raw['name_en'] ?? $en)),
                'updated_at' => $now,
            ];
            if ($payload['seckill_price'] <= 0) {
                throw new ApiException('INVALID', '秒殺價須大於 0', 422, 42201);
            }
            if ($itemId > 0) {
                $existing = Db::name('seckill_items')
                    ->where('id', $itemId)
                    ->where('session_id', $sessionId)
                    ->find();
                if (!$existing) {
                    throw new ApiException('INVALID', '秒殺商品行不存在', 422, 42201);
                }
                Db::name('seckill_items')->where('id', $itemId)->update($payload);
                $keepIds[] = $itemId;
            } else {
                $payload['session_id'] = $sessionId;
                $payload['sold'] = 0;
                $payload['created_at'] = $now;
                $keepIds[] = (int) Db::name('seckill_items')->insertGetId($payload);
            }
        }
        if ($replace) {
            $q = Db::name('seckill_items')->where('session_id', $sessionId);
            if ($keepIds !== []) {
                $q->whereNotIn('id', $keepIds);
            }
            $q->delete();
        }
    }

    private function presentAdminSession(array $row): array
    {
        $products = Db::name('seckill_items')
            ->where('session_id', $row['id'])
            ->order('id', 'asc')
            ->select()
            ->toArray();

        return [
            'id' => (int) $row['id'],
            'code' => (string) $row['code'],
            'name' => ['zh-HK' => $row['name_zh'], 'en' => $row['name_en']],
            'status' => $this->statusOf($row),
            'starts_at' => $row['starts_at'],
            'ends_at' => $row['ends_at'],
            'items' => array_map(function (array $p) {
                return [
                    'id' => (int) $p['id'],
                    'product_id' => (int) $p['product_id'],
                    'product_code' => (string) $p['product_code'],
                    'sku_id' => (int) $p['sku_id'],
                    'name' => ['zh-HK' => $p['name_zh'], 'en' => $p['name_en']],
                    'image' => (string) ($p['image_url'] ?: ''),
                    'seckill_price' => (float) $p['seckill_price'],
                    'origin_price' => (float) $p['origin_price'],
                    'stock' => (int) $p['stock'],
                    'sold' => (int) ($p['sold'] ?? 0),
                ];
            }, $products),
        ];
    }

    private function findSession(string $id): array
    {
        $row = is_numeric($id)
            ? Db::name('seckill_sessions')->where('id', (int) $id)->find()
            : Db::name('seckill_sessions')->where('code', $id)->find();
        if (!$row) {
            throw new ApiException('NOT_FOUND', '秒殺場次不存在', 404, 40480);
        }

        return $row;
    }

    private function presentSession(array $row): array
    {
        $products = Db::name('seckill_items')
            ->where('session_id', $row['id'])
            ->order('id', 'asc')
            ->select()
            ->toArray();

        return [
            'id' => (string) $row['code'],
            'name' => ['zh-HK' => $row['name_zh'], 'en' => $row['name_en']],
            'status' => $this->statusOf($row),
            'starts_at' => $row['starts_at'],
            'ends_at' => $row['ends_at'],
            'products' => array_map(fn ($p) => $this->presentItem($p), $products),
        ];
    }

    private function presentItem(array $row): array
    {
        $product = Db::name('products')->where('id', (int) $row['product_id'])->find();
        $soldLabel = $product
            ? SoldDisplay::labelFromProduct($product)
            : SoldDisplay::label((int) ($row['sold'] ?? 0));

        return [
            'id' => 'si-' . $row['id'],
            'seckill_item_id' => (int) $row['id'],
            'product_id' => (string) $row['product_code'],
            'sku_id' => (int) $row['sku_id'],
            'name' => ['zh-HK' => $row['name_zh'], 'en' => $row['name_en']],
            'image' => (string) ($row['image_url'] ?: ''),
            'seckill_price' => (float) $row['seckill_price'],
            'origin_price' => (float) $row['origin_price'],
            'stock' => (int) $row['stock'],
            /** 秒殺場次真實已售（後台只讀） */
            'seckill_sold' => (int) ($row['sold'] ?? 0),
            /** H5 展示：商品真實銷售 + 疊加，再套 50+ */
            'sold' => $soldLabel,
            'in_seckill' => true,
        ];
    }

    private function statusOf(array $session): string
    {
        $now = time();
        $start = strtotime((string) $session['starts_at']);
        $end = strtotime((string) $session['ends_at']);
        if ($now < $start) {
            return 'upcoming';
        }
        if ($now >= $end) {
            return 'ended';
        }

        return 'live';
    }

    /** 生產庫若未跑 m11，列表會空／500；冪等建表。 */
    private function ensureSchema(): void
    {
        if (self::$schemaReady) {
            return;
        }
        $prefix = (string) (Db::getConnection()->getConfig('prefix') ?: '');
        $sessions = $prefix . 'seckill_sessions';
        $items = $prefix . 'seckill_items';
        try {
            Db::execute(
                "CREATE TABLE IF NOT EXISTS `{$sessions}` (
                  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                  `code` VARCHAR(32) NOT NULL,
                  `name_zh` VARCHAR(128) NOT NULL,
                  `name_en` VARCHAR(128) NOT NULL,
                  `starts_at` DATETIME NOT NULL,
                  `ends_at` DATETIME NOT NULL,
                  `created_at` DATETIME NOT NULL,
                  `updated_at` DATETIME NOT NULL,
                  UNIQUE KEY `uk_seckill_sessions_code` (`code`),
                  KEY `idx_seckill_sessions_window` (`starts_at`, `ends_at`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            );
            Db::execute(
                "CREATE TABLE IF NOT EXISTS `{$items}` (
                  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                  `session_id` BIGINT UNSIGNED NOT NULL,
                  `product_id` BIGINT UNSIGNED NOT NULL,
                  `product_code` VARCHAR(64) NOT NULL,
                  `sku_id` BIGINT UNSIGNED NOT NULL,
                  `seckill_price` DECIMAL(12,2) NOT NULL,
                  `origin_price` DECIMAL(12,2) NOT NULL,
                  `stock` INT NOT NULL DEFAULT 0,
                  `sold` INT NOT NULL DEFAULT 0,
                  `image_url` VARCHAR(512) NULL,
                  `name_zh` VARCHAR(255) NOT NULL,
                  `name_en` VARCHAR(255) NOT NULL,
                  `created_at` DATETIME NOT NULL,
                  `updated_at` DATETIME NOT NULL,
                  KEY `idx_seckill_items_session` (`session_id`),
                  KEY `idx_seckill_items_product_code` (`product_code`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            );
            self::$schemaReady = true;
        } catch (\Throwable $e) {
            throw new ApiException(
                'SERVER',
                '秒殺表未就緒，請執行 m11_seckill.sql',
                500,
                50011,
            );
        }
    }
}
