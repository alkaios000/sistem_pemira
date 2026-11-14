<?php
/**
 * SINGLE ENTRY POINT (Front Controller Tunggal Sistem PEMIRA Kampus)
 * Memuat bootstrap konfigurasi, helper, middleware, serta memetakan rute
 * action (controller) dan page (view) melalui mekanisme whitelist yang aman.
 */

define('BASE_PATH', __DIR__);

require_once BASE_PATH . '/config/config.php';
require_once BASE_PATH . '/helpers/security_helper.php';
require_once BASE_PATH . '/helpers/url_helper.php';
require_once BASE_PATH . '/helpers/date_helper.php';
require_once BASE_PATH . '/helpers/upload_helper.php';
require_once BASE_PATH . '/middlewares/auth_middleware.php';
require_once BASE_PATH . '/middlewares/role_middleware.php';
require_once BASE_PATH . '/middlewares/csrf_middleware.php';

// 1. Whitelist Rute Controller Penangan Aksi (Action Layer)
$allowed_actions = [
    'auth'       => BASE_PATH . '/app/controllers/auth_action.php',
    'kandidat'   => BASE_PATH . '/app/controllers/kandidat_action.php',
    'bilik'      => BASE_PATH . '/app/controllers/bilik_action.php',
    'mahasiswa'  => BASE_PATH . '/app/controllers/mahasiswa_action.php',
    'jurusan'    => BASE_PATH . '/app/controllers/jurusan_action.php',
    'voting'     => BASE_PATH . '/app/controllers/voting_action.php',
    'log'        => BASE_PATH . '/app/controllers/log_action.php',
    'live_count' => BASE_PATH . '/app/controllers/live_count_action.php',
    'user'       => BASE_PATH . '/app/controllers/user_action.php',
];

if (isset($_GET['action']) && $_GET['action'] !== '') {
    $action_key = preg_replace('/[^a-z0-9_]/', '', strtolower((string) $_GET['action']));
    if (isset($allowed_actions[$action_key]) && file_exists($allowed_actions[$action_key])) {
        require $allowed_actions[$action_key];
        exit;
    }

    http_response_code(404);
    require BASE_PATH . '/views/errors/404.php';
    exit;
}

// 2. Whitelist Rute Halaman Tampilan (Presentation Layer)
$allowed_pages = [
    'login'               => BASE_PATH . '/views/pages/login.php',
    'logout'              => BASE_PATH . '/views/pages/logout.php',
    'dashboard'           => BASE_PATH . '/views/pages/dashboard.php',
    'manajemen_kandidat'  => BASE_PATH . '/views/pages/manajemen_kandidat.php',
    'manajemen_bilik'     => BASE_PATH . '/views/pages/portal_qr.php',
    'manajemen_mahasiswa' => BASE_PATH . '/views/pages/manajemen_mahasiswa.php',
    'manajemen_jurusan'   => BASE_PATH . '/views/pages/manajemen_jurusan.php',
    'manajemen_user'      => BASE_PATH . '/views/pages/manajemen_user.php',
    'profil_akun'         => BASE_PATH . '/views/pages/profil_akun.php',
    'log_keamanan'        => BASE_PATH . '/views/pages/log_keamanan.php',
    'portal_qr'           => BASE_PATH . '/views/pages/portal_qr.php',
    'bilik_pemilihan'     => BASE_PATH . '/views/pages/bilik_pemilihan.php',
    'live_count_bem'      => BASE_PATH . '/views/pages/live_count_bem.php',
    'live_count_bpm'      => BASE_PATH . '/views/pages/live_count_bpm.php',
];

$default_page = is_authenticated() ? 'dashboard' : 'login';
$requested_page = $_GET['page'] ?? $default_page;
$page_key = preg_replace('/[^a-z0-9_]/', '', strtolower((string) $requested_page));

if (isset($allowed_pages[$page_key]) && file_exists($allowed_pages[$page_key])) {
    require $allowed_pages[$page_key];
    exit;
}

http_response_code(404);
require BASE_PATH . '/views/errors/404.php';
exit;

