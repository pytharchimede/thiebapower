-- Preserve all checkout discount history; expired unpaid attempts no longer consume limits.
ALTER TABLE promotions ADD COLUMN IF NOT EXISTS max_uses_per_phone INT UNSIGNED NOT NULL DEFAULT 1;
ALTER TABLE rental_promotion_redemptions ADD INDEX IF NOT EXISTS idx_promotion_phone (promotion_id,phone_key);
ALTER TABLE rental_promotion_redemptions DROP INDEX IF EXISTS uniq_promotion_phone;
