<?php
/**
 * Controller Manajemen Kelas & Mahasiswa / DPT (Termasuk Preview & Import Massal Excel/CSV/Paste)
 */

require_once BASE_PATH . '/models/mahasiswa_model.php';
require_once BASE_PATH . '/models/jurusan_model.php';

$pdo = get_db_connection();
$sub_action = $_POST['sub_action'] ?? $_GET['sub_action'] ?? '';

/**
 * Endpoint AJAX untuk memvalidasi & menampilkan tabel Review/Preview sebelum simpan import
 */
if ($sub_action === 'preview_import_json') {
    require_auth(true);
    verify_csrf_request(true);

    $raw_text = trim((string) ($_POST['raw_import_data'] ?? ''));

    // Jika mengunggah file CSV, baca isinya
    if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
        $file_content = file_get_contents($_FILES['csv_file']['tmp_name']);
        if ($file_content !== false) {
            $raw_text = trim($file_content);
        }
    }

    if ($raw_text === '') {
        json_response(false, 'Data impor masih kosong. Silakan unggah berkas CSV atau tempel (paste) baris NIM dan Nama dari Excel.');
    }

    $lines = preg_split('/\r\n|\r|\n/', $raw_text);
    $parsed_rows = [];

    foreach ($lines as $idx => $line) {
        $line = trim($line);
        if ($line === '') {
            continue;
        }

        // Deteksi pemisah otomatis: Tab (\t dari copy-paste Excel), Titik Koma (;), atau Koma (,)
        if (str_contains($line, "\t")) {
            $cols = explode("\t", $line);
        } elseif (str_contains($line, ';')) {
            $cols = str_getcsv($line, ';');
        } else {
            $cols = str_getcsv($line, ',');
        }

        $col0 = trim((string) ($cols[0] ?? ''));
        $col1 = trim((string) ($cols[1] ?? ''));

        // Lewati baris header jika baris pertama berisi tulisan "NIM" dan "NAMA"
        if ($idx === 0 && strtolower($col0) === 'nim' && str_contains(strtolower($col1), 'nama')) {
            continue;
        }

        // Jika pengguna hanya menempelkan format "NIM - Nama" dengan tanda hubung
        if ($col1 === '' && str_contains($col0, '-')) {
            $parts = explode('-', $col0, 2);
            $col0 = trim($parts[0]);
            $col1 = trim($parts[1] ?? '');
        }

        $parsed_rows[] = [
            'nim'          => $col0,
            'nama_lengkap' => $col1,
        ];
    }

    if (empty($parsed_rows)) {
        json_response(false, 'Tidak ditemukan baris data NIM dan Nama yang dapat diproses.');
    }

    $validation = validate_batch_mahasiswas($pdo, $parsed_rows);
    json_response(true, 'Data berhasil diproses untuk peninjauan (review).', $validation);
}

require_auth();
verify_csrf_request();

$kelas_id_redirect = (int) ($_POST['kelas_id'] ?? $_GET['kelas_id'] ?? 0);

try {
    // 1. CRUD Kelas (Nama Kelas, Jurusan Terhubung Master, Semester)
    if ($sub_action === 'create_kelas') {
        $nama_kelas = trim((string) ($_POST['nama_kelas'] ?? ''));
        $jurusan_id = (int) ($_POST['jurusan_id'] ?? 0);
        $jurusan_raw = trim((string) ($_POST['jurusan'] ?? ''));
        $semester = (int) ($_POST['semester'] ?? 1);

        $jurRow = $jurusan_id > 0 ? find_jurusan_by_id($pdo, $jurusan_id) : find_jurusan_by_nama($pdo, $jurusan_raw);
        $jurusan = $jurRow ? $jurRow['nama_jurusan'] : $jurusan_raw;
        $resolved_jurusan_id = $jurRow ? (int) $jurRow['id'] : null;

        if ($nama_kelas === '' || $jurusan === '' || $semester < 1 || $semester > 14) {
            set_flash('danger', 'Nama kelas, jurusan, dan semester (1-14) wajib diisi dengan benar.');
            redirect(route_url('manajemen_mahasiswa'));
        }

        $new_kelas_id = create_kelas($pdo, $nama_kelas, $jurusan, $semester, $resolved_jurusan_id);
        set_flash('success', "Kelas {$nama_kelas} ({$jurusan} - Semester {$semester}) berhasil dibuat. Silakan tambahkan atau impor data mahasiswa.");
        redirect(route_url('manajemen_mahasiswa', ['kelas_id' => $new_kelas_id]));
    }

    if ($sub_action === 'update_kelas') {
        $id = (int) ($_POST['id'] ?? 0);
        $nama_kelas = trim((string) ($_POST['nama_kelas'] ?? ''));
        $jurusan_id = (int) ($_POST['jurusan_id'] ?? 0);
        $jurusan_raw = trim((string) ($_POST['jurusan'] ?? ''));
        $semester = (int) ($_POST['semester'] ?? 1);

        $jurRow = $jurusan_id > 0 ? find_jurusan_by_id($pdo, $jurusan_id) : find_jurusan_by_nama($pdo, $jurusan_raw);
        $jurusan = $jurRow ? $jurRow['nama_jurusan'] : $jurusan_raw;
        $resolved_jurusan_id = $jurRow ? (int) $jurRow['id'] : null;

        if ($id < 1 || $nama_kelas === '' || $jurusan === '') {
            set_flash('danger', 'Data pembaruan kelas tidak lengkap.');
            redirect(route_url('manajemen_mahasiswa'));
        }

        update_kelas($pdo, $id, $nama_kelas, $jurusan, $semester, $resolved_jurusan_id);
        set_flash('success', "Data kelas {$nama_kelas} berhasil diperbarui.");
        redirect(route_url('manajemen_mahasiswa', ['kelas_id' => $id]));
    }

    if ($sub_action === 'delete_kelas') {
        $id = (int) ($_POST['id'] ?? 0);
        delete_kelas($pdo, $id);
        set_flash('success', 'Data kelas beserta daftar mahasiswa di dalamnya berhasil dihapus.');
        redirect(route_url('manajemen_mahasiswa'));
    }

    // 2. CRUD Mahasiswa Tunggal di dalam Kelas
    if ($sub_action === 'create_mahasiswa') {
        $kelas_id = (int) ($_POST['kelas_id'] ?? 0);
        $nim = preg_replace('/[^a-zA-Z0-9\-\.]/', '', trim((string) ($_POST['nim'] ?? '')));
        $nama_lengkap = trim((string) ($_POST['nama_lengkap'] ?? ''));
        $status_hadir = (int) ($_POST['status_hadir'] ?? 1);

        if ($kelas_id < 1 || $nim === '' || $nama_lengkap === '') {
            set_flash('danger', 'NIM dan Nama Lengkap mahasiswa wajib diisi.');
            redirect(route_url('manajemen_mahasiswa', ['kelas_id' => $kelas_id]));
        }

        create_mahasiswa($pdo, $kelas_id, $nim, $nama_lengkap, $status_hadir);
        set_flash('success', "Mahasiswa {$nama_lengkap} (NIM: {$nim}) berhasil ditambahkan ke kelas.");
        redirect(route_url('manajemen_mahasiswa', ['kelas_id' => $kelas_id]));
    }

    if ($sub_action === 'update_mahasiswa') {
        $id = (int) ($_POST['id'] ?? 0);
        $kelas_id = (int) ($_POST['kelas_id'] ?? 0);
        $nim = preg_replace('/[^a-zA-Z0-9\-\.]/', '', trim((string) ($_POST['nim'] ?? '')));
        $nama_lengkap = trim((string) ($_POST['nama_lengkap'] ?? ''));
        $status_hadir = (int) ($_POST['status_hadir'] ?? 1);

        if ($id < 1 || $kelas_id < 1 || $nim === '' || $nama_lengkap === '') {
            set_flash('danger', 'Data pembaruan mahasiswa tidak valid.');
            redirect(route_url('manajemen_mahasiswa', ['kelas_id' => $kelas_id]));
        }

        update_mahasiswa($pdo, $id, $kelas_id, $nim, $nama_lengkap, $status_hadir);
        set_flash('success', "Data mahasiswa {$nama_lengkap} (NIM: {$nim}) berhasil diperbarui.");
        redirect(route_url('manajemen_mahasiswa', ['kelas_id' => $kelas_id]));
    }

    if ($sub_action === 'delete_mahasiswa') {
        $id = (int) ($_POST['id'] ?? 0);
        $kelas_id = (int) ($_POST['kelas_id'] ?? 0);
        delete_mahasiswa($pdo, $id);
        set_flash('success', 'Data mahasiswa berhasil dihapus dari kelas.');
        redirect(route_url('manajemen_mahasiswa', $kelas_id > 0 ? ['kelas_id' => $kelas_id] : []));
    }

    if ($sub_action === 'toggle_hadir') {
        $id = (int) ($_POST['id'] ?? 0);
        $kelas_id = (int) ($_POST['kelas_id'] ?? 0);
        $status_hadir = (int) ($_POST['status_hadir'] ?? 1);
        toggle_hadir_mahasiswa($pdo, $id, $status_hadir);
        set_flash('success', 'Status kehadiran / aktivasi hak pilih mahasiswa berhasil diperbarui.');
        redirect(route_url('manajemen_mahasiswa', $kelas_id > 0 ? ['kelas_id' => $kelas_id] : []));
    }

    if ($sub_action === 'set_all_hadir_kelas') {
        $kelas_id = (int) ($_POST['kelas_id'] ?? 0);
        $status_hadir = (int) ($_POST['status_hadir'] ?? 1);
        set_all_hadir_in_kelas($pdo, $kelas_id, $status_hadir);
        set_flash('success', 'Status kehadiran seluruh mahasiswa pada kelas ini berhasil diperbarui.');
        redirect(route_url('manajemen_mahasiswa', ['kelas_id' => $kelas_id]));
    }

    // 3. Simpan Hasil Import Massal Setelah Direview oleh Admin/Panitia
    if ($sub_action === 'confirm_import_batch') {
        $kelas_id = (int) ($_POST['kelas_id'] ?? 0);
        $nims = $_POST['import_nim'] ?? [];
        $namas = $_POST['import_nama'] ?? [];

        if ($kelas_id < 1 || !is_array($nims) || empty($nims)) {
            set_flash('danger', 'Tidak ada data mahasiswa valid yang dikirim untuk diimpor.');
            redirect(route_url('manajemen_mahasiswa', ['kelas_id' => $kelas_id]));
        }

        $rows_to_import = [];
        foreach ($nims as $idx => $nim_val) {
            $rows_to_import[] = [
                'nim'          => trim((string) $nim_val),
                'nama_lengkap' => trim((string) ($namas[$idx] ?? '')),
            ];
        }

        $result = import_batch_mahasiswas($pdo, $kelas_id, $rows_to_import);
        set_flash(
            'success',
            "Import massal berhasil! Sebanyak {$result['inserted']} mahasiswa ditambahkan ke dalam kelas"
            . ($result['skipped'] > 0 ? " ({$result['skipped']} baris duplikat/tidak valid dilewati)." : '.')
        );
        redirect(route_url('manajemen_mahasiswa', ['kelas_id' => $kelas_id]));
    }
} catch (PDOException $e) {
    if ((int) $e->errorInfo[1] === 1062) {
        set_flash('danger', 'Data gagal disimpan karena NIM atau kombinasi Nama Kelas/Jurusan/Semester sudah terdaftar di sistem.');
    } else {
        error_log('Mahasiswa Action Error: ' . $e->getMessage());
        set_flash('danger', 'Terjadi kesalahan basis data saat menyimpan data kelas/mahasiswa.');
    }
    redirect(route_url('manajemen_mahasiswa', $kelas_id_redirect > 0 ? ['kelas_id' => $kelas_id_redirect] : []));
}

redirect(route_url('manajemen_mahasiswa', $kelas_id_redirect > 0 ? ['kelas_id' => $kelas_id_redirect] : []));

