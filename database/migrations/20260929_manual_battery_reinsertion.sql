ALTER TABLE manual_release_commands
 MODIFY COLUMN status ENUM('requested','unknown','confirmed','reinserted') NOT NULL DEFAULT 'requested';
