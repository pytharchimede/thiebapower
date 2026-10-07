-- Existing offers stay private. Only campaign codes can be published.
ALTER TABLE promotions ADD COLUMN IF NOT EXISTS is_public TINYINT(1) NOT NULL DEFAULT 0;
