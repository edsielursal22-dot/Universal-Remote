<?php
declare(strict_types=1);

// ============================================================
// PARA SA MAESTRO: TANAN SQL TRANSACTION ANHI LANG TAN-AWA
// Kini nga file ang demo sa duha ka video.
//
// Video 1: COMMIT, ROLLBACK, SAVEPOINT
// Video 2: AUTOCOMMIT, COMMIT, ROLLBACK
//
// - Ang PDO naka-AUTOCOMMIT=1 by default (kada query auto-save).
// - Ang beginTransaction() = BEGIN / START TRANSACTION:
//   gipalong ang autocommit hangtod mag COMMIT o ROLLBACK.
// - Kung success tanan queries -> commit() = COMMIT (ma-save).
// - Kung naay bisan usa nga palpak -> rollBack() = ROLLBACK
//   (walay ma-save, balik sa dati — all-or-nothing).
//
// Gigamit kini sa classes/RemoteRepository.php:
//   Transaction::run($conn, fn() => ...)
//   Transaction::savepoint($conn, 'apps_batch', fn() => ...)
// ============================================================

final class Transaction
{
    /**
     * Video 1 + 2: BEGIN -> COMMIT kung success, ROLLBACK kung palpak.
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
     * Video 1: SAVEPOINT — partial rollback.
     * Kung palpak ang sulod, kana rang batch ang ma-undo
     * (ROLLBACK TO SAVEPOINT), dili apil ang nauna.
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
