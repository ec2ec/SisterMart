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
 *   cs.whatsapp      （純數字，如 85290001234）
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

$whatsapp = preg_replace('/\D+/', '', ensureSetting('cs.whatsapp', '85290001234')) ?: '85290001234';
$facebook = trim(ensureSetting('cs.facebook_url', 'https://www.facebook.com/'));
if ($facebook === '') {
    $facebook = 'https://www.facebook.com/';
}
$waLink = 'https://wa.me/' . $whatsapp;
$fbEsc = htmlspecialchars($facebook, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$waEsc = htmlspecialchars($waLink, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

// 輕量按鈕（避免內聯 SVG 造成 H5 v-html／後台富文本卡頓）
$btnWa = 'class="sm-contact-btn sm-contact-wa"';
$btnFb = 'class="sm-contact-btn sm-contact-fb"';

// ——— 關於我們 ———
upsertArticle(
    'about',
    '關於我們',
    <<<HTML
<p><strong>SisterMart（姐妹商店）</strong>於 <strong>2026 年</strong>成立，是一間立足香港的線上選品小店。</p>
<p>我們專注優選<strong>高性價比</strong>的各類好物，希望把<strong>實惠與品質</strong>同時帶給姐妹們——好看、好用、價錢也公道。</p>
<h2>我們在意什麼</h2>
<ul>
<li>嚴選商品與清晰實拍，減少「貨不對板」</li>
<li>香港本地配送體驗，流程簡單好跟進</li>
<li>售前售後都聽得見你的聲音</li>
</ul>
<p>謝謝你來到 SisterMart。有任何想法，都歡迎透過「聯絡我們」告訴我們。</p>
HTML,
    'About Us',
    <<<HTML
<p><strong>SisterMart</strong> was founded in <strong>2026</strong> as a Hong Kong–based online boutique.</p>
<p>We curate <strong>great value</strong> everyday finds—bringing <strong>affordable quality</strong> to our community with clear photos and honest pricing.</p>
<h2>What we care about</h2>
<ul>
<li>Careful picks and real product photos</li>
<li>Straightforward local shipping in Hong Kong</li>
<li>Friendly support before and after your order</li>
</ul>
<p>Thanks for visiting SisterMart. Reach us anytime via Contact Us.</p>
HTML,
    10,
);

// ——— 聯絡我們 ———
upsertArticle(
    'contact',
    '聯絡我們',
    <<<HTML
<p>購物、訂單或售後問題，歡迎透過以下方式聯絡 <strong>SisterMart 姐妹商店</strong>。</p>
<h2>1. 線上聊天（WhatsApp）</h2>
<p><a href="{$waEsc}" target="_blank" rel="noopener noreferrer" {$btnWa}>WhatsApp 客服</a></p>
<p>工作時間：每日 <strong>10:00–19:00</strong>（香港時間）。非工作時段留言，我們會在下一工作時段回覆。</p>
<h2>2. 社交媒體</h2>
<p>你也可以在社交平台找到我們。繁忙時段該服務<strong>可能需要排隊</strong>，感謝耐心等候。</p>
<p><a href="{$fbEsc}" target="_blank" rel="noopener noreferrer" {$btnFb}>Facebook</a></p>
HTML,
    'Contact Us',
    <<<HTML
<p>For shopping, orders, or after-sales questions, contact <strong>SisterMart</strong> below.</p>
<h2>1. Live chat (WhatsApp)</h2>
<p><a href="{$waEsc}" target="_blank" rel="noopener noreferrer" {$btnWa}>WhatsApp support</a></p>
<p>Hours: daily <strong>10:00–19:00</strong> (Hong Kong time). Messages outside hours will be answered in the next working window.</p>
<h2>2. Social media</h2>
<p>You can also find us on social media. During busy periods this channel <strong>may require queuing</strong>—thanks for your patience.</p>
<p><a href="{$fbEsc}" target="_blank" rel="noopener noreferrer" {$btnFb}>Facebook</a></p>
HTML,
    40,
);

// ——— 私隱協議（原創精簡；非照搬外部條款） ———
upsertArticle(
    'privacy',
    '私隱協議',
    <<<HTML
<p>SisterMart（姐妹商店）重視你的私隱。本協議說明我們如何收集、使用與保護個人資料。使用本店服務，即表示你知悉本協議內容。</p>
<h2>我們收集的資料</h2>
<ul>
<li>帳戶資料：電郵、手機號碼、暱稱／頭像等</li>
<li>訂單與配送：收件人姓名、電話、地址、訂單內容</li>
<li>登入與驗證：驗證碼發送記錄、裝置／瀏覽的基本技術資訊</li>
<li>客服溝通：你透過 WhatsApp 或站內訊息提供的內容</li>
</ul>
<h2>用途</h2>
<ul>
<li>建立帳戶、登入驗證（電郵或 WhatsApp）</li>
<li>處理訂單、付款核對、配送與售後</li>
<li>發送與訂單／帳戶相關的必要通知</li>
<li>改善網站體驗與防止欺詐（在合理範圍內）</li>
</ul>
<h2>分享與保存</h2>
<p>我們不會出售你的個人資料。僅在配送、支付或法律要求等必要情況下，與受託服務方共享完成服務所需的最少資料。資料保存至業務與法律所需期間，期後刪除或匿名化。</p>
<h2>你的選擇</h2>
<p>你可在帳戶設定更新資料或退出登錄。如需查閱、更正或刪除個人資料，請透過「聯絡我們」提出。我們可能不時更新本協議，更新後以本頁最新版本為準。</p>
HTML,
    'Privacy Policy',
    <<<HTML
<p>SisterMart respects your privacy. This notice explains what we collect and how we use it. Using our shop means you acknowledge this policy.</p>
<h2>Data we collect</h2>
<ul>
<li>Account details: email, phone, nickname/avatar</li>
<li>Order &amp; delivery: recipient name, phone, address, order items</li>
<li>Sign-in &amp; verification: OTP delivery logs and basic device/browser data</li>
<li>Support messages you send via WhatsApp or in-app chat</li>
</ul>
<h2>How we use it</h2>
<ul>
<li>Account creation and login (email or WhatsApp)</li>
<li>Orders, payment checks, shipping, and after-sales</li>
<li>Necessary account/order notifications</li>
<li>Improving the site and preventing abuse, within reason</li>
</ul>
<h2>Sharing &amp; retention</h2>
<p>We do not sell your personal data. We only share the minimum needed with trusted providers (e.g. delivery or payment) or when required by law. We keep data only as long as needed for business and legal purposes, then delete or anonymize it.</p>
<h2>Your choices</h2>
<p>You can update your profile or log out in account settings. To access, correct, or delete personal data, contact us via Contact Us. We may update this page from time to time; the latest version here applies.</p>
HTML,
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

// ——— 退貨政策 ———
upsertArticle(
    'return',
    '退貨政策',
    <<<HTML
<p>我們希望你收到滿意的商品。以下為 SisterMart 退貨說明（簡要）。</p>
<h2>可申請情形</h2>
<ul>
<li>收到商品後 <strong>7 天內</strong>（以簽收日計）</li>
<li>商品未穿用／未使用、吊牌與包裝完整、不影響二次銷售</li>
<li>質量問題或與頁面描述明顯不符</li>
</ul>
<h2>不適用／限制</h2>
<ul>
<li>個人衛生相關、已拆封的貼身／美容耗材等（法律或衛生原因）</li>
<li>特價清貨、訂製或註明不可退換的商品</li>
<li>人為損壞、洗滌後、或缺少配件</li>
</ul>
<h2>如何辦理</h2>
<ol>
<li>在訂單進入售後申請，或透過「聯絡我們」的 WhatsApp 說明訂單編號與原因</li>
<li>按指引寄回（保留寄件證明）</li>
<li>我們收貨檢驗後，再安排換貨或按退款政策處理</li>
</ol>
<p>非質量問題的退貨，來回運費一般由顧客承擔；質量問題則由我們承擔合理運費。</p>
HTML,
    'Return Policy',
    <<<HTML
<p>We want you to be happy with your order. Here is SisterMart’s simple return guide.</p>
<h2>Eligible cases</h2>
<ul>
<li>Within <strong>7 days</strong> of delivery (signed date)</li>
<li>Unused item with tags/packaging intact and resalable</li>
<li>Quality issues or clear mismatch with the product page</li>
</ul>
<h2>Not eligible / limits</h2>
<ul>
<li>Hygiene-sensitive or opened intimate/beauty consumables</li>
<li>Final sale, made-to-order, or items marked non-returnable</li>
<li>Damage from use/washing, or missing accessories</li>
</ul>
<h2>How to return</h2>
<ol>
<li>Open an after-sales request on the order, or WhatsApp us with order no. and reason</li>
<li>Ship back as instructed (keep the receipt)</li>
<li>After inspection we arrange exchange or refund per the Refund Policy</li>
</ol>
<p>For non-quality returns, shipping is usually paid by the customer; for quality issues we cover reasonable return shipping.</p>
HTML,
    90,
);

// ——— 退款政策 ———
upsertArticle(
    'refund',
    '退款政策',
    <<<HTML
<p>本政策說明 SisterMart 何時及如何退款，並與「退貨政策」一併適用。</p>
<h2>可退款情況</h2>
<ul>
<li>退貨檢驗通過後</li>
<li>付款後長時間未能出貨，且你選擇取消</li>
<li>重複扣款、系統錯誤或多收運費等經核實的情況</li>
</ul>
<h2>退款方式與時間</h2>
<ul>
<li>原則退回<strong>原支付途徑</strong>（如信用卡／支付平台／帳戶餘額等）</li>
<li>我們確認退款後，一般於 <strong>3–10 個工作天</strong>到帳（視銀行或支付平台而定）</li>
<li>使用優惠券／積分的訂單，退款時可能按規則回退券額或積分，現金部分另行計算</li>
</ul>
<h2>部分退款</h2>
<p>若訂單含多件商品，可只退回符合條件的部分；運費是否退還視整筆訂單是否仍達免運門檻及退貨原因而定。</p>
<p>詳情可於「聯絡我們」查詢你的訂單編號。</p>
HTML,
    'Refund Policy',
    <<<HTML
<p>This policy explains when and how SisterMart issues refunds, together with the Return Policy.</p>
<h2>When we refund</h2>
<ul>
<li>After an approved return passes inspection</li>
<li>Long delay before shipment where you choose to cancel</li>
<li>Verified duplicate charges, system errors, or overcharged shipping</li>
</ul>
<h2>Method &amp; timing</h2>
<ul>
<li>Refunds go back to the <strong>original payment method</strong> when possible (card/wallet/balance, etc.)</li>
<li>After we confirm, funds usually arrive in <strong>3–10 business days</strong> (bank/provider dependent)</li>
<li>Orders with coupons/points may restore benefits by rule; cash portion is calculated separately</li>
</ul>
<h2>Partial refunds</h2>
<p>For multi-item orders we may refund only eligible items. Shipping refunds depend on whether the remaining order still meets free-shipping rules and the return reason.</p>
<p>Contact us with your order number for case-specific help.</p>
HTML,
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
