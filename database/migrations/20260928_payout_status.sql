ALTER TABLE deposit_settlements ADD COLUMN provider_session_id VARCHAR(120) NULL AFTER provider_reference;
CREATE UNIQUE INDEX idx_settlements_provider_session ON deposit_settlements(provider_session_id);
