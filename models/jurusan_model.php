<?php
/**
 * Model Master Jurusan / Program Studi
 * Sumber data terpusat yang terhubung ke Manajemen Kelas (DPT) dan Manajemen Kandidat.
 */

/**
 * Memastikan tabel jurusans dan kolom relasi jurusan_id pada kelas & kandidats tersedia serta tersinkronisasi
 */
function ensure_jurusans_ready(PDO $pdo): void
{
    static $ready = false;
    if ($ready) {
        return;
    }

    // 1. Pastikan tabel jurusans ada
    $pdo->exec("
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    // 2. Pastikan kolom jurusan_id ada di tabel kelas & kandidats
    $colKelas = $pdo->query("SHOW COLUMNS FROM `kelas` LIKE 'jurusan_id'")->fetch();
    if (!$colKelas) {
        $pdo->exec("ALTER TABLE `kelas` ADD COLUMN `jurusan_id` INT UNSIGNED DEFAULT NULL AFTER `id`, ADD KEY `idx_kelas_jurusan_id` (`jurusan_id`)");
    }

    $colKand = $pdo->query("SHOW COLUMNS FROM `kandidats` LIKE 'jurusan_id'")->fetch();
    if (!$colKand) {
        $pdo->exec("ALTER TABLE `kandidats` ADD COLUMN `jurusan_id` INT UNSIGNED DEFAULT NULL AFTER `kategori`, ADD KEY `idx_kandidat_jurusan_id` (`jurusan_id`)");
    }

    // 3. Sinkronkan jurusan yang sudah ada di tabel kelas agar otomatis masuk ke master jurusans
    $distinctKelasJur = $pdo->query("SELECT DISTINCT TRIM(jurusan) AS nama FROM `kelas` WHERE TRIM(jurusan) != ''")->fetchAll(PDO::FETCH_COLUMN);
    $stmtCheck = $pdo->prepare('SELECT id FROM jurusans WHERE LOWER(nama_jurusan) = LOWER(:nama) LIMIT 1');
    $stmtInsert = $pdo->prepare('INSERT INTO jurusans (kode_jurusan, nama_jurusan, jenjang) VALUES (:kode, :nama, :jenjang)');

    foreach ($distinctKelasJur as $nama) {
        $stmtCheck->execute([':nama' => $nama]);
        if (!$stmtCheck->fetch()) {
            $kode = generate_unique_kode_jurusan($pdo, $nama);
            $jenjang = str_contains(strtolower($nama), 'manajemen informatika') ? 'D3' : 'S1';
            $stmtInsert->execute([
                ':kode'    => $kode,
                ':nama'    => $nama,
                ':jenjang' => $jenjang,
            ]);
        }
    }

    // 4. Lengkapi kelas.jurusan_id yang masih NULL
    $pdo->exec("
        UPDATE `kelas` k
        INNER JOIN `jurusans` j ON LOWER(TRIM(j.nama_jurusan)) = LOWER(TRIM(k.jurusan))
        SET k.jurusan_id = j.id
        WHERE k.jurusan_id IS NULL OR k.jurusan_id = 0
    ");

    // 5. Lengkapi kandidats.jurusan_id jika jurusan_kelas cocok langsung atau diawali nama_jurusan
    $allJurusans = $pdo->query('SELECT id, nama_jurusan FROM jurusans ORDER BY CHAR_LENGTH(nama_jurusan) DESC')->fetchAll();
    if (!empty($allJurusans)) {
        $kandsNull = $pdo->query("SELECT id, jurusan_kelas FROM `kandidats` WHERE (jurusan_id IS NULL OR jurusan_id = 0) AND jurusan_kelas IS NOT NULL AND TRIM(jurusan_kelas) != ''")->fetchAll();
        $stmtUpdKand = $pdo->prepare('UPDATE kandidats SET jurusan_id = :jid WHERE id = :id');
        foreach ($kandsNull as $k) {
            $raw = strtolower(trim((string) $k['jurusan_kelas']));
            foreach ($allJurusans as $j) {
                $jName = strtolower(trim((string) $j['nama_jurusan']));
                if ($raw === $jName || str_starts_with($raw, $jName . ' ') || str_starts_with($raw, $jName . '·') || str_starts_with($raw, $jName . '-')) {
                    $stmtUpdKand->execute([':jid' => (int) $j['id'], ':id' => (int) $k['id']]);
                    break;
                }
            }
        }
    }

    $ready = true;
}

/**
 * Menghasilkan kode singkat unik otomatis dari nama jurusan (misal: Teknik Informatika -> TI)
 */
function generate_unique_kode_jurusan(PDO $pdo, string $nama): string
{
    $words = preg_split('/\s+/', trim($nama)) ?: [];
    $initials = '';
    foreach ($words as $w) {
        $clean = preg_replace('/[^A-Za-z0-9]/', '', $w);
        if ($clean !== '') {
            $initials .= strtoupper($clean[0]);
        }
    }
    $base = substr($initials !== '' ? $initials : strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $nama)), 0, 6);
    if ($base === '') {
        $base = 'JUR';
    }

    $candidate = $base;
    $suffix = 1;
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM jurusans WHERE kode_jurusan = :kode');
    while (true) {
        $stmt->execute([':kode' => $candidate]);
        if ((int) $stmt->fetchColumn() === 0) {
            return $candidate;
        }
        $suffix++;
        $candidate = substr($base, 0, 12) . $suffix;
    }
}

/**
 * Mengambil seluruh daftar jurusan ringkas untuk kebutuhan dropdown formulir
 */
function get_all_jurusans(PDO $pdo): array
{
    ensure_jurusans_ready($pdo);
    $stmt = $pdo->query('
        SELECT id, kode_jurusan, nama_jurusan, jenjang
        FROM jurusans
        ORDER BY nama_jurusan ASC
    ');
    return $stmt->fetchAll();
}

/**
 * Mengambil seluruh daftar jurusan beserta jumlah kelas, mahasiswa, dan kandidat terkait
 */
function get_all_jurusans_with_stats(PDO $pdo): array
{
    ensure_jurusans_ready($pdo);
    $stmt = $pdo->query('
        SELECT
            j.id,
            j.kode_jurusan,
            j.nama_jurusan,
            j.jenjang,
            j.created_at,
            COALESCE(ks.total_kelas, 0) AS total_kelas,
            COALESCE(ks.total_mahasiswa, 0) AS total_mahasiswa,
            COALESCE(cnd.total_kandidat, 0) AS total_kandidat
        FROM jurusans j
        LEFT JOIN (
            SELECT
                k.jurusan_id,
                COUNT(DISTINCT k.id) AS total_kelas,
                COUNT(m.id) AS total_mahasiswa
            FROM kelas k
            LEFT JOIN mahasiswas m ON m.kelas_id = k.id
            GROUP BY k.jurusan_id
        ) ks ON ks.jurusan_id = j.id
        LEFT JOIN (
            SELECT
                jurusan_id,
                COUNT(*) AS total_kandidat
            FROM kandidats
            GROUP BY jurusan_id
        ) cnd ON cnd.jurusan_id = j.id
        ORDER BY j.nama_jurusan ASC
    ');
    return $stmt->fetchAll();
}

/**
 * Mengambil statistik ringkas master jurusan
 */
function get_jurusan_global_stats(PDO $pdo): array
{
    ensure_jurusans_ready($pdo);
    $rows = get_all_jurusans_with_stats($pdo);
    $totalKelas = 0;
    $totalMahasiswa = 0;
    $totalKandidat = 0;
    foreach ($rows as $r) {
        $totalKelas += (int) $r['total_kelas'];
        $totalMahasiswa += (int) $r['total_mahasiswa'];
        $totalKandidat += (int) $r['total_kandidat'];
    }
    return [
        'total_jurusan'   => count($rows),
        'total_kelas'     => $totalKelas,
        'total_mahasiswa' => $totalMahasiswa,
        'total_kandidat'  => $totalKandidat,
    ];
}

/**
 * Mencari satu jurusan berdasarkan ID
 */
function find_jurusan_by_id(PDO $pdo, int $id): ?array
{
    ensure_jurusans_ready($pdo);
    $stmt = $pdo->prepare('SELECT id, kode_jurusan, nama_jurusan, jenjang FROM jurusans WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/**
 * Mencari satu jurusan berdasarkan nama jurusan
 */
function find_jurusan_by_nama(PDO $pdo, string $nama): ?array
{
    ensure_jurusans_ready($pdo);
    $stmt = $pdo->prepare('SELECT id, kode_jurusan, nama_jurusan, jenjang FROM jurusans WHERE LOWER(TRIM(nama_jurusan)) = LOWER(TRIM(:nama)) LIMIT 1');
    $stmt->execute([':nama' => $nama]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/**
 * Menambahkan jurusan baru
 */
function create_jurusan(PDO $pdo, string $kode_jurusan, string $nama_jurusan, string $jenjang = 'S1'): int
{
    ensure_jurusans_ready($pdo);
    $stmt = $pdo->prepare('
        INSERT INTO jurusans (kode_jurusan, nama_jurusan, jenjang)
        VALUES (:kode, :nama, :jenjang)
    ');
    $stmt->execute([
        ':kode'    => strtoupper(trim($kode_jurusan)),
        ':nama'    => trim($nama_jurusan),
        ':jenjang' => strtoupper(trim($jenjang)) ?: 'S1',
    ]);
    return (int) $pdo->lastInsertId();
}

/**
 * Memperbarui data jurusan dan menyinkronkan nama jurusan ke seluruh kelas & kandidat terhubung
 */
function update_jurusan(PDO $pdo, int $id, string $kode_jurusan, string $nama_jurusan, string $jenjang = 'S1'): bool
{
    ensure_jurusans_ready($pdo);
    $existing = find_jurusan_by_id($pdo, $id);
    if (!$existing) {
        return false;
    }

    $oldNama = trim((string) $existing['nama_jurusan']);
    $newKode = strtoupper(trim($kode_jurusan));
    $newNama = trim($nama_jurusan);
    $newJenjang = strtoupper(trim($jenjang)) ?: 'S1';

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('
            UPDATE jurusans
            SET kode_jurusan = :kode,
                nama_jurusan = :nama,
                jenjang = :jenjang
            WHERE id = :id
        ');
        $stmt->execute([
            ':kode'    => $newKode,
            ':nama'    => $newNama,
            ':jenjang' => $newJenjang,
            ':id'      => $id,
        ]);

        // Sinkronkan nama jurusan di seluruh kelas yang terhubung
        $stmtKelas = $pdo->prepare('
            UPDATE kelas
            SET jurusan = :new_nama,
                jurusan_id = :id
            WHERE jurusan_id = :id_match OR LOWER(TRIM(jurusan)) = LOWER(TRIM(:old_nama))
        ');
        $stmtKelas->execute([
            ':new_nama' => $newNama,
            ':id'       => $id,
            ':id_match' => $id,
            ':old_nama' => $oldNama,
        ]);

        // Sinkronkan label jurusan di seluruh kandidat yang terhubung (termasuk menjaga akhiran " · Smt X" jika ada)
        $stmtKandList = $pdo->prepare('
            SELECT id, jurusan_kelas
            FROM kandidats
            WHERE jurusan_id = :id_match OR LOWER(TRIM(jurusan_kelas)) = LOWER(TRIM(:old_nama))
        ');
        $stmtKandList->execute([
            ':id_match' => $id,
            ':old_nama' => $oldNama,
        ]);

        $stmtUpdKand = $pdo->prepare('UPDATE kandidats SET jurusan_id = :jid, jurusan_kelas = :jkelas WHERE id = :id');
        foreach ($stmtKandList->fetchAll() as $kRow) {
            $curLabel = trim((string) ($kRow['jurusan_kelas'] ?? ''));
            $updatedLabel = $newNama;
            if ($oldNama !== '' && str_starts_with(strtolower($curLabel), strtolower($oldNama))) {
                $suffix = trim(substr($curLabel, strlen($oldNama)));
                if ($suffix !== '') {
                    $updatedLabel = $newNama . ' ' . $suffix;
                }
            }
            $stmtUpdKand->execute([
                ':jid'    => $id,
                ':jkelas' => $updatedLabel,
                ':id'     => (int) $kRow['id'],
            ]);
        }

        $pdo->commit();
        return true;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/**
 * Menghapus jurusan jika tidak lagi digunakan oleh kelas atau kandidat
 */
function delete_jurusan(PDO $pdo, int $id): array
{
    ensure_jurusans_ready($pdo);
    $existing = find_jurusan_by_id($pdo, $id);
    if (!$existing) {
        return ['success' => false, 'message' => 'Data jurusan tidak ditemukan.'];
    }

    $stmtCountKelas = $pdo->prepare('SELECT COUNT(*) FROM kelas WHERE jurusan_id = :id OR LOWER(TRIM(jurusan)) = LOWER(TRIM(:nama))');
    $stmtCountKelas->execute([':id' => $id, ':nama' => $existing['nama_jurusan']]);
    $usedKelas = (int) $stmtCountKelas->fetchColumn();

    $stmtCountKand = $pdo->prepare('SELECT COUNT(*) FROM kandidats WHERE jurusan_id = :id OR LOWER(TRIM(jurusan_kelas)) = LOWER(TRIM(:nama))');
    $stmtCountKand->execute([':id' => $id, ':nama' => $existing['nama_jurusan']]);
    $usedKand = (int) $stmtCountKand->fetchColumn();

    if ($usedKelas > 0 || $usedKand > 0) {
        $parts = [];
        if ($usedKelas > 0) $parts[] = "{$usedKelas} kelas";
        if ($usedKand > 0) $parts[] = "{$usedKand} kandidat";
        return [
            'success' => false,
            'message' => "Jurusan {$existing['nama_jurusan']} tidak dapat dihapus karena masih terhubung dengan " . implode(' dan ', $parts) . '.',
        ];
    }

    $stmtDel = $pdo->prepare('DELETE FROM jurusans WHERE id = :id');
    $stmtDel->execute([':id' => $id]);
    return ['success' => true, 'message' => "Jurusan {$existing['nama_jurusan']} berhasil dihapus."];
}

