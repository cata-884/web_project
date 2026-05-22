<?php
define('APP_NAME', 'CaT Camping Info Web Tool');
define('BASE_URL', '/cat/public');
define('DEFAULT_LANG', 'ro');

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
