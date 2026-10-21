<?php

declare(strict_types=1);

namespace app\service;

use app\support\ApiException;
use think\facade\Db;

/**
 * 購物車頂部信任標籤（名稱 + 詳情，中英文；點擊彈窗展示詳情）。
 */
class CartTrustTagService
{
    private static bool $schemaReady = false;

    /** 前台：僅啟用項，按排序。 */
    public function listPublic(): array
    {
        $this->ensureSchema();
        $rows = Db::name('cart_trust_tags')
            ->where('status', 1)
            ->order('sort_order', 'asc')
            ->order('id', 'asc')
            ->select()
            ->toArray();

        return [
            'items' => array_map(fn ($r) => $this->present($r), $rows),
        ];
    }

    public function listAdmin(): array
    {
        $this->ensureSchema();
        $rows = Db::name('cart_trust_tags')
            ->order('sort_order', 'asc')
            ->order('id', 'asc')
            ->select()
            ->toArray();

        return [
            'items' => array_map(fn ($r) => $this->presentAdmin($r), $rows),
        ];
    }

    public function saveAdmin(array $body, ?int $id = null): array
    {
        $this->ensureSchema();
        $code = trim((string) ($body['code'] ?? ''));
        $nameZh = trim((string) ($body['name']['zh-HK'] ?? $body['name_zh'] ?? ''));
        $nameEn = trim((string) ($body['name']['en'] ?? $body['name_en'] ?? ''));
        $detailZh = trim((string) ($body['detail']['zh-HK'] ?? $body['detail_zh'] ?? ''));
        $detailEn = trim((string) ($body['detail']['en'] ?? $body['detail_en'] ?? ''));
        $sort = (int) ($body['sort'] ?? $body['sort_order'] ?? 0);
        if ($nameZh === '' && $nameEn === '') {
            throw new ApiException('INVALID', '請填寫標籤名稱', 422, 42201);
        }
        $now = date('Y-m-d H:i:s');
        if ($id) {
            $row = Db::name('cart_trust_tags')->where('id', $id)->find();
            if (!$row) {
                throw new ApiException('NOT_FOUND', '標籤不存在', 404, 40401);
            }
            if ($code === '') {
                $code = (string) $row['code'];
            }
        } else {
            if ($code === '') {
                $code = 'trust_' . bin2hex(random_bytes(3));
            }
        }
        $dup = Db::name('cart_trust_tags')->where('code', $code);
        if ($id) {
            $dup->where('id', '<>', $id);
        }
        if ($dup->find()) {
            throw new ApiException('INVALID', 'code 已存在', 422, 42201);
        }
        $data = [
            'code' => $code,
            'sort_order' => $sort,
            'status' => (int) ($body['status'] ?? 1) === 0 ? 0 : 1,
            'name_zh' => $nameZh !== '' ? $nameZh : $nameEn,
            'name_en' => $nameEn !== '' ? $nameEn : $nameZh,
            'detail_zh' => $detailZh,
            'detail_en' => $detailEn,
            'updated_at' => $now,
        ];
        if ($id) {
            Db::name('cart_trust_tags')->where('id', $id)->update($data);
        } else {
            $data['created_at'] = $now;
            $id = (int) Db::name('cart_trust_tags')->insertGetId($data);
        }
        $row = Db::name('cart_trust_tags')->where('id', $id)->find();

        return $this->presentAdmin($row);
    }

    public function deleteAdmin(int $id): array
    {
        $this->ensureSchema();
        $row = Db::name('cart_trust_tags')->where('id', $id)->find();
        if (!$row) {
            throw new ApiException('NOT_FOUND', '標籤不存在', 404, 40401);
        }
        Db::name('cart_trust_tags')->where('id', $id)->delete();

        return ['id' => $id, 'deleted' => true];
    }

    private function ensureSchema(): void
    {
        if (self::$schemaReady) {
            return;
        }
        $prefix = (string) (Db::getConnection()->getConfig('prefix') ?: '');
        $table = $prefix . 'cart_trust_tags';
        try {
            Db::execute(
                "CREATE TABLE IF NOT EXISTS `{$table}` (
                  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                  `code` VARCHAR(32) NOT NULL,
                  `sort_order` INT NOT NULL DEFAULT 0,
                  `status` TINYINT NOT NULL DEFAULT 1,
                  `name_zh` VARCHAR(64) NOT NULL,
                  `name_en` VARCHAR(64) NOT NULL,
                  `detail_zh` TEXT NULL,
                  `detail_en` TEXT NULL,
                  `created_at` DATETIME NOT NULL,
                  `updated_at` DATETIME NOT NULL,
                  UNIQUE KEY `uk_cart_trust_tags_code` (`code`),
                  KEY `idx_cart_trust_tags_status_sort` (`status`, `sort_order`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            );
            $this->ensureSeedIfEmpty();
            self::$schemaReady = true;
        } catch (ApiException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new ApiException('SERVER', '購物車標籤表未就緒', 500, 50033);
        }
    }

    private function ensureSeedIfEmpty(): void
    {
        $count = (int) Db::name('cart_trust_tags')->count();
        if ($count > 0) {
            return;
        }
        $now = date('Y-m-d H:i:s');
        $seeds = [
            [
                'code' => 'authentic',
                'sort_order' => 1,
                'name_zh' => '香港正品保證',
                'name_en' => 'Authentic',
                'detail_zh' => 'SisterMart 商品均經品質把關，來源可追溯，保障您購得正品。',
                'detail_en' => 'Every SisterMart item is quality-checked with a traceable source so you receive authentic goods.',
            ],
            [
                'code' => 'craft',
                'sort_order' => 2,
                'name_zh' => '精工細作',
                'name_en' => 'Handcrafted',
                'detail_zh' => '選材與工藝嚴格把控，細節打磨，呈現耐看的日常飾品質感。',
                'detail_en' => 'Materials and craftsmanship are carefully controlled for refined everyday jewelry.',
            ],
            [
                'code' => 'aftersale',
                'sort_order' => 3,
                'name_zh' => '售後保障',
                'name_en' => 'After-sales',
                'detail_zh' => '支持按店鋪規則申請售後；如有問題請聯絡客服，我們會盡快協助處理。',
                'detail_en' => 'After-sales follow store policy. Contact support anytime and we will help promptly.',
            ],
        ];
        foreach ($seeds as $s) {
            Db::name('cart_trust_tags')->insert([
                'code' => $s['code'],
                'sort_order' => $s['sort_order'],
                'status' => 1,
                'name_zh' => $s['name_zh'],
                'name_en' => $s['name_en'],
                'detail_zh' => $s['detail_zh'],
                'detail_en' => $s['detail_en'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    private function present(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'code' => (string) $row['code'],
            'sort' => (int) $row['sort_order'],
            'name' => [
                'zh-HK' => (string) $row['name_zh'],
                'en' => (string) $row['name_en'],
            ],
            'detail' => [
                'zh-HK' => (string) ($row['detail_zh'] ?? ''),
                'en' => (string) ($row['detail_en'] ?? ''),
            ],
        ];
    }

    private function presentAdmin(array $row): array
    {
        return array_merge($this->present($row), [
            'status' => (int) $row['status'],
            'created_at' => $row['created_at'] ?? null,
            'updated_at' => $row['updated_at'] ?? null,
        ]);
    }
}
