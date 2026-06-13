-- Migration 2026-06-13_001: add nb_children to bookings and create booking_children table

-- 1. Add nb_children column to bookings (number of children for this booking slot).
--    Defaults to 1 for all existing bookings.
DO $$ BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_name = 'bookings' AND column_name = 'nb_children'
    ) THEN
        ALTER TABLE bookings ADD COLUMN nb_children INTEGER NOT NULL DEFAULT 1 CHECK (nb_children >= 1);
    END IF;
END $$;

-- 2. Create booking_children table for additional children (2nd, 3rd, etc.) per booking.
CREATE TABLE IF NOT EXISTS booking_children (
    id           SERIAL PRIMARY KEY,
    booking_id   INTEGER      NOT NULL REFERENCES bookings(id) ON DELETE CASCADE,
    first_name   VARCHAR(100) NOT NULL,
    last_name    VARCHAR(100) NOT NULL,
    age          INTEGER,
    allergies    TEXT,
    child_order  SMALLINT     NOT NULL DEFAULT 2,   -- 2 = second child, 3 = third, etc.
    created_at   TIMESTAMPTZ  NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_booking_children_booking_id ON booking_children(booking_id);
