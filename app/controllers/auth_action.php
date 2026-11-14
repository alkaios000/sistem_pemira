<?php
/**
 * Controller Autentikasi Panel Admin / KPRM / Banwasra (Login & Logout)
 * - Logout wajib menggunakan POST + token CSRF (mencegah Logout CSRF via GET/tag <img>).
 * - Login dilindungi rate limit berbasis database dan tercatat ke audit_logs.
 */

require_once BASE_PATH . '/models/user_model.php';
require_once BASE_PATH . '/models/log_model.php';

$pdo = get_db_connection();
$sub_action = $_POST['sub_action'] ?? $_GET['sub_action'] ?? 'login';
$is_ajax = is_ajax_request();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    if ($sub_action === 'logout') {
        redirect(is_authenticated() ? route_url('logout') : route_url('login'));
    }
    redirect(route_url('login'));
}

verify_csrf_request($is_ajax);

if ($sub_action === 'logout') {
    if (is_authenticated()) {
        $actor_override = [
            'user_id'  => (int) ($_SESSION['user_id'] ?? 0),
            'username' => (string) ($_SESSION['username'] ?? 'unknown'),
            'role'     => (string) ($_SESSION['user_role'] ?? 'kprm'),
        ];
        record_admin_audit_log(
            $pdo,
            'ADMIN_LOGOUT',
            'Logout Panel Sistem PEMIRA',
            "Pengguna {$actor_override['username']} ({$actor_override['role']}) keluar dari sesi panel administrasi.",
            'NORMAL',
            null,
            null,
            $actor_override
        );
    }

    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires'  => time() - 42000,
            'path'     => $params['path'] ?? '/',
            'domain'   => $params['domain'] ?? '',
            'secure'   => is_https_request(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    session_destroy();
    session_start();
    session_regenerate_id(true);
    set_flash('success', 'Anda telah berhasil keluar dari sesi panel administrasi.');
    redirect(route_url('login'));
}

$username = trim((string) ($_POST['username'] ?? ''));
$password = (string) ($_POST['password'] ?? '');

$login_error = static function (string $message, string $code = 'INVALID_CREDENTIALS', int $http_code = 401) use ($is_ajax): never {
    if ($is_ajax) {
        json_response(false, $message, [
            'code'       => $code,
            'csrf_token' => csrf_token(),
        ], $http_code);
    }
    set_flash('error', $message);
    redirect(route_url('login'));
};

if ($username === '' || $password === '') {
    $login_error('Incorrect password', 'EMPTY_CREDENTIALS', 400);
}

$ip = get_client_ip();
$user_key = strtolower($username) . '|' . $ip;

$ip_limit = check_rate_limit($pdo, 'admin_login_ip', $ip, 300);
$user_limit = check_rate_limit($pdo, 'admin_login_user', $user_key, 300);

if ($ip_limit['blocked'] || $user_limit['blocked']) {
    $wait = max($ip_limit['remaining_seconds'], $user_limit['remaining_seconds'], 60);
    $rateTargetUser = find_user_by_username($pdo, $username);
    $rateTargetRole = ($rateTargetUser && ($rateTargetUser['role'] ?? '') === 'superadmin') ? 'superadmin' : 'guest';
    record_admin_audit_log(
        $pdo,
        'ADMIN_LOGIN_RATE_LIMITED',
        'Pemblokiran Brute-Force Login Panel',
        "Percobaan login untuk username '{$username}' dari IP {$ip} diblokir sementara selama {$wait} detik.",
        'FRAUD',
        null,
        null,
        ['user_id' => null, 'username' => $username, 'role' => $rateTargetRole]
    );
    $login_error("Terlalu banyak percobaan login gagal. Silakan tunggu {$wait} detik sebelum mencoba kembali.", 'RATE_LIMITED', 429);
}

$user = find_user_by_username($pdo, $username);
$dummy_hash = '$2y$10$usesomesillystringfore7hnbRJHxXVLeakoG8K30oukPsA.ztMG';
$password_ok = password_verify($password, $user ? (string) $user['password_hash'] : $dummy_hash);

if (!$user || (int) ($user['is_active'] ?? 1) !== 1 || !$password_ok) {
    record_rate_limit_hit($pdo, 'admin_login_user', $user_key, 5, 300, 300);
    record_rate_limit_hit($pdo, 'admin_login_ip', $ip, 15, 300, 300);

    $reason = ($user && (int) ($user['is_active'] ?? 1) !== 1) ? 'akun berstatus nonaktif' : 'kredensial tidak cocok';
    $failedRole = ($user && ($user['role'] ?? '') === 'superadmin') ? 'superadmin' : 'guest';
    record_admin_audit_log(
        $pdo,
        'ADMIN_LOGIN_FAILED',
        'Percobaan Login Panel Gagal',
        "Percobaan login gagal untuk username '{$username}' dari IP {$ip} ({$reason}).",
        'FRAUD',
        null,
        null,
        ['user_id' => null, 'username' => $username, 'role' => $failedRole]
    );

    $login_error('Incorrect password', 'INVALID_CREDENTIALS', 401);
}

clear_rate_limit($pdo, 'admin_login_user', $user_key);

// Regenerasi Session ID untuk mencegah Session Fixation
session_regenerate_id(true);
$now = time();
$_SESSION['user_id'] = (int) $user['id'];
$_SESSION['username'] = $user['username'];
$_SESSION['nama_lengkap'] = $user['nama_lengkap'];
$_SESSION['user_role'] = $user['role'];
$_SESSION['login_at'] = $now;
$_SESSION['last_activity_at'] = $now;

update_user_last_login($pdo, (int) $user['id']);

record_admin_audit_log(
    $pdo,
    'ADMIN_LOGIN_SUCCESS',
    'Login Panel Sistem PEMIRA',
    "Pengguna {$user['username']} ({$user['role']}) berhasil masuk dari IP {$ip}."
);

set_flash('success', 'Selamat datang kembali, ' . $user['nama_lengkap'] . '.');
if ($is_ajax) {
    json_response(true, 'Login berhasil.', [
        'redirect_url' => route_url('dashboard'),
    ]);
}
redirect(route_url('dashboard'));
