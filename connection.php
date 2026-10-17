<?php
declare(strict_types=1);


$INFINITY_DB_NAME = (string) (getenv('DB_NAME') ?: 'if0_43164779_XXX');
$INFINITY_DB_USER = (string) (getenv('DB_USER') ?: 'if0_43164779');
$INFINITY_DB_PASS = (string) (getenv('DB_PASS') ?: 'B1UaYPU5JpdV7e');
$INFINITY_DB_HOST = (string) (getenv('DB_HOST') ?: 'sql303.infinityfree.com');
$INFINITY_DB_PORT = (string) (getenv('DB_PORT') ?: '3306');

// Detect kung naa nata sa InfinityFree hosting.
$httpHost = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
$isHosting = str_contains($httpHost, 'infinityfree')
    || str_contains($httpHost, 'epizy')
    || str_contains($httpHost, 'rf.gd')
    || str_contains($httpHost, 'ezy.ro')
    || str_contains($httpHost, 'great-site');

$driver = strtolower((string) (getenv('DB_DRIVER') ?: ($isHosting ? 'mysql' : 'sqlite')));
$dbDirectory = __DIR__ . '/storage';

if ($driver !== 'mysql' && !is_dir($dbDirectory)) {
    mkdir($dbDirectory, 0750, true);
}

try {
    if ($driver === 'mysql') {
       
        $isLocalMysql = !$isHosting && $INFINITY_DB_HOST === 'sql303.infinityfree.com' && getenv('DB_HOST') === false;
        if ($isLocalMysql && getenv('DB_NAME') === false) {
            $host = '127.0.0.1';
            $port = '3306';
            $name = 'mobileremote_db';
            $user = 'root';
            $pass = '';
        } else {
            $host = $INFINITY_DB_HOST;
            $port = $INFINITY_DB_PORT;
            $name = $INFINITY_DB_NAME;
            $user = $INFINITY_DB_USER;
            $pass = $INFINITY_DB_PASS;
        }
        $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";
    } else {
        $dsn = 'sqlite:' . $dbDirectory . '/remote.sqlite';
        $user = null;
        $pass = null;
    }

    $conn = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $exception) {
    http_response_code(500);
    error_log('Universal Remote database connection failed: ' . $exception->getMessage());
    exit('The application could not connect to its data store. pls Check your database configuration.');
}
?>

