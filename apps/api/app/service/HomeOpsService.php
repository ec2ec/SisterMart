<?php

declare(strict_types=1);

namespace app\service;

use app\support\ApiException;

/**
 * M24：首頁字幕 + 頁腳標語（存 shop_settings JSON）。
 */
class HomeOpsService
{
    private const LINK_TYPES = ['none', 'product', 'category', 'article', 'url'];

    public const KEY_MARQUEE = 'home.marquee';
    public const KEY_SLOGAN = 'home.footer_slogan';

    public function publicBundle(): array
    {
        return [
            'marquee' => $this->presentMarquee($this->rawMarquee(), publicOnly: true),
            'footer_slogan' => $this->presentSlogan($this->rawSlogan()),
        ];
    }

    public function adminRead(): array
    {
        return [
            'marquee' => $this->presentMarquee($this->rawMarquee(), publicOnly: false),
            'footer_slogan' => $this->presentSlogan($this->rawSlogan()),
        ];
    }

    /**
     * @param array<string, mixed> $body
     */
    public function adminUpdate(array $body): array
    {
        if (array_key_exists('marquee', $body) && is_array($body['marquee'])) {
            $this->saveMarquee($body['marquee']);
        }
        if (array_key_exists('footer_slogan', $body) && is_array($body['footer_slogan'])) {
            $this->saveSlogan($body['footer_slogan']);
        }

        return $this->adminRead();
    }

    /** @return array<string, mixed> */
    private function rawMarquee(): array
    {
        $raw = TradeSettings::get(self::KEY_MARQUEE, '');
        if ($raw === '') {
            return $this->defaultMarquee();
        }
        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : $this->defaultMarquee();
    }

    /** @return array<string, mixed> */
    private function rawSlogan(): array
    {
        $raw = TradeSettings::get(self::KEY_SLOGAN, '');
        if ($raw === '') {
            return $this->defaultSlogan();
        }
        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : $this->defaultSlogan();
    }

    /** @return array<string, mixed> */
    private function defaultMarquee(): array
    {
        return [
            'text_zh' => '首次下單最高享 4 折優惠 · 立即選購',
            'text_en' => 'Get up to 40% off on your 1st order · Shop now',
            'link_type' => 'none',
            'link_value' => '',
            'status' => 1,
        ];
    }

    /** @return array<string, mixed> */
    private function defaultSlogan(): array
    {
        return [
            'zh-HK' => '* 我們守護您的美麗 *',
            'en' => '* We guard your beauty *',
        ];
    }

    /**
     * @param array<string, mixed> $raw
     * @return array<string, mixed>|null
     */
    private function presentMarquee(array $raw, bool $publicOnly): ?array
    {
        $status = (int) ($raw['status'] ?? 1) === 0 ? 0 : 1;
        $payload = [
            'text' => [
                'zh-HK' => (string) ($raw['text_zh'] ?? $raw['text']['zh-HK'] ?? ''),
                'en' => (string) ($raw['text_en'] ?? $raw['text']['en'] ?? ''),
            ],
            'link_type' => (string) ($raw['link_type'] ?? 'none'),
            'link_value' => (string) ($raw['link_value'] ?? ''),
            'status' => $status,
            'enabled' => $status === 1,
        ];
        if ($publicOnly && $status !== 1) {
            return null;
        }

        return $payload;
    }

    /**
     * @param array<string, mixed> $raw
     * @return array{zh-HK: string, en: string}
     */
    private function presentSlogan(array $raw): array
    {
        return [
            'zh-HK' => (string) ($raw['zh-HK'] ?? $raw['zh'] ?? ''),
            'en' => (string) ($raw['en'] ?? ''),
        ];
    }

    /** @param array<string, mixed> $body */
    private function saveMarquee(array $body): void
    {
        $linkType = (string) ($body['link_type'] ?? 'none');
        if (!in_array($linkType, self::LINK_TYPES, true)) {
            throw new ApiException('INVALID', '字幕跳轉類型無效', 422, 42201);
        }
        $linkValue = trim((string) ($body['link_value'] ?? ''));
        if ($linkType !== 'none' && $linkValue === '') {
            throw new ApiException('INVALID', '請填寫字幕跳轉目標', 422, 42201);
        }
        $textZh = trim((string) ($body['text_zh'] ?? $body['text']['zh-HK'] ?? ''));
        $textEn = trim((string) ($body['text_en'] ?? $body['text']['en'] ?? ''));
        if ($textZh === '' && $textEn === '') {
            throw new ApiException('INVALID', '請填寫字幕文案', 422, 42201);
        }
        $payload = [
            'text_zh' => $textZh,
            'text_en' => $textEn,
            'link_type' => $linkType,
            'link_value' => $linkType === 'none' ? '' : $linkValue,
            'status' => (int) ($body['status'] ?? 1) === 0 ? 0 : 1,
        ];
        TradeSettings::set(self::KEY_MARQUEE, json_encode($payload, JSON_UNESCAPED_UNICODE));
    }

    /** @param array<string, mixed> $body */
    private function saveSlogan(array $body): void
    {
        $payload = [
            'zh-HK' => trim((string) ($body['zh-HK'] ?? $body['zh'] ?? '')),
            'en' => trim((string) ($body['en'] ?? '')),
        ];
        if ($payload['zh-HK'] === '' && $payload['en'] === '') {
            throw new ApiException('INVALID', '請填寫頁腳標語', 422, 42201);
        }
        TradeSettings::set(self::KEY_SLOGAN, json_encode($payload, JSON_UNESCAPED_UNICODE));
    }
}
