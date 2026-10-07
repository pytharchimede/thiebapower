-- PaiementPro Côte d'Ivoire channel policy. Replay-safe.
ALTER TABLE deposit_wallet_settings
 ADD COLUMN IF NOT EXISTS payment_channels TEXT NULL AFTER fee_rules,
 ADD COLUMN IF NOT EXISTS payout_channels TEXT NULL AFTER payment_channels;

UPDATE deposit_wallet_settings
SET payment_channels=COALESCE(payment_channels,'["WAVECI","OMCIV","MOMOCI","FLOOZ"]'),
    payout_channels=COALESCE(payout_channels,'["WAVECI"]')
WHERE id=1;
