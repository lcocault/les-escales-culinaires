-- Migration 2026-06-27_001: add number_of_children to bookings and basket_items

ALTER TABLE bookings
    ADD COLUMN IF NOT EXISTS number_of_children INTEGER NOT NULL DEFAULT 1
        CHECK (number_of_children >= 1);

ALTER TABLE basket_items
    ADD COLUMN IF NOT EXISTS number_of_children INTEGER NOT NULL DEFAULT 1
        CHECK (number_of_children >= 1);
