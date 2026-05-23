<?php
define('APP_NAME', 'CaT Camping Info Web Tool');
define('BASE_URL', '/cat/public');
define('DEFAULT_LANG', 'ro');

define('GOOGLE_CLIENT_ID', getenv('GOOGLE_CLIENT_ID') ?: '');
define('GOOGLE_CLIENT_SECRET', getenv('GOOGLE_CLIENT_SECRET') ?: '');
define('GOOGLE_REDIRECT_URI', 'http://localhost/cat/public/api/auth/oauth/google/callback');

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
