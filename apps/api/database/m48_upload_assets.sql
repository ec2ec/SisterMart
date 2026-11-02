-- M48: upload_assets — index of new uploads only (no file move).
-- Runtime also creates this table on first upload or overview load.

CREATE TABLE IF NOT EXISTS `upload_assets` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `path` VARCHAR(191) NOT NULL,
  `domain` VARCHAR(32) NOT NULL,
  `asset_type` VARCHAR(32) NOT NULL,
  `entity_type` VARCHAR(32) NULL,
  `entity_id` BIGINT UNSIGNED NULL,
  `uploader_type` VARCHAR(16) NOT NULL,
  `uploader_id` BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `bytes` INT UNSIGNED NOT NULL DEFAULT 0,
  `width` INT UNSIGNED NULL,
  `height` INT UNSIGNED NULL,
  `status` VARCHAR(16) NOT NULL DEFAULT 'active',
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_upload_assets_path` (`path`),
  KEY `idx_upload_assets_type` (`domain`, `asset_type`),
  KEY `idx_upload_assets_uploader` (`uploader_type`, `uploader_id`),
  KEY `idx_upload_assets_status` (`status`, `id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SELECT 'm48_upload_assets ok' AS result;
