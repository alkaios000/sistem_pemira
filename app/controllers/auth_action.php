<?php
/**
 * Controller Autentikasi Pengguna (Login & Logout Action)
 */

require_once BASE_PATH . '/models/user_model.php';

$pdo = get_db_connection();
$sub_action = $_POST['sub_action'] ?? $_GET['sub_action'] ?? 'login';

if ($sub_action === 'logout') {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
    session_start();
    set_flash('success', 'Anda telah berhasil keluar dari sesi panel administrasi.');
    redirect(route_url('login'));
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(route_url('login'));
}

verify_csrf_request();

$username = trim((string) ($_POST['username'] ?? ''));
$password = (string) ($_POST['password'] ?? '');

if ($username === '' || $password === '') {
    set_flash('danger', 'Username dan kata sandi wajib diisi.');
    redirect(route_url('login'));
}

$user = find_user_by_username($pdo, $username);

if (!$user || !password_verify($password, $user['password_hash'])) {
    set_flash('danger', 'Kombinasi username atau kata sandi tidak sesuai.');
    redirect(route_url('login'));
}

session_regenerate_id(true);
$_SESSION['user_id'] = (int) $user['id'];
$_SESSION['username'] = $user['username'];
$_SESSION['nama_lengkap'] = $user['nama_lengkap'];
$_SESSION['user_role'] = $user['role'];

set_flash('success', 'Selamat datang kembali, ' . $user['nama_lengkap'] . '.');
redirect(route_url('dashboard'));

