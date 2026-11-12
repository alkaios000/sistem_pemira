<?php
/**
 * Pustaka Format Penanggalan & Waktu Lokal Indonesia (Date Helper)
 */

/**
 * Mengubah string datetime menjadi format tanggal resmi Bahasa Indonesia
 */
function format_tanggal_indo(?string $datetime, bool $with_time = true): string
{
    if (empty($datetime)) {
        return '-';
    }

    $timestamp = strtotime($datetime);
    if ($timestamp === false) {
        return '-';
    }

    $bulan = [
        1  => 'Januari',
        2  => 'Februari',
        3  => 'Maret',
        4  => 'April',
        5  => 'Mei',
        6  => 'Juni',
        7  => 'Juli',
        8  => 'Agustus',
        9  => 'September',
        10 => 'Oktober',
        11 => 'November',
        12 => 'Desember',
    ];

    $tgl = date('d', $timestamp);
    $bln = $bulan[(int) date('n', $timestamp)] ?? '';
    $thn = date('Y', $timestamp);

    if ($with_time) {
        $jam = date('H:i:s', $timestamp);
        return "{$tgl} {$bln} {$thn}, {$jam} WIB";
    }

    return "{$tgl} {$bln} {$thn}";
}

/**
 * Menghitung selisih waktu relatif dalam Bahasa Indonesia (contoh: "12 detik lalu")
 */
function format_waktu_relatif(?string $datetime): string
{
    if (empty($datetime)) {
        return '-';
    }

    $timestamp = strtotime($datetime);
    if ($timestamp === false) {
        return '-';
    }

    $diff = time() - $timestamp;
    if ($diff < 5) {
        return 'Baru saja';
    }
    if ($diff < 60) {
        return $diff . ' detik lalu';
    }
    if ($diff < 3600) {
        return floor($diff / 60) . ' menit lalu';
    }
    if ($diff < 86400) {
        return floor($diff / 3600) . ' jam lalu';
    }
    return format_tanggal_indo($datetime, true);
}

