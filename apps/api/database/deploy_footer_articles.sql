-- 頁腳 9 欄文章種子（可重跑；已存在則只確保上架／頁腳展示／排序，不覆蓋正文）
-- mysql -uUSER -pPASSWORD DBNAME < apps/api/database/deploy_footer_articles.sql
--
-- 佈局（三欄）：
--   about / terms / shipping
--   contact / privacy / refund
--   service / payment / return

INSERT INTO articles (code, status, show_in_footer, sort_order, created_at, updated_at)
SELECT 'about', 1, 1, 10, NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM articles WHERE code = 'about');

INSERT INTO articles (code, status, show_in_footer, sort_order, created_at, updated_at)
SELECT 'terms', 1, 1, 20, NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM articles WHERE code = 'terms');

INSERT INTO articles (code, status, show_in_footer, sort_order, created_at, updated_at)
SELECT 'shipping', 1, 1, 30, NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM articles WHERE code = 'shipping');

INSERT INTO articles (code, status, show_in_footer, sort_order, created_at, updated_at)
SELECT 'contact', 1, 1, 40, NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM articles WHERE code = 'contact');

INSERT INTO articles (code, status, show_in_footer, sort_order, created_at, updated_at)
SELECT 'privacy', 1, 1, 50, NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM articles WHERE code = 'privacy');

INSERT INTO articles (code, status, show_in_footer, sort_order, created_at, updated_at)
SELECT 'refund', 1, 1, 60, NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM articles WHERE code = 'refund');

INSERT INTO articles (code, status, show_in_footer, sort_order, created_at, updated_at)
SELECT 'service', 1, 1, 70, NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM articles WHERE code = 'service');

INSERT INTO articles (code, status, show_in_footer, sort_order, created_at, updated_at)
SELECT 'payment', 1, 1, 80, NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM articles WHERE code = 'payment');

INSERT INTO articles (code, status, show_in_footer, sort_order, created_at, updated_at)
SELECT 'return', 1, 1, 90, NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM articles WHERE code = 'return');

-- 確保頁腳 9 篇開啟展示
UPDATE articles
SET status = 1, show_in_footer = 1, updated_at = NOW()
WHERE code IN ('about','terms','shipping','contact','privacy','refund','service','payment','return');

UPDATE articles SET sort_order = 10 WHERE code = 'about';
UPDATE articles SET sort_order = 20 WHERE code = 'terms';
UPDATE articles SET sort_order = 30 WHERE code = 'shipping';
UPDATE articles SET sort_order = 40 WHERE code = 'contact';
UPDATE articles SET sort_order = 50 WHERE code = 'privacy';
UPDATE articles SET sort_order = 60 WHERE code = 'refund';
UPDATE articles SET sort_order = 70 WHERE code = 'service';
UPDATE articles SET sort_order = 80 WHERE code = 'payment';
UPDATE articles SET sort_order = 90 WHERE code = 'return';

-- 缺 i18n 時補預設標題／占位正文（不覆蓋已有內容）
INSERT INTO article_i18n (article_id, locale, title, body)
SELECT a.id, 'zh-HK', '關於我們', '請在後台「文章」編輯此頁內容。'
FROM articles a
WHERE a.code = 'about'
  AND NOT EXISTS (SELECT 1 FROM article_i18n i WHERE i.article_id = a.id AND i.locale = 'zh-HK');
INSERT INTO article_i18n (article_id, locale, title, body)
SELECT a.id, 'en', 'About Us', 'Please edit this page in admin CMS.'
FROM articles a
WHERE a.code = 'about'
  AND NOT EXISTS (SELECT 1 FROM article_i18n i WHERE i.article_id = a.id AND i.locale = 'en');

INSERT INTO article_i18n (article_id, locale, title, body)
SELECT a.id, 'zh-HK', '使用條款', '請在後台「文章」編輯此頁內容。'
FROM articles a WHERE a.code = 'terms'
  AND NOT EXISTS (SELECT 1 FROM article_i18n i WHERE i.article_id = a.id AND i.locale = 'zh-HK');
INSERT INTO article_i18n (article_id, locale, title, body)
SELECT a.id, 'en', 'Terms of Use', 'Please edit this page in admin CMS.'
FROM articles a WHERE a.code = 'terms'
  AND NOT EXISTS (SELECT 1 FROM article_i18n i WHERE i.article_id = a.id AND i.locale = 'en');

INSERT INTO article_i18n (article_id, locale, title, body)
SELECT a.id, 'zh-HK', '運送政策', '請在後台「文章」編輯此頁內容。'
FROM articles a WHERE a.code = 'shipping'
  AND NOT EXISTS (SELECT 1 FROM article_i18n i WHERE i.article_id = a.id AND i.locale = 'zh-HK');
INSERT INTO article_i18n (article_id, locale, title, body)
SELECT a.id, 'en', 'Shipping Policy', 'Please edit this page in admin CMS.'
FROM articles a WHERE a.code = 'shipping'
  AND NOT EXISTS (SELECT 1 FROM article_i18n i WHERE i.article_id = a.id AND i.locale = 'en');

INSERT INTO article_i18n (article_id, locale, title, body)
SELECT a.id, 'zh-HK', '聯絡我們', '請在後台「文章」編輯此頁內容。'
FROM articles a WHERE a.code = 'contact'
  AND NOT EXISTS (SELECT 1 FROM article_i18n i WHERE i.article_id = a.id AND i.locale = 'zh-HK');
INSERT INTO article_i18n (article_id, locale, title, body)
SELECT a.id, 'en', 'Contact Us', 'Please edit this page in admin CMS.'
FROM articles a WHERE a.code = 'contact'
  AND NOT EXISTS (SELECT 1 FROM article_i18n i WHERE i.article_id = a.id AND i.locale = 'en');

INSERT INTO article_i18n (article_id, locale, title, body)
SELECT a.id, 'zh-HK', '私隱政策', '請在後台「文章」編輯此頁內容。'
FROM articles a WHERE a.code = 'privacy'
  AND NOT EXISTS (SELECT 1 FROM article_i18n i WHERE i.article_id = a.id AND i.locale = 'zh-HK');
INSERT INTO article_i18n (article_id, locale, title, body)
SELECT a.id, 'en', 'Privacy Policy', 'Please edit this page in admin CMS.'
FROM articles a WHERE a.code = 'privacy'
  AND NOT EXISTS (SELECT 1 FROM article_i18n i WHERE i.article_id = a.id AND i.locale = 'en');

INSERT INTO article_i18n (article_id, locale, title, body)
SELECT a.id, 'zh-HK', '退款政策', '請在後台「文章」編輯此頁內容。'
FROM articles a WHERE a.code = 'refund'
  AND NOT EXISTS (SELECT 1 FROM article_i18n i WHERE i.article_id = a.id AND i.locale = 'zh-HK');
INSERT INTO article_i18n (article_id, locale, title, body)
SELECT a.id, 'en', 'Refund Policy', 'Please edit this page in admin CMS.'
FROM articles a WHERE a.code = 'refund'
  AND NOT EXISTS (SELECT 1 FROM article_i18n i WHERE i.article_id = a.id AND i.locale = 'en');

INSERT INTO article_i18n (article_id, locale, title, body)
SELECT a.id, 'zh-HK', '客戶服務', '請在後台「文章」編輯此頁內容。'
FROM articles a WHERE a.code = 'service'
  AND NOT EXISTS (SELECT 1 FROM article_i18n i WHERE i.article_id = a.id AND i.locale = 'zh-HK');
INSERT INTO article_i18n (article_id, locale, title, body)
SELECT a.id, 'en', 'Customer Service', 'Please edit this page in admin CMS.'
FROM articles a WHERE a.code = 'service'
  AND NOT EXISTS (SELECT 1 FROM article_i18n i WHERE i.article_id = a.id AND i.locale = 'en');

INSERT INTO article_i18n (article_id, locale, title, body)
SELECT a.id, 'zh-HK', '付款政策', '請在後台「文章」編輯此頁內容。'
FROM articles a WHERE a.code = 'payment'
  AND NOT EXISTS (SELECT 1 FROM article_i18n i WHERE i.article_id = a.id AND i.locale = 'zh-HK');
INSERT INTO article_i18n (article_id, locale, title, body)
SELECT a.id, 'en', 'Payment Policy', 'Please edit this page in admin CMS.'
FROM articles a WHERE a.code = 'payment'
  AND NOT EXISTS (SELECT 1 FROM article_i18n i WHERE i.article_id = a.id AND i.locale = 'en');

INSERT INTO article_i18n (article_id, locale, title, body)
SELECT a.id, 'zh-HK', '退貨政策', '請在後台「文章」編輯此頁內容。'
FROM articles a WHERE a.code = 'return'
  AND NOT EXISTS (SELECT 1 FROM article_i18n i WHERE i.article_id = a.id AND i.locale = 'zh-HK');
INSERT INTO article_i18n (article_id, locale, title, body)
SELECT a.id, 'en', 'Return Policy', 'Please edit this page in admin CMS.'
FROM articles a WHERE a.code = 'return'
  AND NOT EXISTS (SELECT 1 FROM article_i18n i WHERE i.article_id = a.id AND i.locale = 'en');

SELECT code, status, show_in_footer, sort_order FROM articles
WHERE code IN ('about','terms','shipping','contact','privacy','refund','service','payment','return')
ORDER BY sort_order;

SELECT 'deploy_footer_articles ok' AS result;
