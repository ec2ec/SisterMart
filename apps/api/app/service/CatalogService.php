<?php

declare(strict_types=1);

namespace app\service;

use app\support\ApiException;
use think\facade\Db;

class CatalogService
{
    public function categoryTree(bool $activeOnly = true): array
    {
        $query = Db::name('categories')->order('sort_order', 'asc')->order('id', 'asc');
        if ($activeOnly) {
            $query->where('status', 1);
        }
        $rows = $query->select()->toArray();
        $ids = array_column($rows, 'id');
        $i18n = $ids ? Db::name('category_i18n')->whereIn('category_id', $ids)->select()->toArray() : [];
        $names = [];
        foreach ($i18n as $row) {
            $names[$row['category_id']][$row['locale']] = $row['name'];
        }
        $nodes = [];
        foreach ($rows as $row) {
            $nodes[] = [
                'id'        => (int) $row['id'],
                'code'      => (string) $row['code'],
                'parent_id' => $row['parent_id'] !== null ? (int) $row['parent_id'] : null,
                'icon'      => $row['icon'] ?: null,
                'sort'      => (int) $row['sort_order'],
                'status'    => (int) $row['status'],
                'name'      => [
                    'zh-HK' => $names[$row['id']]['zh-HK'] ?? $row['code'],
                    'en'    => $names[$row['id']]['en'] ?? $row['code'],
                ],
                'children'  => [],
            ];
        }
        $byId = [];
        foreach ($nodes as &$node) {
            $byId[$node['id']] = &$node;
        }
        unset($node);
        $roots = [];
        foreach ($nodes as &$node) {
            if ($node['parent_id'] && isset($byId[$node['parent_id']])) {
                $byId[$node['parent_id']]['children'][] = &$node;
            } else {
                $roots[] = &$node;
            }
        }
        unset($node);

        return $roots;
    }

    public function saveCategory(array $body, ?int $id = null): array
    {
        $code = trim((string) ($body['code'] ?? ''));
        if ($code === '' || !preg_match('/^[a-z0-9-]{1,64}$/', $code)) {
            throw new ApiException('INVALID', '分類代碼需為小寫字母、數字或連字符', 422, 42201);
        }
        $nameZh = trim((string) ($body['name']['zh-HK'] ?? $body['name_zh'] ?? ''));
        $nameEn = trim((string) ($body['name']['en'] ?? $body['name_en'] ?? ''));
        if ($nameZh === '' || $nameEn === '') {
            throw new ApiException('INVALID', '請填寫雙語分類名稱', 422, 42201);
        }
        $parentId = $body['parent_id'] ?? null;
        $parentId = $parentId === null || $parentId === '' ? null : (int) $parentId;
        if ($parentId) {
            $parent = Db::name('categories')->where('id', $parentId)->find();
            if (!$parent) {
                throw new ApiException('NOT_FOUND', '上級分類不存在', 404, 40401);
            }
        }
        $dup = Db::name('categories')->where('code', $code);
        if ($id) {
            $dup->where('id', '<>', $id);
        }
        if ($dup->find()) {
            throw new ApiException('CODE_TAKEN', '分類代碼已存在', 422, 42202);
        }
        $now = date('Y-m-d H:i:s');
        $data = [
            'parent_id'  => $parentId,
            'code'       => $code,
            'icon'       => trim((string) ($body['icon'] ?? '')) ?: null,
            'sort_order' => (int) ($body['sort'] ?? 0),
            'status'     => (int) ($body['status'] ?? 1) === 0 ? 0 : 1,
            'updated_at' => $now,
        ];
        if ($id) {
            if (!Db::name('categories')->where('id', $id)->find()) {
                throw new ApiException('NOT_FOUND', '分類不存在', 404, 40401);
            }
            Db::name('categories')->where('id', $id)->update($data);
        } else {
            $data['created_at'] = $now;
            $id = (int) Db::name('categories')->insertGetId($data);
        }
        foreach (['zh-HK' => $nameZh, 'en' => $nameEn] as $locale => $name) {
            $exists = Db::name('category_i18n')->where(['category_id' => $id, 'locale' => $locale])->find();
            if ($exists) {
                Db::name('category_i18n')->where(['category_id' => $id, 'locale' => $locale])->update(['name' => $name]);
            } else {
                Db::name('category_i18n')->insert(['category_id' => $id, 'locale' => $locale, 'name' => $name]);
            }
        }

        return $this->categoryById($id);
    }

    public function categoryById(int $id): array
    {
        $row = Db::name('categories')->where('id', $id)->find();
        if (!$row) {
            throw new ApiException('NOT_FOUND', '分類不存在', 404, 40401);
        }
        $names = Db::name('category_i18n')->where('category_id', $id)->select()->toArray();
        $name = ['zh-HK' => $row['code'], 'en' => $row['code']];
        foreach ($names as $n) {
            $name[$n['locale']] = $n['name'];
        }

        return [
            'id'        => (int) $row['id'],
            'code'      => (string) $row['code'],
            'parent_id' => $row['parent_id'] !== null ? (int) $row['parent_id'] : null,
            'icon'      => $row['icon'] ?: null,
            'sort'      => (int) $row['sort_order'],
            'status'    => (int) $row['status'],
            'name'      => $name,
        ];
    }

    public function listProducts(array $query, bool $admin = false): array
    {
        $q = trim((string) ($query['q'] ?? ''));
        $category = trim((string) ($query['category'] ?? ''));
        $sort = (string) ($query['sort'] ?? 'default');
        $limit = isset($query['limit']) ? (int) $query['limit'] : 0;
        $builder = Db::name('products')->alias('p');
        if (!$admin) {
            $builder->where('p.status', 1);
        }
        if ($category !== '') {
            $ids = $this->categoryScopeIds($category);
            if (!$ids) {
                return ['items' => [], 'sold_display_mode' => SoldDisplay::mode()];
            }
            $builder->whereIn('p.category_id', $ids);
        }
        if ($q !== '') {
            $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $q) . '%';
            $idsFromName = Db::name('product_i18n')->whereLike('name', $like)->column('product_id');
            $idsFromSummary = Db::name('product_i18n')->whereLike('summary', $like)->column('product_id');
            $idsFromDetail = Db::name('product_i18n')->whereLike('detail', $like)->column('product_id');
            $idsFromCode = Db::name('products')->whereLike('code', $like)->column('id');
            $idsFromId = ctype_digit($q) ? [(int) $q] : [];
            $productIds = array_values(array_unique(array_map(
                'intval',
                array_merge(
                    $idsFromName ?: [],
                    $idsFromSummary ?: [],
                    $idsFromDetail ?: [],
                    $idsFromCode ?: [],
                    $idsFromId,
                ),
            )));
            if (!$productIds) {
                return ['items' => [], 'sold_display_mode' => SoldDisplay::mode()];
            }
            $builder->whereIn('p.id', $productIds);
        }
        $builder->field('p.*');
        match ($sort) {
            'newest' => $builder->order('p.id', 'desc'),
            'oldest' => $builder->order('p.id', 'asc'),
            'price_asc' => $builder->orderRaw('(SELECT MIN(price) FROM skus WHERE product_id = p.id AND status = 1) ASC'),
            'price_desc' => $builder->orderRaw('(SELECT MIN(price) FROM skus WHERE product_id = p.id AND status = 1) DESC'),
            'sales' => $builder->orderRaw('(p.sold_count + COALESCE(p.sold_boost, 0)) DESC')->order('p.id', 'desc'),
            // 後台默認按加入時間倒序；前台 default 仍按 id 升序
            default => $admin
                ? $builder->order('p.id', 'desc')
                : $builder->order('p.id', 'asc'),
        };
        if ($limit > 0) {
            $builder->limit(max(1, min(100, $limit)));
        }
        $rows = $builder->select()->toArray();
        $mode = SoldDisplay::mode();
        $items = array_map(fn ($row) => $this->presentCard($row, $mode), $rows);

        return ['items' => $items, 'sold_display_mode' => $mode];
    }

    /**
     * 首頁推薦：hot=熱銷 / new=新品 / for_you=猜你喜歡（足跡分類，無則回退熱銷）。
     * 先按規則有序切片（保證第 N 頁整體新/熱於第 N+1 頁），再頁內 shuffle；
     * offset 超過總量時取模回到前面（往復），每次請求重新打亂。
     *
     * @return array{
     *   tab: string,
     *   source: string,
     *   items: list<array<string,mixed>>,
     *   sold_display_mode: string,
     *   total: int,
     *   offset: int,
     *   next_offset: int,
     *   limit: int,
     *   wrapped: bool
     * }
     */
    public function homeRecommendations(string $tab, ?int $viewerMemberId = null, int $limit = 16, int $offset = 0): array
    {
        $tab = in_array($tab, ['hot', 'new', 'for_you'], true) ? $tab : 'hot';
        $limit = max(1, min(40, $limit));
        $offset = max(0, $offset);
        $mode = SoldDisplay::mode();
        $source = 'sales';
        $categoryIds = null;

        if ($tab === 'new') {
            $source = 'newest';
        } elseif ($tab === 'for_you' && $viewerMemberId) {
            $catIds = (new BrowseHistoryService())->recentCategoryIds($viewerMemberId, 5);
            if ($catIds) {
                $categoryIds = $catIds;
                $source = 'browse_categories';
            } else {
                $source = 'hot_fallback';
            }
        } elseif ($tab === 'for_you') {
            $source = 'hot_fallback';
        }

        $countQ = Db::name('products')->where('status', 1);
        if ($categoryIds) {
            $countQ->whereIn('category_id', $categoryIds);
        }
        $total = (int) $countQ->count();
        if ($total === 0) {
            return [
                'tab'               => $tab === 'for_you' ? 'for_you' : ($tab === 'new' ? 'new' : 'hot'),
                'source'            => $source,
                'items'             => [],
                'sold_display_mode' => $mode,
                'total'             => 0,
                'offset'            => $offset,
                'next_offset'       => $offset,
                'limit'             => $limit,
                'wrapped'           => false,
            ];
        }

        $wrapped = false;
        $fetchOffset = $offset;
        if ($fetchOffset >= $total) {
            $fetchOffset = $fetchOffset % $total;
            $wrapped = true;
        }

        $listQ = Db::name('products')->where('status', 1);
        if ($categoryIds) {
            $listQ->whereIn('category_id', $categoryIds);
        }
        if ($tab === 'new') {
            $listQ->order('id', 'desc');
        } else {
            $listQ->orderRaw('(sold_count + COALESCE(sold_boost, 0)) DESC')->order('id', 'desc');
        }
        // ThinkPHP: limit(offset, length)
        $rows = $listQ->limit($fetchOffset, $limit)->select()->toArray();

        // 頁內打亂：集合仍屬該有序切片，順序每次不同
        if (count($rows) > 1) {
            shuffle($rows);
        }

        return [
            'tab'               => $tab === 'for_you' ? 'for_you' : ($tab === 'new' ? 'new' : 'hot'),
            'source'            => $source,
            'items'             => array_map(fn ($r) => $this->presentCard($r, $mode), $rows),
            'sold_display_mode' => $mode,
            'total'             => $total,
            'offset'            => $offset,
            'next_offset'       => $offset + count($rows),
            'limit'             => $limit,
            'wrapped'           => $wrapped,
        ];
    }

    public function productDetail(string $idOrCode, bool $admin = false, ?int $viewerMemberId = null): array
    {
        $row = $this->findProduct($idOrCode);
        if (!$admin && (int) $row['status'] !== 1) {
            throw new ApiException('NOT_FOUND', '商品不存在或已下架', 404, 40402);
        }

        return $this->presentDetail($row, SoldDisplay::mode(), $viewerMemberId, $admin);
    }

    public function saveProduct(array $body, ?int $id = null): array
    {
        $code = trim((string) ($body['code'] ?? ''));
        if ($code === '' || !preg_match('/^[a-zA-Z0-9_-]{1,64}$/', $code)) {
            throw new ApiException('INVALID', '商品代碼不正確', 422, 42201);
        }
        $nameZh = trim((string) ($body['name']['zh-HK'] ?? ''));
        $nameEn = trim((string) ($body['name']['en'] ?? ''));
        $detailZh = (string) ($body['detail']['zh-HK'] ?? '');
        $detailEn = (string) ($body['detail']['en'] ?? '');
        if ($nameZh === '' || $nameEn === '') {
            throw new ApiException('INVALID', '請填寫雙語商品名稱', 422, 42201);
        }
        $categoryId = (int) ($body['category_id'] ?? 0);
        if (!Db::name('categories')->where('id', $categoryId)->find()) {
            throw new ApiException('INVALID', '請選擇分類', 422, 42201);
        }
        $dup = Db::name('products')->where('code', $code);
        if ($id) {
            $dup->where('id', '<>', $id);
        }
        if ($dup->find()) {
            throw new ApiException('CODE_TAKEN', '商品代碼已存在', 422, 42202);
        }
        $media = $body['media'] ?? [];
        if (!is_array($media) || $media === []) {
            throw new ApiException('INVALID', '請至少上傳一張商品圖片', 422, 42201);
        }
        $skus = $body['skus'] ?? [];
        if (!is_array($skus) || $skus === []) {
            throw new ApiException('INVALID', '請至少配置一個 SKU', 422, 42201);
        }
        $groups = $body['spec_groups'] ?? [];
        if (!is_array($groups) || $groups === []) {
            throw new ApiException('INVALID', '請配置規格', 422, 42201);
        }

        $now = date('Y-m-d H:i:s');
        $cover = null;
        foreach ($media as $item) {
            if (($item['media_type'] ?? 'image') === 'image') {
                $cover = $this->normalizeStoredMediaUrl(trim((string) ($item['url'] ?? '')));
                if ($cover !== '') {
                    break;
                }
            }
        }

        $commissionType = $body['commission_type'] ?? null;
        $commissionType = $commissionType === '' || $commissionType === null ? null : (string) $commissionType;
        if ($commissionType !== null && !in_array($commissionType, ['percent', 'fixed'], true)) {
            throw new ApiException('INVALID', '佣金類型須為 percent / fixed / 空', 422, 42201);
        }
        $commissionValue = $commissionType === null ? null : round((float) ($body['commission_value'] ?? 0), 2);
        if ($commissionType !== null && $commissionValue !== null && $commissionValue < 0) {
            throw new ApiException('INVALID', '佣金值不可為負', 422, 42201);
        }

        $soldBoost = max(0, (int) ($body['sold_boost'] ?? 0));

        Db::transaction(function () use ($body, &$id, $code, $categoryId, $nameZh, $nameEn, $detailZh, $detailEn, $media, $groups, $skus, $cover, $now, $commissionType, $commissionValue, $soldBoost) {
            $data = [
                'code'        => $code,
                'category_id' => $categoryId,
                'status'      => (int) ($body['status'] ?? 1) === 0 ? 0 : 1,
                'sold_boost'  => $soldBoost,
                'cover_url'   => $cover ?: null,
                'commission_type' => $commissionType,
                'commission_value' => $commissionValue,
                'updated_at'  => $now,
            ];
            if ($id) {
                if (!Db::name('products')->where('id', $id)->find()) {
                    throw new ApiException('NOT_FOUND', '商品不存在', 404, 40402);
                }
                // sold_count 真實銷售只由訂單加減，後台不可改
                Db::name('products')->where('id', $id)->update($data);
            } else {
                $data['sold_count'] = 0;
                $data['created_at'] = $now;
                $id = (int) Db::name('products')->insertGetId($data);
            }

            foreach (['zh-HK' => [$nameZh, $detailZh], 'en' => [$nameEn, $detailEn]] as $locale => [$name, $detail]) {
                $payload = ['name' => $name, 'summary' => (string) ($body['summary'][$locale] ?? ''), 'detail' => $detail];
                $exists = Db::name('product_i18n')->where(['product_id' => $id, 'locale' => $locale])->find();
                if ($exists) {
                    Db::name('product_i18n')->where(['product_id' => $id, 'locale' => $locale])->update($payload);
                } else {
                    Db::name('product_i18n')->insert(array_merge(['product_id' => $id, 'locale' => $locale], $payload));
                }
            }

            Db::name('product_media')->where('product_id', $id)->delete();
            foreach (array_values($media) as $i => $item) {
                $url = $this->normalizeStoredMediaUrl(trim((string) ($item['url'] ?? '')));
                if ($url === '') {
                    continue;
                }
                Db::name('product_media')->insert([
                    'product_id' => $id,
                    'media_type' => (($item['media_type'] ?? 'image') === 'video') ? 'video' : 'image',
                    'url'        => $url,
                    'sort_order' => $i,
                ]);
            }

            $allowedBadges = Db::name('product_badge_defs')->where('status', 1)->column('code');
            $allowedBadges = array_map('strval', $allowedBadges ?: []);
            Db::name('product_badges')->where('product_id', $id)->delete();
            foreach (($body['badges'] ?? []) as $badge) {
                $badge = (string) $badge;
                if (in_array($badge, $allowedBadges, true)) {
                    Db::name('product_badges')->insert(['product_id' => $id, 'badge' => $badge]);
                }
            }

            Db::name('product_service_tags')->where('product_id', $id)->delete();
            foreach (($body['service_tag_ids'] ?? []) as $tagId) {
                Db::name('product_service_tags')->insert(['product_id' => $id, 'service_tag_id' => (int) $tagId]);
            }

            if (array_key_exists('attr_value_ids', $body)) {
                Db::name('product_attr_links')->where('product_id', $id)->delete();
                $valueIds = is_array($body['attr_value_ids']) ? $body['attr_value_ids'] : [];
                $seen = [];
                foreach ($valueIds as $vid) {
                    $vid = (int) $vid;
                    if ($vid <= 0 || isset($seen[$vid])) {
                        continue;
                    }
                    $ok = Db::name('product_attr_values')->alias('v')
                        ->join('product_attrs a', 'a.id = v.attr_id')
                        ->where('v.id', $vid)
                        ->where('v.status', 1)
                        ->where('a.status', 1)
                        ->find();
                    if (!$ok) {
                        continue;
                    }
                    $seen[$vid] = true;
                    Db::name('product_attr_links')->insert([
                        'product_id' => $id,
                        'attr_value_id' => $vid,
                    ]);
                }
            }

            $oldGroups = Db::name('spec_groups')->where('product_id', $id)->column('id');
            if ($oldGroups) {
                $oldOptions = Db::name('spec_options')->whereIn('spec_group_id', $oldGroups)->column('id');
                if ($oldOptions) {
                    Db::name('spec_option_i18n')->whereIn('spec_option_id', $oldOptions)->delete();
                    Db::name('sku_options')->whereIn('spec_option_id', $oldOptions)->delete();
                    Db::name('spec_options')->whereIn('id', $oldOptions)->delete();
                }
                Db::name('spec_group_i18n')->whereIn('spec_group_id', $oldGroups)->delete();
                Db::name('spec_groups')->whereIn('id', $oldGroups)->delete();
            }
            $oldSkus = Db::name('skus')->where('product_id', $id)->column('id');
            if ($oldSkus) {
                Db::name('sku_options')->whereIn('sku_id', $oldSkus)->delete();
                Db::name('skus')->whereIn('id', $oldSkus)->delete();
            }

            $optionMap = [];
            foreach (array_values($groups) as $gi => $group) {
                $gcode = trim((string) ($group['code'] ?? 'g' . $gi));
                $gid = (int) Db::name('spec_groups')->insertGetId([
                    'product_id' => $id, 'code' => $gcode, 'sort_order' => $gi,
                ]);
                foreach (['zh-HK', 'en'] as $locale) {
                    Db::name('spec_group_i18n')->insert([
                        'spec_group_id' => $gid,
                        'locale'        => $locale,
                        'name'          => trim((string) ($group['name'][$locale] ?? $gcode)),
                    ]);
                }
                foreach (array_values($group['options'] ?? []) as $oi => $opt) {
                    $ocode = trim((string) ($opt['code'] ?? 'o' . $oi));
                    $oid = (int) Db::name('spec_options')->insertGetId([
                        'spec_group_id' => $gid,
                        'code'          => $ocode,
                        'image_url'     => trim((string) ($opt['image'] ?? '')) ?: null,
                        'sort_order'    => $oi,
                    ]);
                    foreach (['zh-HK', 'en'] as $locale) {
                        Db::name('spec_option_i18n')->insert([
                            'spec_option_id' => $oid,
                            'locale'         => $locale,
                            'name'           => trim((string) ($opt['name'][$locale] ?? $ocode)),
                        ]);
                    }
                    $optionMap[$gcode . ':' . $ocode] = $oid;
                }
            }

            foreach (array_values($skus) as $sku) {
                $scode = trim((string) ($sku['code'] ?? ''));
                if ($scode === '') {
                    throw new ApiException('INVALID', 'SKU 代碼不能為空', 422, 42201);
                }
                $sid = (int) Db::name('skus')->insertGetId([
                    'product_id' => $id,
                    'code'       => $scode,
                    'price'      => round((float) ($sku['price'] ?? 0), 2),
                    'compare_at' => round((float) ($sku['compare_at'] ?? 0), 2),
                    'agent_price' => round((float) ($sku['agent_price'] ?? 0), 2),
                    'cost_price' => round((float) ($sku['cost_price'] ?? 0), 2),
                    'stock'      => max(0, (int) ($sku['stock'] ?? 0)),
                    'image_url'  => trim((string) ($sku['image'] ?? '')) ?: null,
                    'status'     => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                foreach (($sku['option_refs'] ?? []) as $ref) {
                    if (!isset($optionMap[$ref])) {
                        throw new ApiException('INVALID', 'SKU 規格引用不正確: ' . $ref, 422, 42201);
                    }
                    Db::name('sku_options')->insert([
                        'sku_id' => $sid, 'spec_option_id' => $optionMap[$ref],
                    ]);
                }
            }
        });

        return $this->productDetail((string) $id, true);
    }

    public function serviceTags(): array
    {
        $rows = Db::name('service_tags')->where('status', 1)->order('sort_order', 'asc')->select()->toArray();
        $out = [];
        foreach ($rows as $row) {
            $names = Db::name('service_tag_i18n')->where('service_tag_id', $row['id'])->select()->toArray();
            $name = ['zh-HK' => $row['code'], 'en' => $row['code']];
            foreach ($names as $n) {
                $name[$n['locale']] = $n['name'];
            }
            $out[] = ['id' => (int) $row['id'], 'code' => $row['code'], 'name' => $name];
        }

        return $out;
    }

    public function soldMode(?string $mode = null): array
    {
        if ($mode !== null) {
            if (!in_array($mode, ['exact', 'fuzzy'], true)) {
                throw new ApiException('INVALID', '已售展示只能是 exact 或 fuzzy', 422, 42201);
            }
            $now = date('Y-m-d H:i:s');
            $exists = Db::name('shop_settings')->where('setting_key', 'sold.display_mode')->find();
            if ($exists) {
                Db::name('shop_settings')->where('setting_key', 'sold.display_mode')->update([
                    'setting_value' => $mode, 'updated_at' => $now,
                ]);
            } else {
                Db::name('shop_settings')->insert([
                    'setting_key' => 'sold.display_mode', 'setting_value' => $mode, 'updated_at' => $now,
                ]);
            }
        }

        return ['sold_display_mode' => SoldDisplay::mode()];
    }

    private function findProduct(string $idOrCode): array
    {
        $row = ctype_digit($idOrCode)
            ? Db::name('products')->where('id', (int) $idOrCode)->find()
            : Db::name('products')->where('code', $idOrCode)->find();
        if (!$row) {
            throw new ApiException('NOT_FOUND', '商品不存在', 404, 40402);
        }

        return $row;
    }

    private function categoryScopeIds(string $category): array
    {
        $row = ctype_digit($category)
            ? Db::name('categories')->where('id', (int) $category)->find()
            : Db::name('categories')->where('code', $category)->find();
        if (!$row) {
            return [];
        }
        $ids = [(int) $row['id']];
        $frontier = $ids;
        while ($frontier) {
            $children = Db::name('categories')->whereIn('parent_id', $frontier)->column('id');
            $frontier = [];
            foreach ($children as $child) {
                $child = (int) $child;
                if (!in_array($child, $ids, true)) {
                    $ids[] = $child;
                    $frontier[] = $child;
                }
            }
        }

        return $ids;
    }

    /**
     * 落庫媒體 URL：files 代理 / 絕對地址 → /storage/...
     */
    private function normalizeStoredMediaUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }
        if (preg_match('#/api/v1/files\?path=([^&]+)#i', $url, $m)) {
            return '/storage/' . ltrim(rawurldecode($m[1]), '/');
        }
        if (preg_match('#https?://[^/]+(/storage/.+)$#i', $url, $m)) {
            return $m[1];
        }
        if (str_starts_with($url, 'storage/')) {
            return '/' . $url;
        }

        return $url;
    }

    /** @param array<string,mixed> $row */
    public function presentCardPublic(array $row, ?string $mode = null): array
    {
        return $this->presentCard($row, $mode ?? SoldDisplay::mode());
    }

    private function presentCard(array $row, string $mode): array
    {
        $names = Db::name('product_i18n')->where('product_id', $row['id'])->select()->toArray();
        $name = ['zh-HK' => $row['code'], 'en' => $row['code']];
        foreach ($names as $n) {
            $name[$n['locale']] = $n['name'];
        }
        $price = (float) (Db::name('skus')->where('product_id', $row['id'])->where('status', 1)->min('price') ?: 0);
        $stock = (int) (Db::name('skus')->where('product_id', $row['id'])->where('status', 1)->sum('stock') ?: 0);
        $badgeItems = $this->presentBadgeItems((int) $row['id']);
        $badges = array_map(static fn ($b) => (string) $b['code'], $badgeItems);
        $cat = Db::name('categories')->where('id', $row['category_id'])->find();
        $categoryName = ['zh-HK' => '', 'en' => ''];
        if ($cat) {
            $catNames = Db::name('category_i18n')->where('category_id', $cat['id'])->select()->toArray();
            foreach ($catNames as $cn) {
                $categoryName[$cn['locale']] = $cn['name'];
            }
            if ($categoryName['zh-HK'] === '' && $categoryName['en'] === '') {
                $categoryName['zh-HK'] = (string) ($cat['code'] ?? '');
                $categoryName['en'] = (string) ($cat['code'] ?? '');
            }
        }
        $image = (string) ($row['cover_url'] ?: '');
        if ($image === '') {
            $image = (string) (Db::name('product_media')
                ->where('product_id', $row['id'])
                ->where('media_type', 'image')
                ->order('sort_order', 'asc')
                ->value('url') ?: '');
        }
        $image = $this->normalizeStoredMediaUrl($image);

        return [
            'id'       => (string) $row['code'],
            'db_id'    => (int) $row['id'],
            'code'     => (string) $row['code'],
            'name'     => $name,
            'price'    => $price,
            'sold'     => SoldDisplay::labelFromProduct($row, $mode),
            'sold_count' => (int) ($row['sold_count'] ?? 0),
            'sold_boost' => (int) ($row['sold_boost'] ?? 0),
            'rating'   => round((float) ($row['rating_avg'] ?? 0), 1),
            'rating_count' => (int) ($row['rating_count'] ?? 0),
            'stock'    => $stock,
            'image'    => $image,
            'category' => $cat['code'] ?? '',
            'category_id' => (int) $row['category_id'],
            'category_name' => $categoryName,
            'badges'   => $badges,
            'badge_items' => $badgeItems,
            'status'   => (int) $row['status'],
            'commission_type' => $row['commission_type'] ?? null,
            'commission_value' => isset($row['commission_value']) && $row['commission_value'] !== null
                ? (float) $row['commission_value']
                : null,
        ];
    }

    /**
     * @return list<array{code: string, color: string, name: array{zh-HK: string, en: string}}>
     */
    private function presentBadgeItems(int $productId): array
    {
        $codes = Db::name('product_badges')->where('product_id', $productId)->column('badge');
        if (!$codes) {
            return [];
        }
        $defs = Db::name('product_badge_defs')
            ->whereIn('code', $codes)
            ->where('status', 1)
            ->order('sort_order', 'asc')
            ->order('id', 'asc')
            ->select()
            ->toArray();
        $out = [];
        foreach ($defs as $def) {
            $names = Db::name('product_badge_def_i18n')->where('badge_def_id', $def['id'])->select()->toArray();
            $name = ['zh-HK' => (string) $def['code'], 'en' => (string) $def['code']];
            foreach ($names as $n) {
                $name[$n['locale']] = $n['name'];
            }
            $out[] = [
                'code' => (string) $def['code'],
                'color' => (string) $def['color'],
                'name' => $name,
            ];
        }

        return $out;
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function presentProductAttrs(int $productId): array
    {
        $valueIds = Db::name('product_attr_links')->where('product_id', $productId)->column('attr_value_id');
        if (!$valueIds) {
            return [];
        }
        $values = Db::name('product_attr_values')
            ->whereIn('id', $valueIds)
            ->where('status', 1)
            ->order('sort_order', 'asc')
            ->order('id', 'asc')
            ->select()
            ->toArray();
        if (!$values) {
            return [];
        }
        $byAttr = [];
        foreach ($values as $v) {
            $byAttr[(int) $v['attr_id']][] = $v;
        }
        $attrs = Db::name('product_attrs')
            ->whereIn('id', array_keys($byAttr))
            ->where('status', 1)
            ->order('sort_order', 'asc')
            ->order('id', 'asc')
            ->select()
            ->toArray();
        $out = [];
        foreach ($attrs as $attr) {
            $aid = (int) $attr['id'];
            $names = Db::name('product_attr_i18n')->where('attr_id', $aid)->select()->toArray();
            $name = ['zh-HK' => (string) $attr['code'], 'en' => (string) $attr['code']];
            foreach ($names as $n) {
                $name[$n['locale']] = $n['name'];
            }
            $valsOut = [];
            foreach ($byAttr[$aid] ?? [] as $v) {
                $vid = (int) $v['id'];
                $vNames = Db::name('product_attr_value_i18n')->where('attr_value_id', $vid)->select()->toArray();
                $vName = ['zh-HK' => (string) $v['code'], 'en' => (string) $v['code']];
                foreach ($vNames as $n) {
                    $vName[$n['locale']] = $n['name'];
                }
                $valsOut[] = [
                    'id' => $vid,
                    'code' => (string) $v['code'],
                    'name' => $vName,
                ];
            }
            if ($valsOut === []) {
                continue;
            }
            $out[] = [
                'id' => $aid,
                'code' => (string) $attr['code'],
                'name' => $name,
                'values' => $valsOut,
            ];
        }

        return $out;
    }

    private function presentDetail(array $row, string $mode, ?int $viewerMemberId = null, bool $admin = false): array
    {
        $card = $this->presentCard($row, $mode);
        $i18n = Db::name('product_i18n')->where('product_id', $row['id'])->select()->toArray();
        $detail = ['zh-HK' => '', 'en' => ''];
        $summary = ['zh-HK' => '', 'en' => ''];
        foreach ($i18n as $n) {
            $detail[$n['locale']] = $n['detail'];
            $summary[$n['locale']] = $n['summary'];
        }
        $media = Db::name('product_media')->where('product_id', $row['id'])->order('sort_order', 'asc')->select()->toArray();
        $gallery = [];
        $mediaOut = [];
        foreach ($media as $m) {
            $url = $this->normalizeStoredMediaUrl((string) ($m['url'] ?? ''));
            $mediaOut[] = [
                'media_type' => $m['media_type'],
                'url'        => $url,
                'sort'       => (int) $m['sort_order'],
            ];
            if ($m['media_type'] === 'image' && $url !== '') {
                $gallery[] = $url;
            }
        }
        $groups = Db::name('spec_groups')->where('product_id', $row['id'])->order('sort_order', 'asc')->select()->toArray();
        $groupsOut = [];
        foreach ($groups as $g) {
            $gNames = Db::name('spec_group_i18n')->where('spec_group_id', $g['id'])->select()->toArray();
            $gName = ['zh-HK' => $g['code'], 'en' => $g['code']];
            foreach ($gNames as $n) {
                $gName[$n['locale']] = $n['name'];
            }
            $options = Db::name('spec_options')->where('spec_group_id', $g['id'])->order('sort_order', 'asc')->select()->toArray();
            $optsOut = [];
            foreach ($options as $o) {
                $oNames = Db::name('spec_option_i18n')->where('spec_option_id', $o['id'])->select()->toArray();
                $oName = ['zh-HK' => $o['code'], 'en' => $o['code']];
                foreach ($oNames as $n) {
                    $oName[$n['locale']] = $n['name'];
                }
                $optsOut[] = [
                    'id'    => (string) $o['code'],
                    'code'  => (string) $o['code'],
                    'name'  => $oName,
                    'image' => $o['image_url'] ?: null,
                ];
            }
            $groupsOut[] = [
                'id'      => (string) $g['code'],
                'code'    => (string) $g['code'],
                'name'    => $gName,
                'options' => $optsOut,
            ];
        }

        $skus = Db::name('skus')->where('product_id', $row['id'])->where('status', 1)->select()->toArray();
        $skusOut = [];
        foreach ($skus as $sku) {
            $optionIds = Db::name('sku_options')->where('sku_id', $sku['id'])->column('spec_option_id');
            $refs = [];
            $selected = [];
            if ($optionIds) {
                $opts = Db::name('spec_options')->alias('o')
                    ->join('spec_groups g', 'g.id = o.spec_group_id')
                    ->whereIn('o.id', $optionIds)
                    ->field('g.code as gcode, o.code as ocode')
                    ->select()
                    ->toArray();
                foreach ($opts as $opt) {
                    $refs[] = $opt['gcode'] . ':' . $opt['ocode'];
                    $selected[$opt['gcode']] = $opt['ocode'];
                }
            }
            $skuRow = [
                'id'          => (int) $sku['id'],
                'code'        => (string) $sku['code'],
                'key'         => (string) $sku['code'],
                'price'       => (float) $sku['price'],
                'compare_at'  => (float) $sku['compare_at'],
                'stock'       => (int) $sku['stock'],
                'image'       => $sku['image_url'] ?: null,
                'option_refs' => $refs,
                'selected'    => $selected,
            ];
            if ($admin) {
                $skuRow['agent_price'] = (float) ($sku['agent_price'] ?? 0);
                $skuBag['cost_price'] = (float) ($sku['cost_price'] ?? 0);
            }
            $skusOut[] = $skuRow;
        }

        $tagIds = Db::name('product_service_tags')->where('product_id', $row['id'])->column('service_tag_id');
        $tags = [];
        if ($tagIds) {
            foreach ($this->serviceTags() as $tag) {
                if (in_array($tag['id'], array_map('intval', $tagIds), true)) {
                    $tags[] = $tag['name'];
                }
            }
        }

        $attrValueIds = array_map(
            'intval',
            Db::name('product_attr_links')->where('product_id', $row['id'])->column('attr_value_id') ?: [],
        );

        return array_merge($card, [
            'summary'         => $summary,
            'detail'          => $detail,
            'gallery'         => $gallery,
            'media'           => $mediaOut,
            'spec_groups'     => $groupsOut,
            'skus'            => $skusOut,
            'tags'            => $tags,
            'service_tag_ids' => array_map('intval', $tagIds),
            'attrs'           => $this->presentProductAttrs((int) $row['id']),
            'attr_value_ids'  => $attrValueIds,
            'reviews'         => (new ReviewService())->listForProduct((int) $row['id'], $viewerMemberId),
        ]);
    }
}
