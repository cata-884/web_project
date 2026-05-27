DROP TABLE IF EXISTS review_media CASCADE;
DROP TABLE IF EXISTS reviews CASCADE;
DROP TABLE IF EXISTS bookings CASCADE;
DROP TABLE IF EXISTS camping_media CASCADE;
DROP TABLE IF EXISTS campings CASCADE;
DROP TABLE IF EXISTS contact_messages CASCADE;
DROP TABLE IF EXISTS sesiuni CASCADE;
DROP TABLE IF EXISTS users CASCADE;
DROP TABLE IF EXISTS user_sections CASCADE;
DROP TABLE IF EXISTS section_campings CASCADE;
DROP TABLE IF EXISTS user_bans CASCADE;
DROP TABLE IF EXISTS organizer_verifications CASCADE;
DROP TYPE IF EXISTS user_role CASCADE;
DROP TYPE IF EXISTS camping_type CASCADE;
DROP TYPE IF EXISTS booking_status CASCADE;
DROP TYPE IF EXISTS media_type CASCADE;

CREATE TYPE user_role AS ENUM (
    'user',
    'organizer',	
    'admin'
);

CREATE TYPE camping_type AS ENUM (
	'wild'
	,'glamping'
	,'rv'
	,'tent'
	,'cabin'
	);

CREATE TYPE booking_status AS ENUM (
	'pending'
	,'confirmed'
	,'cancelled'
	,'completed'
	);

CREATE TYPE media_type AS ENUM (
	'image'
	,'audio'
	,'video'
	);

-- USERS
CREATE TABLE users (
	id SERIAL PRIMARY KEY
	,username VARCHAR(50) UNIQUE NOT NULL
	,email VARCHAR(200) UNIQUE NOT NULL
	,password_hash VARCHAR(255)
	,-- NULL pentru OAuth-only
	full_name VARCHAR(200)
	,avatar_url TEXT
	,ROLE user_role NOT NULL DEFAULT 'user'
	,oauth_provider VARCHAR(30)
	,-- 'google' | NULL
	oauth_id VARCHAR(255)
	,created_at TIMESTAMP NOT NULL DEFAULT NOW()
	,UNIQUE (
		oauth_provider
		,oauth_id
		)
	);

-- SESIUNI (Bearer tokens, pattern din proiectul BD)
CREATE TABLE sesiuni (
	token VARCHAR(64) PRIMARY KEY
	,user_id INT NOT NULL REFERENCES users(id) ON DELETE CASCADE
	,created_at TIMESTAMP NOT NULL DEFAULT NOW()
	,expires_at TIMESTAMP NOT NULL
	);

CREATE INDEX idx_sesiuni_expires ON sesiuni (expires_at);

CREATE INDEX idx_sesiuni_user ON sesiuni (user_id);

-- CAMPINGS (hybrid: orice user poate adauga, creatorul are control)
CREATE TABLE campings (
	id SERIAL PRIMARY KEY
	,created_by INT NOT NULL REFERENCES users(id) ON DELETE CASCADE
	,name VARCHAR(200) NOT NULL
	,slug VARCHAR(220) UNIQUE NOT NULL
	,-- pentru URL-uri prietenoase
	description TEXT
	,TYPE camping_type NOT NULL DEFAULT 'tent'
	,address VARCHAR(300)
	,region VARCHAR(100)
	,-- pt statistici
	latitude DECIMAL(10, 7) NOT NULL
	,longitude DECIMAL(10, 7) NOT NULL
	,price_per_night DECIMAL(8, 2)
	,--alternativ, cost per guest sau per camping
	capacity INT
	,rating_avg DECIMAL(3, 2)
	,-- recalculat de trigger
	rating_count INT NOT NULL DEFAULT 0
	,is_published BOOLEAN NOT NULL DEFAULT TRUE
	,approval_status INT NOT NULL DEFAULT 0
	,admin_feedback TEXT
	,created_at TIMESTAMP NOT NULL DEFAULT NOW()
	,updated_at TIMESTAMP NOT NULL DEFAULT NOW()
	);

CREATE INDEX idx_campings_location ON campings (
	latitude
	,longitude
	);

CREATE INDEX idx_campings_creator ON campings (created_by);

CREATE INDEX idx_campings_region ON campings (region);

-- MEDIA campings (image/audio/video)
CREATE TABLE camping_media (
	id SERIAL PRIMARY KEY
	,camping_id INT NOT NULL REFERENCES campings(id) ON DELETE CASCADE
	,TYPE media_type NOT NULL
	,url TEXT NOT NULL
	,sort_order INT NOT NULL DEFAULT 0
	,created_at TIMESTAMP NOT NULL DEFAULT NOW()
	);

CREATE INDEX idx_camping_media_camping ON camping_media (camping_id
	);

-- FACILITIES & ENVIRONMENTS
CREATE TABLE camping_facilities (
    id          SERIAL PRIMARY KEY,
    camping_id  INT NOT NULL REFERENCES campings(id) ON DELETE CASCADE,
    facility_name VARCHAR(100) NOT NULL,
    UNIQUE (camping_id, facility_name)
);

CREATE TABLE camping_environments (
    id               SERIAL PRIMARY KEY,
    camping_id       INT NOT NULL REFERENCES campings(id) ON DELETE CASCADE,
    environment_name VARCHAR(100) NOT NULL,
    UNIQUE (camping_id, environment_name)
);

-- BOOKINGS
CREATE TABLE bookings (
	id SERIAL PRIMARY KEY
	,user_id INT NOT NULL REFERENCES users(id)
	,camping_id INT NOT NULL REFERENCES campings(id)
	,check_in DATE NOT NULL
	,check_out DATE NOT NULL
	,guests INT NOT NULL DEFAULT 1 CHECK (guests > 0)
	,total_price DECIMAL(10, 2)
	,STATUS booking_status NOT NULL DEFAULT 'pending'
	,created_at TIMESTAMP NOT NULL DEFAULT NOW()
	,CHECK (check_out > check_in)
	);

CREATE INDEX idx_bookings_user ON bookings (user_id);

CREATE INDEX idx_bookings_camping ON bookings (camping_id);

CREATE INDEX idx_bookings_dates ON bookings (
	check_in
	,check_out
	);

-- REVIEWS
CREATE TABLE reviews (
	id SERIAL PRIMARY KEY
	,user_id INT NOT NULL REFERENCES users(id) ON DELETE CASCADE
	,camping_id INT NOT NULL REFERENCES campings(id) ON DELETE CASCADE
	,booking_id INT REFERENCES bookings(id) ON DELETE SET NULL
	,rating INT NOT NULL CHECK (
		rating BETWEEN 1
			AND 5
		)
	,title VARCHAR(200)
	,content TEXT
	,created_at TIMESTAMP NOT NULL DEFAULT NOW()
	,UNIQUE (
		user_id
		,camping_id
		)
	);

CREATE INDEX idx_reviews_camping ON reviews (camping_id);

-- MEDIA reviews
CREATE TABLE review_media (
	id SERIAL PRIMARY KEY
	,review_id INT NOT NULL REFERENCES reviews(id) ON DELETE CASCADE
	,TYPE media_type NOT NULL
	,url TEXT NOT NULL
	,created_at TIMESTAMP NOT NULL DEFAULT NOW()
	);

-- CONTACT MESSAGES
CREATE TABLE contact_messages (
	id SERIAL PRIMARY KEY
	,name VARCHAR(200) NOT NULL
	,email VARCHAR(200) NOT NULL
	,phone VARCHAR(50)
	,message TEXT NOT NULL
	,created_at TIMESTAMP NOT NULL DEFAULT NOW()
	);

-- SECTIUNI PERSONALE
CREATE TABLE

IF NOT EXISTS user_sections(id SERIAL PRIMARY KEY, user_id INT NOT NULL 
		REFERENCES users(id) ON DELETE CASCADE, name VARCHAR(100) NOT 
		NULL, color VARCHAR(7) DEFAULT '#4A90D9', 
		-- hex color pentru UI
		created_at TIMESTAMP NOT NULL DEFAULT NOW());
	-- CAMPING-URI ASOCIATE SECTIUNILOR (many-to-many)
	CREATE TABLE

IF NOT EXISTS section_campings(section_id INT NOT NULL REFERENCES 
		user_sections(id) ON DELETE CASCADE, camping_id INT NOT NULL 
		REFERENCES campings(id) ON DELETE CASCADE, added_at TIMESTAMP 
		NOT NULL DEFAULT NOW(), PRIMARY KEY (
			section_id
			,camping_id
			));
	CREATE INDEX

IF NOT EXISTS idx_sections_user ON user_sections(user_id);
	CREATE INDEX

IF NOT EXISTS idx_section_campings_section ON section_campings(
		section_id);
	CREATE INDEX IF NOT EXISTS idx_section_campings_camping ON section_campings(
		camping_id);

-- Cereri de verificare / promovare la rolul de organizer
CREATE TABLE organizer_verifications (
    id SERIAL PRIMARY KEY,
    user_id INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    -- Date reprezentant legal
    last_name                VARCHAR(100),
    first_name               VARCHAR(100),
    id_document_path         TEXT,
    -- Date firma
    business_type            VARCHAR(50),
    company_name             VARCHAR(200),
    registration_number      VARCHAR(50),
    address_street           VARCHAR(200),
    address_number           VARCHAR(20),
    address_city             VARCHAR(100),
    address_zip              VARCHAR(20),
    registration_document_path TEXT,
    -- Contact
    contact_phone            VARCHAR(50),
    contact_email            VARCHAR(200),
    -- Campuri vechi pastrate pentru compatibilitate
    legal_name               VARCHAR(200),
    cui                      VARCHAR(50),
    id_card_url              TEXT,
    authorization_url        TEXT,
    contract_url             TEXT,
    -- Status si metadate
    status                   VARCHAR(20) NOT NULL DEFAULT 'pending',
    admin_notes              TEXT,
    reviewed_by              INT REFERENCES users(id),
    submitted_at             TIMESTAMP NOT NULL DEFAULT NOW(),
    reviewed_at              TIMESTAMP,
    CONSTRAINT chk_org_status CHECK (status IN ('pending', 'approved', 'rejected', 'rejected_feedback'))
);

CREATE INDEX idx_org_verif_user ON organizer_verifications(user_id);
CREATE INDEX idx_org_verif_status ON organizer_verifications(status);

-- Tabel pentru ban-uri utilizatori
CREATE TABLE user_bans (
    id SERIAL PRIMARY KEY,
    user_id INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    reason TEXT NOT NULL,
    banned_until TIMESTAMP,             -- NULL = permanent
    banned_by INT NOT NULL REFERENCES users(id),
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP NOT NULL DEFAULT NOW()
);

CREATE INDEX idx_bans_user_active ON user_bans(user_id, is_active);