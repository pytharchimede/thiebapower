CREATE TABLE payout_api_events (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 reference VARCHAR(120) NOT NULL,
 source ENUM('init','status','callback','error') NOT NULL,
 response JSON NOT NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 KEY payout_api_events_reference (reference,created_at)
);
