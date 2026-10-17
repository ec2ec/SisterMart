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
    public function sessions(): array
    {
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
        $row = $this->findSession($id);

        return array_merge($this->presentSession($row), ['mock' => false]);
    }

    public function product(string $productCode): array
    {
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
            'sold' => (int) ($row['sold'] ?? 0),
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
}
