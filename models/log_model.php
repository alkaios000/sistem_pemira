<?php
/**
 * Model Log Audit Keamanan & Deteksi Kecurangan (Audit & Fraud Detection Log Model)
 * Mencatat aktivitas pemilihan: NORMAL (Baris Hijau) dan FRAUD (Baris Merah).
 */

/**
 * Mencatat satu entri log audit ke dalam basis data
 */
function record_audit_log(PDO $pdo, array $data): int
{
    $nim = !empty($data['nim']) ? trim((string) $data['nim']) : null;
    $nama_cache = $data['nama_mahasiswa_cache'] ?? null;

    // Jika NIM ada tetapi nama_mahasiswa_cache belum diisi, cari dari tabel mahasiswas
    if ($nim !== null && empty($nama_cache)) {
        $stmt_mhs = $pdo->prepare('SELECT nama_lengkap FROM mahasiswas WHERE nim = :nim LIMIT 1');
        $stmt_mhs->execute([':nim' => $nim]);
        $found_name = $stmt_mhs->fetchColumn();
        if ($found_name) {
            $nama_cache = (string) $found_name;
        }
    }

    $stmt = $pdo->prepare('
        INSERT INTO audit_logs (
            bilik_id,
            nim,
            nama_mahasiswa_cache,
            device_id,
            fingerprint_hash,
            browser_session_id,
            ip_address,
            user_agent,
            status_log,
            kode_event,
            judul_event,
            keterangan,
            created_at
        ) VALUES (
            :bilik_id,
            :nim,
            :nama_mahasiswa_cache,
            :device_id,
            :fingerprint_hash,
            :browser_session_id,
            :ip_address,
            :user_agent,
            :status_log,
            :kode_event,
            :judul_event,
            :keterangan,
            NOW()
        )
    ');

    $stmt->execute([
        ':bilik_id'             => !empty($data['bilik_id']) ? (int) $data['bilik_id'] : null,
        ':nim'                  => $nim,
        ':nama_mahasiswa_cache' => $nama_cache,
        ':device_id'            => substr((string) ($data['device_id'] ?? 'unknown_device'), 0, 128),
        ':fingerprint_hash'     => !empty($data['fingerprint_hash']) ? substr((string) $data['fingerprint_hash'], 0, 128) : null,
        ':browser_session_id'   => !empty($data['browser_session_id']) ? substr((string) $data['browser_session_id'], 0, 128) : null,
        ':ip_address'           => substr((string) ($data['ip_address'] ?? get_client_ip()), 0, 45),
        ':user_agent'           => substr((string) ($data['user_agent'] ?? get_client_user_agent()), 0, 255),
        ':status_log'           => ($data['status_log'] ?? 'NORMAL') === 'FRAUD' ? 'FRAUD' : 'NORMAL',
        ':kode_event'           => substr((string) ($data['kode_event'] ?? 'SYSTEM_EVENT'), 0, 64),
        ':judul_event'          => substr((string) ($data['judul_event'] ?? 'Aktivitas Sistem'), 0, 150),
        ':keterangan'          => (string) ($data['keterangan'] ?? ''),
    ]);

    return (int) $pdo->lastInsertId();
}

/**
 * Mengambil daftar log audit dengan relasi bilik dan data kelas mahasiswa (tanpa N+1 query)
 */
function get_audit_logs(
    PDO $pdo,
    ?string $status_filter = null,
    ?int $bilik_id = null,
    string $search = '',
    int $limit = 150
): array {
    $conditions = [];
    $params = [];

    if ($status_filter !== null && in_array($status_filter, ['NORMAL', 'FRAUD'], true)) {
        $conditions[] = 'l.status_log = :status_log';
        $params[':status_log'] = $status_filter;
    }

    if ($bilik_id !== null && $bilik_id > 0) {
        $conditions[] = 'l.bilik_id = :bilik_id';
        $params[':bilik_id'] = $bilik_id;
    }

    if ($search !== '') {
        $conditions[] = '(l.nim LIKE :s1 OR l.nama_mahasiswa_cache LIKE :s2 OR l.device_id LIKE :s3 OR l.kode_event LIKE :s4 OR l.keterangan LIKE :s5)';
        $kw = '%' . $search . '%';
        $params[':s1'] = $kw;
        $params[':s2'] = $kw;
        $params[':s3'] = $kw;
        $params[':s4'] = $kw;
        $params[':s5'] = $kw;
    }

    $where_sql = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';
    $safe_limit = max(10, min(500, $limit));

    $stmt = $pdo->prepare("
        SELECT
            l.id,
            l.bilik_id,
            l.nim,
            COALESCE(m.nama_lengkap, l.nama_mahasiswa_cache) AS nama_mahasiswa,
            k.nama_kelas,
            k.jurusan,
            k.semester,
            b.nomor_bilik,
            b.nama_bilik,
            l.device_id,
            l.fingerprint_hash,
            l.browser_session_id,
            l.ip_address,
            l.user_agent,
            l.status_log,
            l.kode_event,
            l.judul_event,
            l.keterangan,
            l.created_at
        FROM audit_logs l
        LEFT JOIN mahasiswas m ON m.nim = l.nim
        LEFT JOIN kelas k ON k.id = m.kelas_id
        LEFT JOIN bilik_suaras b ON b.id = l.bilik_id
        {$where_sql}
        ORDER BY l.id DESC
        LIMIT {$safe_limit}
    ");
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/**
 * Mengambil ringkasan statistik log keamanan (Total, Normal/Hijau, Fraud/Merah, dan rincian per jenis kecurangan)
 */
function get_audit_log_stats(PDO $pdo): array
{
    $stmt = $pdo->query('
        SELECT
            COUNT(*) AS total_logs,
            COALESCE(SUM(CASE WHEN status_log = "NORMAL" THEN 1 ELSE 0 END), 0) AS total_normal,
            COALESCE(SUM(CASE WHEN status_log = "FRAUD" THEN 1 ELSE 0 END), 0) AS total_fraud
        FROM audit_logs
    ');
    $summary = $stmt->fetch() ?: [
        'total_logs'   => 0,
        'total_normal' => 0,
        'total_fraud'  => 0,
    ];

    $stmt_breakdown = $pdo->query('
        SELECT kode_event, judul_event, COUNT(*) AS jumlah
        FROM audit_logs
        WHERE status_log = "FRAUD"
        GROUP BY kode_event, judul_event
        ORDER BY jumlah DESC
    ');

    return [
        'total_logs'      => (int) $summary['total_logs'],
        'total_normal'    => (int) $summary['total_normal'],
        'total_fraud'     => (int) $summary['total_fraud'],
        'fraud_breakdown' => $stmt_breakdown->fetchAll(),
    ];
}

