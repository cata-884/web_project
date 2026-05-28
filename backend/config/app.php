<?php
define('APP_NAME', 'CaT Camping Info Web Tool');
define('BASE_URL', '/cat/public');
define('DEFAULT_LANG', 'ro');

// Incarca .env daca variabilele nu sunt deja in mediu (Apache nu citeste .env automat)
// in mediul de executie al mediului php curent
(function () {
    $envFile = dirname(__DIR__, 2) . '/.env';
    if (!file_exists($envFile)) return;
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) continue;
        [$key, $val] = array_map('trim', explode('=', $line, 2));
        if ($key && getenv($key) === false) {
            putenv("$key=$val");
        }
    }
})();

define('GOOGLE_CLIENT_ID',     getenv('GOOGLE_CLIENT_ID')     ?: '');
define('GOOGLE_CLIENT_SECRET', getenv('GOOGLE_CLIENT_SECRET') ?: '');
define('GOOGLE_REDIRECT_URI',  getenv('GOOGLE_REDIRECT_URI')  ?: 'http://localhost/cat/public/api/auth/oauth/google/callback');

spl_autoload_register(function (string $class): void {
    $dirs = [
        ROOT . SEP . 'app' . SEP . 'controllers',
        ROOT . SEP . 'app' . SEP . 'models',
    ];

    foreach ($dirs as $dir) {
        $path = $dir . SEP . $class . '.php';
        if (file_exists($path)) {
            require_once $path;
            return;
        }
    }
});
