CREATE EXTENSION IF NOT EXISTS pgcrypto;

-- Cleanup
TRUNCATE
    organizer_verifications, user_bans, user_preferences,
    camping_facilities, camping_environments, section_campings, user_sections,
    review_media, reviews, bookings,
    camping_media, campings, contact_messages, sesiuni, users
RESTART IDENTITY CASCADE;

DO $seed$
DECLARE
    -- constante
    c_pass_admin  CONSTANT TEXT := 'admin1234';
    c_pass_user   CONSTANT TEXT := 'parola1234';
    c_bcrypt_cost CONSTANT INT  := 10;

    -- useri
    l_user_uname TEXT[] := ARRAY['mihai', 'ana', 'radu', 'ioana', 'vlad'];
    l_user_email TEXT[] := ARRAY['mihai@gmail.com', 'ana@gmail.com', 'radu@gmail.com', 'ioana@gmail.com', 'vlad@gmail.com'];
    l_user_full  TEXT[] := ARRAY['Mihai Popescu', 'Ana Ionescu', 'Radu Marin', 'Ioana Stoica', 'Vlad Diaconu'];
    l_user_avtr  TEXT[] := ARRAY[
        'https://i.pravatar.cc/150?img=12',
        'https://i.pravatar.cc/150?img=5',
        'https://i.pravatar.cc/150?img=15',
        'https://i.pravatar.cc/150?img=20',
        'https://i.pravatar.cc/150?img=33'
    ];

    -- facilități
    l_facilities TEXT[] := ARRAY['wi-fi', 'dușuri calde', 'toalete ecologice', 'electricitate', 'parcare', 'zonă foc de tabără', 'apă potabilă', 'restaurant'];
    v_num_items  INT;

    -- media URL-uri camping
    l_media_urls TEXT[] := ARRAY[
        'https://images.unsplash.com/photo-1504280390367-361c6d9f38f4?w=800',
        'https://images.unsplash.com/photo-1496545672447-f699b503d270?w=800',
        'https://images.unsplash.com/photo-1478131143081-80f7f84ca84d?w=800',
        'https://images.unsplash.com/photo-1455496231601-e6195da1f841?w=800',
        'https://images.unsplash.com/photo-1487730116645-74489c95b41b?w=800',
        'https://images.unsplash.com/photo-1510312305653-8ed496efae75?w=800',
        'https://images.unsplash.com/photo-1517824806704-9040b037703b?w=800',
        'https://images.unsplash.com/photo-1444930694458-01babe71870e?w=800'
    ];

    -- medii: array-uri paralele (index camping + nume mediu)
    l_env_cidx INT[]  := ARRAY[1,1,2,3,3,4,5,5,6,7,7,8,9,9,10,10];
    l_env_name TEXT[] := ARRAY[
        'munte','pășune alpină','munte','munte','pădure','munte','deltă','lângă lac',
        'plajă','munte','pădure','munte','pădure','lângă râu','pădure','lângă lac'
    ];

    -- bookings: status și număr per status
    l_bk_status TEXT[] := ARRAY['completed','confirmed','pending','cancelled'];
    l_bk_count  INT[]  := ARRAY[10, 5, 3, 2];

    -- recenzii (16 texte pentru varietate)
    l_rev_content TEXT[] := ARRAY[
        'Locul a depasit asteptarile. Peisaj spectaculos, gazdele prietenoase, totul a fost perfect. Recomand cu toata inima!',
        'Am avut parte de o vacanta extraordinara. Linistea naturii, confortul facilitatilor, totul a fost la nivel maxim.',
        'In general am avut o experienta placuta. Locul e frumos si curat. O singura observatie: drumul de acces e cam denivelat.',
        'Am stat 3 nopti si ne-am bucurat de fiecare moment. WiFi-ul era cam slab, dar intr-un camping nici nu prea ai nevoie.',
        'Stelele noaptea, focul de tabara, cantecul greierilor... exact ce cautam. Vom reveni cu siguranta.',
        'Totul a fost OK, fara sa iasa in evidenta. Pentru pret e corect. Poate cu mai multe facilitati ar fi excelent.',
        'Departe de aglomeratia orasului, exact ce ne trebuia. Aerul curat si peisajele de poveste compenseaza orice mic inconvenient.',
        'Aprecierile noastre pentru gazde. Totul a fost organizat impecabil. Vom reveni anul viitor.',
        'Natura salbatica si autentica. Nu te astepta la lux, dar te vei intoarce incarcat de energie.',
        'Facilitatile sunt curate si bine intretinute. Personalul a fost extrem de amabil si de ajutor.',
        'Un loc de poveste! Focul de tabara, cerul instelat si linistea totala au facut aceasta vacanta memorabila.',
        'Pretul este excelent pentru ce ofera. Recomandat pentru familii cu copii, zona este sigura si frumoasa.',
        'Traseele din jur sunt superbe. Am explorat imprejurimile ore in sir si nu am dat de niciun loc dezamagitor.',
        'Singura problema a fost aglomeratia din weekend, dar in rest totul a fost la superlativ.',
        'Am revenit pentru a treia oara si de fiecare data gasesc ceva nou de descoperit. Locul are farmec aparte.',
        'Curatenia si organizarea sunt exemplare. Recomand oricui vrea sa evada din zgomotul orasului.'
    ];

    -- sectiuni: array-uri paralele (index user + nume secțiune)
    l_sec_uidx INT[]  := ARRAY[1, 1, 2, 3];
    l_sec_name TEXT[] := ARRAY['Favorite','Pentru anul viitor','Locuri vizitate','Trekking dream'];

    -- id-uri
    v_admin_id    INT;
    v_org_carpati INT;
    v_org_delta   INT;
    v_banned_id   INT;

    v_user_ids    INT[] := ARRAY[]::INT[];
    v_camping_ids INT[] := ARRAY[]::INT[];

    -- helpers
    v_temp_id    INT;
    v_camping_id INT;
    v_section_id INT;
    v_num_imgs   INT;
    v_rec        RECORD;
    i            INT;
    j            INT;
    s            INT;

BEGIN
    RAISE NOTICE 'Seeding users...';

    INSERT INTO users (username, email, password_hash, full_name, role)
    VALUES ('admin', 'admin@cat.ro', crypt(c_pass_admin, gen_salt('bf', c_bcrypt_cost)), 'Administrator Sistem', 'admin')
    RETURNING id INTO v_admin_id;

    INSERT INTO users (username, email, password_hash, full_name, role)
    VALUES ('org_carpati', 'org@carpati.ro', crypt(c_pass_user, gen_salt('bf', c_bcrypt_cost)), 'Carpathian Camps SRL', 'organizer')
    RETURNING id INTO v_org_carpati;

    INSERT INTO users (username, email, password_hash, full_name, role)
    VALUES ('org_delta', 'org@delta.ro', crypt(c_pass_user, gen_salt('bf', c_bcrypt_cost)), 'Delta Adventures', 'organizer')
    RETURNING id INTO v_org_delta;

    FOR i IN 1..array_length(l_user_uname, 1) LOOP
        INSERT INTO users (username, email, password_hash, full_name, avatar_url, role)
        VALUES (l_user_uname[i], l_user_email[i], crypt(c_pass_user, gen_salt('bf', c_bcrypt_cost)), l_user_full[i], l_user_avtr[i], 'user')
        RETURNING id INTO v_temp_id;
        v_user_ids := array_append(v_user_ids, v_temp_id);
    END LOOP;

    INSERT INTO users (username, email, password_hash, full_name, role)
    VALUES ('troll', 'banned@gmail.com', crypt(c_pass_user, gen_salt('bf', c_bcrypt_cost)), 'Troll User', 'user')
    RETURNING id INTO v_banned_id;

    RAISE NOTICE 'Seeding campings...';

    INSERT INTO campings (created_by, name, slug, type, region, address, latitude, longitude, description, price_per_night, capacity, approval_status)
    VALUES (v_org_carpati, 'Valea Verde - Transfagarasan', 'valea-verde-transfagarasan', 'tent', 'Arges',
        'DN7C km 104, Cumpana, Arges', 45.60240, 24.61680,
        'Camping situat pe celebra sosea Transfagarasan, la altitudine de 2034m. Vedere spectaculoasa catre lacul Balea. Locuri pentru corturi si parcare pentru rulote.',
        80.00, 4, 1)
    RETURNING id INTO v_temp_id;
    v_camping_ids := array_append(v_camping_ids, v_temp_id);

    INSERT INTO campings (created_by, name, slug, type, region, address, latitude, longitude, description, price_per_night, capacity, approval_status)
    VALUES (v_org_carpati, 'Cheile Bicazului Glamping', 'cheile-bicazului-glamping', 'glamping', 'Neamt',
        'Bicazu Ardelean, Neamt', 46.81940, 25.85100,
        'Experienta de lux in natura. Corturi safari cu paturi, mobilier si electricitate. Acces direct la traseele de hiking. Mic dejun inclus.',
        280.00, 2, 1)
    RETURNING id INTO v_temp_id;
    v_camping_ids := array_append(v_camping_ids, v_temp_id);

    INSERT INTO campings (created_by, name, slug, type, region, address, latitude, longitude, description, price_per_night, capacity, approval_status)
    VALUES (v_org_carpati, 'Padis Wild Camp', 'padis-wild-camp', 'wild', 'Bihor',
        'Platoul Padis, Bihor', 46.55833, 22.71667,
        'Camping salbatic in muntii Apuseni. Doar pentru aventurieri experimentati. Fara facilitati, doar apa curenta dintr-un izvor.',
        25.00, 10, 1)
    RETURNING id INTO v_temp_id;
    v_camping_ids := array_append(v_camping_ids, v_temp_id);

    INSERT INTO campings (created_by, name, slug, type, region, address, latitude, longitude, description, price_per_night, capacity, approval_status)
    VALUES (v_org_carpati, 'Bucegi RV Park', 'bucegi-rv-park', 'rv', 'Prahova',
        'DN1 km 134, Busteni, Prahova', 45.41060, 25.53670,
        'Parc dedicat rulotelor si autorulotelor la poalele Bucegilor. Curent 220V, apa, evacuare ape uzate, WiFi. Aproape de telecabina Busteni.',
        120.00, 6, 1)
    RETURNING id INTO v_temp_id;
    v_camping_ids := array_append(v_camping_ids, v_temp_id);

    INSERT INTO campings (created_by, name, slug, type, region, address, latitude, longitude, description, price_per_night, capacity, approval_status)
    VALUES (v_org_delta, 'Delta Dunarii Eco Camp', 'delta-dunarii-eco-camp', 'cabin', 'Tulcea',
        'Crisan, Tulcea', 45.17500, 29.46500,
        'Casute pe stalpi in mijlocul deltei. Acces doar pe apa. Tururi cu barca incluse. Observatii de pasari, pescuit, liniste absoluta.',
        200.00, 4, 0)
    RETURNING id INTO v_temp_id;
    v_camping_ids := array_append(v_camping_ids, v_temp_id);

    INSERT INTO campings (created_by, name, slug, type, region, address, latitude, longitude, description, price_per_night, capacity, approval_status, admin_feedback)
    VALUES (v_org_delta, 'Vama Veche Beach Camp', 'vama-veche-beach-camp', 'tent', 'Constanta',
        'Vama Veche, Constanta', 43.74800, 28.57600,
        'Camping pe plaja, la 50m de mare. Atmosfera boema, dusuri reci, terase apropiate. Perfect pentru vara.',
        60.00, 4, 2, 'Te rugam sa atasezi autorizatia de functionare valabila pe acest an si poze clare cu dusurile.')
    RETURNING id INTO v_temp_id;
    v_camping_ids := array_append(v_camping_ids, v_temp_id);

    INSERT INTO campings (created_by, name, slug, type, region, address, latitude, longitude, description, price_per_night, capacity, approval_status)
    VALUES (v_org_carpati, 'Retezat Mountain Lodge', 'retezat-mountain-lodge', 'cabin', 'Hunedoara',
        'Carnic, Rau de Mori, Hunedoara', 45.36670, 22.85000,
        'Cabane montane la intrarea in Parcul National Retezat. Ideal ca tabara de baza pentru tururi de 2-5 zile.',
        150.00, 8, 1)
    RETURNING id INTO v_temp_id;
    v_camping_ids := array_append(v_camping_ids, v_temp_id);

    INSERT INTO campings (created_by, name, slug, type, region, address, latitude, longitude, description, price_per_night, capacity, approval_status)
    VALUES (v_org_carpati, 'Ceahlau Tent Site', 'ceahlau-tent-site', 'tent', 'Neamt',
        'Durau, Ceahlau, Neamt', 46.97800, 25.93400,
        'Loc de campare pentru corturi la baza Ceahlaului. Apa, toalete, foc de tabara permis in vatra special amenajata.',
        40.00, 4, 1)
    RETURNING id INTO v_temp_id;
    v_camping_ids := array_append(v_camping_ids, v_temp_id);

    INSERT INTO campings (created_by, name, slug, type, region, address, latitude, longitude, description, price_per_night, capacity, approval_status)
    VALUES (v_org_carpati, 'Cheile Turzii Adventure Camp', 'cheile-turzii-adventure-camp', 'tent', 'Cluj',
        'Petrestii de Jos, Cluj', 46.56700, 23.69200,
        'Camping pentru pasionati de catarare. Acces direct la rutele din Cheile Turzii. Echipament la inchiriere.',
        55.00, 3, 1)
    RETURNING id INTO v_temp_id;
    v_camping_ids := array_append(v_camping_ids, v_temp_id);

    INSERT INTO campings (created_by, name, slug, type, region, address, latitude, longitude, description, price_per_night, capacity, approval_status)
    VALUES (v_org_carpati, 'Lacul Sfanta Ana Glamping', 'lacul-sfanta-ana-glamping', 'glamping', 'Harghita',
        'Tusnad, Harghita', 46.12500, 25.88800,
        'Yurturi si corturi safari in padurea de langa Lacul Sfanta Ana, singurul lac vulcanic din Romania. Sauna finlandeza disponibila.',
        320.00, 2, 1)
    RETURNING id INTO v_temp_id;
    v_camping_ids := array_append(v_camping_ids, v_temp_id);

    RAISE NOTICE 'Seeding camping media...';

    FOREACH v_camping_id IN ARRAY v_camping_ids LOOP
        v_num_imgs := 2 + floor(random() * 3)::INT;  -- 2..4 poze
        FOR i IN 1..v_num_imgs LOOP
            INSERT INTO camping_media (camping_id, type, url)
            VALUES (v_camping_id, 'image', l_media_urls[1 + floor(random() * array_length(l_media_urls, 1))::INT]);
        END LOOP;
    END LOOP;

    RAISE NOTICE 'Seeding environments and facilities...';

    FOR i IN 1..array_length(l_env_cidx, 1) LOOP
        BEGIN
            INSERT INTO camping_environments (camping_id, environment_name)
            VALUES (v_camping_ids[l_env_cidx[i]], l_env_name[i]);
        EXCEPTION WHEN unique_violation THEN NULL;
        END;
    END LOOP;

    FOREACH v_camping_id IN ARRAY v_camping_ids LOOP
        v_num_items := 2 + floor(random() * 4)::INT;  -- 2..5 facilități
        FOR i IN 1..v_num_items LOOP
            BEGIN
                INSERT INTO camping_facilities (camping_id, facility_name)
                VALUES (v_camping_id, l_facilities[1 + floor(random() * array_length(l_facilities, 1))::INT]);
            EXCEPTION WHEN unique_violation THEN NULL;
            END;
        END LOOP;
    END LOOP;

    RAISE NOTICE 'Seeding bookings...';

    FOR s IN 1..array_length(l_bk_status, 1) LOOP
        FOR i IN 1..l_bk_count[s] LOOP
            DECLARE
                v_uid     INT  := v_user_ids[1 + floor(random()*array_length(v_user_ids,1))::INT];
                v_cid     INT  := v_camping_ids[1 + floor(random()*array_length(v_camping_ids,1))::INT];
                v_guests  INT  := 1 + floor(random()*4)::INT;   -- 1..4
                v_nights  INT  := 1 + floor(random()*6)::INT;   -- 1..7 nopți
                v_checkin DATE;
            BEGIN
                IF l_bk_status[s] = 'completed' THEN
                    v_checkin := CURRENT_DATE - (30 + floor(random()*335))::INT;  -- trecut
                ELSE
                    v_checkin := CURRENT_DATE + (5 + floor(random()*120))::INT;   -- viitor
                END IF;

                INSERT INTO bookings (user_id, camping_id, check_in, check_out, guests, status)
                VALUES (v_uid, v_cid, v_checkin, v_checkin + v_nights, v_guests, l_bk_status[s]::booking_status);
            END;
        END LOOP;
    END LOOP;

    RAISE NOTICE 'Seeding reviews...';

    FOR v_rec IN
        SELECT id, user_id, camping_id FROM bookings WHERE status = 'completed'
    LOOP
        IF random() < 0.8 THEN
            BEGIN
                INSERT INTO reviews (user_id, camping_id, booking_id, rating, content)
                VALUES (
                    v_rec.user_id,
                    v_rec.camping_id,
                    v_rec.id,
                    3 + floor(random()*3)::INT,  -- 3..5
                    l_rev_content[1 + floor(random()*array_length(l_rev_content,1))::INT]
                );
            EXCEPTION WHEN unique_violation THEN NULL;
            END;
        END IF;
    END LOOP;

    RAISE NOTICE 'Seeding user sections...';

    FOR i IN 1..array_length(l_sec_uidx, 1) LOOP
        INSERT INTO user_sections (user_id, name)
        VALUES (v_user_ids[l_sec_uidx[i]], l_sec_name[i])
        RETURNING id INTO v_section_id;

        -- 2..4 campinguri random, fără duplicate în aceeași secțiune
        FOR j IN 1..(2 + floor(random()*3)::INT) LOOP
            BEGIN
                INSERT INTO section_campings (section_id, camping_id)
                VALUES (v_section_id, v_camping_ids[1 + floor(random()*array_length(v_camping_ids,1))::INT]);
            EXCEPTION WHEN unique_violation THEN NULL;
            END;
        END LOOP;
    END LOOP;

    RAISE NOTICE 'Seeding user bans...';

    INSERT INTO user_bans (user_id, reason, banned_until, banned_by, is_active)
    VALUES (v_banned_id, 'Comportament toxic, spam si limbaj inadecvat in sectiunea de recenzii.', NULL, v_admin_id, TRUE);

    RAISE NOTICE 'Seeding user preferences...';

    INSERT INTO user_preferences (user_id, camping_types, travel_styles, preferred_zones)
    VALUES (v_user_ids[1], ARRAY['tent','wild'], ARRAY['trekking','aventura'], ARRAY['munte','pădure']);

    INSERT INTO user_preferences (user_id, camping_types, travel_styles, preferred_zones)
    VALUES (v_user_ids[2], ARRAY['glamping','cabin'], ARRAY['relaxare','natura'], ARRAY['lângă lac','deltă']);

    RAISE NOTICE 'Seeding organizer verifications...';

    INSERT INTO organizer_verifications (
        user_id, first_name, last_name, business_type, company_name, registration_number,
        address_street, address_number, address_city, contact_phone, contact_email, status
    ) VALUES
    (v_org_carpati, 'Ionut', 'Carpati', 'SRL', 'Carpathian Camps SRL', 'RO123456',
     'Str. Cumpana', '10', 'Curtea de Arges', '0722111222', 'org@carpati.ro', 'approved'),
    (v_org_delta, 'Marin', 'Pescarul', 'PFA', 'Delta Adventures PFA', 'RO987654',
     'Str. Dunarii', '4', 'Tulcea', '0733444555', 'org@delta.ro', 'approved'),
    (v_user_ids[1], 'Mihai', 'Popescu', 'SRL', 'Mihai Camping Group', 'RO112233',
     'Str. Eroilor', '50', 'Bucuresti', '0744999888', 'mihai@gmail.com', 'pending');

    RAISE NOTICE 'Seeding contact messages...';

    INSERT INTO contact_messages (name, email, phone, message) VALUES
        ('Maria Pop', 'maria@example.com', '+40712345678',
         'Salut, vreau sa intreb daca puteti adauga si platile cu card pe site.'),
        ('Ion Vasile', 'ion.vasile@example.com', NULL,
         'Mi-a placut foarte mult experienta. Continuati!'),
        ('Elena Diaconescu', 'elena.d@example.com', '+40733111222',
         'Intampin o problema la rezervare. Pot sa va trimit un screenshot?');

    RAISE NOTICE '=== SEED COMPLETE ===';
END
$seed$;
