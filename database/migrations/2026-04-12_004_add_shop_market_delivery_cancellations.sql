-- Migration 2026-04-12_004: add cancellation table for candidate market delivery dates

CREATE TABLE IF NOT EXISTS shop_market_delivery_cancellations (
    id            SERIAL PRIMARY KEY,
    delivery_date DATE        NOT NULL UNIQUE,
    created_at    TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
