<?php
/**
 * Konfigurasi Global & Inisialisasi Sistem
 * Memuat variabel .env, mengatur timezone, session, dan koneksi PDO MySQL.
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
    return $_ENV[$key] ?? getenv($key) ?: $default;
}

// Konfigurasi zona waktu lokal Indonesia
date_default_timezone_set('Asia/Jakarta');

// Pengaturan Error Reporting berdasarkan mode APP_DEBUG
$is_debug = filter_var(env_val('APP_DEBUG', false), FILTER_VALIDATE_BOOLEAN);
if ($is_debug) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(0);
}

// Inisialisasi Session yang aman
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
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

    $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";

    try {
        $pdo = new PDO($dsn, $username, $password, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);

        // Cek apakah tabel dasar sudah tersedia; jika belum, jalankan auto-migrasi awal
        $stmt_check = $pdo->query("SHOW TABLES LIKE 'users'");
        if ($stmt_check->rowCount() === 0) {
            bootstrap_database_schema($pdo);
        }
    } catch (PDOException $exception) {
        // Jika database belum dibuat (kode error 1049), buat database dan jalankan migrasi otomatis
        if ((int) $exception->getCode() === 1049 || str_contains($exception->getMessage(), 'Unknown database')) {
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
        if (filter_var(env_val('APP_DEBUG', false), FILTER_VALIDATE_BOOLEAN)) {
            die('Gagal terhubung ke basis data MySQL. Pastikan layanan MySQL pada Laragon sedang berjalan. Detail: ' . htmlspecialchars($exception->getMessage(), ENT_QUOTES, 'UTF-8'));
        }
        http_response_code(500);
        require BASE_PATH . '/views/errors/500.php';
        exit;
    }

    return $pdo;
}

/**
 * Menjalankan migrasi SQL dan seeder awal secara otomatis saat basis data masih kosong
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
