<?php

declare(strict_types=1);

/**
 * Seed M3 catalog from mock jewelry set. Safe to re-run (upsert by code).
 * php apps/api/database/m3_seed.php
 */

require dirname(__DIR__) . '/vendor/autoload.php';

$app = new think\App(dirname(__DIR__));
$app->initialize();

use think\facade\Db;

function now(): string
{
    return date('Y-m-d H:i:s');
}

function mock(string $file): string
{
    return '/mock/' . $file;
}

function upsertCategory(string $code, ?string $parentCode, string $zh, string $en, ?string $icon, int $sort): int
{
    $parentId = null;
    if ($parentCode) {
        $parentId = (int) Db::name('categories')->where('code', $parentCode)->value('id');
    }
    $row = Db::name('categories')->where('code', $code)->find();
    $data = [
        'parent_id'  => $parentId,
        'code'       => $code,
        'icon'       => $icon,
        'sort_order' => $sort,
        'status'     => 1,
        'updated_at' => now(),
    ];
    if ($row) {
        Db::name('categories')->where('id', $row['id'])->update($data);
        $id = (int) $row['id'];
    } else {
        $data['created_at'] = now();
        $id = (int) Db::name('categories')->insertGetId($data);
    }
    foreach (['zh-HK' => $zh, 'en' => $en] as $locale => $name) {
        $exists = Db::name('category_i18n')->where(['category_id' => $id, 'locale' => $locale])->find();
        if ($exists) {
            Db::name('category_i18n')->where(['category_id' => $id, 'locale' => $locale])->update(['name' => $name]);
        } else {
            Db::name('category_i18n')->insert(['category_id' => $id, 'locale' => $locale, 'name' => $name]);
        }
    }

    return $id;
}

function upsertTag(string $code, string $zh, string $en, int $sort): int
{
    $row = Db::name('service_tags')->where('code', $code)->find();
    if ($row) {
        $id = (int) $row['id'];
        Db::name('service_tags')->where('id', $id)->update(['sort_order' => $sort, 'status' => 1]);
    } else {
        $id = (int) Db::name('service_tags')->insertGetId([
            'code' => $code, 'sort_order' => $sort, 'status' => 1,
        ]);
    }
    foreach (['zh-HK' => $zh, 'en' => $en] as $locale => $name) {
        $exists = Db::name('service_tag_i18n')->where(['service_tag_id' => $id, 'locale' => $locale])->find();
        if ($exists) {
            Db::name('service_tag_i18n')->where(['service_tag_id' => $id, 'locale' => $locale])->update(['name' => $name]);
        } else {
            Db::name('service_tag_i18n')->insert(['service_tag_id' => $id, 'locale' => $locale, 'name' => $name]);
        }
    }

    return $id;
}

function upsertArticle(string $code, string $zhTitle, string $zhBody, string $enTitle, string $enBody, int $sort): void
{
    $row = Db::name('articles')->where('code', $code)->find();
    $data = [
        'code' => $code, 'status' => 1, 'show_in_footer' => 1, 'sort_order' => $sort, 'updated_at' => now(),
    ];
    if ($row) {
        Db::name('articles')->where('id', $row['id'])->update($data);
        $id = (int) $row['id'];
    } else {
        $data['created_at'] = now();
        $id = (int) Db::name('articles')->insertGetId($data);
    }
    $i18n = [
        'zh-HK' => [$zhTitle, $zhBody],
        'en'    => [$enTitle, $enBody],
    ];
    foreach ($i18n as $locale => [$title, $body]) {
        $exists = Db::name('article_i18n')->where(['article_id' => $id, 'locale' => $locale])->find();
        if ($exists) {
            Db::name('article_i18n')->where(['article_id' => $id, 'locale' => $locale])->update(compact('title', 'body'));
        } else {
            Db::name('article_i18n')->insert(['article_id' => $id, 'locale' => $locale, 'title' => $title, 'body' => $body]);
        }
    }
}

/**
 * @param list<string> $media
 * @param list<string> $badges
 * @param list<int> $tagIds
 * @param list<array{code:string,name:array{zh-HK:string,en:string},options:list<array{code:string,name:array{zh-HK:string,en:string},image?:?string}>}> $groups
 * @param list<array{code:string,price:float,compare:float,stock:int,options:list<string>,image?:?string}> $skus
 */
function upsertProduct(
    string $code,
    string $categoryCode,
    array $name,
    array $detail,
    int $sold,
    ?string $cover,
    array $media,
    array $badges,
    array $tagIds,
    array $groups,
    array $skus,
): void {
    $categoryId = (int) Db::name('categories')->where('code', $categoryCode)->value('id');
    $row = Db::name('products')->where('code', $code)->find();
    $data = [
        'code'        => $code,
        'category_id' => $categoryId,
        'status'      => 1,
        'sold_count'  => $sold,
        'cover_url'   => $cover,
        'updated_at'  => now(),
    ];
    if ($row) {
        Db::name('products')->where('id', $row['id'])->update($data);
        $id = (int) $row['id'];
    } else {
        $data['created_at'] = now();
        $id = (int) Db::name('products')->insertGetId($data);
    }

    foreach (['zh-HK', 'en'] as $locale) {
        $payload = [
            'name'    => $name[$locale],
            'summary' => '',
            'detail'  => $detail[$locale],
        ];
        $exists = Db::name('product_i18n')->where(['product_id' => $id, 'locale' => $locale])->find();
        if ($exists) {
            Db::name('product_i18n')->where(['product_id' => $id, 'locale' => $locale])->update($payload);
        } else {
            Db::name('product_i18n')->insert(array_merge(['product_id' => $id, 'locale' => $locale], $payload));
        }
    }

    Db::name('product_media')->where('product_id', $id)->delete();
    foreach ($media as $i => $url) {
        Db::name('product_media')->insert([
            'product_id' => $id, 'media_type' => 'image', 'url' => $url, 'sort_order' => $i,
        ]);
    }

    Db::name('product_badges')->where('product_id', $id)->delete();
    foreach ($badges as $badge) {
        Db::name('product_badges')->insert(['product_id' => $id, 'badge' => $badge]);
    }

    Db::name('product_service_tags')->where('product_id', $id)->delete();
    foreach ($tagIds as $tagId) {
        Db::name('product_service_tags')->insert(['product_id' => $id, 'service_tag_id' => $tagId]);
    }

    $oldGroups = Db::name('spec_groups')->where('product_id', $id)->column('id');
    if ($oldGroups) {
        $oldOptions = Db::name('spec_options')->whereIn('spec_group_id', $oldGroups)->column('id');
        if ($oldOptions) {
            Db::name('spec_option_i18n')->whereIn('spec_option_id', $oldOptions)->delete();
            Db::name('sku_options')->whereIn('spec_option_id', $oldOptions)->delete();
            Db::name('spec_options')->whereIn('id', $oldOptions)->delete();
        }
        Db::name('spec_group_i18n')->whereIn('spec_group_id', $oldGroups)->delete();
        Db::name('spec_groups')->whereIn('id', $oldGroups)->delete();
    }
    $oldSkus = Db::name('skus')->where('product_id', $id)->column('id');
    if ($oldSkus) {
        Db::name('sku_options')->whereIn('sku_id', $oldSkus)->delete();
        Db::name('skus')->whereIn('id', $oldSkus)->delete();
    }

    $optionIds = [];
    foreach ($groups as $gi => $group) {
        $gid = (int) Db::name('spec_groups')->insertGetId([
            'product_id' => $id, 'code' => $group['code'], 'sort_order' => $gi,
        ]);
        foreach (['zh-HK', 'en'] as $locale) {
            Db::name('spec_group_i18n')->insert([
                'spec_group_id' => $gid, 'locale' => $locale, 'name' => $group['name'][$locale],
            ]);
        }
        foreach ($group['options'] as $oi => $opt) {
            $oid = (int) Db::name('spec_options')->insertGetId([
                'spec_group_id' => $gid,
                'code'          => $opt['code'],
                'image_url'     => $opt['image'] ?? null,
                'sort_order'    => $oi,
            ]);
            foreach (['zh-HK', 'en'] as $locale) {
                Db::name('spec_option_i18n')->insert([
                    'spec_option_id' => $oid, 'locale' => $locale, 'name' => $opt['name'][$locale],
                ]);
            }
            $optionIds[$group['code'] . ':' . $opt['code']] = $oid;
        }
    }

    foreach ($skus as $sku) {
        $sid = (int) Db::name('skus')->insertGetId([
            'product_id' => $id,
            'code'       => $sku['code'],
            'price'      => $sku['price'],
            'compare_at' => $sku['compare'],
            'stock'      => $sku['stock'],
            'image_url'  => $sku['image'] ?? null,
            'status'     => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        foreach ($sku['options'] as $ref) {
            Db::name('sku_options')->insert([
                'sku_id'         => $sid,
                'spec_option_id' => $optionIds[$ref],
            ]);
        }
    }
}

Db::transaction(function () {
    upsertCategory('bracelets', null, '手鏈', 'Bracelets', mock('cat-bracelet.jpg'), 1);
    upsertCategory('necklaces', null, '項鏈', 'Necklaces', mock('cat-necklace.jpg'), 2);
    upsertCategory('earrings', null, '耳環', 'Earrings', mock('cat-earrings.jpg'), 3);
    upsertCategory('rings', null, '戒指', 'Rings', mock('cat-ring.jpg'), 4);
    upsertCategory('scarves', null, '頭巾', 'Scarves', mock('cat-headscarf.jpg'), 5);
    upsertCategory('bags', null, '包袋', 'Bags', mock('cat-bag.jpg'), 6);
    upsertCategory('sets', null, '套裝', 'Sets', null, 7);

    upsertCategory('b-heart', 'bracelets', '愛心手鏈', 'Heart', mock('product-4.jpg'), 1);
    upsertCategory('b-gold', 'bracelets', '金珠手鏈', 'Gold Link', '/products/sub-gold-bead.jpg', 2);
    upsertCategory('n-gourd', 'necklaces', '葫蘆項鏈', 'Gourd', mock('product-2.jpg'), 1);
    upsertCategory('n-square', 'necklaces', '方鑽項鏈', 'Cushion', mock('product-1.jpg'), 2);
    upsertCategory('n-pearl', 'necklaces', '珍珠項鏈', 'Pearl', '/products/sub-pearl.jpg', 3);
    upsertCategory('n-clavicle', 'necklaces', '鎖骨鏈', 'Clavicle', '/products/sub-clavicle.jpg', 4);
    upsertCategory('n-heart', 'necklaces', '愛心項鏈', 'Heart', '/products/sub-heart-neck.jpg', 5);

    $tagShip = upsertTag('hk-ship', '香港包郵門檻', 'Free-shipping threshold', 1);
    $tagAfter = upsertTag('aftersale', '售後保障', 'After-sales cover', 2);

    upsertArticle(
        'terms',
        '用戶協議',
        '使用 SisterMart 即表示你同意以電郵或手機建立帳戶，並遵守本店的下單、付款與售後規則。帳戶資料只用於訂單、驗證碼和客戶服務。',
        'Terms of Use',
        'By using SisterMart you agree to create an account with email or phone, and to follow this store’s ordering, payment, and after-sales rules.',
        1,
    );
    upsertArticle(
        'privacy',
        '私隱協議',
        '我們會保存你的電郵、手機和收貨地址，用於登錄驗證、配送和售後。驗證碼可能經電郵或 WhatsApp 發送。你可以在設置中修改資料或退出登錄。',
        'Privacy Policy',
        'We store your email, phone number, and shipping address for sign-in, delivery, and after-sales. Verification codes may be sent by email or WhatsApp.',
        2,
    );

    $p1 = mock('product-1.jpg');
    $p2 = mock('product-2.jpg');
    $p3 = mock('product-3.jpg');
    $p4 = mock('product-4.jpg');

    upsertProduct(
        'p1',
        'n-square',
        ['zh-HK' => '方鑽項鏈', 'en' => 'Cushion Necklace'],
        [
            'zh-HK' => '方鑽項鏈，香港現貨。下方為細節實拍。',
            'en'    => 'Cushion Necklace, ready to ship in Hong Kong. Detail photos are below.',
        ],
        56,
        $p1,
        [$p1, mock('product-1-detail.jpg'), mock('product-1-detail-2.jpg')],
        ['flash'],
        [$tagShip, $tagAfter],
        [[
            'code' => 'style',
            'name' => ['zh-HK' => '款式', 'en' => 'Style'],
            'options' => [
                ['code' => 'classic', 'name' => ['zh-HK' => '經典', 'en' => 'Classic'], 'image' => $p1],
                ['code' => 'detail', 'name' => ['zh-HK' => '細節款', 'en' => 'Detail'], 'image' => mock('product-1-detail.jpg')],
            ],
        ]],
        [
            ['code' => 'classic', 'price' => 128, 'compare' => 158, 'stock' => 20, 'options' => ['style:classic']],
            ['code' => 'detail', 'price' => 138, 'compare' => 168, 'stock' => 9, 'options' => ['style:detail']],
        ],
    );

    upsertProduct(
        'p2',
        'n-gourd',
        ['zh-HK' => '葫蘆項鏈', 'en' => 'Gourd Necklace'],
        [
            'zh-HK' => '葫蘆項鏈，香港現貨。下方為細節實拍。',
            'en'    => 'Gourd Necklace, ready to ship in Hong Kong. Detail photos are below.',
        ],
        88,
        $p2,
        [$p2, mock('product-2-detail-1.jpg'), mock('product-2-detail-2.jpg'), mock('product-2-detail-3.jpg')],
        ['new', 'editor'],
        [$tagShip, $tagAfter],
        [[
            'code' => 'style',
            'name' => ['zh-HK' => '款式', 'en' => 'Style'],
            'options' => [
                ['code' => 'classic', 'name' => ['zh-HK' => '經典', 'en' => 'Classic'], 'image' => $p2],
                ['code' => 'detail', 'name' => ['zh-HK' => '細節款', 'en' => 'Detail'], 'image' => mock('product-2-detail-1.jpg')],
            ],
        ]],
        [
            ['code' => 'classic', 'price' => 128, 'compare' => 158, 'stock' => 20, 'options' => ['style:classic']],
            ['code' => 'detail', 'price' => 138, 'compare' => 168, 'stock' => 9, 'options' => ['style:detail']],
        ],
    );

    upsertProduct(
        'p3',
        'bracelets',
        ['zh-HK' => '四葉草手鏈', 'en' => 'Clover Bracelet'],
        [
            'zh-HK' => '四葉草手鏈，香港現貨。下方為細節實拍。',
            'en'    => 'Clover Bracelet, ready to ship in Hong Kong. Detail photos are below.',
        ],
        84,
        $p3,
        [$p3, mock('product-3-detail.jpg'), mock('product-3-detail-2.jpg'), mock('product-3-detail-3.jpg')],
        ['hot'],
        [$tagShip, $tagAfter],
        [[
            'code' => 'style',
            'name' => ['zh-HK' => '款式', 'en' => 'Style'],
            'options' => [
                ['code' => 'classic', 'name' => ['zh-HK' => '經典', 'en' => 'Classic'], 'image' => $p3],
                ['code' => 'detail', 'name' => ['zh-HK' => '細節款', 'en' => 'Detail'], 'image' => mock('product-3-detail.jpg')],
            ],
        ]],
        [
            ['code' => 'classic', 'price' => 49, 'compare' => 79, 'stock' => 20, 'options' => ['style:classic']],
            ['code' => 'detail', 'price' => 59, 'compare' => 89, 'stock' => 9, 'options' => ['style:detail']],
        ],
    );

    upsertProduct(
        'p4',
        'b-heart',
        ['zh-HK' => '紅心手鏈', 'en' => 'Heart Bracelet'],
        [
            'zh-HK' => '紅色愛心手鏈，鍍金鏈身配琺瑯愛心。可選顏色與鏈長，細節見下方實拍。',
            'en'    => 'Heart bracelet with an enamel motif on a gold-tone chain. Choose a color and length. Photos below show the details.',
        ],
        42,
        $p4,
        [$p4, mock('product-4-detail-1.jpg'), mock('product-4-detail-2.jpg'), mock('product-4-detail-3.jpg')],
        ['flash'],
        [$tagShip, $tagAfter],
        [
            [
                'code' => 'color',
                'name' => ['zh-HK' => '顏色', 'en' => 'Color'],
                'options' => [
                    ['code' => 'red', 'name' => ['zh-HK' => '紅色愛心', 'en' => 'Red heart'], 'image' => $p4],
                    ['code' => 'white', 'name' => ['zh-HK' => '白色', 'en' => 'White'], 'image' => mock('product-4-detail-3.jpg')],
                    ['code' => 'gold', 'name' => ['zh-HK' => '金色', 'en' => 'Gold'], 'image' => mock('product-4-detail-2.jpg')],
                ],
            ],
            [
                'code' => 'size',
                'name' => ['zh-HK' => '尺寸', 'en' => 'Size'],
                'options' => [
                    ['code' => '16', 'name' => ['zh-HK' => '16 cm', 'en' => '16 cm']],
                    ['code' => '18', 'name' => ['zh-HK' => '18 cm', 'en' => '18 cm']],
                ],
            ],
        ],
        [
            ['code' => 'red|16', 'price' => 69, 'compare' => 99, 'stock' => 12, 'options' => ['color:red', 'size:16'], 'image' => $p4],
            ['code' => 'red|18', 'price' => 79, 'compare' => 109, 'stock' => 8, 'options' => ['color:red', 'size:18'], 'image' => $p4],
            ['code' => 'white|16', 'price' => 69, 'compare' => 99, 'stock' => 5, 'options' => ['color:white', 'size:16'], 'image' => mock('product-4-detail-3.jpg')],
            ['code' => 'white|18', 'price' => 79, 'compare' => 109, 'stock' => 3, 'options' => ['color:white', 'size:18'], 'image' => mock('product-4-detail-3.jpg')],
            ['code' => 'gold|16', 'price' => 89, 'compare' => 119, 'stock' => 6, 'options' => ['color:gold', 'size:16'], 'image' => mock('product-4-detail-2.jpg')],
            ['code' => 'gold|18', 'price' => 99, 'compare' => 129, 'stock' => 0, 'options' => ['color:gold', 'size:18'], 'image' => mock('product-4-detail-2.jpg')],
        ],
    );
});

echo "m3 seed ok\n";
