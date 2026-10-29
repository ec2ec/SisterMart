<?php

declare(strict_types=1);

namespace app\service;

use app\support\ApiException;

/**
 * 首頁圖片廣告（多條，存 shop_settings JSON）。
 *
 * 結構：{ version: 1, items: [ { code, title_*, image_url, link_*, status, show_on_home, sort_order } ] }
 * 勾選「展示於首頁」且啟用時，同步 home_layout_modules（module_type=image_block, key=image_ad:{code}）。
 */
class HomeImageAdService
{
    private const LINK_TYPES = ['none', 'product', 'category', 'article', 'url'];

    public const KEY = 'home.image_ads';

    /**
     * @return list<array<string, mixed>>
     */
    public function publicList(): array
    {
        return $this->enabledPresented($this->listItems());
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function adminList(): array
    {
        return array_map(fn ($raw) => $this->presentItem($raw, false), $this->listItems());
    }

    /**
     * @param list<mixed> $list
     * @return list<array<string, mixed>>
     */
    public function adminSave(array $list): array
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
                throw new ApiException('INVALID', '圖片廣告 code 重複：' . $code, 422, 42201);
            }
            $seen[$code] = true;
            $items[] = $normalized;
        }
        $this->persistItems($items);
        $this->syncAllLayoutModules($items);

        return $this->adminList();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function publicByCode(?string $code): ?array
    {
        $code = trim((string) $code);
        if ($code === '') {
            return null;
        }
        foreach ($this->listItems() as $raw) {
            if ((string) ($raw['code'] ?? '') !== $code) {
                continue;
            }

            return $this->presentItem($raw, true);
        }

        return null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listItems(): array
    {
        $raw = TradeSettings::get(self::KEY, '');
        if ($raw === '') {
            return [];
        }
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return [];
        }
        $rows = isset($decoded['items']) && is_array($decoded['items'])
            ? $decoded['items']
            : (array_is_list($decoded) ? $decoded : []);
        $items = [];
        foreach ($rows as $i => $row) {
            if (!is_array($row)) {
                continue;
            }
            $items[] = $this->normalizeItem($row, $i);
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

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function normalizeItem(array $row, int $index): array
    {
        $code = trim((string) ($row['code'] ?? ''));
        if ($code === '') {
            $code = 'ad' . ($index + 1);
        }
        $code = preg_replace('/[^a-zA-Z0-9_\-]/', '-', $code) ?: ('ad' . ($index + 1));

        return [
            'code' => $code,
            'title_zh' => (string) ($row['title_zh'] ?? $row['title']['zh-HK'] ?? ''),
            'title_en' => (string) ($row['title_en'] ?? $row['title']['en'] ?? ''),
            'image_url' => (string) ($row['image_url'] ?? ''),
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
        $image = trim((string) ($raw['image_url'] ?? ''));
        if ($publicOnly && $image === '') {
            return null;
        }

        return [
            'code' => (string) ($raw['code'] ?? ''),
            'title' => [
                'zh-HK' => (string) ($raw['title_zh'] ?? ''),
                'en' => (string) ($raw['title_en'] ?? ''),
            ],
            'image_url' => $image,
            'link_type' => (string) ($raw['link_type'] ?? 'none'),
            'link_value' => (string) ($raw['link_value'] ?? ''),
            'status' => $status,
            'enabled' => $status === 1,
            'show_on_home' => (int) ($raw['show_on_home'] ?? 1) === 1 ? 1 : 0,
            'sort_order' => (int) ($raw['sort_order'] ?? 0),
        ];
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
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    private function validateAndBuildItem(array $body, int $index): array
    {
        $linkType = (string) ($body['link_type'] ?? 'none');
        if (!in_array($linkType, self::LINK_TYPES, true)) {
            throw new ApiException('INVALID', '圖片廣告跳轉類型無效', 422, 42201);
        }
        $linkValue = trim((string) ($body['link_value'] ?? ''));
        if ($linkType !== 'none' && $linkValue === '') {
            throw new ApiException('INVALID', '請填寫圖片廣告跳轉目標', 422, 42201);
        }
        $image = trim((string) ($body['image_url'] ?? ''));
        if ($image === '') {
            throw new ApiException('INVALID', '請上傳圖片廣告圖', 422, 42201);
        }
        $titleZh = trim((string) ($body['title_zh'] ?? $body['title']['zh-HK'] ?? ''));
        $titleEn = trim((string) ($body['title_en'] ?? $body['title']['en'] ?? ''));
        if ($titleZh === '' && $titleEn === '') {
            throw new ApiException('INVALID', '請填寫圖片廣告標題', 422, 42201);
        }
        $code = trim((string) ($body['code'] ?? ''));
        if ($code === '') {
            $code = 'ad' . ($index + 1);
        }
        $code = strtolower((string) preg_replace('/[^a-zA-Z0-9_\-]/', '-', $code));
        if ($code === '' || $code === '-') {
            throw new ApiException('INVALID', '圖片廣告 code 無效', 422, 42201);
        }

        return [
            'code' => $code,
            'title_zh' => $titleZh,
            'title_en' => $titleEn,
            'image_url' => $image,
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
        TradeSettings::set(self::KEY, json_encode([
            'version' => 1,
            'items' => array_values($items),
        ], JSON_UNESCAPED_UNICODE));
    }

    /** @param list<array<string, mixed>> $items */
    private function syncAllLayoutModules(array $items): void
    {
        $layout = new HomeLayoutService();
        $alive = [];
        foreach ($items as $item) {
            $layout->syncImageAdModule($item);
            $alive[] = 'image_ad:' . $item['code'];
        }
        $layout->pruneImageAdModules($alive);
    }
}
