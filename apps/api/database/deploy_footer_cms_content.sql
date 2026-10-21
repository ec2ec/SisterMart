-- SisterMart footer CMS content
-- Prefer: php apps/api/database/m33_footer_cms_seed.php
SET NAMES utf8mb4;

INSERT INTO articles (code, status, show_in_footer, sort_order, created_at, updated_at)
SELECT 'about', 1, 1, 10, NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM articles WHERE code='about');
UPDATE articles SET status=1, show_in_footer=1, sort_order=10, updated_at=NOW() WHERE code='about';
INSERT INTO article_i18n (article_id, locale, title, body)
SELECT a.id, 'zh-HK', '關於我們', '<p><strong>SisterMart（姐妹商店）</strong>於 <strong>2026 年</strong>成立，是一間立足香港的線上選品小店。</p>\n<p>我們專注優選<strong>高性價比</strong>的各類好物，希望把<strong>實惠與品質</strong>同時帶給姐妹們——好看、好用、價錢也公道。</p>\n<h2>我們在意什麼</h2>\n<ul>\n<li>嚴選商品與清晰實拍，減少「貨不對板」</li>\n<li>香港本地配送體驗，流程簡單好跟進</li>\n<li>售前售後都聽得見你的聲音</li>\n</ul>\n<p>謝謝你來到 SisterMart。有任何想法，都歡迎透過「聯絡我們」告訴我們。</p>'
FROM articles a WHERE a.code='about'
ON DUPLICATE KEY UPDATE title=VALUES(title), body=VALUES(body);

INSERT INTO article_i18n (article_id, locale, title, body)
SELECT a.id, 'en', 'About Us', '<p><strong>SisterMart</strong> was founded in <strong>2026</strong> as a Hong Kong–based online boutique.</p>\n<p>We curate <strong>great value</strong> everyday finds—bringing <strong>affordable quality</strong> to our community with clear photos and honest pricing.</p>\n<h2>What we care about</h2>\n<ul>\n<li>Careful picks and real product photos</li>\n<li>Straightforward local shipping in Hong Kong</li>\n<li>Friendly support before and after your order</li>\n</ul>\n<p>Thanks for visiting SisterMart. Reach us anytime via Contact Us.</p>'
FROM articles a WHERE a.code='about'
ON DUPLICATE KEY UPDATE title=VALUES(title), body=VALUES(body);

INSERT INTO articles (code, status, show_in_footer, sort_order, created_at, updated_at)
SELECT 'shipping', 1, 1, 30, NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM articles WHERE code='shipping');
UPDATE articles SET status=1, show_in_footer=1, sort_order=30, updated_at=NOW() WHERE code='shipping';
INSERT INTO article_i18n (article_id, locale, title, body)
SELECT a.id, 'zh-HK', '運送政策', '<p>SisterMart 目前主要服務<strong>香港地區</strong>配送（離島／偏遠地區或需另議）。</p>\n<h2>運費</h2>\n<ul>\n<li>訂單滿 <strong>HK$200</strong>：香港本地<strong>免運費</strong></li>\n<li>未滿 HK$200：運費 <strong>HK$25</strong></li>\n</ul>\n<p>實際金額以結帳頁顯示為準；如有活動調整會在頁面另行說明。</p>\n<h2>出貨與時效</h2>\n<ul>\n<li>現貨訂單一般於付款確認後 <strong>1–2 個工作天</strong>安排出貨</li>\n<li>送達時間視物流公司與收件地區而定，通常數個工作天內</li>\n<li>遇公眾假期、天氣或物流繁忙，或會稍有延遲</li>\n</ul>\n<h2>訂單追蹤</h2>\n<p>出貨後可在「我的訂單」查看物流狀態。地址請務必填寫正確；因資料錯誤導致的延誤或退回，可能需另行協調補寄費用。</p>'
FROM articles a WHERE a.code='shipping'
ON DUPLICATE KEY UPDATE title=VALUES(title), body=VALUES(body);

INSERT INTO article_i18n (article_id, locale, title, body)
SELECT a.id, 'en', 'Shipping Policy', '<p>SisterMart currently ships mainly within <strong>Hong Kong</strong> (outlying/remote areas may need special arrangement).</p>\n<h2>Shipping fees</h2>\n<ul>\n<li>Orders <strong>HK$200+</strong>: <strong>free</strong> local shipping</li>\n<li>Under HK$200: <strong>HK$25</strong> shipping</li>\n</ul>\n<p>The checkout page always shows the final amount. Promo changes will be noted on site.</p>\n<h2>Dispatch &amp; timing</h2>\n<ul>\n<li>In-stock orders usually ship within <strong>1–2 business days</strong> after payment</li>\n<li>Delivery typically takes a few business days depending on the courier and district</li>\n<li>Public holidays, weather, or peak periods may cause delays</li>\n</ul>\n<h2>Tracking</h2>\n<p>After dispatch, track status under My Orders. Please enter a correct address—delays or returns caused by wrong details may require extra reship fees.</p>'
FROM articles a WHERE a.code='shipping'
ON DUPLICATE KEY UPDATE title=VALUES(title), body=VALUES(body);

INSERT INTO articles (code, status, show_in_footer, sort_order, created_at, updated_at)
SELECT 'contact', 1, 1, 40, NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM articles WHERE code='contact');
UPDATE articles SET status=1, show_in_footer=1, sort_order=40, updated_at=NOW() WHERE code='contact';
INSERT INTO article_i18n (article_id, locale, title, body)
SELECT a.id, 'zh-HK', '聯絡我們', '<p>購物、訂單或售後問題，歡迎透過以下方式聯絡 <strong>SisterMart 姐妹商店</strong>。</p>\n<h2>1. 線上聊天（WhatsApp）</h2>\n<p><a href=\"https://wa.me/85290001234\" target=\"_blank\" rel=\"noopener noreferrer\" style=\"display:inline-flex;align-items:center;gap:0.15rem;padding:0.55rem 0.85rem;border-radius:999px;text-decoration:none;font-weight:600;background:#e8f8ef;color:#128C7E\"><svg xmlns=\"http://www.w3.org/2000/svg\" width=\"22\" height=\"22\" viewBox=\"0 0 24 24\" aria-hidden=\"true\" style=\"vertical-align:-0.35em;margin-right:0.4rem\"><path fill=\"#25D366\" d=\"M12.04 2c-5.46 0-9.91 4.43-9.91 9.9 0 1.75.46 3.45 1.32 4.95L2 22l5.25-1.38c1.45.79 3.08 1.21 4.79 1.21 5.46 0 9.9-4.44 9.9-9.9C21.94 6.44 17.5 2 12.04 2zm5.76 14.02c-.24.68-1.42 1.25-1.96 1.33-.5.07-1.14.1-1.84-.12-.42-.13-.97-.32-1.67-.62-2.94-1.27-4.85-4.24-5-4.44-.14-.2-1.18-1.57-1.18-3 0-1.42.74-2.12 1.01-2.41.26-.28.58-.35.77-.35h.55c.18 0 .42-.06.66.5.24.58.82 2 .89 2.15.07.14.12.32.02.51-.1.2-.14.32-.28.5-.14.17-.3.39-.43.52-.14.14-.29.3-.12.58.16.28.73 1.2 1.57 1.95 1.08.96 1.99 1.26 2.27 1.4.28.14.45.12.61-.07.17-.2.7-.81.88-1.09.19-.28.37-.23.63-.14.26.1 1.64.77 1.92.91.28.14.47.21.54.32.07.12.07.68-.17 1.36z\"/></svg>WhatsApp 客服</a></p>\n<p>工作時間：每日 <strong>10:00–19:00</strong>（香港時間）。非工作時段留言，我們會在下一工作時段回覆。</p>\n<h2>2. 社交媒體</h2>\n<p>你也可以在社交平台找到我們。繁忙時段該服務<strong>可能需要排隊</strong>，感謝耐心等候。</p>\n<p><a href=\"https://www.facebook.com/\" target=\"_blank\" rel=\"noopener noreferrer\" style=\"display:inline-flex;align-items:center;gap:0.15rem;padding:0.55rem 0.85rem;border-radius:999px;text-decoration:none;font-weight:600;background:#e8f1ff;color:#1877F2\"><svg xmlns=\"http://www.w3.org/2000/svg\" width=\"22\" height=\"22\" viewBox=\"0 0 24 24\" aria-hidden=\"true\" style=\"vertical-align:-0.35em;margin-right:0.4rem\"><path fill=\"#1877F2\" d=\"M22 12.07C22 6.48 17.52 2 11.93 2S1.86 6.48 1.86 12.07c0 5.02 3.66 9.18 8.45 9.93v-7.02H7.9v-2.91h2.41V9.84c0-2.38 1.42-3.69 3.6-3.69 1.04 0 2.13.19 2.13.19v2.34h-1.2c-1.18 0-1.55.73-1.55 1.48v1.78h2.64l-.42 2.91h-2.22V22c4.79-.75 8.45-4.91 8.45-9.93z\"/></svg>Facebook</a></p>'
FROM articles a WHERE a.code='contact'
ON DUPLICATE KEY UPDATE title=VALUES(title), body=VALUES(body);

INSERT INTO article_i18n (article_id, locale, title, body)
SELECT a.id, 'en', 'Contact Us', '<p>For shopping, orders, or after-sales questions, contact <strong>SisterMart</strong> below.</p>\n<h2>1. Live chat (WhatsApp)</h2>\n<p><a href=\"https://wa.me/85290001234\" target=\"_blank\" rel=\"noopener noreferrer\" style=\"display:inline-flex;align-items:center;gap:0.15rem;padding:0.55rem 0.85rem;border-radius:999px;text-decoration:none;font-weight:600;background:#e8f8ef;color:#128C7E\"><svg xmlns=\"http://www.w3.org/2000/svg\" width=\"22\" height=\"22\" viewBox=\"0 0 24 24\" aria-hidden=\"true\" style=\"vertical-align:-0.35em;margin-right:0.4rem\"><path fill=\"#25D366\" d=\"M12.04 2c-5.46 0-9.91 4.43-9.91 9.9 0 1.75.46 3.45 1.32 4.95L2 22l5.25-1.38c1.45.79 3.08 1.21 4.79 1.21 5.46 0 9.9-4.44 9.9-9.9C21.94 6.44 17.5 2 12.04 2zm5.76 14.02c-.24.68-1.42 1.25-1.96 1.33-.5.07-1.14.1-1.84-.12-.42-.13-.97-.32-1.67-.62-2.94-1.27-4.85-4.24-5-4.44-.14-.2-1.18-1.57-1.18-3 0-1.42.74-2.12 1.01-2.41.26-.28.58-.35.77-.35h.55c.18 0 .42-.06.66.5.24.58.82 2 .89 2.15.07.14.12.32.02.51-.1.2-.14.32-.28.5-.14.17-.3.39-.43.52-.14.14-.29.3-.12.58.16.28.73 1.2 1.57 1.95 1.08.96 1.99 1.26 2.27 1.4.28.14.45.12.61-.07.17-.2.7-.81.88-1.09.19-.28.37-.23.63-.14.26.1 1.64.77 1.92.91.28.14.47.21.54.32.07.12.07.68-.17 1.36z\"/></svg>WhatsApp support</a></p>\n<p>Hours: daily <strong>10:00–19:00</strong> (Hong Kong time). Messages outside hours will be answered in the next working window.</p>\n<h2>2. Social media</h2>\n<p>You can also find us on social media. During busy periods this channel <strong>may require queuing</strong>—thanks for your patience.</p>\n<p><a href=\"https://www.facebook.com/\" target=\"_blank\" rel=\"noopener noreferrer\" style=\"display:inline-flex;align-items:center;gap:0.15rem;padding:0.55rem 0.85rem;border-radius:999px;text-decoration:none;font-weight:600;background:#e8f1ff;color:#1877F2\"><svg xmlns=\"http://www.w3.org/2000/svg\" width=\"22\" height=\"22\" viewBox=\"0 0 24 24\" aria-hidden=\"true\" style=\"vertical-align:-0.35em;margin-right:0.4rem\"><path fill=\"#1877F2\" d=\"M22 12.07C22 6.48 17.52 2 11.93 2S1.86 6.48 1.86 12.07c0 5.02 3.66 9.18 8.45 9.93v-7.02H7.9v-2.91h2.41V9.84c0-2.38 1.42-3.69 3.6-3.69 1.04 0 2.13.19 2.13.19v2.34h-1.2c-1.18 0-1.55.73-1.55 1.48v1.78h2.64l-.42 2.91h-2.22V22c4.79-.75 8.45-4.91 8.45-9.93z\"/></svg>Facebook</a></p>'
FROM articles a WHERE a.code='contact'
ON DUPLICATE KEY UPDATE title=VALUES(title), body=VALUES(body);

INSERT INTO articles (code, status, show_in_footer, sort_order, created_at, updated_at)
SELECT 'privacy', 1, 1, 50, NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM articles WHERE code='privacy');
UPDATE articles SET status=1, show_in_footer=1, sort_order=50, updated_at=NOW() WHERE code='privacy';
INSERT INTO article_i18n (article_id, locale, title, body)
SELECT a.id, 'zh-HK', '私隱協議', '<p>SisterMart（姐妹商店）重視你的私隱。本協議說明我們如何收集、使用與保護個人資料。使用本店服務，即表示你知悉本協議內容。</p>\n<h2>我們收集的資料</h2>\n<ul>\n<li>帳戶資料：電郵、手機號碼、暱稱／頭像等</li>\n<li>訂單與配送：收件人姓名、電話、地址、訂單內容</li>\n<li>登入與驗證：驗證碼發送記錄、裝置／瀏覽的基本技術資訊</li>\n<li>客服溝通：你透過 WhatsApp 或站內訊息提供的內容</li>\n</ul>\n<h2>用途</h2>\n<ul>\n<li>建立帳戶、登入驗證（電郵或 WhatsApp）</li>\n<li>處理訂單、付款核對、配送與售後</li>\n<li>發送與訂單／帳戶相關的必要通知</li>\n<li>改善網站體驗與防止欺詐（在合理範圍內）</li>\n</ul>\n<h2>分享與保存</h2>\n<p>我們不會出售你的個人資料。僅在配送、支付或法律要求等必要情況下，與受託服務方共享完成服務所需的最少資料。資料保存至業務與法律所需期間，期後刪除或匿名化。</p>\n<h2>你的選擇</h2>\n<p>你可在帳戶設定更新資料或退出登錄。如需查閱、更正或刪除個人資料，請透過「聯絡我們」提出。我們可能不時更新本協議，更新後以本頁最新版本為準。</p>'
FROM articles a WHERE a.code='privacy'
ON DUPLICATE KEY UPDATE title=VALUES(title), body=VALUES(body);

INSERT INTO article_i18n (article_id, locale, title, body)
SELECT a.id, 'en', 'Privacy Policy', '<p>SisterMart respects your privacy. This notice explains what we collect and how we use it. Using our shop means you acknowledge this policy.</p>\n<h2>Data we collect</h2>\n<ul>\n<li>Account details: email, phone, nickname/avatar</li>\n<li>Order &amp; delivery: recipient name, phone, address, order items</li>\n<li>Sign-in &amp; verification: OTP delivery logs and basic device/browser data</li>\n<li>Support messages you send via WhatsApp or in-app chat</li>\n</ul>\n<h2>How we use it</h2>\n<ul>\n<li>Account creation and login (email or WhatsApp)</li>\n<li>Orders, payment checks, shipping, and after-sales</li>\n<li>Necessary account/order notifications</li>\n<li>Improving the site and preventing abuse, within reason</li>\n</ul>\n<h2>Sharing &amp; retention</h2>\n<p>We do not sell your personal data. We only share the minimum needed with trusted providers (e.g. delivery or payment) or when required by law. We keep data only as long as needed for business and legal purposes, then delete or anonymize it.</p>\n<h2>Your choices</h2>\n<p>You can update your profile or log out in account settings. To access, correct, or delete personal data, contact us via Contact Us. We may update this page from time to time; the latest version here applies.</p>'
FROM articles a WHERE a.code='privacy'
ON DUPLICATE KEY UPDATE title=VALUES(title), body=VALUES(body);

INSERT INTO articles (code, status, show_in_footer, sort_order, created_at, updated_at)
SELECT 'refund', 1, 1, 60, NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM articles WHERE code='refund');
UPDATE articles SET status=1, show_in_footer=1, sort_order=60, updated_at=NOW() WHERE code='refund';
INSERT INTO article_i18n (article_id, locale, title, body)
SELECT a.id, 'zh-HK', '退款政策', '<p>本政策說明 SisterMart 何時及如何退款，並與「退貨政策」一併適用。</p>\n<h2>可退款情況</h2>\n<ul>\n<li>退貨檢驗通過後</li>\n<li>付款後長時間未能出貨，且你選擇取消</li>\n<li>重複扣款、系統錯誤或多收運費等經核實的情況</li>\n</ul>\n<h2>退款方式與時間</h2>\n<ul>\n<li>原則退回<strong>原支付途徑</strong>（如信用卡／支付平台／帳戶餘額等）</li>\n<li>我們確認退款後，一般於 <strong>3–10 個工作天</strong>到帳（視銀行或支付平台而定）</li>\n<li>使用優惠券／積分的訂單，退款時可能按規則回退券額或積分，現金部分另行計算</li>\n</ul>\n<h2>部分退款</h2>\n<p>若訂單含多件商品，可只退回符合條件的部分；運費是否退還視整筆訂單是否仍達免運門檻及退貨原因而定。</p>\n<p>詳情可於「聯絡我們」查詢你的訂單編號。</p>'
FROM articles a WHERE a.code='refund'
ON DUPLICATE KEY UPDATE title=VALUES(title), body=VALUES(body);

INSERT INTO article_i18n (article_id, locale, title, body)
SELECT a.id, 'en', 'Refund Policy', '<p>This policy explains when and how SisterMart issues refunds, together with the Return Policy.</p>\n<h2>When we refund</h2>\n<ul>\n<li>After an approved return passes inspection</li>\n<li>Long delay before shipment where you choose to cancel</li>\n<li>Verified duplicate charges, system errors, or overcharged shipping</li>\n</ul>\n<h2>Method &amp; timing</h2>\n<ul>\n<li>Refunds go back to the <strong>original payment method</strong> when possible (card/wallet/balance, etc.)</li>\n<li>After we confirm, funds usually arrive in <strong>3–10 business days</strong> (bank/provider dependent)</li>\n<li>Orders with coupons/points may restore benefits by rule; cash portion is calculated separately</li>\n</ul>\n<h2>Partial refunds</h2>\n<p>For multi-item orders we may refund only eligible items. Shipping refunds depend on whether the remaining order still meets free-shipping rules and the return reason.</p>\n<p>Contact us with your order number for case-specific help.</p>'
FROM articles a WHERE a.code='refund'
ON DUPLICATE KEY UPDATE title=VALUES(title), body=VALUES(body);

INSERT INTO articles (code, status, show_in_footer, sort_order, created_at, updated_at)
SELECT 'return', 1, 1, 90, NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM articles WHERE code='return');
UPDATE articles SET status=1, show_in_footer=1, sort_order=90, updated_at=NOW() WHERE code='return';
INSERT INTO article_i18n (article_id, locale, title, body)
SELECT a.id, 'zh-HK', '退貨政策', '<p>我們希望你收到滿意的商品。以下為 SisterMart 退貨說明（簡要）。</p>\n<h2>可申請情形</h2>\n<ul>\n<li>收到商品後 <strong>7 天內</strong>（以簽收日計）</li>\n<li>商品未穿用／未使用、吊牌與包裝完整、不影響二次銷售</li>\n<li>質量問題或與頁面描述明顯不符</li>\n</ul>\n<h2>不適用／限制</h2>\n<ul>\n<li>個人衛生相關、已拆封的貼身／美容耗材等（法律或衛生原因）</li>\n<li>特價清貨、訂製或註明不可退換的商品</li>\n<li>人為損壞、洗滌後、或缺少配件</li>\n</ul>\n<h2>如何辦理</h2>\n<ol>\n<li>在訂單進入售後申請，或透過「聯絡我們」的 WhatsApp 說明訂單編號與原因</li>\n<li>按指引寄回（保留寄件證明）</li>\n<li>我們收貨檢驗後，再安排換貨或按退款政策處理</li>\n</ol>\n<p>非質量問題的退貨，來回運費一般由顧客承擔；質量問題則由我們承擔合理運費。</p>'
FROM articles a WHERE a.code='return'
ON DUPLICATE KEY UPDATE title=VALUES(title), body=VALUES(body);

INSERT INTO article_i18n (article_id, locale, title, body)
SELECT a.id, 'en', 'Return Policy', '<p>We want you to be happy with your order. Here is SisterMart’s simple return guide.</p>\n<h2>Eligible cases</h2>\n<ul>\n<li>Within <strong>7 days</strong> of delivery (signed date)</li>\n<li>Unused item with tags/packaging intact and resalable</li>\n<li>Quality issues or clear mismatch with the product page</li>\n</ul>\n<h2>Not eligible / limits</h2>\n<ul>\n<li>Hygiene-sensitive or opened intimate/beauty consumables</li>\n<li>Final sale, made-to-order, or items marked non-returnable</li>\n<li>Damage from use/washing, or missing accessories</li>\n</ul>\n<h2>How to return</h2>\n<ol>\n<li>Open an after-sales request on the order, or WhatsApp us with order no. and reason</li>\n<li>Ship back as instructed (keep the receipt)</li>\n<li>After inspection we arrange exchange or refund per the Refund Policy</li>\n</ol>\n<p>For non-quality returns, shipping is usually paid by the customer; for quality issues we cover reasonable return shipping.</p>'
FROM articles a WHERE a.code='return'
ON DUPLICATE KEY UPDATE title=VALUES(title), body=VALUES(body);

INSERT INTO shop_settings (setting_key, setting_value, updated_at)
SELECT 'cs.facebook_url', 'https://www.facebook.com/', NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM shop_settings WHERE setting_key='cs.facebook_url');
