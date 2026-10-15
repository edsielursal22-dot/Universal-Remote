<?php
declare(strict_types=1);

/**
 * Database bootstrap.
 *
 * Local (XAMPP) = SQLite automatically, no setup required.
 * Hosting (InfinityFree) = MySQL automatically using the config below.
 * Can be overridden via env: DB_DRIVER, DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASS.
 */

// Hosting database name — see InfinityFree > MySQL Databases > Database Name.
$INFINITY_DB_NAME = (string) (getenv('DB_NAME') ?: 'if0_43164779_remotee');
$INFINITY_DB_USER = (string) (getenv('DB_USER') ?: 'if0_43164779');
$INFINITY_DB_PASS = (string) (getenv('DB_PASS') ?: 'CHANGE_ME_IN_HOSTING');
$INFINITY_DB_HOST = (string) (getenv('DB_HOST') ?: 'sql303.infinityfree.com');
$INFINITY_DB_PORT = (string) (getenv('DB_PORT') ?: '3306');

// Detect hosting environment.
// Uses stripos for compatibility with older PHP versions.
$httpHost = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
$isHosting = stripos($httpHost, 'infinityfree') !== false
    || stripos($httpHost, 'epizy') !== false
    || stripos($httpHost, 'rf.gd') !== false
    || stripos($httpHost, 'ezy.ro') !== false
    || stripos($httpHost, 'great-site') !== false;

$driver = strtolower((string) (getenv('DB_DRIVER') ?: ($isHosting ? 'mysql' : 'sqlite')));
$dbDirectory = __DIR__ . '/storage';

if ($driver !== 'mysql' && !is_dir($dbDirectory)) {
    mkdir($dbDirectory, 0750, true);
}

try {
    if ($driver === 'mysql') {
        if ($isHosting) {
            // On hosting, use the InfinityFree config above.
            $host = $INFINITY_DB_HOST;
            $port = $INFINITY_DB_PORT;
            $name = $INFINITY_DB_NAME;
            $user = $INFINITY_DB_USER;
            $pass = $INFINITY_DB_PASS;
        } else {
            // Local XAMPP MySQL.
            $host = (string) (getenv('DB_HOST') ?: '127.0.0.1');
            $port = (string) (getenv('DB_PORT') ?: '3306');
            $name = (string) (getenv('DB_NAME') ?: 'mobileremote_db');
            $user = (string) (getenv('DB_USER') ?: 'root');
            $pass = (string) (getenv('DB_PASS') ?: '');
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
    exit('The application could not connect to its data store. Check your database configuration.');
}
?>

