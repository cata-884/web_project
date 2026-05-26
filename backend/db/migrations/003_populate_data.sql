CREATE EXTENSION IF NOT EXISTS pgcrypto;

-- Cleanup (incluzând noile tabele)
TRUNCATE
    organizer_verifications, camping_facilities, camping_environments, section_campings, user_sections, review_media, reviews, bookings,
    camping_media, campings, contact_messages, sesiuni, users
RESTART IDENTITY CASCADE;

DO $seed$
DECLARE
    -- constante
    c_pass_admin CONSTANT TEXT := 'admin1234';
    c_pass_user  CONSTANT TEXT := 'parola1234';
    c_bcrypt_cost CONSTANT INT := 10;

    -- array uri
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

    -- array uri pentru mediu și facilități
    l_environments TEXT[] := ARRAY['pădure', 'munte', 'lângă râu', 'lângă lac', 'plajă', 'pășune alpină'];
    l_facilities   TEXT[] := ARRAY['wi-fi', 'dușuri calde', 'toalete ecologice', 'electricitate', 'parcare', 'zonă foc de tabără', 'apă potabilă', 'restaurant'];
    v_num_items    INT;
    v_item_idx     INT;

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

    l_rev_title TEXT[] := ARRAY[
        'Experienta de neuitat!',
        'Perfect pentru relaxare',
        'Foarte bine, dar...',
        'Recomand cu placere',
        'Atmosfera magica',
        'Decent',
        'Locul perfect pentru deconectare',
        'Frumos si curat'
    ];
    l_rev_content TEXT[] := ARRAY[
        'Locul a depasit asteptarile. Peisaj spectaculos, gazdele prietenoase, totul a fost perfect. Recomand cu toata inima!',
        'Am avut parte de o vacanta extraordinara. Linistea naturii, confortul facilitatilor, totul a fost la nivel maxim.',
        'In general am avut o experienta placuta. Locul e frumos si curat. O singura observatie: drumul de acces e cam denivelat.',
        'Am stat 3 nopti si ne-am bucurat de fiecare moment. WiFi-ul era cam slab, dar intr-un camping nici nu prea ai nevoie.',
        'Stelele noaptea, focul de tabara, cantecul greierilor... exact ce cautam. Vom reveni cu siguranta.',
        'Totul a fost OK, fara sa iasa in evidenta. Pentru pret e corect. Poate cu mai multe facilitati ar fi excelent.',
        'Departe de aglomeratia orasului, exact ce ne trebuia. Aerul curat si peisajele de poveste compenseaza orice mic inconvenient.',
        'Aprecierile noastre pentru gazde. Totul a fost organizat impecabil. Vom reveni anul viitor.'
    ];
    l_rev_rating INT[] := ARRAY[5, 5, 4, 4, 5, 3, 5, 4];

    -- variabile pentru id uri
    v_admin_id      INT;
    v_org_carpati   INT;
    v_org_delta     INT;
    v_banned_id     INT;

    v_user_ids      INT[] := ARRAY[]::INT[];
    v_camping_ids   INT[] := ARRAY[]::INT[];
    v_completed_ids INT[] := ARRAY[]::INT[];

    -- helpers
    v_temp_id    INT;
    v_camping_id INT;
    v_section_id INT;
    v_num_imgs   INT;
    v_idx        INT;
    v_rec        RECORD;
    i            INT;

BEGIN
    -- users
    RAISE NOTICE 'Seeding users...';

    -- admin
    INSERT INTO users (username, email, password_hash, full_name, role)
    VALUES (
        'admin',
        'admin@cat.ro',
        crypt(c_pass_admin, gen_salt('bf', c_bcrypt_cost)),
        'Administrator Sistem',
        'admin'
    )
    RETURNING id INTO v_admin_id;

    -- Organizatori (rolul 'organizer')
    INSERT INTO users (username, email, password_hash, full_name, role)
    VALUES (
        'org_carpati',
        'org@carpati.ro',
        crypt(c_pass_user, gen_salt('bf', c_bcrypt_cost)),
        'Carpathian Camps SRL',
        'organizer'
    )
    RETURNING id INTO v_org_carpati;

    INSERT INTO users (username, email, password_hash, full_name, role)
    VALUES (
        'org_delta',
        'org@delta.ro',
        crypt(c_pass_user, gen_salt('bf', c_bcrypt_cost)),
        'Delta Adventures',
        'organizer'
    )
    RETURNING id INTO v_org_delta;

    -- Useri normali (loop prin array)
    FOR i IN 1..array_length(l_user_uname, 1) LOOP
        INSERT INTO users (username, email, password_hash, full_name, avatar_url, role)
        VALUES (
            l_user_uname[i],
            l_user_email[i],
            crypt(c_pass_user, gen_salt('bf', c_bcrypt_cost)),
            l_user_full[i],
            l_user_avtr[i],
            'user'
        )
        RETURNING id INTO v_temp_id;
        v_user_ids := array_append(v_user_ids, v_temp_id);
    END LOOP;

    -- User banat (pentru testare moderare)
    INSERT INTO users (username, email, password_hash, full_name, role)
    VALUES (
        'troll',
        'banned@gmail.com',
        crypt(c_pass_user, gen_salt('bf', c_bcrypt_cost)),
        'Troll User',
        'user'
    )
    RETURNING id INTO v_banned_id;

    -- campings (locatii din Romania, coordonate exacte)
    RAISE NOTICE 'Seeding campings...';

    -- Transfagarasan (tent, Arges) - Aprobat (1)
    INSERT INTO campings (created_by, name, slug, type, region, address, latitude, longitude, description, price_per_night, capacity, is_published, approval_status)
    VALUES (v_org_carpati, 'Valea Verde - Transfagarasan', 'valea-verde-transfagarasan', 'tent', 'Arges',
        'DN7C km 104, Cumpana, Arges', 45.60240, 24.61680,
        'Camping situat pe celebra sosea Transfagarasan, la altitudine de 2034m. Vedere spectaculoasa catre lacul Balea. Locuri pentru corturi si parcare pentru rulote.',
        80.00, 4, TRUE, 1)
    RETURNING id INTO v_temp_id;
    v_camping_ids := array_append(v_camping_ids, v_temp_id);

    -- Cheile Bicazului (glamping, Neamt) - Aprobat (1)
    INSERT INTO campings (created_by, name, slug, type, region, address, latitude, longitude, description, price_per_night, capacity, is_published, approval_status)
    VALUES (v_org_carpati, 'Cheile Bicazului Glamping', 'cheile-bicazului-glamping', 'glamping', 'Neamt',
        'Bicazu Ardelean, Neamt', 46.81940, 25.85100,
        'Experienta de lux in natura. Corturi safari cu paturi, mobilier si electricitate. Acces direct la traseele de hiking. Mic dejun inclus.',
        280.00, 2, TRUE, 1)
    RETURNING id INTO v_temp_id;
    v_camping_ids := array_append(v_camping_ids, v_temp_id);

    -- Padis (wild, Bihor) - Aprobat (1)
    INSERT INTO campings (created_by, name, slug, type, region, address, latitude, longitude, description, price_per_night, capacity, is_published, approval_status)
    VALUES (v_org_carpati, 'Padis Wild Camp', 'padis-wild-camp', 'wild', 'Bihor',
        'Platoul Padis, Bihor', 46.55833, 22.71667,
        'Camping salbatic in muntii Apuseni. Doar pentru aventurieri experimentati. Fara facilitati, doar apa curenta dintr-un izvor.',
        25.00, 10, TRUE, 1)
    RETURNING id INTO v_temp_id;
    v_camping_ids := array_append(v_camping_ids, v_temp_id);

    -- Bucegi (rv, Prahova) - Aprobat (1)
    INSERT INTO campings (created_by, name, slug, type, region, address, latitude, longitude, description, price_per_night, capacity, is_published, approval_status)
    VALUES (v_org_carpati, 'Bucegi RV Park', 'bucegi-rv-park', 'rv', 'Prahova',
        'DN1 km 134, Busteni, Prahova', 45.41060, 25.53670,
        'Parc dedicat rulotelor si autorulotelor la poalele Bucegilor. Curent 220V, apa, evacuare ape uzate, WiFi. Aproape de telecabina Busteni.',
        120.00, 6, TRUE, 1)
    RETURNING id INTO v_temp_id;
    v_camping_ids := array_append(v_camping_ids, v_temp_id);

    -- Delta Dunarii (cabin, Tulcea) - Pending (0)
    INSERT INTO campings (created_by, name, slug, type, region, address, latitude, longitude, description, price_per_night, capacity, is_published, approval_status)
    VALUES (v_org_delta, 'Delta Dunarii Eco Camp', 'delta-dunarii-eco-camp', 'cabin', 'Tulcea',
        'Crisan, Tulcea', 45.17500, 29.46500,
        'Casute pe stalpi in mijlocul deltei. Acces doar pe apa. Tururi cu barca incluse. Observatii de pasari, pescuit, liniste absoluta.',
        200.00, 4, TRUE, 0)
    RETURNING id INTO v_temp_id;
    v_camping_ids := array_append(v_camping_ids, v_temp_id);

    -- Vama Veche (tent, Constanta) - Respins cu feedback (2)
    INSERT INTO campings (created_by, name, slug, type, region, address, latitude, longitude, description, price_per_night, capacity, is_published, approval_status, admin_feedback)
    VALUES (v_org_delta, 'Vama Veche Beach Camp', 'vama-veche-beach-camp', 'tent', 'Constanta',
        'Vama Veche, Constanta', 43.74800, 28.57600,
        'Camping pe plaja, la 50m de mare. Atmosfera boema, dusuri reci, terase apropiate. Perfect pentru vara.',
        60.00, 4, TRUE, 2, 'Te rugam sa atasezi autorizatia de functionare valabila pe acest an si poze clare cu dusurile.')
    RETURNING id INTO v_temp_id;
    v_camping_ids := array_append(v_camping_ids, v_temp_id);

    -- Retezat (cabin, Hunedoara) - Aprobat (1)
    INSERT INTO campings (created_by, name, slug, type, region, address, latitude, longitude, description, price_per_night, capacity, is_published, approval_status)
    VALUES (v_org_carpati, 'Retezat Mountain Lodge', 'retezat-mountain-lodge', 'cabin', 'Hunedoara',
        'Carnic, Rau de Mori, Hunedoara', 45.36670, 22.85000,
        'Cabane montane la intrarea in Parcul National Retezat. Ideal ca tabara de baza pentru tururi de 2-5 zile.',
        150.00, 8, TRUE, 1)
    RETURNING id INTO v_temp_id;
    v_camping_ids := array_append(v_camping_ids, v_temp_id);

    -- Ceahlau (tent, Neamt) - Aprobat (1)
    INSERT INTO campings (created_by, name, slug, type, region, address, latitude, longitude, description, price_per_night, capacity, is_published, approval_status)
    VALUES (v_org_carpati, 'Ceahlau Tent Site', 'ceahlau-tent-site', 'tent', 'Neamt',
        'Durau, Ceahlau, Neamt', 46.97800, 25.93400,
        'Loc de campare pentru corturi la baza Ceahlaului. Apa, toalete, foc de tabara permis in vatra special amenajata.',
        40.00, 4, TRUE, 1)
    RETURNING id INTO v_temp_id;
    v_camping_ids := array_append(v_camping_ids, v_temp_id);

    -- Cheile Turzii (tent, Cluj) - Aprobat (1)
    INSERT INTO campings (created_by, name, slug, type, region, address, latitude, longitude, description, price_per_night, capacity, is_published, approval_status)
    VALUES (v_org_carpati, 'Cheile Turzii Adventure Camp', 'cheile-turzii-adventure-camp', 'tent', 'Cluj',
        'Petrestii de Jos, Cluj', 46.56700, 23.69200,
        'Camping pentru pasionati de catarare. Acces direct la rutele din Cheile Turzii. Echipament la inchiriere.',
        55.00, 3, TRUE, 1)
    RETURNING id INTO v_temp_id;
    v_camping_ids := array_append(v_camping_ids, v_temp_id);

    -- Lacul Sfanta Ana (glamping, Harghita) - Aprobat (1)
    INSERT INTO campings (created_by, name, slug, type, region, address, latitude, longitude, description, price_per_night, capacity, is_published, approval_status)
    VALUES (v_org_carpati, 'Lacul Sfanta Ana Glamping', 'lacul-sfanta-ana-glamping', 'glamping', 'Harghita',
        'Tusnad, Harghita', 46.12500, 25.88800,
        'Yurturi si corturi safari in padurea de langa Lacul Sfanta Ana, singurul lac vulcanic din Romania. Sauna finlandeza disponibila.',
        320.00, 2, TRUE, 1)
    RETURNING id INTO v_temp_id;
    v_camping_ids := array_append(v_camping_ids, v_temp_id);

    -- media campings (poze)
    RAISE NOTICE 'Seeding camping media...';

    FOREACH v_camping_id IN ARRAY v_camping_ids LOOP
        v_num_imgs := 2 + floor(random() * 3)::INT;  -- 2, 3 sau 4
        FOR i IN 1..v_num_imgs LOOP
            v_idx := 1 + floor(random() * array_length(l_media_urls, 1))::INT;
            INSERT INTO camping_media (camping_id, type, url, sort_order)
            VALUES (v_camping_id, 'image', l_media_urls[v_idx], i - 1);
        END LOOP;
    END LOOP;

    -- mediu și facilități
    RAISE NOTICE 'Seeding environments and facilities...';

    FOREACH v_camping_id IN ARRAY v_camping_ids LOOP
        -- Mediu (1-2 aleatorii)
        v_num_items := 1 + floor(random() * 2)::INT;
        FOR i IN 1..v_num_items LOOP
            v_idx := 1 + floor(random() * array_length(l_environments, 1))::INT;
            BEGIN
                INSERT INTO camping_environments (camping_id, environment_name)
                VALUES (v_camping_id, l_environments[v_idx]);
            EXCEPTION WHEN unique_violation THEN NULL; END;
        END LOOP;

        -- Facilități (2-5 aleatorii)
        v_num_items := 2 + floor(random() * 4)::INT;
        FOR i IN 1..v_num_items LOOP
            v_idx := 1 + floor(random() * array_length(l_facilities, 1))::INT;
            BEGIN
                INSERT INTO camping_facilities (camping_id, facility_name)
                VALUES (v_camping_id, l_facilities[v_idx]);
            EXCEPTION WHEN unique_violation THEN NULL; END;
        END LOOP;
    END LOOP;

    -- bookinguri
    RAISE NOTICE 'Seeding bookings...';

    -- in trecut
    INSERT INTO bookings (user_id, camping_id, check_in, check_out, guests, total_price, status) VALUES
        (v_user_ids[1], v_camping_ids[1],  '2025-08-10', '2025-08-13', 2, 240.00, 'completed'),
        (v_user_ids[1], v_camping_ids[7],  '2025-09-15', '2025-09-18', 4, 450.00, 'completed'),
        (v_user_ids[2], v_camping_ids[2],  '2025-07-20', '2025-07-22', 2, 560.00, 'completed'),
        (v_user_ids[2], v_camping_ids[5],  '2025-08-05', '2025-08-08', 2, 600.00, 'completed'),
        (v_user_ids[3], v_camping_ids[4],  '2025-09-01', '2025-09-04', 2, 360.00, 'completed'),
        (v_user_ids[3], v_camping_ids[6],  '2025-08-22', '2025-08-25', 4, 180.00, 'completed'),
        (v_user_ids[4], v_camping_ids[3],  '2025-09-10', '2025-09-12', 2,  50.00, 'completed'),
        (v_user_ids[4], v_camping_ids[9],  '2025-07-15', '2025-07-17', 2, 110.00, 'completed'),
        (v_user_ids[5], v_camping_ids[10], '2025-08-28', '2025-08-30', 2, 640.00, 'completed'),
        (v_user_ids[5], v_camping_ids[8],  '2026-04-05', '2026-04-07', 4,  80.00, 'completed');

    -- viitoare confirmate
    INSERT INTO bookings (user_id, camping_id, check_in, check_out, guests, total_price, status) VALUES
        (v_user_ids[1], v_camping_ids[6],  '2026-07-15', '2026-07-18', 2, 180.00, 'confirmed'),
        (v_user_ids[2], v_camping_ids[1],  '2026-08-05', '2026-08-08', 2, 240.00, 'confirmed'),
        (v_user_ids[3], v_camping_ids[10], '2026-06-20', '2026-06-22', 2, 640.00, 'confirmed'),
        (v_user_ids[4], v_camping_ids[5],  '2026-08-15', '2026-08-18', 4, 600.00, 'confirmed'),
        (v_user_ids[5], v_camping_ids[2],  '2026-07-25', '2026-07-27', 2, 560.00, 'confirmed');

    -- viitoare neconfirmate
    INSERT INTO bookings (user_id, camping_id, check_in, check_out, guests, total_price, status) VALUES
        (v_user_ids[1], v_camping_ids[3], '2026-09-10', '2026-09-12', 3,  50.00, 'pending'),
        (v_user_ids[3], v_camping_ids[9], '2026-06-05', '2026-06-07', 2, 110.00, 'pending'),
        (v_user_ids[5], v_camping_ids[7], '2026-08-22', '2026-08-25', 4, 450.00, 'pending');

    -- bookinguri anulate
    INSERT INTO bookings (user_id, camping_id, check_in, check_out, guests, total_price, status) VALUES
        (v_user_ids[2], v_camping_ids[4], '2025-10-10', '2025-10-12', 2, 240.00, 'cancelled'),
        (v_user_ids[4], v_camping_ids[1], '2026-05-05', '2026-05-07', 2, 160.00, 'cancelled');

    -- recenzii pe completed bookings
    RAISE NOTICE 'Seeding reviews...';

    i := 0;
    FOR v_rec IN
        SELECT id, user_id, camping_id FROM bookings WHERE status = 'completed'
    LOOP
        i := i + 1;
        IF random() < 0.8 THEN
            BEGIN
                v_idx := 1 + ((i - 1) % array_length(l_rev_title, 1));
                INSERT INTO reviews (user_id, camping_id, booking_id, rating, title, content)
                VALUES (
                    v_rec.user_id,
                    v_rec.camping_id,
                    v_rec.id,
                    l_rev_rating[v_idx],
                    l_rev_title[v_idx],
                    l_rev_content[v_idx]
                );
            EXCEPTION
                WHEN unique_violation THEN
                    -- Userul a recenzat deja acest camping (UNIQUE constraint pe user_id, camping_id)
                    NULL;
            END;
        END IF;
    END LOOP;

    -- sectiuni personale
    RAISE NOTICE 'Seeding user sections...';

    -- Mihai (user 1) — 2 sectiuni
    INSERT INTO user_sections (user_id, name, color)
    VALUES (v_user_ids[1], 'Favorite', '#EF6A00')
    RETURNING id INTO v_section_id;
    INSERT INTO section_campings (section_id, camping_id) VALUES
        (v_section_id, v_camping_ids[1]),
        (v_section_id, v_camping_ids[2]),
        (v_section_id, v_camping_ids[5]);

    INSERT INTO user_sections (user_id, name, color)
    VALUES (v_user_ids[1], 'Pentru anul viitor', '#84AC00')
    RETURNING id INTO v_section_id;
    INSERT INTO section_campings (section_id, camping_id) VALUES
        (v_section_id, v_camping_ids[10]),
        (v_section_id, v_camping_ids[7]);

    -- Ana (user 2)
    INSERT INTO user_sections (user_id, name, color)
    VALUES (v_user_ids[2], 'Locuri vizitate', '#4A90D9')
    RETURNING id INTO v_section_id;
    INSERT INTO section_campings (section_id, camping_id) VALUES
        (v_section_id, v_camping_ids[2]),
        (v_section_id, v_camping_ids[5]);

    -- Radu (user 3)
    INSERT INTO user_sections (user_id, name, color)
    VALUES (v_user_ids[3], 'Trekking dream', '#9B59B6')
    RETURNING id INTO v_section_id;
    INSERT INTO section_campings (section_id, camping_id) VALUES
        (v_section_id, v_camping_ids[7]),
        (v_section_id, v_camping_ids[3]),
        (v_section_id, v_camping_ids[8]);

    -- date formular organizatori
    RAISE NOTICE 'Seeding organizer verifications...';

    INSERT INTO organizer_verifications (
        user_id, first_name, last_name, business_type, company_name, registration_number,
        address_street, address_number, address_city, contact_phone, contact_email, status
    ) VALUES (
        v_org_carpati, 'Ionut', 'Carpati', 'SRL', 'Carpathian Camps SRL', 'RO123456',
        'Str. Cumpana', '10', 'Curtea de Arges', '0722111222', 'org@carpati.ro', 'approved'
    ), (
        v_org_delta, 'Marin', 'Pescarul', 'PFA', 'Delta Adventures PFA', 'RO987654',
        'Str. Dunarii', '4', 'Tulcea', '0733444555', 'org@delta.ro', 'approved'
    ), (
        v_user_ids[1], 'Mihai', 'Popescu', 'SRL', 'Mihai Camping Group', 'RO112233',
        'Str. Eroilor', '50', 'Bucuresti', '0744999888', 'mihai@gmail.com', 'pending'
    );

    -- mesaje de contact
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

--recap la datele create (actualizat)
SELECT 'Users'             AS entity, COUNT(*) FROM users
UNION ALL SELECT 'Campings',          COUNT(*) FROM campings
UNION ALL SELECT 'Camping environments',COUNT(*) FROM camping_environments
UNION ALL SELECT 'Camping facilities',  COUNT(*) FROM camping_facilities
UNION ALL SELECT 'Camping media',     COUNT(*) FROM camping_media
UNION ALL SELECT 'Bookings (total)',  COUNT(*) FROM bookings
UNION ALL SELECT '  - completed',     COUNT(*) FROM bookings WHERE status = 'completed'
UNION ALL SELECT '  - confirmed',     COUNT(*) FROM bookings WHERE status = 'confirmed'
UNION ALL SELECT '  - pending',       COUNT(*) FROM bookings WHERE status = 'pending'
UNION ALL SELECT '  - cancelled',     COUNT(*) FROM bookings WHERE status = 'cancelled'
UNION ALL SELECT 'Reviews',           COUNT(*) FROM reviews
UNION ALL SELECT 'User sections',     COUNT(*) FROM user_sections
UNION ALL SELECT 'Section campings',  COUNT(*) FROM section_campings
UNION ALL SELECT 'Organizer verifications', COUNT(*) FROM organizer_verifications
UNION ALL SELECT 'Contact messages',  COUNT(*) FROM contact_messages;