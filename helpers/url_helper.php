<?php
/**
 * Pustaka Pembentuk URL & Navigasi Rute (URL Helper)
 */

/**
 * Mendapatkan Base URL aplikasi secara dinamis atau dari .env
 */
function base_url(string $path = ''): string
{
    $script_dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
    $script_dir = rtrim($script_dir, '/');

    if (isset($_SERVER['HTTP_HOST'])) {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $base = $scheme . '://' . $_SERVER['HTTP_HOST'] . $script_dir;
    } else {
        $base = rtrim((string) env_val('BASE_URL', 'http://localhost/sistem_pemira'), '/');
    }

    $clean_path = ltrim($path, '/');
    return $clean_path !== '' ? $base . '/' . $clean_path : $base;
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
 * Melakukan pengalihan halaman HTTP (Redirect) dan menghentikan skrip
 */
function redirect(string $url): never
{
    header('Location: ' . $url);
    exit;
}

