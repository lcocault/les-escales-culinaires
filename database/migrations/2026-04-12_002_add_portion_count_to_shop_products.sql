-- Migration 2026-04-12_002: add portion_count to shop_products

BEGIN;

ALTER TABLE shop_products
    ADD COLUMN IF NOT EXISTS portion_count INTEGER NOT NULL DEFAULT 1;

DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM pg_constraint
        WHERE conname = 'chk_shop_products_portion_count_positive'
    ) THEN
        ALTER TABLE shop_products
            ADD CONSTRAINT chk_shop_products_portion_count_positive
            CHECK (portion_count > 0);
    END IF;
END $$;

COMMIT;
