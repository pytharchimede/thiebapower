CREATE TABLE users (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 username VARCHAR(80) NOT NULL UNIQUE,
 display_name VARCHAR(160) NOT NULL,
 password_hash VARCHAR(255) NOT NULL,
 role ENUM('owner','manager','operator','auditor') NOT NULL,
 is_active TINYINT(1) NOT NULL DEFAULT 1,
 last_login_at DATETIME NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
CREATE TABLE role_permissions (
 role ENUM('manager','operator','auditor') NOT NULL,
 permission VARCHAR(64) NOT NULL,
 PRIMARY KEY (role, permission)
);
INSERT INTO role_permissions(role, permission) VALUES
 ('manager','dashboard.view'),('manager','pricing.manage'),('manager','fleet.manage'),('manager','integrations.manage'),('manager','rentals.manage'),('manager','payout.view'),('manager','payout.send'),('manager','audit.view'),
 ('operator','dashboard.view'),('operator','rentals.manage'),('operator','fleet.manage'),
 ('auditor','dashboard.view'),('auditor','payout.view'),('auditor','audit.view');
CREATE TABLE audit_events (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 request_id CHAR(32) NOT NULL,
 actor_id BIGINT UNSIGNED NULL,
 action VARCHAR(100) NOT NULL,
 subject_type VARCHAR(80) NULL,
 subject_id VARCHAR(120) NULL,
 details JSON NULL,
 ip_address VARCHAR(45) NULL,
 occurred_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 KEY audit_events_actor_time(actor_id, occurred_at),
 KEY audit_events_action_time(action, occurred_at),
 KEY audit_events_request(request_id),
 FOREIGN KEY (actor_id) REFERENCES users(id) ON DELETE SET NULL
);
CREATE TABLE request_events (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 request_id CHAR(32) NOT NULL UNIQUE,
 actor_id BIGINT UNSIGNED NULL,
 session_fingerprint CHAR(64) NULL,
 method VARCHAR(12) NOT NULL,
 path VARCHAR(250) NOT NULL,
 status_code SMALLINT UNSIGNED NOT NULL,
 ip_address VARCHAR(45) NULL,
 user_agent VARCHAR(255) NULL,
 referer VARCHAR(500) NULL,
 duration_ms INT UNSIGNED NOT NULL,
 occurred_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 KEY request_events_time(occurred_at),
 KEY request_events_actor_time(actor_id,occurred_at),
 FOREIGN KEY (actor_id) REFERENCES users(id) ON DELETE SET NULL
);
CREATE TABLE auth_attempts (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 username VARCHAR(80) NOT NULL,
 ip_address VARCHAR(45) NOT NULL,
 successful TINYINT(1) NOT NULL,
 occurred_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 KEY auth_attempts_lookup(username,ip_address,occurred_at)
);
