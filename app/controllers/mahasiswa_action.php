<?php
/**
 * Controller Manajemen Kelas & Mahasiswa / DPT (Termasuk Preview & Import Massal Excel/CSV/Paste)
 */

require_once BASE_PATH . '/models/mahasiswa_model.php';
require_once BASE_PATH . '/models/jurusan_model.php';
require_once BASE_PATH . '/models/log_model.php';

$is_ajax = is_ajax_request();
$pdo = get_db_connection();
$sub_action = $_POST['sub_action'] ?? $_GET['sub_action'] ?? '';

/**
 * Endpoint AJAX untuk memvalidasi & menampilkan tabel Review/Preview sebelum simpan import
 */
if ($sub_action === 'preview_import_json') {
    require_role(['superadmin', 'kprm'], true);
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        json_response(false, 'Metode tidak diizinkan.', [], 405);
    }
    verify_csrf_request(true);

    $raw_text = trim((string) ($_POST['raw_import_data'] ?? ''));

    // Jika mengunggah file CSV, validasi ukuran (maks 2MB), ekstensi, dan isi teks murni
    if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] !== UPLOAD_ERR_NO_FILE) {
        if ($_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
            json_response(false, 'Berkas CSV gagal diunggah.', [], 400);
        }
        if ((int) $_FILES['csv_file']['size'] > (2 * 1024 * 1024)) {
            json_response(false, 'Ukuran berkas CSV maksimal 2 MB.', [], 400);
        }
        $ext = strtolower(pathinfo((string) ($_FILES['csv_file']['name'] ?? ''), PATHINFO_EXTENSION));
        if (!in_array($ext, ['csv', 'txt'], true)) {
            json_response(false, 'Format berkas impor harus berupa CSV atau TXT.', [], 400);
        }
        $file_content = file_get_contents($_FILES['csv_file']['tmp_name']);
        if ($file_content === false || str_contains($file_content, "\0")) {
            json_response(false, 'Berkas CSV tidak valid atau mengandung data biner.', [], 400);
        }
        $raw_text = trim($file_content);
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
        if (count($parsed_rows) >= 2000) {
            json_response(false, 'Maksimal 2.000 baris mahasiswa per satu kali impor.', [], 400);
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

        $col0 = mb_substr($col0, 0, 30);
        $col1 = mb_substr($col1, 0, 150);

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

require_role(['superadmin', 'kprm'], $is_ajax);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(route_url('manajemen_mahasiswa'));
}
verify_csrf_request($is_ajax);

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
        record_admin_audit_log(
            $pdo,
            'KELAS_CREATE',
            'Penambahan Kelas DPT',
            "Operator {$_SESSION['username']} menambahkan kelas {$nama_kelas} ({$jurusan} - Smt {$semester})."
        );
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
        record_admin_audit_log(
            $pdo,
            'KELAS_UPDATE',
            'Perubahan Data Kelas DPT',
            "Operator {$_SESSION['username']} memperbarui kelas #{$id} menjadi {$nama_kelas} ({$jurusan} - Smt {$semester})."
        );
        set_flash('success', "Data kelas {$nama_kelas} berhasil diperbarui.");
        redirect(route_url('manajemen_mahasiswa', ['kelas_id' => $id]));
    }

    if ($sub_action === 'delete_kelas') {
        $id = (int) ($_POST['id'] ?? 0);
        $stmt_voted = $pdo->prepare('SELECT COUNT(*) FROM mahasiswas WHERE kelas_id = :id AND sudah_memilih = 1');
        $stmt_voted->execute([':id' => $id]);
        $voted_count = (int) $stmt_voted->fetchColumn();
        if ($voted_count > 0) {
            set_flash('danger', "Kelas ini tidak dapat dihapus karena terdapat {$voted_count} mahasiswa di dalamnya yang sudah memberikan suara.");
            redirect(route_url('manajemen_mahasiswa', ['kelas_id' => $id]));
        }

        delete_kelas($pdo, $id);
        record_admin_audit_log(
            $pdo,
            'KELAS_DELETE',
            'Penghapusan Kelas DPT',
            "Operator {$_SESSION['username']} menghapus kelas #{$id} beserta mahasiswa yang belum memilih di dalamnya."
        );
        set_flash('success', 'Data kelas beserta daftar mahasiswa di dalamnya berhasil dihapus.');
        redirect(route_url('manajemen_mahasiswa'));
    }

    // 2. CRUD Mahasiswa Tunggal di dalam Kelas
    if ($sub_action === 'create_mahasiswa') {
        $kelas_id = (int) ($_POST['kelas_id'] ?? 0);
        $nim = preg_replace('/[^a-zA-Z0-9\-\.]/', '', trim((string) ($_POST['nim'] ?? '')));
        $nama_lengkap = trim((string) ($_POST['nama_lengkap'] ?? ''));
        $status_hadir = (int) ($_POST['status_hadir'] ?? 1);

        if ($kelas_id < 1 || $nim === '' || $nama_lengkap === '' || strlen($nim) > 30 || mb_strlen($nama_lengkap) > 150) {
            set_flash('danger', 'NIM (maks 30 karakter) dan Nama Lengkap (maks 150 karakter) mahasiswa wajib diisi dengan valid.');
            redirect(route_url('manajemen_mahasiswa', ['kelas_id' => $kelas_id]));
        }

        create_mahasiswa($pdo, $kelas_id, $nim, $nama_lengkap, $status_hadir);
        record_admin_audit_log(
            $pdo,
            'MAHASISWA_CREATE',
            'Penambahan Mahasiswa DPT',
            "Operator {$_SESSION['username']} menambahkan mahasiswa {$nama_lengkap} (NIM {$nim}) ke kelas #{$kelas_id}.",
            'NORMAL',
            null,
            $nim
        );
        set_flash('success', "Mahasiswa {$nama_lengkap} (NIM: {$nim}) berhasil ditambahkan ke kelas.");
        redirect(route_url('manajemen_mahasiswa', ['kelas_id' => $kelas_id]));
    }

    if ($sub_action === 'update_mahasiswa') {
        $id = (int) ($_POST['id'] ?? 0);
        $kelas_id = (int) ($_POST['kelas_id'] ?? 0);
        $nim = preg_replace('/[^a-zA-Z0-9\-\.]/', '', trim((string) ($_POST['nim'] ?? '')));
        $nama_lengkap = trim((string) ($_POST['nama_lengkap'] ?? ''));
        $status_hadir = (int) ($_POST['status_hadir'] ?? 1);

        if ($id < 1 || $kelas_id < 1 || $nim === '' || $nama_lengkap === '' || strlen($nim) > 30 || mb_strlen($nama_lengkap) > 150) {
            set_flash('danger', 'Data pembaruan mahasiswa tidak valid.');
            redirect(route_url('manajemen_mahasiswa', ['kelas_id' => $kelas_id]));
        }

        $existing_mhs = find_mahasiswa_by_id($pdo, $id);
        if (!$existing_mhs) {
            set_flash('danger', 'Data mahasiswa tidak ditemukan.');
            redirect(route_url('manajemen_mahasiswa', ['kelas_id' => $kelas_id]));
        }
        if ((int) $existing_mhs['sudah_memilih'] === 1 && strcasecmp((string) $existing_mhs['nim'], $nim) !== 0) {
            set_flash('danger', 'NIM mahasiswa yang sudah memberikan suara tidak boleh diubah demi menjaga integritas jejak audit.');
            redirect(route_url('manajemen_mahasiswa', ['kelas_id' => $kelas_id]));
        }

        update_mahasiswa($pdo, $id, $kelas_id, $nim, $nama_lengkap, $status_hadir);
        record_admin_audit_log(
            $pdo,
            'MAHASISWA_UPDATE',
            'Perubahan Data Mahasiswa DPT',
            "Operator {$_SESSION['username']} memperbarui data mahasiswa #{$id} ({$nama_lengkap} / NIM {$nim}).",
            'NORMAL',
            null,
            $nim
        );
        set_flash('success', "Data mahasiswa {$nama_lengkap} (NIM: {$nim}) berhasil diperbarui.");
        redirect(route_url('manajemen_mahasiswa', ['kelas_id' => $kelas_id]));
    }

    if ($sub_action === 'delete_mahasiswa') {
        $id = (int) ($_POST['id'] ?? 0);
        $kelas_id = (int) ($_POST['kelas_id'] ?? 0);
        $existing_mhs = $id > 0 ? find_mahasiswa_by_id($pdo, $id) : null;
        if ($existing_mhs && (int) $existing_mhs['sudah_memilih'] === 1) {
            set_flash('danger', "Mahasiswa {$existing_mhs['nama_lengkap']} (NIM {$existing_mhs['nim']}) tidak dapat dihapus karena sudah memberikan suara.");
            redirect(route_url('manajemen_mahasiswa', $kelas_id > 0 ? ['kelas_id' => $kelas_id] : []));
        }

        delete_mahasiswa($pdo, $id);
        record_admin_audit_log(
            $pdo,
            'MAHASISWA_DELETE',
            'Penghapusan Mahasiswa DPT',
            "Operator {$_SESSION['username']} menghapus mahasiswa #{$id}" . ($existing_mhs ? " ({$existing_mhs['nim']} - {$existing_mhs['nama_lengkap']})" : '') . '.',
            'NORMAL',
            null,
            $existing_mhs['nim'] ?? null
        );
        set_flash('success', 'Data mahasiswa berhasil dihapus dari kelas.');
        redirect(route_url('manajemen_mahasiswa', $kelas_id > 0 ? ['kelas_id' => $kelas_id] : []));
    }

    if ($sub_action === 'toggle_hadir') {
        $id = (int) ($_POST['id'] ?? 0);
        $kelas_id = (int) ($_POST['kelas_id'] ?? 0);
        $status_hadir = (int) ($_POST['status_hadir'] ?? 1);
        toggle_hadir_mahasiswa($pdo, $id, $status_hadir);
        record_admin_audit_log(
            $pdo,
            'MAHASISWA_TOGGLE_HADIR',
            'Aktivasi / Check-In Mahasiswa DPT',
            "Operator {$_SESSION['username']} mengatur status check-in mahasiswa #{$id} menjadi " . ($status_hadir === 1 ? 'HADIR' : 'BELUM HADIR') . '.'
        );
        set_flash('success', 'Status kehadiran / aktivasi hak pilih mahasiswa berhasil diperbarui.');
        redirect(route_url('manajemen_mahasiswa', $kelas_id > 0 ? ['kelas_id' => $kelas_id] : []));
    }

    if ($sub_action === 'set_all_hadir_kelas') {
        $kelas_id = (int) ($_POST['kelas_id'] ?? 0);
        $status_hadir = (int) ($_POST['status_hadir'] ?? 1);
        set_all_hadir_in_kelas($pdo, $kelas_id, $status_hadir);
        record_admin_audit_log(
            $pdo,
            'KELAS_ALL_HADIR',
            'Check-In Massal Satu Kelas',
            "Operator {$_SESSION['username']} mengatur status hadir massal kelas #{$kelas_id} menjadi " . ($status_hadir === 1 ? 'HADIR' : 'BELUM HADIR') . '.'
        );
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

        if (count($nims) > 2000) {
            set_flash('danger', 'Maksimal 2.000 baris data per satu kali impor.');
            redirect(route_url('manajemen_mahasiswa', ['kelas_id' => $kelas_id]));
        }

        $rows_to_import = [];
        foreach ($nims as $idx => $nim_val) {
            $rows_to_import[] = [
                'nim'          => mb_substr(trim((string) $nim_val), 0, 30),
                'nama_lengkap' => mb_substr(trim((string) ($namas[$idx] ?? '')), 0, 150),
            ];
        }

        $result = import_batch_mahasiswas($pdo, $kelas_id, $rows_to_import);
        record_admin_audit_log(
            $pdo,
            'MAHASISWA_IMPORT_BATCH',
            'Impor Massal Mahasiswa DPT',
            "Operator {$_SESSION['username']} mengimpor {$result['inserted']} mahasiswa ke kelas #{$kelas_id} (dilewati: {$result['skipped']})."
        );
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

