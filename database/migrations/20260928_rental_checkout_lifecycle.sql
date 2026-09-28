ALTER TABLE rentals ADD COLUMN station_code VARCHAR(120) NULL AFTER battery_id;
ALTER TABLE rentals ADD COLUMN payment_environment VARCHAR(20) NULL AFTER station_code;
ALTER TABLE rentals ADD COLUMN payment_session_id VARCHAR(160) NULL AFTER status;
ALTER TABLE rentals ADD COLUMN reservation_expires_at DATETIME NULL AFTER payment_session_id;
CREATE UNIQUE INDEX idx_rentals_payment_session ON rentals(payment_session_id);
