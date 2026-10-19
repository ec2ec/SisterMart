<?php

declare(strict_types=1);

namespace app\service;

use app\support\ApiException;
use think\facade\Db;

/**
 * 角標 / 服務標籤 / 商品參數 主數據 CRUD。
 */
class CatalogMetaService
{
    /** @return list<array<string,mixed>> */
    public function listBadges(bool $activeOnly = false): array
    {
        $q = Db::name('product_badge_defs')->order('sort_order', 'asc')->order('id', 'asc');
        if ($activeOnly) {
            $q->where('status', 1);
        }
        $rows = $q->select()->toArray();
        $out = [];
        foreach ($rows as $row) {
            $out[] = $this->presentBadge($row);
        }

        return $out;
    }

    public function saveBadge(array $body, ?int $id = null): array
    {
        $code = trim((string) ($body['code'] ?? ''));
        if ($code === '' || !preg_match('/^[a-z][a-z0-9_\-]{0,30}$/', $code)) {
            throw new ApiException('INVALID', '角標代碼須為小寫字母開頭', 422, 42201);
        }
        $nameZh = trim((string) ($body['name']['zh-HK'] ?? ''));
        $nameEn = trim((string) ($body['name']['en'] ?? ''));
        if ($nameZh === '' || $nameEn === '') {
            throw new ApiException('INVALID', '請填寫角標中英文名稱', 422, 42201);
        }
        $color = trim((string) ($body['color'] ?? '#16162b'));
        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
            throw new ApiException('INVALID', '顏色須為 #RRGGBB', 422, 42201);
        }
        $dup = Db::name('product_badge_defs')->where('code', $code);
        if ($id) {
            $dup->where('id', '<>', $id);
        }
        if ($dup->find()) {
            throw new ApiException('CONFLICT', '角標代碼已存在', 409, 40901);
        }
        $now = date('Y-m-d H:i:s');
        $data = [
            'code' => $code,
            'color' => strtolower($color),
            'sort_order' => (int) ($body['sort'] ?? $body['sort_order'] ?? 0),
            'status' => (int) ($body['status'] ?? 1) === 0 ? 0 : 1,
            'updated_at' => $now,
        ];
        if ($id) {
            if (!Db::name('product_badge_defs')->where('id', $id)->find()) {
                throw new ApiException('NOT_FOUND', '角標不存在', 404, 40402);
            }
            $old = Db::name('product_badge_defs')->where('id', $id)->find();
            Db::name('product_badge_defs')->where('id', $id)->update($data);
            if ($old && $old['code'] !== $code) {
                Db::name('product_badges')->where('badge', $old['code'])->update(['badge' => $code]);
            }
        } else {
            $data['created_at'] = $now;
            $id = (int) Db::name('product_badge_defs')->insertGetId($data);
        }
        $this->upsertI18n('product_badge_def_i18n', 'badge_def_id', $id, $nameZh, $nameEn);

        return $this->presentBadge(Db::name('product_badge_defs')->where('id', $id)->find());
    }

    /** @return list<array<string,mixed>> */
    public function listServiceTags(bool $activeOnly = false): array
    {
        $q = Db::name('service_tags')->order('sort_order', 'asc')->order('id', 'asc');
        if ($activeOnly) {
            $q->where('status', 1);
        }
        $rows = $q->select()->toArray();
        $out = [];
        foreach ($rows as $row) {
            $out[] = $this->presentServiceTag($row);
        }

        return $out;
    }

    public function saveServiceTag(array $body, ?int $id = null): array
    {
        $code = trim((string) ($body['code'] ?? ''));
        if ($code === '' || !preg_match('/^[a-z][a-z0-9_\-]{0,62}$/', $code)) {
            throw new ApiException('INVALID', '標籤代碼須為小寫字母開頭', 422, 42201);
        }
        $nameZh = trim((string) ($body['name']['zh-HK'] ?? ''));
        $nameEn = trim((string) ($body['name']['en'] ?? ''));
        if ($nameZh === '' || $nameEn === '') {
            throw new ApiException('INVALID', '請填寫中英文名稱', 422, 42201);
        }
        $dup = Db::name('service_tags')->where('code', $code);
        if ($id) {
            $dup->where('id', '<>', $id);
        }
        if ($dup->find()) {
            throw new ApiException('CONFLICT', '代碼已存在', 409, 40901);
        }
        $data = [
            'code' => $code,
            'sort_order' => (int) ($body['sort'] ?? $body['sort_order'] ?? 0),
            'status' => (int) ($body['status'] ?? 1) === 0 ? 0 : 1,
        ];
        if ($id) {
            if (!Db::name('service_tags')->where('id', $id)->find()) {
                throw new ApiException('NOT_FOUND', '服務標籤不存在', 404, 40402);
            }
            Db::name('service_tags')->where('id', $id)->update($data);
        } else {
            $id = (int) Db::name('service_tags')->insertGetId($data);
        }
        $this->upsertI18n('service_tag_i18n', 'service_tag_id', $id, $nameZh, $nameEn);

        return $this->presentServiceTag(Db::name('service_tags')->where('id', $id)->find());
    }

    /** @return list<array<string,mixed>> */
    public function listAttrs(bool $activeOnly = false): array
    {
        $q = Db::name('product_attrs')->order('sort_order', 'asc')->order('id', 'asc');
        if ($activeOnly) {
            $q->where('status', 1);
        }
        $rows = $q->select()->toArray();
        $out = [];
        foreach ($rows as $row) {
            $out[] = $this->presentAttr($row, $activeOnly);
        }

        return $out;
    }

    public function saveAttr(array $body, ?int $id = null): array
    {
        $code = trim((string) ($body['code'] ?? ''));
        if ($code === '' || !preg_match('/^[a-z][a-z0-9_\-]{0,62}$/', $code)) {
            throw new ApiException('INVALID', '參數代碼須為小寫字母開頭', 422, 42201);
        }
        $nameZh = trim((string) ($body['name']['zh-HK'] ?? ''));
        $nameEn = trim((string) ($body['name']['en'] ?? ''));
        if ($nameZh === '' || $nameEn === '') {
            throw new ApiException('INVALID', '請填寫參數中英文名稱', 422, 42201);
        }
        $dup = Db::name('product_attrs')->where('code', $code);
        if ($id) {
            $dup->where('id', '<>', $id);
        }
        if ($dup->find()) {
            throw new ApiException('CONFLICT', '參數代碼已存在', 409, 40901);
        }
        $now = date('Y-m-d H:i:s');
        $data = [
            'code' => $code,
            'sort_order' => (int) ($body['sort'] ?? $body['sort_order'] ?? 0),
            'status' => (int) ($body['status'] ?? 1) === 0 ? 0 : 1,
            'updated_at' => $now,
        ];
        if ($id) {
            if (!Db::name('product_attrs')->where('id', $id)->find()) {
                throw new ApiException('NOT_FOUND', '參數不存在', 404, 40402);
            }
            Db::name('product_attrs')->where('id', $id)->update($data);
        } else {
            $data['created_at'] = $now;
            $id = (int) Db::name('product_attrs')->insertGetId($data);
        }
        $this->upsertI18n('product_attr_i18n', 'attr_id', $id, $nameZh, $nameEn);

        $values = $body['values'] ?? null;
        if (is_array($values)) {
            $this->syncAttrValues($id, $values);
        }

        return $this->presentAttr(Db::name('product_attrs')->where('id', $id)->find(), false);
    }

    public function saveAttrValue(int $attrId, array $body, ?int $valueId = null): array
    {
        if (!Db::name('product_attrs')->where('id', $attrId)->find()) {
            throw new ApiException('NOT_FOUND', '參數不存在', 404, 40402);
        }
        $code = trim((string) ($body['code'] ?? ''));
        if ($code === '' || !preg_match('/^[a-z][a-z0-9_\-]{0,62}$/', $code)) {
            throw new ApiException('INVALID', '參數值代碼無效', 422, 42201);
        }
        $nameZh = trim((string) ($body['name']['zh-HK'] ?? ''));
        $nameEn = trim((string) ($body['name']['en'] ?? ''));
        if ($nameZh === '' || $nameEn === '') {
            throw new ApiException('INVALID', '請填寫參數值中英文', 422, 42201);
        }
        $dup = Db::name('product_attr_values')->where(['attr_id' => $attrId, 'code' => $code]);
        if ($valueId) {
            $dup->where('id', '<>', $valueId);
        }
        if ($dup->find()) {
            throw new ApiException('CONFLICT', '參數值代碼已存在', 409, 40901);
        }
        $data = [
            'attr_id' => $attrId,
            'code' => $code,
            'sort_order' => (int) ($body['sort'] ?? $body['sort_order'] ?? 0),
            'status' => (int) ($body['status'] ?? 1) === 0 ? 0 : 1,
        ];
        if ($valueId) {
            if (!Db::name('product_attr_values')->where(['id' => $valueId, 'attr_id' => $attrId])->find()) {
                throw new ApiException('NOT_FOUND', '參數值不存在', 404, 40402);
            }
            Db::name('product_attr_values')->where('id', $valueId)->update($data);
        } else {
            $valueId = (int) Db::name('product_attr_values')->insertGetId($data);
        }
        $this->upsertI18n('product_attr_value_i18n', 'attr_value_id', $valueId, $nameZh, $nameEn);

        return $this->presentAttrValue(Db::name('product_attr_values')->where('id', $valueId)->find());
    }

    public function deleteAttrValue(int $attrId, int $valueId): array
    {
        $row = Db::name('product_attr_values')->where(['id' => $valueId, 'attr_id' => $attrId])->find();
        if (!$row) {
            throw new ApiException('NOT_FOUND', '參數值不存在', 404, 40402);
        }
        Db::name('product_attr_links')->where('attr_value_id', $valueId)->delete();
        Db::name('product_attr_value_i18n')->where('attr_value_id', $valueId)->delete();
        Db::name('product_attr_values')->where('id', $valueId)->delete();

        return ['ok' => true];
    }

    /** @param list<array<string,mixed>> $values */
    private function syncAttrValues(int $attrId, array $values): void
    {
        $keep = [];
        foreach ($values as $i => $v) {
            if (!is_array($v)) {
                continue;
            }
            $vid = isset($v['id']) ? (int) $v['id'] : 0;
            $payload = [
                'code' => $v['code'] ?? ('v' . ($i + 1)),
                'name' => $v['name'] ?? ['zh-HK' => '', 'en' => ''],
                'sort' => $v['sort'] ?? $v['sort_order'] ?? $i,
                'status' => $v['status'] ?? 1,
            ];
            $saved = $this->saveAttrValue($attrId, $payload, $vid > 0 ? $vid : null);
            $keep[] = (int) $saved['id'];
        }
        $existing = Db::name('product_attr_values')->where('attr_id', $attrId)->column('id');
        foreach ($existing as $eid) {
            $eid = (int) $eid;
            if (!in_array($eid, $keep, true)) {
                $this->deleteAttrValue($attrId, $eid);
            }
        }
    }

    private function upsertI18n(string $table, string $fk, int $id, string $zh, string $en): void
    {
        foreach (['zh-HK' => $zh, 'en' => $en] as $locale => $name) {
            $exists = Db::name($table)->where([$fk => $id, 'locale' => $locale])->find();
            if ($exists) {
                Db::name($table)->where([$fk => $id, 'locale' => $locale])->update(['name' => $name]);
            } else {
                Db::name($table)->insert([$fk => $id, 'locale' => $locale, 'name' => $name]);
            }
        }
    }

    /** @param array<string,mixed> $row */
    private function presentBadge(array $row): array
    {
        $id = (int) $row['id'];
        $names = Db::name('product_badge_def_i18n')->where('badge_def_id', $id)->select()->toArray();
        $name = ['zh-HK' => $row['code'], 'en' => $row['code']];
        foreach ($names as $n) {
            $name[$n['locale']] = $n['name'];
        }

        return [
            'id' => $id,
            'code' => (string) $row['code'],
            'color' => (string) $row['color'],
            'sort' => (int) $row['sort_order'],
            'status' => (int) $row['status'],
            'name' => $name,
        ];
    }

    /** @param array<string,mixed> $row */
    private function presentServiceTag(array $row): array
    {
        $id = (int) $row['id'];
        $names = Db::name('service_tag_i18n')->where('service_tag_id', $id)->select()->toArray();
        $name = ['zh-HK' => $row['code'], 'en' => $row['code']];
        foreach ($names as $n) {
            $name[$n['locale']] = $n['name'];
        }

        return [
            'id' => $id,
            'code' => (string) $row['code'],
            'sort' => (int) $row['sort_order'],
            'status' => (int) $row['status'],
            'name' => $name,
        ];
    }

    /** @param array<string,mixed> $row */
    private function presentAttr(array $row, bool $activeValuesOnly): array
    {
        $id = (int) $row['id'];
        $names = Db::name('product_attr_i18n')->where('attr_id', $id)->select()->toArray();
        $name = ['zh-HK' => $row['code'], 'en' => $row['code']];
        foreach ($names as $n) {
            $name[$n['locale']] = $n['name'];
        }
        $vq = Db::name('product_attr_values')->where('attr_id', $id)->order('sort_order', 'asc')->order('id', 'asc');
        if ($activeValuesOnly) {
            $vq->where('status', 1);
        }
        $values = [];
        foreach ($vq->select()->toArray() as $v) {
            $values[] = $this->presentAttrValue($v);
        }

        return [
            'id' => $id,
            'code' => (string) $row['code'],
            'sort' => (int) $row['sort_order'],
            'status' => (int) $row['status'],
            'name' => $name,
            'values' => $values,
        ];
    }

    /** @param array<string,mixed> $row */
    private function presentAttrValue(array $row): array
    {
        $id = (int) $row['id'];
        $names = Db::name('product_attr_value_i18n')->where('attr_value_id', $id)->select()->toArray();
        $name = ['zh-HK' => $row['code'], 'en' => $row['code']];
        foreach ($names as $n) {
            $name[$n['locale']] = $n['name'];
        }

        return [
            'id' => $id,
            'attr_id' => (int) $row['attr_id'],
            'code' => (string) $row['code'],
            'sort' => (int) $row['sort_order'],
            'status' => (int) $row['status'],
            'name' => $name,
        ];
    }
}
