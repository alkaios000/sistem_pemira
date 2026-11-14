<?php
/**
 * Pustaka Pembentuk URL & Navigasi Rute (URL Helper)
 * Dilengkapi perlindungan terhadap Host Header Injection dan Open Redirect.
 */

/**
 * Daftar host resmi yang diizinkan aplikasi berdasarkan BASE_URL dan ALLOWED_HOSTS
 */
function get_allowed_hosts(): array
{
    $hosts = [];
    $configuredBase = (string) env_val('BASE_URL', '');
    if ($configuredBase !== '') {
        $parsedHost = parse_url($configuredBase, PHP_URL_HOST);
        $parsedPort = parse_url($configuredBase, PHP_URL_PORT);
        if (is_string($parsedHost) && $parsedHost !== '') {
            $hosts[] = strtolower($parsedHost);
            if ($parsedPort !== null) {
                $hosts[] = strtolower($parsedHost . ':' . $parsedPort);
            }
        }
    }

    $allowedRaw = (string) env_val('ALLOWED_HOSTS', '');
    foreach (explode(',', $allowedRaw) as $item) {
        $h = strtolower(trim($item));
        if ($h !== '') {
            $hosts[] = $h;
        }
    }

    return array_values(array_unique($hosts));
}

/**
 * Mendapatkan Base URL aplikasi secara aman (terlindungi dari Host Header Poisoning)
 */
function base_url(string $path = ''): string
{
    $configuredBase = rtrim((string) env_val('BASE_URL', 'http://localhost/sistem_pemira'), '/');
    $script_dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
    $script_dir = rtrim($script_dir, '/');

    $base = $configuredBase;
    $rawHost = trim((string) ($_SERVER['HTTP_HOST'] ?? ''));

    if ($rawHost !== '' && preg_match('/^[a-zA-Z0-9\.\-]+(:\d{1,5})?$/', $rawHost)) {
        $hostLower = strtolower($rawHost);
        $hostOnly = strtolower(explode(':', $hostLower, 2)[0]);
        $allowedHosts = get_allowed_hosts();

        if (in_array($hostLower, $allowedHosts, true) || in_array($hostOnly, $allowedHosts, true)) {
            $scheme = is_https_request() ? 'https' : 'http';
            $base = $scheme . '://' . $rawHost . $script_dir;
        }
    }

    $clean_path = ltrim($path, '/');
    return $clean_path !== '' ? $base . '/' . $clean_path : $base;
}

/**
 * Memvalidasi bahwa URL tujuan redirect merupakan URL internal yang aman
 */
function is_safe_internal_url(?string $url): bool
{
    $url = trim((string) $url);
    if ($url === '') {
        return false;
    }

    // Tolak karakter kontrol atau skema berbahaya
    if (preg_match('/[\r\n\0]/', $url)) {
        return false;
    }

    // Path relatif lokal (misal /sistem_pemira/index.php?...)
    if (str_starts_with($url, '/') && !str_starts_with($url, '//') && !str_starts_with($url, '/\\')) {
        return true;
    }

    $parts = parse_url($url);
    if ($parts === false || empty($parts['host']) || empty($parts['scheme'])) {
        return false;
    }

    $scheme = strtolower($parts['scheme']);
    if (!in_array($scheme, ['http', 'https'], true)) {
        return false;
    }

    $targetHost = strtolower($parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : ''));
    $targetHostOnly = strtolower($parts['host']);
    $allowedHosts = get_allowed_hosts();
    $baseHost = strtolower((string) parse_url(base_url(), PHP_URL_HOST));

    return $targetHostOnly === $baseHost
        || in_array($targetHost, $allowedHosts, true)
        || in_array($targetHostOnly, $allowedHosts, true);
}

/**
 * Menghasilkan URL menuju halaman tampilan (page route) melalui Front Controller index.php
 */
function route_url(string $page, array $params = []): string
{
    $query = array_merge(['page' => $page], $params);
    return base_url('index.php?' . http_build_query($query));
}

/**
 * Menghasilkan URL menuju controller penangan aksi (action route) melalui Front Controller index.php
 */
function action_url(string $action, array $params = []): string
{
    $query = array_merge(['action' => $action], $params);
    return base_url('index.php?' . http_build_query($query));
}

/**
 * Menghasilkan URL untuk berkas statis di dalam direktori assets/
 */
function asset_url(string $asset_path): string
{
    return base_url('assets/' . ltrim($asset_path, '/'));
}

/**
 * Menghasilkan URL untuk berkas unggahan di dalam direktori uploads/
 */
function upload_url(string $upload_path): string
{
    return base_url('uploads/' . ltrim($upload_path, '/'));
}

/**
 * Memeriksa apakah request berasal dari pemanggilan AJAX / Fetch Real-Time
 */
function is_ajax_request(): bool
{
    return (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower((string) $_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
        || (!empty($_POST['_ajax']) && $_POST['_ajax'] === '1')
        || (!empty($_GET['_ajax']) && $_GET['_ajax'] === '1');
}

/**
 * Melakukan pengalihan halaman HTTP (Redirect) atau mengembalikan JSON bila dipanggil via AJAX Real-Time
 */
function redirect(string $url): never
{
    $safeUrl = is_safe_internal_url($url) ? $url : route_url('dashboard');
    if (is_ajax_request() && function_exists('get_flash') && function_exists('json_response')) {
        $flash = get_flash();
        $type = $flash['type'] ?? 'success';
        $message = $flash['message'] ?? 'Perubahan berhasil disimpan.';
        $is_ok = ($type !== 'danger');
        json_response($is_ok, $message, [
            'redirect_url' => $safeUrl,
            'flash_type'   => $type,
        ]);
    }
    header('Location: ' . $safeUrl);
    exit;
}
