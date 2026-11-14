<?php
/**
 * Middleware Validasi Token Anti-CSRF
 * Mencegah serangan Cross-Site Request Forgery pada seluruh request mutasi data (POST).
 */

/**
 * Memvalidasi token CSRF pada request POST (baik form maupun JSON/AJAX)
 */
function verify_csrf_request(bool $is_ajax = false): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return;
    }

    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;

    if (!verify_csrf_token($token)) {
        if ($is_ajax) {
            json_response(false, 'Token keamanan CSRF tidak valid atau kadaluarsa. Silakan muat ulang halaman.', [], 419);
        }
        set_flash('danger', 'Sesi formulir telah kadaluarsa (CSRF Invalid). Silakan ulangi tindakan Anda.');
        $referer = $_SERVER['HTTP_REFERER'] ?? '';
        $fallback = is_authenticated() ? route_url('dashboard') : route_url('login');
        redirect(is_safe_internal_url($referer) ? $referer : $fallback);
    }
}
