-- Migrasi 06: Tabel Master Jurusan / Program Studi
-- Menjadi sumber data terpusat untuk pemilihan jurusan pada Kelas DPT dan Kandidat.

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

