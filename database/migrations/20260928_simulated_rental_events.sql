CREATE TABLE rental_simulation_events (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 rental_id BIGINT UNSIGNED NOT NULL,
 event_type ENUM('release','return') NOT NULL,
 provider_proof VARCHAR(120) NULL UNIQUE,
 created_at DATETIME NOT NULL,
 UNIQUE KEY unique_simulation_event (rental_id,event_type),
 FOREIGN KEY (rental_id) REFERENCES rentals(id)
);
