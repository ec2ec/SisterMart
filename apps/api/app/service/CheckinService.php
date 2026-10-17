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
    public function calendar(int $memberId): array
    {
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
}
