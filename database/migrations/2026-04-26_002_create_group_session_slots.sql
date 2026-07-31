-- Migration 2026-04-26_002: create group_session_slots table and link to group_booking_requests

CREATE TABLE IF NOT EXISTS group_session_slots (
    id                      SERIAL PRIMARY KEY,
    title                   VARCHAR(255)    NOT NULL DEFAULT 'Atelier anniversaire',
    description             TEXT,
    slot_date               DATE            NOT NULL,
    start_time              TIME            NOT NULL,
    end_time                TIME            NOT NULL,
    max_groups              INTEGER         NOT NULL DEFAULT 1 CHECK (max_groups > 0),
    remaining_groups        INTEGER         NOT NULL DEFAULT 1 CHECK (remaining_groups >= 0),
    price_per_child_cents   INTEGER         NOT NULL CHECK (price_per_child_cents >= 0),
    status                  VARCHAR(20)     NOT NULL DEFAULT 'open'
                                CHECK (status IN ('open', 'full', 'cancelled')),
    created_at              TIMESTAMPTZ     NOT NULL DEFAULT NOW(),
    deleted_at              TIMESTAMPTZ,
    CONSTRAINT remaining_lte_max_groups CHECK (remaining_groups <= max_groups)
);

ALTER TABLE group_booking_requests
    ADD COLUMN IF NOT EXISTS group_session_slot_id INTEGER
        REFERENCES group_session_slots(id) ON DELETE SET NULL;
