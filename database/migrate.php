<?php
/**
 * Skrip Runner Migrasi & Seeder Otomatis (CLI Only / Protected)
 * Membaca seluruh berkas SQL di database/migrations/ secara berurutan (idempotent untuk migrasi 07).
 */

define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/config/config.php';
require_once BASE_PATH . '/database/seeders/seed_initial_data.php';

$is_cli = (PHP_SAPI === 'cli');
if (!$is_cli) {
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'status'  => 'error',
        'message' => 'Akses web langsung ke skrip migrasi dinonaktifkan demi keamanan. Jalankan melalui CLI server.',
    ]);
    exit;
}

$host = env_val('DB_HOST', '127.0.0.1');
$port = env_val('DB_PORT', '3306');
$dbname = env_val('DB_DATABASE', 'db_sistem_pemira');
$username = env_val('DB_USERNAME', 'root');
$password = env_val('DB_PASSWORD', '');

/**
 * Menerapkan migrasi 07 secara aman agar tidak error bila kolom sudah ada
 */
function apply_security_migration_idempotent(PDO $pdo, string $dbname): void
{
    $checkCol = static function (string $table, string $column) use ($pdo, $dbname): bool {
        $stmt = $pdo->prepare('
            SELECT COUNT(*)
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = :db AND TABLE_NAME = :tbl AND COLUMN_NAME = :col
        ');
        $stmt->execute([':db' => $dbname, ':tbl' => $table, ':col' => $column]);
        return (int) $stmt->fetchColumn() > 0;
    };

    if (!$checkCol('users', 'is_active')) {
        $pdo->exec("
            ALTER TABLE `users`
                MODIFY COLUMN `role` ENUM('admin', 'panitia', 'superadmin', 'kprm', 'banwasra') NOT NULL DEFAULT 'kprm',
                ADD COLUMN `is_active` TINYINT(1) NOT NULL DEFAULT 1 AFTER `role`,
                ADD COLUMN `last_login_at` DATETIME DEFAULT NULL AFTER `is_active`
        ");
    } else {
        $pdo->exec("
            ALTER TABLE `users`
                MODIFY COLUMN `role` ENUM('admin', 'panitia', 'superadmin', 'kprm', 'banwasra') NOT NULL DEFAULT 'kprm'
        ");
    }

    $pdo->exec("UPDATE `users` SET `role` = 'superadmin' WHERE `role` = 'admin'");
    $pdo->exec("UPDATE `users` SET `role` = 'kprm' WHERE `role` = 'panitia'");
    $pdo->exec("ALTER TABLE `users` MODIFY COLUMN `role` ENUM('superadmin', 'kprm', 'banwasra') NOT NULL DEFAULT 'kprm'");

    if (!$checkCol('audit_logs', 'actor_role')) {
        $pdo->exec("
            ALTER TABLE `audit_logs`
                ADD COLUMN `actor_user_id` INT UNSIGNED DEFAULT NULL AFTER `bilik_id`,
                ADD COLUMN `actor_username` VARCHAR(60) DEFAULT NULL AFTER `actor_user_id`,
                ADD COLUMN `actor_role` ENUM('superadmin', 'kprm', 'banwasra') DEFAULT NULL AFTER `actor_username`,
                ADD KEY `idx_audit_actor_role` (`actor_role`),
                ADD KEY `idx_audit_actor_user` (`actor_user_id`)
        ");
    }

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `security_rate_limits` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `scope` VARCHAR(40) NOT NULL,
            `identifier` VARCHAR(191) NOT NULL,
            `attempts` INT UNSIGNED NOT NULL DEFAULT 0,
            `window_started_at` DATETIME NOT NULL,
            `locked_until` DATETIME DEFAULT NULL,
            `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uq_rate_limit_scope_ident` (`scope`, `identifier`),
            KEY `idx_rate_limit_locked` (`locked_until`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `ballot_boxes` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `ballot_hash` CHAR(64) NOT NULL,
            `bilik_id` INT UNSIGNED DEFAULT NULL,
            `kandidat_bem_id` INT UNSIGNED NOT NULL,
            `kandidat_bpm_id` INT UNSIGNED NOT NULL,
            `cast_bucket` DATETIME NOT NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uq_ballot_hash` (`ballot_hash`),
            KEY `idx_ballot_bem` (`kandidat_bem_id`),
            KEY `idx_ballot_bpm` (`kandidat_bpm_id`),
            KEY `idx_ballot_bucket` (`cast_bucket`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $pdo->exec("
        INSERT IGNORE INTO `pengaturan_sistem` (`kunci`, `nilai`, `keterangan`)
        VALUES ('live_count_publik', 'BUKA', 'Status tampilan Live Count publik (BUKA / TUTUP)')
    ");
}

/**
 * Menerapkan migrasi 08 secara idempotent untuk pemisahan log pemilih vs log admin
 */
function apply_log_category_migration_idempotent(PDO $pdo, string $dbname): void
{
    $stmtCol = $pdo->prepare('
        SELECT COUNT(*)
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = :db AND TABLE_NAME = :tbl AND COLUMN_NAME = :col
    ');
    $stmtCol->execute([':db' => $dbname, ':tbl' => 'audit_logs', ':col' => 'log_category']);
    $hasCol = (int) $stmtCol->fetchColumn() > 0;

    if (!$hasCol) {
        $pdo->exec("
            ALTER TABLE `audit_logs`
                ADD COLUMN `log_category` ENUM('pemilih', 'admin') NOT NULL DEFAULT 'pemilih' AFTER `actor_role`,
                ADD KEY `idx_audit_log_category` (`log_category`)
        ");
    }

    $pdo->exec("
        UPDATE `audit_logs`
        SET `log_category` = 'admin'
        WHERE `actor_role` IS NOT NULL
           OR `actor_user_id` IS NOT NULL
           OR `actor_username` IS NOT NULL
           OR `device_id` LIKE 'panel_%'
           OR `kode_event` LIKE 'ADMIN_%'
           OR `kode_event` LIKE 'USER_%'
           OR `kode_event` LIKE 'BILIK_%'
           OR `kode_event` LIKE 'PORTAL_%'
           OR `kode_event` LIKE 'CHECKIN_%'
           OR `kode_event` LIKE 'LIVE_COUNT_%'
           OR `kode_event` LIKE 'QR_FORCE_%'
           OR `kode_event` LIKE 'KANDIDAT_%'
           OR `kode_event` LIKE 'MAHASISWA_%'
           OR `kode_event` LIKE 'KELAS_%'
           OR `kode_event` LIKE 'JURUSAN_%'
           OR `kode_event` LIKE 'LOG_%'
    ");
}

/**
 * Menerapkan migrasi 09 secara idempotent untuk kunci kiosk independen per bilik
 */
function apply_bilik_kiosk_migration_idempotent(PDO $pdo, string $dbname): void
{
    $stmtCol = $pdo->prepare('
        SELECT COUNT(*)
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = :db AND TABLE_NAME = :tbl AND COLUMN_NAME = :col
    ');
    $stmtCol->execute([':db' => $dbname, ':tbl' => 'bilik_suaras', ':col' => 'kiosk_token']);
    $hasCol = (int) $stmtCol->fetchColumn() > 0;

    if (!$hasCol) {
        $pdo->exec("
            ALTER TABLE `bilik_suaras`
                ADD COLUMN `kiosk_token` VARCHAR(128) DEFAULT NULL AFTER `previous_token`,
                ADD KEY `idx_bilik_kiosk_token` (`kiosk_token`)
        ");
    }

    $emptyIds = $pdo->query("SELECT id FROM bilik_suaras WHERE kiosk_token IS NULL OR kiosk_token = ''")->fetchAll(PDO::FETCH_COLUMN);
    if (!empty($emptyIds)) {
        $stmtUpd = $pdo->prepare('UPDATE bilik_suaras SET kiosk_token = :kiosk_token WHERE id = :id');
        foreach ($emptyIds as $bid) {
            $stmtUpd->execute([
                ':kiosk_token' => bin2hex(random_bytes(32)),
                ':id'          => (int) $bid,
            ]);
        }
    }
}

try {
    $pdo_root = new PDO("mysql:host={$host};port={$port};charset=utf8mb4", $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);

    $pdo_root->exec("CREATE DATABASE IF NOT EXISTS `{$dbname}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo_root->exec("USE `{$dbname}`");

    $migration_files = glob(__DIR__ . '/migrations/*.sql');
    sort($migration_files);

    $executed = [];
    foreach ($migration_files as $file) {
        $base = basename($file);
        if (str_starts_with($base, '07_')) {
            apply_security_migration_idempotent($pdo_root, $dbname);
            $executed[] = $base . ' (idempotent)';
            continue;
        }
        if (str_starts_with($base, '08_')) {
            apply_log_category_migration_idempotent($pdo_root, $dbname);
            $executed[] = $base . ' (idempotent)';
            continue;
        }
        if (str_starts_with($base, '09_')) {
            apply_bilik_kiosk_migration_idempotent($pdo_root, $dbname);
            $executed[] = $base . ' (idempotent)';
            continue;
        }
        $sql = file_get_contents($file);
        if ($sql !== false && trim($sql) !== '') {
            $pdo_root->exec($sql);
            $executed[] = $base;
        }
    }

    run_initial_seeder($pdo_root);

    echo "=== MIGRASI & SEEDING BERHASIL ===\n";
    echo "Database: {$dbname}\n";
    foreach ($executed as $item) {
        echo " - [OK] {$item}\n";
    }
    echo " - [OK] seed_initial_data.php\n";
} catch (PDOException $e) {
    fwrite(STDERR, "ERROR MIGRASI: " . $e->getMessage() . "\n");
    exit(1);
}
