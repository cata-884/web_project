# TODO Final — CaT (Camping Info Web Tool)

Ordinea recomandata: P0 → P1 → P2 → P3.
Daca timpul nu ajunge: nu sari peste P0, sunt cerinte explicite.

---

## P0 — Cerinte EXPLICITE din tema/regulament neimplementate

Fara astea, pierzi puncte direct, nu interpretativ.

### 1. Rescrie landing page-ul (HerbaLink → CaT)

- [ ] `frontend/index.html` — TOT continutul vorbeste despre plante/ierbar. De rescris:
  - `<title>` — "CaT | Camping Info Web Tool"
  - Hero: h1, paragraf, statistici (in loc de "Plants Documented" → "Locatii de camping", "Recenzii", "Rezervari", "Utilizatori activi")
  - Sectiunea about — text + iconuri pentru: cataloage de camping, harta interactiva, rezervari, recenzii
  - Sectiunea reviews — comentarii despre experiente camping, nu botanica
  - Sectiunea team — pastreaza dar adapteaza
  - Sectiunea footer — schimba "HerbaLink" → "CaT" peste tot
- [ ] `frontend/pages/aboutUs.html` — la fel, scrie textele pentru camping, nu plante
- [ ] Cauta global "HerbaLink", "botanical", "plant", "herbarium" si elimina-le
- [ ] `frontend/assets/` — verifica daca pozele se potrivesc cu tema (probabil sunt OK din ce am vazut in cod)

### 2. OAuth (Google) — cerinta explicita a temei 6

Tema 6 spune literal: *"Operatiile de autorizare si autentificare vor recurge la OAuth."*
DB-ul are coloanele `oauth_provider` si `oauth_id` deja, nu trebuie migratie.

- [ ] Inregistreaza o aplicatie pe https://console.cloud.google.com (OAuth 2.0 Client ID, tip Web)
  - Authorized redirect URIs: `http://localhost/cat/public/api/auth/oauth/google/callback`
- [ ] Adauga in `backend/config/app.php`:
  ```php
  define('GOOGLE_CLIENT_ID', getenv('GOOGLE_CLIENT_ID') ?: '');
  define('GOOGLE_CLIENT_SECRET', getenv('GOOGLE_CLIENT_SECRET') ?: '');
  define('GOOGLE_REDIRECT_URI', 'http://localhost/cat/public/api/auth/oauth/google/callback');
  ```
- [ ] In `docker-compose.yml`, sectiunea `backend`, adauga environment:
  ```yaml
  GOOGLE_CLIENT_ID: "..."
  GOOGLE_CLIENT_SECRET: "..."
  ```
- [ ] In `AuthController` adauga 2 metode:
  - `oauthGoogleStart()` — face redirect spre `https://accounts.google.com/o/oauth2/v2/auth?client_id=...&redirect_uri=...&response_type=code&scope=email%20profile`
  - `oauthGoogleCallback()` — primeste `?code=...`, face POST la `https://oauth2.googleapis.com/token` (cu curl/file_get_contents) pentru access_token, apoi GET la `https://www.googleapis.com/oauth2/v2/userinfo` pentru date user. Cauta user dupa `oauth_id`, creeaza-l daca lipseste, genereaza sesiune, redirect spre frontend cu tokenul in URL (sau seteaza cookie).
- [ ] In `routes.php` adauga:
  ```php
  'get /api/auth/oauth/google'           => ['AuthController', 'oauthGoogleStart'],
  'get /api/auth/oauth/google/callback'  => ['AuthController', 'oauthGoogleCallback'],
  ```
- [ ] In `frontend/pages/auth.html` — butonul "Google" sa fie:
  ```html
  <a href="http://localhost/cat/public/api/auth/oauth/google" class="btn-social">G Google</a>
  ```
- [ ] Sterge butonul "Apple" daca nu-l implementezi (mai bine fara decat ne-functional)

### 3. Statistici admin (SVG + PDF) — cerinta explicita a temei

Tema 6: *"platforma va oferi statistici textuale si vizuale (minimal SVG si PDF)"*.

- [ ] Creeaza `backend/app/controllers/StatsController.php` cu endpoint-uri`:
  - `GET /api/admin/stats/summary` — JSON cu: nr_users, nr_campings, nr_bookings_pending/confirmed/completed/cancelled, total_revenue, top_regions, top_campings_by_rating, bookings_per_month (ultimele 12 luni)
  - `GET /api/admin/stats/chart.svg?type=bookings_per_month` — returneaza direct `Content-Type: image/svg+xml` cu bar chart construit manual cu `<svg><rect /><text /></svg>`. Fara biblioteca.
  - `GET /api/admin/stats/report.pdf` — folosind dompdf (install: `composer require dompdf/dompdf`) primeste un HTML cu tabele si SVG-uri si scoate PDF.
- [ ] Inregistreaza rutele in `backend/config/routes.php`
- [ ] In Dockerfile-ul backend, asigura-te ca composer e instalat si ruleaza `composer install`. SAU descarca dompdf direct ca .phar / vendor manual daca ai probleme.
- [ ] In modulul admin UI (vezi P0 #5) adauga o pagina "Statistici" care:
  - face fetch la `/summary` si afiseaza numerele in carduri
  - embed-uieste `<object data="/api/admin/stats/chart.svg" type="image/svg+xml"></object>`
  - are un buton "Descarca raport PDF" care face download la `/report.pdf`

### 4. Import/Export CSV + JSON — cerinta generala obligatorie

Regulament: *"Import/export de date folosind formate deschise — minim, CSV (Comma Separated Values) si JSON (JavaScript Object Notation)."*

- [ ] Creeaza `backend/app/controllers/ExportController.php` cu:
  - `GET /api/admin/export/campings.csv` — `header('Content-Type: text/csv'); header('Content-Disposition: attachment; filename=...'); $out = fopen('php://output', 'w'); fputcsv($out, $headers); foreach (...) fputcsv($out, $row);`
  - `GET /api/admin/export/campings.json` — `header('Content-Type: application/json'); echo json_encode($campings);`
  - Acelasi pattern pentru: bookings, reviews, users (fara parole)
- [ ] Creeaza `backend/app/controllers/ImportController.php` cu:
  - `POST /api/admin/import/campings` — primeste fisier multipart, accepta CSV sau JSON dupa extension/MIME, valideaza, face insert. Returneaza nr de randuri inserate + erori per rand.
- [ ] In modulul admin adauga butoane "Exporta CSV", "Exporta JSON" si un upload pentru import.

### 5. Modul propriu de administrare (UI)

Regulament: *"Existenta unui modul propriu de administrare a aplicatiei Web."*
Controllerele AdminController + OrganizersController exista, doar UI-ul lipseste.

- [ ] Creeaza `frontend/pages/admin/dashboard.html` cu layout-ul existent (sidebar + content), avand tab-uri:
  - **Useri**: lista cu filtre (`/api/admin/users`), butoane Ban/Unban, modal pentru motivul ban-ului. Click pe user → istoric ban-uri (`/api/admin/users/{id}/bans`).
  - **Cereri organizer**: lista pending (`/api/organizers/pending`), butoane Approve / Reject (cu motiv).
  - **Statistici**: vezi P0 #3 (carduri + SVG + buton PDF).
  - **Export/Import**: butoanele de la P0 #4.
- [ ] Creeaza `frontend/js/admin.js` care:
  - Pe load verifica `user.role === 'admin'`, daca nu — redirect
  - Foloseste `api.get/post/delete` ca celelalte pagini
- [ ] Adauga link spre admin in sidebar-ul din `account.html` doar daca user-ul e admin

### 6. Multimedia audio/video la recenzii

Tema 6: *"in format multimedia (fotografii, audio, video)"*.

- [ ] In `backend/config/routes.php` inregistreaza rutele pentru MediaController (deja exista in fisier):
  ```php
  'post /api/campings/(\d+)/media'    => ['MediaController', 'uploadCampingMedia'],
  'post /api/reviews/(\d+)/media'     => ['MediaController', 'uploadReviewMedia'],
  'delete /api/media/camping/(\d+)'   => ['MediaController', 'destroyCampingMedia'],
  'delete /api/media/review/(\d+)'    => ['MediaController', 'destroyReviewMedia'],
  ```
- [ ] In `frontend/pages/account/account.html` la sectiunea review (`#review-section`):
  - Schimba `accept="image/*,video/*"` → `accept="image/*,audio/*,video/*"`
  - In JS la trimite recenzia: dupa POST-ul de review, fa N upload-uri (FormData multipart) pe `/api/reviews/{reviewId}/media`
- [ ] La afisarea unei recenzii pe pagina camping, randeaza media:
  - `image` → `<img src="...">`
  - `audio` → `<audio controls src="..."></audio>`
  - `video` → `<video controls src="..."></video>`
- [ ] In nginx `default.conf` asigura-te ca `/uploads/` e servit (acum probabil nu e, pentru ca nginx serveste doar `/usr/share/nginx/html` care e frontend-ul). Cea mai simpla solutie: muta `uploads/` din `backend/public` si servete-l prin Apache direct, sau adauga in nginx un location `/uploads/ { proxy_pass http://backend; }`.

### 7. Overpass API (OpenStreetMap)

Tema 6: *"harta interactiva (via OpenStreetMap API — Overpass API)"*.
Acum folosesti doar tile-uri OSM, ce e cu totul altceva.

- [ ] In pagina detaliu camping (`frontend/js/camping-detail.js`), dupa initMiniMap(), fa un fetch la Overpass:
  ```js
  const query = `[out:json][timeout:25];
    (
      node["amenity"="drinking_water"](around:5000,${lat},${lng});
      node["tourism"="viewpoint"](around:5000,${lat},${lng});
      node["natural"="peak"](around:5000,${lat},${lng});
    );
    out body;`;
  const res = await fetch('https://overpass-api.de/api/interpreter', {
    method: 'POST',
    body: 'data=' + encodeURIComponent(query)
  });
  const data = await res.json();
  // Pune markere pe miniMap cu data.elements
  ```
- [ ] Afiseaza in pagina o sectiune "In apropiere" cu lista POI-urilor returnate de Overpass.
- [ ] Alternativ / suplimentar: pe harta principala, un buton "Sugereaza locuri de camping din OSM" care interogheaza `node["tourism"="camp_site"](bbox);`

---

## P1 — Bug-uri si inconsistente in cod (de reparat OBLIGATORIU)

### 8. Reviews complet stricate

- [ ] `frontend/js/camping-detail.js` linia ~145: schimba `GET /api/reviews?camping_id=X` → `GET /api/campings/${currentCamping.id}/reviews`
- [ ] `frontend/js/camping-detail.js` linia ~180 (`submitReview`): schimba `POST /api/reviews` cu body `{camping_id, ...}` → `POST /api/campings/${currentCamping.id}/reviews` cu body fara camping_id
- [ ] `frontend/js/camping-detail.js` linia ~166: schimba `r.comment` → `r.content` la randare
- [ ] `frontend/js/camping-detail.js` linia ~190: schimba `comment: comment` → `content: comment` in body-ul POST
- [ ] `frontend/js/camping-detail.js` linia ~165: schimba `r.author_name` → `r.username` (ca in raspunsul backend-ului)

### 9. Bookings — guests_count vs guests

- [ ] `frontend/js/camping-detail.js` linia ~205: schimba `guests_count: 2` → `guests: parseInt(document.getElementById('book-guests').value)`
- [ ] Adauga in formularul de booking un input pentru numar oaspeti:
  ```html
  <div class="form-group">
    <label>Numar oaspeti</label>
    <input type="number" id="book-guests" min="1" max="20" value="2">
  </div>
  ```
- [ ] Sterge `special_requests: ""` din body (nu exista coloana in DB)

### 10. Organizer verifications — schema vs cod incompatibile

- [ ] `backend/db/migrations/001_create_tables.sql` — ALEGE: fie schema, fie codul. Recomandare: schimba schema sa se potriveasca cu codul (mai usor):
  ```sql
  CREATE TABLE organizer_verifications (
      id SERIAL PRIMARY KEY,
      user_id INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
      legal_name VARCHAR(200) NOT NULL,
      cui VARCHAR(50),                       -- nullable
      id_card_url TEXT,
      authorization_url TEXT,
      contract_url TEXT,
      status VARCHAR(20) NOT NULL DEFAULT 'pending',
      admin_notes TEXT,
      reviewed_by INT REFERENCES users(id),
      submitted_at TIMESTAMP NOT NULL DEFAULT NOW(),
      reviewed_at TIMESTAMP,
      CONSTRAINT chk_org_status CHECK (status IN ('pending', 'approved', 'rejected'))
  );
  ```
- [ ] Re-ruleaza migratiile (drop + recreate)

### 11. Filtru type cu mai multe valori in campings

- [ ] `frontend/js/campings.js` — acum trimite doar primul type, daca user-ul bifeaza mai multe nu functioneaza. Solutii:
  - **A (rapid)**: cand sunt mai multe type-uri bifate, fa un singur request fara filtru `type`, apoi filtreaza pe client.
  - **B (corect)**: in `CampingsController::index()` accepta `type` ca array (`?type[]=tent&type[]=glamping`) si construieste `WHERE type IN (...)`.

### 12. Slug lookup limitat la 100 campinguri

- [ ] `frontend/js/camping-detail.js` — acum pentru `?slug=X` face GET tuturor si filtreaza client. Cand depasesti 100 campinguri se strica.
  - **Solutie**: in `CampingsModel` exista deja `findBySlug()`. Adauga in `CampingsController` o metoda `showBySlug` si o ruta `GET /api/campings/by-slug/{slug}`. Apeleaz-o din frontend.

### 13. Filtru min_rating ignora null

- [ ] `backend/app/models/CampingsModel.php` la `search()` si `countSearch()`: cand `rating_avg IS NULL` (camping fara recenzii), filtrul `min_rating` il scoate. E acceptabil dar e bine sa fie explicit: schimba in `rating_avg >= :min_rating AND rating_avg IS NOT NULL`.

### 14. `BookingsModel::calculatePrice()` ignora guests

- [ ] Modifica: `return round($pricePerNight * $nights * $guests, 2);` SAU schimba docstring-ul daca asa vrei. Coerenta intre comentariu si cod.

### 15. Slug transliterator broken

- [ ] `CampingsModel::makeUniqueSlug()` — `strtr` cu chei duplicate (`'a'=>'a'` apare de 2 ori) face nimic util. Inlocuieste cu maparea reala:
  ```php
  $slug = strtr($slug, [
      'ă'=>'a','â'=>'a','î'=>'i','ș'=>'s','ş'=>'s','ț'=>'t','ţ'=>'t',
      'Ă'=>'a','Â'=>'a','Î'=>'i','Ș'=>'s','Ş'=>'s','Ț'=>'t','Ţ'=>'t',
  ]);
  ```

### 16. MediaModel::findById crapa

- [ ] `backend/app/models/MediaModel.php` — `findById` face UNION ALL intre `camping_media` si `review_media`, dar coloanele difera (`camping_id` vs `review_id`, `sort_order` doar la primul). Query-ul esueaza.
  - **Solutie**: sterge metoda generica `findById`, ai deja `findByCampingId` / `findByReviewId` care merg.

### 17. Format data: american → european

- [ ] In `frontend/js/main.js`, functia `formatDateRange`: deja folosesti `toLocaleDateString('ro-RO', ...)`, dar verifica si in `booking-details` daca apare data nativa. Inputurile `type="date"` raman ISO oricum, dar afisarea sa fie peste tot ro-RO.

### 18. Calendar european in input-uri

- [ ] `<input type="date">` foloseste locale-ul browser-ului automat. Daca vrei sa fortezi european: nu se poate prin HTML pur. Solutii:
  - Lasa native — pe majoritatea browserelor in RO va fi DD.MM.YYYY.
  - Daca chiar vrei: schimba in `<input type="text">` + JS validare regex DD/MM/YYYY si convertire spre ISO la trimitere catre backend.

---

## P2 — Curatare cod si polish

### 19. Sterge dead code

- [ ] `backend/app/controllers/Controller.php` — sterge `render()`, `redirect()`, `$viewPath`, plus inialitizarea `$viewPath` din constructor. Proiectul e REST pur.
- [ ] `backend/app/models/BookingsModel.php` — sterge `findByCampingId()` (nimic n-o cheama).
- [ ] `backend/app/controllers/MediaController.php` — DECIDE: fie inregistrezi rutele (P0 #6) fie stergi fisierul + ReviewsModel::getOwnerId nu mai e nevoie nicaieri altundeva.
- [ ] `frontend/pages/account/booking-details.html` — e o pagina separata duplicat cu sectiunea `#booking-details` din `account.html`. Sterge fisierul daca nu o folosesti, sau elimina sectiunea duplicat din account.html.

### 20. Inconsistente minore

- [ ] `frontend/js/main.js` — `populateUserPill` seteaza avatarul doar prin id `user-pill-avatar` dar nu setezi `src` din `user.avatar_url`. Adauga:
  ```js
  const avatarEl = document.getElementById('user-pill-avatar');
  if (avatarEl && user.avatar_url) avatarEl.src = user.avatar_url;
  ```
- [ ] `frontend/js/main.js` — in `openBookingDetails`, linia `booking.camping_name = undefined;` apoi `nameEl.textContent = booking.camping_name;` — bug, suprascrii numele cu undefined. STERGE linia `booking.camping_name = undefined;`.
- [ ] `frontend/index.html` linia `<link rel="stylesheet" href="pages/auth.html">` — gresit, HTML-ul nu e stylesheet. STERGE linia.

### 21. Validare W3C / Stylelint

- [ ] Trece fiecare pagina HTML prin https://validator.w3.org/nu/
- [ ] Trece CSS-urile prin https://jigsaw.w3.org/css-validator/ sau ruleaza local `npx stylelint "frontend/css/**/*.css"`
- [ ] Repara warnings, mai ales: tag-uri ne-inchise, atribute duplicate, valori CSS invalide

### 22. Pagina camping — informatii lipsa

- [ ] In `camping-detail.js`, in card-ul "Booking", afiseaza:
  - Capacitate maxima a campingului
  - Tip camping cu icon/label uman (nu doar `glamping`)
  - Mesaj de eroare cand fetch-ul esueaza (acum apare doar in consola)

### 23. Pagina campings — search debouncing

- [ ] In `campings.js`, adauga debounce pe input-ul de search (acum trebuie sa apesi Apply ca sa filtrezi). Optional dar mareste UX-ul vizibil.

### 24. Filtre din search care nu corespund schemei

Per TODO original punctul 6 ("Filtrele functioneaza in ciuda lipsei de atribute"):
- [ ] Verifica daca filtrele "Pentru pet-friendly", "Cu duș", "WiFi", etc. sunt afisate in UI undeva. Daca da si nu sunt in DB → ori adauga coloane (`pet_friendly BOOL`, `has_wifi BOOL`, `has_shower BOOL` in tabela campings), ori scoate-le din UI.

### 25. Securitate

- [ ] `BookingsController::index()` — listeaza doar rezervarile user-ului curent (OK), dar nu suporta filtrul `camping_id` desi frontend-ul il trimite. Adauga:
  ```php
  $campingId = isset($_GET['camping_id']) ? (int)$_GET['camping_id'] : null;
  // si pass-eaza in model: findByUserIdAndCamping(...)
  ```
- [ ] CORS in `routes.php` e `*` — pentru productie ar trebui domeniu specific, dar pentru evaluare e OK.
- [ ] Rate limiting pe login — nu e cerinta dar e bun de mentionat in raport.

---

## P3 — Livrabile obligatorii (NU codul, dar tot conteaza)

Regulamentul cere astea EXPLICIT:

### 26. Raport Scholarly HTML

- [ ] Creeaza `docs/specification.html` in format Scholarly HTML. Trebuie sa contina:
  - Cerintele functionale (ce face aplicatia, cine sunt actorii)
  - Cerintele non-functionale (securitate, performanta, scalabilitate)
  - Mockup-uri / wireframes
  - User stories sau use case-uri principale
- [ ] Pune-l accesibil online (GitHub Pages, free) sau in repo.
- [ ] Foloseste macheta IEEE SRS ca structura.

### 27. Diagrama arhitecturala C4

- [ ] Cel putin nivelurile 1 (Context) si 2 (Container) ale modelului C4.
- [ ] Tool: https://app.diagrams.net (gratis) sau https://structurizr.com.
- [ ] Salveaza ca PNG/SVG in `docs/` si linkuieste din README.

### 28. README

- [ ] `README.md` cu:
  - Descriere proiect (1 paragraf)
  - Stack tehnologic
  - Cum se ruleaza local (cu pasii pentru Docker, exact ca in init_backend.sh)
  - Credentiale demo (admin/admin1234, user/parola1234)
  - Linkuri spre raport Scholarly HTML, diagrama C4, demo video
  - Echipa (cine ce a facut)
  - Licenta (MIT sau alta OSS)

### 29. Demo video 3-5 min

- [ ] Inregistreaza demo-ul aratand:
  - Inregistrare cont nou
  - Login cu Google OAuth
  - Cautare camping pe harta
  - Detalii camping, recenzii cu poze
  - Rezervare
  - Admin: ban user, statistici, export CSV
- [ ] Calitate (U)HD, max 5 min.
- [ ] Pune-l pe YouTube (unlisted) sau Drive si pune linkul in README.

### 30. Licenta

- [ ] Adauga `LICENSE` (MIT) in root.
- [ ] Headere de licenta in fisierele principale (optional dar profesionist).

---

## P4 — Bonus pentru ~1 punct in plus

Astea NU sunt obligatorii dar pot face diferenta:

- [ ] Suport pentru export ICS (calendar) pentru rezervari — utilizatorul descarca .ics si il importa in calendar.
- [ ] Generare cod QR pentru fiecare camping (cu o biblioteca PHP simpla precum endroid/qr-code), pus in pagina camping si in PDF.
- [ ] Notificari email la confirmare rezervare (PHPMailer + un SMTP gratuit).
- [ ] Internationalizare RO/EN — un fisier JSON cu traduceri, switch in UI.
- [ ] Pagina publica a unui camping accesibila prin slug, deschisa pentru SEO + share — adica suport pentru `/pages/camping/[slug]` ca URL frumos.
- [ ] Tests — chiar si cateva teste unitare cu PHPUnit pe modelele importante (BookingsModel::checkAvailability, calculatePrice, etc).
- [ ] Heatmap pe harta pentru densitatea de rezervari pe regiuni (folosind leaflet-heat).

---

## Ordine sugerata de atac (in zile)

**Ziua 1**: P0 #1 (landing), P1 toate (#8-#18). Acum aplicatia merge corect cap-coada.
**Ziua 2**: P0 #2 (OAuth), P0 #5 (admin UI scheletul).
**Ziua 3**: P0 #3 (statistici SVG/PDF), P0 #4 (CSV/JSON).
**Ziua 4**: P0 #6 (audio/video), P0 #7 (Overpass).
**Ziua 5**: P2 curatare, P3 documentatie.
**Ziua 6**: Demo video + testare end-to-end + bug-uri ramase.

---

## Lista finala de "smell-test" inainte de prezentare

- [ ] Pornesc cu `init_backend.sh` curat (sterg volumele Docker), aplicatia merge fara erori
- [ ] Pot face register + login normal SI cu Google
- [ ] Pot vedea harta, dau click pe marker, ajung in pagina camping
- [ ] Pot lasa recenzie cu poza si o vad afisata
- [ ] Pot face rezervare si o vad in istoric
- [ ] Pot intra in admin si vad: useri (cu filtre), cereri organizer, statistici (cu SVG vizibil), buton PDF care descarca
- [ ] Pot exporta campings.csv si campings.json
- [ ] Pot importa un CSV de campings
- [ ] HTML-ul fiecarei pagini valideaza W3C
- [ ] Cele 2 livrabile non-cod (raport Scholarly HTML, C4) sunt disponibile
- [ ] Video demo e gata si linkat in README
- [ ] Repo curat, fara fisiere temporare, fara comentarii vagi gen "TODO" sau "XXX"