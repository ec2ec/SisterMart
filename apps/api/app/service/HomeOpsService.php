<?php

declare(strict_types=1);

namespace app\service;

use app\support\ApiException;

/**
 * M24→擴展：首頁字幕（多條）+ 頁腳標語（存 shop_settings JSON）。
 *
 * 字幕存儲：
 * - 舊：單一物件 { text_zh, text_en, link_*, status }
 * - 新：{ version: 2, items: [ { code, title_*, text_*, link_*, status, show_on_home, sort_order } ] }
 *
 * 對外仍保留 `marquee`（主條／兼容）與新增 `marquees`（全部）。
 */
class HomeOpsService
{
    private const LINK_TYPES = ['none', 'product', 'category', 'article', 'url'];

    public const KEY_MARQUEE = 'home.marquee';
    public const KEY_SLOGAN = 'home.footer_slogan';

    public function publicBundle(): array
    {
        $items = $this->enabledPresented($this->listItems());
        $imageAds = (new HomeImageAdService())->publicList();

        return [
            'marquee' => $items[0] ?? null,
            'marquees' => $items,
            'image_ads' => $imageAds,
            'footer_slogan' => $this->presentSlogan($this->rawSlogan()),
        ];
    }

    public function adminRead(): array
    {
        $items = array_map(fn ($raw) => $this->presentItem($raw, false), $this->listItems());
        $primary = $this->findPrimaryPresented($items);

        return [
            'marquee' => $primary,
            'marquees' => $items,
            'image_ads' => (new HomeImageAdService())->adminList(),
            'footer_slogan' => $this->presentSlogan($this->rawSlogan()),
        ];
    }

    /**
     * @param array<string, mixed> $body
     */
    public function adminUpdate(array $body): array
    {
        if (array_key_exists('marquees', $body) && is_array($body['marquees'])) {
            $this->saveMarqueeList($body['marquees']);
        } elseif (array_key_exists('marquee', $body) && is_array($body['marquee'])) {
            // 兼容舊單一保存：寫入／更新 code=default（或指定 code）
            $this->upsertSingleMarquee($body['marquee']);
        }
        if (array_key_exists('image_ads', $body) && is_array($body['image_ads'])) {
            (new HomeImageAdService())->adminSave($body['image_ads']);
        }
        if (array_key_exists('footer_slogan', $body) && is_array($body['footer_slogan'])) {
            $this->saveSlogan($body['footer_slogan']);
        }

        return $this->adminRead();
    }

    /**
     * 依 code 取一條已啟用字幕（公開／佈局用）。
     *
     * @return array<string, mixed>|null
     */
    public function publicByCode(?string $code): ?array
    {
        $code = trim((string) $code);
        foreach ($this->listItems() as $raw) {
            if ($code !== '' && (string) ($raw['code'] ?? '') !== $code) {
                continue;
            }
            if ($code === '' && (string) ($raw['code'] ?? '') !== 'default') {
                // 無 code 時優先 default，否則第一條啟用
                continue;
            }
            $presented = $this->presentItem($raw, true);
            if ($presented) {
                return $presented;
            }
        }
        if ($code === '') {
            $enabled = $this->enabledPresented($this->listItems());

            return $enabled[0] ?? null;
        }

        return null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listItems(): array
    {
        $raw = TradeSettings::get(self::KEY_MARQUEE, '');
        if ($raw === '') {
            return [$this->defaultItem()];
        }
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return [$this->defaultItem()];
        }

        // 新格式
        if (isset($decoded['items']) && is_array($decoded['items'])) {
            $items = [];
            foreach ($decoded['items'] as $i => $row) {
                if (!is_array($row)) {
                    continue;
                }
                $items[] = $this->normalizeItem($row, $i);
            }
            if (!$items) {
                return [$this->defaultItem()];
            }
            usort($items, static function ($a, $b) {
                $sa = (int) ($a['sort_order'] ?? 0);
                $sb = (int) ($b['sort_order'] ?? 0);
                if ($sa === $sb) {
                    return strcmp((string) $a['code'], (string) $b['code']);
                }

                return $sa <=> $sb;
            });

            return $items;
        }

        // 舊單一物件 → 視為 default
        return [$this->normalizeItem(array_merge($decoded, [
            'code' => 'default',
            'title_zh' => (string) ($decoded['title_zh'] ?? '首頁字幕'),
            'title_en' => (string) ($decoded['title_en'] ?? 'Home marquee'),
            'show_on_home' => 1,
            'sort_order' => 0,
        ]), 0)];
    }

    /** @return array<string, mixed> */
    private function defaultItem(): array
    {
        return [
            'code' => 'default',
            'title_zh' => '首頁字幕',
            'title_en' => 'Home marquee',
            'text_zh' => '首次下單最高享 4 折優惠 · 立即選購',
            'text_en' => 'Get up to 40% off on your 1st order · Shop now',
            'link_type' => 'none',
            'link_value' => '',
            'status' => 1,
            'show_on_home' => 1,
            'sort_order' => 0,
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function normalizeItem(array $row, int $index): array
    {
        $code = trim((string) ($row['code'] ?? ''));
        if ($code === '') {
            $code = $index === 0 ? 'default' : ('m' . ($index + 1));
        }
        $code = preg_replace('/[^a-zA-Z0-9_\-]/', '-', $code) ?: ('m' . ($index + 1));

        return [
            'code' => $code,
            'title_zh' => (string) ($row['title_zh'] ?? $row['title']['zh-HK'] ?? ''),
            'title_en' => (string) ($row['title_en'] ?? $row['title']['en'] ?? ''),
            'text_zh' => (string) ($row['text_zh'] ?? $row['text']['zh-HK'] ?? ''),
            'text_en' => (string) ($row['text_en'] ?? $row['text']['en'] ?? ''),
            'link_type' => (string) ($row['link_type'] ?? 'none'),
            'link_value' => (string) ($row['link_value'] ?? ''),
            'status' => (int) ($row['status'] ?? 1) === 0 ? 0 : 1,
            'show_on_home' => (int) ($row['show_on_home'] ?? 1) === 0 ? 0 : 1,
            'sort_order' => (int) ($row['sort_order'] ?? ($index * 10)),
        ];
    }

    /**
     * @param array<string, mixed> $raw
     * @return array<string, mixed>|null
     */
    private function presentItem(array $raw, bool $publicOnly): ?array
    {
        $status = (int) ($raw['status'] ?? 1) === 0 ? 0 : 1;
        if ($publicOnly && $status !== 1) {
            return null;
        }
        $payload = [
            'code' => (string) ($raw['code'] ?? 'default'),
            'title' => [
                'zh-HK' => (string) ($raw['title_zh'] ?? ''),
                'en' => (string) ($raw['title_en'] ?? ''),
            ],
            'text' => [
                'zh-HK' => (string) ($raw['text_zh'] ?? ''),
                'en' => (string) ($raw['text_en'] ?? ''),
            ],
            'link_type' => (string) ($raw['link_type'] ?? 'none'),
            'link_value' => (string) ($raw['link_value'] ?? ''),
            'status' => $status,
            'enabled' => $status === 1,
            'show_on_home' => (int) ($raw['show_on_home'] ?? 1) === 1 ? 1 : 0,
            'sort_order' => (int) ($raw['sort_order'] ?? 0),
        ];

        return $payload;
    }

    /**
     * @param list<array<string, mixed>> $rawItems
     * @return list<array<string, mixed>>
     */
    private function enabledPresented(array $rawItems): array
    {
        $out = [];
        foreach ($rawItems as $raw) {
            $p = $this->presentItem($raw, true);
            if ($p) {
                $out[] = $p;
            }
        }

        return $out;
    }

    /**
     * @param list<array<string, mixed>> $presented
     * @return array<string, mixed>|null
     */
    private function findPrimaryPresented(array $presented): ?array
    {
        foreach ($presented as $row) {
            if (($row['code'] ?? '') === 'default') {
                return $row;
            }
        }

        return $presented[0] ?? null;
    }

    /** @return array<string, mixed> */
    private function rawSlogan(): array
    {
        $raw = TradeSettings::get(self::KEY_SLOGAN, '');
        if ($raw === '') {
            return $this->defaultSlogan();
        }
        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : $this->defaultSlogan();
    }

    /** @return array<string, mixed> */
    private function defaultSlogan(): array
    {
        return [
            'zh-HK' => '* 我們守護您的美麗 *',
            'en' => '* We guard your beauty *',
        ];
    }

    /**
     * @param array<string, mixed> $raw
     * @return array{zh-HK: string, en: string}
     */
    private function presentSlogan(array $raw): array
    {
        return [
            'zh-HK' => (string) ($raw['zh-HK'] ?? $raw['zh'] ?? ''),
            'en' => (string) ($raw['en'] ?? ''),
        ];
    }

    /** @param list<mixed> $list */
    private function saveMarqueeList(array $list): void
    {
        $items = [];
        $seen = [];
        foreach (array_values($list) as $i => $row) {
            if (!is_array($row)) {
                continue;
            }
            $normalized = $this->validateAndBuildItem($row, $i);
            $code = $normalized['code'];
            if (isset($seen[$code])) {
                throw new ApiException('INVALID', '字幕 code 重複：' . $code, 422, 42201);
            }
            $seen[$code] = true;
            $items[] = $normalized;
        }
        if (!$items) {
            throw new ApiException('INVALID', '請至少保留一條字幕', 422, 42201);
        }
        $this->persistItems($items);
        $this->syncAllLayoutModules($items);
    }

    /** @param array<string, mixed> $body */
    private function upsertSingleMarquee(array $body): void
    {
        $items = $this->listItems();
        $code = trim((string) ($body['code'] ?? 'default'));
        if ($code === '') {
            $code = 'default';
        }
        $body['code'] = $code;
        // 舊單一保存未帶 title／show_on_home 時保留原值
        $existing = null;
        $idx = null;
        foreach ($items as $i => $it) {
            if ($it['code'] === $code) {
                $existing = $it;
                $idx = $i;
                break;
            }
        }
        if ($existing) {
            if (!array_key_exists('title_zh', $body) && !isset($body['title']['zh-HK'])) {
                $body['title_zh'] = $existing['title_zh'];
            }
            if (!array_key_exists('title_en', $body) && !isset($body['title']['en'])) {
                $body['title_en'] = $existing['title_en'];
            }
            if (!array_key_exists('show_on_home', $body)) {
                $body['show_on_home'] = $existing['show_on_home'];
            }
            if (!array_key_exists('sort_order', $body)) {
                $body['sort_order'] = $existing['sort_order'];
            }
        } else {
            if (!array_key_exists('title_zh', $body) && !isset($body['title']['zh-HK'])) {
                $body['title_zh'] = $code === 'default' ? '首頁字幕' : $code;
            }
            if (!array_key_exists('show_on_home', $body)) {
                $body['show_on_home'] = 1;
            }
        }
        $normalized = $this->validateAndBuildItem($body, $idx ?? count($items));
        if ($idx === null) {
            $items[] = $normalized;
        } else {
            $items[$idx] = $normalized;
        }
        $this->persistItems($items);
        $this->syncAllLayoutModules($items);
    }

    /**
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    private function validateAndBuildItem(array $body, int $index): array
    {
        $linkType = (string) ($body['link_type'] ?? 'none');
        if (!in_array($linkType, self::LINK_TYPES, true)) {
            throw new ApiException('INVALID', '字幕跳轉類型無效', 422, 42201);
        }
        $linkValue = trim((string) ($body['link_value'] ?? ''));
        if ($linkType !== 'none' && $linkValue === '') {
            throw new ApiException('INVALID', '請填寫字幕跳轉目標', 422, 42201);
        }
        $textZh = trim((string) ($body['text_zh'] ?? $body['text']['zh-HK'] ?? ''));
        $textEn = trim((string) ($body['text_en'] ?? $body['text']['en'] ?? ''));
        if ($textZh === '' && $textEn === '') {
            throw new ApiException('INVALID', '請填寫字幕文案', 422, 42201);
        }
        $titleZh = trim((string) ($body['title_zh'] ?? $body['title']['zh-HK'] ?? ''));
        $titleEn = trim((string) ($body['title_en'] ?? $body['title']['en'] ?? ''));
        $code = trim((string) ($body['code'] ?? ''));
        if ($code === '') {
            $code = $index === 0 ? 'default' : ('m' . ($index + 1));
        }
        $code = strtolower((string) preg_replace('/[^a-zA-Z0-9_\-]/', '-', $code));
        if ($code === '' || $code === '-') {
            throw new ApiException('INVALID', '字幕 code 無效', 422, 42201);
        }
        if ($titleZh === '' && $titleEn === '') {
            $titleZh = $code === 'default' ? '首頁字幕' : $code;
        }

        return [
            'code' => $code,
            'title_zh' => $titleZh,
            'title_en' => $titleEn,
            'text_zh' => $textZh,
            'text_en' => $textEn,
            'link_type' => $linkType,
            'link_value' => $linkType === 'none' ? '' : $linkValue,
            'status' => (int) ($body['status'] ?? 1) === 0 ? 0 : 1,
            'show_on_home' => (int) ($body['show_on_home'] ?? 1) === 0 ? 0 : 1,
            'sort_order' => (int) ($body['sort_order'] ?? ($index * 10)),
        ];
    }

    /** @param list<array<string, mixed>> $items */
    private function persistItems(array $items): void
    {
        $payload = [
            'version' => 2,
            'items' => array_values($items),
        ];
        TradeSettings::set(self::KEY_MARQUEE, json_encode($payload, JSON_UNESCAPED_UNICODE));
    }

    /** @param list<array<string, mixed>> $items */
    private function syncAllLayoutModules(array $items): void
    {
        $layout = new HomeLayoutService();
        $alive = [];
        foreach ($items as $item) {
            $layout->syncMarqueeModule($item);
            $alive[] = 'marquee:' . $item['code'];
        }
        // 遷移舊的無 ref 通用 text_marquee
        $layout->migrateLegacyTextMarquee($items);
        $layout->pruneMarqueeModules($alive);
    }

    /** @param array<string, mixed> $body */
    private function saveSlogan(array $body): void
    {
        $payload = [
            'zh-HK' => trim((string) ($body['zh-HK'] ?? $body['zh'] ?? '')),
            'en' => trim((string) ($body['en'] ?? '')),
        ];
        if ($payload['zh-HK'] === '' && $payload['en'] === '') {
            throw new ApiException('INVALID', '請填寫頁腳標語', 422, 42201);
        }
        TradeSettings::set(self::KEY_SLOGAN, json_encode($payload, JSON_UNESCAPED_UNICODE));
    }
}
