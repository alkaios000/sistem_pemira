<?php
/**
 * Middleware Otorisasi Berbasis Peran (Role-Based Access Control)
 */

/**
 * Memastikan peran pengguna sesuai dengan daftar peran yang diizinkan
 */
function require_role(array $allowed_roles, bool $is_ajax = false): void
{
    require_auth($is_ajax);

    $current_role = $_SESSION['user_role'] ?? '';
    if (!in_array($current_role, $allowed_roles, true)) {
        if ($is_ajax) {
            json_response(false, 'Anda tidak memiliki hak akses untuk tindakan ini.', [], 403);
        }
        http_response_code(403);
        require BASE_PATH . '/views/errors/403.php';
        exit;
    }
}

