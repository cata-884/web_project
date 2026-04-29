-- Schema bazata pe arhitectura aplicatiei (Dashboard / Colectii / Comunitate / Setari)

-- USERS
CREATE TABLE users (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    username      TEXT    UNIQUE NOT NULL,
    email         TEXT    UNIQUE NOT NULL,
    password_hash TEXT    NOT NULL,
    avatar_url    TEXT,
    created_at    DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Preferinte utilizator (Settings > App Preferences)
CREATE TABLE user_preferences (
    user_id       INTEGER PRIMARY KEY REFERENCES users(id) ON DELETE CASCADE,
    theme         TEXT DEFAULT 'auto',     -- 'light' | 'dark' | 'auto'
    language      TEXT DEFAULT 'ro',       -- 'ro' | 'en' | ...
    default_view  TEXT DEFAULT 'gallery',  -- 'gallery' | 'table'
    measurement   TEXT DEFAULT 'metric'    -- 'metric' | 'imperial'
);

-- COMUNITATE 
-- Prietenii (cereri + acceptate)
CREATE TABLE friendships (
    requester_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    addressee_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    status       TEXT NOT NULL DEFAULT 'pending', -- 'pending' | 'accepted'
    created_at   DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (requester_id, addressee_id)
);

-- COLECTII (Ierbarele) 
CREATE TABLE collections (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    owner_id    INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    name        TEXT NOT NULL,
    description TEXT,
    is_public   INTEGER NOT NULL DEFAULT 0, -- 0 privat / 1 public (apare in Dashboard)
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Membri colectie (rol editor / viewer)
CREATE TABLE collection_members (
    collection_id INTEGER NOT NULL REFERENCES collections(id) ON DELETE CASCADE,
    user_id       INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    role          TEXT NOT NULL DEFAULT 'viewer', -- 'editor' | 'viewer'
    PRIMARY KEY (collection_id, user_id)
);

-- PLANTE 
CREATE TABLE plants (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    collection_id   INTEGER REFERENCES collections(id) ON DELETE CASCADE,
    name_popular    TEXT NOT NULL,
    name_scientific TEXT,
    origin          TEXT,
    status          TEXT,   -- 'protected' | 'endangered' | 'invasive' | 'common'
    climate         TEXT,
    danger_level    TEXT,   -- 'none' | 'low' | 'medium' | 'high'
    propagation     TEXT,   -- 'seeds' | 'cuttings' | 'division' | ...
    created_by      INTEGER REFERENCES users(id),
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Descrieri multilingve
CREATE TABLE plant_descriptions (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    plant_id    INTEGER NOT NULL REFERENCES plants(id) ON DELETE CASCADE,
    language    TEXT NOT NULL,
    description TEXT
);

-- Proprietati (medicinal, ornamental, toxic, edible...)
CREATE TABLE plant_properties (
    id       INTEGER PRIMARY KEY AUTOINCREMENT,
    plant_id INTEGER NOT NULL REFERENCES plants(id) ON DELETE CASCADE,
    property TEXT NOT NULL
);

-- Specii inrudite
CREATE TABLE plant_relations (
    plant_id         INTEGER REFERENCES plants(id) ON DELETE CASCADE,
    related_plant_id INTEGER REFERENCES plants(id) ON DELETE CASCADE,
    PRIMARY KEY (plant_id, related_plant_id)
);

-- Imagini / video
CREATE TABLE plant_media (
    id       INTEGER PRIMARY KEY AUTOINCREMENT,
    plant_id INTEGER NOT NULL REFERENCES plants(id) ON DELETE CASCADE,
    type     TEXT NOT NULL, -- 'image' | 'video'
    url      TEXT NOT NULL
);

-- REVIEWS (pentru landing page) 
CREATE TABLE reviews (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id    INTEGER REFERENCES users(id),
    rating     INTEGER NOT NULL CHECK (rating BETWEEN 1 AND 5),
    content    TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- CONTACT MESSAGES 
CREATE TABLE contact_messages (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    name       TEXT NOT NULL,
    email      TEXT NOT NULL,
    message    TEXT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
