-- M17：售後窗口天數 + 客服 WhatsApp（冪等由 seed 保證）
INSERT INTO shop_settings (setting_key, setting_value, updated_at)
SELECT 'aftersale.window_days', '7', NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM shop_settings WHERE setting_key = 'aftersale.window_days');
UPDATE shop_settings SET setting_value = '7', updated_at = NOW()
WHERE setting_key = 'aftersale.window_days' AND setting_value IN ('15', '30');

INSERT INTO shop_settings (setting_key, setting_value, updated_at)
SELECT 'cs.whatsapp', '85290001234', NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM shop_settings WHERE setting_key = 'cs.whatsapp');
