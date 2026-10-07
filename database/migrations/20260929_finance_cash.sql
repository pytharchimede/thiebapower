CREATE TABLE cash_entries (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 direction ENUM('in','out') NOT NULL,
 amount INT UNSIGNED NOT NULL,
 category VARCHAR(80) NOT NULL,
 reference VARCHAR(100) NOT NULL UNIQUE,
 note VARCHAR(500) NOT NULL,
 occurred_at DATETIME NOT NULL,
 created_by BIGINT UNSIGNED NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 KEY cash_entries_occurred (occurred_at,id),
 FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
 CONSTRAINT cash_entries_positive CHECK (amount > 0)
);
ALTER TABLE deposit_settlements ADD COLUMN confirmed_at DATETIME NULL AFTER sent_at;
INSERT INTO role_permissions(role,permission) VALUES
 ('manager','finance.view'),('manager','finance.manage'),('auditor','finance.view');
