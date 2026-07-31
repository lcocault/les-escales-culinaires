-- Migration 2026-04-12_001: add external image URL support to shop products

ALTER TABLE shop_products
    ADD COLUMN IF NOT EXISTS external_photo_url TEXT NULL;

DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM pg_constraint
        WHERE conname = 'chk_shop_products_single_photo_source'
    ) THEN
        ALTER TABLE shop_products
            ADD CONSTRAINT chk_shop_products_single_photo_source
            CHECK (
                NOT (
                    photo_filename IS NOT NULL
                    AND external_photo_url IS NOT NULL
                )
            );
    END IF;
END $$;
