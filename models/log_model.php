<?php
/**
 * Model Log Audit Keamanan & Deteksi Kecurangan (Audit & Fraud Detection Log Model)
 * Mencatat aktivitas pemilihan & panel operator serta mendukung penyaringan berdasarkan peran.
 */

/**
 * Mencatat satu entri log audit ke dalam basis data
 */
function record_audit_log(PDO $pdo, array $data): int
{
    $nim = !empty($data['nim']) ? trim((string) $data['nim']) : null;
    $nama_cache = $data['nama_mahasiswa_cache'] ?? null;

    if ($nim !== null && empty($nama_cache)) {
        $stmt_mhs = $pdo->prepare('SELECT nama_lengkap FROM mahasiswas WHERE nim = :nim LIMIT 1');
        $stmt_mhs->execute([':nim' => $nim]);
        $found_name = $stmt_mhs->fetchColumn();
        if ($found_name) {
            $nama_cache = (string) $found_name;
        }
    }

    $allowedRoles = ['superadmin', 'kprm', 'banwasra'];
    $actorRole = isset($data['actor_role']) && in_array($data['actor_role'], $allowedRoles, true)
        ? $data['actor_role']
        : null;

    $deviceId = substr((string) ($data['device_id'] ?? 'unknown_device'), 0, 128);
    $allowedCategories = ['pemilih', 'admin'];
    $logCategory = isset($data['log_category']) && in_array($data['log_category'], $allowedCategories, true)
        ? $data['log_category']
        : (($actorRole !== null || !empty($data['actor_user_id']) || !empty($data['actor_username']) || str_starts_with($deviceId, 'panel_'))
            ? 'admin'
            : 'pemilih');

    $stmt = $pdo->prepare('
        INSERT INTO audit_logs (
            bilik_id,
            actor_user_id,
            actor_username,
            actor_role,
            log_category,
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
            :actor_user_id,
            :actor_username,
            :actor_role,
            :log_category,
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
        ':actor_user_id'        => !empty($data['actor_user_id']) ? (int) $data['actor_user_id'] : null,
        ':actor_username'       => !empty($data['actor_username']) ? substr((string) $data['actor_username'], 0, 60) : null,
        ':actor_role'           => $actorRole,
        ':log_category'         => $logCategory,
        ':nim'                  => $nim,
        ':nama_mahasiswa_cache' => $nama_cache,
        ':device_id'            => $deviceId,
        ':fingerprint_hash'     => !empty($data['fingerprint_hash']) ? substr((string) $data['fingerprint_hash'], 0, 128) : null,
        ':browser_session_id'   => !empty($data['browser_session_id']) ? substr((string) $data['browser_session_id'], 0, 128) : null,
        ':ip_address'           => substr((string) ($data['ip_address'] ?? get_client_ip()), 0, 45),
        ':user_agent'           => substr((string) ($data['user_agent'] ?? get_client_user_agent()), 0, 255),
        ':status_log'           => ($data['status_log'] ?? 'NORMAL') === 'FRAUD' ? 'FRAUD' : 'NORMAL',
        ':kode_event'           => substr((string) ($data['kode_event'] ?? 'SYSTEM_EVENT'), 0, 64),
        ':judul_event'          => substr((string) ($data['judul_event'] ?? 'Aktivitas Sistem'), 0, 150),
        ':keterangan'           => (string) ($data['keterangan'] ?? ''),
    ]);

    return (int) $pdo->lastInsertId();
}

/**
 * Mencatat aktivitas panel/administrasi beserta identitas aktor yang login (superadmin/kprm/banwasra)
 */
function record_admin_audit_log(
    PDO $pdo,
    string $kode_event,
    string $judul_event,
    string $keterangan,
    string $status_log = 'NORMAL',
    ?int $bilik_id = null,
    ?string $nim = null,
    ?array $actor_override = null
): int {
    $actorId = $actor_override['user_id'] ?? ($_SESSION['user_id'] ?? null);
    $actorUsername = $actor_override['username'] ?? ($_SESSION['username'] ?? null);
    $actorRole = $actor_override['role'] ?? ($_SESSION['user_role'] ?? null);

    return record_audit_log($pdo, [
        'bilik_id'       => $bilik_id,
        'actor_user_id'  => $actorId,
        'actor_username' => $actorUsername,
        'actor_role'     => $actorRole,
        'log_category'   => 'admin',
        'nim'            => $nim,
        'device_id'      => 'panel_' . ($actorUsername ?: 'guest'),
        'ip_address'     => get_client_ip(),
        'user_agent'     => get_client_user_agent(),
        'status_log'     => $status_log,
        'kode_event'     => $kode_event,
        'judul_event'    => $judul_event,
        'keterangan'     => $keterangan,
    ]);
}

/**
 * Mengambil daftar log audit dengan relasi bilik, kelas, dan penyaringan log superadmin untuk BANWASRA
 */
function get_audit_logs(
    PDO $pdo,
    ?string $status_filter = null,
    ?int $bilik_id = null,
    string $search = '',
    int $limit = 150,
    bool $exclude_superadmin = false,
    ?string $category = null,
    ?string $actor_role_filter = null
): array {
    $conditions = [];
    $params = [];

    if ($exclude_superadmin) {
        $conditions[] = '(l.actor_role IS NULL OR l.actor_role <> "superadmin")';
    }

    if ($category !== null && in_array($category, ['pemilih', 'admin'], true)) {
        $conditions[] = 'l.log_category = :log_category';
        $params[':log_category'] = $category;
    }

    if ($actor_role_filter !== null) {
        if ($actor_role_filter === 'guest') {
            $conditions[] = "(l.log_category = 'admin' AND l.actor_role IS NULL)";
        } elseif ($exclude_superadmin && $actor_role_filter === 'superadmin') {
            $conditions[] = '1 = 0';
        } elseif (in_array($actor_role_filter, ['superadmin', 'kprm', 'banwasra'], true)) {
            $conditions[] = 'l.actor_role = :actor_role_filter';
            $params[':actor_role_filter'] = $actor_role_filter;
        }
    }

    if ($status_filter !== null && in_array($status_filter, ['NORMAL', 'FRAUD'], true)) {
        $conditions[] = 'l.status_log = :status_log';
        $params[':status_log'] = $status_filter;
    }

    if ($bilik_id !== null && $bilik_id > 0) {
        $conditions[] = 'l.bilik_id = :bilik_id';
        $params[':bilik_id'] = $bilik_id;
    }

    if ($search !== '') {
        $conditions[] = '(l.nim LIKE :s1 OR l.nama_mahasiswa_cache LIKE :s2 OR l.device_id LIKE :s3 OR l.kode_event LIKE :s4 OR l.keterangan LIKE :s5 OR l.actor_username LIKE :s6 OR l.actor_role LIKE :s7)';
        $kw = '%' . $search . '%';
        $params[':s1'] = $kw;
        $params[':s2'] = $kw;
        $params[':s3'] = $kw;
        $params[':s4'] = $kw;
        $params[':s5'] = $kw;
        $params[':s6'] = $kw;
        $params[':s7'] = $kw;
    }

    $where_sql = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';
    $safe_limit = max(10, min(500, $limit));

    $stmt = $pdo->prepare("
        SELECT
            l.id,
            l.bilik_id,
            l.actor_user_id,
            l.actor_username,
            l.actor_role,
            l.log_category,
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
 * Mengambil ringkasan statistik log keamanan (termasuk opsi pengecualian log superadmin bagi BANWASRA)
 */
function get_audit_log_stats(PDO $pdo, bool $exclude_superadmin = false, ?string $category = null): array
{
    $baseConditions = [];
    if ($exclude_superadmin) {
        $baseConditions[] = '(actor_role IS NULL OR actor_role <> "superadmin")';
    }
    $baseWhere = !empty($baseConditions) ? ' WHERE ' . implode(' AND ', $baseConditions) : '';

    $stmtCategories = $pdo->query("
        SELECT
            COUNT(*) AS total_all,
            COALESCE(SUM(CASE WHEN log_category = 'pemilih' THEN 1 ELSE 0 END), 0) AS total_pemilih,
            COALESCE(SUM(CASE WHEN log_category = 'admin' THEN 1 ELSE 0 END), 0) AS total_admin
        FROM audit_logs
        {$baseWhere}
    ");
    $categoryCounts = $stmtCategories->fetch() ?: [
        'total_all'     => 0,
        'total_pemilih' => 0,
        'total_admin'   => 0,
    ];

    $scopeConditions = $baseConditions;
    $params = [];
    if ($category !== null && in_array($category, ['pemilih', 'admin'], true)) {
        $scopeConditions[] = 'log_category = :log_category';
        $params[':log_category'] = $category;
    }

    $scopeWhere = !empty($scopeConditions) ? ' WHERE ' . implode(' AND ', $scopeConditions) : '';
    $fraudConditions = $scopeConditions;
    $fraudConditions[] = 'status_log = "FRAUD"';
    $fraudWhere = ' WHERE ' . implode(' AND ', $fraudConditions);

    $stmt = $pdo->prepare("
        SELECT
            COUNT(*) AS total_logs,
            COALESCE(SUM(CASE WHEN status_log = 'NORMAL' THEN 1 ELSE 0 END), 0) AS total_normal,
            COALESCE(SUM(CASE WHEN status_log = 'FRAUD' THEN 1 ELSE 0 END), 0) AS total_fraud
        FROM audit_logs
        {$scopeWhere}
    ");
    $stmt->execute($params);
    $summary = $stmt->fetch() ?: [
        'total_logs'   => 0,
        'total_normal' => 0,
        'total_fraud'  => 0,
    ];

    $stmt_breakdown = $pdo->prepare("
        SELECT kode_event, judul_event, COUNT(*) AS jumlah
        FROM audit_logs
        {$fraudWhere}
        GROUP BY kode_event, judul_event
        ORDER BY jumlah DESC
    ");
    $stmt_breakdown->execute($params);

    return [
        'total_logs'      => (int) $summary['total_logs'],
        'total_normal'    => (int) $summary['total_normal'],
        'total_fraud'     => (int) $summary['total_fraud'],
        'total_all'       => (int) $categoryCounts['total_all'],
        'total_pemilih'   => (int) $categoryCounts['total_pemilih'],
        'total_admin'     => (int) $categoryCounts['total_admin'],
        'fraud_breakdown' => $stmt_breakdown->fetchAll(),
    ];
}
