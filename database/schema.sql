-- ============================================================
-- database/schema.sql
-- Full DDL for the Escales Culinaires PostgreSQL database.
-- Run once to provision the schema.
-- ============================================================

-- Users -------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
    id              SERIAL PRIMARY KEY,
    email           VARCHAR(255) NOT NULL UNIQUE,
    password_hash   VARCHAR(255) NOT NULL,
    first_name      VARCHAR(100) NOT NULL,
    last_name       VARCHAR(100) NOT NULL,
    phone           VARCHAR(30),
    phone2          VARCHAR(30),
    role            VARCHAR(20)  NOT NULL DEFAULT 'user' CHECK (role IN ('user', 'admin')),
    photo_consent   BOOLEAN      NOT NULL DEFAULT FALSE,
    credits         INTEGER      NOT NULL DEFAULT 0,
    created_at      TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
    deleted_at      TIMESTAMPTZ
);

-- Cooking sessions --------------------------------------------
CREATE TABLE IF NOT EXISTS sessions (
    id                  SERIAL PRIMARY KEY,
    title               VARCHAR(255)    NOT NULL,
    theme               VARCHAR(255)    NOT NULL,
    session_date        DATE            NOT NULL,
    start_time          TIME            NOT NULL,
    end_time            TIME            NOT NULL,
    max_attendees       INTEGER         NOT NULL CHECK (max_attendees > 0),
    remaining_seats     INTEGER         NOT NULL CHECK (remaining_seats >= 0),
    price_cents         INTEGER         NOT NULL CHECK (price_cents >= 0),  -- price in euro cents
    status              VARCHAR(20)     NOT NULL DEFAULT 'pending'
                            CHECK (status IN ('pending', 'confirmed', 'cancelled')),
    age_category        VARCHAR(10)     NOT NULL DEFAULT '6-12'
                            CHECK (age_category IN ('3-5', '3-10', '3-12', '6-12', '13+')),
    summary             TEXT,           -- public teaser
    objectives          TEXT,           -- pedagogic objectives (shown post-session)
    theoretical_content TEXT,           -- theoretical part (shown post-session)
    recipe              TEXT,           -- practical recipe (shown post-session)
    is_private          BOOLEAN         NOT NULL DEFAULT FALSE,
    created_at          TIMESTAMPTZ     NOT NULL DEFAULT NOW(),
    deleted_at          TIMESTAMPTZ,
    CONSTRAINT remaining_lte_max CHECK (remaining_seats <= max_attendees)
);

-- Session allowances (users allowed to register for private sessions) ----
CREATE TABLE IF NOT EXISTS session_allowances (
    id          SERIAL PRIMARY KEY,
    session_id  INTEGER     NOT NULL REFERENCES sessions(id) ON DELETE CASCADE,
    user_id     INTEGER     NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    created_at  TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    UNIQUE (session_id, user_id)
);

-- Session media (photos, post-session) ------------------------
CREATE TABLE IF NOT EXISTS session_media (
    id           SERIAL PRIMARY KEY,
    session_id   INTEGER      NOT NULL REFERENCES sessions(id) ON DELETE CASCADE,
    filename     VARCHAR(255) NULL,                  -- set for locally-uploaded files
    external_url TEXT         NULL,                  -- set for external-URL photos
    is_private   BOOLEAN      NOT NULL DEFAULT TRUE, -- requires photo_consent
    created_at   TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
    CONSTRAINT chk_session_media_source CHECK (
        (filename IS NOT NULL AND external_url IS NULL)
        OR
        (filename IS NULL AND external_url IS NOT NULL)
    )
);

-- Promotional codes ------------------------------------------
CREATE TABLE IF NOT EXISTS promo_codes (
    id              SERIAL PRIMARY KEY,
    code            VARCHAR(50)  NOT NULL,
    session_id      INTEGER      REFERENCES sessions(id) ON DELETE CASCADE,  -- NULL = valid for any session
    discount_cents  INTEGER      NOT NULL CHECK (discount_cents > 0),
    max_uses        INTEGER,                                                  -- NULL = unlimited
    used_count      INTEGER      NOT NULL DEFAULT 0,
    expires_at      TIMESTAMPTZ,
    created_at      TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
    deleted_at      TIMESTAMPTZ,
    UNIQUE (code)
);

-- Bookings ----------------------------------------------------
CREATE TABLE IF NOT EXISTS bookings (
    id                  SERIAL PRIMARY KEY,
    user_id             INTEGER     NOT NULL REFERENCES users(id) ON DELETE RESTRICT,
    session_id          INTEGER     NOT NULL REFERENCES sessions(id) ON DELETE RESTRICT,
    status              VARCHAR(30) NOT NULL DEFAULT 'pending'
                            CHECK (status IN ('pending', 'confirmed', 'attended', 'absent', 'credited', 'cancelled')),
    payment_intent_id   VARCHAR(255),           -- Stripe PaymentIntent id
    paid_at             TIMESTAMPTZ,
    used_credit         BOOLEAN     NOT NULL DEFAULT FALSE,
    confirmed_by_admin  BOOLEAN     NOT NULL DEFAULT FALSE,
    child_first_name    VARCHAR(100),           -- first name of the child attending
    child_last_name     VARCHAR(100),           -- last name of the child attending
    child_age           INTEGER,                -- age of the child (may differ from session age category)
    child_allergies     TEXT,                   -- food allergies (optional)
    promo_code_id       INTEGER     REFERENCES promo_codes(id) ON DELETE SET NULL,
    discount_cents      INTEGER     NOT NULL DEFAULT 0,
    number_of_children  INTEGER     NOT NULL DEFAULT 1 CHECK (number_of_children >= 1),
    created_at          TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    UNIQUE (user_id, session_id)
);

-- Credits -----------------------------------------------------
CREATE TABLE IF NOT EXISTS credits (
    id              SERIAL PRIMARY KEY,
    user_id         INTEGER     NOT NULL REFERENCES users(id) ON DELETE RESTRICT,
    booking_id      INTEGER     REFERENCES bookings(id) ON DELETE SET NULL,
    reason          TEXT,
    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    used_at         TIMESTAMPTZ,
    used_booking_id INTEGER     REFERENCES bookings(id) ON DELETE SET NULL
);

-- Basket items (sessions added to basket before checkout) ----
CREATE TABLE IF NOT EXISTS basket_items (
    id               SERIAL PRIMARY KEY,
    user_id          INTEGER      NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    session_id       INTEGER      NOT NULL REFERENCES sessions(id) ON DELETE CASCADE,
    child_first_name VARCHAR(100),
    child_last_name  VARCHAR(100),
    child_age        INTEGER,
    child_allergies  TEXT,
    number_of_children INTEGER NOT NULL DEFAULT 1 CHECK (number_of_children >= 1),
    created_at       TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
    UNIQUE (user_id, session_id)
);

-- General messages (homepage news thread) --------------------
CREATE TABLE IF NOT EXISTS general_messages (
    id         SERIAL PRIMARY KEY,
    body       TEXT        NOT NULL,
    type       VARCHAR(20) NOT NULL DEFAULT 'info'
                   CHECK (type IN ('info', 'warning', 'danger', 'success')),
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    deleted_at TIMESTAMPTZ
);

-- Ratings (post-session, by confirmed attendees) -------------
CREATE TABLE IF NOT EXISTS ratings (
    id           SERIAL PRIMARY KEY,
    booking_id   INTEGER      NOT NULL REFERENCES bookings(id) ON DELETE CASCADE,
    user_id      INTEGER      NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    session_id   INTEGER      NOT NULL REFERENCES sessions(id) ON DELETE CASCADE,
    stars        SMALLINT     NOT NULL CHECK (stars >= 0 AND stars <= 5),
    comment      VARCHAR(200),
    is_anonymous BOOLEAN      NOT NULL DEFAULT FALSE,
    created_at   TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
    UNIQUE (user_id, session_id)
);

-- Packs (groups of sessions with a global price) ------------
CREATE TABLE IF NOT EXISTS packs (
    id          SERIAL PRIMARY KEY,
    title       VARCHAR(255) NOT NULL,
    description TEXT,
    price_cents INTEGER     NOT NULL CHECK (price_cents >= 0),
    created_at  TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    deleted_at  TIMESTAMPTZ
);

-- Pack ↔ session join table ----------------------------------
CREATE TABLE IF NOT EXISTS pack_sessions (
    pack_id    INTEGER NOT NULL REFERENCES packs(id) ON DELETE CASCADE,
    session_id INTEGER NOT NULL REFERENCES sessions(id) ON DELETE CASCADE,
    PRIMARY KEY (pack_id, session_id)
);

-- Shop products (prepared meals catalog) ---------------------
CREATE TABLE IF NOT EXISTS shop_products (
    id             SERIAL PRIMARY KEY,
    name           VARCHAR(255) NOT NULL,
    description    TEXT,
    photo_filename VARCHAR(255),
    external_photo_url TEXT,
    price_cents    INTEGER      NOT NULL CHECK (price_cents >= 0),
    portion_count  INTEGER      NOT NULL DEFAULT 1,
    min_order_portions INTEGER  NOT NULL DEFAULT 1,
    is_available   BOOLEAN      NOT NULL DEFAULT TRUE,
    created_at     TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
    deleted_at     TIMESTAMPTZ,
    CONSTRAINT chk_shop_products_portion_count_positive CHECK (portion_count > 0),
    CONSTRAINT chk_shop_products_min_order_portions_positive CHECK (min_order_portions > 0),
    CONSTRAINT chk_shop_products_single_photo_source CHECK (
        NOT (
            photo_filename IS NOT NULL
            AND external_photo_url IS NOT NULL
        )
    )
);

-- Shop orders -------------------------------------------------
CREATE TABLE IF NOT EXISTS shop_orders (
    id                SERIAL PRIMARY KEY,
    user_id           INTEGER      NOT NULL REFERENCES users(id) ON DELETE RESTRICT,
    status            VARCHAR(20)  NOT NULL DEFAULT 'pending'
                          CHECK (status IN ('pending', 'paid', 'prepared', 'delivered', 'cancelled')),
    delivery_method   VARCHAR(30)  NOT NULL
                          CHECK (delivery_method IN ('home', 'market_wednesday', 'market_friday', 'shop')),
    delivery_date     DATE         NOT NULL,
    delivery_address  TEXT,
    delivery_fee_cents INTEGER     NOT NULL DEFAULT 0 CHECK (delivery_fee_cents >= 0),
    total_cents       INTEGER      NOT NULL CHECK (total_cents >= 0),
    payment_intent_id VARCHAR(255),
    paid_at           TIMESTAMPTZ,
    created_at        TIMESTAMPTZ  NOT NULL DEFAULT NOW()
);

-- Shop order items --------------------------------------------
CREATE TABLE IF NOT EXISTS shop_order_items (
    id              SERIAL PRIMARY KEY,
    order_id        INTEGER      NOT NULL REFERENCES shop_orders(id) ON DELETE CASCADE,
    product_id      INTEGER      REFERENCES shop_products(id) ON DELETE SET NULL,
    product_name    VARCHAR(255) NOT NULL,
    unit_price_cents INTEGER     NOT NULL CHECK (unit_price_cents >= 0),
    quantity        INTEGER      NOT NULL CHECK (quantity > 0)
);

-- Canceled market delivery dates -----------------------------------
CREATE TABLE IF NOT EXISTS shop_market_delivery_cancellations (
    id            SERIAL PRIMARY KEY,
    delivery_date DATE        NOT NULL UNIQUE,
    created_at    TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

-- Password reset tokens ---------------------------------------
CREATE TABLE IF NOT EXISTS password_resets (
    id          SERIAL PRIMARY KEY,
    user_id     INTEGER     NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    token_hash  VARCHAR(255) NOT NULL UNIQUE,
    expires_at  TIMESTAMPTZ NOT NULL,
    used        BOOLEAN     NOT NULL DEFAULT FALSE,
    created_at  TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
