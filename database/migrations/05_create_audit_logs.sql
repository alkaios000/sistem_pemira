-- Migrasi 05: Tabel Audit Log Keamanan & Deteksi Kecurangan (Hijau = NORMAL, Merah = FRAUD)

CREATE TABLE IF NOT EXISTS `audit_logs` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `bilik_id` INT UNSIGNED DEFAULT NULL,
    `nim` VARCHAR(30) DEFAULT NULL,
    `nama_mahasiswa_cache` VARCHAR(150) DEFAULT NULL,
    `device_id` VARCHAR(128) NOT NULL,
    `fingerprint_hash` VARCHAR(128) DEFAULT NULL,
    `browser_session_id` VARCHAR(128) DEFAULT NULL,
    `ip_address` VARCHAR(45) NOT NULL,
    `user_agent` VARCHAR(255) NOT NULL,
    `status_log` ENUM('NORMAL', 'FRAUD') NOT NULL DEFAULT 'NORMAL',
    `kode_event` VARCHAR(64) NOT NULL,
    `judul_event` VARCHAR(150) NOT NULL,
    `keterangan` TEXT NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_audit_status` (`status_log`),
    KEY `idx_audit_nim` (`nim`),
    KEY `idx_audit_device` (`device_id`),
    KEY `idx_audit_created` (`created_at`),
    CONSTRAINT `fk_audit_logs_bilik` FOREIGN KEY (`bilik_id`) REFERENCES `bilik_suaras` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

