<?php
define('SEP',  DIRECTORY_SEPARATOR);
define('ROOT', dirname(__DIR__));

require_once ROOT . SEP . 'config' . SEP . 'database.php';

$files = glob(ROOT . SEP . 'db' . SEP . 'migrations' . SEP . '*.sql');
sort($files);

$pdo = DB::getConnection();
foreach ($files as $f) {
    echo "Run: " . basename($f) . "\n";
    $pdo->exec(file_get_contents($f));
}
echo "Done.\n";
