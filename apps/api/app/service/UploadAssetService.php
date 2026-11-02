<?php

declare(strict_types=1);

namespace app\service;

use think\facade\Db;

/**
 * 上傳資產索引。只記新上傳的路徑與體積，不搬舊圖、不刪檔。
 */
class UploadAssetService
{
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
                "UPDATE `{$table}` SET `domain` = ?, `asset_type` = ?, `uploader_type` = ?, `uploader_id` = ?, `bytes` = ?, `width` = ?, `height` = ?, `status` = 'active', `updated_at` = ? WHERE `path` = ?",
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
              `created_at` DATETIME NOT NULL,
              `updated_at` DATETIME NOT NULL,
              PRIMARY KEY (`id`),
              UNIQUE KEY `uk_upload_assets_path` (`path`),
              KEY `idx_upload_assets_type` (`domain`, `asset_type`),
              KEY `idx_upload_assets_uploader` (`uploader_type`, `uploader_id`),
              KEY `idx_upload_assets_status` (`status`, `id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        );
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
