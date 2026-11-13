<?php
/**
 * Controller Manajemen Master Jurusan / Program Studi
 */

require_once BASE_PATH . '/models/jurusan_model.php';

require_auth();
verify_csrf_request();

$pdo = get_db_connection();
$sub_action = $_POST['sub_action'] ?? '';

try {
    if ($sub_action === 'create_jurusan') {
        $kode = strtoupper(preg_replace('/[^a-zA-Z0-9\-]/', '', trim((string) ($_POST['kode_jurusan'] ?? ''))));
        $nama = trim((string) ($_POST['nama_jurusan'] ?? ''));
        $jenjang = strtoupper(trim((string) ($_POST['jenjang'] ?? 'S1')));

        if ($kode === '' || $nama === '') {
            set_flash('danger', 'Kode jurusan dan nama jurusan wajib diisi.');
            redirect(route_url('manajemen_jurusan'));
        }

        create_jurusan($pdo, $kode, $nama, $jenjang);
        set_flash('success', "Jurusan {$nama} ({$kode}) berhasil ditambahkan.");
        redirect(route_url('manajemen_jurusan'));
    }

    if ($sub_action === 'update_jurusan') {
        $id = (int) ($_POST['id'] ?? 0);
        $kode = strtoupper(preg_replace('/[^a-zA-Z0-9\-]/', '', trim((string) ($_POST['kode_jurusan'] ?? ''))));
        $nama = trim((string) ($_POST['nama_jurusan'] ?? ''));
        $jenjang = strtoupper(trim((string) ($_POST['jenjang'] ?? 'S1')));

        if ($id < 1 || $kode === '' || $nama === '') {
            set_flash('danger', 'Data pembaruan jurusan tidak valid.');
            redirect(route_url('manajemen_jurusan'));
        }

        update_jurusan($pdo, $id, $kode, $nama, $jenjang);
        set_flash('success', "Data jurusan {$nama} ({$kode}) berhasil diperbarui dan disinkronkan.");
        redirect(route_url('manajemen_jurusan'));
    }

    if ($sub_action === 'delete_jurusan') {
        $id = (int) ($_POST['id'] ?? 0);
        $res = delete_jurusan($pdo, $id);
        set_flash($res['success'] ? 'success' : 'danger', $res['message']);
        redirect(route_url('manajemen_jurusan'));
    }
} catch (PDOException $e) {
    if ((int) ($e->errorInfo[1] ?? 0) === 1062) {
        set_flash('danger', 'Kode jurusan atau nama jurusan tersebut sudah terdaftar.');
    } else {
        error_log('Jurusan Action Error: ' . $e->getMessage());
        set_flash('danger', 'Terjadi kesalahan basis data saat memproses jurusan.');
    }
    redirect(route_url('manajemen_jurusan'));
}

redirect(route_url('manajemen_jurusan'));

