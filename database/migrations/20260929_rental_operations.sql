ALTER TABLE rentals MODIFY COLUMN status ENUM('pending_payment','paid','releasing','active','returned','payment_failed','release_failed','payment_review') NOT NULL;
ALTER TABLE rentals ADD COLUMN last_station_check_at DATETIME NULL;
CREATE INDEX idx_rentals_status_created ON rentals(status,created_at);
CREATE TABLE service_heartbeats (name VARCHAR(64) PRIMARY KEY,last_run_at DATETIME NOT NULL);
