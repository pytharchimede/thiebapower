CREATE TABLE manual_release_commands (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 battery_id BIGINT UNSIGNED NOT NULL,
 station_imei VARCHAR(120) NOT NULL,
 battery_serial VARCHAR(100) NOT NULL,
 slot_id VARCHAR(32) NOT NULL,
 status ENUM('requested','unknown','confirmed') NOT NULL DEFAULT 'requested',
 requested_by BIGINT UNSIGNED NULL,
 requested_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 confirmed_at DATETIME NULL,
 KEY idx_manual_release_open(station_imei,status),
 FOREIGN KEY (battery_id) REFERENCES batteries(id),
 FOREIGN KEY (requested_by) REFERENCES users(id) ON DELETE SET NULL
);
