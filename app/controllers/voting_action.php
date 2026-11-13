<?php
/**
 * Controller Engine QR Code & Transaksi Pemungutan Suara (Voting & Portal QR Action)
 * Menangani polling layar QR bilik, klaim scan QR oleh perangkat, verifikasi NIM,
 * dan konfirmasi pemilihan Presiden BEM serta Ketua Umum BPM secara atomik.
 */

require_once BASE_PATH . '/models/voting_model.php';
require_once BASE_PATH . '/models/kandidat_model.php';
require_once BASE_PATH . '/models/bilik_model.php';
require_once BASE_PATH . '/models/user_model.php';

$pdo = get_db_connection();
$sub_action = $_POST['sub_action'] ?? $_GET['sub_action'] ?? '';

$qr_ttl = (int) env_val('QR_TOKEN_TTL_SECONDS', 8);
$session_ttl = (int) env_val('VOTING_SESSION_TTL_SECONDS', 180);
$max_nim_attempts = (int) env_val('MAX_NIM_ATTEMPTS', 3);

// Pastikan setiap browser pemilih memiliki browser_session_id unik di cookie/session
if (empty($_SESSION['voter_browser_session_id'])) {
    $_SESSION['voter_browser_session_id'] = 'bsess_' . bin2hex(random_bytes(16));
}
$browser_session_id = $_SESSION['voter_browser_session_id'];

/**
 * 1. Endpoint Polling Layar Portal QR Bilik (Real-Time / Low Latency)
 * Mengembalikan token QR terbaru (berotasi tiap 8 detik saat IDLE) atau status IN_USE / CLOSED.
 */
if ($sub_action === 'poll_portal_qr') {
    $nomor_bilik = (int) ($_GET['booth'] ?? 1);
    $force_rotate = isset($_GET['force']) && $_GET['force'] === '1';

    $global_portal = get_setting_value($pdo, 'status_portal_global', 'BUKA');
    $wajib_checkin = get_setting_value($pdo, 'wajib_checkin_panitia', '0');
    $bilik = rotate_or_get_bilik_token($pdo, $nomor_bilik, $qr_ttl, $force_rotate);

    if (!$bilik) {
        $all_fallback = get_all_biliks($pdo);
        if (!empty($all_fallback)) {
            $nomor_bilik = (int) $all_fallback[0]['nomor_bilik'];
            $bilik = rotate_or_get_bilik_token($pdo, $nomor_bilik, $qr_ttl, $force_rotate);
        }
        if (!$bilik) {
            json_response(false, 'Nomor bilik suara tidak ditemukan.', [], 404);
        }
    }

    $effective_status = ($global_portal !== 'BUKA') ? 'CLOSED' : $bilik['status_bilik'];
    $scan_url = route_url('bilik_pemilihan', [
        'booth' => (int) $bilik['nomor_bilik'],
        'token' => $bilik['current_token'],
    ]);

    $all_biliks = get_all_biliks($pdo);
    $now_ts = time();
    foreach ($all_biliks as &$b_item) {
        $b_item['remaining_lock_seconds'] = ($b_item['status_bilik'] === 'IN_USE' && !empty($b_item['locked_until']))
            ? max(0, strtotime($b_item['locked_until']) - $now_ts)
            : 0;
    }
    unset($b_item);

    json_response(true, 'Status bilik diperbarui.', [
        'bilik_id'               => (int) $bilik['id'],
        'nomor_bilik'            => (int) $bilik['nomor_bilik'],
        'nama_bilik'             => $bilik['nama_bilik'],
        'lokasi_bilik'           => $bilik['lokasi_bilik'],
        'status_bilik'           => $effective_status,
        'global_portal'          => $global_portal,
        'wajib_checkin'          => $wajib_checkin,
        'qr_token'               => $effective_status === 'IDLE' ? $bilik['current_token'] : null,
        'scan_url'               => $effective_status === 'IDLE' ? $scan_url : null,
        'qr_ttl_seconds'         => $qr_ttl,
        'remaining_qr_seconds'   => (int) $bilik['remaining_qr_seconds'],
        'remaining_lock_seconds' => (int) $bilik['remaining_lock_seconds'],
        'active_nim_masked'      => !empty($bilik['active_nim'])
            ? substr($bilik['active_nim'], 0, 3) . '***' . substr($bilik['active_nim'], -2)
            : null,
        'total_suara_bilik'      => (int) $bilik['total_suara_bilik'],
        'last_vote_at'           => $bilik['last_vote_at'],
        'all_biliks'             => $all_biliks,
    ]);
}

/**
 * 2. Endpoint Klaim Token QR Saat Perangkat Mahasiswa Membuka Link Hasil Scan
 * Merekam Device ID + Fingerprint + Session Browser dan langsung mengunci bilik (reset token seketika).
 */
if ($sub_action === 'claim_qr_scan') {
    verify_csrf_request(true);

    $nomor_bilik = (int) ($_POST['nomor_bilik'] ?? 0);
    $qr_token = trim((string) ($_POST['qr_token'] ?? ''));
    $client_device_token = preg_replace('/[^a-zA-Z0-9_\-]/', '', trim((string) ($_POST['device_id'] ?? '')));
    $fingerprint_hash = preg_replace('/[^a-zA-Z0-9_\-]/', '', trim((string) ($_POST['fingerprint_hash'] ?? 'unknown_fp')));

    if ($client_device_token === '') {
        $client_device_token = 'dev_' . substr(hash('sha256', $browser_session_id . '|' . $fingerprint_hash), 0, 24);
    }

    // Jika cookie browser menandakan perangkat ini sudah memilih, paksa pencocokan device_id lama
    if (!empty($_COOKIE['pemira_voted_device'])) {
        $cookie_dev = preg_replace('/[^a-zA-Z0-9_\-]/', '', $_COOKIE['pemira_voted_device']);
        if ($cookie_dev !== '') {
            $client_device_token = $cookie_dev;
        }
    }

    $result = claim_qr_token_for_device(
        $pdo,
        $nomor_bilik,
        $qr_token,
        $client_device_token,
        $fingerprint_hash,
        $browser_session_id,
        get_client_ip(),
        get_client_user_agent(),
        $session_ttl
    );

    if (!$result['status']) {
        json_response(false, $result['message'], [
            'kode_error' => $result['kode_error'] ?? 'CLAIM_FAILED',
        ]);
    }

    // Simpan sesi voting aktif di PHP session pemilih
    $_SESSION['active_voting_session'] = [
        'bilik_id'             => $result['bilik_id'],
        'nomor_bilik'          => $result['nomor_bilik'],
        'nama_bilik'           => $result['nama_bilik'],
        'voting_session_token' => $result['voting_session_token'],
        'device_id'            => $client_device_token,
        'fingerprint_hash'     => $fingerprint_hash,
    ];

    json_response(true, $result['message'], [
        'bilik_id'             => $result['bilik_id'],
        'nomor_bilik'          => $result['nomor_bilik'],
        'nama_bilik'           => $result['nama_bilik'],
        'voting_session_token' => $result['voting_session_token'],
        'device_id'            => $client_device_token,
        'session_ttl_seconds'  => $session_ttl,
    ]);
}

/**
 * 3. Endpoint Verifikasi NIM Pemilih di Dalam Bilik Suara
 */
if ($sub_action === 'verify_nim') {
    verify_csrf_request(true);

    $bilik_id = (int) ($_POST['bilik_id'] ?? 0);
    $voting_session_token = trim((string) ($_POST['voting_session_token'] ?? ''));
    $device_id = preg_replace('/[^a-zA-Z0-9_\-]/', '', trim((string) ($_POST['device_id'] ?? '')));
    $fingerprint_hash = preg_replace('/[^a-zA-Z0-9_\-]/', '', trim((string) ($_POST['fingerprint_hash'] ?? 'unknown_fp')));
    $nim = preg_replace('/[^a-zA-Z0-9\-\.]/', '', trim((string) ($_POST['nim'] ?? '')));

    if ($nim === '') {
        json_response(false, 'Silakan masukkan Nomor Induk Mahasiswa (NIM) Anda terlebih dahulu.');
    }

    $verify = verify_voter_nim_in_session(
        $pdo,
        $bilik_id,
        $voting_session_token,
        $device_id,
        $fingerprint_hash,
        $browser_session_id,
        $nim,
        get_client_ip(),
        get_client_user_agent(),
        $max_nim_attempts
    );

    if (!$verify['status']) {
        json_response(false, $verify['message'], [
            'kode_error'   => $verify['kode_error'] ?? 'VERIFY_FAILED',
            'session_dead' => $verify['session_dead'] ?? false,
        ]);
    }

    $_SESSION['active_voting_session']['verified_nim'] = $verify['mahasiswa']['nim'];
    $_SESSION['active_voting_session']['verified_nama'] = $verify['mahasiswa']['nama_lengkap'];
    $_SESSION['active_voting_session']['verified_kelas'] = $verify['mahasiswa']['nama_kelas'];
    $_SESSION['active_voting_session']['verified_jurusan'] = $verify['mahasiswa']['jurusan'];

    json_response(true, $verify['message'], [
        'mahasiswa' => $verify['mahasiswa'],
    ]);
}

/**
 * 4. Endpoint Konfirmasi Pilihan (Confirm Vote)
 * Saat kandidat diklik di UI belum direkam; baru direkam secara atomik saat tombol Confirm ditekan.
 */
if ($sub_action === 'confirm_vote') {
    verify_csrf_request(true);

    $bilik_id = (int) ($_POST['bilik_id'] ?? 0);
    $voting_session_token = trim((string) ($_POST['voting_session_token'] ?? ''));
    $device_id = preg_replace('/[^a-zA-Z0-9_\-]/', '', trim((string) ($_POST['device_id'] ?? '')));
    $fingerprint_hash = preg_replace('/[^a-zA-Z0-9_\-]/', '', trim((string) ($_POST['fingerprint_hash'] ?? 'unknown_fp')));
    $nim = preg_replace('/[^a-zA-Z0-9\-\.]/', '', trim((string) ($_POST['nim'] ?? '')));
    $kandidat_bem_id = (int) ($_POST['kandidat_bem_id'] ?? 0);
    $kandidat_bpm_id = (int) ($_POST['kandidat_bpm_id'] ?? 0);

    if ($kandidat_bem_id < 1 || $kandidat_bpm_id < 1) {
        json_response(false, 'Anda wajib memilih 1 Calon Presiden BEM dan 1 Calon Ketua Umum BPM sebelum mengonfirmasi suara.');
    }

    $vote_res = submit_atomic_vote(
        $pdo,
        $bilik_id,
        $voting_session_token,
        $device_id,
        $fingerprint_hash,
        $browser_session_id,
        $nim,
        $kandidat_bem_id,
        $kandidat_bpm_id,
        get_client_ip(),
        get_client_user_agent()
    );

    if (!$vote_res['status']) {
        json_response(false, $vote_res['message'], [
            'kode_error' => $vote_res['kode_error'] ?? 'VOTE_FAILED',
        ]);
    }

    // Tanam cookie penanda device sudah memilih (berlaku 30 hari) & bersihkan sesi bilik aktif
    setcookie('pemira_voted_device', $device_id, [
        'expires'  => time() + (86400 * 30),
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    unset($_SESSION['active_voting_session']);

    json_response(true, $vote_res['message']);
}

json_response(false, 'Aksi tidak dikenali.', [], 400);

