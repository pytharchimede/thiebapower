-- Run once after 20261003_finance_billing.sql. Existing rentals are not auto-funded.
CREATE TABLE deposit_wallet_settings (
 id TINYINT UNSIGNED PRIMARY KEY,
 enabled TINYINT NOT NULL DEFAULT 0,
 fee_rules TEXT NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
INSERT INTO deposit_wallet_settings(id,enabled,fee_rules) VALUES(1,0,'{}');
CREATE TABLE deposit_wallet_transfers (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 rental_id BIGINT UNSIGNED NULL,
 request_key VARCHAR(64) NOT NULL,
 purpose VARCHAR(16) NOT NULL,
 deposit_amount BIGINT NOT NULL DEFAULT 0,
 fee_reserve BIGINT NOT NULL DEFAULT 0,
 amount BIGINT NOT NULL,
 status VARCHAR(20) NOT NULL DEFAULT 'pending',
 http_status INT NULL,
 response_summary TEXT NULL,
 confirmation_proof VARCHAR(240) NULL,
 created_by BIGINT UNSIGNED NULL,
 confirmed_by BIGINT UNSIGNED NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 sent_at DATETIME NULL,
 confirmed_at DATETIME NULL,
 UNIQUE KEY uq_wallet_rental(rental_id),
 UNIQUE KEY uq_wallet_request(request_key),
 KEY ix_wallet_status(status,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
ALTER TABLE rentals ADD COLUMN deposit_payment_verified_at DATETIME NULL;
ALTER TABLE deposit_settlements ADD COLUMN payout_fee_reserve BIGINT NOT NULL DEFAULT 0;
