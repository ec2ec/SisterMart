-- 選中 Tab 點亮：深色圓底 + 實心圖標（可重複執行）
UPDATE tabbar_items SET icon_active = 'home', icon_bg_active = '#16162b', text_color_active = '#16162b' WHERE slot = 'home';
UPDATE tabbar_items SET icon_active = 'apps-o', icon_bg_active = '#16162b', text_color_active = '#16162b' WHERE slot = 'category';
UPDATE tabbar_items SET icon_active = 'shopping-cart', icon_bg_active = '#16162b', text_color_active = '#16162b' WHERE slot = 'cart';
UPDATE tabbar_items SET icon_active = 'user', icon_bg_active = '#16162b', text_color_active = '#16162b' WHERE slot = 'account';
