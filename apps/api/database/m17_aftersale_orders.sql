-- M17：售後窗口天數 + 客服 WhatsApp（冪等由 seed 保證）
INSERT INTO shop_settings (setting_key, setting_value, updated_at)
SELECT 'aftersale.window_days', '30', NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM shop_settings WHERE setting_key = 'aftersale.window_days');

INSERT INTO shop_settings (setting_key, setting_value, updated_at)
SELECT 'cs.whatsapp', '85290001234', NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM shop_settings WHERE setting_key = 'cs.whatsapp');
