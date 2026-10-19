-- M2：會員令牌、驗證碼、管理員、底欄。可重複執行。
-- mysql -usistermart -psistermart -h127.0.0.1 sistermart < apps/api/database/m2_auth_tabbar.sql

CREATE TABLE IF NOT EXISTS admin_roles (
  id TINYINT UNSIGNED NOT NULL,
  code VARCHAR(32) NOT NULL,
  name_zh VARCHAR(64) NOT NULL,
  name_en VARCHAR(64) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uk_admin_roles_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO admin_roles (id, code, name_zh, name_en)
SELECT 1, 'super', '超級管理員', 'Super admin' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM admin_roles WHERE id = 1);
INSERT INTO admin_roles (id, code, name_zh, name_en)
SELECT 2, 'ops', '運營', 'Operations' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM admin_roles WHERE id = 2);

CREATE TABLE IF NOT EXISTS admins (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  email VARCHAR(191) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  name VARCHAR(64) NOT NULL,
  role_id TINYINT UNSIGNED NOT NULL,
  status TINYINT NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uk_admins_email (email),
  KEY idx_admins_role (role_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO admins (email, password_hash, name, role_id, status, created_at, updated_at)
SELECT 'admin@sistermart.local',
       '$2y$10$N5gSxiz4nk.dWEYzDXBmc.YVN3A/Ci64l3yyk9qguizdUInzXBaqu',
       'SisterMart', 1, 1, NOW(), NOW()
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM admins WHERE email = 'admin@sistermart.local');

CREATE TABLE IF NOT EXISTS admin_tokens (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  admin_id BIGINT UNSIGNED NOT NULL,
  token_hash CHAR(64) NOT NULL,
  expires_at DATETIME NOT NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uk_admin_tokens_hash (token_hash),
  KEY idx_admin_tokens_admin (admin_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS members (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  email VARCHAR(191) NULL,
  phone VARCHAR(32) NULL,
  password_hash VARCHAR(255) NOT NULL,
  locale VARCHAR(8) NOT NULL DEFAULT 'zh-HK',
  promo_code VARCHAR(16) NOT NULL,
  parent_id BIGINT UNSIGNED NULL,
  status TINYINT NOT NULL DEFAULT 1,
  agreed_terms_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uk_members_email (email),
  UNIQUE KEY uk_members_phone (phone),
  UNIQUE KEY uk_members_promo (promo_code),
  KEY idx_members_parent (parent_id)
) ENGINE=InnoDB AUTO_INCREMENT=1001 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS member_tokens (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  member_id BIGINT UNSIGNED NOT NULL,
  token_hash CHAR(64) NOT NULL,
  expires_at DATETIME NOT NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uk_member_tokens_hash (token_hash),
  KEY idx_member_tokens_member (member_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS verify_codes (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  channel VARCHAR(16) NOT NULL,
  target VARCHAR(191) NOT NULL,
  purpose VARCHAR(16) NOT NULL,
  code_hash CHAR(64) NOT NULL,
  member_id BIGINT UNSIGNED NULL,
  attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
  expires_at DATETIME NOT NULL,
  used_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_verify_codes_lookup (target, purpose, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS shop_settings (
  setting_key VARCHAR(64) NOT NULL,
  setting_value VARCHAR(255) NOT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO shop_settings (setting_key, setting_value, updated_at)
SELECT 'tabbar.bar_bg', '#ffffff', NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM shop_settings WHERE setting_key = 'tabbar.bar_bg');

CREATE TABLE IF NOT EXISTS tabbar_items (
  slot VARCHAR(16) NOT NULL,
  label_zh VARCHAR(32) NOT NULL,
  label_en VARCHAR(32) NOT NULL,
  icon VARCHAR(255) NOT NULL,
  icon_active VARCHAR(255) NOT NULL,
  icon_bg VARCHAR(16) NOT NULL,
  icon_bg_active VARCHAR(16) NOT NULL,
  text_color VARCHAR(16) NOT NULL,
  text_color_active VARCHAR(16) NOT NULL,
  sort_order TINYINT UNSIGNED NOT NULL,
  PRIMARY KEY (slot)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO tabbar_items
  (slot, label_zh, label_en, icon, icon_active, icon_bg, icon_bg_active, text_color, text_color_active, sort_order)
SELECT 'home', '首頁', 'Home', 'home-o', 'home', '#f6f3ee', '#16162b', '#8a8378', '#16162b', 1 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM tabbar_items WHERE slot = 'home');
INSERT INTO tabbar_items
  (slot, label_zh, label_en, icon, icon_active, icon_bg, icon_bg_active, text_color, text_color_active, sort_order)
SELECT 'category', '分類', 'Category', 'apps-o', 'apps-o', '#f6f3ee', '#16162b', '#8a8378', '#16162b', 2 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM tabbar_items WHERE slot = 'category');
INSERT INTO tabbar_items
  (slot, label_zh, label_en, icon, icon_active, icon_bg, icon_bg_active, text_color, text_color_active, sort_order)
SELECT 'cart', '購物車', 'Cart', 'shopping-cart-o', 'shopping-cart', '#f6f3ee', '#16162b', '#8a8378', '#16162b', 3 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM tabbar_items WHERE slot = 'cart');
INSERT INTO tabbar_items
  (slot, label_zh, label_en, icon, icon_active, icon_bg, icon_bg_active, text_color, text_color_active, sort_order)
SELECT 'account', '我的', 'Account', 'user-o', 'user', '#f6f3ee', '#16162b', '#8a8378', '#16162b', 4 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM tabbar_items WHERE slot = 'account');

-- 已有環境：選中態改為深底 + 實心圖標，避免與未選中幾乎無差別
UPDATE tabbar_items SET icon_active = 'home', icon_bg_active = '#16162b', text_color_active = '#16162b' WHERE slot = 'home';
UPDATE tabbar_items SET icon_active = 'apps-o', icon_bg_active = '#16162b', text_color_active = '#16162b' WHERE slot = 'category';
UPDATE tabbar_items SET icon_active = 'shopping-cart', icon_bg_active = '#16162b', text_color_active = '#16162b' WHERE slot = 'cart';
UPDATE tabbar_items SET icon_active = 'user', icon_bg_active = '#16162b', text_color_active = '#16162b' WHERE slot = 'account';
