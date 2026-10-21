<?php

declare(strict_types=1);

namespace app\controller\api;

use app\controller\ApiController;
use app\service\ImageUploadService;
use app\service\MemberAuthService;
use app\support\ApiException;
use think\facade\Db;
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

        // 上傳上限 5MB；落庫經 ImageUploadService avatar preset 壓縮（512px / ≤512KB）
        $uploader = new ImageUploadService();
        $stored = $uploader->store($file, [
            'preset' => 'avatar',
            'bucket' => 'avatar',
            'owner_id' => $memberId,
        ]);
        $url = (string) $stored['url'];
        $destPath = $uploader->absolutePath($url);
        if (is_file($destPath)) {
            $this->cropSquare($destPath, 512);
        }
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
            'member' => (new MemberAuthService())->present($row),
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
        if (str_contains($raw, 'finfo_open') || str_contains($raw, 'fileinfo')) {
            return new ApiException(
                'SERVER',
                '圖片類型檢測失敗（主機未啟用 fileinfo），請部署最新 API 後重試',
                500,
                50002,
            );
        }
        if (str_contains($raw, 'Permission denied') || str_contains($raw, 'Disk quota') || str_contains($raw, 'Read-only')) {
            return new ApiException('SERVER', '無法寫入上傳目錄，請檢查 public/storage 權限', 500, 50001);
        }

        return new ApiException('SERVER', '頭像保存失敗：' . mb_substr($raw, 0, 120), 500, 50001);
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
