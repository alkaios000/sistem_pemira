<?php
/**
 * Pustaka Fungsi Keamanan (Security Helper)
 * Menyediakan sanitasi XSS, manajemen token CSRF, pesan flash, dan respons JSON standar.
 */

/**
 * Sanitasi output string terhadap serangan XSS
 */
function e(mixed $value): string
{
    if ($value === null) {
        return '';
    }
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Menghasilkan atau mengambil token CSRF aktif pada sesi pengguna
 */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Menghasilkan elemen input hidden HTML untuk form CSRF
 */
function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

/**
 * Memvalidasi kecocokan token CSRF dari request pengguna
 */
function verify_csrf_token(?string $token): bool
{
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Menghasilkan token kriptografis unik untuk QR Code dan sesi bilik
 */
function generate_secure_token(int $bytes = 24): string
{
    $raw = bin2hex(random_bytes($bytes));
    $secret = env_val('APP_KEY', 'pemira_default_secret');
    $signature = substr(hash_hmac('sha256', $raw, $secret), 0, 16);
    return $raw . $signature;
}

/**
 * Menyimpan pesan notifikasi sementara (flash message) ke dalam session
 */
function set_flash(string $type, string $message): void
{
    $_SESSION['flash_message'] = [
        'type'    => $type,
        'message' => $message,
    ];
}

/**
 * Mengambil sekaligus menghapus flash message dari session
 */
function get_flash(): ?array
{
    if (!isset($_SESSION['flash_message'])) {
        return null;
    }
    $flash = $_SESSION['flash_message'];
    unset($_SESSION['flash_message']);
    return $flash;
}

/**
 * Mengambil alamat IP klien secara akurat
 */
function get_client_ip(): string
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
}

/**
 * Mengambil User-Agent browser klien (dibatasi panjangnya untuk keamanan kolom DB)
 */
function get_client_user_agent(): string
{
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
    return mb_substr(trim($ua), 0, 255);
}

/**
 * Mengirimkan respons berformat JSON seragam dan menghentikan eksekusi
 */
function json_response(bool $status, string $message, array $data = [], int $http_code = 200): never
{
    http_response_code($http_code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    echo json_encode([
        'status'    => $status ? 'success' : 'error',
        'success'   => $status,
        'message'   => $message,
        'data'      => $data,
        'timestamp' => date('Y-m-d H:i:s'),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

