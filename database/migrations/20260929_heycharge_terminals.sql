CREATE TABLE stations (
 imei VARCHAR(120) PRIMARY KEY, iccid VARCHAR(32) NULL, label VARCHAR(160) NULL,
 status ENUM('online','offline','unknown') NOT NULL DEFAULT 'unknown',
 enabled TINYINT(1) NOT NULL DEFAULT 0, last_seen_at DATETIME NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);
ALTER TABLE batteries ADD COLUMN station_imei VARCHAR(120) NULL, ADD COLUMN slot_id VARCHAR(32) NULL,
 ADD COLUMN battery_capacity TINYINT UNSIGNED NULL, ADD COLUMN battery_abnormal TINYINT(1) NOT NULL DEFAULT 0,
 ADD COLUMN cable_abnormal TINYINT(1) NOT NULL DEFAULT 0,
 ADD CONSTRAINT fk_battery_station FOREIGN KEY (station_imei) REFERENCES stations(imei);
ALTER TABLE rentals ADD COLUMN release_command_at DATETIME NULL;
ALTER TABLE pricing ADD COLUMN deposit_enabled TINYINT(1) NOT NULL DEFAULT 1;
CREATE TABLE heycharge_events (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, event_type VARCHAR(20) NOT NULL,
 imei VARCHAR(120) NOT NULL, battery_serial VARCHAR(100) NULL, payload JSON NOT NULL,
 received_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 KEY idx_heycharge_event(imei,received_at)
);
