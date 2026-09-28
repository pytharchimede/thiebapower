ALTER TABLE rentals ADD COLUMN payout_channel VARCHAR(20) NULL AFTER customer_phone;
ALTER TABLE deposit_settlements MODIFY COLUMN status ENUM('pending','processing','unknown','refunded','failed') NOT NULL DEFAULT 'pending';
ALTER TABLE deposit_settlements ADD COLUMN sent_at DATETIME NULL AFTER provider_reference;
