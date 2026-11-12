<?php
/**
 * Middleware Autentikasi Sesi Pengguna
 * Memastikan pengguna telah login sebelum mengakses rute privat.
 */

/**
 * Memeriksa apakah admin/panitia saat ini sudah terautentikasi
 */
function is_authenticated(): bool
{
    return !empty($_SESSION['user_id']) && !empty($_SESSION['user_role']);
}

/**
 * Memaksa autentikasi pada rute admin; mengalihkan ke halaman login jika belum masuk
 */
function require_auth(bool $is_ajax = false): void
{
    if (!is_authenticated()) {
        if ($is_ajax) {
            json_response(false, 'Sesi Anda telah berakhir. Silakan login kembali.', [], 401);
        }
        set_flash('warning', 'Silakan login terlebih dahulu untuk mengakses panel sistem.');
        redirect(route_url('login'));
    }
}

/**
 * Mengalihkan pengguna yang sudah login agar tidak kembali ke halaman login
 */
function redirect_if_authenticated(): void
{
    if (is_authenticated()) {
        redirect(route_url('dashboard'));
    }
}

