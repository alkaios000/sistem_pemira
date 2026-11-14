-- Arsip Skema Master Basis Data Sistem PEMIRA Kampus (BEM & BPM)
-- Menggabungkan seluruh berkas migrasi 01 hingga 05

CREATE DATABASE IF NOT EXISTS `db_sistem_pemira` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `db_sistem_pemira`;

-- 01: Tabel Pengguna Admin/KPRM/BANWASRA dan Pengaturan Sistem Global
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `username` VARCHAR(60) NOT NULL,
    `nama_lengkap` VARCHAR(120) NOT NULL,
    `password_hash` VARCHAR(255) NOT NULL,
    `role` ENUM('superadmin', 'kprm', 'banwasra') NOT NULL DEFAULT 'kprm',
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `last_login_at` DATETIME DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_users_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `pengaturan_sistem` (
    `kunci` VARCHAR(64) NOT NULL,
    `nilai` TEXT NOT NULL,
    `keterangan` VARCHAR(255) DEFAULT NULL,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`kunci`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 02: Tabel Master Jurusan, Kelas dan Mahasiswa (DPT)
CREATE TABLE IF NOT EXISTS `jurusans` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `kode_jurusan` VARCHAR(20) NOT NULL,
    `nama_jurusan` VARCHAR(120) NOT NULL,
    `jenjang` VARCHAR(20) NOT NULL DEFAULT 'S1',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_jurusan_kode` (`kode_jurusan`),
    UNIQUE KEY `uq_jurusan_nama` (`nama_jurusan`),
    KEY `idx_jurusan_jenjang` (`jenjang`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `kelas` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `jurusan_id` INT UNSIGNED DEFAULT NULL,
    `nama_kelas` VARCHAR(100) NOT NULL,
    `jurusan` VARCHAR(120) NOT NULL,
    `semester` TINYINT UNSIGNED NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_kelas_kombinasi` (`nama_kelas`, `jurusan`, `semester`),
    KEY `idx_kelas_jurusan_id` (`jurusan_id`),
    KEY `idx_kelas_jurusan` (`jurusan`),
    KEY `idx_kelas_semester` (`semester`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `mahasiswas` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `kelas_id` INT UNSIGNED NOT NULL,
    `nim` VARCHAR(30) NOT NULL,
    `nama_lengkap` VARCHAR(150) NOT NULL,
    `status_hadir` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1 = Aktif/Siap Memilih, 0 = Belum Check-In',
    `sudah_memilih` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '0 = Belum Memilih, 1 = Sudah Memilih',
    `waktu_memilih` DATETIME DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_mahasiswas_nim` (`nim`),
    KEY `idx_mahasiswas_kelas` (`kelas_id`),
    KEY `idx_mahasiswas_status_vote` (`sudah_memilih`, `status_hadir`),
    CONSTRAINT `fk_mahasiswas_kelas` FOREIGN KEY (`kelas_id`) REFERENCES `kelas` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 03: Tabel Kandidat BEM/BPM dan Tim Sukses
CREATE TABLE IF NOT EXISTS `kandidats` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `kategori` ENUM('BEM', 'BPM') NOT NULL,
    `jurusan_id` INT UNSIGNED DEFAULT NULL,
    `nomor_urut` TINYINT UNSIGNED NOT NULL,
    `nama_calon` VARCHAR(150) NOT NULL,
    `nama_wakil` VARCHAR(150) DEFAULT NULL,
    `jurusan_kelas` VARCHAR(120) DEFAULT NULL,
    `foto_path` VARCHAR(255) DEFAULT NULL,
    `visi` TEXT NOT NULL,
    `misi` TEXT NOT NULL,
    `jumlah_suara` INT UNSIGNED NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_kandidat_kategori_urut` (`kategori`, `nomor_urut`),
    KEY `idx_kandidat_kategori` (`kategori`),
    KEY `idx_kandidat_jurusan_id` (`jurusan_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tim_sukses` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `kandidat_id` INT UNSIGNED NOT NULL,
    `nama_anggota` VARCHAR(150) NOT NULL,
    `nim_anggota` VARCHAR(30) DEFAULT NULL,
    `jabatan_timses` VARCHAR(100) NOT NULL DEFAULT 'Anggota Tim Sukses',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_timses_kandidat` (`kandidat_id`),
    CONSTRAINT `fk_timses_kandidat` FOREIGN KEY (`kandidat_id`) REFERENCES `kandidats` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 04: Tabel Bilik Suara dan Sesi Perangkat
CREATE TABLE IF NOT EXISTS `bilik_suaras` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `nomor_bilik` INT UNSIGNED NOT NULL,
    `nama_bilik` VARCHAR(100) NOT NULL,
    `lokasi_bilik` VARCHAR(150) DEFAULT NULL,
    `status_bilik` ENUM('IDLE', 'IN_USE', 'CLOSED') NOT NULL DEFAULT 'IDLE',
    `current_token` VARCHAR(128) NOT NULL,
    `previous_token` VARCHAR(128) DEFAULT NULL,
    `kiosk_token` VARCHAR(128) DEFAULT NULL,
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
    KEY `idx_bilik_tokens` (`current_token`, `previous_token`),
    KEY `idx_bilik_kiosk_token` (`kiosk_token`)
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

-- 05: Tabel Audit Log Keamanan
CREATE TABLE IF NOT EXISTS `audit_logs` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `bilik_id` INT UNSIGNED DEFAULT NULL,
    `actor_user_id` INT UNSIGNED DEFAULT NULL,
    `actor_username` VARCHAR(60) DEFAULT NULL,
    `actor_role` ENUM('superadmin', 'kprm', 'banwasra') DEFAULT NULL,
    `log_category` ENUM('pemilih', 'admin') NOT NULL DEFAULT 'pemilih',
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
    KEY `idx_audit_actor_role` (`actor_role`),
    KEY `idx_audit_actor_user` (`actor_user_id`),
    KEY `idx_audit_log_category` (`log_category`),
    CONSTRAINT `fk_audit_logs_bilik` FOREIGN KEY (`bilik_id`) REFERENCES `bilik_suaras` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 07: Tabel Rate Limit Keamanan & Kotak Suara Anonim
CREATE TABLE IF NOT EXISTS `security_rate_limits` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `scope` VARCHAR(40) NOT NULL,
    `identifier` VARCHAR(191) NOT NULL,
    `attempts` INT UNSIGNED NOT NULL DEFAULT 0,
    `window_started_at` DATETIME NOT NULL,
    `locked_until` DATETIME DEFAULT NULL,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_rate_limit_scope_ident` (`scope`, `identifier`),
    KEY `idx_rate_limit_locked` (`locked_until`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ballot_boxes` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `ballot_hash` CHAR(64) NOT NULL,
    `bilik_id` INT UNSIGNED DEFAULT NULL,
    `kandidat_bem_id` INT UNSIGNED NOT NULL,
    `kandidat_bpm_id` INT UNSIGNED NOT NULL,
    `cast_bucket` DATETIME NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_ballot_hash` (`ballot_hash`),
    KEY `idx_ballot_bem` (`kandidat_bem_id`),
    KEY `idx_ballot_bpm` (`kandidat_bpm_id`),
    KEY `idx_ballot_bucket` (`cast_bucket`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

