<?php

declare(strict_types=1);

namespace app\service;

use app\support\ApiException;
use think\facade\Db;

class HomePopupService
{
    private const LINK_TYPES = ['none', 'product', 'category', 'article', 'url'];
    private const FREQUENCIES = ['once', 'daily', 'always'];

    public function listPublic(?int $memberId): array
    {
        $this->ensureTables();
        $now = date('Y-m-d H:i:s');
        $rows = Db::name('home_popups')
            ->where('status', 1)
            ->whereRaw('(starts_at IS NULL OR starts_at <= ?)', [$now])
            ->whereRaw('(ends_at IS NULL OR ends_at >= ?)', [$now])
            ->order('sort_order', 'asc')
            ->order('id', 'asc')
            ->select()
            ->toArray();

        $dismissMap = [];
        if ($memberId) {
            $dismissRows = Db::name('home_popup_dismissals')
                ->where('member_id', $memberId)
                ->whereIn('popup_id', array_column($rows, 'id') ?: [0])
                ->select()
                ->toArray();
            foreach ($dismissRows as $d) {
                $dismissMap[(int) $d['popup_id']] = (string) $d['last_dismissed_at'];
            }
        }

        $today = date('Y-m-d');
        $items = [];
        foreach ($rows as $r) {
            $id = (int) $r['id'];
            $freq = $this->normalizeFrequency($r['frequency'] ?? 'once');
            if ($memberId && $freq !== 'always' && isset($dismissMap[$id])) {
                if ($freq === 'once') {
                    continue;
                }
                if ($freq === 'daily' && substr($dismissMap[$id], 0, 10) === $today) {
                    continue;
                }
            }
            $items[] = $this->present($r, false);
        }

        return ['items' => $items];
    }

    public function dismiss(int $memberId, int $popupId): array
    {
        $this->ensureTables();
        $row = Db::name('home_popups')->where('id', $popupId)->find();
        if (!$row) {
            throw new ApiException('NOT_FOUND', '廣告不存在', 404, 40401);
        }
        $freq = $this->normalizeFrequency($row['frequency'] ?? 'once');
        if ($freq === 'always') {
            // 每次進首頁都可再出：不必持久化
            return ['popup_id' => $popupId, 'dismissed' => true, 'persisted' => false];
        }
        $now = date('Y-m-d H:i:s');
        $exists = Db::name('home_popup_dismissals')
            ->where(['member_id' => $memberId, 'popup_id' => $popupId])
            ->find();
        if ($exists) {
            Db::name('home_popup_dismissals')
                ->where(['member_id' => $memberId, 'popup_id' => $popupId])
                ->update(['last_dismissed_at' => $now]);
        } else {
            Db::name('home_popup_dismissals')->insert([
                'member_id' => $memberId,
                'popup_id' => $popupId,
                'last_dismissed_at' => $now,
            ]);
        }

        return ['popup_id' => $popupId, 'dismissed' => true, 'persisted' => true];
    }

    public function listAdmin(): array
    {
        $this->ensureTables();
        $rows = Db::name('home_popups')
            ->order('sort_order', 'asc')
            ->order('id', 'asc')
            ->select()
            ->toArray();

        return ['items' => array_map(fn ($r) => $this->present($r, true), $rows)];
    }

    public function save(array $body, ?int $id = null): array
    {
        $this->ensureTables();
        $image = trim((string) ($body['image_url'] ?? ''));
        if ($image === '') {
            throw new ApiException('INVALID', '請上傳背景圖', 422, 42201);
        }
        $linkType = (string) ($body['link_type'] ?? 'none');
        if (!in_array($linkType, self::LINK_TYPES, true)) {
            throw new ApiException('INVALID', '跳轉類型無效', 422, 42201);
        }
        $linkValue = trim((string) ($body['link_value'] ?? ''));
        if ($linkType !== 'none' && $linkValue === '') {
            throw new ApiException('INVALID', '請填寫跳轉目標', 422, 42201);
        }
        $freq = $this->normalizeFrequency($body['frequency'] ?? 'once');
        $startsAt = $this->normalizeDateTime($body['starts_at'] ?? null, false);
        $endsAt = $this->normalizeDateTime($body['ends_at'] ?? null, true);
        if ($startsAt && $endsAt && $startsAt > $endsAt) {
            throw new ApiException('INVALID', '結束時間不可早於開始時間', 422, 42201);
        }

        $now = date('Y-m-d H:i:s');
        $data = [
            'image_url' => $image,
            'title_zh' => trim((string) ($body['title']['zh-HK'] ?? $body['title_zh'] ?? '')),
            'title_en' => trim((string) ($body['title']['en'] ?? $body['title_en'] ?? '')),
            'link_type' => $linkType,
            'link_value' => $linkType === 'none' ? null : $linkValue,
            'frequency' => $freq,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'sort_order' => (int) ($body['sort_order'] ?? $body['sort'] ?? 0),
            'status' => (int) ($body['status'] ?? 1) === 0 ? 0 : 1,
            'updated_at' => $now,
        ];
        if ($id) {
            $row = Db::name('home_popups')->where('id', $id)->find();
            if (!$row) {
                throw new ApiException('NOT_FOUND', '廣告不存在', 404, 40401);
            }
            Db::name('home_popups')->where('id', $id)->update($data);
        } else {
            $data['created_at'] = $now;
            $id = (int) Db::name('home_popups')->insertGetId($data);
        }

        return $this->present(Db::name('home_popups')->where('id', $id)->find(), true);
    }

    public function delete(int $id): array
    {
        $this->ensureTables();
        $row = Db::name('home_popups')->where('id', $id)->find();
        if (!$row) {
            throw new ApiException('NOT_FOUND', '廣告不存在', 404, 40401);
        }
        Db::transaction(function () use ($id) {
            Db::name('home_popup_dismissals')->where('popup_id', $id)->delete();
            Db::name('home_popups')->where('id', $id)->delete();
        });

        return ['id' => $id, 'deleted' => true];
    }

    private function normalizeFrequency(mixed $raw): string
    {
        $freq = strtolower(trim((string) $raw));
        if (!in_array($freq, self::FREQUENCIES, true)) {
            return 'once';
        }

        return $freq;
    }

    private function normalizeDateTime(mixed $raw, bool $endOfDayIfDateOnly): ?string
    {
        if ($raw === null || $raw === '') {
            return null;
        }
        if (is_numeric($raw)) {
            $n = (float) $raw;
            $ts = (int) ($n > 1e12 ? floor($n / 1000) : $n);

            return $ts > 0 ? date('Y-m-d H:i:s', $ts) : null;
        }
        if (!is_string($raw)) {
            return null;
        }
        $s = trim($raw);
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $s)) {
            return $endOfDayIfDateOnly ? $s . ' 23:59:59' : $s . ' 00:00:00';
        }
        $ts = strtotime(str_replace('T', ' ', $s));

        return $ts === false ? null : date('Y-m-d H:i:s', $ts);
    }

    /** @param array<string, mixed> $row */
    private function present(array $row, bool $admin): array
    {
        $data = [
            'id' => (int) $row['id'],
            'image_url' => (string) $row['image_url'],
            'title' => [
                'zh-HK' => (string) ($row['title_zh'] ?? ''),
                'en' => (string) ($row['title_en'] ?? ''),
            ],
            'link_type' => (string) $row['link_type'],
            'link_value' => $row['link_value'] !== null ? (string) $row['link_value'] : '',
            'frequency' => $this->normalizeFrequency($row['frequency'] ?? 'once'),
            'starts_at' => $row['starts_at'] ?? null,
            'ends_at' => $row['ends_at'] ?? null,
            'sort_order' => (int) $row['sort_order'],
            'status' => (int) $row['status'],
        ];
        if ($admin) {
            $data['title_zh'] = (string) ($row['title_zh'] ?? '');
            $data['title_en'] = (string) ($row['title_en'] ?? '');
            $data['created_at'] = $row['created_at'] ?? null;
            $data['updated_at'] = $row['updated_at'] ?? null;
        }

        return $data;
    }

    private function ensureTables(): void
    {
        static $ready = false;
        if ($ready) {
            return;
        }
        $prefix = (string) config('database.connections.mysql.prefix', '');
        $popups = $prefix . 'home_popups';
        $dismiss = $prefix . 'home_popup_dismissals';
        try {
            Db::execute("CREATE TABLE IF NOT EXISTS `{$popups}` (
              `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
              `image_url` VARCHAR(512) NOT NULL,
              `title_zh` VARCHAR(191) NOT NULL DEFAULT '',
              `title_en` VARCHAR(191) NOT NULL DEFAULT '',
              `link_type` VARCHAR(32) NOT NULL DEFAULT 'none',
              `link_value` VARCHAR(512) NULL,
              `frequency` VARCHAR(16) NOT NULL DEFAULT 'once',
              `starts_at` DATETIME NULL,
              `ends_at` DATETIME NULL,
              `sort_order` INT NOT NULL DEFAULT 0,
              `status` TINYINT NOT NULL DEFAULT 1,
              `created_at` DATETIME NOT NULL,
              `updated_at` DATETIME NOT NULL,
              KEY `idx_home_popups_status_sort` (`status`, `sort_order`, `id`),
              KEY `idx_home_popups_window` (`starts_at`, `ends_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            Db::execute("CREATE TABLE IF NOT EXISTS `{$dismiss}` (
              `member_id` BIGINT UNSIGNED NOT NULL,
              `popup_id` BIGINT UNSIGNED NOT NULL,
              `last_dismissed_at` DATETIME NOT NULL,
              PRIMARY KEY (`member_id`, `popup_id`),
              KEY `idx_home_popup_dismissals_popup` (`popup_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            $ready = true;
        } catch (\Throwable $e) {
            throw new ApiException(
                'SERVER',
                '遮罩廣告表未就緒，請執行 m41_home_popups.sql',
                500,
                50041,
            );
        }
    }
}
