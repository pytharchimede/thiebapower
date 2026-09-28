ALTER TABLE payment_lab_operations
 MODIFY COLUMN status ENUM('created','initiated','notification_unverified','processing','succeeded','failed','unknown','archived') NOT NULL DEFAULT 'created',
 ADD COLUMN archived_at DATETIME NULL,
 ADD COLUMN archive_note VARCHAR(250) NULL;
