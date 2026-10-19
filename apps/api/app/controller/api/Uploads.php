<?php

declare(strict_types=1);

namespace app\controller\api;

use app\controller\ApiController;
use app\service\ImageUploadService;
use app\support\ApiException;
use app\support\ImageProcess;
use think\facade\Db;
use think\file\UploadedFile;
use think\Response;

class Uploads extends ApiController
{
    public function aftersaleEvidence(): Response
    {
        return $this->run(function () {
            $file = $this->request->file('file');
            if (!$file) {
                throw new ApiException('INVALID', '請選擇圖片', 422, 42201);
            }
            $memberId = (int) app('sistermart.member')['id'];

            return (new ImageUploadService())->store($file, [
                'preset' => 'aftersale',
                'bucket' => 'aftersale',
                'owner_id' => $memberId,
            ]);
        }, 201);
    }

    public function reviewImage(): Response
    {
        return $this->run(function () {
            $file = $this->request->file('file');
            if (!$file) {
                throw new ApiException('INVALID', '請選擇圖片', 422, 42201);
            }
            $memberId = (int) app('sistermart.member')['id'];

            return (new ImageUploadService())->store($file, [
                'preset' => 'review',
                'bucket' => 'review',
                'owner_id' => $memberId,
            ]);
        }, 201);
    }

    public function avatar(): Response
    {
        return $this->run(function () {
            try {
                return $this->saveAvatar();
            } catch (ApiException $e) {
                throw $e;
            } catch (\Throwable $e) {
                throw $this->wrapAvatarError($e);
            }
        }, 201);
    }

    /** @return array{url: string, member: array<string,mixed>} */
    private function saveAvatar(): array
    {
        $memberId = (int) app('sistermart.member')['id'];
        $this->ensureAvatarColumn();

        $file = $this->request->file('file');
        if (!$file) {
            throw new ApiException('INVALID', '請選擇圖片（上傳可能被主機禁用，請換 JPG 再試）', 422, 42201);
        }
        $size = (int) $file->getSize();
        if ($size <= 0 || $size > 5 * 1024 * 1024) {
            throw new ApiException('INVALID', '圖片過大（上限 5MB）', 422, 42289);
        }
        $ext = $this->resolveImageExt($file);
        $relDir = 'avatar/' . $memberId . '/' . date('Ymd');
        $root = app()->getRootPath() . 'public/storage/' . $relDir;
        if (!is_dir($root) && !mkdir($root, 0775, true) && !is_dir($root)) {
            throw new ApiException('SERVER', '無法建立上傳目錄，請檢查 sm-backend/public/storage 寫入權限', 500, 50001);
        }
        $tmpName = bin2hex(random_bytes(8)) . '.' . $ext;
        try {
            $file->move($root, $tmpName);
        } catch (\Throwable $e) {
            throw new ApiException('SERVER', '圖片寫入失敗：' . mb_substr($e->getMessage(), 0, 80), 500, 50001);
        }
        $tmpPath = $root . '/' . $tmpName;
        if (!is_file($tmpPath)) {
            throw new ApiException('SERVER', '圖片寫入失敗（移動後找不到檔案）', 500, 50001);
        }
        $outName = bin2hex(random_bytes(8)) . '.jpg';
        $destPath = $root . '/' . $outName;
        try {
            ImageProcess::compress($tmpPath, $destPath, [
                'max_edge' => 512,
                'max_bytes' => 512 * 1024,
                'quality' => 85,
            ]);
            $this->cropSquare($destPath, 512);
        } finally {
            @unlink($tmpPath);
        }
        $url = '/storage/' . $relDir . '/' . $outName;
        $now = date('Y-m-d H:i:s');
        // 必須用原生 SQL：ThinkPHP 在同請求內若已緩存 members 欄位表（如 MemberAuth 先查過），
        // ALTER 後 Db::name()->update(['avatar_url'=>…]) 仍會報 fields not exists:[avatar_url]
        $table = $this->membersTable();
        try {
            Db::execute(
                "UPDATE `{$table}` SET `avatar_url` = ?, `updated_at` = ? WHERE `id` = ?",
                [$url, $now, $memberId],
            );
        } catch (\Throwable $e) {
            $this->refreshMembersSchema();
            Db::execute(
                "UPDATE `{$table}` SET `avatar_url` = ?, `updated_at` = ? WHERE `id` = ?",
                [$url, $now, $memberId],
            );
        }
        $member = Db::query("SELECT * FROM `{$table}` WHERE `id` = ? LIMIT 1", [$memberId]);
        $row = is_array($member[0] ?? null) ? $member[0] : [];
        $row['avatar_url'] = $url;

        return [
            'url' => $url,
            'member' => (new \app\service\MemberAuthService())->present($row),
        ];
    }

    private function membersTable(): string
    {
        $prefix = (string) (Db::getConnection()->getConfig('prefix') ?: '');

        return $prefix . 'members';
    }

    private function ensureAvatarColumn(): void
    {
        $table = $this->membersTable();
        try {
            $rows = Db::query("SHOW COLUMNS FROM `{$table}` LIKE 'avatar_url'");
            if ($rows) {
                $this->refreshMembersSchema();

                return;
            }
            Db::execute(
                "ALTER TABLE `{$table}` ADD COLUMN `avatar_url` VARCHAR(512) NULL COMMENT '頭像路徑' AFTER `locale`",
            );
            $this->refreshMembersSchema();
        } catch (\Throwable $e) {
            // 若無權 ALTER，後續 UPDATE 會給出更明確錯誤
        }
    }

    /** 強制刷新 ThinkPHP 進程內／快取中的 members 欄位表（ALTER 後必須）。 */
    private function refreshMembersSchema(): void
    {
        try {
            $conn = Db::getConnection();
            $table = $this->membersTable();
            if (method_exists($conn, 'getSchemaInfo')) {
                $conn->getSchemaInfo($table, true);
            }
        } catch (\Throwable $e) {
            // ignore
        }
    }

    private function wrapAvatarError(\Throwable $e): ApiException
    {
        $raw = $e->getMessage();
        if (
            str_contains($raw, 'avatar_url')
            || str_contains($raw, 'Unknown column')
            || str_contains($raw, 'fields not exists')
        ) {
            return new ApiException(
                'SERVER',
                '資料庫缺少 avatar_url，請執行 m28_favorites_avatar.sql 或 releases/sql 中的遷移',
                500,
                50003,
            );
        }
        if (str_contains($raw, 'Permission denied') || str_contains($raw, 'Disk quota') || str_contains($raw, 'Read-only')) {
            return new ApiException('SERVER', '無法寫入上傳目錄，請檢查 public/storage 權限', 500, 50001);
        }

        return new ApiException('SERVER', '頭像保存失敗：' . mb_substr($raw, 0, 120), 500, 50001);
    }

    /** 優先 MIME，失敗則用 getimagesize／副檔名推斷（兼容 Blob 無 Content-Type）。 */
    private function resolveImageExt(UploadedFile $file): string
    {
        $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        $mime = strtolower((string) ($file->getMime() ?: ''));
        if (isset($allowed[$mime])) {
            return $allowed[$mime];
        }
        $path = method_exists($file, 'getPathname') ? (string) $file->getPathname() : '';
        if ($path !== '' && is_file($path)) {
            $info = @getimagesize($path);
            $type = (int) ($info[2] ?? 0);
            $byType = [
                IMAGETYPE_JPEG => 'jpg',
                IMAGETYPE_PNG => 'png',
                IMAGETYPE_WEBP => 'webp',
            ];
            if (isset($byType[$type])) {
                return $byType[$type];
            }
        }
        $name = strtolower((string) $file->getOriginalName());
        if (preg_match('/\.(jpe?g)$/', $name)) {
            return 'jpg';
        }
        if (preg_match('/\.png$/', $name)) {
            return 'png';
        }
        if (preg_match('/\.webp$/', $name)) {
            return 'webp';
        }
        throw new ApiException('INVALID', '僅支持 JPG／PNG／WebP', 422, 42290);
    }

    private function cropSquare(string $path, int $side): void
    {
        if (!function_exists('imagecreatefromjpeg') || !function_exists('imagecreatetruecolor')) {
            return;
        }
        $src = @imagecreatefromjpeg($path);
        if (!$src) {
            return;
        }
        $w = imagesx($src);
        $h = imagesy($src);
        $crop = min($w, $h);
        $sx = (int) (($w - $crop) / 2);
        $sy = (int) (($h - $crop) / 2);
        $dst = imagecreatetruecolor($side, $side);
        if ($dst === false) {
            imagedestroy($src);

            return;
        }
        imagecopyresampled($dst, $src, 0, 0, $sx, $sy, $side, $side, $crop, $crop);
        imagejpeg($dst, $path, 82);
        imagedestroy($src);
        imagedestroy($dst);
    }
}
