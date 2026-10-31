<?php

declare(strict_types=1);

namespace app\service;

use app\support\ApiException;

/**
 * 分銷中心入口白名單（shop_settings JSON：distribution.access）。
 *
 * mode=whitelist：僅 member_ids 可進 H5 分銷中心與相關 API。
 * mode=open：全員開放（兼容舊行為）。
 * 預設 whitelist + 空名單 → 入口關閉直至後台加入用戶。
 */
class DistributionAccessService
{
    public const SETTING_KEY = 'distribution.access';

    public const MAX_MEMBER_WHITELIST = 200;

    /** @var array{mode: string, member_ids: list<int>}|null */
    private ?array $cfgCache = null;

    /** @return array{mode: string, member_ids: list<int>, members: list<array<string, mixed>>, whitelist_only: bool} */
    public function get(): array
    {
        $cfg = $this->config();
        $whitelistOnly = $cfg['mode'] === 'whitelist';

        return [
            'mode' => $cfg['mode'],
            'whitelist_only' => $whitelistOnly,
            'member_ids' => $cfg['member_ids'],
            'members' => $this->resolveMembers($cfg['member_ids']),
        ];
    }

    /**
     * 當前會員是否可使用分銷中心。
     * 未登錄視為不可用（入口需登錄）。
     */
    public function isMemberAllowed(?int $memberId): bool
    {
        $cfg = $this->config();
        if ($cfg['mode'] !== 'whitelist') {
            return $memberId !== null && $memberId > 0;
        }
        if ($memberId === null || $memberId <= 0) {
            return false;
        }

        return in_array($memberId, $cfg['member_ids'], true);
    }

    /** 供 shop/status：H5 隱藏入口 / 路由守衛 */
    public function publicFlag(?int $memberId): array
    {
        $cfg = $this->config();
        $whitelistOnly = $cfg['mode'] === 'whitelist';

        return [
            'distribution_access' => [
                'whitelist_only' => $whitelistOnly,
                'allowed' => $this->isMemberAllowed($memberId),
            ],
        ];
    }

    /** API 閘：不在白名單拋 403 */
    public function assertMemberAllowed(int $memberId): void
    {
        if ($this->isMemberAllowed($memberId)) {
            return;
        }
        throw new ApiException('FORBIDDEN', '分銷中心僅對白名單會員開放', 403, 40351);
    }

    /** @param array<string, mixed> $body */
    public function save(array $body): array
    {
        $current = $this->config();
        $mode = $current['mode'];
        if (array_key_exists('mode', $body)) {
            $mode = $this->normalizeMode($body['mode']);
        } elseif (array_key_exists('whitelist_only', $body)) {
            // 兼容開關字段
            $mode = in_array($body['whitelist_only'], [true, 1, '1', 'true'], true)
                ? 'whitelist'
                : 'open';
        }

        $memberIds = array_key_exists('member_ids', $body)
            ? $this->normalizeMemberIds($body['member_ids'])
            : $current['member_ids'];

        $payload = [
            'mode' => $mode,
            'member_ids' => $memberIds,
        ];
        TradeSettings::set(self::SETTING_KEY, json_encode($payload, JSON_UNESCAPED_UNICODE));
        $this->cfgCache = null;

        return $this->get();
    }

    /** @return array{mode: string, member_ids: list<int>} */
    private function config(): array
    {
        if ($this->cfgCache !== null) {
            return $this->cfgCache;
        }
        $raw = TradeSettings::get(self::SETTING_KEY, '');
        $decoded = $raw !== '' ? json_decode($raw, true) : null;
        if (!is_array($decoded)) {
            $decoded = [];
        }
        $this->cfgCache = [
            'mode' => $this->normalizeMode($decoded['mode'] ?? 'whitelist'),
            'member_ids' => $this->normalizeMemberIds($decoded['member_ids'] ?? []),
        ];

        return $this->cfgCache;
    }

    private function normalizeMode(mixed $raw): string
    {
        $m = strtolower(trim((string) $raw));
        if ($m === 'open' || $m === 'all' || $m === 'off') {
            return 'open';
        }

        return 'whitelist';
    }

    /** @return list<int> */
    private function normalizeMemberIds(mixed $raw): array
    {
        if (!is_array($raw)) {
            return [];
        }
        $out = [];
        foreach ($raw as $v) {
            if (is_array($v)) {
                $id = (int) ($v['id'] ?? $v['member_id'] ?? 0);
            } else {
                $id = (int) $v;
            }
            if ($id <= 0) {
                continue;
            }
            $out[$id] = true;
            if (count($out) > self::MAX_MEMBER_WHITELIST) {
                throw new ApiException('INVALID', '分銷白名單最多 ' . self::MAX_MEMBER_WHITELIST . ' 人', 422, 42201);
            }
        }

        return array_map('intval', array_keys($out));
    }

    /**
     * @param list<int> $ids
     * @return list<array{id: int, email: ?string, phone: ?string, nickname: ?string, promo_code: string, status: int}>
     */
    private function resolveMembers(array $ids): array
    {
        if (!$ids) {
            return [];
        }
        try {
            $rows = \think\facade\Db::name('members')
                ->whereIn('id', $ids)
                ->field(['id', 'email', 'phone', 'nickname', 'promo_code', 'status'])
                ->select()
                ->toArray();
        } catch (\Throwable) {
            return [];
        }
        $byId = [];
        foreach ($rows as $r) {
            $byId[(int) $r['id']] = [
                'id' => (int) $r['id'],
                'email' => $r['email'] !== null && $r['email'] !== '' ? (string) $r['email'] : null,
                'phone' => $r['phone'] !== null && $r['phone'] !== '' ? (string) $r['phone'] : null,
                'nickname' => $r['nickname'] !== null && $r['nickname'] !== '' ? (string) $r['nickname'] : null,
                'promo_code' => (string) ($r['promo_code'] ?? ''),
                'status' => (int) ($r['status'] ?? 0),
            ];
        }
        $out = [];
        foreach ($ids as $id) {
            if (isset($byId[$id])) {
                $out[] = $byId[$id];
            } else {
                $out[] = [
                    'id' => $id,
                    'email' => null,
                    'phone' => null,
                    'nickname' => null,
                    'promo_code' => '',
                    'status' => 0,
                ];
            }
        }

        return $out;
    }
}
