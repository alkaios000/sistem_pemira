<?php
/**
 * Controller Log Keamanan & Audit Kecurangan (Audit & Fraud Detection Log Action)
 */

require_once BASE_PATH . '/models/log_model.php';

require_auth(true);
$pdo = get_db_connection();
$sub_action = $_GET['sub_action'] ?? $_POST['sub_action'] ?? 'live_logs';

if ($sub_action === 'export_csv') {
    $status_filter = $_GET['status'] ?? null;
    $bilik_id = isset($_GET['bilik_id']) ? (int) $_GET['bilik_id'] : null;
    $search = trim((string) ($_GET['q'] ?? ''));

    $logs = get_audit_logs($pdo, $status_filter, $bilik_id, $search, 500);

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="log_audit_pemira_' . date('Ymd_His') . '.csv"');

    $out = fopen('php://output', 'w');
    fputcsv($out, ['ID', 'Waktu', 'Status', 'Kode Event', 'Judul Event', 'NIM', 'Nama Mahasiswa', 'Kelas', 'Bilik', 'Device ID', 'Fingerprint', 'IP Address', 'Keterangan']);

    foreach ($logs as $row) {
        fputcsv($out, [
            $row['id'],
            $row['created_at'],
            $row['status_log'],
            $row['kode_event'],
            $row['judul_event'],
            $row['nim'] ?? '-',
            $row['nama_mahasiswa'] ?? '-',
            $row['nama_kelas'] ?? '-',
            $row['nama_bilik'] ?? '-',
            $row['device_id'],
            $row['fingerprint_hash'] ?? '-',
            $row['ip_address'],
            $row['keterangan'],
        ]);
    }
    fclose($out);
    exit;
}

// Default: Endpoint JSON Live Audit Log Polling
$status_filter = $_GET['status'] ?? null;
$bilik_id = isset($_GET['bilik_id']) ? (int) $_GET['bilik_id'] : null;
$search = trim((string) ($_GET['q'] ?? ''));

$logs = get_audit_logs($pdo, $status_filter, $bilik_id, $search, 100);
$stats = get_audit_log_stats($pdo);

json_response(true, 'Data log audit berhasil dimuat.', [
    'stats' => $stats,
    'logs'  => $logs,
]);

