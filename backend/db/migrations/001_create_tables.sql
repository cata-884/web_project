/*
 docker run --name my-postgres \
  -e POSTGRES_USER=user \
  -e POSTGRES_PASSWORD=pass \
  -p 5432:5432 \
  -d postgres

 docker ps

 php script/migrate.php

 */

DROP TABLE IF EXISTS review_media     CASCADE;
DROP TABLE IF EXISTS reviews          CASCADE;
DROP TABLE IF EXISTS bookings         CASCADE;
DROP TABLE IF EXISTS camping_media    CASCADE;
DROP TABLE IF EXISTS campings         CASCADE;
DROP TABLE IF EXISTS contact_messages CASCADE;
DROP TABLE IF EXISTS sesiuni          CASCADE;
DROP TABLE IF EXISTS users            CASCADE;

DROP TYPE IF EXISTS user_role        CASCADE;
DROP TYPE IF EXISTS camping_type     CASCADE;
DROP TYPE IF EXISTS booking_status   CASCADE;
DROP TYPE IF EXISTS media_type       CASCADE;

CREATE TYPE user_role      AS ENUM ('user', 'admin');
CREATE TYPE camping_type   AS ENUM ('wild', 'glamping', 'rv', 'tent', 'cabin');
CREATE TYPE booking_status AS ENUM ('pending', 'confirmed', 'cancelled', 'completed');
CREATE TYPE media_type     AS ENUM ('image', 'audio', 'video');

-- USERS
CREATE TABLE users (
                       id              SERIAL       PRIMARY KEY,
                       username        VARCHAR(50)  UNIQUE NOT NULL,
                       email           VARCHAR(200) UNIQUE NOT NULL,
                       password_hash   VARCHAR(255),                 -- NULL pentru OAuth-only
                       full_name       VARCHAR(200),
                       avatar_url      TEXT,
                       role            user_role    NOT NULL DEFAULT 'user',
                       oauth_provider  VARCHAR(30),                  -- 'google' | NULL
                       oauth_id        VARCHAR(255),
                       created_at      TIMESTAMP    NOT NULL DEFAULT NOW(),
                       UNIQUE (oauth_provider, oauth_id)
);

-- SESIUNI (Bearer tokens, pattern din proiectul BD)
CREATE TABLE sesiuni (
                         token       VARCHAR(64) PRIMARY KEY,
                         user_id     INT         NOT NULL REFERENCES users(id) ON DELETE CASCADE,
                         created_at  TIMESTAMP   NOT NULL DEFAULT NOW(),
                         expires_at  TIMESTAMP   NOT NULL
);

CREATE INDEX idx_sesiuni_expires ON sesiuni(expires_at);
CREATE INDEX idx_sesiuni_user    ON sesiuni(user_id);

-- CAMPINGS (hybrid: orice user poate adauga, creatorul are control)
CREATE TABLE campings (
                          id              SERIAL          PRIMARY KEY,
                          created_by      INT             NOT NULL REFERENCES users(id) ON DELETE CASCADE,
                          name            VARCHAR(200)    NOT NULL,
                          slug            VARCHAR(220)    UNIQUE NOT NULL, -- pentru URL-uri prietenoase
                          description     TEXT,
                          type            camping_type    NOT NULL DEFAULT 'tent',
                          address         VARCHAR(300),
                          region          VARCHAR(100),                       -- pt statistici
                          latitude        DECIMAL(10, 7)  NOT NULL,
                          longitude       DECIMAL(10, 7)  NOT NULL,
                          price_per_night DECIMAL(8, 2), --alternativ, cost per guest sau per camping
                          capacity        INT,
                          rating_avg      DECIMAL(3, 2),                      -- recalculat de trigger
                          rating_count    INT             NOT NULL DEFAULT 0,
                          is_published    BOOLEAN         NOT NULL DEFAULT TRUE,
                          created_at      TIMESTAMP       NOT NULL DEFAULT NOW(),
                          updated_at      TIMESTAMP       NOT NULL DEFAULT NOW()
);

CREATE INDEX idx_campings_location ON campings(latitude, longitude);
CREATE INDEX idx_campings_creator  ON campings(created_by);
CREATE INDEX idx_campings_region   ON campings(region);

-- MEDIA campings (image/audio/video)
CREATE TABLE camping_media (
                               id         SERIAL     PRIMARY KEY,
                               camping_id INT        NOT NULL REFERENCES campings(id) ON DELETE CASCADE,
                               type       media_type NOT NULL,
                               url        TEXT       NOT NULL,
                               sort_order INT        NOT NULL DEFAULT 0,
                               created_at TIMESTAMP  NOT NULL DEFAULT NOW()
);

CREATE INDEX idx_camping_media_camping ON camping_media(camping_id);

-- BOOKINGS
CREATE TABLE bookings (
                          id          SERIAL         PRIMARY KEY,
                          user_id     INT            NOT NULL REFERENCES users(id),
                          camping_id  INT            NOT NULL REFERENCES campings(id),
                          check_in    DATE           NOT NULL,
                          check_out   DATE           NOT NULL,
                          guests      INT            NOT NULL DEFAULT 1 CHECK (guests > 0),
                          total_price DECIMAL(10, 2),
                          status      booking_status NOT NULL DEFAULT 'pending',
                          created_at  TIMESTAMP      NOT NULL DEFAULT NOW(),
                          CHECK (check_out > check_in)
);

CREATE INDEX idx_bookings_user    ON bookings(user_id);
CREATE INDEX idx_bookings_camping ON bookings(camping_id);
CREATE INDEX idx_bookings_dates   ON bookings(check_in, check_out);

-- REVIEWS
CREATE TABLE reviews (
                         id          SERIAL    PRIMARY KEY,
                         user_id     INT       NOT NULL REFERENCES users(id) ON DELETE CASCADE,
                         camping_id  INT       NOT NULL REFERENCES campings(id) ON DELETE CASCADE,
                         booking_id  INT       REFERENCES bookings(id) ON DELETE SET NULL,
                         rating      INT       NOT NULL CHECK (rating BETWEEN 1 AND 5),
                         title       VARCHAR(200),
                         content     TEXT,
                         created_at  TIMESTAMP NOT NULL DEFAULT NOW(),
                         UNIQUE (user_id, camping_id)
);
CREATE INDEX idx_reviews_camping ON reviews(camping_id);

-- MEDIA reviews
CREATE TABLE review_media (
                              id         SERIAL     PRIMARY KEY,
                              review_id  INT        NOT NULL REFERENCES reviews(id) ON DELETE CASCADE,
                              type       media_type NOT NULL,
                              url        TEXT       NOT NULL,
                              created_at TIMESTAMP  NOT NULL DEFAULT NOW()
);

-- CONTACT MESSAGES
CREATE TABLE contact_messages (
                                  id         SERIAL       PRIMARY KEY,
                                  name       VARCHAR(200) NOT NULL,
                                  email      VARCHAR(200) NOT NULL,
                                  phone      VARCHAR(50),
                                  message    TEXT         NOT NULL,
                                  created_at TIMESTAMP    NOT NULL DEFAULT NOW()
);

-- TRIGGERE PL/pgSQL

-- Recalculare rating_avg + rating_count la INSERT/UPDATE/DELETE pe reviews
CREATE OR REPLACE FUNCTION recalc_camping_rating()
RETURNS TRIGGER AS $$
DECLARE
    target_id INT := COALESCE(NEW.camping_id, OLD.camping_id); -- camping_id afectat, prima valoare nenula
BEGIN
UPDATE campings
SET rating_avg   = (SELECT ROUND(AVG(rating)::NUMERIC, 2) FROM reviews WHERE camping_id = target_id),
    rating_count = (SELECT COUNT(*) FROM reviews WHERE camping_id = target_id),
    updated_at   = NOW()
WHERE id = target_id;
RETURN COALESCE(NEW, OLD);
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER trg_recalc_rating
    AFTER INSERT OR UPDATE OR DELETE ON reviews
    FOR EACH ROW EXECUTE FUNCTION recalc_camping_rating();

-- Auto-update la updated_at pe campings
CREATE OR REPLACE FUNCTION set_updated_at()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = NOW();
RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER trg_campings_updated_at
    BEFORE UPDATE ON campings
    FOR EACH ROW EXECUTE FUNCTION set_updated_at();