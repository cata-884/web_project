#!/bin/bash

sudo systemctl start postgresql
if [ $? -ne 0 ]; then
    echo "EROARE: Nu s-a putut porni PostgreSQL."
    exit 1
fi

sudo /opt/lampp/lampp startapache
if [ $? -ne 0 ]; then
    echo "EROARE: Nu s-a putut porni Apache."
    exit 1
fi

cd /opt/lampp/htdocs/web_project/backend || {
    echo "EROARE: Directorul backend nu a fost gasit."
    exit 1
}

/opt/lampp/bin/php script/migrate.php
if [ $? -ne 0 ]; then
    echo "EROARE: Rularea migratiilor a esuat."
    exit 1
fi

sudo /opt/lampp/lampp status
