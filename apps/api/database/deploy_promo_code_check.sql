-- Promo codes: widen column for SM + digits + HMAC check letter (A-Z)
ALTER TABLE members
  MODIFY COLUMN promo_code VARCHAR(32) NOT NULL;
