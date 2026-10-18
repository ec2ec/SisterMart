<?php

declare(strict_types=1);

namespace app\service;

use app\support\ApiException;
use think\facade\Db;

/**
 * M29 首頁商品展示區。
 */
class HomeSectionService
{
    private const VIEW_TYPES = ['list', 'category', 'url', 'none'];

    public function listPublic(): array
    {
        $sections = Db::name('home_sections')
            ->where('status', 1)
            ->order('sort_order', 'asc')
            ->order('id', 'asc')
            ->select()
            ->toArray();
        if (!$sections) {
            return ['items' => []];
        }

        return ['items' => array_map(fn ($s) => $this->presentSection($s, true), $sections)];
    }

    public function listAdmin(): array
    {
        $sections = Db::name('home_sections')
            ->order('sort_order', 'asc')
            ->order('id', 'asc')
            ->select()
            ->toArray();

        return ['items' => array_map(fn ($s) => $this->presentSection($s, true), $sections)];
    }

    public function productsByCode(string $code): array
    {
        $section = Db::name('home_sections')->where('code', $code)->where('status', 1)->find();
        if (!$section) {
            throw new ApiException('NOT_FOUND', '展示區不存在', 404, 40401);
        }
        $items = $this->loadProductCards((int) $section['id'], true);

        return [
            'section' => $this->presentSection($section, false),
            'items' => $items,
            'sold_display_mode' => SoldDisplay::mode(),
        ];
    }

    public function save(array $body, ?int $id = null): array
    {
        $code = trim((string) ($body['code'] ?? ''));
        $titleZh = trim((string) ($body['title']['zh-HK'] ?? $body['title_zh'] ?? ''));
        $titleEn = trim((string) ($body['title']['en'] ?? $body['title_en'] ?? ''));
        if ($titleZh === '' && $titleEn === '') {
            throw new ApiException('INVALID', '請填寫標題', 422, 42201);
        }
        $viewType = (string) ($body['view_all_type'] ?? 'list');
        if (!in_array($viewType, self::VIEW_TYPES, true)) {
            throw new ApiException('INVALID', '查看全部類型無效', 422, 42201);
        }
        $viewValue = trim((string) ($body['view_all_value'] ?? ''));
        if (in_array($viewType, ['category', 'url'], true) && $viewValue === '') {
            throw new ApiException('INVALID', '請填寫查看全部目標', 422, 42201);
        }
        $now = date('Y-m-d H:i:s');
        if ($id) {
            $row = Db::name('home_sections')->where('id', $id)->find();
            if (!$row) {
                throw new ApiException('NOT_FOUND', '展示區不存在', 404, 40401);
            }
            if ($code === '') {
                $code = (string) $row['code'];
            }
        } else {
            if ($code === '') {
                $code = 'sec_' . bin2hex(random_bytes(4));
            }
        }
        $dup = Db::name('home_sections')->where('code', $code);
        if ($id) {
            $dup->where('id', '<>', $id);
        }
        if ($dup->find()) {
            throw new ApiException('INVALID', 'code 已存在', 422, 42201);
        }
        $data = [
            'code' => $code,
            'title_zh' => $titleZh !== '' ? $titleZh : $titleEn,
            'title_en' => $titleEn !== '' ? $titleEn : $titleZh,
            'view_all_type' => $viewType,
            'view_all_value' => $viewType === 'none' ? null : ($viewValue !== '' ? $viewValue : null),
            'sort_order' => (int) ($body['sort_order'] ?? 0),
            'status' => (int) ($body['status'] ?? 1) === 0 ? 0 : 1,
            'updated_at' => $now,
        ];
        if ($id) {
            Db::name('home_sections')->where('id', $id)->update($data);
        } else {
            $data['created_at'] = $now;
            $id = (int) Db::name('home_sections')->insertGetId($data);
        }
        if (array_key_exists('product_ids', $body) || array_key_exists('product_codes', $body)) {
            $this->replaceProducts($id, $body);
        }
        $row = Db::name('home_sections')->where('id', $id)->find();

        return $this->presentSection($row, true);
    }

    public function delete(int $id): array
    {
        $row = Db::name('home_sections')->where('id', $id)->find();
        if (!$row) {
            throw new ApiException('NOT_FOUND', '展示區不存在', 404, 40401);
        }
        Db::name('home_section_products')->where('section_id', $id)->delete();
        Db::name('home_sections')->where('id', $id)->delete();

        return ['id' => $id, 'deleted' => true];
    }

    public function setProducts(int $sectionId, array $body): array
    {
        $row = Db::name('home_sections')->where('id', $sectionId)->find();
        if (!$row) {
            throw new ApiException('NOT_FOUND', '展示區不存在', 404, 40401);
        }
        $this->replaceProducts($sectionId, $body);

        return $this->presentSection($row, true);
    }

    private function replaceProducts(int $sectionId, array $body): void
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
        Db::name('home_section_products')->where('section_id', $sectionId)->delete();
        $now = date('Y-m-d H:i:s');
        $sort = 0;
        foreach ($ids as $pid) {
            $exists = Db::name('products')->where('id', $pid)->find();
            if (!$exists) {
                continue;
            }
            Db::name('home_section_products')->insert([
                'section_id' => $sectionId,
                'product_id' => $pid,
                'sort_order' => $sort++,
                'created_at' => $now,
            ]);
        }
    }

    private function presentSection(array $row, bool $withItems): array
    {
        $out = [
            'id' => (int) $row['id'],
            'code' => (string) $row['code'],
            'title' => [
                'zh-HK' => (string) ($row['title_zh'] ?? ''),
                'en' => (string) ($row['title_en'] ?? ''),
            ],
            'view_all_type' => (string) ($row['view_all_type'] ?? 'list'),
            'view_all_value' => $row['view_all_value'] !== null ? (string) $row['view_all_value'] : '',
            'sort_order' => (int) $row['sort_order'],
            'status' => (int) $row['status'],
        ];
        if ($withItems) {
            $out['items'] = $this->loadProductCards((int) $row['id'], (int) $row['status'] === 1);
            $out['product_ids'] = array_map(fn ($c) => (int) ($c['db_id'] ?? 0), $out['items']);
            $out['product_codes'] = array_map(fn ($c) => (string) ($c['code'] ?? $c['id'] ?? ''), $out['items']);
        }

        return $out;
    }

    /** @return list<array<string,mixed>> */
    private function loadProductCards(int $sectionId, bool $activeOnly): array
    {
        $links = Db::name('home_section_products')
            ->where('section_id', $sectionId)
            ->order('sort_order', 'asc')
            ->order('id', 'asc')
            ->select()
            ->toArray();
        if (!$links) {
            return [];
        }
        $pids = array_map(fn ($r) => (int) $r['product_id'], $links);
        $q = Db::name('products')->whereIn('id', $pids);
        if ($activeOnly) {
            $q->where('status', 1);
        }
        $products = $q->select()->toArray();
        $byId = [];
        foreach ($products as $p) {
            $byId[(int) $p['id']] = $p;
        }
        $catalog = new CatalogService();
        $mode = SoldDisplay::mode();
        $items = [];
        foreach ($links as $link) {
            $p = $byId[(int) $link['product_id']] ?? null;
            if (!$p) {
                continue;
            }
            $items[] = $catalog->presentCardPublic($p, $mode);
        }

        return $items;
    }
}
