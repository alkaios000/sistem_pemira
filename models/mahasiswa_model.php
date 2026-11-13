<?php
/**
 * Model Kelas & Mahasiswa / Pemilih (Voter & Class Management Model)
 * Menerapkan agregasi query yang efisien dan mendukung Batch Import / Preview.
 */

require_once BASE_PATH . '/models/jurusan_model.php';

/**
 * Mengambil seluruh daftar kelas beserta jumlah mahasiswa yang sudah dan belum memilih dalam 1 query
 */
function get_all_kelas_with_stats(PDO $pdo): array
{
    ensure_jurusans_ready($pdo);
    $stmt = $pdo->query('
        SELECT
            k.id,
            k.jurusan_id,
            k.nama_kelas,
            COALESCE(j.nama_jurusan, k.jurusan) AS jurusan,
            j.kode_jurusan,
            k.semester,
            k.created_at,
            COUNT(m.id) AS total_mahasiswa,
            COALESCE(SUM(CASE WHEN m.sudah_memilih = 1 THEN 1 ELSE 0 END), 0) AS jumlah_sudah_memilih,
            COALESCE(SUM(CASE WHEN m.sudah_memilih = 0 THEN 1 ELSE 0 END), 0) AS jumlah_belum_memilih,
            COALESCE(SUM(CASE WHEN m.status_hadir = 1 THEN 1 ELSE 0 END), 0) AS jumlah_hadir
        FROM kelas k
        LEFT JOIN jurusans j ON j.id = k.jurusan_id
        LEFT JOIN mahasiswas m ON m.kelas_id = k.id
        GROUP BY k.id, k.jurusan_id, k.nama_kelas, j.nama_jurusan, j.kode_jurusan, k.jurusan, k.semester, k.created_at
        ORDER BY k.semester ASC, jurusan ASC, k.nama_kelas ASC
    ');
    return $stmt->fetchAll();
}

/**
 * Mencari detail satu kelas beserta statistik pemilihnya
 */
function find_kelas_by_id(PDO $pdo, int $id): ?array
{
    ensure_jurusans_ready($pdo);
    $stmt = $pdo->prepare('
        SELECT
            k.id,
            k.jurusan_id,
            k.nama_kelas,
            COALESCE(j.nama_jurusan, k.jurusan) AS jurusan,
            j.kode_jurusan,
            k.semester,
            k.created_at,
            COUNT(m.id) AS total_mahasiswa,
            COALESCE(SUM(CASE WHEN m.sudah_memilih = 1 THEN 1 ELSE 0 END), 0) AS jumlah_sudah_memilih,
            COALESCE(SUM(CASE WHEN m.sudah_memilih = 0 THEN 1 ELSE 0 END), 0) AS jumlah_belum_memilih,
            COALESCE(SUM(CASE WHEN m.status_hadir = 1 THEN 1 ELSE 0 END), 0) AS jumlah_hadir
        FROM kelas k
        LEFT JOIN jurusans j ON j.id = k.jurusan_id
        LEFT JOIN mahasiswas m ON m.kelas_id = k.id
        WHERE k.id = :id
        GROUP BY k.id, k.jurusan_id, k.nama_kelas, j.nama_jurusan, j.kode_jurusan, k.jurusan, k.semester, k.created_at
        LIMIT 1
    ');
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/**
 * Menambahkan kelas baru
 */
function create_kelas(PDO $pdo, string $nama_kelas, string $jurusan, int $semester, ?int $jurusan_id = null): int
{
    ensure_jurusans_ready($pdo);
    $stmt = $pdo->prepare('
        INSERT INTO kelas (jurusan_id, nama_kelas, jurusan, semester)
        VALUES (:jurusan_id, :nama_kelas, :jurusan, :semester)
    ');
    $stmt->execute([
        ':jurusan_id' => $jurusan_id,
        ':nama_kelas' => $nama_kelas,
        ':jurusan'    => $jurusan,
        ':semester'   => $semester,
    ]);
    return (int) $pdo->lastInsertId();
}

/**
 * Memperbarui data kelas
 */
function update_kelas(PDO $pdo, int $id, string $nama_kelas, string $jurusan, int $semester, ?int $jurusan_id = null): bool
{
    ensure_jurusans_ready($pdo);
    $stmt = $pdo->prepare('
        UPDATE kelas
        SET jurusan_id = :jurusan_id,
            nama_kelas = :nama_kelas,
            jurusan = :jurusan,
            semester = :semester
        WHERE id = :id
    ');
    return $stmt->execute([
        ':jurusan_id' => $jurusan_id,
        ':nama_kelas' => $nama_kelas,
        ':jurusan'    => $jurusan,
        ':semester'   => $semester,
        ':id'         => $id,
    ]);
}

/**
 * Menghapus kelas beserta mahasiswa di dalamnya (CASCADE)
 */
function delete_kelas(PDO $pdo, int $id): bool
{
    $stmt = $pdo->prepare('DELETE FROM kelas WHERE id = :id');
    return $stmt->execute([':id' => $id]);
}

/**
 * Mengambil daftar mahasiswa dengan filter kelas, pencarian NIM/Nama, dan status memilih
 */
function get_mahasiswas_by_filter(PDO $pdo, ?int $kelas_id = null, string $search = '', ?int $status_vote = null): array
{
    $conditions = [];
    $params = [];

    if ($kelas_id !== null && $kelas_id > 0) {
        $conditions[] = 'm.kelas_id = :kelas_id';
        $params[':kelas_id'] = $kelas_id;
    }

    if ($search !== '') {
        $conditions[] = '(m.nim LIKE :search_nim OR m.nama_lengkap LIKE :search_nama)';
        $params[':search_nim'] = '%' . $search . '%';
        $params[':search_nama'] = '%' . $search . '%';
    }

    if ($status_vote !== null && in_array($status_vote, [0, 1], true)) {
        $conditions[] = 'm.sudah_memilih = :status_vote';
        $params[':status_vote'] = $status_vote;
    }

    $where_sql = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

    $stmt = $pdo->prepare("
        SELECT
            m.id,
            m.kelas_id,
            m.nim,
            m.nama_lengkap,
            m.status_hadir,
            m.sudah_memilih,
            m.waktu_memilih,
            m.created_at,
            k.nama_kelas,
            k.jurusan,
            k.semester
        FROM mahasiswas m
        INNER JOIN kelas k ON k.id = m.kelas_id
        {$where_sql}
        ORDER BY k.semester ASC, k.nama_kelas ASC, m.nim ASC
    ");
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/**
 * Mencari mahasiswa berdasarkan NIM lengkap dengan informasi kelasnya
 */
function find_mahasiswa_by_nim(PDO $pdo, string $nim): ?array
{
    $stmt = $pdo->prepare('
        SELECT
            m.id,
            m.kelas_id,
            m.nim,
            m.nama_lengkap,
            m.status_hadir,
            m.sudah_memilih,
            m.waktu_memilih,
            k.nama_kelas,
            k.jurusan,
            k.semester
        FROM mahasiswas m
        INNER JOIN kelas k ON k.id = m.kelas_id
        WHERE m.nim = :nim
        LIMIT 1
    ');
    $stmt->execute([':nim' => $nim]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/**
 * Mencari mahasiswa berdasarkan ID
 */
function find_mahasiswa_by_id(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare('
        SELECT m.*, k.nama_kelas, k.jurusan, k.semester
        FROM mahasiswas m
        INNER JOIN kelas k ON k.id = m.kelas_id
        WHERE m.id = :id
        LIMIT 1
    ');
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/**
 * Menambahkan satu data mahasiswa ke dalam kelas
 */
function create_mahasiswa(PDO $pdo, int $kelas_id, string $nim, string $nama_lengkap, int $status_hadir = 1): bool
{
    $stmt = $pdo->prepare('
        INSERT INTO mahasiswas (kelas_id, nim, nama_lengkap, status_hadir, sudah_memilih)
        VALUES (:kelas_id, :nim, :nama_lengkap, :status_hadir, 0)
    ');
    return $stmt->execute([
        ':kelas_id'     => $kelas_id,
        ':nim'          => $nim,
        ':nama_lengkap' => $nama_lengkap,
        ':status_hadir' => $status_hadir ? 1 : 0,
    ]);
}

/**
 * Memperbarui data identitas mahasiswa
 */
function update_mahasiswa(PDO $pdo, int $id, int $kelas_id, string $nim, string $nama_lengkap, int $status_hadir): bool
{
    $stmt = $pdo->prepare('
        UPDATE mahasiswas
        SET kelas_id = :kelas_id,
            nim = :nim,
            nama_lengkap = :nama_lengkap,
            status_hadir = :status_hadir
        WHERE id = :id
    ');
    return $stmt->execute([
        ':kelas_id'     => $kelas_id,
        ':nim'          => $nim,
        ':nama_lengkap' => $nama_lengkap,
        ':status_hadir' => $status_hadir ? 1 : 0,
        ':id'           => $id,
    ]);
}

/**
 * Mengubah status kehadiran / aktivasi memilih mahasiswa (Check-In meja panitia)
 */
function toggle_hadir_mahasiswa(PDO $pdo, int $id, int $status_hadir): bool
{
    $stmt = $pdo->prepare('UPDATE mahasiswas SET status_hadir = :status_hadir WHERE id = :id');
    return $stmt->execute([
        ':status_hadir' => $status_hadir ? 1 : 0,
        ':id'           => $id,
    ]);
}

/**
 * Mengubah status hadir seluruh mahasiswa dalam satu kelas sekaligus
 */
function set_all_hadir_in_kelas(PDO $pdo, int $kelas_id, int $status_hadir): bool
{
    $stmt = $pdo->prepare('UPDATE mahasiswas SET status_hadir = :status_hadir WHERE kelas_id = :kelas_id');
    return $stmt->execute([
        ':status_hadir' => $status_hadir ? 1 : 0,
        ':kelas_id'     => $kelas_id,
    ]);
}

/**
 * Menghapus satu data mahasiswa
 */
function delete_mahasiswa(PDO $pdo, int $id): bool
{
    $stmt = $pdo->prepare('DELETE FROM mahasiswas WHERE id = :id');
    return $stmt->execute([':id' => $id]);
}

/**
 * Memvalidasi daftar baris mahasiswa hasil CSV/Excel/Copy-Paste untuk tahap Review/Preview
 */
function validate_batch_mahasiswas(PDO $pdo, array $raw_rows): array
{
    if (empty($raw_rows)) {
        return ['rows' => [], 'valid_count' => 0, 'invalid_count' => 0];
    }

    // Ambil seluruh NIM yang sudah terdaftar di database untuk pengecekan cepat
    $stmt_existing = $pdo->query('
        SELECT m.nim, m.nama_lengkap, k.nama_kelas
        FROM mahasiswas m
        INNER JOIN kelas k ON k.id = m.kelas_id
    ');
    $existing_map = [];
    foreach ($stmt_existing->fetchAll() as $ex) {
        $existing_map[strtolower(trim($ex['nim']))] = $ex;
    }

    $seen_in_batch = [];
    $validated_rows = [];
    $valid_count = 0;
    $invalid_count = 0;

    foreach ($raw_rows as $idx => $item) {
        $nim = preg_replace('/[^a-zA-Z0-9\-\.]/', '', trim((string) ($item['nim'] ?? '')));
        $nama = trim((string) ($item['nama_lengkap'] ?? $item['nama'] ?? ''));

        $nim_key = strtolower($nim);
        $is_valid = true;
        $catatan = 'Siap diimpor';

        if ($nim === '' || $nama === '') {
            $is_valid = false;
            $catatan = 'NIM atau Nama kosong';
        } elseif (isset($existing_map[$nim_key])) {
            $is_valid = false;
            $ex_info = $existing_map[$nim_key];
            $catatan = 'NIM sudah terdaftar (' . $ex_info['nama_lengkap'] . ' - ' . $ex_info['nama_kelas'] . ')';
        } elseif (isset($seen_in_batch[$nim_key])) {
            $is_valid = false;
            $catatan = 'Duplikat NIM pada daftar impor (baris ke-' . $seen_in_batch[$nim_key] . ')';
        } else {
            $seen_in_batch[$nim_key] = $idx + 1;
        }

        if ($is_valid) {
            $valid_count++;
        } else {
            $invalid_count++;
        }

        $validated_rows[] = [
            'no'           => $idx + 1,
            'nim'          => $nim,
            'nama_lengkap' => $nama,
            'is_valid'     => $is_valid,
            'catatan'      => $catatan,
        ];
    }

    return [
        'rows'          => $validated_rows,
        'valid_count'   => $valid_count,
        'invalid_count' => $invalid_count,
    ];
}

/**
 * Menyimpan hasil batch import mahasiswa yang sudah direview ke dalam database secara atomik
 */
function import_batch_mahasiswas(PDO $pdo, int $kelas_id, array $rows): array
{
    $validation = validate_batch_mahasiswas($pdo, $rows);
    $inserted = 0;
    $skipped = 0;

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('
            INSERT INTO mahasiswas (kelas_id, nim, nama_lengkap, status_hadir, sudah_memilih)
            VALUES (:kelas_id, :nim, :nama_lengkap, 1, 0)
        ');

        foreach ($validation['rows'] as $row) {
            if (!$row['is_valid']) {
                $skipped++;
                continue;
            }
            $stmt->execute([
                ':kelas_id'     => $kelas_id,
                ':nim'          => $row['nim'],
                ':nama_lengkap' => $row['nama_lengkap'],
            ]);
            $inserted++;
        }

        $pdo->commit();
        return [
            'inserted' => $inserted,
            'skipped'  => $skipped,
        ];
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/**
 * Mengambil statistik global Daftar Pemilih Tetap (DPT) untuk Dashboard
 */
function get_dpt_global_stats(PDO $pdo): array
{
    $stmt = $pdo->query('
        SELECT
            COUNT(*) AS total_dpt,
            COALESCE(SUM(CASE WHEN sudah_memilih = 1 THEN 1 ELSE 0 END), 0) AS sudah_memilih,
            COALESCE(SUM(CASE WHEN sudah_memilih = 0 THEN 1 ELSE 0 END), 0) AS belum_memilih,
            COALESCE(SUM(CASE WHEN status_hadir = 1 THEN 1 ELSE 0 END), 0) AS total_hadir
        FROM mahasiswas
    ');
    $row = $stmt->fetch() ?: [
        'total_dpt'     => 0,
        'sudah_memilih' => 0,
        'belum_memilih' => 0,
        'total_hadir'   => 0,
    ];

    $total = (int) $row['total_dpt'];
    $sudah = (int) $row['sudah_memilih'];
    $belum = (int) $row['belum_memilih'];

    $stmt_kelas_count = $pdo->query('SELECT COUNT(*) FROM kelas');
    $total_kelas = (int) $stmt_kelas_count->fetchColumn();

    return [
        'total_kelas'        => $total_kelas,
        'total_dpt'          => $total,
        'sudah_memilih'      => $sudah,
        'belum_memilih'      => $belum,
        'total_hadir'        => (int) $row['total_hadir'],
        'persen_partisipasi' => $total > 0 ? round(($sudah / $total) * 100, 1) : 0.0,
        'persen_belum'       => $total > 0 ? round(($belum / $total) * 100, 1) : 0.0,
    ];
}

