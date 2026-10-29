<?php

declare(strict_types=1);

namespace app\service;

use app\support\ApiException;
use think\facade\Db;

/**
 * M45 通用活動專區（P0：角標選品 + 註冊天數受眾）。
 */
class CampaignService
{
    public const SOURCES = ['badge', 'manual', 'badge_and_manual'];

    /** 購買資格：註冊天數（與彈窗 7 天分開） */
    public const NEWCOMER_BUY_DAYS = 30;

    public function ensureSchema(): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;
        Db::execute(<<<'SQL'
CREATE TABLE IF NOT EXISTS campaigns (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(64) NOT NULL,
  title_zh VARCHAR(128) NOT NULL DEFAULT '',
  title_en VARCHAR(128) NOT NULL DEFAULT '',
  status TINYINT NOT NULL DEFAULT 1,
  starts_at DATETIME NULL,
  ends_at DATETIME NULL,
  audience_json JSON NULL,
  product_source VARCHAR(32) NOT NULL DEFAULT 'badge',
  product_badge VARCHAR(64) NULL,
  banner_image_url VARCHAR(512) NULL,
  intro_html_zh MEDIUMTEXT NULL,
  intro_html_en MEDIUMTEXT NULL,
  show_on_home TINYINT NOT NULL DEFAULT 0,
  home_rail_limit INT NOT NULL DEFAULT 10,
  sort_order INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  UNIQUE KEY uk_campaigns_code (code),
  KEY idx_campaigns_status (status, starts_at, ends_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        Db::execute(<<<'SQL'
CREATE TABLE IF NOT EXISTS campaign_products (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  campaign_id BIGINT UNSIGNED NOT NULL,
  product_id BIGINT UNSIGNED NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  UNIQUE KEY uk_campaign_product (campaign_id, product_id),
  KEY idx_campaign_products_sort (campaign_id, sort_order, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function listAdmin(): array
    {
        $this->ensureSchema();
        $rows = Db::name('campaigns')
            ->order('sort_order', 'asc')
            ->order('id', 'asc')
            ->select()
            ->toArray();

        return ['items' => array_map(fn ($r) => $this->presentAdmin($r), $rows)];
    }

    public function getPublicByCode(string $code): array
    {
        $this->ensureSchema();
        $row = Db::name('campaigns')->where('code', $code)->where('status', 1)->find();
        if (!$row || !$this->inWindow($row)) {
            throw new ApiException('NOT_FOUND', '活動不存在', 404, 40401);
        }

        return ['campaign' => $this->presentPublic($row, false)];
    }

    public function productsPublic(string $code, int $offset = 0, int $limit = 30): array
    {
        $this->ensureSchema();
        $row = Db::name('campaigns')->where('code', $code)->where('status', 1)->find();
        if (!$row || !$this->inWindow($row)) {
            throw new ApiException('NOT_FOUND', '活動不存在', 404, 40401);
        }
        $limit = max(1, min(60, $limit));
        $offset = max(0, $offset);
        $ids = $this->resolveProductIds($row);
        $total = count($ids);
        $slice = array_slice($ids, $offset, $limit);
        $items = $this->loadCards($slice);

        return [
            'campaign' => $this->presentPublic($row, false),
            'items' => $items,
            'total' => $total,
            'offset' => $offset,
            'limit' => $limit,
            'next_offset' => $offset + count($items) < $total ? $offset + count($items) : null,
            'sold_display_mode' => SoldDisplay::mode(),
        ];
    }

    /** 首頁橫滑：最多 home_rail_limit */
    public function railProducts(string $code): array
    {
        $this->ensureSchema();
        $row = Db::name('campaigns')->where('code', $code)->where('status', 1)->find();
        if (!$row || !(int) $row['show_on_home'] || !$this->inWindow($row)) {
            return ['items' => [], 'limit' => 0];
        }
        $limit = max(1, min(30, (int) ($row['home_rail_limit'] ?? 10)));
        $ids = array_slice($this->resolveProductIds($row), 0, $limit);

        return [
            'campaign' => $this->presentPublic($row, false),
            'items' => $this->loadCards($ids),
            'limit' => $limit,
        ];
    }

    public function save(array $body, ?int $id = null): array
    {
        $this->ensureSchema();
        $code = trim((string) ($body['code'] ?? ''));
        $titleZh = trim((string) ($body['title']['zh-HK'] ?? $body['title_zh'] ?? ''));
        $titleEn = trim((string) ($body['title']['en'] ?? $body['title_en'] ?? ''));
        if ($titleZh === '' && $titleEn === '') {
            throw new ApiException('INVALID', '請填寫標題', 422, 42201);
        }
        $source = (string) ($body['product_source'] ?? 'badge');
        if (!in_array($source, self::SOURCES, true)) {
            throw new ApiException('INVALID', '選品來源無效', 422, 42201);
        }
        $badge = trim((string) ($body['product_badge'] ?? ''));
        if (in_array($source, ['badge', 'badge_and_manual'], true) && $badge === '') {
            throw new ApiException('INVALID', '請選擇商品角標', 422, 42201);
        }
        $now = date('Y-m-d H:i:s');
        if ($id) {
            $row = Db::name('campaigns')->where('id', $id)->find();
            if (!$row) {
                throw new ApiException('NOT_FOUND', '活動不存在', 404, 40401);
            }
            if ($code === '') {
                $code = (string) $row['code'];
            }
        } else {
            if ($code === '') {
                $code = 'camp_' . bin2hex(random_bytes(4));
            }
        }
        $dup = Db::name('campaigns')->where('code', $code);
        if ($id) {
            $dup->where('id', '<>', $id);
        }
        if ($dup->find()) {
            throw new ApiException('INVALID', 'code 已存在', 422, 42201);
        }

        $audience = $body['audience'] ?? $body['audience_json'] ?? ['all' => true];
        if (is_string($audience)) {
            $decoded = json_decode($audience, true);
            $audience = is_array($decoded) ? $decoded : ['all' => true];
        }

        $data = [
            'code' => $code,
            'title_zh' => $titleZh !== '' ? $titleZh : $titleEn,
            'title_en' => $titleEn !== '' ? $titleEn : $titleZh,
            'status' => (int) ($body['status'] ?? 1) === 0 ? 0 : 1,
            'starts_at' => $this->nullableDatetime($body['starts_at'] ?? null),
            'ends_at' => $this->nullableDatetime($body['ends_at'] ?? null),
            'audience_json' => json_encode($audience, JSON_UNESCAPED_UNICODE),
            'product_source' => $source,
            'product_badge' => $badge !== '' ? $badge : null,
            'banner_image_url' => ($u = trim((string) ($body['banner_image_url'] ?? ''))) !== '' ? $u : null,
            'intro_html_zh' => (string) ($body['intro_html']['zh-HK'] ?? $body['intro_html_zh'] ?? ''),
            'intro_html_en' => (string) ($body['intro_html']['en'] ?? $body['intro_html_en'] ?? ''),
            'show_on_home' => (int) ($body['show_on_home'] ?? 0) === 1 ? 1 : 0,
            'home_rail_limit' => max(1, min(30, (int) ($body['home_rail_limit'] ?? 10))),
            'sort_order' => (int) ($body['sort_order'] ?? 0),
            'updated_at' => $now,
        ];

        if ($id) {
            Db::name('campaigns')->where('id', $id)->update($data);
        } else {
            $data['created_at'] = $now;
            $id = (int) Db::name('campaigns')->insertGetId($data);
        }

        if (array_key_exists('product_ids', $body) || array_key_exists('product_codes', $body)) {
            $this->replaceManualProducts($id, $body);
        }

        $row = Db::name('campaigns')->where('id', $id)->find();
        (new HomeLayoutService())->syncCampaignModule($row);

        return $this->presentAdmin($row);
    }

    public function delete(int $id): array
    {
        $this->ensureSchema();
        $row = Db::name('campaigns')->where('id', $id)->find();
        if (!$row) {
            throw new ApiException('NOT_FOUND', '活動不存在', 404, 40401);
        }
        $code = (string) $row['code'];
        Db::name('campaign_products')->where('campaign_id', $id)->delete();
        Db::name('campaigns')->where('id', $id)->delete();
        Db::name('home_layout_modules')->where('module_key', 'campaign:' . $code)->delete();

        return ['id' => $id, 'deleted' => true];
    }

    /**
     * 商品是否受某活動購買資格限制；若限制且會員不符合則返回錯誤文案，否則 null。
     */
    public function purchaseBlockReason(?int $memberId, int $productId): ?string
    {
        $this->ensureSchema();
        $badges = Db::name('product_badges')->where('product_id', $productId)->column('badge');
        if (!$badges) {
            return null;
        }
        $camps = Db::name('campaigns')
            ->where('status', 1)
            ->whereIn('product_badge', $badges)
            ->whereIn('product_source', ['badge', 'badge_and_manual'])
            ->select()
            ->toArray();
        foreach ($camps as $camp) {
            if (!$this->inWindow($camp)) {
                continue;
            }
            $audience = $this->decodeAudience($camp);
            if (!empty($audience['all'])) {
                continue;
            }
            if (!$this->memberMatchesAudience($memberId, $audience)) {
                $title = (string) ($camp['title_zh'] ?: $camp['title_en'] ?: '專屬');

                return '此商品為' . $title . '商品，當前無法加入購物車';
            }
        }

        return null;
    }

    public function presentPublic(array $row, bool $withProducts): array
    {
        $out = [
            'id' => (int) $row['id'],
            'code' => (string) $row['code'],
            'title' => [
                'zh-HK' => (string) ($row['title_zh'] ?? ''),
                'en' => (string) ($row['title_en'] ?? ''),
            ],
            'banner_image_url' => $row['banner_image_url'] !== null ? (string) $row['banner_image_url'] : '',
            'intro_html' => [
                'zh-HK' => (string) ($row['intro_html_zh'] ?? ''),
                'en' => (string) ($row['intro_html_en'] ?? ''),
            ],
            'home_rail_limit' => (int) ($row['home_rail_limit'] ?? 10),
            'show_on_home' => (int) ($row['show_on_home'] ?? 0),
            'audience' => $this->decodeAudience($row),
        ];
        if ($withProducts) {
            $out['items'] = $this->loadCards($this->resolveProductIds($row));
        }

        return $out;
    }

    private function presentAdmin(array $row): array
    {
        $out = $this->presentPublic($row, false);
        $out['status'] = (int) $row['status'];
        $out['starts_at'] = $row['starts_at'] ?? null;
        $out['ends_at'] = $row['ends_at'] ?? null;
        $out['product_source'] = (string) ($row['product_source'] ?? 'badge');
        $out['product_badge'] = $row['product_badge'] !== null ? (string) $row['product_badge'] : '';
        $out['sort_order'] = (int) ($row['sort_order'] ?? 0);
        $out['product_ids'] = Db::name('campaign_products')
            ->where('campaign_id', (int) $row['id'])
            ->order('sort_order', 'asc')
            ->column('product_id');

        return $out;
    }

    /** @return list<int> */
    private function resolveProductIds(array $camp): array
    {
        $source = (string) ($camp['product_source'] ?? 'badge');
        $badgeIds = [];
        $manualIds = [];
        if (in_array($source, ['badge', 'badge_and_manual'], true)) {
            $badge = (string) ($camp['product_badge'] ?? '');
            if ($badge !== '') {
                $badgeIds = Db::name('product_badges')
                    ->alias('pb')
                    ->join('products p', 'p.id = pb.product_id')
                    ->where('pb.badge', $badge)
                    ->where('p.status', 1)
                    ->order('p.id', 'desc')
                    ->column('p.id');
                $badgeIds = array_map('intval', $badgeIds);
            }
        }
        if (in_array($source, ['manual', 'badge_and_manual'], true)) {
            $manualIds = Db::name('campaign_products')
                ->alias('cp')
                ->join('products p', 'p.id = cp.product_id')
                ->where('cp.campaign_id', (int) $camp['id'])
                ->where('p.status', 1)
                ->order('cp.sort_order', 'asc')
                ->order('cp.id', 'asc')
                ->column('p.id');
            $manualIds = array_map('intval', $manualIds);
        }
        if ($source === 'manual') {
            return $manualIds;
        }
        if ($source === 'badge') {
            return $badgeIds;
        }
        // badge_and_manual：手工置頂，其餘角標池去重追加
        $seen = [];
        $out = [];
        foreach (array_merge($manualIds, $badgeIds) as $pid) {
            if (isset($seen[$pid])) {
                continue;
            }
            $seen[$pid] = true;
            $out[] = $pid;
        }

        return $out;
    }

    /** @param list<int> $ids */
    private function loadCards(array $ids): array
    {
        if (!$ids) {
            return [];
        }
        $products = Db::name('products')->whereIn('id', $ids)->where('status', 1)->select()->toArray();
        $byId = [];
        foreach ($products as $p) {
            $byId[(int) $p['id']] = $p;
        }
        $catalog = new CatalogService();
        $mode = SoldDisplay::mode();
        $items = [];
        foreach ($ids as $pid) {
            $p = $byId[$pid] ?? null;
            if (!$p) {
                continue;
            }
            $items[] = $catalog->presentCardPublic($p, $mode);
        }

        return $items;
    }

    private function replaceManualProducts(int $campaignId, array $body): void
    {
        $ids = [];
        if (!empty($body['product_ids']) && is_array($body['product_ids'])) {
            foreach ($body['product_ids'] as $pid) {
                $ids[] = (int) $pid;
            }
        } elseif (!empty($body['product_codes']) && is_array($body['product_codes'])) {
            foreach ($body['product_codes'] as $code) {
                $p = Db::name('products')->where('code', (string) $code)->find();
                if ($p) {
                    $ids[] = (int) $p['id'];
                }
            }
        }
        $ids = array_values(array_unique(array_filter($ids)));
        Db::name('campaign_products')->where('campaign_id', $campaignId)->delete();
        $now = date('Y-m-d H:i:s');
        $sort = 0;
        foreach ($ids as $pid) {
            if (!Db::name('products')->where('id', $pid)->find()) {
                continue;
            }
            Db::name('campaign_products')->insert([
                'campaign_id' => $campaignId,
                'product_id' => $pid,
                'sort_order' => $sort++,
                'created_at' => $now,
            ]);
        }
    }

    private function decodeAudience(array $row): array
    {
        $raw = $row['audience_json'] ?? null;
        if (is_array($raw)) {
            return $raw;
        }
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return ['all' => true];
    }

    private function memberMatchesAudience(?int $memberId, array $audience): bool
    {
        if (!empty($audience['all'])) {
            return true;
        }
        if ($memberId === null || $memberId <= 0) {
            return false;
        }
        if (isset($audience['registered_within_days'])) {
            $days = max(1, (int) $audience['registered_within_days']);
            $member = Db::name('members')->where('id', $memberId)->field('created_at')->find();
            if (!$member || empty($member['created_at'])) {
                return false;
            }
            $ts = strtotime((string) $member['created_at']);
            if ($ts === false) {
                return false;
            }

            return $ts >= strtotime('-' . $days . ' days');
        }

        return false;
    }

    private function inWindow(array $camp): bool
    {
        $now = time();
        if (!empty($camp['starts_at']) && strtotime((string) $camp['starts_at']) > $now) {
            return false;
        }
        if (!empty($camp['ends_at']) && strtotime((string) $camp['ends_at']) < $now) {
            return false;
        }

        return true;
    }

    private function nullableDatetime(mixed $v): ?string
    {
        if ($v === null || $v === '') {
            return null;
        }
        $s = trim((string) $v);

        return $s !== '' ? $s : null;
    }
}
