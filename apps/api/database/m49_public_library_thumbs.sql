-- M49: public library tags + thumb_path on upload_assets
-- Prefer letting UploadAssetService::ensureSchema() add columns at runtime.
-- Manual (ignore duplicate-column errors if already applied):

-- ALTER TABLE `upload_assets` ADD COLUMN `thumb_path` VARCHAR(191) NULL AFTER `path`;
-- ALTER TABLE `upload_assets` ADD COLUMN `tags_json` TEXT NULL AFTER `height`;

SELECT 'm49_public_library_thumbs ok (runtime schema)' AS result;
