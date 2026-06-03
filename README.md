# CaT — Camping & Travel

**CaT** este o platformă web de tip marketplace pentru descoperirea, listarea și rezervarea locațiilor de camping din România. Turiștii pot răsfoi un catalog filtrat, vizualiza campinguri pe o hartă interactivă Leaflet, face rezervări cu calcul automat de preț, lăsa recenzii cu media atașată și organiza favorite în colecții personale. Organizatorii verificați pot publica și administra propria locații, iar un panou de administrare complet permite moderarea conținutului, gestionarea utilizatorilor și exportul datelor.

---

## Stack tehnologic

| Strat | Tehnologii |
|---|---|
| **Frontend** | HTML5, CSS3, JavaScript (ES2020+), Leaflet.js 1.9 |
| **Backend** | PHP 8.2, Apache 2.4, Composer |
| **Bază de date** | PostgreSQL 16, PDO (prepared statements) |
| **Autentificare** | Bearer tokens (sesiuni proprii) + Google OAuth 2.0 |
| **Containerizare** | Docker, Docker Compose |
| **Dev tools** | stylelint, vnu-jar (HTML validator) |

---

## Rulare locală (Docker)

### Cerințe preliminare

- [Docker Desktop](https://www.docker.com/products/docker-desktop/) ≥ 24 (sau Docker Engine + Compose plugin)
- Git

### 1. Clonează repo-ul

```bash
git clone https://github.com/cata-884/web_project.git
cd web_project
```

### 2. Configurează variabilele de mediu

Copiază fișierul de exemplu și completează valorile:

```bash
cp .env.example .env
```

Variabilele minime necesare:

```dotenv
POSTGRES_USER=postgres
POSTGRES_PASSWORD=secret
POSTGRES_DB=web_project_db

DB_HOST=db
DB_PORT=5432
DB_NAME=web_project_db
DB_USER=postgres
DB_PASS=secret

GOOGLE_CLIENT_ID=
GOOGLE_CLIENT_SECRET=
```

### 3. Pornește mediul și rulează migrările

**Linux / macOS:**

```bash
chmod +x init_backend.sh
./init_backend.sh
```

**Windows (PowerShell):**

```powershell
.\init_backend_windows.ps1
```

Scriptul execută automat:

```
1. docker compose up -d --build   # pornește backend + db
2. Așteaptă ca PostgreSQL să fie gata (pg_isready)
3. docker compose exec backend php script/migrate.php   # migrări + seed
```

### 4. Accesează aplicația

| Serviciu | URL |
|---|---|
| Frontend | http://localhost/ |
| API backend | http://localhost/cat/public/api/ |
| Documentație SRS | http://localhost/docs/specification.html |

### Oprire

```bash
docker compose down          # oprește containerele
docker compose down -v       # oprește + șterge volumul de date
```

---

## Credențiale demo

| Rol | Username | Parolă | Email |
|---|---|---|---|
| Administrator | `admin` | `admin1234` | admin@cat.ro |
| Organizator | `org_carpati` | `parola1234` | org@carpati.ro |
| Organizator | `org_delta` | `parola1234` | org@delta.ro |
| Utilizator | `mihai` | `parola1234` | mihai@gmail.com |
| Utilizator | `ana` | `parola1234` | ana@gmail.com |

---

## Documentație

| Resursă | Link |
|---|---|
| Specificație SRS (Scholarly HTML) | [`docs/specification.html`](docs/specification.html) |


---

## Echipă

### Rusu Catalin
- Inițializare proiect; schelet backend bazat pe specificații Figma
- Arhitectura backend: router, controllers, models (PHP 8.2), schemă PostgreSQL și migrări
- API REST: autentificare (register/login/logout/me), campinguri, rezervări, recenzii (CRUD + sanitizare XSS), media, secțiuni personale, organizatori
- Google OAuth 2.0
- Pagini frontend: listing campinguri, detaliu camping, hartă interactivă cu auth guard
- Migrare XAMPP → Docker; `docker-compose.yml`, Dockerfile, entrypoint, Apache (servește frontend + API)
- Admin backend: aprobare/respingere campinguri, tabele facilități/mediu, statistici (JSON + SVG + PDF), export date
- Admin panel UI: cereri camping, gestionare utilizatori, statistici
- Sistem toast & confirm modal (înlocuire `alert`/`confirm` nativ)
- Refactorizare separare logică controllers/models

### Luciu Sebastian
- Structura inițială frontend; reorganizare foldere proiect
- Design landing page: hero, carduri features, responsive
- Pagina de login și dashboard cont
- Ecranele de profil și setări: poză profil, temă light/dark
- UI istoric rezervări și wishlist/colecții
- Formular creare camping și logica de navigare (frontend)
- Conectare formular camping cu baza de date; update schemă BD cu facilități, mediu, `approval_status`
- Admin UI locații camping: cereri, stări, mesaje primite

---

## Licență

Distribuit sub licența **MIT**. Vezi fișierul [LICENSE](LICENSE) pentru detalii.

```
MIT License

Copyright (c) 2026 Luciu Sebastian, Rusu Catalin

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
```
