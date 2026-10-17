<?php

declare(strict_types=1);

namespace app\service;

use app\support\ApiException;
use think\facade\Db;
use think\facade\Log;

class MemberAuthService
{
    public function sendCode(array $body): array
    {
        $channel = (string) ($body['channel'] ?? '');
        $purpose = (string) ($body['purpose'] ?? '');
        $target = trim((string) ($body['target'] ?? ''));
        if (!in_array($purpose, ['register', 'login', 'reset'], true)) {
            throw new ApiException('INVALID', '用途不正確');
        }

        if ($channel === 'whatsapp') {
            return $this->sendWhatsapp($purpose, $target);
        }
        if ($channel !== 'email') {
            throw new ApiException('INVALID', '發送渠道不正確');
        }

        $email = $this->email($target);
        if ($purpose === 'login') {
            throw new ApiException('USE_PASSWORD', '郵箱請使用密碼登錄');
        }
        $member = Db::name('members')->where('email', $email)->find();
        if ($purpose === 'register' && $member) {
            throw new ApiException('EMAIL_TAKEN', '這個郵箱已經註冊');
        }
        if ($purpose === 'reset' && !$member) {
            throw new ApiException('ACCOUNT_NOT_FOUND', '找不到這個帳號');
        }

        return $this->issueEmailCode($email, $purpose, $member ? (int) $member['id'] : null, false);
    }

    public function register(array $body): array
    {
        $this->assertAgreed($body);
        $password = (string) ($body['password'] ?? '');
        $this->assertPassword($password);
        $channel = (string) ($body['channel'] ?? 'email');
        $email = $this->email((string) ($body['email'] ?? ''));
        $phone = null;
        if ($channel === 'phone') {
            $phone = $this->phone((string) ($body['phone'] ?? ''));
            if (Db::name('members')->where('phone', $phone)->find()) {
                throw new ApiException('PHONE_TAKEN', '這個手機號碼已經註冊');
            }
        } elseif ($channel !== 'email') {
            throw new ApiException('INVALID', '註冊方式不正確');
        }
        if (Db::name('members')->where('email', $email)->find()) {
            throw new ApiException('EMAIL_TAKEN', '這個郵箱已經註冊');
        }
        $this->consumeCode($email, 'register', (string) ($body['code'] ?? ''));

        $parentId = $this->parentId(trim((string) ($body['ref'] ?? '')));
        $locale = $this->locale((string) ($body['locale'] ?? 'zh-HK'));
        $now = date('Y-m-d H:i:s');

        $id = (int) Db::transaction(function () use ($email, $phone, $password, $locale, $parentId, $now) {
            $memberId = (int) Db::name('members')->insertGetId([
                'email'           => $email,
                'phone'           => $phone,
                'password_hash'   => password_hash($password, PASSWORD_DEFAULT),
                'locale'          => $locale,
                'promo_code'      => 'TMP' . bin2hex(random_bytes(4)),
                'parent_id'       => $parentId,
                'status'          => 1,
                'balance'         => 50,
                'points'          => 5000,
                'agreed_terms_at' => $now,
                'created_at'      => $now,
                'updated_at'      => $now,
            ]);
            Db::name('members')->where('id', $memberId)->update([
                'promo_code' => 'SM' . str_pad((string) $memberId, 4, '0', STR_PAD_LEFT),
            ]);
            $welcome = Db::name('coupons')->where('code', 'WELCOME20')->find();
            if ($welcome) {
                Db::name('coupon_grants')->insert([
                    'coupon_id' => (int) $welcome['id'],
                    'member_id' => $memberId,
                    'status' => 'unused',
                    'order_id' => null,
                    'expires_at' => $welcome['ends_at'] ?? null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                Db::name('coupons')->where('id', $welcome['id'])->inc('claimed', 1)->update([]);
            }

            return $memberId;
        });

        $member = Db::name('members')->where('id', $id)->find();

        return [
            'token'  => TokenService::issue('member_tokens', 'member_id', $id),
            'member' => $this->present($member),
            'bound'  => $parentId !== null,
        ];
    }

    public function login(array $body): array
    {
        $this->assertAgreed($body);
        $channel = (string) ($body['channel'] ?? 'email');
        if ($channel === 'email') {
            $email = $this->email((string) ($body['email'] ?? ''));
            $member = Db::name('members')->where('email', $email)->find();
            if (!$member || !password_verify((string) ($body['password'] ?? ''), (string) $member['password_hash'])) {
                throw new ApiException('BAD_PASSWORD', '郵箱或密碼不正確', 401);
            }
        } elseif ($channel === 'phone') {
            $phone = $this->phone((string) ($body['phone'] ?? ''));
            $member = Db::name('members')->where('phone', $phone)->find();
            if (!$member || !$member['email']) {
                throw new ApiException('ACCOUNT_NOT_FOUND', '找不到這個帳號', 401);
            }
            $this->consumeCode((string) $member['email'], 'login', (string) ($body['code'] ?? ''));
        } else {
            throw new ApiException('INVALID', '登錄方式不正確');
        }
        if ((int) $member['status'] !== 1) {
            throw new ApiException('DISABLED', '這個帳號已停用', 403);
        }

        return [
            'token'  => TokenService::issue('member_tokens', 'member_id', (int) $member['id']),
            'member' => $this->present($member),
        ];
    }

    public function reset(array $body): array
    {
        $password = (string) ($body['password'] ?? '');
        $this->assertPassword($password);
        $identity = trim((string) ($body['identity'] ?? ''));
        $member = $this->memberByIdentity($identity);
        if (!$member || !$member['email']) {
            throw new ApiException('ACCOUNT_NOT_FOUND', '找不到這個帳號');
        }
        $this->consumeCode((string) $member['email'], 'reset', (string) ($body['code'] ?? ''));
        Db::name('members')->where('id', $member['id'])->update([
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'updated_at'    => date('Y-m-d H:i:s'),
        ]);

        return ['reset' => true];
    }

    public function updateLocale(int $memberId, string $locale): array
    {
        $locale = $this->locale($locale);
        Db::name('members')->where('id', $memberId)->update([
            'locale'     => $locale,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $member = Db::name('members')->where('id', $memberId)->find();

        return ['member' => $this->present($member)];
    }

    public function present(?array $member): array
    {
        if (!$member) {
            throw new ApiException('UNAUTHORIZED', '請先登錄', 401);
        }

        $assets = new AssetService();
        $levelId = (int) ($member['level_id'] ?? 1);
        $level = $assets->presentLevel($assets->findLevel($levelId));
        $couponCount = (int) Db::name('coupon_grants')
            ->where(['member_id' => (int) $member['id'], 'status' => 'unused'])
            ->count();

        return [
            'id'         => (int) $member['id'],
            'email'      => $member['email'] ?: null,
            'phone'      => $member['phone'] ?: null,
            'locale'     => (string) $member['locale'],
            'promo_code' => (string) $member['promo_code'],
            'balance' => (float) ($member['balance'] ?? 0),
            'points' => (int) ($member['points'] ?? 0),
            'total_spent' => (float) ($member['total_spent'] ?? 0),
            'level_id' => $levelId,
            'level' => $level,
            'coupon_count' => $couponCount,
        ];
    }

    private function sendWhatsapp(string $purpose, string $target): array
    {
        $this->phone($target);
        if ($purpose === 'register') {
            throw new ApiException('WHATSAPP_UNAVAILABLE', 'WhatsApp 暫時無法發送，請改用郵箱收取驗證碼');
        }
        if (!in_array($purpose, ['login', 'reset'], true)) {
            throw new ApiException('INVALID', '用途不正確');
        }
        $member = Db::name('members')->where('phone', $this->phone($target))->find();
        if (!$member) {
            throw new ApiException('ACCOUNT_NOT_FOUND', '找不到這個帳號');
        }
        if (!$member['email']) {
            throw new ApiException('NO_BOUND_EMAIL', '這個手機沒有綁定郵箱，無法改發驗證碼');
        }

        return $this->issueEmailCode((string) $member['email'], $purpose, (int) $member['id'], true);
    }

    private function issueEmailCode(string $email, string $purpose, ?int $memberId, bool $fallback): array
    {
        $recent = Db::name('verify_codes')
            ->where('target', $email)
            ->where('purpose', $purpose)
            ->where('created_at', '>', date('Y-m-d H:i:s', time() - 60))
            ->find();
        if ($recent) {
            throw new ApiException('TOO_SOON', '請稍後再發送驗證碼', 429);
        }

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $now = date('Y-m-d H:i:s');
        Db::name('verify_codes')
            ->where('target', $email)
            ->where('purpose', $purpose)
            ->whereNull('used_at')
            ->update(['used_at' => $now]);
        Db::name('verify_codes')->insert([
            'channel'    => 'email',
            'target'     => $email,
            'purpose'    => $purpose,
            'code_hash'  => hash('sha256', $code),
            'member_id'  => $memberId,
            'attempts'   => 0,
            'expires_at' => date('Y-m-d H:i:s', time() + 600),
            'used_at'    => null,
            'created_at' => $now,
        ]);
        Log::info('sistermart verify code ' . $purpose . ' ' . $email . ' ' . $code);

        $payload = [
            'via'          => 'email',
            'fallback'     => $fallback,
            'masked_email' => $this->maskEmail($email),
        ];
        if (env('APP_DEBUG')) {
            $payload['debug_code'] = $code;
        }

        return $payload;
    }

    private function consumeCode(string $email, string $purpose, string $code): void
    {
        $code = trim($code);
        $row = Db::name('verify_codes')
            ->where('target', $email)
            ->where('purpose', $purpose)
            ->whereNull('used_at')
            ->where('expires_at', '>', date('Y-m-d H:i:s'))
            ->order('id', 'desc')
            ->find();
        $matches = $row && hash_equals((string) $row['code_hash'], hash('sha256', $code));
        if (!$matches) {
            if ($row) {
                $attempts = (int) $row['attempts'] + 1;
                $update = ['attempts' => $attempts];
                if ($attempts >= 5) {
                    $update['used_at'] = date('Y-m-d H:i:s');
                }
                Db::name('verify_codes')->where('id', $row['id'])->update($update);
            }
            throw new ApiException('BAD_CODE', '驗證碼不正確或已過期');
        }
        Db::name('verify_codes')->where('id', $row['id'])->update(['used_at' => date('Y-m-d H:i:s')]);
    }

    private function memberByIdentity(string $identity): ?array
    {
        if (str_contains($identity, '@')) {
            return Db::name('members')->where('email', $this->email($identity))->find() ?: null;
        }

        return Db::name('members')->where('phone', $this->phone($identity))->find() ?: null;
    }

    private function parentId(string $ref): ?int
    {
        if ($ref === '') {
            return null;
        }
        $parent = Db::name('members')->where('promo_code', $ref)->find();
        if (!$parent || (int) $parent['status'] !== 1) {
            return null;
        }

        return (int) $parent['id'];
    }

    private function assertAgreed(array $body): void
    {
        $agreed = $body['agreed'] ?? false;
        if (!in_array($agreed, [true, 1, '1', 'true'], true)) {
            throw new ApiException('NEED_AGREE', '請先閱讀並同意用戶協議和私隱協議');
        }
    }

    private function assertPassword(string $password): void
    {
        if (!preg_match('/^(?=.*[A-Za-z])(?=.*\d).{8,}$/', $password)) {
            throw new ApiException('WEAK_PASSWORD', '密碼至少 8 位，且同時包含字母和數字');
        }
    }

    private function email(string $raw): string
    {
        $email = strtolower(trim($raw));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new ApiException('EMAIL_INVALID', '請輸入有效的電子郵件');
        }

        return $email;
    }

    private function phone(string $raw): string
    {
        $value = preg_replace('/[\s-]/', '', trim($raw)) ?? '';
        if (preg_match('/^[2-9]\d{7}$/', $value)) {
            $value = '+852' . $value;
        } elseif (preg_match('/^852[2-9]\d{7}$/', $value)) {
            $value = '+' . $value;
        }
        if (!preg_match('/^\+852[2-9]\d{7}$/', $value)) {
            throw new ApiException('PHONE_INVALID', '請輸入有效的香港手機號碼');
        }

        return $value;
    }

    private function locale(string $locale): string
    {
        return $locale === 'en' ? 'en' : 'zh-HK';
    }

    private function maskEmail(string $email): string
    {
        [$name, $domain] = explode('@', $email, 2);
        $visible = substr($name, 0, 1);

        return $visible . '***@' . $domain;
    }
}
