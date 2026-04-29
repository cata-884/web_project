# DiH - Digital Herbarium (Skeleton)

## Rulare
php script/migrate.php creeaza baza de date SQLite
Configureaza Apache sa serveasca `public/` ca document root, sau acceseaza `http://localhost/dih/public/`
Ajusteaza `BASE_URL` din `config/app.php` daca calea difera

## Structura
- `public/` — entry point + assets (CSS/JS/uploads)
- `app/controllers/` — un controller per modul (Home, Auth, Dashboard, Plants, Collections, Community, Settings)
- `app/models/` — un model per controller
- `app/views/` — un folder per modul
- `config/` — app.php (constante + autoloader), database.php (PDO Singleton), routes.php
- `db/migrations/` — schema SQL
- `lib/exporters/` + `lib/importers/` — JSON + XML

## TODO
Toate metodele din controllere sunt stub-uri cu `// TODO`.
Modelele sunt goale — adauga query-uri dupa cum apare nevoia.
View-urile nu exista inca — se vor crea pe masura ce se construieste UI-ul.
