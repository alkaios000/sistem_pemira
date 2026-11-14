<?php
/**
 * Middleware Otorisasi Berbasis Peran (Role-Based Access Control)
 * Mendukung 3 peran resmi:
 * - superadmin : Akses penuh seluruh sistem, manajemen akun, & seluruh log aktivitas
 * - kprm       : Akses kelola penuh seluruh modul PEMIRA kecuali Log Aktivitas & manajemen akun
 * - banwasra   : Mode pengawas (Viewer/Read-Only) di semua modul + Log Aktivitas (tanpa log superadmin)
 */

/**
 * Mengembalikan peran pengguna aktif saat ini
 */
function current_user_role(): string
{
    return strtolower((string) ($_SESSION['user_role'] ?? ''));
}

/**
 * Memeriksa apakah pengguna aktif memiliki salah satu peran yang diizinkan
 */
function has_role(array $allowed_roles): bool
{
    if (!is_authenticated()) {
        return false;
    }
    return in_array(current_user_role(), $allowed_roles, true);
}

function is_superadmin(): bool
{
    return has_role(['superadmin']);
}

function is_kprm(): bool
{
    return has_role(['kprm']);
}

function is_banwasra(): bool
{
    return has_role(['banwasra']);
}

/**
 * Hak tulis/kelola modul PEMIRA (Kandidat, Bilik, DPT, Jurusan)
 */
function can_manage_pemira(): bool
{
    return has_role(['superadmin', 'kprm']);
}

/**
 * Hak melihat Log Aktivitas / Keamanan (KPRM dilarang, BANWASRA tanpa log superadmin)
 */
function can_view_logs(): bool
{
    return has_role(['superadmin', 'banwasra']);
}

/**
 * Memastikan peran pengguna sesuai dengan daftar peran yang diizinkan
 */
function require_role(array $allowed_roles, bool $is_ajax = false): void
{
    require_auth($is_ajax);

    $current_role = current_user_role();
    if (!in_array($current_role, $allowed_roles, true)) {
        if ($is_ajax) {
            json_response(false, 'Anda tidak memiliki hak akses untuk tindakan atau data ini.', [], 403);
        }
        http_response_code(403);
        $errorMessage = 'Peran akun Anda (' . strtoupper($current_role) . ') tidak memiliki izin untuk mengakses halaman atau melakukan tindakan ini.';
        require BASE_PATH . '/views/errors/403.php';
        exit;
    }
}
