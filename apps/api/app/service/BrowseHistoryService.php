<?php

declare(strict_types=1);

namespace app\service;

use app\support\ApiException;
use think\facade\Db;

/**
 * M26 瀏覽足跡：登錄後打開詳情寫入；同一商品只保留最近一次；最多 50 條。
 */
class BrowseHistoryService
{
    public const MAX_ITEMS = 50;

    private static bool $schemaReady = false;

    public function touch(int $memberId, int $productId): void
    {
        if ($memberId <= 0 || $productId <= 0) {
            return;
        }
        $this->ensureSchema();
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
        $this->prune($memberId);
    }

    public function touchByCode(int $memberId, string $idOrCode): void
    {
        $this->ensureSchema();
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
    public function list(int $memberId, int $limit = self::MAX_ITEMS): array
    {
        $this->ensureSchema();
        $limit = max(1, min(self::MAX_ITEMS, $limit));
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
        $this->ensureSchema();
        Db::name('browse_history')->where('member_id', $memberId)->delete();

        return ['cleared' => true];
    }

    public function remove(int $memberId, string $idOrCode): array
    {
        $this->ensureSchema();
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
        $this->ensureSchema();
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

    /** 生產庫若未跑 m26，列表會 500；冪等建表。 */
    private function ensureSchema(): void
    {
        if (self::$schemaReady) {
            return;
        }
        $prefix = (string) (Db::getConnection()->getConfig('prefix') ?: '');
        $table = $prefix . 'browse_history';
        try {
            Db::execute(
                "CREATE TABLE IF NOT EXISTS `{$table}` (
                  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                  `member_id` BIGINT UNSIGNED NOT NULL,
                  `product_id` BIGINT UNSIGNED NOT NULL,
                  `viewed_at` DATETIME NOT NULL,
                  `created_at` DATETIME NOT NULL,
                  UNIQUE KEY `uk_browse_member_product` (`member_id`, `product_id`),
                  KEY `idx_browse_member_viewed` (`member_id`, `viewed_at`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            );
            self::$schemaReady = true;
        } catch (\Throwable $e) {
            throw new ApiException(
                'SERVER',
                '足跡表未就緒，請執行 m26 browse_history 遷移',
                500,
                50026,
            );
        }
    }

    /** 超出上限時刪最舊記錄，只留最近 MAX_ITEMS 條。 */
    private function prune(int $memberId): void
    {
        $ids = Db::name('browse_history')
            ->where('member_id', $memberId)
            ->order('viewed_at', 'desc')
            ->order('id', 'desc')
            ->column('id');
        if (count($ids) <= self::MAX_ITEMS) {
            return;
        }
        $drop = array_slice($ids, self::MAX_ITEMS);
        if ($drop) {
            Db::name('browse_history')->whereIn('id', $drop)->delete();
        }
    }
}
