-- 分類頁手鏈/項鏈子類圖標補齊（設計稿 /products/sub-*.jpg 已存在）
UPDATE categories SET icon = '/products/sub-gold-bead.jpg', updated_at = NOW() WHERE code = 'b-gold' AND (icon IS NULL OR icon = '');
UPDATE categories SET icon = '/products/sub-pearl.jpg', updated_at = NOW() WHERE code = 'n-pearl' AND (icon IS NULL OR icon = '');
UPDATE categories SET icon = '/products/sub-clavicle.jpg', updated_at = NOW() WHERE code = 'n-clavicle' AND (icon IS NULL OR icon = '');
UPDATE categories SET icon = '/products/sub-heart-neck.jpg', updated_at = NOW() WHERE code = 'n-heart' AND (icon IS NULL OR icon = '');
