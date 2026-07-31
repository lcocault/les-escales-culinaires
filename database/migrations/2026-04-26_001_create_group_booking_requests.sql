-- Migration 2026-04-26_001: create group_booking_requests table for birthday party / private group sessions

CREATE TABLE IF NOT EXISTS group_booking_requests (
    id               SERIAL PRIMARY KEY,
    user_id          INTEGER      NOT NULL REFERENCES users(id) ON DELETE RESTRICT,
    contact_phone    VARCHAR(50),
    nb_children      INTEGER      NOT NULL CHECK (nb_children BETWEEN 4 AND 8),
    children_ages    TEXT,                      -- free-text description of the children's ages
    preferred_date   DATE         NOT NULL,     -- must be at least 7 days in the future at submission time
    location_type    VARCHAR(10)  NOT NULL CHECK (location_type IN ('home', 'escales')),
    location_address TEXT,                      -- required when location_type = 'home'
    allergies        TEXT,
    additional_info  TEXT,
    status           VARCHAR(20)  NOT NULL DEFAULT 'pending'
                         CHECK (status IN ('pending', 'confirmed', 'cancelled')),
    admin_notes      TEXT,
    created_at       TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
    deleted_at       TIMESTAMPTZ
);
