-- Expand shop_settings.setting_value for JSON configs (registration welcome HTML, etc.)
ALTER TABLE shop_settings
  MODIFY COLUMN setting_value MEDIUMTEXT NOT NULL;
