<?php

declare(strict_types=1);

namespace app\service;

use app\support\ApiException;
use think\facade\Db;

class TabbarService
{
    private const SLOTS = ['home', 'category', 'cart', 'account'];

    public function read(): array
    {
        $setting = Db::name('shop_settings')->where('setting_key', 'tabbar.bar_bg')->find();
        $rows = Db::name('tabbar_items')->order('sort_order', 'asc')->select()->toArray();

        return [
            'bar_bg' => $setting['setting_value'] ?? '#ffffff',
            'items'  => array_map([$this, 'present'], $rows),
        ];
    }

    public function save(array $body): array
    {
        $barBg = $this->color((string) ($body['bar_bg'] ?? ''), '底欄背景色不正確');
        $items = $body['items'] ?? null;
        if (!is_array($items) || count($items) !== 4) {
            throw new ApiException('INVALID', '底部導航必須是四個固定按鈕');
        }
        $seen = [];
        $rows = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                throw new ApiException('INVALID', '底部導航格式不正確');
            }
            $slot = (string) ($item['slot'] ?? '');
            if (!in_array($slot, self::SLOTS, true) || isset($seen[$slot])) {
                throw new ApiException('INVALID', '底部導航槽位不正確');
            }
            $seen[$slot] = true;
            $rows[] = [
                'slot'              => $slot,
                'label_zh'          => $this->label((string) ($item['label_zh'] ?? '')),
                'label_en'          => $this->label((string) ($item['label_en'] ?? '')),
                'icon'              => $this->icon((string) ($item['icon'] ?? '')),
                'icon_active'       => $this->icon((string) ($item['icon_active'] ?? '')),
                'icon_bg'           => $this->color((string) ($item['icon_bg'] ?? ''), '圖標背景色不正確'),
                'icon_bg_active'    => $this->color((string) ($item['icon_bg_active'] ?? ''), '選中圖標背景色不正確'),
                'text_color'        => $this->color((string) ($item['text_color'] ?? ''), '文字顏色不正確'),
                'text_color_active' => $this->color((string) ($item['text_color_active'] ?? ''), '選中文字顏色不正確'),
                'sort_order'        => self::sortOf($slot),
            ];
        }
        if (count($seen) !== 4) {
            throw new ApiException('INVALID', '底部導航必須包含首頁、分類、購物車、我的');
        }

        Db::transaction(function () use ($rows, $barBg) {
            foreach ($rows as $row) {
                $exists = Db::name('tabbar_items')->where('slot', $row['slot'])->find();
                if ($exists) {
                    Db::name('tabbar_items')->where('slot', $row['slot'])->update($row);
                } else {
                    Db::name('tabbar_items')->insert($row);
                }
            }
            $now = date('Y-m-d H:i:s');
            $setting = Db::name('shop_settings')->where('setting_key', 'tabbar.bar_bg')->find();
            if ($setting) {
                Db::name('shop_settings')->where('setting_key', 'tabbar.bar_bg')->update([
                    'setting_value' => $barBg,
                    'updated_at'    => $now,
                ]);
            } else {
                Db::name('shop_settings')->insert([
                    'setting_key'   => 'tabbar.bar_bg',
                    'setting_value' => $barBg,
                    'updated_at'    => $now,
                ]);
            }
        });

        return $this->read();
    }

    private function present(array $row): array
    {
        return [
            'slot'              => (string) $row['slot'],
            'label_zh'          => (string) $row['label_zh'],
            'label_en'          => (string) $row['label_en'],
            'icon'              => (string) $row['icon'],
            'icon_active'       => (string) $row['icon_active'],
            'icon_bg'           => (string) $row['icon_bg'],
            'icon_bg_active'    => (string) $row['icon_bg_active'],
            'text_color'        => (string) $row['text_color'],
            'text_color_active' => (string) $row['text_color_active'],
            'sort'              => (int) $row['sort_order'],
        ];
    }

    private static function sortOf(string $slot): int
    {
        return match ($slot) {
            'home' => 1,
            'category' => 2,
            'cart' => 3,
            default => 4,
        };
    }

    private function label(string $value): string
    {
        $value = trim($value);
        if ($value === '' || mb_strlen($value) > 16) {
            throw new ApiException('INVALID', '按鈕文字需要 1 到 16 個字');
        }

        return $value;
    }

    private function color(string $value, string $message): string
    {
        if (!preg_match('/^#([0-9a-fA-F]{6})$/', $value)) {
            throw new ApiException('INVALID', $message);
        }

        return strtolower($value);
    }

    private function icon(string $value): string
    {
        $value = trim($value);
        $vant = (bool) preg_match('/^[a-z0-9-]{1,64}$/', $value);
        $url = (bool) preg_match('#^https?://#i', $value);
        $path = str_starts_with($value, '/') && !str_starts_with($value, '//');
        if (!$vant && !$url && !$path) {
            throw new ApiException('INVALID', '圖標請填 Vant 圖標名或圖片地址');
        }
        if (strlen($value) > 255) {
            throw new ApiException('INVALID', '圖標地址過長');
        }

        return $value;
    }
}
