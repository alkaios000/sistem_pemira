-- Migrasi 03: Tabel Kandidat (Presiden BEM & Ketua Umum BPM) dan Tim Sukses

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

