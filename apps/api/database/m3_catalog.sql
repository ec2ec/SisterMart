-- M3：分類、商品、SKU、媒體、文章。可重複執行。
-- mysql -usistermart -psistermart -h127.0.0.1 sistermart < apps/api/database/m3_catalog.sql

CREATE TABLE IF NOT EXISTS categories (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  parent_id BIGINT UNSIGNED NULL,
  code VARCHAR(64) NOT NULL,
  icon VARCHAR(255) NULL,
  sort_order INT NOT NULL DEFAULT 0,
  status TINYINT NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uk_categories_code (code),
  KEY idx_categories_parent (parent_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS category_i18n (
  category_id BIGINT UNSIGNED NOT NULL,
  locale VARCHAR(8) NOT NULL,
  name VARCHAR(128) NOT NULL,
  PRIMARY KEY (category_id, locale)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS products (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  code VARCHAR(64) NOT NULL,
  category_id BIGINT UNSIGNED NOT NULL,
  status TINYINT NOT NULL DEFAULT 1,
  sold_count INT UNSIGNED NOT NULL DEFAULT 0,
  sold_boost INT UNSIGNED NOT NULL DEFAULT 0,
  cover_url VARCHAR(255) NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uk_products_code (code),
  KEY idx_products_category (category_id),
  KEY idx_products_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS product_i18n (
  product_id BIGINT UNSIGNED NOT NULL,
  locale VARCHAR(8) NOT NULL,
  name VARCHAR(191) NOT NULL,
  summary VARCHAR(255) NOT NULL DEFAULT '',
  detail MEDIUMTEXT NOT NULL,
  PRIMARY KEY (product_id, locale)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS product_media (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  product_id BIGINT UNSIGNED NOT NULL,
  media_type VARCHAR(16) NOT NULL DEFAULT 'image',
  url VARCHAR(255) NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  KEY idx_product_media_product (product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS product_badges (
  product_id BIGINT UNSIGNED NOT NULL,
  badge VARCHAR(16) NOT NULL,
  PRIMARY KEY (product_id, badge)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS spec_groups (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  product_id BIGINT UNSIGNED NOT NULL,
  code VARCHAR(64) NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  KEY idx_spec_groups_product (product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS spec_group_i18n (
  spec_group_id BIGINT UNSIGNED NOT NULL,
  locale VARCHAR(8) NOT NULL,
  name VARCHAR(64) NOT NULL,
  PRIMARY KEY (spec_group_id, locale)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS spec_options (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  spec_group_id BIGINT UNSIGNED NOT NULL,
  code VARCHAR(64) NOT NULL,
  image_url VARCHAR(255) NULL,
  sort_order INT NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  KEY idx_spec_options_group (spec_group_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS spec_option_i18n (
  spec_option_id BIGINT UNSIGNED NOT NULL,
  locale VARCHAR(8) NOT NULL,
  name VARCHAR(64) NOT NULL,
  PRIMARY KEY (spec_option_id, locale)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS skus (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  product_id BIGINT UNSIGNED NOT NULL,
  code VARCHAR(64) NOT NULL,
  price DECIMAL(12,2) NOT NULL,
  compare_at DECIMAL(12,2) NOT NULL DEFAULT 0,
  agent_price DECIMAL(12,2) NOT NULL DEFAULT 0,
  cost_price DECIMAL(12,2) NOT NULL DEFAULT 0,
  stock INT NOT NULL DEFAULT 0,
  image_url VARCHAR(255) NULL,
  status TINYINT NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uk_skus_product_code (product_id, code),
  KEY idx_skus_product (product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sku_options (
  sku_id BIGINT UNSIGNED NOT NULL,
  spec_option_id BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (sku_id, spec_option_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS service_tags (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  code VARCHAR(64) NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  status TINYINT NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  UNIQUE KEY uk_service_tags_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS service_tag_i18n (
  service_tag_id BIGINT UNSIGNED NOT NULL,
  locale VARCHAR(8) NOT NULL,
  name VARCHAR(64) NOT NULL,
  PRIMARY KEY (service_tag_id, locale)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS product_service_tags (
  product_id BIGINT UNSIGNED NOT NULL,
  service_tag_id BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (product_id, service_tag_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS articles (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  code VARCHAR(64) NOT NULL,
  status TINYINT NOT NULL DEFAULT 1,
  show_in_footer TINYINT NOT NULL DEFAULT 0,
  sort_order INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uk_articles_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS article_i18n (
  article_id BIGINT UNSIGNED NOT NULL,
  locale VARCHAR(8) NOT NULL,
  title VARCHAR(191) NOT NULL,
  body MEDIUMTEXT NOT NULL,
  PRIMARY KEY (article_id, locale)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO shop_settings (setting_key, setting_value, updated_at)
SELECT 'sold.display_mode', 'fuzzy', NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM shop_settings WHERE setting_key = 'sold.display_mode');
