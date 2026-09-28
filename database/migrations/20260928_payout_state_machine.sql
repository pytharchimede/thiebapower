-- INITIATED means accepted by initTransact but not confirmed as delivered.
-- UNKNOWN is reserved for transport errors or ambiguous/unrecognised responses.
ALTER TABLE deposit_settlements
 MODIFY COLUMN status ENUM('pending','processing','initiated','unknown','refunded','failed') NOT NULL DEFAULT 'pending';
