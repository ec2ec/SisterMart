<?php

declare(strict_types=1);

namespace app\service;

use app\support\ApiException;
use think\facade\Db;

/**
 * M45 首頁版面模組（跨類型排序；保存即生效）。
 */
class HomeLayoutService
{
    public const TYPES = [
        'banner_swipe',
        'text_marquee',
        'category_row',
        'seckill_rail',
        'recommend_feed',
        'campaign_rail',
        'product_showcase',
        'image_block',
    ];

    public function ensureSchema(): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;
        Db::execute(<<<'SQL'
CREATE TABLE IF NOT EXISTS home_layout_modules (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  module_key VARCHAR(64) NOT NULL,
  module_type VARCHAR(32) NOT NULL,
  ref_type VARCHAR(32) NULL,
  ref_code VARCHAR(64) NULL,
  title_zh VARCHAR(128) NOT NULL DEFAULT '',
  title_en VARCHAR(128) NOT NULL DEFAULT '',
  enabled TINYINT NOT NULL DEFAULT 1,
  sort_order INT NOT NULL DEFAULT 0,
  config_json JSON NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  UNIQUE KEY uk_home_layout_module_key (module_key),
  KEY idx_home_layout_sort (enabled, sort_order, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function listPublic(): array
    {
        $this->ensureSchema();
        $this->ensureContentModulesSynced();
        $rows = Db::name('home_layout_modules')
            ->where('enabled', 1)
            ->order('sort_order', 'asc')
            ->order('id', 'asc')
            ->select()
            ->toArray();

        $items = [];
        foreach ($rows as $row) {
            $mod = $this->present($row);
            // 活動模組：活動未發佈／未掛首頁則跳過
            if ($mod['module_type'] === 'campaign_rail' && $mod['ref_code']) {
                $camp = Db::name('campaigns')->where('code', $mod['ref_code'])->find();
                if (!$camp || (int) $camp['status'] !== 1 || (int) $camp['show_on_home'] !== 1) {
                    continue;
                }
                if (!$this->campaignInWindow($camp)) {
                    continue;
                }
                $mod['campaign'] = (new CampaignService())->presentPublic($camp, false);
            }
            if ($mod['module_type'] === 'product_showcase' && $mod['ref_code']) {
                $sec = Db::name('home_sections')->where('code', $mod['ref_code'])->where('status', 1)->find();
                if (!$sec) {
                    continue;
                }
            }
            if ($mod['module_type'] === 'text_marquee') {
                $ops = new HomeOpsService();
                $marquee = $ops->publicByCode($mod['ref_code'] ?: null);
                if (!$marquee) {
                    continue;
                }
                $mod['marquee'] = $marquee;
            }
            if ($mod['module_type'] === 'image_block') {
                $ad = (new HomeImageAdService())->publicByCode($mod['ref_code'] ?: null);
                if (!$ad) {
                    continue;
                }
                $mod['image_ad'] = $ad;
            }
            $items[] = $mod;
        }

        return ['items' => $items];
    }

    public function listAdmin(): array
    {
        $this->ensureSchema();
        $this->ensureContentModulesSynced();
        $rows = Db::name('home_layout_modules')
            ->order('sort_order', 'asc')
            ->order('id', 'asc')
            ->select()
            ->toArray();

        return ['items' => array_map(fn ($r) => $this->present($r), $rows)];
    }

    /** 首次讀取佈局時：同步字幕／圖片廣告模組 */
    private function ensureContentModulesSynced(): void
    {
        static $synced = false;
        if ($synced) {
            return;
        }
        $synced = true;
        try {
            $ops = new HomeOpsService();
            $items = $ops->listItems();
            foreach ($items as $item) {
                $this->syncMarqueeModule($item, true);
            }
            $this->migrateLegacyTextMarquee($items);
            $alive = array_map(static fn ($it) => 'marquee:' . $it['code'], $items);
            $this->pruneMarqueeModules($alive);
        } catch (\Throwable $e) {
            // 佈局讀取不因字幕遷移失敗而中斷
        }
        try {
            $ads = (new HomeImageAdService())->listItems();
            foreach ($ads as $item) {
                $this->syncImageAdModule($item, true);
            }
            $aliveAds = array_map(static fn ($it) => 'image_ad:' . $it['code'], $ads);
            $this->pruneImageAdModules($aliveAds);
        } catch (\Throwable $e) {
            // 佈局讀取不因圖片廣告同步失敗而中斷
        }
    }

    /**
     * 拖放保存：按 ids 順序重寫 sort_order；可選更新 enabled。
     *
     * @param list<array{id:int,enabled?:int}|int> $modules
     */
    public function reorder(array $modules): array
    {
        $this->ensureSchema();
        $now = date('Y-m-d H:i:s');
        $sort = 10;
        Db::startTrans();
        try {
            foreach ($modules as $entry) {
                $id = is_array($entry) ? (int) ($entry['id'] ?? 0) : (int) $entry;
                if ($id <= 0) {
                    continue;
                }
                $row = Db::name('home_layout_modules')->where('id', $id)->find();
                if (!$row) {
                    continue;
                }
                $data = [
                    'sort_order' => $sort,
                    'updated_at' => $now,
                ];
                if (is_array($entry) && array_key_exists('enabled', $entry)) {
                    $data['enabled'] = (int) $entry['enabled'] === 0 ? 0 : 1;
                }
                Db::name('home_layout_modules')->where('id', $id)->update($data);
                $sort += 10;
            }
            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            throw $e;
        }

        return $this->listAdmin();
    }

    public function updateModule(int $id, array $body): array
    {
        $this->ensureSchema();
        $row = Db::name('home_layout_modules')->where('id', $id)->find();
        if (!$row) {
            throw new ApiException('NOT_FOUND', '模組不存在', 404, 40401);
        }
        $data = ['updated_at' => date('Y-m-d H:i:s')];
        if (array_key_exists('enabled', $body)) {
            $data['enabled'] = (int) $body['enabled'] === 0 ? 0 : 1;
        }
        if (array_key_exists('title', $body) && is_array($body['title'])) {
            $data['title_zh'] = trim((string) ($body['title']['zh-HK'] ?? $body['title_zh'] ?? $row['title_zh']));
            $data['title_en'] = trim((string) ($body['title']['en'] ?? $body['title_en'] ?? $row['title_en']));
        }
        if (array_key_exists('title_zh', $body)) {
            $data['title_zh'] = trim((string) $body['title_zh']);
        }
        if (array_key_exists('title_en', $body)) {
            $data['title_en'] = trim((string) $body['title_en']);
        }
        if (array_key_exists('config', $body) || array_key_exists('config_json', $body)) {
            $cfg = $body['config'] ?? $body['config_json'];
            $data['config_json'] = is_string($cfg) ? $cfg : json_encode($cfg, JSON_UNESCAPED_UNICODE);
        }
        if (array_key_exists('sort_order', $body)) {
            $data['sort_order'] = (int) $body['sort_order'];
        }
        Db::name('home_layout_modules')->where('id', $id)->update($data);

        return $this->present(Db::name('home_layout_modules')->where('id', $id)->find());
    }

    /**
     * 字幕勾選「展示於首頁」時同步／插入 text_marquee 模組。
     *
     * @param array<string, mixed> $marquee raw item（含 code / title_* / status / show_on_home）
     */
    public function syncMarqueeModule(array $marquee, bool $appendIfNew = true): void
    {
        $this->ensureSchema();
        $code = (string) ($marquee['code'] ?? '');
        if ($code === '') {
            return;
        }
        $key = 'marquee:' . $code;
        $now = date('Y-m-d H:i:s');
        $existing = Db::name('home_layout_modules')->where('module_key', $key)->find();
        $show = (int) ($marquee['show_on_home'] ?? 0) === 1 && (int) ($marquee['status'] ?? 0) === 1;
        $titleZh = (string) ($marquee['title_zh'] ?? $marquee['title']['zh-HK'] ?? $code);
        $titleEn = (string) ($marquee['title_en'] ?? $marquee['title']['en'] ?? '');

        if ($existing) {
            Db::name('home_layout_modules')->where('id', (int) $existing['id'])->update([
                'title_zh' => $titleZh !== '' ? $titleZh : (string) $existing['title_zh'],
                'title_en' => $titleEn !== '' ? $titleEn : (string) $existing['title_en'],
                'enabled' => $show ? 1 : 0,
                'module_type' => 'text_marquee',
                'ref_type' => 'marquee',
                'ref_code' => $code,
                'updated_at' => $now,
            ]);

            return;
        }
        if (!$show || !$appendIfNew) {
            return;
        }
        $maxSort = (int) Db::name('home_layout_modules')->max('sort_order');
        $cats = Db::name('home_layout_modules')->where('module_key', 'category_row')->find();
        // 默認插在分類前（字幕常見位置）；若無分類則靠前
        $sort = $cats ? max(10, (int) $cats['sort_order'] - 5) : max(20, $maxSort + 10);
        // default 字幕優先沿用種子 sort 20
        if ($code === 'default') {
            $legacy = Db::name('home_layout_modules')->where('module_key', 'text_marquee')->find();
            $sort = $legacy ? (int) $legacy['sort_order'] : 20;
        }
        Db::name('home_layout_modules')->insert([
            'module_key' => $key,
            'module_type' => 'text_marquee',
            'ref_type' => 'marquee',
            'ref_code' => $code,
            'title_zh' => $titleZh,
            'title_en' => $titleEn,
            'enabled' => 1,
            'sort_order' => $sort,
            'config_json' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /**
     * 把舊的無 ref 通用 text_marquee 模組遷到 marquee:default。
     *
     * @param list<array<string, mixed>> $items
     */
    public function migrateLegacyTextMarquee(array $items): void
    {
        $this->ensureSchema();
        $legacy = Db::name('home_layout_modules')->where('module_key', 'text_marquee')->find();
        if (!$legacy) {
            return;
        }
        $default = null;
        foreach ($items as $it) {
            if (($it['code'] ?? '') === 'default') {
                $default = $it;
                break;
            }
        }
        if (!$default && $items) {
            $default = $items[0];
        }
        if (!$default) {
            return;
        }
        $code = (string) $default['code'];
        $key = 'marquee:' . $code;
        $now = date('Y-m-d H:i:s');
        $target = Db::name('home_layout_modules')->where('module_key', $key)->find();
        if ($target) {
            // 已有新模組：把舊模組的排序／開關合入後刪舊
            Db::name('home_layout_modules')->where('id', (int) $target['id'])->update([
                'sort_order' => (int) $legacy['sort_order'],
                'enabled' => (int) $legacy['enabled'] === 1 && (int) ($default['show_on_home'] ?? 0) === 1 && (int) ($default['status'] ?? 0) === 1 ? 1 : 0,
                'updated_at' => $now,
            ]);
            Db::name('home_layout_modules')->where('id', (int) $legacy['id'])->delete();

            return;
        }
        Db::name('home_layout_modules')->where('id', (int) $legacy['id'])->update([
            'module_key' => $key,
            'module_type' => 'text_marquee',
            'ref_type' => 'marquee',
            'ref_code' => $code,
            'title_zh' => (string) ($default['title_zh'] ?: $legacy['title_zh']),
            'title_en' => (string) ($default['title_en'] ?: $legacy['title_en']),
            'updated_at' => $now,
        ]);
    }

    /**
     * 刪除已不存在於字幕列表的 marquee:* 模組。
     *
     * @param list<string> $aliveKeys module_key 白名單
     */
    public function pruneMarqueeModules(array $aliveKeys): void
    {
        $this->ensureSchema();
        $rows = Db::name('home_layout_modules')
            ->where('module_type', 'text_marquee')
            ->whereLike('module_key', 'marquee:%')
            ->select()
            ->toArray();
        $alive = array_fill_keys($aliveKeys, true);
        foreach ($rows as $row) {
            $key = (string) $row['module_key'];
            if (!isset($alive[$key])) {
                Db::name('home_layout_modules')->where('id', (int) $row['id'])->delete();
            }
        }
    }

    /**
     * 圖片廣告勾選「展示於首頁」時同步／插入 image_block 模組。
     *
     * @param array<string, mixed> $ad raw item（含 code / title_* / status / show_on_home）
     */
    public function syncImageAdModule(array $ad, bool $appendIfNew = true): void
    {
        $this->ensureSchema();
        $code = (string) ($ad['code'] ?? '');
        if ($code === '') {
            return;
        }
        $key = 'image_ad:' . $code;
        $now = date('Y-m-d H:i:s');
        $existing = Db::name('home_layout_modules')->where('module_key', $key)->find();
        $show = (int) ($ad['show_on_home'] ?? 0) === 1 && (int) ($ad['status'] ?? 0) === 1;
        $titleZh = (string) ($ad['title_zh'] ?? $ad['title']['zh-HK'] ?? $code);
        $titleEn = (string) ($ad['title_en'] ?? $ad['title']['en'] ?? '');

        if ($existing) {
            Db::name('home_layout_modules')->where('id', (int) $existing['id'])->update([
                'title_zh' => $titleZh !== '' ? $titleZh : (string) $existing['title_zh'],
                'title_en' => $titleEn !== '' ? $titleEn : (string) $existing['title_en'],
                'enabled' => $show ? 1 : 0,
                'module_type' => 'image_block',
                'ref_type' => 'image_ad',
                'ref_code' => $code,
                'updated_at' => $now,
            ]);

            return;
        }
        if (!$show || !$appendIfNew) {
            return;
        }
        $maxSort = (int) Db::name('home_layout_modules')->max('sort_order');
        // 默認插在分類後、活動前
        $cats = Db::name('home_layout_modules')->where('module_key', 'category_row')->find();
        $camp = Db::name('home_layout_modules')->whereLike('module_key', 'campaign:%')->order('sort_order', 'asc')->find();
        if ($cats) {
            $sort = (int) $cats['sort_order'] + 5;
        } elseif ($camp) {
            $sort = max(10, (int) $camp['sort_order'] - 5);
        } else {
            $sort = $maxSort + 10;
        }
        Db::name('home_layout_modules')->insert([
            'module_key' => $key,
            'module_type' => 'image_block',
            'ref_type' => 'image_ad',
            'ref_code' => $code,
            'title_zh' => $titleZh,
            'title_en' => $titleEn,
            'enabled' => 1,
            'sort_order' => $sort,
            'config_json' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /**
     * 刪除已不存在於圖片廣告列表的 image_ad:* 模組。
     *
     * @param list<string> $aliveKeys module_key 白名單
     */
    public function pruneImageAdModules(array $aliveKeys): void
    {
        $this->ensureSchema();
        $rows = Db::name('home_layout_modules')
            ->where('module_type', 'image_block')
            ->whereLike('module_key', 'image_ad:%')
            ->select()
            ->toArray();
        $alive = array_fill_keys($aliveKeys, true);
        foreach ($rows as $row) {
            $key = (string) $row['module_key'];
            if (!isset($alive[$key])) {
                Db::name('home_layout_modules')->where('id', (int) $row['id'])->delete();
            }
        }
    }

    /**
     * 活動勾選「上首頁」時同步／插入 campaign_rail 模組。
     */
    public function syncCampaignModule(array $campaign, bool $appendIfNew = true): void
    {
        $this->ensureSchema();
        $code = (string) ($campaign['code'] ?? '');
        if ($code === '') {
            return;
        }
        $key = 'campaign:' . $code;
        $now = date('Y-m-d H:i:s');
        $existing = Db::name('home_layout_modules')->where('module_key', $key)->find();
        $show = (int) ($campaign['show_on_home'] ?? 0) === 1;
        $limit = max(1, min(30, (int) ($campaign['home_rail_limit'] ?? 10)));
        $cfg = json_encode(['rail_limit' => $limit], JSON_UNESCAPED_UNICODE);

        if ($existing) {
            Db::name('home_layout_modules')->where('id', (int) $existing['id'])->update([
                'title_zh' => (string) ($campaign['title_zh'] ?? $existing['title_zh']),
                'title_en' => (string) ($campaign['title_en'] ?? $existing['title_en']),
                'enabled' => $show ? 1 : 0,
                'config_json' => $cfg,
                'ref_type' => 'campaign',
                'ref_code' => $code,
                'updated_at' => $now,
            ]);

            return;
        }
        if (!$show || !$appendIfNew) {
            return;
        }
        $maxSort = (int) Db::name('home_layout_modules')->max('sort_order');
        // 插在 recommend_feed 之前
        $feed = Db::name('home_layout_modules')->where('module_key', 'recommend_feed')->find();
        $sort = $feed ? max(10, (int) $feed['sort_order'] - 10) : ($maxSort + 10);
        Db::name('home_layout_modules')->insert([
            'module_key' => $key,
            'module_type' => 'campaign_rail',
            'ref_type' => 'campaign',
            'ref_code' => $code,
            'title_zh' => (string) ($campaign['title_zh'] ?? ''),
            'title_en' => (string) ($campaign['title_en'] ?? ''),
            'enabled' => 1,
            'sort_order' => $sort,
            'config_json' => $cfg,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function campaignInWindow(array $camp): bool
    {
        $now = time();
        $starts = $camp['starts_at'] ?? null;
        $ends = $camp['ends_at'] ?? null;
        if ($starts && strtotime((string) $starts) > $now) {
            return false;
        }
        if ($ends && strtotime((string) $ends) < $now) {
            return false;
        }

        return true;
    }

    /** @param array<string,mixed> $row */
    private function present(array $row): array
    {
        $cfg = $row['config_json'] ?? null;
        if (is_string($cfg) && $cfg !== '') {
            $decoded = json_decode($cfg, true);
            $cfg = is_array($decoded) ? $decoded : null;
        } elseif (!is_array($cfg)) {
            $cfg = null;
        }

        return [
            'id' => (int) $row['id'],
            'module_key' => (string) $row['module_key'],
            'module_type' => (string) $row['module_type'],
            'ref_type' => $row['ref_type'] !== null ? (string) $row['ref_type'] : null,
            'ref_code' => $row['ref_code'] !== null ? (string) $row['ref_code'] : null,
            'title' => [
                'zh-HK' => (string) ($row['title_zh'] ?? ''),
                'en' => (string) ($row['title_en'] ?? ''),
            ],
            'enabled' => (int) $row['enabled'],
            'sort_order' => (int) $row['sort_order'],
            'config' => $cfg,
        ];
    }
}
