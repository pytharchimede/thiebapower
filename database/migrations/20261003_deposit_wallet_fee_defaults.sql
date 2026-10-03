-- Apply after 20261003_deposit_wallet.sql. Replay-safe: preserve configured fees and activation.
-- Payout costs 2% (200 basis points); internal collection -> payout transfer is free.
UPDATE deposit_wallet_settings
SET fee_rules=JSON_SET(fee_rules,'$.WAVECI',JSON_OBJECT('fixed',0,'basis_points',200))
WHERE id=1 AND JSON_EXTRACT(fee_rules,'$.WAVECI') IS NULL;
UPDATE deposit_wallet_settings
SET fee_rules=JSON_SET(fee_rules,'$.MOMOCI',JSON_OBJECT('fixed',0,'basis_points',200))
WHERE id=1 AND JSON_EXTRACT(fee_rules,'$.MOMOCI') IS NULL;
UPDATE deposit_wallet_settings
SET fee_rules=JSON_SET(fee_rules,'$.OMCIV',JSON_OBJECT('fixed',0,'basis_points',200))
WHERE id=1 AND JSON_EXTRACT(fee_rules,'$.OMCIV') IS NULL;
UPDATE deposit_wallet_settings
SET fee_rules=JSON_SET(fee_rules,'$.FLOOZ',JSON_OBJECT('fixed',0,'basis_points',200))
WHERE id=1 AND JSON_EXTRACT(fee_rules,'$.FLOOZ') IS NULL;
