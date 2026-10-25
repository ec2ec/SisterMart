<?php

declare(strict_types=1);

namespace app\service;

use think\facade\Db;

/**
 * 會員最後活躍：需登入接口與公開可選 Bearer 共用，2 分鐘節流。
 */
class MemberActivityService
{
    public const THROTTLE_SECONDS = 120;

    public static function touch(int $memberId, ?string $knownLastActive = null): void
    {
        if ($memberId <= 0) {
            return;
        }

        $nowTs = time();
        $now = date('Y-m-d H:i:s', $nowTs);

        if ($knownLastActive !== null && $knownLastActive !== '') {
            $prev = strtotime($knownLastActive);
            if ($prev !== false && ($nowTs - $prev) < self::THROTTLE_SECONDS) {
                return;
            }
            try {
                Db::name('members')->where('id', $memberId)->update(['last_active_at' => $now]);
            } catch (\Throwable) {
                // 遷移未跑或寫入失敗不影響業務
            }

            return;
        }

        $cutoff = date('Y-m-d H:i:s', $nowTs - self::THROTTLE_SECONDS);
        try {
            Db::name('members')
                ->where('id', $memberId)
                ->where('status', 1)
                ->where(function ($q) use ($cutoff) {
                    $q->whereNull('last_active_at')->whereOr('last_active_at', '<', $cutoff);
                })
                ->update(['last_active_at' => $now]);
        } catch (\Throwable) {
            // ignore
        }
    }

    public static function touchNow(int $memberId): void
    {
        if ($memberId <= 0) {
            return;
        }
        try {
            Db::name('members')->where('id', $memberId)->update([
                'last_active_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable) {
            // ignore
        }
    }
}
