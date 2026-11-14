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
        SELECT id, username, nama_lengkap, password_hash, role, is_active, last_login_at, created_at
        FROM users
        WHERE username = :username
        LIMIT 1
    ');
    $stmt->execute([':username' => $username]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/**
 * Mencari pengguna berdasarkan ID (tanpa password_hash)
 */
function find_user_by_id(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare('
        SELECT id, username, nama_lengkap, role, is_active, last_login_at, created_at
        FROM users
        WHERE id = :id
        LIMIT 1
    ');
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/**
 * Mengambil password hash pengguna berdasarkan ID (hanya untuk verifikasi ganti password)
 */
function find_user_password_hash_by_id(PDO $pdo, int $id): ?string
{
    $stmt = $pdo->prepare('SELECT password_hash FROM users WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $id]);
    $val = $stmt->fetchColumn();
    return ($val !== false && $val !== null) ? (string) $val : null;
}

/**
 * Mengambil seluruh daftar akun pengguna untuk Manajemen Akun Superadmin
 */
function get_all_users(PDO $pdo): array
{
    $stmt = $pdo->query('
        SELECT id, username, nama_lengkap, role, is_active, last_login_at, created_at, updated_at
        FROM users
        ORDER BY FIELD(role, "superadmin", "kprm", "banwasra"), username ASC
    ');
    return $stmt->fetchAll();
}

/**
 * Menghitung jumlah akun superadmin yang aktif
 */
function count_active_superadmins(PDO $pdo, ?int $exclude_user_id = null): int
{
    if ($exclude_user_id !== null && $exclude_user_id > 0) {
        $stmt = $pdo->prepare('
            SELECT COUNT(*) FROM users
            WHERE role = "superadmin" AND is_active = 1 AND id <> :id
        ');
        $stmt->execute([':id' => $exclude_user_id]);
    } else {
        $stmt = $pdo->query('SELECT COUNT(*) FROM users WHERE role = "superadmin" AND is_active = 1');
    }
    return (int) $stmt->fetchColumn();
}

/**
 * Menambahkan akun pengguna baru
 */
function create_user_account(
    PDO $pdo,
    string $username,
    string $nama_lengkap,
    string $plain_password,
    string $role,
    int $is_active = 1
): int {
    $allowed_roles = ['superadmin', 'kprm', 'banwasra'];
    if (!in_array($role, $allowed_roles, true)) {
        throw new InvalidArgumentException('Peran pengguna tidak valid.');
    }

    $stmt = $pdo->prepare('
        INSERT INTO users (username, nama_lengkap, password_hash, role, is_active)
        VALUES (:username, :nama_lengkap, :password_hash, :role, :is_active)
    ');
    $stmt->execute([
        ':username'      => $username,
        ':nama_lengkap'  => $nama_lengkap,
        ':password_hash' => password_hash($plain_password, PASSWORD_BCRYPT),
        ':role'          => $role,
        ':is_active'     => $is_active ? 1 : 0,
    ]);
    return (int) $pdo->lastInsertId();
}

/**
 * Memperbarui informasi profil, peran, dan status aktif akun
 */
function update_user_account(
    PDO $pdo,
    int $id,
    string $username,
    string $nama_lengkap,
    string $role,
    int $is_active
): bool {
    $allowed_roles = ['superadmin', 'kprm', 'banwasra'];
    if (!in_array($role, $allowed_roles, true)) {
        return false;
    }

    $stmt = $pdo->prepare('
        UPDATE users
        SET username = :username,
            nama_lengkap = :nama_lengkap,
            role = :role,
            is_active = :is_active
        WHERE id = :id
    ');
    return $stmt->execute([
        ':username'     => $username,
        ':nama_lengkap' => $nama_lengkap,
        ':role'         => $role,
        ':is_active'    => $is_active ? 1 : 0,
        ':id'           => $id,
    ]);
}

/**
 * Memperbarui kata sandi pengguna dengan bcrypt
 */
function update_user_password(PDO $pdo, int $id, string $new_plain_password): bool
{
    $stmt = $pdo->prepare('UPDATE users SET password_hash = :password_hash WHERE id = :id');
    return $stmt->execute([
        ':password_hash' => password_hash($new_plain_password, PASSWORD_BCRYPT),
        ':id'            => $id,
    ]);
}

/**
 * Menghapus akun pengguna berdasarkan ID
 */
function delete_user_account(PDO $pdo, int $id): bool
{
    $stmt = $pdo->prepare('DELETE FROM users WHERE id = :id');
    return $stmt->execute([':id' => $id]);
}

/**
 * Mencatat waktu login terakhir pengguna
 */
function update_user_last_login(PDO $pdo, int $id): void
{
    $stmt = $pdo->prepare('UPDATE users SET last_login_at = NOW() WHERE id = :id');
    $stmt->execute([':id' => $id]);
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
