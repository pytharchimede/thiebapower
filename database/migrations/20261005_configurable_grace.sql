-- Existing rentals retain five free minutes; future checkouts capture the configured value.
ALTER TABLE pricing ADD COLUMN IF NOT EXISTS grace_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 5;
ALTER TABLE rentals ADD COLUMN IF NOT EXISTS grace_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 5;
