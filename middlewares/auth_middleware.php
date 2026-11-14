<?php
/**
 * Middleware Autentikasi Sesi Pengguna
 * Memastikan sesi aktif valid, belum kadaluarsa (idle/absolute timeout),
 * dan pengguna masih berstatus aktif di basis data.
 */

require_once BASE_PATH . '/models/user_model.php';

/**
 * Menghapus sesi autentikasi admin/panitia tanpa mengganggu sesi voter lain yang terpisah
 */
function clear_admin_session_state(): void
{
    unset(
        $_SESSION['user_id'],
        $_SESSION['username'],
        $_SESSION['nama_lengkap'],
        $_SESSION['user_role'],
        $_SESSION['login_at'],
        $_SESSION['last_activity_at']
    );
}

/**
 * Memeriksa apakah pengguna panel saat ini sudah terautentikasi secara sah
 */
function is_authenticated(): bool
{
    static $cachedResult = null;
    if ($cachedResult !== null) {
        return $cachedResult;
    }

    if (empty($_SESSION['user_id']) || empty($_SESSION['user_role'])) {
        $cachedResult = false;
        return false;
    }

    $now = time();
    $idleTimeout = max(60, (int) env_val('SESSION_IDLE_TIMEOUT_SECONDS', 1800));
    $absoluteTimeout = max(300, (int) env_val('SESSION_ABSOLUTE_TIMEOUT_SECONDS', 28800));

    $lastActivity = (int) ($_SESSION['last_activity_at'] ?? 0);
    $loginAt = (int) ($_SESSION['login_at'] ?? 0);

    if ($lastActivity > 0 && ($now - $lastActivity) > $idleTimeout) {
        clear_admin_session_state();
        $cachedResult = false;
        return false;
    }

    if ($loginAt > 0 && ($now - $loginAt) > $absoluteTimeout) {
        clear_admin_session_state();
        $cachedResult = false;
        return false;
    }

    // Re-validasi status aktif & peran terbaru dari basis data
    $pdo = get_db_connection();
    $user = find_user_by_id($pdo, (int) $_SESSION['user_id']);
    if (!$user || (int) ($user['is_active'] ?? 1) !== 1) {
        clear_admin_session_state();
        $cachedResult = false;
        return false;
    }

    $_SESSION['username'] = $user['username'];
    $_SESSION['nama_lengkap'] = $user['nama_lengkap'];
    $_SESSION['user_role'] = $user['role'];
    $_SESSION['last_activity_at'] = $now;

    $cachedResult = true;
    return true;
}

/**
 * Memaksa autentikasi pada rute panel; mengalihkan ke halaman login jika belum masuk / sesi kedaluwarsa
 */
function require_auth(bool $is_ajax = false): void
{
    if (!is_authenticated()) {
        if ($is_ajax) {
            json_response(false, 'Sesi Anda telah berakhir. Silakan login kembali.', [], 401);
        }
        set_flash('warning', 'Silakan login terlebih dahulu atau sesi Anda telah berakhir.');
        redirect(route_url('login'));
    }
}

/**
 * Mengalihkan pengguna yang sudah login agar tidak kembali ke halaman login
 */
function redirect_if_authenticated(): void
{
    if (is_authenticated()) {
        redirect(route_url('dashboard'));
    }
}
