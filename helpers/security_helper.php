<?php
/**
 * Pustaka Fungsi Keamanan (Security Helper)
 * Menyediakan sanitasi XSS, token CSRF, HMAC, sanitasi CSV, dan rate limiting DB.
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
 * Mengambil kunci rahasia aplikasi tanpa fallback statis yang dapat ditebak
 */
function get_app_secret_key(): string
{
    static $runtimeFallback = null;
    $key = trim((string) env_val('APP_KEY', ''));

    $insecureDefaults = [
        '',
        'pemira_default_secret',
        'GANTI_DENGAN_64_KARAKTER_HEX_ACAK_SEBELUM_PRODUKSI',
    ];

    if (!in_array($key, $insecureDefaults, true) && strlen($key) >= 16) {
        return $key;
    }

    if ($runtimeFallback === null) {
        error_log('Security Warning: APP_KEY belum dikonfigurasi dengan aman pada .env.');
        $runtimeFallback = bin2hex(random_bytes(32));
    }
    return $runtimeFallback;
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
 * Menghasilkan tanda tangan HMAC-SHA256 untuk melindungi integritas data/cookie
 */
function sign_hmac_value(string $value): string
{
    return hash_hmac('sha256', $value, get_app_secret_key());
}

/**
 * Memverifikasi tanda tangan HMAC-SHA256
 */
function verify_hmac_value(string $value, ?string $signature): bool
{
    if ($value === '' || empty($signature)) {
        return false;
    }
    return hash_equals(sign_hmac_value($value), $signature);
}

/**
 * Menghasilkan token kriptografis unik untuk QR Code dan sesi bilik
 */
function generate_secure_token(int $bytes = 24): string
{
    $raw = bin2hex(random_bytes($bytes));
    $signature = substr(hash_hmac('sha256', $raw, get_app_secret_key()), 0, 16);
    return $raw . $signature;
}

/**
 * Menyamarkan NIM pada tampilan layar bilik/status publik (contoh: 240***03)
 */
function mask_nim(?string $nim): ?string
{
    $nim = trim((string) ($nim ?? ''));
    if ($nim === '') {
        return null;
    }
    if (strlen($nim) <= 4) {
        return substr($nim, 0, 1) . str_repeat('*', max(1, strlen($nim) - 1));
    }
    return substr($nim, 0, 3) . '***' . substr($nim, -2);
}

/**
 * Menetralkan karakter pemicu formula spreadsheet (CSV Formula Injection)
 */
function sanitize_csv_cell(mixed $value): string
{
    if ($value === null) {
        return '';
    }
    $str = (string) $value;
    if ($str === '') {
        return '';
    }
    $trimmedLeft = ltrim($str, " \t\r\n");
    $firstChar = $str[0];
    $trimmedFirst = $trimmedLeft !== '' ? $trimmedLeft[0] : '';
    $triggers = ['=', '+', '-', '@', "\t", "\r"];
    if (in_array($firstChar, $triggers, true) || in_array($trimmedFirst, ['=', '+', '-', '@'], true)) {
        return "'" . $str;
    }
    return $str;
}

/**
 * Memeriksa status pembatasan laju (Rate Limit) berdasarkan scope & identifier di database
 */
function check_rate_limit(PDO $pdo, string $scope, string $identifier, int $window_seconds): array
{
    $stmt = $pdo->prepare('
        SELECT attempts, window_started_at, locked_until
        FROM security_rate_limits
        WHERE scope = :scope AND identifier = :identifier
        LIMIT 1
    ');
    $stmt->execute([
        ':scope'      => substr($scope, 0, 40),
        ':identifier' => substr($identifier, 0, 191),
    ]);
    $row = $stmt->fetch();

    if (!$row) {
        return ['blocked' => false, 'remaining_seconds' => 0, 'attempts' => 0];
    }

    $now = time();
    if (!empty($row['locked_until'])) {
        $lockedTs = strtotime((string) $row['locked_until']);
        if ($lockedTs !== false && $lockedTs > $now) {
            return [
                'blocked'           => true,
                'remaining_seconds' => $lockedTs - $now,
                'attempts'          => (int) $row['attempts'],
            ];
        }
    }

    $windowTs = strtotime((string) $row['window_started_at']);
    if ($windowTs === false || ($now - $windowTs) > $window_seconds) {
        return ['blocked' => false, 'remaining_seconds' => 0, 'attempts' => 0];
    }

    return [
        'blocked'           => false,
        'remaining_seconds' => 0,
        'attempts'          => (int) $row['attempts'],
    ];
}

/**
 * Mencatat 1 kegagalan / hit ke rate limit dan mengunci sementara bila mencapai ambang batas
 */
function record_rate_limit_hit(
    PDO $pdo,
    string $scope,
    string $identifier,
    int $max_attempts,
    int $window_seconds,
    int $lock_seconds
): array {
    $scope = substr($scope, 0, 40);
    $identifier = substr($identifier, 0, 191);
    $now = time();

    $stmt = $pdo->prepare('
        SELECT id, attempts, window_started_at, locked_until
        FROM security_rate_limits
        WHERE scope = :scope AND identifier = :identifier
        LIMIT 1
    ');
    $stmt->execute([':scope' => $scope, ':identifier' => $identifier]);
    $row = $stmt->fetch();

    if (!$row) {
        $attempts = 1;
        $lockedUntil = ($attempts >= $max_attempts) ? date('Y-m-d H:i:s', $now + $lock_seconds) : null;
        $ins = $pdo->prepare('
            INSERT INTO security_rate_limits (scope, identifier, attempts, window_started_at, locked_until)
            VALUES (:scope, :identifier, :attempts, NOW(), :locked_until)
        ');
        $ins->execute([
            ':scope'        => $scope,
            ':identifier'   => $identifier,
            ':attempts'     => $attempts,
            ':locked_until' => $lockedUntil,
        ]);
        return [
            'blocked'           => $lockedUntil !== null,
            'remaining_seconds' => $lockedUntil !== null ? $lock_seconds : 0,
            'attempts'          => $attempts,
        ];
    }

    $windowTs = strtotime((string) $row['window_started_at']);
    $lockedTs = !empty($row['locked_until']) ? strtotime((string) $row['locked_until']) : false;
    $expiredWindow = ($windowTs === false) || (($now - $windowTs) > $window_seconds && ($lockedTs === false || $lockedTs <= $now));

    $attempts = $expiredWindow ? 1 : ((int) $row['attempts'] + 1);
    $windowStartStr = $expiredWindow ? date('Y-m-d H:i:s', $now) : (string) $row['window_started_at'];
    $lockedUntil = ($attempts >= $max_attempts) ? date('Y-m-d H:i:s', $now + $lock_seconds) : null;

    $upd = $pdo->prepare('
        UPDATE security_rate_limits
        SET attempts = :attempts,
            window_started_at = :window_started_at,
            locked_until = :locked_until
        WHERE id = :id
    ');
    $upd->execute([
        ':attempts'          => $attempts,
        ':window_started_at' => $windowStartStr,
        ':locked_until'      => $lockedUntil,
        ':id'                => (int) $row['id'],
    ]);

    return [
        'blocked'           => $lockedUntil !== null,
        'remaining_seconds' => $lockedUntil !== null ? $lock_seconds : 0,
        'attempts'          => $attempts,
    ];
}

/**
 * Menghapus catatan rate limit setelah autentikasi berhasil
 */
function clear_rate_limit(PDO $pdo, string $scope, string $identifier): void
{
    $stmt = $pdo->prepare('DELETE FROM security_rate_limits WHERE scope = :scope AND identifier = :identifier');
    $stmt->execute([
        ':scope'      => substr($scope, 0, 40),
        ':identifier' => substr($identifier, 0, 191),
    ]);
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
