<?php
/**
 * Controller Audit Log & Pengawasan Keamanan (Hanya Superadmin & Banwasra)
 * - KPRM diblokir total (403) dari melihat maupun mengekspor log aktivitas.
 * - Banwasra dapat melihat dan mengekspor seluruh log KECUALI log yang dilakukan oleh Superadmin.
 * - Superadmin melihat seluruh log tanpa pengecualian.
 */

require_once BASE_PATH . '/models/log_model.php';

$sub_action = $_GET['sub_action'] ?? 'list_json';
$is_ajax = ($sub_action !== 'export_csv');
require_role(['superadmin', 'banwasra'], $is_ajax);

$pdo = get_db_connection();
$status = strtoupper(trim((string) ($_GET['status'] ?? '')));
if (!in_array($status, ['NORMAL', 'FRAUD'], true)) {
    $status = null;
}

$category = strtolower(trim((string) ($_GET['category'] ?? $_GET['tab'] ?? '')));
if (!in_array($category, ['pemilih', 'admin'], true)) {
    $category = null;
}

$actor_role = strtolower(trim((string) ($_GET['actor_role'] ?? '')));
if (!in_array($actor_role, ['superadmin', 'kprm', 'banwasra', 'guest'], true)) {
    $actor_role = null;
}

$bilik_id = isset($_GET['bilik_id']) && $_GET['bilik_id'] !== '' ? (int) $_GET['bilik_id'] : null;
$search = trim((string) ($_GET['q'] ?? ''));
$exclude_superadmin = is_banwasra();

if ($sub_action === 'export_csv') {
    $logs = get_audit_logs($pdo, $status, $bilik_id, $search, 5000, $exclude_superadmin, $category, $actor_role);
    $categoryLabel = $category === 'pemilih' ? 'Log Pemilih' : ($category === 'admin' ? 'Log Admin' : 'Semua Log');
    $filePrefix = $category !== null ? "audit_log_{$category}_" : 'audit_log_pemira_';

    record_admin_audit_log(
        $pdo,
        'LOG_EXPORT_CSV',
        'Ekspor Log Keamanan CSV',
        "Pengguna {$_SESSION['username']} (" . current_user_role() . ") mengekspor " . count($logs) . " baris {$categoryLabel} ke CSV."
    );

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filePrefix . date('Ymd_His') . '.csv"');
    header('X-Content-Type-Options: nosniff');

    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['ID', 'Waktu', 'Kategori', 'Status', 'Kode Event', 'Judul Event', 'Bilik', 'Aktor', 'Role Aktor', 'NIM', 'Device ID', 'IP Address', 'Keterangan']);

    foreach ($logs as $row) {
        fputcsv($out, [
            sanitize_csv_cell($row['id'] ?? ''),
            sanitize_csv_cell($row['created_at'] ?? ''),
            sanitize_csv_cell(strtoupper((string) ($row['log_category'] ?? 'pemilih'))),
            sanitize_csv_cell($row['status_log'] ?? ''),
            sanitize_csv_cell($row['kode_event'] ?? ''),
            sanitize_csv_cell($row['judul_event'] ?? ''),
            sanitize_csv_cell($row['nama_bilik'] ?? '-'),
            sanitize_csv_cell($row['actor_username'] ?? '-'),
            sanitize_csv_cell($row['actor_role'] ?? '-'),
            sanitize_csv_cell($row['nim'] ?? '-'),
            sanitize_csv_cell($row['device_id'] ?? '-'),
            sanitize_csv_cell($row['ip_address'] ?? '-'),
            sanitize_csv_cell($row['keterangan'] ?? ''),
        ]);
    }
    fclose($out);
    exit;
}

$logs = get_audit_logs($pdo, $status, $bilik_id, $search, 200, $exclude_superadmin, $category, $actor_role);
$stats = get_audit_log_stats($pdo, $exclude_superadmin, $category);

json_response(true, 'Data log keamanan berhasil dimuat.', [
    'logs'  => $logs,
    'stats' => $stats,
]);
