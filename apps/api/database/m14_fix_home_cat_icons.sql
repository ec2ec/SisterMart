-- Home top-level cat icons: leave /mock paths (some clients break cat-bracelet|necklace.png).
-- Prefer neutral /products/home-cat-*.jpg paths.
UPDATE categories SET icon = '/products/home-cat-brace.jpg', updated_at = NOW() WHERE code = 'bracelets';
UPDATE categories SET icon = '/products/home-cat-neck.jpg', updated_at = NOW() WHERE code = 'necklaces';
UPDATE categories SET icon = '/products/home-cat-earrings.jpg', updated_at = NOW() WHERE code = 'earrings';
UPDATE categories SET icon = '/products/home-cat-ring.jpg', updated_at = NOW() WHERE code = 'rings';
UPDATE categories SET icon = '/products/home-cat-scarf.jpg', updated_at = NOW() WHERE code = 'scarves';
UPDATE categories SET icon = '/products/home-cat-bag.jpg', updated_at = NOW() WHERE code = 'bags';
