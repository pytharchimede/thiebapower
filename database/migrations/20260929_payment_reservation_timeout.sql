ALTER TABLE rentals MODIFY COLUMN status ENUM('pending_payment','paid','releasing','active','returned','payment_failed','payment_timeout','release_failed','payment_review') NOT NULL;
