ALTER TABLE rental_promotion_redemptions ADD COLUMN IF NOT EXISTS discount_target VARCHAR(20) NOT NULL DEFAULT 'rental_fee';
ALTER TABLE rental_promotion_redemptions ADD COLUMN IF NOT EXISTS original_deposit INT UNSIGNED NULL;
