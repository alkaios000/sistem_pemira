-- Migrasi 04: Tabel Bilik Suara (Multi-Booth QR Token) dan Sesi Perangkat (Device Lock)

CREATE TABLE IF NOT EXISTS `bilik_suaras` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `nomor_bilik` INT UNSIGNED NOT NULL,
    `nama_bilik` VARCHAR(100) NOT NULL,
    `lokasi_bilik` VARCHAR(150) DEFAULT NULL,
    `status_bilik` ENUM('IDLE', 'IN_USE', 'CLOSED') NOT NULL DEFAULT 'IDLE',
    `current_token` VARCHAR(128) NOT NULL,
    `previous_token` VARCHAR(128) DEFAULT NULL,
    `token_generated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `active_session_token` VARCHAR(128) DEFAULT NULL,
    `active_device_id` VARCHAR(128) DEFAULT NULL,
    `active_nim` VARCHAR(30) DEFAULT NULL,
    `locked_until` DATETIME DEFAULT NULL,
    `last_vote_at` DATETIME DEFAULT NULL,
    `total_suara_bilik` INT UNSIGNED NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_bilik_nomor` (`nomor_bilik`),
    KEY `idx_bilik_status` (`status_bilik`),
    KEY `idx_bilik_tokens` (`current_token`, `previous_token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `device_sessions` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `device_id` VARCHAR(128) NOT NULL,
    `browser_session_id` VARCHAR(128) NOT NULL,
    `fingerprint_hash` VARCHAR(128) NOT NULL,
    `ip_address` VARCHAR(45) NOT NULL,
    `user_agent` VARCHAR(255) NOT NULL,
    `bilik_id` INT UNSIGNED DEFAULT NULL,
    `nim_terkait` VARCHAR(30) DEFAULT NULL,
    `sudah_digunakan_vote` TINYINT(1) NOT NULL DEFAULT 0,
    `jumlah_percobaan_nim` TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_dev_device_id` (`device_id`),
    KEY `idx_dev_fingerprint` (`fingerprint_hash`),
    KEY `idx_dev_browser_session` (`browser_session_id`),
    CONSTRAINT `fk_dev_sessions_bilik` FOREIGN KEY (`bilik_id`) REFERENCES `bilik_suaras` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

