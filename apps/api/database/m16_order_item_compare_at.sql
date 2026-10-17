-- 訂單明細快照原價/劃線價（訂單詳情展示用）
ALTER TABLE order_items
  ADD COLUMN compare_at DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER unit_price;
