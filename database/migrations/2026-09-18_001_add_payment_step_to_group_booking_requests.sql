-- Migration 2026-09-18_001: add payment tracking and awaiting-payment status to group booking requests

ALTER TABLE group_booking_requests
    ADD COLUMN IF NOT EXISTS payment_intent_id VARCHAR(255);

ALTER TABLE group_booking_requests
    ADD COLUMN IF NOT EXISTS paid_at TIMESTAMPTZ;

DO $$
BEGIN
    IF EXISTS (
        SELECT 1
        FROM pg_constraint
        WHERE conname = 'group_booking_requests_status_check'
    ) THEN
        ALTER TABLE group_booking_requests
            DROP CONSTRAINT group_booking_requests_status_check;
    END IF;
END $$;

DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM pg_constraint
        WHERE conname = 'group_booking_requests_status_check'
    ) THEN
        ALTER TABLE group_booking_requests
            ADD CONSTRAINT group_booking_requests_status_check
            CHECK (status IN ('pending', 'awaiting_payment', 'confirmed', 'cancelled'));
    END IF;
END $$;
