<?php

declare(strict_types=1);

namespace app\service;

use app\support\ApiException;
use think\facade\Db;

class HomeBannerService
{
    private const LINK_TYPES = ['none', 'product', 'category', 'article', 'url'];

    public function listPublic(): array
    {
        $rows = Db::name('home_banners')
            ->where('status', 1)
            ->order('sort_order', 'asc')
            ->order('id', 'asc')
            ->select()
            ->toArray();

        return ['items' => array_map(fn ($r) => $this->present($r), $rows)];
    }

    public function listAdmin(): array
    {
        $rows = Db::name('home_banners')
            ->order('sort_order', 'asc')
            ->order('id', 'asc')
            ->select()
            ->toArray();

        return ['items' => array_map(fn ($r) => $this->present($r), $rows)];
    }

    public function save(array $body, ?int $id = null): array
    {
        $image = trim((string) ($body['image_url'] ?? ''));
        if ($image === '') {
            throw new ApiException('INVALID', '請填寫圖片 URL', 422, 42201);
        }
        $linkType = (string) ($body['link_type'] ?? 'none');
        if (!in_array($linkType, self::LINK_TYPES, true)) {
            throw new ApiException('INVALID', '跳轉類型無效', 422, 42201);
        }
        $linkValue = trim((string) ($body['link_value'] ?? ''));
        if ($linkType !== 'none' && $linkValue === '') {
            throw new ApiException('INVALID', '請填寫跳轉目標', 422, 42201);
        }
        $now = date('Y-m-d H:i:s');
        $data = [
            'image_url' => $image,
            'title_zh' => trim((string) ($body['title']['zh-HK'] ?? $body['title_zh'] ?? '')),
            'title_en' => trim((string) ($body['title']['en'] ?? $body['title_en'] ?? '')),
            'link_type' => $linkType,
            'link_value' => $linkType === 'none' ? null : $linkValue,
            'sort_order' => (int) ($body['sort_order'] ?? $body['sort'] ?? 0),
            'status' => (int) ($body['status'] ?? 1) === 0 ? 0 : 1,
            'updated_at' => $now,
        ];
        if ($id) {
            $row = Db::name('home_banners')->where('id', $id)->find();
            if (!$row) {
                throw new ApiException('NOT_FOUND', '輪播不存在', 404, 40401);
            }
            Db::name('home_banners')->where('id', $id)->update($data);
        } else {
            $data['created_at'] = $now;
            $id = (int) Db::name('home_banners')->insertGetId($data);
        }
        $row = Db::name('home_banners')->where('id', $id)->find();

        return $this->present($row);
    }

    public function delete(int $id): array
    {
        $row = Db::name('home_banners')->where('id', $id)->find();
        if (!$row) {
            throw new ApiException('NOT_FOUND', '輪播不存在', 404, 40401);
        }
        Db::name('home_banners')->where('id', $id)->delete();

        return ['id' => $id, 'deleted' => true];
    }

    private function present(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'image_url' => (string) $row['image_url'],
            'title' => [
                'zh-HK' => (string) ($row['title_zh'] ?? ''),
                'en' => (string) ($row['title_en'] ?? ''),
            ],
            'link_type' => (string) $row['link_type'],
            'link_value' => $row['link_value'] !== null ? (string) $row['link_value'] : '',
            'sort_order' => (int) $row['sort_order'],
            'status' => (int) $row['status'],
            'created_at' => $row['created_at'] ?? null,
            'updated_at' => $row['updated_at'] ?? null,
        ];
    }
}
