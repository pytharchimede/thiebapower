-- Existing stations inherit the current global price; rental snapshots are unchanged.
CREATE TABLE IF NOT EXISTS station_pricing (
 station_imei VARCHAR(120) PRIMARY KEY,
 rental_fee INT UNSIGNED NOT NULL,
 default_deposit INT UNSIGNED NOT NULL,
 duration_minutes INT UNSIGNED NOT NULL,
 late_percent TINYINT UNSIGNED NOT NULL,
 deposit_enabled TINYINT(1) NOT NULL,
 grace_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 5,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 FOREIGN KEY (station_imei) REFERENCES stations(imei)
);
