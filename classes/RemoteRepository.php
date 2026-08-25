<?php
declare(strict_types=1);

final class RemoteRepository
{
    public function __construct(private PDO $conn)
    {
        $this->ensureSchema();
    }

    public function dashboard(): array
    {
        $devices = $this->all('SELECT device_id, device_name, device_type, brand, connection_status, source_input FROM devices ORDER BY device_id');
        $apps = $this->all('SELECT app_id, device_id, app_name, app_icon, launch_url FROM app_shortcuts ORDER BY app_id');
        $themes = $this->all('SELECT theme_id, theme_name, color_scheme FROM themes ORDER BY theme_id');
        $logs = $this->all(
            'SELECT l.log_id, l.device_id, l.action, l.timestamp, d.device_name
             FROM activity_logs l JOIN devices d ON d.device_id = l.device_id
             ORDER BY l.timestamp DESC LIMIT 8'
        );

        foreach ($devices as &$device) {
            $device['device_id'] = (int) $device['device_id'];
            $device['apps'] = array_values(array_filter($apps, static fn (array $app): bool => (int) $app['device_id'] === $device['device_id']));
        }
        unset($device);

        return ['devices' => $devices, 'themes' => $themes, 'activity' => $logs];
    }

    public function createDevice(string $name, string $type, string $brand): int
    {
        $statement = $this->conn->prepare(
            'INSERT INTO devices (device_name, device_type, brand, connection_status, source_input)
             VALUES (:name, :type, :brand, :status, :source)'
        );
        $statement->execute([
            ':name' => $name,
            ':type' => $type,
            ':brand' => $brand,
            ':status' => 'pairing',
            ':source' => 'Not set',
        ]);
        $id = (int) $this->conn->lastInsertId();
        $this->logAction($id, 'Device added');
        return $id;
    }

    public function disconnectDevice(int $deviceId): array
    {
        if (!$this->deviceExists($deviceId)) {
            throw new InvalidArgumentException('The selected device could not be found.');
        }
        $statement = $this->conn->prepare('UPDATE devices SET connection_status = :status WHERE device_id = :id');
        $statement->execute([':status' => 'offline', ':id' => $deviceId]);
        $this->logAction($deviceId, 'Device disconnected');
        return ['deviceId' => $deviceId, 'message' => 'Device disconnected'];
    }

    public function connectDevice(int $deviceId): array
    {
        if (!$this->deviceExists($deviceId)) {
            throw new InvalidArgumentException('The selected device could not be found.');
        }
        $statement = $this->conn->prepare('UPDATE devices SET connection_status = :status WHERE device_id = :id');
        $statement->execute([':status' => 'connected', ':id' => $deviceId]);
        $this->logAction($deviceId, 'Device connected');
        return ['deviceId' => $deviceId, 'message' => 'Device connected'];
    }

    public function removeDevice(int $deviceId): array
    {
        if (!$this->deviceExists($deviceId)) {
            throw new InvalidArgumentException('The selected device could not be found.');
        }
        $statement = $this->conn->prepare('DELETE FROM devices WHERE device_id = :id');
        $statement->execute([':id' => $deviceId]);
        return ['deviceId' => $deviceId, 'message' => 'Device removed'];
    }

    public function runCommand(int $deviceId, string $command): array
    {
        $allowed = [
            'power' => 'Power toggled',
            'volume-up' => 'Volume increased',
            'volume-down' => 'Volume decreased',
            'mute' => 'Mute toggled',
            'channel-up' => 'Channel increased',
            'channel-down' => 'Channel decreased',
            'home' => 'Home opened',
            'back' => 'Back pressed',
            'play-pause' => 'Playback toggled',
            'rewind' => 'Rewind pressed',
            'forward' => 'Fast-forward pressed',
            'source' => 'Source selector opened',
        ];

        if (!isset($allowed[$command])) {
            throw new InvalidArgumentException('That remote action is not available.');
        }
        if (!$this->deviceExists($deviceId)) {
            throw new InvalidArgumentException('The selected device could not be found.');
        }

        $this->logAction($deviceId, $allowed[$command]);
        return ['command' => $command, 'message' => $allowed[$command]];
    }

    public function setTheme(int $deviceId, int $themeId): void
    {
        if (!$this->deviceExists($deviceId)) {
            throw new InvalidArgumentException('The selected device could not be found.');
        }
        if (!$this->themeExists($themeId)) {
            throw new InvalidArgumentException('The selected theme could not be found.');
        }
        if ($this->driver() === 'mysql') {
            $statement = $this->conn->prepare(
                'INSERT INTO settings (device_id, setting_name, setting_value)
                 VALUES (:device, :name, :value)
                 ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
            );
        } else {
            $statement = $this->conn->prepare(
                'INSERT INTO settings (device_id, setting_name, setting_value)
                 VALUES (:device, :name, :value)
                 ON CONFLICT(device_id, setting_name) DO UPDATE SET setting_value = excluded.setting_value'
            );
        }
        $statement->execute([':device' => $deviceId, ':name' => 'theme_id', ':value' => (string) $themeId]);
        $this->logAction($deviceId, 'Theme updated');
    }

    private function logAction(int $deviceId, string $action): void
    {
        $statement = $this->conn->prepare('INSERT INTO activity_logs (device_id, action, timestamp) VALUES (:device, :action, CURRENT_TIMESTAMP)');
        $statement->execute([':device' => $deviceId, ':action' => $action]);
    }

    private function deviceExists(int $deviceId): bool
    {
        $statement = $this->conn->prepare('SELECT 1 FROM devices WHERE device_id = :id LIMIT 1');
        $statement->execute([':id' => $deviceId]);
        return (bool) $statement->fetchColumn();
    }

    private function themeExists(int $themeId): bool
    {
        $statement = $this->conn->prepare('SELECT 1 FROM themes WHERE theme_id = :id LIMIT 1');
        $statement->execute([':id' => $themeId]);
        return (bool) $statement->fetchColumn();
    }

    private function all(string $query): array
    {
        return $this->conn->query($query)->fetchAll();
    }

    private function driver(): string
    {
        return (string) $this->conn->getAttribute(PDO::ATTR_DRIVER_NAME);
    }

    private function ensureSchema(): void
    {
        if ($this->driver() === 'mysql') {
            $schemaPath = __DIR__ . '/../database/schema.sql';
            $this->conn->exec((string) file_get_contents($schemaPath));
            return;
        }

        $statements = [
            'CREATE TABLE IF NOT EXISTS devices (device_id INTEGER PRIMARY KEY AUTOINCREMENT, device_name TEXT NOT NULL, device_type TEXT NOT NULL, brand TEXT NOT NULL, connection_status TEXT NOT NULL DEFAULT "connected", source_input TEXT NOT NULL DEFAULT "HDMI 1", created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP)',
            'CREATE TABLE IF NOT EXISTS themes (theme_id INTEGER PRIMARY KEY AUTOINCREMENT, theme_name TEXT NOT NULL UNIQUE, color_scheme TEXT NOT NULL DEFAULT "midnight")',
            'CREATE TABLE IF NOT EXISTS settings (setting_id INTEGER PRIMARY KEY AUTOINCREMENT, device_id INTEGER NOT NULL, setting_name TEXT NOT NULL, setting_value TEXT NOT NULL, UNIQUE(device_id, setting_name), FOREIGN KEY(device_id) REFERENCES devices(device_id) ON DELETE CASCADE)',
            'CREATE TABLE IF NOT EXISTS app_shortcuts (app_id INTEGER PRIMARY KEY AUTOINCREMENT, device_id INTEGER NOT NULL, app_name TEXT NOT NULL, app_icon TEXT NOT NULL DEFAULT "app", launch_url TEXT, FOREIGN KEY(device_id) REFERENCES devices(device_id) ON DELETE CASCADE)',
            'CREATE TABLE IF NOT EXISTS activity_logs (log_id INTEGER PRIMARY KEY AUTOINCREMENT, device_id INTEGER NOT NULL, action TEXT NOT NULL, timestamp TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY(device_id) REFERENCES devices(device_id) ON DELETE CASCADE)',
        ];
        foreach ($statements as $statement) {
            $this->conn->exec($statement);
        }

        $this->conn->exec("INSERT OR IGNORE INTO themes (theme_id, theme_name, color_scheme) VALUES (1, 'Midnight', 'midnight'), (2, 'Aurora', 'aurora')");
        $count = (int) $this->conn->query('SELECT COUNT(*) FROM devices')->fetchColumn();
        if ($count === 0) {
            $seed = [
                ['Living room TV', 'Smart TV', 'Samsung', 'connected', 'HDMI 1'],
                ['Bedroom TV', 'Smart TV', 'LG', 'offline', 'HDMI 2'],
                ['Soundbar', 'Audio', 'Sonos', 'connected', 'Optical'],
            ];
            $insert = $this->conn->prepare('INSERT INTO devices (device_name, device_type, brand, connection_status, source_input) VALUES (?, ?, ?, ?, ?)');
            foreach ($seed as $device) {
                $insert->execute($device);
            }
            $appInsert = $this->conn->prepare('INSERT INTO app_shortcuts (device_id, app_name, app_icon, launch_url) VALUES (?, ?, ?, ?)');
            foreach ([[1, 'Apple TV', 'apple', null], [1, 'YouTube', 'youtube', null], [1, 'Netflix', 'netflix', null], [2, 'Netflix', 'netflix', null]] as $app) {
                $appInsert->execute($app);
            }
            $this->logAction(1, 'Remote connected');
            $this->logAction(1, 'Playback started');
        }
    }
}
?>

