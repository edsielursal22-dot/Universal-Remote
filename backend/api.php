<?php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/../connection.php';
require_once __DIR__ . '/../classes/RemoteRepository.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

try {
    $repository = new RemoteRepository($conn);
    $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

    if ($method === 'GET' && ($_GET['action'] ?? 'dashboard') === 'dashboard') {
        respond(['ok' => true, 'data' => $repository->dashboard()]);
    }

    if ($method !== 'POST') {
        respond(['ok' => false, 'message' => 'Method not allowed.'], 405);
    }

    $expectedToken = (string) ($_SESSION['csrf_token'] ?? '');
    $receivedToken = (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if ($expectedToken === '' || !hash_equals($expectedToken, $receivedToken)) {
        respond(['ok' => false, 'message' => 'Your session expired. Refresh the page and try again.'], 419);
    }

    $payload = json_decode((string) file_get_contents('php://input'), true);
    if (!is_array($payload)) {
        respond(['ok' => false, 'message' => 'The request format is invalid.'], 400);
    }

    $action = (string) ($payload['action'] ?? '');
    if ($action === 'remove-device') {
        $deviceId = filter_var($payload['deviceId'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($deviceId === false) {
            respond(['ok' => false, 'message' => 'Choose a valid device to remove.'], 422);
        }
        respond(['ok' => true, 'data' => $repository->removeDevice((int) $deviceId)]);
    }

    if ($action === 'connect') {
        $deviceId = filter_var($payload['deviceId'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($deviceId === false) {
            respond(['ok' => false, 'message' => 'Choose a valid device to connect.'], 422);
        }
        respond(['ok' => true, 'data' => $repository->connectDevice((int) $deviceId)]);
    }

    if ($action === 'disconnect') {
        $deviceId = filter_var($payload['deviceId'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($deviceId === false) {
            respond(['ok' => false, 'message' => 'Choose a valid device to disconnect.'], 422);
        }
        respond(['ok' => true, 'data' => $repository->disconnectDevice((int) $deviceId)]);
    }

    if ($action === 'command') {
        $deviceId = filter_var($payload['deviceId'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $command = trim((string) ($payload['command'] ?? ''));
        if ($deviceId === false || $command === '') {
            respond(['ok' => false, 'message' => 'Choose a device and a valid remote action.'], 422);
        }
        respond(['ok' => true, 'data' => $repository->runCommand((int) $deviceId, $command)]);
    }

    if ($action === 'theme') {
        $deviceId = filter_var($payload['deviceId'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $themeId = filter_var($payload['themeId'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($deviceId === false || $themeId === false) {
            respond(['ok' => false, 'message' => 'Choose a valid device and theme.'], 422);
        }
        $repository->setTheme((int) $deviceId, (int) $themeId);
        respond(['ok' => true, 'data' => ['message' => 'Theme updated']]);
    }

    if ($action === 'add-device') {
        $name = trim((string) ($payload['name'] ?? ''));
        $type = trim((string) ($payload['type'] ?? ''));
        $brand = trim((string) ($payload['brand'] ?? ''));
        if (strlen($name) < 2 || strlen($name) > 80 || strlen($type) < 2 || strlen($type) > 50 || strlen($brand) < 2 || strlen($brand) > 50) {
            respond(['ok' => false, 'message' => 'Enter a device name, type, and brand using the suggested lengths.'], 422);
        }
        // Uses a SQL transaction internally (BEGIN -> COMMIT / ROLLBACK).
        $id = $repository->createDevice($name, $type, $brand);
        respond(['ok' => true, 'data' => ['deviceId' => $id, 'message' => 'Device added']]);
    }

    if ($action === 'add-device-with-apps') {
        $name = trim((string) ($payload['name'] ?? ''));
        $type = trim((string) ($payload['type'] ?? ''));
        $brand = trim((string) ($payload['brand'] ?? ''));
        $apps = is_array($payload['apps'] ?? null) ? $payload['apps'] : [];
        if (strlen($name) < 2 || strlen($name) > 80 || strlen($type) < 2 || strlen($type) > 50 || strlen($brand) < 2 || strlen($brand) > 50) {
            respond(['ok' => false, 'message' => 'Enter a device name, type, and brand using the suggested lengths.'], 422);
        }
        // Demonstrates BEGIN + SAVEPOINT + COMMIT / ROLLBACK TO SAVEPOINT.
        $id = $repository->createDeviceWithApps($name, $type, $brand, $apps);
        respond(['ok' => true, 'data' => ['deviceId' => $id, 'message' => 'Device added with apps']]);
    }

    respond(['ok' => false, 'message' => 'That action is not available.'], 400);
} catch (InvalidArgumentException $exception) {
    respond(['ok' => false, 'message' => $exception->getMessage()], 422);
} catch (Throwable $exception) {
    error_log('Universal Remote API error: ' . $exception->getMessage());
    respond(['ok' => false, 'message' => 'Something went wrong. Please try again.'], 500);
}

function respond(array $body, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($body, JSON_UNESCAPED_SLASHES);
    exit;
}
?>

