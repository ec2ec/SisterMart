<?php

declare(strict_types=1);

namespace app\service;

use app\support\ApiException;
use think\facade\Db;

/**
 * M14 每日簽到（真實表 + 積分發放；支付無關）。
 */
class CheckinService
{
    private static bool $schemaReady = false;

    public function calendar(int $memberId): array
    {
        $this->ensureSchema();
        $year = (int) date('Y');
        $month = (int) date('n');
        $daysInMonth = (int) date('t');
        $today = (int) date('j');
        $from = sprintf('%04d-%02d-01', $year, $month);
        $to = sprintf('%04d-%02d-%02d', $year, $month, $daysInMonth);

        $rows = Db::name('member_checkins')
            ->where('member_id', $memberId)
            ->whereBetween('checkin_date', [$from, $to])
            ->column('checkin_date');
        $signedDays = [];
        foreach ($rows as $d) {
            $signedDays[] = (int) date('j', strtotime((string) $d));
        }
        $streak = $this->currentStreak($memberId);
        $signedToday = in_array($today, $signedDays, true);
        $rewardToday = $this->rewardForStreak($signedToday ? $streak : $streak + 1);

        $days = [];
        for ($d = 1; $d <= $daysInMonth; $d++) {
            $days[] = [
                'day' => $d,
                'signed' => in_array($d, $signedDays, true),
                'today' => $d === $today,
            ];
        }

        return [
            'mock' => false,
            'year' => $year,
            'month' => $month,
            'streak' => $streak,
            'reward_today' => $rewardToday,
            'signed_today' => $signedToday,
            'days' => $days,
        ];
    }

    public function checkin(int $memberId): array
    {
        $this->ensureSchema();
        $today = date('Y-m-d');
        $now = date('Y-m-d H:i:s');
        $result = [];
        Db::transaction(function () use ($memberId, $today, $now, &$result) {
            $exists = Db::name('member_checkins')
                ->where(['member_id' => $memberId, 'checkin_date' => $today])
                ->lock(true)
                ->find();
            if ($exists) {
                throw new ApiException('ALREADY_SIGNED', '今日已簽到', 422, 42280);
            }
            $streak = $this->currentStreak($memberId) + 1;
            $reward = $this->rewardForStreak($streak);
            Db::name('member_checkins')->insert([
                'member_id' => $memberId,
                'checkin_date' => $today,
                'reward' => $reward,
                'streak' => $streak,
                'created_at' => $now,
            ]);
            (new AssetService())->changePoints($memberId, $reward, 'checkin', 'checkin', null, '每日簽到');
            $result = [
                'mock' => false,
                'reward' => $reward,
                'streak' => $streak,
                'points' => (int) Db::name('members')->where('id', $memberId)->value('points'),
            ];
        });

        return $result;
    }

    private function currentStreak(int $memberId): int
    {
        $last = Db::name('member_checkins')
            ->where('member_id', $memberId)
            ->order('checkin_date', 'desc')
            ->find();
        if (!$last) {
            return 0;
        }
        $lastDate = (string) $last['checkin_date'];
        $today = date('Y-m-d');
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        if ($lastDate === $today || $lastDate === $yesterday) {
            return (int) $last['streak'];
        }

        return 0;
    }

    private function rewardForStreak(int $streak): int
    {
        $base = max(1, (int) TradeSettings::get('checkin.base_reward', '10'));
        $bonus = max(0, (int) TradeSettings::get('checkin.streak_bonus', '1'));
        $cap = max($base, (int) TradeSettings::get('checkin.streak_cap', '30'));
        $streak = max(1, $streak);

        return min($cap, $base + ($streak - 1) * $bonus);
    }

    /**
     * 生產庫若未跑 m14，查日曆會 500（表不存在 →「服務暫時不可用」）。
     * 此處冪等建表並補預設積分設定。
     */
    private function ensureSchema(): void
    {
        if (self::$schemaReady) {
            return;
        }
        $prefix = (string) (Db::getConnection()->getConfig('prefix') ?: '');
        $table = $prefix . 'member_checkins';
        try {
            // 勿用 SHOW TABLES LIKE ?（PDO 預編譯不支持）；直接 IF NOT EXISTS
            Db::execute(
                "CREATE TABLE IF NOT EXISTS `{$table}` (
                  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                  `member_id` BIGINT UNSIGNED NOT NULL,
                  `checkin_date` DATE NOT NULL,
                  `reward` INT NOT NULL DEFAULT 0,
                  `streak` INT NOT NULL DEFAULT 1,
                  `created_at` DATETIME NOT NULL,
                  UNIQUE KEY `uk_member_checkins_day` (`member_id`, `checkin_date`),
                  KEY `idx_member_checkins_member` (`member_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            );
        } catch (\Throwable $e) {
            throw new ApiException(
                'SERVER',
                '簽到表未就緒，請執行 m14_checkin.sql（releases/sql）',
                500,
                50014,
            );
        }
        $now = date('Y-m-d H:i:s');
        foreach (
            [
                'checkin.base_reward' => '10',
                'checkin.streak_bonus' => '1',
                'checkin.streak_cap' => '30',
            ] as $key => $value
        ) {
            try {
                $exists = Db::name('shop_settings')->where('setting_key', $key)->find();
                if (!$exists) {
                    Db::name('shop_settings')->insert([
                        'setting_key' => $key,
                        'setting_value' => $value,
                        'updated_at' => $now,
                    ]);
                }
            } catch (\Throwable $e) {
                // 設定表異常時仍用 TradeSettings 預設值
            }
        }
        self::$schemaReady = true;
    }
}
