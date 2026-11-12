<?php
/**
 * Skrip Runner Migrasi & Seeder Otomatis (CLI / Web)
 * Membaca seluruh berkas SQL di database/migrations/ secara berurutan dan menjalankan seeder awal.
 */

define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/config/config.php';
require_once BASE_PATH . '/database/seeders/seed_initial_data.php';

$host = env_val('DB_HOST', '127.0.0.1');
$port = env_val('DB_PORT', '3306');
$dbname = env_val('DB_DATABASE', 'db_sistem_pemira');
$username = env_val('DB_USERNAME', 'root');
$password = env_val('DB_PASSWORD', '');

$is_cli = (PHP_SAPI === 'cli');

try {
    // 1. Koneksi awal tanpa nama database untuk membuat database jika belum tersedia
    $pdo_root = new PDO("mysql:host={$host};port={$port};charset=utf8mb4", $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);

    $pdo_root->exec("CREATE DATABASE IF NOT EXISTS `{$dbname}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo_root->exec("USE `{$dbname}`");

    // 2. Jalankan seluruh berkas migrasi sesuai urutan abjad/nomor
    $migration_files = glob(__DIR__ . '/migrations/*.sql');
    sort($migration_files);

    $executed = [];
    foreach ($migration_files as $file) {
        $sql = file_get_contents($file);
        if ($sql !== false && trim($sql) !== '') {
            $pdo_root->exec($sql);
            $executed[] = basename($file);
        }
    }

    // 3. Jalankan Seeder Data Awal
    run_initial_seeder($pdo_root);

    if ($is_cli) {
        echo "=== MIGRASI & SEEDING BERHASIL ===\n";
        echo "Database: {$dbname}\n";
        foreach ($executed as $item) {
            echo " - [OK] {$item}\n";
        }
        echo " - [OK] seed_initial_data.php\n";
    } else {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'status'     => 'success',
            'database'   => $dbname,
            'migrations' => $executed,
            'message'    => 'Seluruh tabel berhasil dimigrasikan dan data awal telah diisi.',
        ], JSON_PRETTY_PRINT);
    }
} catch (PDOException $e) {
    if ($is_cli) {
        fwrite(STDERR, "ERROR MIGRASI: " . $e->getMessage() . "\n");
        exit(1);
    }
    http_response_code(500);
    echo json_encode([
        'status'  => 'error',
        'message' => $e->getMessage(),
    ]);
}

