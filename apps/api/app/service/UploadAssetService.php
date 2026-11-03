<?php

declare(strict_types=1);

namespace app\service;

use think\facade\Db;

/**
 * 上傳資產索引。新上傳入索引；未引用滿 30 天且經確認後才刪檔。
 * 仍被引用的舊目錄圖片可原樣搬入場景目錄（不重新壓縮）。
 */
class UploadAssetService
{
    public const GRACE_DAYS = 30;

    /** 每次整理搬遷的檔案上限，避免一次請求超時。 */
    public const MIGRATE_BATCH = 20;

    private static bool $schemaReady = false;

    /**
     * @param array{
     *   url: string,
     *   scene: string,
     *   bytes: int,
     *   width?: int,
     *   height?: int,
     *   uploader_type?: string,
     *   uploader_id?: int
     * } $row
     */
    public function record(array $row): void
    {
        $path = trim((string) ($row['url'] ?? ''));
        if ($path === '' || !str_starts_with($path, '/storage/')) {
            return;
        }
        if (strlen($path) > 191) {
            return;
        }
        $scene = trim((string) ($row['scene'] ?? ''));
        [$domain, $assetType] = $this->splitScene($scene);
        $uploaderType = ($row['uploader_type'] ?? '') === 'member' ? 'member' : 'admin';
        $now = date('Y-m-d H:i:s');
        $this->ensureSchema();
        $table = $this->table();
        $existing = Db::query("SELECT `id` FROM `{$table}` WHERE `path` = ? LIMIT 1", [$path]);
        $payload = [
            $domain,
            $assetType,
            $uploaderType,
            max(0, (int) ($row['uploader_id'] ?? 0)),
            max(0, (int) ($row['bytes'] ?? 0)),
            isset($row['width']) ? (int) $row['width'] : null,
            isset($row['height']) ? (int) $row['height'] : null,
            $now,
            $path,
        ];
        if ($existing) {
            Db::execute(
                "UPDATE `{$table}` SET `domain` = ?, `asset_type` = ?, `uploader_type` = ?, `uploader_id` = ?, `bytes` = ?, `width` = ?, `height` = ?, `status` = 'active', `orphaned_at` = NULL, `updated_at` = ? WHERE `path` = ?",
                $payload,
            );

            return;
        }
        Db::execute(
            "INSERT INTO `{$table}` (`path`, `domain`, `asset_type`, `uploader_type`, `uploader_id`, `bytes`, `width`, `height`, `status`, `created_at`, `updated_at`) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active', ?, ?)",
            [$path, $domain, $assetType, $uploaderType, $payload[3], $payload[4], $payload[5], $payload[6], $now, $now],
        );
    }

    /** @return array{total_count: int, total_bytes: int, groups: list<array<string, mixed>>, recent: list<array<string, mixed>>} */
    public function overview(int $recentLimit = 40): array
    {
        $this->ensureSchema();
        $table = $this->table();
        $recentLimit = max(1, min(100, $recentLimit));
        $groups = Db::query(
            "SELECT `domain`, `asset_type`, COUNT(*) AS `cnt`, COALESCE(SUM(`bytes`), 0) AS `bytes` FROM `{$table}` WHERE `status` = 'active' GROUP BY `domain`, `asset_type` ORDER BY `bytes` DESC, `domain`, `asset_type`",
        );
        $recent = Db::query(
            "SELECT `id`, `path`, `domain`, `asset_type`, `uploader_type`, `uploader_id`, `bytes`, `width`, `height`, `created_at` FROM `{$table}` WHERE `status` = 'active' ORDER BY `id` DESC LIMIT {$recentLimit}",
        );
        $totalCount = 0;
        $totalBytes = 0;
        $presented = [];
        foreach ($groups as $row) {
            $count = (int) ($row['cnt'] ?? 0);
            $bytes = (int) ($row['bytes'] ?? 0);
            $totalCount += $count;
            $totalBytes += $bytes;
            $scene = $row['domain'] . '.' . $row['asset_type'];
            $presented[] = [
                'domain' => (string) $row['domain'],
                'asset_type' => (string) $row['asset_type'],
                'scene' => $scene,
                'label' => $this->label($scene),
                'group' => $this->group($scene),
                'count' => $count,
                'bytes' => $bytes,
            ];
        }
        $recentRows = [];
        foreach ($recent as $row) {
            $scene = $row['domain'] . '.' . $row['asset_type'];
            $recentRows[] = [
                'id' => (int) $row['id'],
                'path' => (string) $row['path'],
                'scene' => $scene,
                'label' => $this->label($scene),
                'uploader_type' => (string) $row['uploader_type'],
                'uploader_id' => (int) $row['uploader_id'],
                'bytes' => (int) $row['bytes'],
                'width' => $row['width'] !== null ? (int) $row['width'] : null,
                'height' => $row['height'] !== null ? (int) $row['height'] : null,
                'created_at' => (string) $row['created_at'],
            ];
        }

        return [
            'total_count' => $totalCount,
            'total_bytes' => $totalBytes,
            'groups' => $presented,
            'recent' => $recentRows,
        ];
    }

    /**
     * 對照全庫仍被引用的 /storage 路徑。未引用的標 orphaned（保留原 orphaned_at）；重新被引用的改回 active。不刪檔。
     *
     * @return array{grace_days: int, newly_orphaned: int, restored: int, orphaned_count: int, eligible_count: int, eligible_bytes: int, items: list<array<string, mixed>>}
     */
    public function scanOrphans(): array
    {
        $this->ensureSchema();
        $referenced = $this->referencedPaths();
        $table = $this->table();
        $rows = Db::query(
            "SELECT `id`, `path`, `status`, `orphaned_at` FROM `{$table}` WHERE `status` IN ('active', 'orphaned')",
        );
        $now = date('Y-m-d H:i:s');
        $newly = 0;
        $restored = 0;
        foreach ($rows as $row) {
            $path = (string) $row['path'];
            $used = isset($referenced[$path]);
            if ($used && (string) $row['status'] === 'orphaned') {
                Db::execute(
                    "UPDATE `{$table}` SET `status` = 'active', `orphaned_at` = NULL, `updated_at` = ? WHERE `id` = ?",
                    [$now, (int) $row['id']],
                );
                $restored++;
            } elseif (!$used && (string) $row['status'] === 'active') {
                Db::execute(
                    "UPDATE `{$table}` SET `status` = 'orphaned', `orphaned_at` = ?, `updated_at` = ? WHERE `id` = ?",
                    [$now, $now, (int) $row['id']],
                );
                $newly++;
            }
        }

        return ['grace_days' => self::GRACE_DAYS, 'newly_orphaned' => $newly, 'restored' => $restored] + $this->orphanPreview();
    }

    /**
     * 刪除已孤立滿 30 天、且此刻仍無引用的檔案。
     *
     * @return array{deleted_count: int, deleted_bytes: int, skipped: int}
     */
    public function purgeOrphans(): array
    {
        $this->ensureSchema();
        $referenced = $this->referencedPaths();
        $table = $this->table();
        $cutoff = date('Y-m-d H:i:s', time() - self::GRACE_DAYS * 86400);
        $rows = Db::query(
            "SELECT `id`, `path`, `bytes`, `orphaned_at` FROM `{$table}` WHERE `status` = 'orphaned' AND `orphaned_at` IS NOT NULL AND `orphaned_at` <= ?",
            [$cutoff],
        );
        $deleted = 0;
        $bytes = 0;
        $skipped = 0;
        $now = date('Y-m-d H:i:s');
        foreach ($rows as $row) {
            $path = (string) $row['path'];
            if (isset($referenced[$path])) {
                Db::execute(
                    "UPDATE `{$table}` SET `status` = 'active', `orphaned_at` = NULL, `updated_at` = ? WHERE `id` = ?",
                    [$now, (int) $row['id']],
                );
                $skipped++;
                continue;
            }
            if (!$this->removeStoredFile($path)) {
                $skipped++;
                continue;
            }
            Db::execute(
                "UPDATE `{$table}` SET `status` = 'deleted', `updated_at` = ? WHERE `id` = ?",
                [$now, (int) $row['id']],
            );
            $deleted++;
            $bytes += (int) $row['bytes'];
        }

        return [
            'deleted_count' => $deleted,
            'deleted_bytes' => $bytes,
            'skipped' => $skipped,
        ];
    }

    /** @return array{orphaned_count: int, eligible_count: int, eligible_bytes: int, items: list<array<string, mixed>>} */
    private function orphanPreview(): array
    {
        $table = $this->table();
        $rows = Db::query(
            "SELECT `id`, `path`, `domain`, `asset_type`, `bytes`, `orphaned_at` FROM `{$table}` WHERE `status` = 'orphaned' ORDER BY `orphaned_at` ASC, `id` ASC LIMIT 200",
        );
        $countRow = Db::query("SELECT COUNT(*) AS c, COALESCE(SUM(CASE WHEN `orphaned_at` IS NOT NULL AND `orphaned_at` <= ? THEN 1 ELSE 0 END), 0) AS eligible, COALESCE(SUM(CASE WHEN `orphaned_at` IS NOT NULL AND `orphaned_at` <= ? THEN `bytes` ELSE 0 END), 0) AS eligible_bytes FROM `{$table}` WHERE `status` = 'orphaned'", [
            date('Y-m-d H:i:s', time() - self::GRACE_DAYS * 86400),
            date('Y-m-d H:i:s', time() - self::GRACE_DAYS * 86400),
        ]);
        $items = [];
        foreach ($rows as $row) {
            $scene = $row['domain'] . '.' . $row['asset_type'];
            $orphanedAt = (string) ($row['orphaned_at'] ?? '');
            $stamp = $orphanedAt !== '' ? strtotime($orphanedAt) : false;
            $eligible = $stamp !== false && $stamp <= time() - self::GRACE_DAYS * 86400;
            $daysLeft = 0;
            if ($stamp !== false && !$eligible) {
                $daysLeft = (int) ceil((($stamp + self::GRACE_DAYS * 86400) - time()) / 86400);
            }
            $items[] = [
                'id' => (int) $row['id'],
                'path' => (string) $row['path'],
                'label' => $this->label($scene),
                'bytes' => (int) $row['bytes'],
                'orphaned_at' => $orphanedAt,
                'eligible' => $eligible,
                'days_left' => $daysLeft,
            ];
        }

        return [
            'orphaned_count' => (int) ($countRow[0]['c'] ?? 0),
            'eligible_count' => (int) ($countRow[0]['eligible'] ?? 0),
            'eligible_bytes' => (int) ($countRow[0]['eligible_bytes'] ?? 0),
            'items' => $items,
        ];
    }

    /** @return array<string, true> */
    public function referencedPathSet(): array
    {
        return $this->referencedPaths();
    }

    public function bindClaim(string $path, string $scene, string $entityType, int $entityId, int $bytes): void
    {
        $this->record([
            'url' => $path,
            'scene' => $scene,
            'bytes' => max(0, $bytes),
            'uploader_type' => 'admin',
            'uploader_id' => 0,
        ]);
        $table = $this->table();
        Db::execute(
            "UPDATE `{$table}` SET `entity_type` = ?, `entity_id` = ?, `updated_at` = ? WHERE `path` = ?",
            [$entityType, $entityId > 0 ? $entityId : null, date('Y-m-d H:i:s'), $path],
        );
    }

    public function markDeleted(string $path): void
    {
        $this->ensureSchema();
        $table = $this->table();
        Db::execute(
            "UPDATE `{$table}` SET `status` = 'deleted', `updated_at` = ? WHERE `path` = ? AND `status` <> 'deleted'",
            [date('Y-m-d H:i:s'), $path],
        );
    }

    /** @return array<string, true> */
    private function referencedPaths(): array
    {
        $cols = Db::query(
            "SELECT c.TABLE_NAME AS table_name, c.COLUMN_NAME AS column_name
             FROM information_schema.COLUMNS c
             INNER JOIN information_schema.TABLES t
               ON t.TABLE_SCHEMA = c.TABLE_SCHEMA AND t.TABLE_NAME = c.TABLE_NAME
             WHERE c.TABLE_SCHEMA = DATABASE()
               AND t.TABLE_TYPE = 'BASE TABLE'
               AND c.DATA_TYPE IN ('char', 'varchar', 'text', 'tinytext', 'mediumtext', 'longtext', 'json')
               AND c.TABLE_NAME <> ?",
            [$this->table()],
        );
        $found = [];
        foreach ($cols as $col) {
            $table = (string) $col['table_name'];
            $column = (string) $col['column_name'];
            if (!preg_match('/^[A-Za-z0-9_]+$/', $table) || !preg_match('/^[A-Za-z0-9_]+$/', $column)) {
                continue;
            }
            $rows = Db::query("SELECT `{$column}` AS v FROM `{$table}` WHERE `{$column}` LIKE '%/storage/%' OR `{$column}` LIKE '%path=%'");
            foreach ($rows as $row) {
                $this->collectStoragePaths((string) ($row['v'] ?? ''), $found);
            }
        }

        return $found;
    }

    /** @param array<string, true> $found */
    private function collectStoragePaths(string $text, array &$found): void
    {
        if ($text === '') {
            return;
        }
        if (str_contains($text, '%2F') || str_contains($text, '%2f')) {
            $text .= "\n" . rawurldecode($text);
        }
        if (preg_match_all('#/storage/[A-Za-z0-9_./\-]+#', $text, $matches)) {
            foreach ($matches[0] as $path) {
                $path = rtrim($path, '.,);');
                if ($path !== '/storage/') {
                    $found[$path] = true;
                }
            }
        }
        if (preg_match_all('#[?&]path=([^&\"\'\\s<]+)#', $text, $matches)) {
            foreach ($matches[1] as $rel) {
                $rel = rawurldecode($rel);
                $rel = ltrim($rel, '/');
                if (str_starts_with($rel, 'storage/')) {
                    $rel = substr($rel, strlen('storage/'));
                }
                $rel = ltrim($rel, '/');
                if ($rel !== '' && !str_contains($rel, '..')) {
                    $found['/storage/' . $rel] = true;
                }
            }
        }
    }

    /**
     * 仍被引用、且還在舊目錄的圖片：按場景各複製一份（不重新壓縮），只改該場景的欄位。
     * 舊檔要等全庫都不再指向它才刪。未引用的舊檔不複製。每次最多 MIGRATE_BATCH 張。
     *
     * @return array{migrated: int, rewritten: int, skipped_missing: int, skipped: int, removed_old: int, bytes_copied: int, has_more: bool}
     */
    public function migrateLegacy(): array
    {
        $this->ensureSchema();
        $jobs = [];
        $seenMissing = [];
        $skippedMissing = 0;
        $stoppedEarly = false;
        foreach ($this->migrateTargets() as $target) {
            if ($stoppedEarly) {
                break;
            }
            $meta = $this->columnMeta($target['table'], $target['column']);
            if ($meta === null) {
                continue;
            }
            $table = $this->physical($target['table']);
            $column = $target['column'];
            $pk = $target['pk'];
            $selectPk = [];
            foreach ($pk as $i => $col) {
                $selectPk[] = "`{$col}` AS `pk{$i}`";
            }
            $sql = 'SELECT ' . implode(', ', $selectPk) . ", `{$column}` AS `v` FROM `{$table}` WHERE `{$column}` LIKE '%/storage/%'";
            $bind = [];
            if ($target['filter_column'] !== null) {
                $sql .= " AND `{$target['filter_column']}` = ?";
                $bind[] = $target['filter_value'];
            }
            $rows = Db::query($sql, $bind);
            foreach ($rows as $row) {
                if ($stoppedEarly) {
                    break;
                }
                foreach ($this->extractStoragePaths($this->cellText($row['v'] ?? '')) as $path) {
                    if (!$this->isLegacyStoragePath($path)) {
                        continue;
                    }
                    $key = $path . "\0" . $target['scene'];
                    if (isset($jobs[$key])) {
                        continue;
                    }
                    if ($this->absoluteStoredPath($path) === null) {
                        if (!isset($seenMissing[$path])) {
                            $seenMissing[$path] = true;
                            $skippedMissing++;
                        }
                        continue;
                    }
                    if (count($jobs) >= self::MIGRATE_BATCH) {
                        $stoppedEarly = true;
                        break;
                    }
                    $jobs[$key] = ['path' => $path, 'scene' => $target['scene']];
                }
            }
        }

        $migrated = 0;
        $rewritten = 0;
        $skipped = 0;
        $bytesCopied = 0;
        $oldPaths = [];
        foreach ($jobs as $job) {
            $copied = $this->copyLegacyIntoScene($job['path'], $job['scene']);
            if ($copied === null) {
                $skipped++;
                continue;
            }
            $n = $this->rewriteScenePath($job['scene'], $job['path'], $copied['url']);
            if ($n < 1) {
                @unlink($copied['dest']);
                $skipped++;
                continue;
            }
            try {
                $this->record([
                    'url' => $copied['url'],
                    'scene' => $job['scene'],
                    'bytes' => $copied['bytes'],
                    'width' => $copied['width'],
                    'height' => $copied['height'],
                    'uploader_type' => 'admin',
                    'uploader_id' => 0,
                ]);
            } catch (\Throwable) {
                // 索引失敗不回滾已改寫的地址；檔案已在新目錄。
            }
            $migrated++;
            $rewritten += $n;
            $bytesCopied += $copied['bytes'];
            $oldPaths[$job['path']] = true;
        }

        $removed = 0;
        if ($oldPaths !== []) {
            $referenced = $this->referencedPaths();
            $now = date('Y-m-d H:i:s');
            $index = $this->table();
            foreach (array_keys($oldPaths) as $old) {
                if (isset($referenced[$old])) {
                    continue;
                }
                if (!$this->removeStoredFile($old)) {
                    continue;
                }
                $removed++;
                Db::execute(
                    "UPDATE `{$index}` SET `status` = 'deleted', `updated_at` = ? WHERE `path` = ? AND `status` <> 'deleted'",
                    [$now, $old],
                );
            }
        }

        return [
            'migrated' => $migrated,
            'rewritten' => $rewritten,
            'skipped_missing' => $skippedMissing,
            'skipped' => $skipped,
            'removed_old' => $removed,
            'bytes_copied' => $bytesCopied,
            'has_more' => $stoppedEarly,
        ];
    }

    /**
     * @return list<array{table: string, column: string, pk: list<string>, scene: string, filter_column: ?string, filter_value: ?string}>
     */
    private function migrateTargets(): array
    {
        $rows = [
            ['categories', 'icon', ['id'], 'catalog.category'],
            ['product_media', 'url', ['id'], 'catalog.product'],
            ['products', 'cover_url', ['id'], 'catalog.product'],
            ['skus', 'image_url', ['id'], 'catalog.product'],
            ['spec_options', 'image_url', ['id'], 'catalog.product'],
            ['order_items', 'image_url', ['id'], 'catalog.product'],
            ['seckill_items', 'image_url', ['id'], 'catalog.product'],
            ['points_mall_items', 'image_url', ['id'], 'catalog.product'],
            ['product_i18n', 'detail', ['product_id', 'locale'], 'catalog.product_detail'],
            ['points_mall_items', 'detail_zh', ['id'], 'catalog.product_detail'],
            ['points_mall_items', 'detail_en', ['id'], 'catalog.product_detail'],
            ['home_banners', 'image_url', ['id'], 'marketing.banner'],
            ['home_popups', 'image_url', ['id'], 'marketing.popup'],
            ['campaigns', 'banner_image_url', ['id'], 'marketing.campaign'],
            ['campaigns', 'intro_html_zh', ['id'], 'content.campaign'],
            ['campaigns', 'intro_html_en', ['id'], 'content.campaign'],
            ['coupons', 'background_image', ['id'], 'marketing.coupon'],
            ['coupons', 'rules_zh', ['id'], 'content.coupon'],
            ['coupons', 'rules_en', ['id'], 'content.coupon'],
            ['members', 'avatar_url', ['id'], 'member.avatar'],
            ['product_reviews', 'images_json', ['id'], 'member.review'],
            ['aftersales', 'evidence_json', ['id'], 'member.aftersale'],
            ['article_i18n', 'body', ['article_id', 'locale'], 'content.article'],
        ];
        $targets = [];
        foreach ($rows as [$table, $column, $pk, $scene]) {
            $targets[] = [
                'table' => $table,
                'column' => $column,
                'pk' => $pk,
                'scene' => $scene,
                'filter_column' => null,
                'filter_value' => null,
            ];
        }
        $targets[] = [
            'table' => 'shop_settings',
            'column' => 'setting_value',
            'pk' => ['setting_key'],
            'scene' => 'marketing.image_ad',
            'filter_column' => 'setting_key',
            'filter_value' => 'home.image_ads',
        ];
        $targets[] = [
            'table' => 'shop_settings',
            'column' => 'setting_value',
            'pk' => ['setting_key'],
            'scene' => 'content.welcome',
            'filter_column' => 'setting_key',
            'filter_value' => 'member.registration',
        ];

        return $targets;
    }

    private function rewriteScenePath(string $scene, string $old, string $new): int
    {
        $updatedRows = 0;
        foreach ($this->migrateTargets() as $target) {
            if ($target['scene'] !== $scene) {
                continue;
            }
            $meta = $this->columnMeta($target['table'], $target['column']);
            if ($meta === null) {
                continue;
            }
            $table = $this->physical($target['table']);
            $column = $target['column'];
            $pk = $target['pk'];
            $selectPk = [];
            foreach ($pk as $i => $col) {
                $selectPk[] = "`{$col}` AS `pk{$i}`";
            }
            $like = '%' . addcslashes($old, '%_\\') . '%';
            $sql = 'SELECT ' . implode(', ', $selectPk) . ", `{$column}` AS `v` FROM `{$table}` WHERE `{$column}` LIKE ?";
            $bind = [$like];
            if ($target['filter_column'] !== null) {
                $sql .= " AND `{$target['filter_column']}` = ?";
                $bind[] = $target['filter_value'];
            }
            $rows = Db::query($sql, $bind);
            foreach ($rows as $row) {
                $value = $this->cellText($row['v'] ?? '');
                $next = $this->replaceStorageUrl($value, $old, $new);
                if ($next === $value) {
                    continue;
                }
                if ($meta['max'] !== null && mb_strlen($next) > $meta['max']) {
                    continue;
                }
                $params = [$next];
                $where = [];
                foreach ($pk as $i => $col) {
                    $where[] = "`{$col}` = ?";
                    $params[] = $row['pk' . $i];
                }
                Db::execute(
                    "UPDATE `{$table}` SET `{$column}` = ? WHERE " . implode(' AND ', $where),
                    $params,
                );
                $updatedRows++;
            }
        }

        return $updatedRows;
    }

    private function replaceStorageUrl(string $text, string $old, string $new): string
    {
        $pattern = '#' . preg_quote($old, '#') . '(?![A-Za-z0-9_./\-])#';
        $text = preg_replace($pattern, $new, $text) ?? $text;
        $escapedOld = str_replace('/', '\\/', $old);
        $escapedNew = str_replace('/', '\\/', $new);
        if ($escapedOld !== $old && str_contains($text, $escapedOld)) {
            $text = str_replace($escapedOld, $escapedNew, $text);
        }
        $encOld = rawurlencode($old);
        $encNew = rawurlencode($new);
        if (str_contains($text, $encOld)) {
            $text = str_replace($encOld, $encNew, $text);
        }
        $encRelOld = rawurlencode(ltrim($old, '/'));
        $encRelNew = rawurlencode(ltrim($new, '/'));
        if (str_contains($text, $encRelOld)) {
            $text = str_replace($encRelOld, $encRelNew, $text);
        }

        return $text;
    }

    /** @return array{url: string, dest: string, bytes: int, width: ?int, height: ?int}|null */
    private function copyLegacyIntoScene(string $url, string $scene): ?array
    {
        if (!isset(ImageUploadSceneService::SCENES[$scene])) {
            return null;
        }
        $bucket = trim((string) ImageUploadSceneService::SCENES[$scene]['bucket'], '/');
        if ($bucket === '' || !preg_match('#^[a-z0-9_\-]+(/[a-z0-9_\-]+)*$#', $bucket)) {
            return null;
        }
        $src = $this->absoluteStoredPath($url);
        if ($src === null) {
            return null;
        }
        $ext = strtolower(pathinfo($src, PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
            return null;
        }
        $bytes = filesize($src);
        if ($bytes === false || $bytes < 1) {
            return null;
        }
        $storageRoot = rtrim(str_replace('\\', '/', app()->getRootPath()), '/') . '/public/storage';
        $relDir = $bucket . '/legacy/' . date('Ymd');
        $dir = $storageRoot . '/' . $relDir;
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            return null;
        }
        $dest = $dir . '/' . bin2hex(random_bytes(8)) . '.' . $ext;
        if (!copy($src, $dest)) {
            return null;
        }
        $realStorage = realpath($storageRoot);
        $realDest = realpath($dest);
        if ($realStorage === false || $realDest === false) {
            @unlink($dest);

            return null;
        }
        $realStorage = str_replace('\\', '/', $realStorage);
        $realDest = str_replace('\\', '/', $realDest);
        $bucketRoot = $realStorage . '/' . $bucket . '/';
        if (!str_starts_with($realDest, $bucketRoot)) {
            @unlink($dest);

            return null;
        }
        $width = null;
        $height = null;
        $info = @getimagesize($dest);
        if (is_array($info)) {
            $width = (int) $info[0];
            $height = (int) $info[1];
        }

        return [
            'url' => '/storage/' . $relDir . '/' . basename($dest),
            'dest' => $realDest,
            'bytes' => (int) $bytes,
            'width' => $width,
            'height' => $height,
        ];
    }

    private function isLegacyStoragePath(string $path): bool
    {
        if (!str_starts_with($path, '/storage/') || str_contains($path, '..')) {
            return false;
        }
        $rel = substr($path, strlen('/storage/'));
        foreach (ImageUploadSceneService::SCENES as $def) {
            $bucket = trim((string) $def['bucket'], '/');
            if ($bucket !== '' && str_starts_with($rel, $bucket . '/')) {
                return false;
            }
        }
        $root = explode('/', $rel, 2)[0];

        return in_array($root, ['avatar', 'review', 'aftersale', 'product', 'banner', 'admin', 'content'], true);
    }

    /** @return list<string> */
    private function extractStoragePaths(string $text): array
    {
        $found = [];
        $this->collectStoragePaths($text, $found);

        return array_keys($found);
    }

    private function cellText(mixed $value): string
    {
        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
        }

        return (string) $value;
    }

    /** @return array{max: ?int}|null */
    private function columnMeta(string $table, string $column): ?array
    {
        static $cache = [];
        $key = $table . '.' . $column;
        if (array_key_exists($key, $cache)) {
            return $cache[$key];
        }
        if (!preg_match('/^[A-Za-z0-9_]+$/', $table) || !preg_match('/^[A-Za-z0-9_]+$/', $column)) {
            return $cache[$key] = null;
        }
        $rows = Db::query(
            "SELECT DATA_TYPE AS t, CHARACTER_MAXIMUM_LENGTH AS n FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?",
            [$this->physical($table), $column],
        );
        if (!$rows) {
            return $cache[$key] = null;
        }
        $type = strtolower((string) ($rows[0]['t'] ?? ''));
        $max = $rows[0]['n'];
        $limited = in_array($type, ['char', 'varchar'], true) && $max !== null;

        return $cache[$key] = ['max' => $limited ? (int) $max : null];
    }

    private function physical(string $table): string
    {
        $prefix = (string) (Db::getConnection()->getConfig('prefix') ?: '');

        return $prefix . $table;
    }

    /** 檔案存在且落在 public/storage 內時回傳真實路徑。 */
    private function absoluteStoredPath(string $url): ?string
    {
        if (!str_starts_with($url, '/storage/') || str_contains($url, '..')) {
            return null;
        }
        $rel = substr($url, strlen('/storage/'));
        if ($rel === '' || str_contains($rel, '..')) {
            return null;
        }
        $storageRoot = rtrim(str_replace('\\', '/', app()->getRootPath()), '/') . '/public/storage';
        $full = $storageRoot . '/' . $rel;
        if (!is_file($full)) {
            return null;
        }
        $realStorage = realpath($storageRoot);
        $realFile = realpath($full);
        if ($realStorage === false || $realFile === false) {
            return null;
        }
        $realStorage = str_replace('\\', '/', $realStorage);
        $realFile = str_replace('\\', '/', $realFile);
        if (!str_starts_with($realFile, $realStorage . '/')) {
            return null;
        }

        return $realFile;
    }

    /** 檔案不存在視為已清理。路徑必須落在 public/storage 內。 */
    private function removeStoredFile(string $url): bool
    {
        if (!str_starts_with($url, '/storage/') || str_contains($url, '..')) {
            return false;
        }
        $rel = substr($url, strlen('/storage/'));
        if ($rel === '' || str_contains($rel, '..')) {
            return false;
        }
        $storageRoot = rtrim(str_replace('\\', '/', app()->getRootPath()), '/') . '/public/storage';
        $full = $storageRoot . '/' . $rel;
        if (!is_file($full)) {
            return true;
        }
        $realStorage = realpath($storageRoot);
        $realFile = realpath($full);
        if ($realStorage === false || $realFile === false) {
            return false;
        }
        $realStorage = str_replace('\\', '/', $realStorage);
        $realFile = str_replace('\\', '/', $realFile);
        if (!str_starts_with($realFile, $realStorage . '/')) {
            return false;
        }

        return unlink($realFile);
    }

    private function ensureSchema(): void
    {
        if (self::$schemaReady) {
            return;
        }
        $table = $this->table();
        Db::execute(
            "CREATE TABLE IF NOT EXISTS `{$table}` (
              `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              `path` VARCHAR(191) NOT NULL,
              `domain` VARCHAR(32) NOT NULL,
              `asset_type` VARCHAR(32) NOT NULL,
              `entity_type` VARCHAR(32) NULL,
              `entity_id` BIGINT UNSIGNED NULL,
              `uploader_type` VARCHAR(16) NOT NULL,
              `uploader_id` BIGINT UNSIGNED NOT NULL DEFAULT 0,
              `bytes` INT UNSIGNED NOT NULL DEFAULT 0,
              `width` INT UNSIGNED NULL,
              `height` INT UNSIGNED NULL,
              `status` VARCHAR(16) NOT NULL DEFAULT 'active',
              `orphaned_at` DATETIME NULL,
              `created_at` DATETIME NOT NULL,
              `updated_at` DATETIME NOT NULL,
              PRIMARY KEY (`id`),
              UNIQUE KEY `uk_upload_assets_path` (`path`),
              KEY `idx_upload_assets_type` (`domain`, `asset_type`),
              KEY `idx_upload_assets_uploader` (`uploader_type`, `uploader_id`),
              KEY `idx_upload_assets_status` (`status`, `id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        );
        $col = Db::query(
            "SELECT COUNT(*) AS c FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = 'orphaned_at'",
            [$table],
        );
        if ((int) ($col[0]['c'] ?? 0) === 0) {
            Db::execute("ALTER TABLE `{$table}` ADD COLUMN `orphaned_at` DATETIME NULL AFTER `status`");
        }
        self::$schemaReady = true;
    }

    private function table(): string
    {
        $prefix = (string) (Db::getConnection()->getConfig('prefix') ?: '');

        return $prefix . 'upload_assets';
    }

    /** @return array{0: string, 1: string} */
    private function splitScene(string $scene): array
    {
        $scene = strtolower($scene);
        if (!str_contains($scene, '.')) {
            return ['content', $scene !== '' ? $scene : 'richtext'];
        }
        [$domain, $type] = explode('.', $scene, 2);
        $domain = preg_replace('/[^a-z0-9_\-]/', '', $domain) ?: 'content';
        $type = preg_replace('/[^a-z0-9_\-]/', '', $type) ?: 'richtext';

        return [substr($domain, 0, 32), substr($type, 0, 32)];
    }

    private function label(string $scene): string
    {
        return (string) (ImageUploadSceneService::SCENES[$scene]['label'] ?? $scene);
    }

    private function group(string $scene): string
    {
        return (string) (ImageUploadSceneService::SCENES[$scene]['group'] ?? '');
    }
}
