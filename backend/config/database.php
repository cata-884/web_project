<?php
class DB
{
    // Replicate singleton pattern
    private static ?PDO $pdo = null;

    // Prevenirea instantierii clasei
    private function __construct() {}

    public static function getConnection(): PDO
    {
        if (self::$pdo === null) {
            $host = getenv('DB_HOST') ?: 'localhost';
            $port = getenv('DB_PORT') ?: '5432';
            $name = getenv('DB_NAME') ?: 'cat_dev';
            $user = getenv('DB_USER') ?: 'cat';
            $pass = getenv('DB_PASS') ?: 'cat';

            $dsn = "pgsql:host=$host;port=$port;dbname=$name";
            self::$pdo = new PDO($dsn, $user, $pass, [
                // in situatia care apare o eroare, arunca exceptie
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                // datele sunt grupate intr-un array asociativ
                // ex: $row['name'] in loc de $row[0]
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                // dezactiveaza emularea prepared statements pentru a preveni SQL injection
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        }
        return self::$pdo;
    }
}