<?php
define('SEP',  DIRECTORY_SEPARATOR);
define('ROOT', dirname(__DIR__));

session_start();

require_once ROOT . SEP . 'config' . SEP . 'app.php';
require_once ROOT . SEP . 'config' . SEP . 'database.php';
require_once ROOT . SEP . 'config' . SEP . 'routes.php';
