<?php

declare(strict_types=1);

namespace app\service;

use think\facade\Db;

/**
 * 上傳資產索引。只記新上傳；未引用滿 30 天且經確認後才刪檔。不搬舊圖。
 */
class UploadAssetService
{
    public const GRACE_DAYS = 30;

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
