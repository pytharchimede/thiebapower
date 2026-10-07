-- Run once; do not infer historical payment channels from old refund preferences.
ALTER TABLE rentals
 ADD COLUMN payment_channel VARCHAR(32) NULL,
 ADD COLUMN payment_channel_source VARCHAR(32) NULL;
