-- Migration 2026-04-07_001: create shop_products, shop_orders, shop_order_items tables

-- Products (prepared meals catalog) ----------------------------------
CREATE TABLE IF NOT EXISTS shop_products (
    id             SERIAL PRIMARY KEY,
    name           VARCHAR(255) NOT NULL,
    description    TEXT,
    photo_filename VARCHAR(255),
    price_cents    INTEGER      NOT NULL CHECK (price_cents >= 0),
    is_available   BOOLEAN      NOT NULL DEFAULT TRUE,
    created_at     TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
    deleted_at     TIMESTAMPTZ
);

-- Orders --------------------------------------------------------------
CREATE TABLE IF NOT EXISTS shop_orders (
    id                SERIAL PRIMARY KEY,
    user_id           INTEGER      NOT NULL REFERENCES users(id) ON DELETE RESTRICT,
    status            VARCHAR(20)  NOT NULL DEFAULT 'pending'
                          CHECK (status IN ('pending', 'paid', 'prepared', 'delivered', 'cancelled')),
    delivery_method   VARCHAR(30)  NOT NULL
                          CHECK (delivery_method IN ('home', 'market_wednesday', 'market_friday', 'shop')),
    delivery_date     DATE         NOT NULL,
    delivery_address  TEXT,          -- only required when delivery_method = 'home'
    delivery_fee_cents INTEGER     NOT NULL DEFAULT 0 CHECK (delivery_fee_cents >= 0),
    total_cents       INTEGER      NOT NULL CHECK (total_cents >= 0),
    payment_intent_id VARCHAR(255),
    paid_at           TIMESTAMPTZ,
    created_at        TIMESTAMPTZ  NOT NULL DEFAULT NOW()
);

-- Order items (snapshot of product at time of order) -----------------
CREATE TABLE IF NOT EXISTS shop_order_items (
    id              SERIAL PRIMARY KEY,
    order_id        INTEGER      NOT NULL REFERENCES shop_orders(id) ON DELETE CASCADE,
    product_id      INTEGER      REFERENCES shop_products(id) ON DELETE SET NULL,
    product_name    VARCHAR(255) NOT NULL,   -- snapshot at order time
    unit_price_cents INTEGER     NOT NULL CHECK (unit_price_cents >= 0),
    quantity        INTEGER      NOT NULL CHECK (quantity > 0)
);
