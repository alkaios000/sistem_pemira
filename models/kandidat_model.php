<?php
/**
 * Model Kandidat BEM & BPM serta Tim Sukses (Candidate Model)
 * Menerapkan eager loading untuk mencegah N+1 Query Problem.
 */

/**
 * Mengambil daftar kandidat beserta daftar tim suksesnya secara efisien (tanpa N+1 query)
 */
function get_kandidats_by_kategori(PDO $pdo, ?string $kategori = null): array
{
    if ($kategori !== null && in_array($kategori, ['BEM', 'BPM'], true)) {
        $stmt = $pdo->prepare('
            SELECT id, kategori, nomor_urut, nama_calon, nama_wakil, jurusan_kelas,
                   foto_path, visi, misi, jumlah_suara, created_at, updated_at
            FROM kandidats
            WHERE kategori = :kategori
            ORDER BY nomor_urut ASC
        ');
        $stmt->execute([':kategori' => $kategori]);
    } else {
        $stmt = $pdo->query('
            SELECT id, kategori, nomor_urut, nama_calon, nama_wakil, jurusan_kelas,
                   foto_path, visi, misi, jumlah_suara, created_at, updated_at
            FROM kandidats
            ORDER BY kategori ASC, nomor_urut ASC
        ');
    }

    $kandidats = $stmt->fetchAll();
    if (empty($kandidats)) {
        return [];
    }

    // Eager loading tim_sukses dengan satu query IN (...)
    $ids = array_column($kandidats, 'id');
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt_timses = $pdo->prepare("
        SELECT id, kandidat_id, nama_anggota, nim_anggota, jabatan_timses
        FROM tim_sukses
        WHERE kandidat_id IN ({$placeholders})
        ORDER BY id ASC
    ");
    $stmt_timses->execute($ids);
    $all_timses = $stmt_timses->fetchAll();

    $timses_by_kandidat = [];
    foreach ($all_timses as $ts) {
        $timses_by_kandidat[(int) $ts['kandidat_id']][] = $ts;
    }

    foreach ($kandidats as &$kandidat) {
        $kid = (int) $kandidat['id'];
        $kandidat['tim_sukses'] = $timses_by_kandidat[$kid] ?? [];
    }
    unset($kandidat);

    return $kandidats;
}

/**
 * Mencari detail satu kandidat beserta tim suksesnya berdasarkan ID
 */
function find_kandidat_by_id(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare('
        SELECT id, kategori, nomor_urut, nama_calon, nama_wakil, jurusan_kelas,
               foto_path, visi, misi, jumlah_suara, created_at, updated_at
        FROM kandidats
        WHERE id = :id
        LIMIT 1
    ');
    $stmt->execute([':id' => $id]);
    $kandidat = $stmt->fetch();

    if (!$kandidat) {
        return null;
    }

    $stmt_ts = $pdo->prepare('
        SELECT id, kandidat_id, nama_anggota, nim_anggota, jabatan_timses
        FROM tim_sukses
        WHERE kandidat_id = :kandidat_id
        ORDER BY id ASC
    ');
    $stmt_ts->execute([':kandidat_id' => $id]);
    $kandidat['tim_sukses'] = $stmt_ts->fetchAll();

    return $kandidat;
}

/**
 * Menyimpan kandidat baru beserta daftar tim suksesnya dalam satu transaksi
 */
function create_kandidat(PDO $pdo, array $data, array $timses_list): int
{
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('
            INSERT INTO kandidats (kategori, nomor_urut, nama_calon, nama_wakil, jurusan_kelas, foto_path, visi, misi, jumlah_suara)
            VALUES (:kategori, :nomor_urut, :nama_calon, :nama_wakil, :jurusan_kelas, :foto_path, :visi, :misi, 0)
        ');
        $stmt->execute([
            ':kategori'      => $data['kategori'],
            ':nomor_urut'    => (int) $data['nomor_urut'],
            ':nama_calon'    => $data['nama_calon'],
            ':nama_wakil'    => !empty($data['nama_wakil']) ? $data['nama_wakil'] : null,
            ':jurusan_kelas' => !empty($data['jurusan_kelas']) ? $data['jurusan_kelas'] : null,
            ':foto_path'     => $data['foto_path'] ?? null,
            ':visi'          => $data['visi'],
            ':misi'          => $data['misi'],
        ]);

        $kandidat_id = (int) $pdo->lastInsertId();
        sync_tim_sukses($pdo, $kandidat_id, $timses_list);

        $pdo->commit();
        return $kandidat_id;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/**
 * Memperbarui data kandidat beserta daftar tim suksesnya
 */
function update_kandidat(PDO $pdo, int $id, array $data, array $timses_list): bool
{
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('
            UPDATE kandidats
            SET kategori = :kategori,
                nomor_urut = :nomor_urut,
                nama_calon = :nama_calon,
                nama_wakil = :nama_wakil,
                jurusan_kelas = :jurusan_kelas,
                foto_path = :foto_path,
                visi = :visi,
                misi = :misi
            WHERE id = :id
        ');
        $stmt->execute([
            ':kategori'      => $data['kategori'],
            ':nomor_urut'    => (int) $data['nomor_urut'],
            ':nama_calon'    => $data['nama_calon'],
            ':nama_wakil'    => !empty($data['nama_wakil']) ? $data['nama_wakil'] : null,
            ':jurusan_kelas' => !empty($data['jurusan_kelas']) ? $data['jurusan_kelas'] : null,
            ':foto_path'     => $data['foto_path'] ?? null,
            ':visi'          => $data['visi'],
            ':misi'          => $data['misi'],
            ':id'            => $id,
        ]);

        sync_tim_sukses($pdo, $id, $timses_list);

        $pdo->commit();
        return true;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/**
 * Menyinkronkan daftar anggota tim sukses untuk seorang kandidat
 */
function sync_tim_sukses(PDO $pdo, int $kandidat_id, array $timses_list): void
{
    $stmt_del = $pdo->prepare('DELETE FROM tim_sukses WHERE kandidat_id = :kandidat_id');
    $stmt_del->execute([':kandidat_id' => $kandidat_id]);

    if (empty($timses_list)) {
        return;
    }

    $stmt_ins = $pdo->prepare('
        INSERT INTO tim_sukses (kandidat_id, nama_anggota, nim_anggota, jabatan_timses)
        VALUES (:kandidat_id, :nama_anggota, :nim_anggota, :jabatan_timses)
    ');

    foreach ($timses_list as $member) {
        $nama = trim((string) ($member['nama_anggota'] ?? ''));
        if ($nama === '') {
            continue;
        }
        $nim = trim((string) ($member['nim_anggota'] ?? ''));
        $jabatan = trim((string) ($member['jabatan_timses'] ?? 'Anggota Tim Sukses'));

        $stmt_ins->execute([
            ':kandidat_id'    => $kandidat_id,
            ':nama_anggota'   => $nama,
            ':nim_anggota'    => $nim !== '' ? $nim : null,
            ':jabatan_timses' => $jabatan !== '' ? $jabatan : 'Anggota Tim Sukses',
        ]);
    }
}

/**
 * Menghapus kandidat berdasarkan ID
 */
function delete_kandidat(PDO $pdo, int $id): bool
{
    $stmt = $pdo->prepare('DELETE FROM kandidats WHERE id = :id');
    return $stmt->execute([':id' => $id]);
}

/**
 * Mengambil ringkasan perolehan suara Live Count untuk kategori tertentu (BEM / BPM)
 */
function get_live_count_summary(PDO $pdo, string $kategori): array
{
    $kandidats = get_kandidats_by_kategori($pdo, $kategori);

    $total_suara_kategori = 0;
    foreach ($kandidats as $k) {
        $total_suara_kategori += (int) $k['jumlah_suara'];
    }

    foreach ($kandidats as &$k) {
        $suara = (int) $k['jumlah_suara'];
        $k['persentase'] = $total_suara_kategori > 0
            ? round(($suara / $total_suara_kategori) * 100, 1)
            : 0.0;
    }
    unset($k);

    $stmt_dpt = $pdo->query('
        SELECT
            COUNT(*) AS total_dpt,
            COALESCE(SUM(CASE WHEN sudah_memilih = 1 THEN 1 ELSE 0 END), 0) AS total_sudah_memilih,
            COALESCE(SUM(CASE WHEN sudah_memilih = 0 THEN 1 ELSE 0 END), 0) AS total_belum_memilih
        FROM mahasiswas
    ');
    $dpt = $stmt_dpt->fetch() ?: [
        'total_dpt'           => 0,
        'total_sudah_memilih' => 0,
        'total_belum_memilih' => 0,
    ];

    $total_dpt = (int) $dpt['total_dpt'];
    $sudah_memilih = (int) $dpt['total_sudah_memilih'];
    $belum_memilih = (int) $dpt['total_belum_memilih'];
    $partisipasi_persen = $total_dpt > 0 ? round(($sudah_memilih / $total_dpt) * 100, 1) : 0.0;

    return [
        'kategori'              => $kategori,
        'kandidats'             => $kandidats,
        'total_suara_kandidat'  => $total_suara_kategori,
        'total_dpt'             => $total_dpt,
        'total_sudah_memilih'   => $sudah_memilih,
        'total_belum_memilih'   => $belum_memilih,
        'partisipasi_persen'    => $partisipasi_persen,
    ];
}

