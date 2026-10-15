<?php
declare(strict_types=1);

// ============================================================
// Centralized SQL transaction helper.
// Demonstrates COMMIT, ROLLBACK, SAVEPOINT, and AUTOCOMMIT behavior.
//
// - PDO runs with AUTOCOMMIT=1 by default (each query auto-saves).
// - beginTransaction() starts BEGIN / START TRANSACTION and
//   disables autocommit until COMMIT or ROLLBACK.
// - On success, commit() persists all queries (COMMIT).
// - On failure, rollBack() discards all queries (ROLLBACK, all-or-nothing).
//
// Used by classes/RemoteRepository.php:
//   Transaction::run($conn, fn() => ...)
//   Transaction::savepoint($conn, 'apps_batch', fn() => ...)
// ============================================================

final class Transaction
{
    /**
     * Run work inside BEGIN -> COMMIT on success, ROLLBACK on failure.
     */
    public static function run(PDO $conn, callable $work): mixed
    {
        $conn->beginTransaction();
        try {
            $result = $work();
            $conn->commit();
            return $result;
        } catch (Throwable $exception) {
            if ($conn->inTransaction()) {
                $conn->rollBack();
            }
            throw $exception;
        }
    }

    /**
     * SAVEPOINT for partial rollback.
     * On failure, only this batch is undone (ROLLBACK TO SAVEPOINT).
     */
    public static function savepoint(PDO $conn, string $name, callable $work): void
    {
        $name = preg_replace('/[^A-Za-z0-9_]/', '', $name) ?: 'sp';
        $conn->exec("SAVEPOINT {$name}");
        try {
            $work();
            $conn->exec("RELEASE SAVEPOINT {$name}");
        } catch (Throwable $exception) {
            $conn->exec("ROLLBACK TO SAVEPOINT {$name}");
            $conn->exec("RELEASE SAVEPOINT {$name}");
            throw $exception;
        }
    }
}
?>
