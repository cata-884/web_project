<?php
define('APP_NAME', 'DiH - Digital Herbarium');
define('BASE_URL', '/dih/public'); // ajusteaza dupa cum e instalat
define('DEFAULT_LANG', 'ro');

// Autoloader - cauta clasele in directoarele cunoscute
spl_autoload_register(function (string $class): void {
    $dirs = [
        ROOT . SEP . 'app' . SEP . 'controllers',
        ROOT . SEP . 'app' . SEP . 'models',
        ROOT . SEP . 'lib'  . SEP . 'exporters',
        ROOT . SEP . 'lib'  . SEP . 'importers',
    ];

    foreach ($dirs as $dir) {
        $path = $dir . SEP . $class . '.php';
        if (file_exists($path)) {
            require_once $path;
            return;
        }
    }
});
