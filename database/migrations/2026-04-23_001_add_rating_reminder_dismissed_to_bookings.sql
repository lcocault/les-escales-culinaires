-- Migration 2026-04-23_001: add rating_reminder_dismissed column to bookings

ALTER TABLE bookings
    ADD COLUMN IF NOT EXISTS rating_reminder_dismissed BOOLEAN NOT NULL DEFAULT FALSE;
