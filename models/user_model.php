<?php
/**
 * Model Pengguna & Pengaturan Sistem (User & Settings Model)
 * Seluruh interaksi basis data menggunakan PDO Prepared Statements.
 */

/**
 * Mencari pengguna berdasarkan username untuk keperluan autentikasi login
 */
function find_user_by_username(PDO $pdo, string $username): ?array
{
    $stmt = $pdo->prepare('
        SELECT id, username, nama_lengkap, password_hash, role, created_at
        FROM users
        WHERE username = :username
        LIMIT 1
    ');
    $stmt->execute([':username' => $username]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/**
 * Mencari pengguna berdasarkan ID
 */
function find_user_by_id(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare('
        SELECT id, username, nama_lengkap, role, created_at
        FROM users
        WHERE id = :id
        LIMIT 1
    ');
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/**
 * Mengambil seluruh pengaturan sistem dalam bentuk array asosiatif [kunci => nilai]
 */
function get_all_settings(PDO $pdo): array
{
    $stmt = $pdo->query('SELECT kunci, nilai FROM pengaturan_sistem');
    $settings = [];
    foreach ($stmt->fetchAll() as $row) {
        $settings[$row['kunci']] = $row['nilai'];
    }
    return $settings;
}

/**
 * Mengambil satu nilai pengaturan berdasarkan kuncinya
 */
function get_setting_value(PDO $pdo, string $kunci, string $default = ''): string
{
    $stmt = $pdo->prepare('SELECT nilai FROM pengaturan_sistem WHERE kunci = :kunci LIMIT 1');
    $stmt->execute([':kunci' => $kunci]);
    $val = $stmt->fetchColumn();
    return ($val !== false && $val !== null) ? (string) $val : $default;
}

/**
 * Memperbarui atau menyimpan nilai pengaturan sistem
 */
function update_setting_value(PDO $pdo, string $kunci, string $nilai, ?string $keterangan = null): bool
{
    $stmt = $pdo->prepare('
        INSERT INTO pengaturan_sistem (kunci, nilai, keterangan)
        VALUES (:kunci, :nilai, :keterangan)
        ON DUPLICATE KEY UPDATE nilai = VALUES(nilai)
    ');
    return $stmt->execute([
        ':kunci'      => $kunci,
        ':nilai'      => $nilai,
        ':keterangan' => $keterangan,
    ]);
}

