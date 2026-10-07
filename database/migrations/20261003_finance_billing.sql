-- Apply once before deploying this release. Existing rentals retain their original contract.
ALTER TABLE rentals ADD COLUMN billing_rule VARCHAR(24) NOT NULL DEFAULT 'legacy_hourly', ADD COLUMN returned_station VARCHAR(120) NULL, ADD COLUMN returned_slot VARCHAR(32) NULL;
CREATE TABLE finance_withdrawals (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 reference VARCHAR(80) NOT NULL UNIQUE,
 request_token CHAR(32) NOT NULL UNIQUE,
 amount INT UNSIGNED NOT NULL,
 recipient_phone VARCHAR(20) NOT NULL,
 recipient_channel VARCHAR(16) NOT NULL,
 recipient_name VARCHAR(120) NOT NULL,
 reason VARCHAR(250) NOT NULL,
 status VARCHAR(20) NOT NULL DEFAULT 'unknown',
 provider_session_id VARCHAR(120) NULL,
 created_by BIGINT UNSIGNED NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 confirmed_at DATETIME NULL,
 provider_message VARCHAR(250) NULL,
 INDEX(status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- Preserve consultation rights previously implied by management rights.
INSERT IGNORE INTO role_permissions(role,permission)
 SELECT role,'rentals.view' FROM role_permissions WHERE permission='rentals.manage';
INSERT IGNORE INTO role_permissions(role,permission)
 SELECT role,'stations.view' FROM role_permissions WHERE permission='fleet.manage';
INSERT IGNORE INTO role_permissions(role,permission)
 SELECT role,'batteries.view' FROM role_permissions WHERE permission='fleet.manage';
INSERT IGNORE INTO role_permissions(role,permission)
 SELECT role,'labels.view' FROM role_permissions WHERE permission='fleet.manage';
INSERT IGNORE INTO role_permissions(role,permission)
 SELECT role,'pricing.view' FROM role_permissions WHERE permission='pricing.manage';
INSERT IGNORE INTO role_permissions(role,permission)
 SELECT role,'rentals.cancel' FROM role_permissions WHERE permission='rentals.manage';
-- New export, manual release and withdrawal rights start disabled for non-owners.
