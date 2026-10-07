ALTER TABLE batteries MODIFY COLUMN status ENUM('available','reserved','rented','maintenance','charging') NOT NULL DEFAULT 'available';
