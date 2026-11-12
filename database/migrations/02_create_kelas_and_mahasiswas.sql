-- Migrasi 02: Tabel Kelas dan Mahasiswa (Daftar Pemilih Tetap / DPT)
-- Menerapkan prinsip Secret Ballot: Tidak ada kolom pilihan kandidat pada tabel mahasiswas.

CREATE TABLE IF NOT EXISTS `kelas` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `nama_kelas` VARCHAR(100) NOT NULL,
    `jurusan` VARCHAR(120) NOT NULL,
    `semester` TINYINT UNSIGNED NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_kelas_kombinasi` (`nama_kelas`, `jurusan`, `semester`),
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

