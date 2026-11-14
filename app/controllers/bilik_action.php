<?php
/**
 * Controller Manajemen Bilik Suara & Portal Manajemen Token (Booth & Token Control Action)
 */

require_once BASE_PATH . '/models/bilik_model.php';
require_once BASE_PATH . '/models/user_model.php';
require_once BASE_PATH . '/models/log_model.php';

$is_ajax = is_ajax_request();
$pdo = get_db_connection();
$sub_action = $_POST['sub_action'] ?? $_GET['sub_action'] ?? '';

// Endpoint JSON AJAX untuk memantau status bilik secara live (Superadmin, KPRM, Banwasra)
if ($sub_action === 'status_list_json') {
    require_role(['superadmin', 'kprm', 'banwasra'], true);
    $biliks = get_all_biliks_sanitized($pdo, can_view_logs(), is_banwasra());
    $global_portal = get_setting_value($pdo, 'status_portal_global', 'BUKA');
    $live_count_publik = get_setting_value($pdo, 'live_count_publik', 'BUKA');
    json_response(true, 'Data status bilik berhasil dimuat.', [
        'global_portal'     => $global_portal,
        'live_count_publik' => $live_count_publik,
        'biliks'            => $biliks,
    ]);
}

require_role(['superadmin', 'kprm'], $is_ajax);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(route_url('portal_qr'));
}
verify_csrf_request($is_ajax);

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

        $new_bilik_id = create_bilik($pdo, $nomor_bilik, $nama_bilik, $lokasi_bilik);
        record_admin_audit_log(
            $pdo,
            'BILIK_CREATE',
            'Penambahan Bilik Suara Baru',
            "Operator {$_SESSION['username']} menambahkan {$nama_bilik} (Nomor {$nomor_bilik}, {$lokasi_bilik}).",
            'NORMAL',
            $new_bilik_id ?: null
        );
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
        record_admin_audit_log(
            $pdo,
            'BILIK_UPDATE',
            'Perubahan Data Bilik Suara',
            "Operator {$_SESSION['username']} memperbarui {$nama_bilik} (Nomor {$nomor_bilik}, {$lokasi_bilik}).",
            'NORMAL',
            $id
        );
        set_flash('success', "Data {$nama_bilik} berhasil diperbarui.");
        redirect(route_url('portal_qr'));
    }

    if ($sub_action === 'delete_bilik') {
        $id = (int) ($_POST['id'] ?? 0);
        $existing = $id > 0 ? find_bilik_by_id($pdo, $id) : null;
        if (!$existing) {
            set_flash('danger', 'Data bilik suara tidak ditemukan.');
            redirect(route_url('portal_qr'));
        }
        if ($existing['status_bilik'] === 'IN_USE' || (int) $existing['total_suara_bilik'] > 0) {
            set_flash('danger', "Bilik {$existing['nama_bilik']} tidak dapat dihapus karena sedang digunakan atau sudah mencatat suara masuk.");
            redirect(route_url('portal_qr'));
        }

        delete_bilik($pdo, $id);
        record_admin_audit_log(
            $pdo,
            'BILIK_DELETE',
            'Penghapusan Bilik Suara',
            "Operator {$_SESSION['username']} menghapus {$existing['nama_bilik']} (Nomor {$existing['nomor_bilik']})."
        );
        set_flash('success', 'Bilik suara berhasil dihapus.');
        redirect(route_url('portal_qr'));
    }

    if ($sub_action === 'toggle_bilik_status') {
        $id = (int) ($_POST['id'] ?? 0);
        $target_status = ($_POST['target_status'] ?? 'IDLE') === 'CLOSED' ? 'CLOSED' : 'IDLE';
        set_status_bilik($pdo, $id, $target_status);

        $label = $target_status === 'CLOSED' ? 'ditutup sementara' : 'dibuka kembali';
        record_admin_audit_log(
            $pdo,
            'BILIK_TOGGLE_STATUS',
            'Perubahan Status Bilik Suara',
            "Operator {$_SESSION['username']} mengubah status bilik #{$id} menjadi {$target_status}.",
            'NORMAL',
            $id ?: null
        );
        set_flash('success', "Status bilik suara berhasil {$label}.");
        redirect(route_url('portal_qr'));
    }

    if ($sub_action === 'force_reset_bilik') {
        $id = (int) ($_POST['id'] ?? 0);
        force_reset_bilik($pdo, $id);
        record_admin_audit_log(
            $pdo,
            'BILIK_FORCE_RESET',
            'Force Reset Sesi Bilik Suara',
            "Operator {$_SESSION['username']} melepaskan paksa sesi aktif dan merotasi token bilik #{$id}.",
            'NORMAL',
            $id ?: null
        );
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
        record_admin_audit_log(
            $pdo,
            'PORTAL_GLOBAL_TOGGLE',
            'Perubahan Status Portal Global',
            "Operator {$_SESSION['username']} mengubah status portal pemungutan suara global menjadi {$portal_status}."
        );
        redirect(route_url('portal_qr'));
    }

    if ($sub_action === 'toggle_wajib_checkin') {
        $wajib = ($_POST['wajib_checkin_panitia'] ?? '0') === '1' ? '1' : '0';
        update_setting_value($pdo, 'wajib_checkin_panitia', $wajib);
        record_admin_audit_log(
            $pdo,
            'CHECKIN_MODE_TOGGLE',
            'Perubahan Mode Wajib Check-In',
            "Operator {$_SESSION['username']} mengatur mode Wajib Check-In menjadi " . ($wajib === '1' ? 'AKTIF' : 'NONAKTIF') . '.'
        );
        $status_text = $wajib === '1'
            ? 'Mode Wajib Check-In Meja Panitia DIAKTIFKAN (hanya mahasiswa yang sudah di-check-in yang bisa memilih).'
            : 'Mode Wajib Check-In Meja Panitia DINONAKTIFKAN (semua NIM di DPT yang belum memilih dapat langsung memilih).';
        set_flash('success', $status_text);
        redirect(route_url('portal_qr'));
    }

    if ($sub_action === 'toggle_live_count_publik') {
        $publik = ($_POST['live_count_publik'] ?? 'BUKA') === 'TUTUP' ? 'TUTUP' : 'BUKA';
        update_setting_value($pdo, 'live_count_publik', $publik);
        record_admin_audit_log(
            $pdo,
            'LIVE_COUNT_PUBLIK_TOGGLE',
            'Perubahan Publikasi Live Count',
            "Operator {$_SESSION['username']} mengatur publikasi Live Count Publik menjadi {$publik}."
        );
        $status_text = $publik === 'BUKA'
            ? 'Publikasi Live Count ke publik telah DIBUKA.'
            : 'Publikasi Live Count ke publik telah DITUTUP SEMENTARA (hanya dapat dilihat panitia/pengawas yang login).';
        set_flash($publik === 'BUKA' ? 'success' : 'warning', $status_text);
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

