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
            b.current_token, b.previous_token, b.token_generated_at,
            b.active_session_token, b.active_device_id, b.active_nim,
            b.locked_until, b.last_vote_at, b.total_suara_bilik, b.created_at
        ORDER BY b.nomor_bilik ASC
    ');
    return $stmt->fetchAll();
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
        INSERT INTO bilik_suaras (nomor_bilik, nama_bilik, lokasi_bilik, status_bilik, current_token, token_generated_at)
        VALUES (:nomor_bilik, :nama_bilik, :lokasi_bilik, "IDLE", :current_token, NOW())
    ');
    $stmt->execute([
        ':nomor_bilik'   => $nomor_bilik,
        ':nama_bilik'    => $nama_bilik,
        ':lokasi_bilik'  => $lokasi_bilik !== '' ? $lokasi_bilik : null,
        ':current_token' => generate_secure_token(20),
    ]);
    return (int) $pdo->lastInsertId();
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

