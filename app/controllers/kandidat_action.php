<?php
/**
 * Controller Manajemen Kandidat BEM & BPM serta Tim Sukses
 */

require_once BASE_PATH . '/models/kandidat_model.php';
require_once BASE_PATH . '/models/jurusan_model.php';

require_auth();
verify_csrf_request();

$pdo = get_db_connection();
$sub_action = $_POST['sub_action'] ?? '';
$active_tab = ($_POST['kategori'] ?? $_GET['kategori'] ?? 'BEM') === 'BPM' ? 'BPM' : 'BEM';

try {
    if ($sub_action === 'create' || $sub_action === 'update') {
        $id = (int) ($_POST['id'] ?? 0);
        $kategori = ($_POST['kategori'] ?? 'BEM') === 'BPM' ? 'BPM' : 'BEM';
        $nomor_urut = (int) ($_POST['nomor_urut'] ?? 1);
        $nama_calon = trim((string) ($_POST['nama_calon'] ?? ''));
        $nama_wakil = trim((string) ($_POST['nama_wakil'] ?? ''));
        $jurusan_id = (int) ($_POST['jurusan_id'] ?? 0);
        $semester = (int) ($_POST['semester'] ?? 0);
        $jurusan_kelas_raw = trim((string) ($_POST['jurusan_kelas'] ?? ''));

        $jurRow = $jurusan_id > 0 ? find_jurusan_by_id($pdo, $jurusan_id) : null;
        if ($jurRow) {
            $jurusan_id = (int) $jurRow['id'];
            $jurusan_kelas = $semester > 0
                ? ($jurRow['nama_jurusan'] . ' · Smt ' . $semester)
                : $jurRow['nama_jurusan'];
        } else {
            $jurusan_kelas = $jurusan_kelas_raw;
            $matchedJur = $jurusan_kelas !== '' ? find_jurusan_by_nama($pdo, $jurusan_kelas) : null;
            $jurusan_id = $matchedJur ? (int) $matchedJur['id'] : 0;
        }

        $visi = trim((string) ($_POST['visi'] ?? ''));
        $misi = trim((string) ($_POST['misi'] ?? ''));

        if ($nama_calon === '' || $jurusan_kelas === '' || $visi === '' || $misi === '' || $nomor_urut < 1) {
            set_flash('danger', 'Nomor urut, Nama Calon Utama, Jurusan, Visi, dan Misi wajib diisi dengan lengkap.');
            redirect(route_url('manajemen_kandidat', ['kategori' => $kategori]));
        }

        // Susun array anggota Tim Sukses dari input form dinamis
        $timses_nama = $_POST['timses_nama'] ?? [];
        $timses_nim = $_POST['timses_nim'] ?? [];
        $timses_jabatan = $_POST['timses_jabatan'] ?? [];
        $timses_list = [];

        if (is_array($timses_nama)) {
            foreach ($timses_nama as $idx => $nama_ts) {
                $nama_clean = trim((string) $nama_ts);
                if ($nama_clean === '') {
                    continue;
                }
                $timses_list[] = [
                    'nama_anggota'   => $nama_clean,
                    'nim_anggota'    => trim((string) ($timses_nim[$idx] ?? '')),
                    'jabatan_timses' => trim((string) ($timses_jabatan[$idx] ?? 'Anggota Tim Sukses')),
                ];
            }
        }

        // Proses upload foto kandidat jika ada berkas yang dipilih
        $existing_kandidat = ($sub_action === 'update' && $id > 0) ? find_kandidat_by_id($pdo, $id) : null;
        $foto_path = $existing_kandidat['foto_path'] ?? null;

        if (isset($_FILES['foto_kandidat']) && $_FILES['foto_kandidat']['error'] !== UPLOAD_ERR_NO_FILE) {
            $upload_res = upload_foto_kandidat($_FILES['foto_kandidat'], 'foto_kandidat');
            if (!$upload_res['status']) {
                set_flash('danger', $upload_res['message']);
                redirect(route_url('manajemen_kandidat', ['kategori' => $kategori]));
            }
            if ($upload_res['filename'] !== null) {
                if (!empty($foto_path)) {
                    delete_uploaded_file($foto_path);
                }
                $foto_path = $upload_res['filename'];
            }
        }

        $payload = [
            'kategori'      => $kategori,
            'jurusan_id'    => $jurusan_id > 0 ? $jurusan_id : null,
            'nomor_urut'    => $nomor_urut,
            'nama_calon'    => $nama_calon,
            'nama_wakil'    => $nama_wakil,
            'jurusan_kelas' => $jurusan_kelas,
            'foto_path'     => $foto_path,
            'visi'          => $visi,
            'misi'          => $misi,
        ];

        if ($sub_action === 'create') {
            create_kandidat($pdo, $payload, $timses_list);
            set_flash('success', "Kandidat {$kategori} Nomor Urut {$nomor_urut} ({$nama_calon}) berhasil ditambahkan.");
        } else {
            update_kandidat($pdo, $id, $payload, $timses_list);
            set_flash('success', "Data Kandidat {$kategori} Nomor Urut {$nomor_urut} ({$nama_calon}) berhasil diperbarui.");
        }

        redirect(route_url('manajemen_kandidat', ['kategori' => $kategori]));
    }

    if ($sub_action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        $existing = find_kandidat_by_id($pdo, $id);
        if ($existing) {
            if (!empty($existing['foto_path'])) {
                delete_uploaded_file($existing['foto_path']);
            }
            delete_kandidat($pdo, $id);
            set_flash('success', 'Data kandidat beserta daftar tim suksesnya berhasil dihapus.');
            redirect(route_url('manajemen_kandidat', ['kategori' => $existing['kategori']]));
        }
        set_flash('danger', 'Data kandidat tidak ditemukan.');
        redirect(route_url('manajemen_kandidat', ['kategori' => $active_tab]));
    }
} catch (PDOException $e) {
    if ((int) $e->errorInfo[1] === 1062) {
        set_flash('danger', "Nomor urut kandidat pada kategori {$active_tab} tersebut sudah digunakan. Gunakan nomor urut lain.");
    } else {
        error_log('Kandidat Action Error: ' . $e->getMessage());
        set_flash('danger', 'Terjadi kesalahan saat menyimpan data kandidat.');
    }
    redirect(route_url('manajemen_kandidat', ['kategori' => $active_tab]));
}

redirect(route_url('manajemen_kandidat', ['kategori' => $active_tab]));

