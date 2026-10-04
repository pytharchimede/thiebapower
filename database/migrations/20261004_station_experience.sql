-- Additive: does not modify rental/payment states or HeyCharge identifiers.
CREATE TABLE IF NOT EXISTS station_profiles (
 station_imei VARCHAR(120) PRIMARY KEY,
 address VARCHAR(500) NOT NULL DEFAULT '', latitude DECIMAL(10,7) NULL, longitude DECIMAL(10,7) NULL,
 venue_type VARCHAR(80) NOT NULL DEFAULT '', opening_hours VARCHAR(250) NOT NULL DEFAULT '',
 manager_name VARCHAR(160) NOT NULL DEFAULT '', manager_phone VARCHAR(30) NOT NULL DEFAULT '',
 manager_email VARCHAR(190) NOT NULL DEFAULT '', manager_notes TEXT NULL,
 investment INT UNSIGNED NOT NULL DEFAULT 0,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 FOREIGN KEY (station_imei) REFERENCES stations(imei)
);
CREATE TABLE IF NOT EXISTS station_costs (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, request_token CHAR(32) NOT NULL UNIQUE, station_imei VARCHAR(120) NOT NULL,
 category ENUM('payment_fee','maintenance','venue','other') NOT NULL, amount INT UNSIGNED NOT NULL,
 description VARCHAR(250) NOT NULL, occurred_on DATE NOT NULL, created_by BIGINT UNSIGNED NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 KEY idx_station_costs(station_imei,occurred_on), FOREIGN KEY (station_imei) REFERENCES stations(imei)
);
CREATE TABLE IF NOT EXISTS rental_support_requests (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, rental_id BIGINT UNSIGNED NOT NULL,
 issue ENUM('release','return','payment','other') NOT NULL,
 message VARCHAR(2000) NOT NULL, status ENUM('open','resolved') NOT NULL DEFAULT 'open',
 resolution VARCHAR(2000) NULL, resolved_by BIGINT UNSIGNED NULL, resolved_at DATETIME NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 KEY idx_support_rental(rental_id,status), FOREIGN KEY(rental_id) REFERENCES rentals(id)
);
CREATE TABLE IF NOT EXISTS promotions (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, code VARCHAR(40) NOT NULL UNIQUE,
 kind ENUM('campaign','referral','loyalty') NOT NULL DEFAULT 'campaign',
 discount_amount INT UNSIGNED NOT NULL, minimum_completed INT UNSIGNED NOT NULL DEFAULT 0,
 max_uses INT UNSIGNED NOT NULL, starts_at DATETIME NOT NULL, ends_at DATETIME NOT NULL,
 enabled TINYINT(1) NOT NULL DEFAULT 0, referrer_rental_id BIGINT UNSIGNED NULL,
 created_by BIGINT UNSIGNED NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(referrer_rental_id) REFERENCES rentals(id)
);
CREATE TABLE IF NOT EXISTS rental_promotion_redemptions (
 rental_id BIGINT UNSIGNED PRIMARY KEY, promotion_id BIGINT UNSIGNED NOT NULL,
 phone_key CHAR(64) NOT NULL, original_fee INT UNSIGNED NOT NULL, discount_amount INT UNSIGNED NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY uniq_promotion_phone(promotion_id,phone_key),
 FOREIGN KEY(rental_id) REFERENCES rentals(id), FOREIGN KEY(promotion_id) REFERENCES promotions(id)
);
