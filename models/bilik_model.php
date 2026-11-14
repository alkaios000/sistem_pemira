<?php
/**
 * Model Bilik Suara & Manajemen Token QR (Booth & Dynamic Token Model)
 * Mengelola rotasi token QR independen per bilik, penguncian status bilik (IDLE/IN_USE/CLOSED),
 * dan mekanisme pelepasan kunci otomatis jika sesi memilih melewati batas waktu.
 */

/**
 * Mengambil seluruh daftar bilik suara beserta ringkasan jumlah log anomali per bilik
 */
function get_all_biliks(PDO $pdo): array
{
    // Bersihkan bilik yang IN_USE tetapi sudah melewati waktu locked_until (timeout)
    release_expired_bilik_locks($pdo);

    $stmt = $pdo->query('
        SELECT
            b.id,
            b.nomor_bilik,
            b.nama_bilik,
            b.lokasi_bilik,
            b.status_bilik,
            b.current_token,
            b.previous_token,
            b.kiosk_token,
            b.token_generated_at,
            b.active_session_token,
            b.active_device_id,
            b.active_nim,
            b.locked_until,
            b.last_vote_at,
            b.total_suara_bilik,
            b.created_at,
            COALESCE(SUM(CASE WHEN l.status_log = "FRAUD" THEN 1 ELSE 0 END), 0) AS total_fraud_bilik
        FROM bilik_suaras b
        LEFT JOIN audit_logs l ON l.bilik_id = b.id
        GROUP BY
            b.id, b.nomor_bilik, b.nama_bilik, b.lokasi_bilik, b.status_bilik,
            b.current_token, b.previous_token, b.kiosk_token, b.token_generated_at,
            b.active_session_token, b.active_device_id, b.active_nim,
            b.locked_until, b.last_vote_at, b.total_suara_bilik, b.created_at
        ORDER BY b.nomor_bilik ASC
    ');
    return $stmt->fetchAll();
}

/**
 * Mengambil daftar bilik suara yang sudah disanitasi untuk respons JSON / polling / UI publik.
 * Tidak pernah mengekspos secret token (current_token, previous_token, active_session_token)
 * maupun identifier perangkat mentah atau NIM lengkap pemilih aktif.
 */
function get_all_biliks_sanitized(
    PDO $pdo,
    bool $include_fraud_count = true,
    bool $exclude_superadmin_fraud = false
): array {
    release_expired_bilik_locks($pdo);

    $fraud_expr = '0 AS total_fraud_bilik';
    $join_sql = '';
    if ($include_fraud_count) {
        $superadmin_filter = $exclude_superadmin_fraud
            ? ' AND (l.actor_role IS NULL OR l.actor_role <> "superadmin")'
            : '';
        $fraud_expr = 'COALESCE(SUM(CASE WHEN l.status_log = "FRAUD"' . $superadmin_filter . ' THEN 1 ELSE 0 END), 0) AS total_fraud_bilik';
        $join_sql = 'LEFT JOIN audit_logs l ON l.bilik_id = b.id';
    }

    $stmt = $pdo->query("
        SELECT
            b.id,
            b.nomor_bilik,
            b.nama_bilik,
            b.lokasi_bilik,
            b.status_bilik,
            b.current_token,
            b.token_generated_at,
            b.active_nim,
            b.locked_until,
            b.last_vote_at,
            b.total_suara_bilik,
            b.created_at,
            {$fraud_expr}
        FROM bilik_suaras b
        {$join_sql}
        GROUP BY
            b.id, b.nomor_bilik, b.nama_bilik, b.lokasi_bilik, b.status_bilik,
            b.current_token, b.token_generated_at, b.active_nim,
            b.locked_until, b.last_vote_at, b.total_suara_bilik, b.created_at
        ORDER BY b.nomor_bilik ASC
    ");
    $rows = $stmt->fetchAll();
    $now = time();

    return array_map(static function (array $row) use ($now, $include_fraud_count): array {
        $locked_ts = !empty($row['locked_until']) ? strtotime((string) $row['locked_until']) : false;
        $remaining = ($locked_ts && $locked_ts > $now) ? ($locked_ts - $now) : 0;

        return [
            'id'                     => (int) $row['id'],
            'nomor_bilik'            => (int) $row['nomor_bilik'],
            'nama_bilik'             => $row['nama_bilik'],
            'lokasi_bilik'           => $row['lokasi_bilik'],
            'status_bilik'           => $row['status_bilik'],
            'token_hint'             => !empty($row['current_token']) ? substr(hash('sha256', (string) $row['current_token']), 0, 8) : null,
            'token_generated_at'     => $row['token_generated_at'],
            'active_nim_masked'      => function_exists('mask_nim') ? mask_nim($row['active_nim'] ?? null) : null,
            'locked_until'           => $row['locked_until'],
            'remaining_lock_seconds' => $remaining,
            'last_vote_at'           => $row['last_vote_at'],
            'total_suara_bilik'      => (int) $row['total_suara_bilik'],
            'total_fraud_bilik'      => $include_fraud_count ? (int) $row['total_fraud_bilik'] : 0,
            'created_at'             => $row['created_at'],
        ];
    }, $rows);
}

/**
 * Mencari bilik suara berdasarkan ID
 */
function find_bilik_by_id(PDO $pdo, int $id): ?array
{
    release_expired_bilik_locks($pdo);

    $stmt = $pdo->prepare('SELECT * FROM bilik_suaras WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/**
 * Mencari bilik suara berdasarkan nomor urut bilik
 */
function find_bilik_by_nomor(PDO $pdo, int $nomor_bilik): ?array
{
    release_expired_bilik_locks($pdo);

    $stmt = $pdo->prepare('SELECT * FROM bilik_suaras WHERE nomor_bilik = :nomor_bilik LIMIT 1');
    $stmt->execute([':nomor_bilik' => $nomor_bilik]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/**
 * Menambahkan bilik suara baru
 */
function create_bilik(PDO $pdo, int $nomor_bilik, string $nama_bilik, string $lokasi_bilik): int
{
    $stmt = $pdo->prepare('
        INSERT INTO bilik_suaras (nomor_bilik, nama_bilik, lokasi_bilik, status_bilik, current_token, kiosk_token, token_generated_at)
        VALUES (:nomor_bilik, :nama_bilik, :lokasi_bilik, "IDLE", :current_token, :kiosk_token, NOW())
    ');
    $stmt->execute([
        ':nomor_bilik'   => $nomor_bilik,
        ':nama_bilik'    => $nama_bilik,
        ':lokasi_bilik'  => $lokasi_bilik !== '' ? $lokasi_bilik : null,
        ':current_token' => generate_secure_token(20),
        ':kiosk_token'   => generate_secure_token(24),
    ]);
    return (int) $pdo->lastInsertId();
}

/**
 * Memastikan bilik memiliki kiosk_token kriptografis (backfill aman bila kosong)
 */
function ensure_bilik_kiosk_token(PDO $pdo, array $bilik): string
{
    $existing = trim((string) ($bilik['kiosk_token'] ?? ''));
    if ($existing !== '') {
        return $existing;
    }

    $token = generate_secure_token(24);
    $stmt = $pdo->prepare('UPDATE bilik_suaras SET kiosk_token = :token WHERE id = :id');
    $stmt->execute([
        ':token' => $token,
        ':id'    => (int) $bilik['id'],
    ]);
    return $token;
}

/**
 * Memutar/mencabut kunci kiosk bilik sehingga tautan/sesi layar bilik lama otomatis gugur
 */
function rotate_bilik_kiosk_token(PDO $pdo, int $id): ?string
{
    $newToken = generate_secure_token(24);
    $stmt = $pdo->prepare('UPDATE bilik_suaras SET kiosk_token = :token WHERE id = :id');
    $stmt->execute([
        ':token' => $newToken,
        ':id'    => $id,
    ]);
    return $stmt->rowCount() > 0 ? $newToken : null;
}

/**
 * Memvalidasi apakah sesi browser saat ini berhak menampilkan layar kiosk untuk bilik tertentu
 */
function verify_bilik_kiosk_session(PDO $pdo, array $bilik): bool
{
    $bilikId = (int) ($bilik['id'] ?? 0);
    if ($bilikId < 1 || empty($_SESSION['kiosk_booth_access'][$bilikId])) {
        return false;
    }

    $token = ensure_bilik_kiosk_token($pdo, $bilik);
    $expectedHash = hash('sha256', $token);
    return hash_equals($expectedHash, (string) $_SESSION['kiosk_booth_access'][$bilikId]);
}

/**
 * Memperbarui informasi bilik suara
 */
function update_bilik(PDO $pdo, int $id, int $nomor_bilik, string $nama_bilik, string $lokasi_bilik): bool
{
    $stmt = $pdo->prepare('
        UPDATE bilik_suaras
        SET nomor_bilik = :nomor_bilik,
            nama_bilik = :nama_bilik,
            lokasi_bilik = :lokasi_bilik
        WHERE id = :id
    ');
    return $stmt->execute([
        ':nomor_bilik'  => $nomor_bilik,
        ':nama_bilik'   => $nama_bilik,
        ':lokasi_bilik' => $lokasi_bilik !== '' ? $lokasi_bilik : null,
        ':id'           => $id,
    ]);
}

/**
 * Menghapus bilik suara berdasarkan ID
 */
function delete_bilik(PDO $pdo, int $id): bool
{
    $stmt = $pdo->prepare('DELETE FROM bilik_suaras WHERE id = :id');
    return $stmt->execute([':id' => $id]);
}

/**
 * Mengubah status satu bilik suara (IDLE / CLOSED) sekaligus mereset token baru
 */
function set_status_bilik(PDO $pdo, int $id, string $status): bool
{
    $allowed = ['IDLE', 'CLOSED'];
    if (!in_array($status, $allowed, true)) {
        return false;
    }

    $stmt = $pdo->prepare('
        UPDATE bilik_suaras
        SET status_bilik = :status,
            current_token = :new_token,
            previous_token = NULL,
            token_generated_at = NOW(),
            active_session_token = NULL,
            active_device_id = NULL,
            active_nim = NULL,
            locked_until = NULL
        WHERE id = :id
    ');
    return $stmt->execute([
        ':status'    => $status,
        ':new_token' => generate_secure_token(20),
        ':id'        => $id,
    ]);
}

/**
 * Mengubah status seluruh bilik suara secara serentak (Buka Semua / Tutup Semua)
 */
function set_status_all_biliks(PDO $pdo, string $status): bool
{
    $allowed = ['IDLE', 'CLOSED'];
    if (!in_array($status, $allowed, true)) {
        return false;
    }

    $stmt_all = $pdo->query('SELECT id FROM bilik_suaras');
    $ids = $stmt_all->fetchAll(PDO::FETCH_COLUMN);

    $pdo->beginTransaction();
    try {
        $stmt_upd = $pdo->prepare('
            UPDATE bilik_suaras
            SET status_bilik = :status,
                current_token = :new_token,
                previous_token = NULL,
                token_generated_at = NOW(),
                active_session_token = NULL,
                active_device_id = NULL,
                active_nim = NULL,
                locked_until = NULL
            WHERE id = :id
        ');

        foreach ($ids as $id) {
            $stmt_upd->execute([
                ':status'    => $status,
                ':new_token' => generate_secure_token(20),
                ':id'        => (int) $id,
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
 * Melepaskan kunci bilik suara secara paksa (Force Reset) apabila pemilih mengalami kendala teknis
 */
function force_reset_bilik(PDO $pdo, int $id): bool
{
    $stmt = $pdo->prepare('
        UPDATE bilik_suaras
        SET status_bilik = "IDLE",
            current_token = :new_token,
            previous_token = NULL,
            token_generated_at = NOW(),
            active_session_token = NULL,
            active_device_id = NULL,
            active_nim = NULL,
            locked_until = NULL
        WHERE id = :id
    ');
    return $stmt->execute([
        ':new_token' => generate_secure_token(20),
        ':id'        => $id,
    ]);
}

/**
 * Membebaskan bilik yang berstatus IN_USE namun telah melewati batas waktu sesi (timeout)
 */
function release_expired_bilik_locks(PDO $pdo): void
{
    $stmt_expired = $pdo->query('
        SELECT id FROM bilik_suaras
        WHERE status_bilik = "IN_USE"
          AND locked_until IS NOT NULL
          AND locked_until < NOW()
    ');
    $expired_ids = $stmt_expired->fetchAll(PDO::FETCH_COLUMN);

    if (empty($expired_ids)) {
        return;
    }

    $stmt_reset = $pdo->prepare('
        UPDATE bilik_suaras
        SET status_bilik = "IDLE",
            current_token = :new_token,
            previous_token = NULL,
            token_generated_at = NOW(),
            active_session_token = NULL,
            active_device_id = NULL,
            active_nim = NULL,
            locked_until = NULL
        WHERE id = :id
    ');

    foreach ($expired_ids as $bid) {
        $stmt_reset->execute([
            ':new_token' => generate_secure_token(20),
            ':id'        => (int) $bid,
        ]);
    }
}

/**
 * Memutar (rotate) token QR bilik jika sudah melewati interval TTL saat status bilik IDLE,
 * serta mengembalikan status terkini bilik untuk layar Portal QR.
 */
function rotate_or_get_bilik_token(PDO $pdo, int $nomor_bilik, int $ttl_seconds = 8, bool $force_rotate = false): ?array
{
    release_expired_bilik_locks($pdo);

    $stmt = $pdo->prepare('SELECT * FROM bilik_suaras WHERE nomor_bilik = :nomor_bilik LIMIT 1');
    $stmt->execute([':nomor_bilik' => $nomor_bilik]);
    $bilik = $stmt->fetch();

    if (!$bilik) {
        return null;
    }

    if ($bilik['status_bilik'] === 'IDLE') {
        $generated_ts = strtotime($bilik['token_generated_at']);
        $age_seconds = max(0, time() - $generated_ts);

        if ($force_rotate || $age_seconds >= $ttl_seconds) {
            $new_token = generate_secure_token(20);
            $prev_token = $bilik['current_token'];

            $stmt_rot = $pdo->prepare('
                UPDATE bilik_suaras
                SET previous_token = :prev_token,
                    current_token = :new_token,
                    token_generated_at = NOW()
                WHERE id = :id AND status_bilik = "IDLE"
            ');
            $stmt_rot->execute([
                ':prev_token' => $prev_token,
                ':new_token'  => $new_token,
                ':id'         => (int) $bilik['id'],
            ]);

            $bilik['previous_token'] = $prev_token;
            $bilik['current_token'] = $new_token;
            $bilik['token_generated_at'] = date('Y-m-d H:i:s');
            $age_seconds = 0;
        }

        $bilik['remaining_qr_seconds'] = max(1, $ttl_seconds - $age_seconds);
        $bilik['remaining_lock_seconds'] = 0;
    } elseif ($bilik['status_bilik'] === 'IN_USE') {
        $locked_until_ts = !empty($bilik['locked_until']) ? strtotime($bilik['locked_until']) : time();
        $bilik['remaining_qr_seconds'] = 0;
        $bilik['remaining_lock_seconds'] = max(0, $locked_until_ts - time());
    } else {
        $bilik['remaining_qr_seconds'] = 0;
        $bilik['remaining_lock_seconds'] = 0;
    }

    return $bilik;
}

