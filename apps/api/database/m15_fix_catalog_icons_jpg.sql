-- Catalog 一級分類圖標：PNG → JPG（/mock/cat-*.jpg）
UPDATE categories SET icon = '/mock/cat-bracelet.jpg', updated_at = NOW() WHERE code = 'bracelets';
UPDATE categories SET icon = '/mock/cat-necklace.jpg', updated_at = NOW() WHERE code = 'necklaces';
UPDATE categories SET icon = '/mock/cat-earrings.jpg', updated_at = NOW() WHERE code = 'earrings';
UPDATE categories SET icon = '/mock/cat-ring.jpg', updated_at = NOW() WHERE code = 'rings';
UPDATE categories SET icon = '/mock/cat-headscarf.jpg', updated_at = NOW() WHERE code = 'scarves';
UPDATE categories SET icon = '/mock/cat-bag.jpg', updated_at = NOW() WHERE code = 'bags';
