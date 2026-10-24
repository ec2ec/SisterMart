<?php

declare(strict_types=1);

namespace app\service;

use app\support\ApiException;

/**
 * 推廣編號：SM + 數字體 + HMAC 校驗字母（A–Z）。
 * letter = A + (first 4 bytes of HMAC-SHA256(secret, digits) as uint32) % 26
 */
final class PromoCodeService
{
    public static function secret(): string
    {
        $s = trim((string) env('PROMO_CODE_HMAC_SECRET', ''));
        if ($s === '') {
            $s = trim((string) env('RELEASE_HMAC_SECRET', ''));
        }
        if (strlen($s) < 16) {
            throw new ApiException('CONFIG', '推廣編號校驗密鑰未配置', 500, 50001);
        }

        return $s;
    }

    public static function digitsForMemberId(int $memberId): string
    {
        if ($memberId < 1) {
            throw new ApiException('INVALID', '會員 ID 無效');
        }

        return str_pad((string) $memberId, 4, '0', STR_PAD_LEFT);
    }

    public static function checkLetter(string $digits): string
    {
        if ($digits === '' || !ctype_digit($digits)) {
            throw new ApiException('INVALID', '推廣編號數字體無效');
        }
        $raw = hash_hmac('sha256', $digits, self::secret(), true);
        $n = unpack('N', substr($raw, 0, 4))[1] % 26;

        return chr(ord('A') + $n);
    }

    public static function fromMemberId(int $memberId): string
    {
        $digits = self::digitsForMemberId($memberId);

        return 'SM' . $digits . self::checkLetter($digits);
    }

    public static function normalize(string $raw): string
    {
        return strtoupper(preg_replace('/\s+/', '', trim($raw)) ?? '');
    }

    /**
     * @return array{ok: bool, code?: string, digits?: string, letter?: string, reason?: string}
     */
    public static function parse(string $raw): array
    {
        $code = self::normalize($raw);
        if ($code === '') {
            return ['ok' => false, 'reason' => 'empty'];
        }
        if (!preg_match('/^SM(\d+)([A-Z])$/', $code, $m)) {
            return ['ok' => false, 'reason' => 'format'];
        }
        $digits = $m[1];
        $letter = $m[2];
        if (self::checkLetter($digits) !== $letter) {
            return ['ok' => false, 'reason' => 'checksum'];
        }

        return ['ok' => true, 'code' => $code, 'digits' => $digits, 'letter' => $letter];
    }

    public static function assertValidChecksum(string $raw): string
    {
        $parsed = self::parse($raw);
        if (!$parsed['ok']) {
            throw new ApiException('BAD_REF', '推薦人編號不正確');
        }

        return (string) $parsed['code'];
    }
}
