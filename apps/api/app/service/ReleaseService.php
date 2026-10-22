<?php

declare(strict_types=1);

namespace app\service;

use app\support\ApiException;
use think\facade\Db;
use ZipArchive;

/**
 * 窄版 Release Channel：簽名包上傳 → 快照 → 白名單覆蓋 → 可選冪等 SQL → 文件回退。
 *
 * 需 .env：RELEASE_ENABLED=true、RELEASE_HMAC_SECRET=<與打包機相同>
 * 可選：RELEASE_SITE_ROOT、RELEASE_MAX_BYTES、RELEASE_LOCK_TTL
 */
class ReleaseService
{
    public const SCHEMA = 1;
    public const STATUS_UPLOADING = 'uploading';
    public const STATUS_READY = 'ready';
    public const STATUS_PUBLISHING = 'publishing';
    public const STATUS_PUBLISHED = 'published';
    public const STATUS_FAILED = 'failed';
    public const STATUS_ROLLED_BACK = 'rolled_back';

    /** @var list<string> */
    public const DEFAULT_WHITELIST = [
        'index.html',
        'favicon.svg',
        'favicon.ico',
        'robots.txt',
        '.htaccess',
        'site.webmanifest',
        'icons.svg',
        'apple-touch-icon.png',
        'apple-touch-icon-180x180.png',
        'android-chrome-192x192.png',
        'android-chrome-512x512.png',
        'assets/',
        'admin/',
        'api/',
        'sm-backend/',
        'mock/',
        'products/',
        'vps-spa/',
        'README-DEPLOY.txt',
    ];

    /** @var list<string> */
    public const DEFAULT_BLACKLIST = [
        'sm-backend/.env',
        'sm-backend/runtime/',
        'sm-backend/public/storage/',
        // 只擋發佈通道運行時目錄；允許包內帶 _releases/.htaccess 護欄
        '_releases/pending/',
        '_releases/staging/',
        '_releases/snapshots/',
        '_releases/logs/',
        '_releases/state.json',
        '.env',
    ];

    private int $maxBytes;
    private int $lockTtl;
    private string $hmacSecret;

    public function __construct()
    {
        $this->maxBytes = max(1, (int) env('RELEASE_MAX_BYTES', 80 * 1024 * 1024));
        $this->lockTtl = max(60, (int) env('RELEASE_LOCK_TTL', 900));
        $this->hmacSecret = trim((string) env('RELEASE_HMAC_SECRET', ''));
    }

    public function assertEnabled(): void
    {
        $on = filter_var(env('RELEASE_ENABLED', false), FILTER_VALIDATE_BOOLEAN);
        if (!$on) {
            throw new ApiException('DISABLED', '發佈通道未開啟（RELEASE_ENABLED）', 503, 50301);
        }
        if ($this->hmacSecret === '' || strlen($this->hmacSecret) < 16) {
            throw new ApiException('DISABLED', '未配置 RELEASE_HMAC_SECRET（至少 16 字符）', 503, 50302);
        }
    }

    public function assertSuper(?array $admin): void
    {
        AdminAcl::assert($admin, AdminAcl::PERM_RELEASES);
        if (AdminAcl::roleCode($admin) !== 'super') {
            throw new ApiException('FORBIDDEN', '僅超級管理員可操作發佈通道', 403, 40301);
        }
    }

    /** @return array<string, mixed> */
    public function state(): array
    {
        $this->assertEnabled();
        $state = $this->readState();
        $this->maybeExpireLock($state);

        return [
            'enabled' => true,
            'site_root' => $this->siteRoot(),
            'current_release_id' => $state['current_release_id'] ?? null,
            'previous_snapshot_id' => $state['previous_snapshot_id'] ?? null,
            'lock' => $state['lock'] ?? null,
            'max_bytes' => $this->maxBytes,
            'updated_at' => $state['updated_at'] ?? null,
        ];
    }

    /** @return array{items: list<array<string, mixed>>, state: array<string, mixed>} */
    public function list(): array
    {
        $this->assertEnabled();
        $pending = $this->releasesRoot() . '/pending';
        $items = [];
        if (is_dir($pending)) {
            foreach (scandir($pending) ?: [] as $name) {
                if ($name === '.' || $name === '..') {
                    continue;
                }
                $meta = $this->readJson($pending . '/' . $name . '/meta.json');
                if ($meta) {
                    $items[] = $this->publicMeta($meta);
                }
            }
        }
        usort($items, static fn ($a, $b) => strcmp((string) ($b['created_at'] ?? ''), (string) ($a['created_at'] ?? '')));

        return ['items' => $items, 'state' => $this->state()];
    }

    /** @return array<string, mixed> */
    public function read(string $id): array
    {
        $this->assertEnabled();
        $meta = $this->requireMeta($id);
        $logPath = $this->releasesRoot() . '/logs/publish_' . $id . '.log';
        $log = is_file($logPath) ? (string) file_get_contents($logPath) : '';

        return [
            'release' => $this->publicMeta($meta),
            'manifest' => $meta['manifest'] ?? null,
            'log' => mb_substr($log, -20000),
            'state' => $this->state(),
        ];
    }

    /**
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    public function initUpload(array $body, int $adminId): array
    {
        $this->assertEnabled();
        $total = (int) ($body['total_bytes'] ?? 0);
        $chunkSize = (int) ($body['chunk_size'] ?? 1024 * 1024);
        $filename = basename((string) ($body['filename'] ?? 'release.tar'));
        $sha256 = strtolower(trim((string) ($body['sha256'] ?? '')));

        if ($total <= 0 || $total > $this->maxBytes) {
            throw new ApiException('INVALID', '包大小不合法（上限 ' . $this->maxBytes . ' 字節）', 422, 42201);
        }
        if ($chunkSize < 64 * 1024 || $chunkSize > 4 * 1024 * 1024) {
            throw new ApiException('INVALID', '分片大小須在 64KB–4MB', 422, 42201);
        }
        if (!preg_match('/\.(tar|tar\.gz|tgz)$/i', $filename)) {
            throw new ApiException('INVALID', '僅接受 .tar / .tar.gz 發佈包', 422, 42201);
        }

        $free = @disk_free_space($this->releasesRoot());
        if ($free !== false && $free < ($total * 2 + 20 * 1024 * 1024)) {
            throw new ApiException('DISK', '磁盤空間不足，無法接收發佈包', 507, 50701);
        }

        $id = 'rel_' . gmdate('Ymd_His') . '_' . bin2hex(random_bytes(3));
        $dir = $this->releasesRoot() . '/pending/' . $id;
        $this->mkdir($dir . '/chunks');

        $chunksExpected = (int) ceil($total / $chunkSize);
        $meta = [
            'id' => $id,
            'status' => self::STATUS_UPLOADING,
            'filename' => $filename,
            'total_bytes' => $total,
            'chunk_size' => $chunkSize,
            'chunks_expected' => $chunksExpected,
            'chunks_received' => [],
            'sha256' => $sha256,
            'title' => '',
            'description' => '',
            'version' => '',
            'has_migrate' => false,
            'created_at' => gmdate('c'),
            'created_by' => $adminId,
            'updated_at' => gmdate('c'),
            'error' => null,
            'manifest' => null,
        ];
        $this->writeJson($dir . '/meta.json', $meta);
        $this->audit('upload_init', $adminId, $id, ['total_bytes' => $total]);

        return [
            'id' => $id,
            'chunk_size' => $chunkSize,
            'chunks_expected' => $chunksExpected,
            'max_bytes' => $this->maxBytes,
        ];
    }

    /** @return array<string, mixed> */
    public function putChunk(string $id, int $n, string $bytes, int $adminId): array
    {
        $this->assertEnabled();
        $meta = $this->requireMeta($id);
        if (($meta['status'] ?? '') !== self::STATUS_UPLOADING) {
            throw new ApiException('INVALID', '當前狀態不可上傳分片', 409, 40901);
        }
        $expected = (int) ($meta['chunks_expected'] ?? 0);
        if ($n < 0 || $n >= $expected) {
            throw new ApiException('INVALID', '分片序號超出範圍', 422, 42201);
        }
        $chunkSize = (int) $meta['chunk_size'];
        $len = strlen($bytes);
        if ($len === 0) {
            throw new ApiException('INVALID', '空分片（拒絕 0 字節）', 422, 42201);
        }
        $isLast = $n === $expected - 1;
        if (!$isLast && $len !== $chunkSize) {
            throw new ApiException('INVALID', '非末片大小必須等於 chunk_size', 422, 42201);
        }
        if ($isLast && $len > $chunkSize) {
            throw new ApiException('INVALID', '末片過大', 422, 42201);
        }

        $path = $this->pendingDir($id) . '/chunks/' . $n . '.part';
        if (file_put_contents($path, $bytes, LOCK_EX) === false) {
            throw new ApiException('SERVER', '寫入分片失敗', 500, 50001);
        }
        $recv = array_map('intval', $meta['chunks_received'] ?? []);
        if (!in_array($n, $recv, true)) {
            $recv[] = $n;
            sort($recv);
        }
        $meta['chunks_received'] = $recv;
        $meta['updated_at'] = gmdate('c');
        $this->writeJson($this->pendingDir($id) . '/meta.json', $meta);

        return [
            'id' => $id,
            'chunk' => $n,
            'received' => count($recv),
            'expected' => $expected,
        ];
    }

    /** @return array<string, mixed> */
    public function complete(string $id, int $adminId): array
    {
        $this->assertEnabled();
        $meta = $this->requireMeta($id);
        if (($meta['status'] ?? '') !== self::STATUS_UPLOADING) {
            throw new ApiException('INVALID', '當前狀態不可完成上傳', 409, 40901);
        }
        $expected = (int) $meta['chunks_expected'];
        $recv = array_map('intval', $meta['chunks_received'] ?? []);
        if (count($recv) !== $expected) {
            throw new ApiException('INVALID', '分片未齊全（' . count($recv) . '/' . $expected . '）', 422, 42201);
        }

        $dir = $this->pendingDir($id);
        $bundle = $dir . '/bundle.tar';
        $out = fopen($bundle, 'wb');
        if ($out === false) {
            throw new ApiException('SERVER', '無法合併分片', 500, 50001);
        }
        for ($i = 0; $i < $expected; $i++) {
            $part = $dir . '/chunks/' . $i . '.part';
            $data = file_get_contents($part);
            if ($data === false) {
                fclose($out);
                throw new ApiException('SERVER', '讀取分片失敗 #' . $i, 500, 50001);
            }
            fwrite($out, $data);
        }
        fclose($out);

        $size = filesize($bundle) ?: 0;
        if ($size !== (int) $meta['total_bytes']) {
            @unlink($bundle);
            throw new ApiException('INVALID', '合併後大小與申報不符', 422, 42201);
        }
        $hash = hash_file('sha256', $bundle) ?: '';
        if (($meta['sha256'] ?? '') !== '' && !hash_equals((string) $meta['sha256'], $hash)) {
            @unlink($bundle);
            throw new ApiException('INVALID', '整包 SHA256 校驗失敗', 422, 42202);
        }

        $extractDir = $dir . '/bundle';
        $this->rmTree($extractDir);
        $this->mkdir($extractDir);
        $this->extractTar($bundle, $extractDir);

        $manifestPath = $extractDir . '/manifest.json';
        $packagePath = $extractDir . '/package.zip';
        if (!is_file($manifestPath) || !is_file($packagePath)) {
            throw new ApiException('INVALID', '發佈包缺少 manifest.json 或 package.zip', 422, 42203);
        }
        $manifest = json_decode((string) file_get_contents($manifestPath), true);
        if (!is_array($manifest)) {
            throw new ApiException('INVALID', 'manifest.json 無效', 422, 42203);
        }
        $this->verifyManifest($manifest, $packagePath, $extractDir . '/migrate.sql');

        // 落到 pending 根便於發佈
        @rename($packagePath, $dir . '/package.zip');
        if (is_file($extractDir . '/migrate.sql')) {
            @rename($extractDir . '/migrate.sql', $dir . '/migrate.sql');
        }
        @copy($manifestPath, $dir . '/manifest.json');
        $this->rmTree($extractDir);
        $this->rmTree($dir . '/chunks');
        @unlink($bundle);

        $meta['status'] = self::STATUS_READY;
        $meta['sha256'] = $hash;
        $meta['title'] = (string) ($manifest['title'] ?? '');
        $meta['description'] = (string) ($manifest['description'] ?? '');
        $meta['version'] = (string) ($manifest['version'] ?? '');
        $meta['has_migrate'] = is_file($dir . '/migrate.sql');
        $meta['manifest'] = $manifest;
        $meta['updated_at'] = gmdate('c');
        $meta['error'] = null;
        $this->writeJson($dir . '/meta.json', $meta);
        $this->audit('upload_complete', $adminId, $id, ['version' => $meta['version']]);

        return $this->publicMeta($meta);
    }

    /** @return array<string, mixed> */
    public function destroy(string $id, int $adminId): array
    {
        $this->assertEnabled();
        $meta = $this->requireMeta($id);
        $status = (string) ($meta['status'] ?? '');
        if (in_array($status, [self::STATUS_PUBLISHING], true)) {
            throw new ApiException('INVALID', '發佈中不可刪除', 409, 40901);
        }
        $this->rmTree($this->pendingDir($id));
        $this->audit('delete', $adminId, $id, ['status' => $status]);

        return ['id' => $id, 'deleted' => true];
    }

    /** @return array<string, mixed> */
    public function publish(string $id, int $adminId): array
    {
        $this->assertEnabled();
        @set_time_limit(0);
        ignore_user_abort(true);

        $meta = $this->requireMeta($id);
        if (($meta['status'] ?? '') !== self::STATUS_READY) {
            throw new ApiException('INVALID', '僅 ready 狀態可發佈', 409, 40901);
        }
        $state = $this->acquireLock($adminId, $id);
        $log = [];
        $snapId = null;

        try {
            $meta['status'] = self::STATUS_PUBLISHING;
            $meta['updated_at'] = gmdate('c');
            $this->writeJson($this->pendingDir($id) . '/meta.json', $meta);
            $this->logLine($id, $log, 'publish start');

            $dir = $this->pendingDir($id);
            $package = $dir . '/package.zip';
            $migrate = $dir . '/migrate.sql';
            $manifest = is_array($meta['manifest'] ?? null) ? $meta['manifest'] : [];
            if (!is_file($package)) {
                throw new ApiException('INVALID', '缺少 package.zip', 422, 42203);
            }

            $site = $this->siteRoot();
            $free = @disk_free_space($site);
            $need = (filesize($package) ?: 0) * 2 + 30 * 1024 * 1024;
            if ($free !== false && $free < $need) {
                throw new ApiException('DISK', '磁盤空間不足，無法快照/解壓', 507, 50701);
            }

            $hasMigrate = is_file($migrate);
            $requiresDb = $hasMigrate || !empty($manifest['requires_backup_db']);
            $snapId = $this->createSnapshot($id, $adminId, $requiresDb, $log);
            $this->logLine($id, $log, 'snapshot ' . $snapId);

            $staging = $this->releasesRoot() . '/staging/' . $id;
            $this->rmTree($staging);
            $this->mkdir($staging);
            $this->extractZipSafe($package, $staging, $manifest, $log);
            $this->logLine($id, $log, 'extracted & validated');

            $this->applyStaging($staging, $site, $manifest, $log);
            $this->logLine($id, $log, 'files applied');

            if ($hasMigrate) {
                try {
                    $n = $this->runSqlFile($migrate, $log);
                    $this->logLine($id, $log, 'sql ok statements=' . $n);
                } catch (\Throwable $e) {
                    $this->logLine($id, $log, 'sql FAILED: ' . $e->getMessage());
                    $rollbackFiles = filter_var(env('RELEASE_ROLLBACK_FILES_ON_SQL_FAIL', true), FILTER_VALIDATE_BOOLEAN);
                    if ($rollbackFiles && $snapId) {
                        $this->restoreFilesSnapshot($snapId, $log);
                        $this->logLine($id, $log, 'files restored after sql fail');
                    }
                    throw new ApiException(
                        'MIGRATE',
                        'SQL 執行失敗，文件' . ($rollbackFiles ? '已自動回滾' : '未回滾') . '：' . $e->getMessage(),
                        500,
                        50010,
                    );
                }
            }

            $this->rmTree($staging);
            $state = $this->readState();
            $state['previous_snapshot_id'] = $snapId;
            $state['current_release_id'] = $id;
            $state['updated_at'] = gmdate('c');
            $this->writeState($state);

            $meta['status'] = self::STATUS_PUBLISHED;
            $meta['published_at'] = gmdate('c');
            $meta['snapshot_id'] = $snapId;
            $meta['updated_at'] = gmdate('c');
            $meta['error'] = null;
            $this->writeJson($dir . '/meta.json', $meta);
            $this->logLine($id, $log, 'publish ok');
            $this->audit('publish', $adminId, $id, ['snapshot_id' => $snapId]);

            return [
                'release' => $this->publicMeta($meta),
                'snapshot_id' => $snapId,
                'state' => $this->state(),
            ];
        } catch (ApiException $e) {
            $meta['status'] = self::STATUS_FAILED;
            $meta['error'] = $e->getMessage();
            $meta['updated_at'] = gmdate('c');
            $this->writeJson($this->pendingDir($id) . '/meta.json', $meta);
            $this->logLine($id, $log, 'FAILED: ' . $e->getMessage());
            $this->audit('publish_fail', $adminId, $id, ['error' => $e->getMessage(), 'snapshot_id' => $snapId]);
            throw $e;
        } catch (\Throwable $e) {
            $meta['status'] = self::STATUS_FAILED;
            $meta['error'] = $e->getMessage();
            $meta['updated_at'] = gmdate('c');
            $this->writeJson($this->pendingDir($id) . '/meta.json', $meta);
            $this->logLine($id, $log, 'FAILED: ' . $e->getMessage());
            $this->audit('publish_fail', $adminId, $id, ['error' => $e->getMessage(), 'snapshot_id' => $snapId]);
            throw new ApiException('SERVER', '發佈失敗：' . $e->getMessage(), 500, 50000);
        } finally {
            $this->releaseLock();
        }
    }

    /**
     * 回退到 previous 快照（P0：默認僅文件；restore_db 需顯式 true）。
     *
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    public function rollback(array $body, int $adminId): array
    {
        $this->assertEnabled();
        @set_time_limit(0);
        ignore_user_abort(true);

        $restoreDb = !empty($body['restore_db']);
        $confirm = trim((string) ($body['confirm'] ?? ''));
        if ($restoreDb && $confirm !== 'ROLLBACK_DB') {
            throw new ApiException('INVALID', '還原數據庫須 confirm=ROLLBACK_DB', 422, 42201);
        }

        $state = $this->acquireLock($adminId, 'rollback');
        $snapId = (string) ($state['previous_snapshot_id'] ?? '');
        if ($snapId === '') {
            $this->releaseLock();
            throw new ApiException('INVALID', '沒有可回退的快照', 409, 40902);
        }
        $log = [];
        $logId = 'rollback_' . gmdate('Ymd_His');

        try {
            $this->logLine($logId, $log, 'rollback start snap=' . $snapId);
            $this->restoreFilesSnapshot($snapId, $log);
            if ($restoreDb) {
                $this->restoreDbSnapshot($snapId, $log);
            }
            $state = $this->readState();
            $prevRelease = (string) ($state['current_release_id'] ?? '');
            if ($prevRelease !== '' && is_dir($this->pendingDir($prevRelease))) {
                $meta = $this->readJson($this->pendingDir($prevRelease) . '/meta.json') ?: [];
                $meta['status'] = self::STATUS_ROLLED_BACK;
                $meta['updated_at'] = gmdate('c');
                $this->writeJson($this->pendingDir($prevRelease) . '/meta.json', $meta);
            }
            $state['current_release_id'] = null;
            $state['updated_at'] = gmdate('c');
            // previous 保留，允許再次查看；不自動清空
            $this->writeState($state);
            $this->logLine($logId, $log, 'rollback ok');
            $this->audit('rollback', $adminId, $snapId, ['restore_db' => $restoreDb, 'from_release' => $prevRelease]);

            return [
                'snapshot_id' => $snapId,
                'restore_db' => $restoreDb,
                'state' => $this->state(),
            ];
        } catch (ApiException $e) {
            $this->logLine($logId, $log, 'FAILED: ' . $e->getMessage());
            throw $e;
        } catch (\Throwable $e) {
            $this->logLine($logId, $log, 'FAILED: ' . $e->getMessage());
            throw new ApiException('SERVER', '回退失敗：' . $e->getMessage(), 500, 50000);
        } finally {
            $this->releaseLock();
        }
    }

    /** @return list<array<string, mixed>> */
    public function auditTail(int $limit = 50): array
    {
        $this->assertEnabled();
        $path = $this->releasesRoot() . '/logs/audit.jsonl';
        if (!is_file($path)) {
            return [];
        }
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        $lines = array_slice($lines, -max(1, min(200, $limit)));
        $out = [];
        foreach ($lines as $line) {
            $row = json_decode($line, true);
            if (is_array($row)) {
                $out[] = $row;
            }
        }

        return array_reverse($out);
    }

    // ─── internals ─────────────────────────────────────────────

    private function siteRoot(): string
    {
        $env = trim((string) env('RELEASE_SITE_ROOT', ''));
        if ($env !== '') {
            $real = realpath($env);
            if ($real === false || !is_dir($real)) {
                throw new ApiException('CONFIG', 'RELEASE_SITE_ROOT 無效', 500, 50002);
            }

            return $real;
        }
        $appRoot = rtrim(str_replace('\\', '/', app()->getRootPath()), '/');
        $base = basename($appRoot);
        $parent = dirname($appRoot);
        if ($base === 'sm-backend' && (is_dir($parent . '/admin') || is_file($parent . '/index.html'))) {
            return $parent;
        }
        // 本地開發：apps/api → 可用 runtime/release-site 作沙箱
        $sandbox = $appRoot . '/runtime/release-site';
        $this->mkdir($sandbox);

        return $sandbox;
    }

    private function releasesRoot(): string
    {
        $root = $this->siteRoot() . '/_releases';
        $this->mkdir($root);
        $this->mkdir($root . '/pending');
        $this->mkdir($root . '/staging');
        $this->mkdir($root . '/snapshots');
        $this->mkdir($root . '/logs');
        $ht = $root . '/.htaccess';
        if (!is_file($ht)) {
            file_put_contents($ht, "Require all denied\nDeny from all\n");
        }
        $deny = $root . '/index.html';
        if (!is_file($deny)) {
            file_put_contents($deny, '');
        }

        return $root;
    }

    private function pendingDir(string $id): string
    {
        if (!preg_match('/^rel_[A-Za-z0-9_]+$/', $id)) {
            throw new ApiException('INVALID', '無效 release id', 422, 42201);
        }

        return $this->releasesRoot() . '/pending/' . $id;
    }

    /** @return array<string, mixed> */
    private function requireMeta(string $id): array
    {
        $path = $this->pendingDir($id) . '/meta.json';
        $meta = $this->readJson($path);
        if (!$meta) {
            throw new ApiException('NOT_FOUND', '發佈包不存在', 404, 40400);
        }

        return $meta;
    }

    /** @param array<string, mixed> $meta @return array<string, mixed> */
    private function publicMeta(array $meta): array
    {
        return [
            'id' => $meta['id'] ?? null,
            'status' => $meta['status'] ?? null,
            'filename' => $meta['filename'] ?? null,
            'total_bytes' => $meta['total_bytes'] ?? null,
            'sha256' => $meta['sha256'] ?? null,
            'title' => $meta['title'] ?? '',
            'description' => $meta['description'] ?? '',
            'version' => $meta['version'] ?? '',
            'has_migrate' => (bool) ($meta['has_migrate'] ?? false),
            'created_at' => $meta['created_at'] ?? null,
            'updated_at' => $meta['updated_at'] ?? null,
            'published_at' => $meta['published_at'] ?? null,
            'snapshot_id' => $meta['snapshot_id'] ?? null,
            'error' => $meta['error'] ?? null,
            'chunks_received' => isset($meta['chunks_received']) ? count((array) $meta['chunks_received']) : null,
            'chunks_expected' => $meta['chunks_expected'] ?? null,
        ];
    }

    /** @param array<string, mixed> $manifest */
    private function verifyManifest(array $manifest, string $packagePath, string $migratePath): void
    {
        if ((int) ($manifest['schema'] ?? 0) !== self::SCHEMA) {
            throw new ApiException('INVALID', '不支持的 manifest schema', 422, 42203);
        }
        $sig = (string) ($manifest['signature'] ?? '');
        if ($sig === '') {
            throw new ApiException('INVALID', 'manifest 缺少 signature', 422, 42204);
        }
        $expected = $this->signManifest($manifest);
        if (!hash_equals($expected, $sig)) {
            throw new ApiException('INVALID', 'manifest 簽名校驗失敗', 422, 42204);
        }
        $pkgHash = hash_file('sha256', $packagePath) ?: '';
        if (!hash_equals(strtolower((string) ($manifest['package_sha256'] ?? '')), $pkgHash)) {
            throw new ApiException('INVALID', 'package.zip SHA256 不匹配', 422, 42202);
        }
        $migDeclared = $manifest['migrate_sha256'] ?? null;
        if ($migDeclared) {
            if (!is_file($migratePath)) {
                throw new ApiException('INVALID', 'manifest 聲明了 migrate 但文件缺失', 422, 42203);
            }
            $migHash = hash_file('sha256', $migratePath) ?: '';
            if (!hash_equals(strtolower((string) $migDeclared), $migHash)) {
                throw new ApiException('INVALID', 'migrate.sql SHA256 不匹配', 422, 42202);
            }
        } elseif (is_file($migratePath)) {
            throw new ApiException('INVALID', '存在 migrate.sql 但 manifest 未聲明 migrate_sha256', 422, 42203);
        }
    }

    /** @param array<string, mixed> $manifest */
    public function signManifest(array $manifest): string
    {
        $copy = $manifest;
        unset($copy['signature']);
        $canonical = $this->canonicalJson($copy);

        return hash_hmac('sha256', $canonical, $this->hmacSecret);
    }

    /** @param array<string, mixed> $data */
    private function canonicalJson(array $data): string
    {
        $this->ksortRecursive($data);
        $json = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            throw new ApiException('SERVER', 'manifest 序列化失敗', 500, 50000);
        }

        return $json;
    }

    /** @param array<mixed> $data */
    private function ksortRecursive(array &$data): void
    {
        ksort($data);
        foreach ($data as &$v) {
            if (is_array($v)) {
                $isList = array_keys($v) === range(0, count($v) - 1);
                if (!$isList) {
                    $this->ksortRecursive($v);
                }
            }
        }
    }

    private function extractTar(string $tarPath, string $dest): void
    {
        $pharPath = $tarPath;
        $tmpGz = null;
        if (preg_match('/\.(tar\.gz|tgz)$/i', $tarPath) || $this->isGzip($tarPath)) {
            $tmpGz = $dest . '/_bundle.tar';
            $data = file_get_contents($tarPath);
            if ($data === false) {
                throw new ApiException('INVALID', '無法讀取壓縮包', 422, 42203);
            }
            $raw = @gzdecode($data);
            if ($raw === false) {
                // 也可能是未壓縮 tar 誤標
                $pharPath = $tarPath;
            } else {
                file_put_contents($tmpGz, $raw);
                $pharPath = $tmpGz;
            }
        }
        try {
            // 共享主機常 disable_functions=exec，禁止 shell tar；僅用 PharData
            $phar = new \PharData($pharPath);
            $phar->extractTo($dest, null, true);
        } catch (\Throwable $e) {
            throw new ApiException('INVALID', '解壓 tar 失敗：' . $e->getMessage(), 422, 42203);
        } finally {
            if ($tmpGz && is_file($tmpGz)) {
                @unlink($tmpGz);
            }
        }
    }

    private function isGzip(string $path): bool
    {
        $fh = fopen($path, 'rb');
        if (!$fh) {
            return false;
        }
        $magic = fread($fh, 2);
        fclose($fh);

        return $magic === "\x1f\x8b";
    }

    /**
     * @param array<string, mixed> $manifest
     * @param list<string> $log
     */
    private function extractZipSafe(string $zipPath, string $staging, array $manifest, array &$log): void
    {
        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            throw new ApiException('INVALID', '無法打開 package.zip', 422, 42203);
        }
        $whitelist = $this->normalizePrefixList($manifest['path_whitelist'] ?? self::DEFAULT_WHITELIST);
        $blacklist = $this->normalizePrefixList($manifest['path_blacklist'] ?? self::DEFAULT_BLACKLIST);

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            if ($stat === false) {
                continue;
            }
            $name = str_replace('\\', '/', (string) $stat['name']);
            if ($name === '' || str_ends_with($name, '/')) {
                continue;
            }
            if (str_starts_with($name, '/') || str_contains($name, "\0") || preg_match('#(^|/)\.\.(/|$)#', $name)) {
                $zip->close();
                throw new ApiException('INVALID', '檢測到非法路徑（zip slip）：' . $name, 422, 42205);
            }
            if (!$this->pathAllowed($name, $whitelist, $blacklist)) {
                $zip->close();
                throw new ApiException('INVALID', '路徑不在白名單或命中黑名單：' . $name, 422, 42205);
            }
        }

        if (!$zip->extractTo($staging)) {
            $zip->close();
            throw new ApiException('SERVER', '解壓 package.zip 失敗', 500, 50001);
        }
        $zip->close();

        // 二次掃描落地文件
        $this->assertTreeSafe($staging, $whitelist, $blacklist);
        $this->logLine('extract', $log, 'entries validated');
    }

    /**
     * @param list<string> $whitelist
     * @param list<string> $blacklist
     */
    private function assertTreeSafe(string $root, array $whitelist, array $blacklist): void
    {
        $rootReal = realpath($root);
        if ($rootReal === false) {
            throw new ApiException('SERVER', 'staging 無效', 500, 50001);
        }
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($rootReal, \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $file) {
            /** @var \SplFileInfo $file */
            if (!$file->isFile()) {
                continue;
            }
            $full = $file->getPathname();
            $real = realpath($full);
            if ($real === false || !str_starts_with($real, $rootReal)) {
                throw new ApiException('INVALID', '檢測到越界文件', 422, 42205);
            }
            $rel = ltrim(str_replace('\\', '/', substr($real, strlen($rootReal))), '/');
            if (!$this->pathAllowed($rel, $whitelist, $blacklist)) {
                throw new ApiException('INVALID', '落地後校驗失敗：' . $rel, 422, 42205);
            }
        }
    }

    /**
     * @param list<string> $whitelist
     * @param list<string> $blacklist
     */
    private function pathAllowed(string $rel, array $whitelist, array $blacklist): bool
    {
        $rel = ltrim(str_replace('\\', '/', $rel), '/');
        // 黑名單始終生效（.env / runtime / storage / _releases）
        foreach ($blacklist as $b) {
            if ($this->prefixMatch($rel, $b)) {
                return false;
            }
        }
        // 默認不強制白名單：包已 HMAC 簽名 + zip-slip 檢查足夠；
        // 需嚴格模式時設 RELEASE_ENFORCE_PATH_WHITELIST=true
        $enforce = filter_var(env('RELEASE_ENFORCE_PATH_WHITELIST', false), FILTER_VALIDATE_BOOLEAN);
        if (!$enforce) {
            return true;
        }
        foreach ($whitelist as $w) {
            if ($this->prefixMatch($rel, $w)) {
                return true;
            }
        }
        if ($this->isSafeRootStatic($rel)) {
            return true;
        }

        return false;
    }

    /** 僅允許網站根下單層安全靜態檔（無子路徑） */
    private function isSafeRootStatic(string $rel): bool
    {
        if ($rel === '' || str_contains($rel, '/') || str_contains($rel, '\\')) {
            return false;
        }
        if ($rel === '.htaccess' || $rel === 'site.webmanifest' || $rel === 'README-DEPLOY.txt') {
            return true;
        }
        // favicon* / apple-touch-icon* / android-chrome* / icons.svg 等
        if (preg_match('/^(favicon|apple-touch-icon|android-chrome)[a-zA-Z0-9._-]*\.(ico|png|svg)$/i', $rel)) {
            return true;
        }
        if (preg_match('/^icons\.svg$/i', $rel)) {
            return true;
        }
        // 其餘根目錄 png/ico/svg（防漏列）
        return (bool) preg_match('/\.(png|ico|svg|webmanifest)$/i', $rel);
    }

    private function prefixMatch(string $rel, string $rule): bool
    {
        $rule = ltrim(str_replace('\\', '/', $rule), '/');
        if ($rule === '') {
            return false;
        }
        if (str_ends_with($rule, '/')) {
            return $rel === rtrim($rule, '/') || str_starts_with($rel, $rule);
        }

        return $rel === $rule || str_starts_with($rel, $rule . '/');
    }

    /** @param list<mixed> $list @return list<string> */
    private function normalizePrefixList(mixed $list): array
    {
        if (!is_array($list) || $list === []) {
            return self::DEFAULT_WHITELIST;
        }
        $out = [];
        foreach ($list as $item) {
            $s = ltrim(str_replace('\\', '/', (string) $item), '/');
            if ($s !== '') {
                $out[] = $s;
            }
        }

        return $out ?: self::DEFAULT_WHITELIST;
    }

    /**
     * @param array<string, mixed> $manifest
     * @param list<string> $log
     */
    private function applyStaging(string $staging, string $site, array $manifest, array &$log): void
    {
        $whitelist = $this->normalizePrefixList($manifest['path_whitelist'] ?? self::DEFAULT_WHITELIST);
        $blacklist = $this->normalizePrefixList($manifest['path_blacklist'] ?? self::DEFAULT_BLACKLIST);
        $stagingReal = realpath($staging);
        if ($stagingReal === false) {
            throw new ApiException('SERVER', 'staging 不存在', 500, 50001);
        }
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($stagingReal, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST,
        );
        $count = 0;
        foreach ($it as $file) {
            /** @var \SplFileInfo $file */
            $full = $file->getPathname();
            $rel = ltrim(str_replace('\\', '/', substr($full, strlen($stagingReal))), '/');
            if ($rel === '') {
                continue;
            }
            if (!$this->pathAllowed($rel, $whitelist, $blacklist)) {
                continue;
            }
            $dest = $site . '/' . $rel;
            if ($file->isDir()) {
                $this->mkdir($dest);
                continue;
            }
            $this->mkdir(dirname($dest));
            if (!@copy($full, $dest)) {
                throw new ApiException('SERVER', '覆蓋失敗：' . $rel, 500, 50001);
            }
            $count++;
        }
        $this->logLine('apply', $log, 'copied_files=' . $count);
    }

    /**
     * @param list<string> $log
     */
    private function createSnapshot(string $releaseId, int $adminId, bool $withDb, array &$log): string
    {
        $snapId = 'snap_' . gmdate('Ymd_His') . '_' . bin2hex(random_bytes(2));
        $dir = $this->releasesRoot() . '/snapshots/' . $snapId;
        $this->mkdir($dir);
        $site = $this->siteRoot();

        $paths = [];
        foreach (self::DEFAULT_WHITELIST as $p) {
            $p = rtrim($p, '/');
            if ($p !== '' && file_exists($site . '/' . $p)) {
                $paths[] = $p;
            }
        }
        // 用 ZipArchive（不依賴 exec/tar；共享主機常禁 shell）
        $zipPath = $dir . '/files.zip';
        if ($paths !== []) {
            $this->zipSnapshotFromSite($site, $paths, $zipPath);
            $this->logLine($releaseId, $log, 'snapshot zip ok bytes=' . (filesize($zipPath) ?: 0));
        } else {
            // 空快照：建一個空 zip
            $z = new ZipArchive();
            if ($z->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new ApiException('SERVER', '無法創建空快照', 500, 50001);
            }
            $z->close();
            $this->logLine($releaseId, $log, 'snapshot empty files (fresh site)');
        }

        $dbFile = null;
        if ($withDb) {
            $dbFile = $dir . '/db.sql.gz';
            $this->dumpDb($dbFile, $log);
        }

        $meta = [
            'id' => $snapId,
            'release_id' => $releaseId,
            'created_at' => gmdate('c'),
            'created_by' => $adminId,
            'has_files' => is_file($zipPath) && (filesize($zipPath) ?: 0) > 22,
            'has_db' => $dbFile !== null && is_file($dbFile),
            'files_format' => 'zip',
        ];
        $this->writeJson($dir . '/meta.json', $meta);
        $this->pruneSnapshots(5);

        return $snapId;
    }

    /**
     * 純 PHP 打快照 zip（排除 .env / runtime / storage）。
     *
     * @param list<string> $paths 相對網站根的白名單路徑
     */
    private function zipSnapshotFromSite(string $site, array $paths, string $zipPath): void
    {
        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new ApiException('SERVER', '無法創建文件快照 zip', 500, 50001);
        }
        $excludePrefixes = [
            'sm-backend/.env',
            'sm-backend/runtime/',
            'sm-backend/public/storage/',
        ];
        $count = 0;
        foreach ($paths as $relRoot) {
            $abs = $site . '/' . $relRoot;
            if (is_file($abs)) {
                if ($this->snapshotPathExcluded($relRoot, $excludePrefixes)) {
                    continue;
                }
                $zip->addFile($abs, $relRoot);
                $count++;
                continue;
            }
            if (!is_dir($abs)) {
                continue;
            }
            $it = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($abs, \FilesystemIterator::SKIP_DOTS),
            );
            foreach ($it as $file) {
                /** @var \SplFileInfo $file */
                if (!$file->isFile()) {
                    continue;
                }
                $full = $file->getPathname();
                $rel = ltrim(str_replace('\\', '/', substr($full, strlen($site))), '/');
                if ($this->snapshotPathExcluded($rel, $excludePrefixes)) {
                    continue;
                }
                $zip->addFile($full, $rel);
                $count++;
            }
        }
        $zip->close();
        if ($count === 0 || !is_file($zipPath)) {
            throw new ApiException('SERVER', '文件快照為空或寫入失敗', 500, 50001);
        }
    }

    /** @param list<string> $excludePrefixes */
    private function snapshotPathExcluded(string $rel, array $excludePrefixes): bool
    {
        $rel = ltrim(str_replace('\\', '/', $rel), '/');
        foreach ($excludePrefixes as $ex) {
            if ($rel === rtrim($ex, '/') || str_starts_with($rel, $ex)) {
                return true;
            }
        }

        return false;
    }

    /** @param list<string> $log */
    private function dumpDb(string $outGz, array &$log): void
    {
        // 純 PDO，不依賴 mysqldump / exec
        try {
            /** @var \PDO $pdo */
            $pdo = Db::connect()->getPdo();
        } catch (\Throwable $e) {
            throw new ApiException('CONFIG', '無法連接數據庫做備份：' . $e->getMessage(), 500, 50002);
        }
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $dbName = (string) env('DB_NAME', '');
        $sql = "-- SisterMart PHP dump\n-- db={$dbName}\nSET FOREIGN_KEY_CHECKS=0;\n\n";
        $tables = $pdo->query('SHOW TABLES')->fetchAll(\PDO::FETCH_COLUMN);
        foreach ($tables as $table) {
            $table = (string) $table;
            $create = $pdo->query('SHOW CREATE TABLE `' . str_replace('`', '``', $table) . '`')->fetch(\PDO::FETCH_ASSOC);
            $createSql = (string) ($create['Create Table'] ?? $create['Create View'] ?? '');
            if ($createSql === '') {
                continue;
            }
            $sql .= "DROP TABLE IF EXISTS `{$table}`;\n{$createSql};\n\n";
            $rows = $pdo->query('SELECT * FROM `' . str_replace('`', '``', $table) . '`');
            while ($row = $rows->fetch(\PDO::FETCH_ASSOC)) {
                $cols = [];
                $vals = [];
                foreach ($row as $k => $v) {
                    $cols[] = '`' . str_replace('`', '``', (string) $k) . '`';
                    if ($v === null) {
                        $vals[] = 'NULL';
                    } else {
                        $vals[] = $pdo->quote((string) $v);
                    }
                }
                $sql .= 'INSERT INTO `' . $table . '` (' . implode(',', $cols) . ') VALUES (' . implode(',', $vals) . ");\n";
            }
            $sql .= "\n";
        }
        $sql .= "SET FOREIGN_KEY_CHECKS=1;\n";
        $encoded = gzencode($sql, 6);
        if ($encoded === false) {
            throw new ApiException('SERVER', '壓縮 DB 快照失敗', 500, 50001);
        }
        if (file_put_contents($outGz, $encoded) === false) {
            throw new ApiException('SERVER', '寫入 DB 快照失敗', 500, 50001);
        }
        $this->logLine('db', $log, 'php dump ok tables=' . count($tables) . ' bytes=' . strlen($sql));
    }

    /** @param list<string> $log */
    private function restoreFilesSnapshot(string $snapId, array &$log): void
    {
        if (!preg_match('/^snap_[A-Za-z0-9_]+$/', $snapId)) {
            throw new ApiException('INVALID', '無效 snapshot id', 422, 42201);
        }
        $base = $this->releasesRoot() . '/snapshots/' . $snapId;
        $zipPath = $base . '/files.zip';
        $tgzPath = $base . '/files.tgz';
        $site = $this->siteRoot();

        if (is_file($zipPath) && (filesize($zipPath) ?: 0) > 22) {
            $zip = new ZipArchive();
            if ($zip->open($zipPath) !== true) {
                throw new ApiException('SERVER', '無法打開快照 zip', 500, 50001);
            }
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i);
                if ($stat === false) {
                    continue;
                }
                $name = str_replace('\\', '/', (string) $stat['name']);
                if ($name === '' || str_ends_with($name, '/')) {
                    continue;
                }
                if (str_contains($name, '..') || str_starts_with($name, '/')) {
                    $zip->close();
                    throw new ApiException('INVALID', '快照含非法路徑', 422, 42205);
                }
            }
            if (!$zip->extractTo($site)) {
                $zip->close();
                throw new ApiException('SERVER', '還原文件失敗（zip）', 500, 50001);
            }
            $zip->close();
            $this->logLine('rollback', $log, 'files restored from zip ' . $snapId);

            return;
        }

        if (is_file($tgzPath) && (filesize($tgzPath) ?: 0) > 0) {
            // 舊格式：嘗試 PharData（仍不依賴 exec）
            $tmp = $base . '/_restore.tar';
            $raw = file_get_contents($tgzPath);
            if ($raw === false) {
                throw new ApiException('SERVER', '讀取 tgz 失敗', 500, 50001);
            }
            $decoded = @gzdecode($raw);
            if ($decoded === false) {
                throw new ApiException('SERVER', '解壓 tgz 失敗', 500, 50001);
            }
            file_put_contents($tmp, $decoded);
            try {
                $phar = new \PharData($tmp);
                $phar->extractTo($site, null, true);
            } catch (\Throwable $e) {
                @unlink($tmp);
                throw new ApiException('SERVER', '還原 tgz 失敗：' . $e->getMessage(), 500, 50001);
            }
            @unlink($tmp);
            $this->logLine('rollback', $log, 'files restored from tgz ' . $snapId);

            return;
        }

        throw new ApiException('INVALID', '快照無文件可還原', 409, 40902);
    }

    /** @param list<string> $log */
    private function restoreDbSnapshot(string $snapId, array &$log): void
    {
        $gz = $this->releasesRoot() . '/snapshots/' . $snapId . '/db.sql.gz';
        if (!is_file($gz)) {
            throw new ApiException('INVALID', '快照無數據庫備份', 409, 40902);
        }
        $raw = file_get_contents($gz);
        if ($raw === false) {
            throw new ApiException('SERVER', '讀取 db 快照失敗', 500, 50001);
        }
        $sql = @gzdecode($raw);
        if ($sql === false) {
            throw new ApiException('SERVER', '解壓 db 快照失敗', 500, 50001);
        }
        $tmp = $gz . '.restore.sql';
        file_put_contents($tmp, $sql);
        try {
            $n = $this->runSqlFile($tmp, $log);
            $this->logLine('rollback', $log, 'db restored statements=' . $n . ' from ' . $snapId);
        } finally {
            @unlink($tmp);
        }
    }

    /** @param list<string> $log */
    private function runSqlFile(string $path, array &$log): int
    {
        $sql = file_get_contents($path);
        if ($sql === false || trim($sql) === '') {
            throw new ApiException('INVALID', 'migrate.sql 為空', 422, 42203);
        }
        $statements = $this->splitSql($sql);
        $n = 0;
        foreach ($statements as $stmt) {
            $trim = trim($stmt);
            if ($trim === '') {
                continue;
            }
            try {
                Db::execute($trim);
                $n++;
            } catch (\Throwable $e) {
                throw new ApiException('MIGRATE', '語句失敗：' . mb_substr($trim, 0, 120) . '… → ' . $e->getMessage(), 500, 50010);
            }
        }
        $this->logLine('sql', $log, 'executed=' . $n);

        return $n;
    }

    /** @return list<string> */
    private function splitSql(string $sql): array
    {
        $sql = preg_replace('/^\xEF\xBB\xBF/', '', $sql) ?? $sql;
        $out = [];
        $buf = '';
        $lines = preg_split('/\R/', $sql) ?: [];
        foreach ($lines as $line) {
            $trim = ltrim($line);
            if ($trim === '' || str_starts_with($trim, '--') || str_starts_with($trim, '#')) {
                continue;
            }
            $buf .= $line . "\n";
            if (str_ends_with(rtrim($line), ';')) {
                $out[] = $buf;
                $buf = '';
            }
        }
        if (trim($buf) !== '') {
            $out[] = $buf;
        }

        return $out;
    }

    private function pruneSnapshots(int $keep): void
    {
        $dir = $this->releasesRoot() . '/snapshots';
        $names = array_values(array_filter(scandir($dir) ?: [], static fn ($n) => str_starts_with($n, 'snap_')));
        rsort($names);
        foreach (array_slice($names, $keep) as $old) {
            $this->rmTree($dir . '/' . $old);
        }
    }

    /** @return array<string, mixed> */
    private function acquireLock(int $adminId, string $releaseId): array
    {
        $state = $this->readState();
        $this->maybeExpireLock($state);
        $state = $this->readState();
        if (!empty($state['lock'])) {
            throw new ApiException('LOCKED', '已有發佈/回退任務進行中', 409, 40903);
        }
        $state['lock'] = [
            'admin_id' => $adminId,
            'release_id' => $releaseId,
            'at' => gmdate('c'),
            'expires_at' => gmdate('c', time() + $this->lockTtl),
        ];
        $state['updated_at'] = gmdate('c');
        $this->writeState($state);

        return $state;
    }

    private function releaseLock(): void
    {
        $state = $this->readState();
        $state['lock'] = null;
        $state['updated_at'] = gmdate('c');
        $this->writeState($state);
    }

    /** @param array<string, mixed> $state */
    private function maybeExpireLock(array &$state): void
    {
        $lock = $state['lock'] ?? null;
        if (!is_array($lock)) {
            return;
        }
        $exp = strtotime((string) ($lock['expires_at'] ?? '')) ?: 0;
        if ($exp > 0 && time() > $exp) {
            $state['lock'] = null;
            $state['updated_at'] = gmdate('c');
            $this->writeState($state);
            $this->audit('lock_expired', 0, (string) ($lock['release_id'] ?? ''), []);
        }
    }

    /** @return array<string, mixed> */
    private function readState(): array
    {
        $path = $this->releasesRoot() . '/state.json';
        $data = $this->readJson($path);

        return $data ?: [
            'current_release_id' => null,
            'previous_snapshot_id' => null,
            'lock' => null,
            'updated_at' => null,
        ];
    }

    /** @param array<string, mixed> $state */
    private function writeState(array $state): void
    {
        $this->writeJson($this->releasesRoot() . '/state.json', $state);
    }

    /** @param array<string, mixed> $details */
    private function audit(string $action, int $adminId, string $target, array $details): void
    {
        $path = $this->releasesRoot() . '/logs/audit.jsonl';
        $row = [
            'at' => gmdate('c'),
            'action' => $action,
            'admin_id' => $adminId,
            'target' => $target,
            'details' => $details,
        ];
        file_put_contents($path, json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n", FILE_APPEND | LOCK_EX);
    }

    /** @param list<string> $log */
    private function logLine(string $id, array &$log, string $line): void
    {
        $line = '[' . gmdate('c') . '] ' . $line;
        $log[] = $line;
        $path = $this->releasesRoot() . '/logs/publish_' . preg_replace('/[^A-Za-z0-9_\-]/', '_', $id) . '.log';
        file_put_contents($path, $line . "\n", FILE_APPEND | LOCK_EX);
    }

    /** @return array<string, mixed>|null */
    private function readJson(string $path): ?array
    {
        if (!is_file($path)) {
            return null;
        }
        $data = json_decode((string) file_get_contents($path), true);

        return is_array($data) ? $data : null;
    }

    /** @param array<string, mixed> $data */
    private function writeJson(string $path, array $data): void
    {
        $this->mkdir(dirname($path));
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new ApiException('SERVER', 'JSON 編碼失敗', 500, 50000);
        }
        $tmp = $path . '.tmp';
        if (file_put_contents($tmp, $json . "\n", LOCK_EX) === false) {
            throw new ApiException('SERVER', '寫入失敗：' . basename($path), 500, 50001);
        }
        if (!@rename($tmp, $path)) {
            @unlink($path);
            if (!@rename($tmp, $path)) {
                throw new ApiException('SERVER', '原子寫入失敗：' . basename($path), 500, 50001);
            }
        }
    }

    private function mkdir(string $dir): void
    {
        if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new ApiException('SERVER', '無法創建目錄：' . $dir, 500, 50001);
        }
    }

    private function rmTree(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($it as $file) {
            /** @var \SplFileInfo $file */
            $file->isDir() ? @rmdir($file->getPathname()) : @unlink($file->getPathname());
        }
        @rmdir($dir);
    }
}
