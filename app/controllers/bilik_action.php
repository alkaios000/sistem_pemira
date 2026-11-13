<?php
/**
 * Controller Manajemen Bilik Suara & Portal Manajemen Token (Booth & Token Control Action)
 */

require_once BASE_PATH . '/models/bilik_model.php';
require_once BASE_PATH . '/models/user_model.php';

$pdo = get_db_connection();
$sub_action = $_POST['sub_action'] ?? $_GET['sub_action'] ?? '';

// Endpoint JSON AJAX untuk memantau status bilik secara live di halaman admin
if ($sub_action === 'status_list_json') {
    require_auth(true);
    $biliks = get_all_biliks($pdo);
    $global_portal = get_setting_value($pdo, 'status_portal_global', 'BUKA');
    json_response(true, 'Data status bilik berhasil dimuat.', [
        'global_portal' => $global_portal,
        'biliks'        => $biliks,
    ]);
}

require_auth();
verify_csrf_request();

try {
    if ($sub_action === 'create_bilik') {
        $nomor_bilik = (int) ($_POST['nomor_bilik'] ?? 0);
        $nama_bilik = trim((string) ($_POST['nama_bilik'] ?? ''));
        $lokasi_bilik = trim((string) ($_POST['lokasi_bilik'] ?? 'Ruang TPS Utama'));
        if ($lokasi_bilik === '') $lokasi_bilik = 'Ruang TPS Utama';

        if ($nomor_bilik < 1 || $nama_bilik === '') {
            set_flash('danger', 'Nomor bilik dan nama bilik suara wajib diisi.');
            redirect(route_url('portal_qr'));
        }

        create_bilik($pdo, $nomor_bilik, $nama_bilik, $lokasi_bilik);
        set_flash('success', "{$nama_bilik} (Nomor {$nomor_bilik}) berhasil ditambahkan.");
        redirect(route_url('portal_qr'));
    }

    if ($sub_action === 'update_bilik') {
        $id = (int) ($_POST['id'] ?? 0);
        $nomor_bilik = (int) ($_POST['nomor_bilik'] ?? 0);
        $nama_bilik = trim((string) ($_POST['nama_bilik'] ?? ''));
        $lokasi_bilik = trim((string) ($_POST['lokasi_bilik'] ?? 'Ruang TPS Utama'));
        if ($lokasi_bilik === '') $lokasi_bilik = 'Ruang TPS Utama';

        if ($id < 1 || $nomor_bilik < 1 || $nama_bilik === '') {
            set_flash('danger', 'Data pembaruan bilik suara tidak valid.');
            redirect(route_url('portal_qr'));
        }

        update_bilik($pdo, $id, $nomor_bilik, $nama_bilik, $lokasi_bilik);
        set_flash('success', "Data {$nama_bilik} berhasil diperbarui.");
        redirect(route_url('portal_qr'));
    }

    if ($sub_action === 'delete_bilik') {
        $id = (int) ($_POST['id'] ?? 0);
        delete_bilik($pdo, $id);
        set_flash('success', 'Bilik suara berhasil dihapus.');
        redirect(route_url('portal_qr'));
    }

    if ($sub_action === 'toggle_bilik_status') {
        $id = (int) ($_POST['id'] ?? 0);
        $target_status = ($_POST['target_status'] ?? 'IDLE') === 'CLOSED' ? 'CLOSED' : 'IDLE';
        set_status_bilik($pdo, $id, $target_status);

        $label = $target_status === 'CLOSED' ? 'ditutup sementara' : 'dibuka kembali';
        set_flash('success', "Status bilik suara berhasil {$label}.");
        redirect(route_url('portal_qr'));
    }

    if ($sub_action === 'force_reset_bilik') {
        $id = (int) ($_POST['id'] ?? 0);
        force_reset_bilik($pdo, $id);
        set_flash('success', 'Token bilik berhasil di-reset dan kunci sesi telah dilepaskan.');
        redirect(route_url('portal_qr'));
    }

    if ($sub_action === 'toggle_global_portal') {
        $portal_status = ($_POST['portal_status'] ?? 'BUKA') === 'TUTUP' ? 'TUTUP' : 'BUKA';
        update_setting_value($pdo, 'status_portal_global', $portal_status);

        if ($portal_status === 'TUTUP') {
            set_status_all_biliks($pdo, 'CLOSED');
            set_flash('warning', 'Portal Pemungutan Suara Global telah DITUTUP SEMENTARA. Seluruh bilik tidak menerima pemindaian baru.');
        } else {
            set_status_all_biliks($pdo, 'IDLE');
            set_flash('success', 'Portal Pemungutan Suara Global telah DIBUKA. Seluruh bilik siap menerima pemilih.');
        }
        redirect(route_url('portal_qr'));
    }

    if ($sub_action === 'toggle_wajib_checkin') {
        $wajib = ($_POST['wajib_checkin_panitia'] ?? '0') === '1' ? '1' : '0';
        update_setting_value($pdo, 'wajib_checkin_panitia', $wajib);
        $status_text = $wajib === '1'
            ? 'Mode Wajib Check-In Meja Panitia DIAKTIFKAN (hanya mahasiswa yang sudah di-check-in yang bisa memilih).'
            : 'Mode Wajib Check-In Meja Panitia DINONAKTIFKAN (semua NIM di DPT yang belum memilih dapat langsung memilih).';
        set_flash('success', $status_text);
        redirect(route_url('portal_qr'));
    }
} catch (PDOException $e) {
    if ((int) $e->errorInfo[1] === 1062) {
        set_flash('danger', 'Nomor bilik suara tersebut sudah terdaftar. Gunakan nomor bilik lain.');
    } else {
        error_log('Bilik Action Error: ' . $e->getMessage());
        set_flash('danger', 'Terjadi kesalahan saat memproses aksi bilik suara.');
    }
    redirect(route_url('portal_qr'));
}

redirect(route_url('portal_qr'));

