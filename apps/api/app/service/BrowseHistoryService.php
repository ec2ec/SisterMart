<?php

declare(strict_types=1);

namespace app\service;

use app\support\ApiException;
use think\facade\Db;

/**
 * M26 瀏覽足跡：登錄後打開詳情寫入；同一商品只保留最近一次。
 */
class BrowseHistoryService
{
    public function touch(int $memberId, int $productId): void
    {
        if ($memberId <= 0 || $productId <= 0) {
            return;
        }
        $product = Db::name('products')->where('id', $productId)->find();
        if (!$product) {
            return;
        }
        $now = date('Y-m-d H:i:s');
        // 刪後重插，保證同秒內再次瀏覽仍靠更大 id 排到最前
        Db::name('browse_history')
            ->where(['member_id' => $memberId, 'product_id' => $productId])
            ->delete();
        Db::name('browse_history')->insert([
            'member_id' => $memberId,
            'product_id' => $productId,
            'viewed_at' => $now,
            'created_at' => $now,
        ]);
    }

    public function touchByCode(int $memberId, string $idOrCode): void
    {
        $row = ctype_digit($idOrCode)
            ? Db::name('products')->where('id', (int) $idOrCode)->find()
            : Db::name('products')->where('code', $idOrCode)->find();
        if (!$row) {
            return;
        }
        $this->touch($memberId, (int) $row['id']);
    }

    /**
     * @return array{items: list<array<string,mixed>>}
     */
    public function list(int $memberId, int $limit = 50): array
    {
        $limit = max(1, min(100, $limit));
        $rows = Db::name('browse_history')
            ->where('member_id', $memberId)
            ->order('viewed_at', 'desc')
            ->order('id', 'desc')
            ->limit($limit)
            ->select()
            ->toArray();
        if (!$rows) {
            return ['items' => []];
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
        foreach ($rows as $r) {
            $p = $byId[(int) $r['product_id']] ?? null;
            if (!$p) {
                continue;
            }
            $card = $catalog->presentCardPublic($p, $mode);
            $card['viewed_at'] = $r['viewed_at'];
            $card['invalid'] = (int) ($p['status'] ?? 0) !== 1;
            $items[] = $card;
        }

        return ['items' => $items];
    }

    public function clear(int $memberId): array
    {
        Db::name('browse_history')->where('member_id', $memberId)->delete();

        return ['cleared' => true];
    }

    public function remove(int $memberId, string $idOrCode): array
    {
        $row = ctype_digit($idOrCode)
            ? Db::name('products')->where('id', (int) $idOrCode)->find()
            : Db::name('products')->where('code', $idOrCode)->find();
        if (!$row) {
            throw new ApiException('NOT_FOUND', '商品不存在', 404, 40402);
        }
        Db::name('browse_history')
            ->where(['member_id' => $memberId, 'product_id' => (int) $row['id']])
            ->delete();

        return ['removed' => true];
    }

    /**
     * 最近瀏覽過的分類 ID（用於猜你喜歡），新→舊去重。
     *
     * @return list<int>
     */
    public function recentCategoryIds(int $memberId, int $limit = 5): array
    {
        $rows = Db::name('browse_history')
            ->alias('b')
            ->join('products p', 'p.id = b.product_id')
            ->where('b.member_id', $memberId)
            ->order('b.viewed_at', 'desc')
            ->limit(40)
            ->field('p.category_id')
            ->select()
            ->toArray();
        $out = [];
        foreach ($rows as $r) {
            $cid = (int) ($r['category_id'] ?? 0);
            if ($cid > 0 && !in_array($cid, $out, true)) {
                $out[] = $cid;
            }
            if (count($out) >= $limit) {
                break;
            }
        }

        return $out;
    }
}
