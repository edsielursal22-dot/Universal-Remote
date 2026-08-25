-- Universal Remote schema
-- MySQL 8+ compatible. The PHP repository also creates the equivalent SQLite tables automatically.

CREATE TABLE IF NOT EXISTS devices (
    device_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    device_name VARCHAR(80) NOT NULL,
    device_type VARCHAR(50) NOT NULL,
    brand VARCHAR(50) NOT NULL,
    connection_status ENUM('connected', 'offline', 'pairing') NOT NULL DEFAULT 'connected',
    source_input VARCHAR(50) NOT NULL DEFAULT 'HDMI 1',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS themes (
    theme_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    theme_name VARCHAR(80) NOT NULL,
    color_scheme VARCHAR(30) NOT NULL DEFAULT 'midnight',
    UNIQUE KEY uq_theme_name (theme_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS remote_layouts (
    layout_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    device_id INT UNSIGNED NOT NULL,
    theme_id INT UNSIGNED NULL,
    layout_name VARCHAR(80) NOT NULL,
    mode VARCHAR(30) NOT NULL DEFAULT 'standard',
    CONSTRAINT fk_layout_device FOREIGN KEY (device_id) REFERENCES devices(device_id) ON DELETE CASCADE,
    CONSTRAINT fk_layout_theme FOREIGN KEY (theme_id) REFERENCES themes(theme_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS buttons (
    button_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    layout_id INT UNSIGNED NOT NULL,
    button_label VARCHAR(40) NOT NULL,
    button_function VARCHAR(80) NOT NULL,
    position_order INT UNSIGNED NOT NULL DEFAULT 0,
    CONSTRAINT fk_button_layout FOREIGN KEY (layout_id) REFERENCES remote_layouts(layout_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS app_shortcuts (
    app_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    device_id INT UNSIGNED NOT NULL,
    app_name VARCHAR(80) NOT NULL,
    app_icon VARCHAR(20) NOT NULL DEFAULT 'app',
    launch_url VARCHAR(255) NULL,
    CONSTRAINT fk_app_device FOREIGN KEY (device_id) REFERENCES devices(device_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS channels (
    channel_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    device_id INT UNSIGNED NOT NULL,
    channel_number VARCHAR(20) NOT NULL,
    channel_name VARCHAR(80) NOT NULL,
    CONSTRAINT fk_channel_device FOREIGN KEY (device_id) REFERENCES devices(device_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS settings (
    setting_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    device_id INT UNSIGNED NOT NULL,
    setting_name VARCHAR(80) NOT NULL,
    setting_value VARCHAR(120) NOT NULL,
    UNIQUE KEY uq_device_setting (device_id, setting_name),
    CONSTRAINT fk_setting_device FOREIGN KEY (device_id) REFERENCES devices(device_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS activity_logs (
    log_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    device_id INT UNSIGNED NOT NULL,
    action VARCHAR(100) NOT NULL,
    timestamp DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_log_device FOREIGN KEY (device_id) REFERENCES devices(device_id) ON DELETE CASCADE,
    INDEX idx_log_device_time (device_id, timestamp)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO themes (theme_id, theme_name, color_scheme) VALUES
(1, 'Midnight', 'midnight'), (2, 'Aurora', 'aurora');

INSERT IGNORE INTO devices (device_id, device_name, device_type, brand, connection_status, source_input) VALUES
(1, 'Living room TV', 'Smart TV', 'Samsung', 'connected', 'HDMI 1'),
(2, 'Bedroom TV', 'Smart TV', 'LG', 'offline', 'HDMI 2'),
(3, 'Soundbar', 'Audio', 'Sonos', 'connected', 'Optical');
