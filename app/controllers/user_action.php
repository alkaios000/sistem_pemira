<?php
/**
 * Controller Manajemen Akun Panel & Profil Keamanan (Admin RBAC User Management Action)
 * - change_own_password: dapat diakses oleh seluruh akun yang login (superadmin, kprm, banwasra)
 * - create, update, reset_password, toggle_active, delete: eksklusif hanya superadmin
 */

require_once BASE_PATH . '/models/user_model.php';
require_once BASE_PATH . '/models/log_model.php';

$is_ajax = is_ajax_request();
require_auth($is_ajax);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(route_url('dashboard'));
}

verify_csrf_request($is_ajax);

$pdo = get_db_connection();
$sub_action = trim((string) ($_POST['sub_action'] ?? ''));

$respond = static function (bool $ok, string $msg, string $redirect_page = 'manajemen_user', int $http_code = 200) use ($is_ajax): void {
    if ($is_ajax) {
        json_response($ok, $msg, [], $http_code);
    }
    set_flash($ok ? 'success' : 'error', $msg);
    redirect(route_url($redirect_page));
};

// 1. Ganti Password Akun Sendiri (semua peran terautentikasi)
if ($sub_action === 'change_own_password') {
    $current_password = (string) ($_POST['current_password'] ?? '');
    $new_password = (string) ($_POST['new_password'] ?? '');
    $confirm_password = (string) ($_POST['confirm_password'] ?? '');
    $user_id = (int) ($_SESSION['user_id'] ?? 0);

    if ($current_password === '' || $new_password === '' || $confirm_password === '') {
        $respond(false, 'Semua kolom password wajib diisi.', 'profil_akun', 400);
    }
    if (strlen($new_password) < 8 || strlen($new_password) > 128) {
        $respond(false, 'Password baru harus memiliki panjang 8 hingga 128 karakter.', 'profil_akun', 400);
    }
    if (!hash_equals($new_password, $confirm_password)) {
        $respond(false, 'Konfirmasi password baru tidak cocok.', 'profil_akun', 400);
    }
    if (hash_equals($current_password, $new_password)) {
        $respond(false, 'Password baru harus berbeda dari password saat ini.', 'profil_akun', 400);
    }

    $current_hash = find_user_password_hash_by_id($pdo, $user_id);
    if (!$current_hash || !password_verify($current_password, $current_hash)) {
        record_admin_audit_log(
            $pdo,
            'USER_OWN_PASSWORD_FAILED',
            'Gagal Ganti Password Sendiri',
            "Pengguna {$_SESSION['username']} memasukkan password lama yang salah saat mengganti password.",
            'FRAUD'
        );
        $respond(false, 'Password saat ini tidak sesuai.', 'profil_akun', 403);
    }

    update_user_password($pdo, $user_id, $new_password);
    session_regenerate_id(true);
    $_SESSION['last_activity_at'] = time();

    record_admin_audit_log(
        $pdo,
        'USER_OWN_PASSWORD_CHANGED',
        'Perubahan Password Akun Sendiri',
        "Pengguna {$_SESSION['username']} (" . current_user_role() . ') berhasil memperbarui password akunnya sendiri.'
    );

    $respond(true, 'Password akun Anda berhasil diperbarui.', 'profil_akun');
}

// 2. Seluruh manajemen akun & hak akses lainnya EKSKLUSIF hanya untuk Superadmin
require_role(['superadmin'], $is_ajax);

$allowed_roles = ['superadmin', 'kprm', 'banwasra'];
$current_uid = (int) ($_SESSION['user_id'] ?? 0);

switch ($sub_action) {
    case 'create':
        $username = trim((string) ($_POST['username'] ?? ''));
        $nama_lengkap = trim((string) ($_POST['nama_lengkap'] ?? ''));
        $role = trim((string) ($_POST['role'] ?? 'kprm'));
        $password = (string) ($_POST['password'] ?? '');
        $is_active = isset($_POST['is_active']) && (string) $_POST['is_active'] === '0' ? 0 : 1;

        if (!preg_match('/^[a-zA-Z0-9._-]{3,50}$/', $username)) {
            $respond(false, 'Username harus 3-50 karakter (huruf, angka, titik, garis bawah, atau strip).', 'manajemen_user', 400);
        }
        if ($nama_lengkap === '' || mb_strlen($nama_lengkap) > 120) {
            $respond(false, 'Nama lengkap wajib diisi (maksimal 120 karakter).', 'manajemen_user', 400);
        }
        if (!in_array($role, $allowed_roles, true)) {
            $respond(false, 'Peran (role) pengguna tidak valid.', 'manajemen_user', 400);
        }
        if (strlen($password) < 8 || strlen($password) > 128) {
            $respond(false, 'Password awal minimal 8 karakter dan maksimal 128 karakter.', 'manajemen_user', 400);
        }
        if (find_user_by_username($pdo, $username)) {
            $respond(false, "Username '{$username}' sudah digunakan oleh akun lain.", 'manajemen_user', 409);
        }

        $new_id = create_user_account($pdo, $username, $nama_lengkap, $password, $role, $is_active);
        record_admin_audit_log(
            $pdo,
            'USER_CREATE',
            'Penambahan Akun Panel Admin',
            "Superadmin membuat akun baru #{$new_id} ({$username} / {$nama_lengkap}) dengan hak akses '{$role}'."
        );

        $respond(true, "Akun {$username} ({$role}) berhasil ditambahkan.");
        break;

    case 'update':
        $id = (int) ($_POST['id'] ?? 0);
        $username = trim((string) ($_POST['username'] ?? ''));
        $nama_lengkap = trim((string) ($_POST['nama_lengkap'] ?? ''));
        $role = trim((string) ($_POST['role'] ?? ''));
        $is_active = isset($_POST['is_active']) && (string) $_POST['is_active'] === '0' ? 0 : 1;

        $existing = $id > 0 ? find_user_by_id($pdo, $id) : null;
        if (!$existing) {
            $respond(false, 'Akun pengguna tidak ditemukan.', 'manajemen_user', 404);
        }
        if (!preg_match('/^[a-zA-Z0-9._-]{3,50}$/', $username)) {
            $respond(false, 'Username harus 3-50 karakter (huruf, angka, titik, garis bawah, atau strip).', 'manajemen_user', 400);
        }
        if ($nama_lengkap === '' || mb_strlen($nama_lengkap) > 120) {
            $respond(false, 'Nama lengkap wajib diisi (maksimal 120 karakter).', 'manajemen_user', 400);
        }
        if (!in_array($role, $allowed_roles, true)) {
            $respond(false, 'Peran (role) pengguna tidak valid.', 'manajemen_user', 400);
        }

        $dup = find_user_by_username($pdo, $username);
        if ($dup && (int) $dup['id'] !== $id) {
            $respond(false, "Username '{$username}' sudah digunakan oleh akun lain.", 'manajemen_user', 409);
        }

        if ($id === $current_uid && ($is_active === 0 || $role !== 'superadmin')) {
            $respond(false, 'Anda tidak dapat menonaktifkan atau menurunkan hak akses akun Anda sendiri.', 'manajemen_user', 400);
        }

        if ($existing['role'] === 'superadmin' && ($role !== 'superadmin' || $is_active === 0)) {
            if (count_active_superadmins($pdo, $id) < 1) {
                $respond(false, 'Sistem harus memiliki minimal satu akun Superadmin yang aktif.', 'manajemen_user', 400);
            }
        }

        update_user_account($pdo, $id, $username, $nama_lengkap, $role, $is_active);
        record_admin_audit_log(
            $pdo,
            'USER_UPDATE',
            'Perubahan Data / Hak Akses Akun',
            "Superadmin memperbarui akun #{$id} ({$username}): role '{$existing['role']}' -> '{$role}', aktif=" . ($is_active ? 'YA' : 'NONAKTIF') . '.'
        );

        $respond(true, "Data akun {$username} berhasil diperbarui.");
        break;

    case 'reset_password':
        $id = (int) ($_POST['id'] ?? 0);
        $new_password = (string) ($_POST['new_password'] ?? '');
        $existing = $id > 0 ? find_user_by_id($pdo, $id) : null;
        if (!$existing) {
            $respond(false, 'Akun pengguna tidak ditemukan.', 'manajemen_user', 404);
        }
        if (strlen($new_password) < 8 || strlen($new_password) > 128) {
            $respond(false, 'Password baru minimal 8 karakter dan maksimal 128 karakter.', 'manajemen_user', 400);
        }

        update_user_password($pdo, $id, $new_password);
        record_admin_audit_log(
            $pdo,
            'USER_RESET_PASSWORD',
            'Reset Password Akun Panel',
            "Superadmin mereset password untuk akun #{$id} ({$existing['username']} / {$existing['role']})."
        );

        $respond(true, "Password untuk akun {$existing['username']} berhasil direset.");
        break;

    case 'toggle_active':
        $id = (int) ($_POST['id'] ?? 0);
        $existing = $id > 0 ? find_user_by_id($pdo, $id) : null;
        if (!$existing) {
            $respond(false, 'Akun pengguna tidak ditemukan.', 'manajemen_user', 404);
        }
        if ($id === $current_uid) {
            $respond(false, 'Anda tidak dapat menonaktifkan akun yang sedang Anda gunakan.', 'manajemen_user', 400);
        }

        $next_active = ((int) $existing['is_active'] === 1) ? 0 : 1;
        if ($existing['role'] === 'superadmin' && $next_active === 0 && count_active_superadmins($pdo, $id) < 1) {
            $respond(false, 'Sistem harus memiliki minimal satu akun Superadmin yang aktif.', 'manajemen_user', 400);
        }

        update_user_account($pdo, $id, $existing['username'], $existing['nama_lengkap'], $existing['role'], $next_active);
        record_admin_audit_log(
            $pdo,
            'USER_TOGGLE_ACTIVE',
            'Perubahan Status Aktif Akun',
            "Superadmin mengubah status akun #{$id} ({$existing['username']}) menjadi " . ($next_active === 1 ? 'AKTIF' : 'NONAKTIF') . '.'
        );

        $respond(true, "Status akun {$existing['username']} berhasil diubah menjadi " . ($next_active === 1 ? 'AKTIF' : 'NONAKTIF') . '.');
        break;

    case 'delete':
        $id = (int) ($_POST['id'] ?? 0);
        $existing = $id > 0 ? find_user_by_id($pdo, $id) : null;
        if (!$existing) {
            $respond(false, 'Akun pengguna tidak ditemukan.', 'manajemen_user', 404);
        }
        if ($id === $current_uid) {
            $respond(false, 'Anda tidak dapat menghapus akun yang sedang aktif Anda gunakan.', 'manajemen_user', 400);
        }
        if ($existing['role'] === 'superadmin' && (int) $existing['is_active'] === 1 && count_active_superadmins($pdo, $id) < 1) {
            $respond(false, 'Tidak dapat menghapus satu-satunya akun Superadmin yang aktif.', 'manajemen_user', 400);
        }

        delete_user_account($pdo, $id);
        record_admin_audit_log(
            $pdo,
            'USER_DELETE',
            'Penghapusan Akun Panel Admin',
            "Superadmin menghapus akun #{$id} ({$existing['username']} / {$existing['role']})."
        );

        $respond(true, "Akun {$existing['username']} berhasil dihapus.");
        break;

    default:
        $respond(false, 'Sub-action manajemen akun tidak dikenali.', 'manajemen_user', 400);
}
