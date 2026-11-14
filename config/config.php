<?php
/**
 * Konfigurasi Global & Inisialisasi Sistem
 * Memuat variabel .env, mengatur timezone, session terproteksi, dan koneksi PDO MySQL.
 */

if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__));
}

/**
 * Fungsi pemuat berkas .env sederhana tanpa ketergantungan eksternal
 */
function load_env_file(string $file_path): void
{
    if (!file_exists($file_path)) {
        return;
    }

    $lines = file($file_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }

        $parts = explode('=', $line, 2);
        if (count($parts) === 2) {
            $key = trim($parts[0]);
            $value = trim($parts[1]);
            $value = trim($value, "\"'");

            $_ENV[$key] = $value;
            putenv("{$key}={$value}");
        }
    }
}

load_env_file(BASE_PATH . '/.env');

/**
 * Mengambil nilai variabel lingkungan dengan nilai fallback
 */
function env_val(string $key, mixed $default = null): mixed
{
    $val = $_ENV[$key] ?? getenv($key);
    if ($val === false || $val === null || $val === '') {
        return $default;
    }
    return $val;
}

/**
 * Mendeteksi apakah request berjalan di atas HTTPS
 */
function is_https_request(): bool
{
    if (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
        return true;
    }
    if ((string) ($_SERVER['SERVER_PORT'] ?? '') === '443') {
        return true;
    }
    if (strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https') {
        return true;
    }
    return false;
}

// Konfigurasi zona waktu lokal Indonesia
date_default_timezone_set('Asia/Jakarta');

// Pengaturan Error Reporting berdasarkan mode APP_DEBUG (default aman: mati)
$is_debug = filter_var(env_val('APP_DEBUG', false), FILTER_VALIDATE_BOOLEAN);
if ($is_debug) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(0);
}

// Kirim HSTS hanya saat HTTPS aktif
if (PHP_SAPI !== 'cli' && is_https_request() && !headers_sent()) {
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
}

// Inisialisasi Session yang aman
if (PHP_SAPI !== 'cli' && session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => is_https_request(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

/**
 * Singleton koneksi database PDO MySQL
 */
function get_db_connection(): PDO
{
    static $pdo = null;

    if ($pdo !== null) {
        return $pdo;
    }

    $host = env_val('DB_HOST', '127.0.0.1');
    $port = env_val('DB_PORT', '3306');
    $dbname = env_val('DB_DATABASE', 'db_sistem_pemira');
    $username = env_val('DB_USERNAME', 'root');
    $password = env_val('DB_PASSWORD', '');
    $isLocalEnv = strtolower((string) env_val('APP_ENV', 'production')) === 'local';

    $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";

    try {
        $pdo = new PDO($dsn, $username, $password, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);

        // Auto-bootstrap hanya diizinkan pada lingkungan local bila tabel belum tersedia
        if ($isLocalEnv) {
            $stmt_check = $pdo->query("SHOW TABLES LIKE 'users'");
            if ($stmt_check->rowCount() === 0) {
                bootstrap_database_schema($pdo);
            }
        }
    } catch (PDOException $exception) {
        if ($isLocalEnv && ((int) $exception->getCode() === 1049 || str_contains($exception->getMessage(), 'Unknown database'))) {
            try {
                $pdo_init = new PDO("mysql:host={$host};port={$port};charset=utf8mb4", $username, $password, [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ]);
                $pdo_init->exec("CREATE DATABASE IF NOT EXISTS `{$dbname}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                $pdo_init->exec("USE `{$dbname}`");
                bootstrap_database_schema($pdo_init);
                $pdo = $pdo_init;
                return $pdo;
            } catch (PDOException $inner_exception) {
                $exception = $inner_exception;
            }
        }

        error_log('Database Connection Error: ' . $exception->getMessage());
        if ($isLocalEnv && filter_var(env_val('APP_DEBUG', false), FILTER_VALIDATE_BOOLEAN)) {
            die('Gagal terhubung ke basis data MySQL. Pastikan layanan MySQL sedang berjalan. Detail: ' . htmlspecialchars($exception->getMessage(), ENT_QUOTES, 'UTF-8'));
        }
        http_response_code(500);
        require BASE_PATH . '/views/errors/500.php';
        exit;
    }

    return $pdo;
}

/**
 * Menjalankan migrasi SQL dan seeder awal saat basis data kosong (khusus instalasi local / CLI)
 */
function bootstrap_database_schema(PDO $pdo): void
{
    $migration_files = glob(BASE_PATH . '/database/migrations/*.sql');
    if (is_array($migration_files)) {
        sort($migration_files);
        foreach ($migration_files as $file) {
            $sql = file_get_contents($file);
            if ($sql !== false && trim($sql) !== '') {
                $pdo->exec($sql);
            }
        }
    }

    $seeder_file = BASE_PATH . '/database/seeders/seed_initial_data.php';
    if (file_exists($seeder_file)) {
        require_once $seeder_file;
        if (function_exists('run_initial_seeder')) {
            run_initial_seeder($pdo);
        }
    }
}
