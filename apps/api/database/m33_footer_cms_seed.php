<?php

declare(strict_types=1);

/**
 * SisterMart 頁腳 CMS 文章（精簡版，zh-HK / en）
 *
 * 覆蓋（皆含 zh-HK + en）：
 *   about / contact / privacy / shipping / return / refund
 *   terms / service / payment
 * 用法：php apps/api/database/m33_footer_cms_seed.php
 *
 * 聯絡頁連結讀取 shop_settings：
 *   cs.whatsapp      （純數字，如 85255983063）
 *   cs.email         （客服電郵，如 service@sistermart.vip）
 *   cs.facebook_url  （粉絲專頁完整 URL；可後改）
 */

require dirname(__DIR__) . '/vendor/autoload.php';

$app = new think\App(dirname(__DIR__));
$app->initialize();

use think\facade\Db;

function now(): string
{
    return date('Y-m-d H:i:s');
}

function ensureSetting(string $key, string $value): string
{
    $row = Db::name('shop_settings')->where('setting_key', $key)->find();
    if ($row) {
        return (string) $row['setting_value'];
    }
    Db::name('shop_settings')->insert([
        'setting_key' => $key,
        'setting_value' => $value,
        'updated_at' => now(),
    ]);

    return $value;
}

function upsertArticle(string $code, string $zhTitle, string $zhBody, string $enTitle, string $enBody, int $sort): void
{
    $row = Db::name('articles')->where('code', $code)->find();
    $data = [
        'code' => $code,
        'status' => 1,
        'show_in_footer' => 1,
        'sort_order' => $sort,
        'updated_at' => now(),
    ];
    if ($row) {
        Db::name('articles')->where('id', $row['id'])->update($data);
        $id = (int) $row['id'];
    } else {
        $data['created_at'] = now();
        $id = (int) Db::name('articles')->insertGetId($data);
    }
    foreach (['zh-HK' => [$zhTitle, $zhBody], 'en' => [$enTitle, $enBody]] as $locale => [$title, $body]) {
        $exists = Db::name('article_i18n')->where(['article_id' => $id, 'locale' => $locale])->find();
        if ($exists) {
            Db::name('article_i18n')->where(['article_id' => $id, 'locale' => $locale])->update([
                'title' => $title,
                'body' => $body,
            ]);
        } else {
            Db::name('article_i18n')->insert([
                'article_id' => $id,
                'locale' => $locale,
                'title' => $title,
                'body' => $body,
            ]);
        }
    }
}

$whatsapp = preg_replace('/\D+/', '', ensureSetting('cs.whatsapp', '85255983063')) ?: '85255983063';
$email = trim(ensureSetting('cs.email', 'service@sistermart.vip'));
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $email = 'service@sistermart.vip';
}
$facebook = trim(ensureSetting('cs.facebook_url', 'https://www.facebook.com/'));
if ($facebook === '') {
    $facebook = 'https://www.facebook.com/';
}
$waLink = 'https://wa.me/' . $whatsapp;
$fbEsc = htmlspecialchars($facebook, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$waEsc = htmlspecialchars($waLink, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$emailEsc = htmlspecialchars($email, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

$fillContact = static function (string $html) use ($waEsc, $fbEsc, $emailEsc): string {
    return str_replace(
        ['{{WA_HREF}}', '{{FB_HREF}}', '{{EMAIL}}'],
        [$waEsc, $fbEsc, $emailEsc],
        $html,
    );
};

// ——— 關於我們（含商業登記 BR 合規披露；見 about_us_*.html） ———
$aboutZh = (string) file_get_contents(__DIR__ . '/about_us_zh_hk.html');
$aboutEn = (string) file_get_contents(__DIR__ . '/about_us_en.html');
upsertArticle(
    'about',
    '關於我們',
    $aboutZh,
    'About Us',
    $aboutEn,
    10,
);

// ——— 聯絡我們（含客服電郵；見 contact_us_*.html） ———
$contactZh = $fillContact((string) file_get_contents(__DIR__ . '/contact_us_zh_hk.html'));
$contactEn = $fillContact((string) file_get_contents(__DIR__ . '/contact_us_en.html'));
upsertArticle(
    'contact',
    '聯絡我們',
    $contactZh,
    'Contact Us',
    $contactEn,
    40,
);

// ——— 私隱政策（PDPO 合規詳版；見 privacy_policy_*.html） ———
$privacyZh = (string) file_get_contents(__DIR__ . '/privacy_policy_zh_hk.html');
$privacyEn = (string) file_get_contents(__DIR__ . '/privacy_policy_en.html');
upsertArticle(
    'privacy',
    '私隱政策',
    $privacyZh,
    'Privacy Policy',
    $privacyEn,
    50,
);

// ——— 運送政策（對齊現有運費設定：滿 200 包郵／否則 25） ———
upsertArticle(
    'shipping',
    '運送政策',
    <<<HTML
<p>SisterMart 目前主要服務<strong>香港地區</strong>配送（離島／偏遠地區或需另議）。</p>
<h2>運費</h2>
<ul>
<li>訂單滿 <strong>HK\$200</strong>：香港本地<strong>免運費</strong></li>
<li>未滿 HK\$200：運費 <strong>HK\$25</strong></li>
</ul>
<p>實際金額以結帳頁顯示為準；如有活動調整會在頁面另行說明。</p>
<h2>出貨與時效</h2>
<ul>
<li>現貨訂單一般於付款確認後 <strong>1–2 個工作天</strong>安排出貨</li>
<li>送達時間視物流公司與收件地區而定，通常數個工作天內</li>
<li>遇公眾假期、天氣或物流繁忙，或會稍有延遲</li>
</ul>
<h2>訂單追蹤</h2>
<p>出貨後可在「我的訂單」查看物流狀態。地址請務必填寫正確；因資料錯誤導致的延誤或退回，可能需另行協調補寄費用。</p>
HTML,
    'Shipping Policy',
    <<<HTML
<p>SisterMart currently ships mainly within <strong>Hong Kong</strong> (outlying/remote areas may need special arrangement).</p>
<h2>Shipping fees</h2>
<ul>
<li>Orders <strong>HK\$200+</strong>: <strong>free</strong> local shipping</li>
<li>Under HK\$200: <strong>HK\$25</strong> shipping</li>
</ul>
<p>The checkout page always shows the final amount. Promo changes will be noted on site.</p>
<h2>Dispatch &amp; timing</h2>
<ul>
<li>In-stock orders usually ship within <strong>1–2 business days</strong> after payment</li>
<li>Delivery typically takes a few business days depending on the courier and district</li>
<li>Public holidays, weather, or peak periods may cause delays</li>
</ul>
<h2>Tracking</h2>
<p>After dispatch, track status under My Orders. Please enter a correct address—delays or returns caused by wrong details may require extra reship fees.</p>
HTML,
    30,
);

// ——— 退貨政策（合規詳版＋店家審核空間；見 return_policy_*.html） ———
$returnZh = (string) file_get_contents(__DIR__ . '/return_policy_zh_hk.html');
$returnEn = (string) file_get_contents(__DIR__ . '/return_policy_en.html');
upsertArticle(
    'return',
    '退貨政策',
    $returnZh,
    'Return Policy',
    $returnEn,
    90,
);

// ——— 退款政策（合規詳版＋店家操作空間；見 refund_policy_*.html） ———
$refundZh = (string) file_get_contents(__DIR__ . '/refund_policy_zh_hk.html');
$refundEn = (string) file_get_contents(__DIR__ . '/refund_policy_en.html');
upsertArticle(
    'refund',
    '退款政策',
    $refundZh,
    'Refund Policy',
    $refundEn,
    60,
);

// ——— 使用條款 ———
upsertArticle(
    'terms',
    '使用條款',
    <<<HTML
<p>歡迎使用 <strong>SisterMart（姐妹商店）</strong>。使用本網站或下單，即表示你同意本條款。若不同意，請停止使用本服務。</p>
<h2>帳戶</h2>
<ul>
<li>你須提供真實、有效的電郵或手機資料以註冊／登入</li>
<li>請妥善保管帳戶；因帳戶外洩導致的損失，請盡快聯絡我們</li>
<li>我們可在發現欺詐、濫用或違法行為時暫停或終止帳戶</li>
</ul>
<h2>商品與訂單</h2>
<ul>
<li>商品圖片與描述力求準確；色差、手感等以實物為準</li>
<li>下單並完成付款後，合約方告成立；庫存不足時我們會盡快通知並協助取消或改訂</li>
<li>價格、優惠與運費以結帳頁最終顯示為準</li>
</ul>
<h2>付款與配送</h2>
<p>請使用本店提供的付款方式。配送範圍、運費與時效見「運送政策」。</p>
<h2>售後</h2>
<p>退貨、退款規則見「退貨政策」與「退款政策」。爭議時我們會本著合理原則處理。</p>
<h2>免責與修改</h2>
<p>在法律允許範圍內，因不可抗力、物流或第三方服務中斷造成的延誤，我們不承擔超出合理範圍的責任。本條款可能更新，更新後以本頁最新版本為準。</p>
HTML,
    'Terms of Use',
    <<<HTML
<p>Welcome to <strong>SisterMart</strong>. By using this site or placing an order, you agree to these terms. If you do not agree, please stop using the service.</p>
<h2>Accounts</h2>
<ul>
<li>Provide a valid email or phone number to register/sign in</li>
<li>Keep your account secure; contact us promptly if it is compromised</li>
<li>We may suspend or close accounts for fraud, abuse, or illegal use</li>
</ul>
<h2>Products &amp; orders</h2>
<ul>
<li>We aim for accurate photos and descriptions; minor color/feel differences may occur</li>
<li>A contract forms after successful payment; if stock is short we will notify you and help cancel or adjust</li>
<li>Price, promos, and shipping follow the final checkout amounts</li>
</ul>
<h2>Payment &amp; shipping</h2>
<p>Please use the payment methods we offer. Coverage, fees, and timing are in the Shipping Policy.</p>
<h2>After-sales</h2>
<p>Returns and refunds follow the Return Policy and Refund Policy. We handle disputes reasonably.</p>
<h2>Liability &amp; updates</h2>
<p>To the extent allowed by law, we are not liable beyond a reasonable scope for delays from force majeure, logistics, or third-party outages. These terms may be updated; the latest version on this page applies.</p>
HTML,
    20,
);

// ——— 客戶服務 ———
upsertArticle(
    'service',
    '客戶服務',
    <<<HTML
<p>SisterMart 希望每次購物都簡單安心。以下是常用服務說明。</p>
<h2>服務範圍</h2>
<ul>
<li>商品咨詢與尺碼／材質建議</li>
<li>訂單、付款、配送進度查詢</li>
<li>退貨、換貨、退款協助</li>
<li>帳戶登入與驗證碼問題</li>
</ul>
<h2>聯絡方式</h2>
<p>請前往「聯絡我們」：工作時間內優先使用 <strong>WhatsApp</strong>；亦可於 Facebook 留言（繁忙時或需排隊）。</p>
<h2>回覆時效</h2>
<ul>
<li>工作時間（10:00–19:00，香港時間）內：一般數小時內回覆</li>
<li>非工作時段：下一工作時段處理</li>
</ul>
<p>為加快處理，請準備<strong>訂單編號</strong>與問題說明／圖片。</p>
HTML,
    'Customer Service',
    <<<HTML
<p>SisterMart aims to make shopping simple. Here is how we support you.</p>
<h2>What we help with</h2>
<ul>
<li>Product questions and fit/material advice</li>
<li>Order, payment, and shipping updates</li>
<li>Returns, exchanges, and refunds</li>
<li>Account login and verification codes</li>
</ul>
<h2>How to reach us</h2>
<p>See <strong>Contact Us</strong>: prefer <strong>WhatsApp</strong> during business hours; Facebook messages are welcome too (may queue when busy).</p>
<h2>Response time</h2>
<ul>
<li>During hours (10:00–19:00 HKT): usually within a few hours</li>
<li>Outside hours: handled in the next working window</li>
</ul>
<p>Please include your <strong>order number</strong> and a short description/photos so we can help faster.</p>
HTML,
    70,
);

// ——— 付款政策 ———
upsertArticle(
    'payment',
    '付款政策',
    <<<HTML
<p>本頁說明 SisterMart 的付款方式與注意事項。</p>
<h2>可用付款方式</h2>
<p>結帳頁會顯示當前可用渠道（例如線上支付、轉數快或其他本店已開通的方式）。請以結帳頁即時選項為準。</p>
<h2>付款與訂單</h2>
<ul>
<li>請在時限內完成付款；逾時未付訂單可能自動取消並釋放庫存</li>
<li>付款成功後，可在「我的訂單」查看狀態</li>
<li>如顯示已扣款但訂單未更新，請保留憑證並聯絡客服，我們會協助核對</li>
</ul>
<h2>貨幣與收據</h2>
<p>本店以 <strong>港幣（HKD）</strong>標價與結算。電子收據／訂單詳情可於帳戶訂單中查看。</p>
<h2>安全</h2>
<p>請勿向非官方渠道提供驗證碼或完整卡資料。我們不會以非官方連結要求你重複付款。</p>
HTML,
    'Payment Policy',
    <<<HTML
<p>This page explains how payment works at SisterMart.</p>
<h2>Available methods</h2>
<p>Checkout shows the methods currently enabled (e.g. online pay, FPS, or other channels we support). Always follow the live options on the checkout page.</p>
<h2>Payment &amp; orders</h2>
<ul>
<li>Pay within the time limit; unpaid orders may auto-cancel and release stock</li>
<li>After successful payment, check status under My Orders</li>
<li>If you were charged but the order did not update, keep the receipt and contact support for verification</li>
</ul>
<h2>Currency &amp; receipts</h2>
<p>Prices and settlement use <strong>Hong Kong Dollars (HKD)</strong>. Order details/receipts are available in your account orders.</p>
<h2>Safety</h2>
<p>Never share OTP codes or full card details via unofficial channels. We will not ask you to pay again through suspicious links.</p>
HTML,
    80,
);

echo "m33_footer_cms_seed ok (zh-HK + en)\n";
echo "whatsapp={$whatsapp} facebook={$facebook}\n";
foreach (['about', 'contact', 'privacy', 'shipping', 'return', 'refund', 'terms', 'service', 'payment'] as $code) {
    $row = Db::name('articles')->where('code', $code)->find();
    $zh = Db::name('article_i18n')->where(['article_id' => $row['id'], 'locale' => 'zh-HK'])->find();
    $en = Db::name('article_i18n')->where(['article_id' => $row['id'], 'locale' => 'en'])->find();
    echo " - {$code}: {$zh['title']} / {$en['title']}\n";
}
