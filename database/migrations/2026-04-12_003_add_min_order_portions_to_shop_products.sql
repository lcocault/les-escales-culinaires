-- Migration 2026-04-12_003: add min_order_portions to shop_products

BEGIN;

ALTER TABLE shop_products
    ADD COLUMN IF NOT EXISTS min_order_portions INTEGER NOT NULL DEFAULT 1;

DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM pg_constraint
        WHERE conname = 'chk_shop_products_min_order_portions_positive'
    ) THEN
        ALTER TABLE shop_products
            ADD CONSTRAINT chk_shop_products_min_order_portions_positive
            CHECK (min_order_portions > 0);
    END IF;
END $$;

COMMIT;
