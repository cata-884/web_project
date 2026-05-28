#!/bin/bash

echo "Pornire mediu Docker (Nginx, Backend, PostgreSQL)..."
docker compose up -d --build
if [ $? -ne 0 ]; then
    echo "EROARE: Nu s-a putut porni mediul Docker."
    exit 1
fi

echo "Așteptare ca baza de date să fie gata..."
attempts=0
max_attempts=30
until docker compose exec -T db pg_isready -U postgres -d web_project_db >/dev/null 2>&1; do
    attempts=$((attempts + 1))
    if [ "$attempts" -ge "$max_attempts" ]; then
        echo "EROARE: Baza de date nu a devenit disponibila la timp."
        exit 1
    fi
    sleep 2
done

docker compose exec -T backend php script/migrate.php
if [ $? -ne 0 ]; then
    echo "EROARE: Rularea migrațiilor a eșuat."
    exit 1
fi

docker compose ps