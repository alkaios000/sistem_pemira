<?php
/**
 * Controller Portal Perhitungan Suara Terpisah (Live Real Count BEM & BPM Action)
 * Menghormati pengaturan publikasi live_count_publik untuk pengguna non-admin.
 */

require_once BASE_PATH . '/models/kandidat_model.php';

$pdo = get_db_connection();
$kategori = strtoupper(trim((string) ($_GET['kategori'] ?? 'BEM')));
if (!in_array($kategori, ['BEM', 'BPM'], true)) {
    $kategori = 'BEM';
}

$live_count_publik = get_setting_value($pdo, 'live_count_publik', 'BUKA');
if ($live_count_publik !== 'BUKA' && !is_authenticated()) {
    json_response(false, 'Publikasi Live Count sementara ditutup oleh panitia KPRM.', [
        'published' => false,
        'kategori'  => $kategori,
    ], 403);
}

$summary = get_live_count_summary($pdo, $kategori);
$summary['published'] = ($live_count_publik === 'BUKA');
json_response(true, "Data Live Count {$kategori} berhasil dimuat.", $summary);
