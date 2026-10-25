<?php

declare(strict_types=1);

namespace app\service;

use app\support\ApiException;

/**
 * 店鋪維護模式（存 shop_settings JSON：shop.maintenance）。
 * 開啟後 H5 業務接口回 MAINTENANCE；後台不受影響。
 * 白名單路徑前綴可在後台配置（另有內建放行列表）。
 */
class MaintenanceService
{
    public const SETTING_KEY = 'shop.maintenance';

    /** 內建放行：狀態查詢、健康檢查、後台、靜態媒體代理、支付回調、定時任務、白名單用戶登錄 */
    public const BUILTIN_ALLOW = [
        '/api/v1/shop/status',
        '/api/v1/health',
        '/api/health',
        '/health',
        '/api/v1/admin',
        '/api/v1/media',
        '/api/v1/files',
        '/api/v1/payment-callbacks',
        '/api/v1/jobs',
        '/api/v1/member-sessions',
        '/api/v1/verification-codes',
        '/api/v1/member-passwords',
        '/api/v1/articles',
    ];

    public const MAX_WHITELIST = 80;

    public const MAX_MEMBER_WHITELIST = 200;

    /** @var array<string, mixed>|null 實例內緩存（不含 members 詳情） */
    private ?array $cfgCache = null;

    /** @return array<string, mixed> */
    public function get(): array
    {
        $data = $this->config();
        $data['members'] = $this->resolveMembers($data['member_ids']);

        return $data;
    }

    /** @return array<string, mixed> */
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
        $this->cfgCache = $this->normalize($decoded);

        return $this->cfgCache;
    }

    public function isEnabled(): bool
    {
        return (bool) $this->config()['enabled'];
    }

    /**
     * 前台狀態：僅在 enabled 時返回文案；否則 enabled=false。
     * 白名單會員帶 member_bypass=true，H5 不蓋維護頁。
     *
     * @return array{
     *   enabled: bool,
     *   member_bypass: bool,
     *   title: array{zh-HK: string, en: string},
     *   message: array{zh-HK: string, en: string},
     *   starts_at: ?string,
     *   ends_at: ?string
     * }
     */
    public function publicStatus(?int $memberId = null): array
    {
        $cfg = $this->config();
        if (!(bool) $cfg['enabled']) {
            return [
                'enabled' => false,
                'member_bypass' => false,
                'title' => ['zh-HK' => '', 'en' => ''],
                'message' => ['zh-HK' => '', 'en' => ''],
                'starts_at' => null,
                'ends_at' => null,
            ];
        }

        $bypass = $memberId !== null && $memberId > 0 && $this->isMemberAllowed($memberId);

        return [
            'enabled' => true,
            'member_bypass' => $bypass,
            'title' => $cfg['title'],
            'message' => $cfg['message'],
            'starts_at' => $cfg['starts_at'],
            'ends_at' => $cfg['ends_at'],
        ];
    }

    public function isPathAllowed(string $path): bool
    {
        $path = $this->normalizePath($path);
        if ($path === '' || $path === '/') {
            return false;
        }

        foreach (array_merge(self::BUILTIN_ALLOW, $this->config()['whitelist']) as $prefix) {
            $prefix = $this->normalizePath((string) $prefix);
            if ($prefix === '') {
                continue;
            }
            if ($path === $prefix || str_starts_with($path, $prefix . '/')) {
                return true;
            }
        }

        return false;
    }

    public function isMemberAllowed(int $memberId): bool
    {
        if ($memberId <= 0) {
            return false;
        }

        return in_array($memberId, $this->config()['member_ids'], true);
    }

    /** @param array<string, mixed> $body */
    public function save(array $body): array
    {
        $current = $this->get();
        $enabled = array_key_exists('enabled', $body)
            ? in_array($body['enabled'], [true, 1, '1', 'true'], true)
            : (bool) $current['enabled'];

        $starts = array_key_exists('starts_at', $body)
            ? $this->normalizeDateTime($body['starts_at'] ?? null)
            : $current['starts_at'];
        $ends = array_key_exists('ends_at', $body)
            ? $this->normalizeDateTime($body['ends_at'] ?? null)
            : $current['ends_at'];

        if ($enabled && $starts && $ends && strcmp($ends, $starts) < 0) {
            throw new ApiException('INVALID', '結束時間不可早於開始時間', 422, 42201);
        }

        $titleZh = trim((string) ($body['title']['zh-HK'] ?? $body['title_zh'] ?? $current['title']['zh-HK']));
        $titleEn = trim((string) ($body['title']['en'] ?? $body['title_en'] ?? $current['title']['en']));
        $msgZh = trim((string) ($body['message']['zh-HK'] ?? $body['message_zh'] ?? $current['message']['zh-HK']));
        $msgEn = trim((string) ($body['message']['en'] ?? $body['message_en'] ?? $current['message']['en']));

        if ($titleZh === '' && $titleEn === '') {
            throw new ApiException('INVALID', '請填寫維護標題', 422, 42201);
        }

        $whitelist = array_key_exists('whitelist', $body)
            ? $this->normalizeWhitelist($body['whitelist'])
            : $current['whitelist'];

        $memberIds = array_key_exists('member_ids', $body)
            ? $this->normalizeMemberIds($body['member_ids'])
            : $current['member_ids'];

        $payload = [
            'enabled' => $enabled,
            'starts_at' => $starts,
            'ends_at' => $ends,
            'title' => [
                'zh-HK' => $titleZh !== '' ? $titleZh : $titleEn,
                'en' => $titleEn !== '' ? $titleEn : $titleZh,
            ],
            'message' => [
                'zh-HK' => $msgZh,
                'en' => $msgEn,
            ],
            'whitelist' => $whitelist,
            'member_ids' => $memberIds,
        ];
        TradeSettings::set(self::SETTING_KEY, json_encode($payload, JSON_UNESCAPED_UNICODE));
        $this->cfgCache = null;

        return $this->get();
    }

    /**
     * @param array<string, mixed> $raw
     * @return array{
     *   enabled: bool,
     *   starts_at: ?string,
     *   ends_at: ?string,
     *   title: array{zh-HK: string, en: string},
     *   message: array{zh-HK: string, en: string},
     *   whitelist: list<string>,
     *   member_ids: list<int>,
     *   builtin_allow: list<string>
     * }
     */
    private function normalize(array $raw): array
    {
        $defaults = $this->defaults();
        $title = is_array($raw['title'] ?? null) ? $raw['title'] : [];
        $message = is_array($raw['message'] ?? null) ? $raw['message'] : [];

        return [
            'enabled' => in_array($raw['enabled'] ?? false, [true, 1, '1', 'true'], true),
            'starts_at' => $this->normalizeDateTime($raw['starts_at'] ?? $defaults['starts_at']),
            'ends_at' => $this->normalizeDateTime($raw['ends_at'] ?? $defaults['ends_at']),
            'title' => [
                'zh-HK' => trim((string) ($title['zh-HK'] ?? $defaults['title']['zh-HK'])),
                'en' => trim((string) ($title['en'] ?? $defaults['title']['en'])),
            ],
            'message' => [
                'zh-HK' => trim((string) ($message['zh-HK'] ?? $defaults['message']['zh-HK'])),
                'en' => trim((string) ($message['en'] ?? $defaults['message']['en'])),
            ],
            'whitelist' => $this->normalizeWhitelist($raw['whitelist'] ?? []),
            'member_ids' => $this->normalizeMemberIds($raw['member_ids'] ?? []),
            'builtin_allow' => self::BUILTIN_ALLOW,
        ];
    }

    /** @return array{enabled: bool, starts_at: string, ends_at: string, title: array{zh-HK: string, en: string}, message: array{zh-HK: string, en: string}, whitelist: list<string>, member_ids: list<int>} */
    private function defaults(): array
    {
        return [
            'enabled' => false,
            'starts_at' => '2026-12-12 12:35:00',
            'ends_at' => '2026-12-13 03:30:00',
            'title' => [
                'zh-HK' => '商店維護中',
                'en' => 'Under Maintenance',
            ],
            'message' => [
                'zh-HK' => '暫時不能為你提供服務。感謝你的支持，我們會盡快回來。',
                'en' => 'We are temporarily unavailable. Thank you for your support — we will be back soon.',
            ],
            'whitelist' => [],
            'member_ids' => [],
        ];
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
                throw new ApiException('INVALID', '用戶白名單最多 ' . self::MAX_MEMBER_WHITELIST . ' 人', 422, 42201);
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
        // 保持與 member_ids 相同順序
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

    /** @return list<string> */
    private function normalizeWhitelist(mixed $raw): array
    {
        $lines = [];
        if (is_string($raw)) {
            $lines = preg_split('/\R+/', $raw) ?: [];
        } elseif (is_array($raw)) {
            $lines = $raw;
        } else {
            return [];
        }

        $out = [];
        foreach ($lines as $line) {
            if (!is_string($line) && !is_numeric($line)) {
                continue;
            }
            $path = $this->normalizePath((string) $line);
            if ($path === '' || $path === '/') {
                continue;
            }
            if (!str_starts_with($path, '/api/')) {
                throw new ApiException('INVALID', '白名單路徑須以 /api/ 開頭：' . $path, 422, 42201);
            }
            if (strlen($path) > 160) {
                throw new ApiException('INVALID', '白名單路徑過長', 422, 42201);
            }
            // 與內建重疊也允許保存，便於運維對照；匹配時合併去重
            $out[$path] = true;
            if (count($out) > self::MAX_WHITELIST) {
                throw new ApiException('INVALID', '白名單最多 ' . self::MAX_WHITELIST . ' 條', 422, 42201);
            }
        }

        return array_keys($out);
    }

    private function normalizePath(string $path): string
    {
        $path = trim($path);
        if ($path === '') {
            return '';
        }
        // 去掉 query / fragment
        $path = explode('?', $path, 2)[0];
        $path = explode('#', $path, 2)[0];
        $path = '/' . trim(str_replace('\\', '/', $path), '/');
        // 摺疊重複斜杠
        $path = (string) preg_replace('#/+#', '/', $path);

        return $path === '/' ? '' : $path;
    }

    private function normalizeDateTime(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        $s = trim((string) $value);
        $s = str_replace('T', ' ', $s);
        if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $s)) {
            $s .= ':00';
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $s)) {
            throw new ApiException('INVALID', '時間格式須為 YYYY-MM-DD HH:mm', 422, 42201);
        }

        return $s;
    }
}
