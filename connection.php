<?php
declare(strict_types=1);

/**
 * Database bootstrap.
 *
 * MySQL is supported through environment variables for production/XAMPP:
 * DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASS.
 * With no configuration, the app uses a local SQLite file so the demo runs
 * without hard-coded credentials or a separate database service.
 */

$driver = strtolower((string) (getenv('DB_DRIVER') ?: 'sqlite'));
$dbDirectory = __DIR__ . '/storage';

if (!is_dir($dbDirectory)) {
    mkdir($dbDirectory, 0750, true);
}

try {
    if ($driver === 'mysql') {
        $host = getenv('DB_HOST') ?: '127.0.0.1';
        $port = getenv('DB_PORT') ?: '3306';
        $name = getenv('DB_NAME') ?: 'mobileremote_db';
        $user = getenv('DB_USER') ?: 'root';
        $pass = getenv('DB_PASS') ?: '';
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

