<?php
/**
 * Controller Portal Perhitungan Suara Terpisah (Live Real Count BEM & BPM Action)
 */

require_once BASE_PATH . '/models/kandidat_model.php';

$pdo = get_db_connection();
$kategori = strtoupper(trim((string) ($_GET['kategori'] ?? 'BEM')));
if (!in_array($kategori, ['BEM', 'BPM'], true)) {
    $kategori = 'BEM';
}

$summary = get_live_count_summary($pdo, $kategori);
json_response(true, "Data Live Count {$kategori} berhasil dimuat.", $summary);

