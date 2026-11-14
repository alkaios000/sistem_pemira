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
require_once BASE_PATH . '/models/log_model.php';

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
 * 1A. Endpoint Polling Layar Portal QR Bilik (Eksklusif Superadmin, KPRM, dan Banwasra)
 * Mengembalikan token QR bilik terpilih dan daftar status bilik yang disanitasi tanpa secret token bilik lain.
 */
if ($sub_action === 'poll_portal_qr') {
    require_role(['superadmin', 'kprm', 'banwasra'], true);

    $nomor_bilik = (int) ($_GET['booth'] ?? 1);

    $global_portal = get_setting_value($pdo, 'status_portal_global', 'BUKA');
    $wajib_checkin = get_setting_value($pdo, 'wajib_checkin_panitia', '0');
    $bilik = rotate_or_get_bilik_token($pdo, $nomor_bilik, $qr_ttl, false);

    if (!$bilik) {
        $all_fallback = get_all_biliks($pdo);
        if (!empty($all_fallback)) {
            $nomor_bilik = (int) $all_fallback[0]['nomor_bilik'];
            $bilik = rotate_or_get_bilik_token($pdo, $nomor_bilik, $qr_ttl, false);
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

    $all_biliks = get_all_biliks_sanitized($pdo, can_view_logs(), is_banwasra());

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
        'remaining_lock_seconds' => ($effective_status === 'IN_USE' && !empty($bilik['locked_until']))
            ? max(0, strtotime($bilik['locked_until']) - time())
            : 0,
        'active_nim_masked'      => mask_nim($bilik['active_nim'] ?? null),
        'total_suara_bilik'      => (int) $bilik['total_suara_bilik'],
        'last_vote_at'           => $bilik['last_vote_at'],
        'all_biliks'             => $all_biliks,
    ]);
}

/**
 * 1A-2. Endpoint Polling Khusus Layar Kiosk Per Bilik (Terkunci ke 1 Bilik via kiosk_token atau Pratinjau Staf)
 * Tidak membocorkan daftar bilik lain, NIM tersamarkan, jumlah suara, maupun kunci kiosk.
 */
if ($sub_action === 'poll_kiosk_qr') {
    header('Cache-Control: no-store, no-cache, must-revalidate, private');
    header('Referrer-Policy: no-referrer');

    $nomor_bilik = (int) ($_GET['booth'] ?? 0);
    $bilik = $nomor_bilik >= 1 ? find_bilik_by_nomor($pdo, $nomor_bilik) : null;
    if (!$bilik) {
        json_response(false, 'Nomor bilik suara tidak ditemukan.', ['revoked' => true], 404);
    }

    $hasKioskGrant = verify_bilik_kiosk_session($pdo, $bilik);
    $hasStaffPreview = is_authenticated() && in_array(current_user_role(), ['superadmin', 'kprm', 'banwasra'], true);

    if (!$hasKioskGrant && !$hasStaffPreview) {
        unset($_SESSION['kiosk_booth_access'][(int) $bilik['id']]);
        json_response(false, 'Sesi layar bilik tidak sah atau kunci akses telah diputar oleh panitia.', ['revoked' => true], 403);
    }

    $global_portal = get_setting_value($pdo, 'status_portal_global', 'BUKA');
    $bilik = rotate_or_get_bilik_token($pdo, $nomor_bilik, $qr_ttl, false);
    $effective_status = ($global_portal !== 'BUKA') ? 'CLOSED' : $bilik['status_bilik'];
    $scan_url = route_url('bilik_pemilihan', [
        'booth' => (int) $bilik['nomor_bilik'],
        'token' => $bilik['current_token'],
    ]);

    json_response(true, 'Status bilik diperbarui.', [
        'nomor_bilik'            => (int) $bilik['nomor_bilik'],
        'nama_bilik'             => $bilik['nama_bilik'],
        'lokasi_bilik'           => $bilik['lokasi_bilik'],
        'status_bilik'           => $effective_status,
        'global_portal'          => $global_portal,
        'qr_token'               => $effective_status === 'IDLE' ? $bilik['current_token'] : null,
        'scan_url'               => $effective_status === 'IDLE' ? $scan_url : null,
        'qr_ttl_seconds'         => $qr_ttl,
        'remaining_qr_seconds'   => (int) $bilik['remaining_qr_seconds'],
        'remaining_lock_seconds' => ($effective_status === 'IN_USE' && !empty($bilik['locked_until']))
            ? max(0, strtotime($bilik['locked_until']) - time())
            : 0,
    ]);
}

/**
 * 1B. Rotasi Paksa Token QR Bilik Secara Manual via POST + CSRF (Hanya Superadmin & KPRM)
 */
if ($sub_action === 'force_rotate_qr') {
    require_role(['superadmin', 'kprm'], true);
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        json_response(false, 'Metode tidak diizinkan.', [], 405);
    }
    verify_csrf_request(true);

    $nomor_bilik = (int) ($_POST['booth'] ?? 0);
    if ($nomor_bilik < 1) {
        json_response(false, 'Nomor bilik tidak valid.', [], 400);
    }

    $bilik = rotate_or_get_bilik_token($pdo, $nomor_bilik, $qr_ttl, true);
    if (!$bilik) {
        json_response(false, 'Nomor bilik suara tidak ditemukan.', [], 404);
    }

    $global_portal = get_setting_value($pdo, 'status_portal_global', 'BUKA');
    $wajib_checkin = get_setting_value($pdo, 'wajib_checkin_panitia', '0');
    $effective_status = ($global_portal !== 'BUKA') ? 'CLOSED' : $bilik['status_bilik'];
    $scan_url = route_url('bilik_pemilihan', [
        'booth' => (int) $bilik['nomor_bilik'],
        'token' => $bilik['current_token'],
    ]);

    record_admin_audit_log(
        $pdo,
        'QR_FORCE_ROTATE',
        'Rotasi Paksa Token QR Bilik',
        "Operator {$_SESSION['username']} (" . current_user_role() . ") merotasi paksa token QR pada {$bilik['nama_bilik']}.",
        'NORMAL',
        (int) $bilik['id']
    );

    json_response(true, 'Token QR bilik berhasil dirotasi secara instan.', [
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
        'remaining_lock_seconds' => ($effective_status === 'IN_USE' && !empty($bilik['locked_until']))
            ? max(0, strtotime($bilik['locked_until']) - time())
            : 0,
        'active_nim_masked'      => mask_nim($bilik['active_nim'] ?? null),
        'total_suara_bilik'      => (int) $bilik['total_suara_bilik'],
        'last_vote_at'           => $bilik['last_vote_at'],
        'all_biliks'             => get_all_biliks_sanitized($pdo, can_view_logs(), is_banwasra()),
    ]);
}

/**
 * 2. Endpoint Klaim Token QR Saat Perangkat Mahasiswa Membuka Link Hasil Scan
 * Merekam Device ID + Fingerprint + Session Browser dan langsung mengunci bilik (reset token seketika).
 */
if ($sub_action === 'claim_qr_scan') {
    verify_csrf_request(true);

    $ip = get_client_ip();
    $claim_limit = check_rate_limit($pdo, 'voting_claim_fail', $ip, 120);
    if ($claim_limit['blocked']) {
        json_response(false, "Terlalu banyak percobaan pindai QR gagal dari jaringan ini. Tunggu {$claim_limit['remaining_seconds']} detik.", [
            'kode_error' => 'RATE_LIMITED',
        ], 429);
    }

    $nomor_bilik = (int) ($_POST['nomor_bilik'] ?? 0);
    $qr_token = trim((string) ($_POST['qr_token'] ?? ''));
    $client_device_token = preg_replace('/[^a-zA-Z0-9_\-]/', '', trim((string) ($_POST['device_id'] ?? '')));
    $fingerprint_hash = preg_replace('/[^a-zA-Z0-9_\-]/', '', trim((string) ($_POST['fingerprint_hash'] ?? 'unknown_fp')));

    $restored_existing = restore_active_voting_session($pdo);
    $verified_cookie_device = get_verified_device_cookie();
    $can_resume_owned_booth = false;

    if ($verified_cookie_device !== null) {
        $client_device_token = $verified_cookie_device;
        $can_resume_owned_booth = true;
    } elseif ($restored_existing && !empty($restored_existing['device_id'])) {
        $client_device_token = (string) $restored_existing['device_id'];
        $can_resume_owned_booth = true;
    }

    if ($client_device_token === '') {
        $client_device_token = 'dev_' . substr(hash('sha256', $browser_session_id . '|' . $fingerprint_hash), 0, 24);
    }

    $result = claim_qr_token_for_device(
        $pdo,
        $nomor_bilik,
        $qr_token,
        $client_device_token,
        $fingerprint_hash,
        $browser_session_id,
        $ip,
        get_client_user_agent(),
        $session_ttl,
        $can_resume_owned_booth
    );

    if (!$result['status']) {
        record_rate_limit_hit($pdo, 'voting_claim_fail', $ip, 25, 120, 120);
        json_response(false, $result['message'], [
            'kode_error'         => $result['kode_error'] ?? 'CLAIM_FAILED',
            'active_nomor_bilik' => $result['active_nomor_bilik'] ?? null,
        ]);
    }

    clear_rate_limit($pdo, 'voting_claim_fail', $ip);

    $active_mhs = $result['active_mahasiswa'] ?? null;

    // Simpan sesi voting aktif di PHP session & cookie persisten terproteksi HMAC (tahan restart browser)
    $_SESSION['active_voting_session'] = [
        'bilik_id'             => $result['bilik_id'],
        'nomor_bilik'          => $result['nomor_bilik'],
        'nama_bilik'           => $result['nama_bilik'],
        'voting_session_token' => $result['voting_session_token'],
        'device_id'            => $client_device_token,
        'device_session_id'    => $result['device_session_id'] ?? null,
        'fingerprint_hash'     => $fingerprint_hash,
        'verified_nim'         => $active_mhs['nim'] ?? null,
        'verified_nama'        => $active_mhs['nama_lengkap'] ?? null,
        'verified_kelas'       => $active_mhs['nama_kelas'] ?? null,
        'verified_jurusan'     => $active_mhs['jurusan'] ?? null,
    ];

    issue_device_identity_cookies($client_device_token);
    issue_active_voting_resume_cookie(
        (int) $result['bilik_id'],
        (string) $result['voting_session_token'],
        $client_device_token,
        $session_ttl
    );

    json_response(true, $result['message'], [
        'bilik_id'             => $result['bilik_id'],
        'nomor_bilik'          => $result['nomor_bilik'],
        'nama_bilik'           => $result['nama_bilik'],
        'voting_session_token' => $result['voting_session_token'],
        'device_id'            => $client_device_token,
        'session_ttl_seconds'  => $session_ttl,
        'next_step'            => $active_mhs ? 3 : 2,
        'verified_mahasiswa'   => $active_mhs,
    ]);
}

/**
 * 3. Endpoint Verifikasi NIM Pemilih di Dalam Bilik Suara
 */
if ($sub_action === 'verify_nim') {
    verify_csrf_request(true);

    $ip = get_client_ip();
    $ua = get_client_user_agent();
    $nim_limit = check_rate_limit($pdo, 'voting_nim_fail', $ip, 120);
    if ($nim_limit['blocked']) {
        json_response(false, "Terlalu banyak kegagalan verifikasi NIM. Tunggu {$nim_limit['remaining_seconds']} detik.", [
            'kode_error' => 'RATE_LIMITED',
        ], 429);
    }

    $bilik_id = (int) ($_POST['bilik_id'] ?? 0);
    $voting_session_token = trim((string) ($_POST['voting_session_token'] ?? ''));
    $device_id = preg_replace('/[^a-zA-Z0-9_\-]/', '', trim((string) ($_POST['device_id'] ?? '')));
    $fingerprint_hash = preg_replace('/[^a-zA-Z0-9_\-]/', '', trim((string) ($_POST['fingerprint_hash'] ?? 'unknown_fp')));
    $nim = preg_replace('/[^a-zA-Z0-9\-\.]/', '', trim((string) ($_POST['nim'] ?? '')));

    if ($nim === '') {
        json_response(false, 'Silakan masukkan Nomor Induk Mahasiswa (NIM) Anda terlebih dahulu.');
    }

    // Validasi / pulihkan ikatan sesi server-side pemilik scan QR
    $sess = restore_active_voting_session($pdo);
    if (
        !is_array($sess)
        || (int) ($sess['bilik_id'] ?? 0) !== $bilik_id
        || !hash_equals((string) ($sess['voting_session_token'] ?? ''), $voting_session_token)
        || !hash_equals((string) ($sess['device_id'] ?? ''), $device_id)
    ) {
        record_audit_log($pdo, [
            'bilik_id'           => $bilik_id ?: null,
            'nim'                => $nim,
            'device_id'          => $device_id,
            'fingerprint_hash'   => $fingerprint_hash,
            'browser_session_id' => $browser_session_id,
            'ip_address'         => $ip,
            'user_agent'         => $ua,
            'status_log'         => 'FRAUD',
            'kode_event'         => 'SESSION_HIJACK_MISMATCH',
            'judul_event'        => 'Ketidakcocokan Session Browser Server-Side Saat Verifikasi NIM',
            'keterangan'         => "Klien IP {$ip} / device {$device_id} mencoba verifikasi NIM {$nim} tanpa session browser pemilik scan QR.",
        ]);
        json_response(false, 'Sesi browser tidak cocok dengan perangkat pemindai QR. Silakan pindai ulang QR Code.', [
            'kode_error'   => 'SESSION_BROWSER_MISMATCH',
            'session_dead' => true,
        ], 403);
    }

    $verify = verify_voter_nim_in_session(
        $pdo,
        $bilik_id,
        $voting_session_token,
        $device_id,
        $fingerprint_hash,
        $browser_session_id,
        $nim,
        $ip,
        $ua,
        $max_nim_attempts
    );

    if (!$verify['status']) {
        record_rate_limit_hit($pdo, 'voting_nim_fail', $ip, 20, 120, 120);
        if (!empty($verify['session_dead'])) {
            unset($_SESSION['active_voting_session']);
            clear_active_voting_resume_cookie();
        }
        json_response(false, $verify['message'], [
            'kode_error'   => $verify['kode_error'] ?? 'VERIFY_FAILED',
            'session_dead' => $verify['session_dead'] ?? false,
        ]);
    }

    clear_rate_limit($pdo, 'voting_nim_fail', $ip);
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

    $ip = get_client_ip();
    $ua = get_client_user_agent();
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

    $sess = restore_active_voting_session($pdo);
    if (
        !is_array($sess)
        || (int) ($sess['bilik_id'] ?? 0) !== $bilik_id
        || !hash_equals((string) ($sess['voting_session_token'] ?? ''), $voting_session_token)
        || !hash_equals((string) ($sess['device_id'] ?? ''), $device_id)
        || empty($sess['verified_nim'])
        || !hash_equals((string) $sess['verified_nim'], $nim)
    ) {
        record_audit_log($pdo, [
            'bilik_id'           => $bilik_id ?: null,
            'nim'                => $nim,
            'device_id'          => $device_id,
            'fingerprint_hash'   => $fingerprint_hash,
            'browser_session_id' => $browser_session_id,
            'ip_address'         => $ip,
            'user_agent'         => $ua,
            'status_log'         => 'FRAUD',
            'kode_event'         => 'SESSION_HIJACK_MISMATCH',
            'judul_event'        => 'Ketidakcocokan Session Browser Server-Side Saat Submit Suara',
            'keterangan'         => "Klien IP {$ip} / device {$device_id} mencoba mengirim suara untuk NIM {$nim} tanpa session verifikasi NIM yang valid.",
        ]);
        json_response(false, 'Sesi pemilihan tidak valid atau telah berakhir. Silakan pindai ulang QR Code bilik.', [
            'kode_error' => 'SESSION_BROWSER_MISMATCH',
        ], 403);
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
        $ip,
        $ua
    );

    if (!$vote_res['status']) {
        json_response(false, $vote_res['message'], [
            'kode_error' => $vote_res['kode_error'] ?? 'VOTE_FAILED',
        ]);
    }

    // Tanam cookie HMAC penanda device sudah memilih (berlaku 30 hari) & hapus sesi aktif
    $voted_ttl = 86400 * 30;
    set_voting_cookie('pemira_voted_device', $device_id, $voted_ttl);
    set_voting_cookie('pemira_voted_sig', sign_hmac_value($device_id), $voted_ttl);
    issue_device_identity_cookies($device_id);
    clear_active_voting_resume_cookie();
    unset($_SESSION['active_voting_session']);

    json_response(true, $vote_res['message']);
}

json_response(false, 'Aksi tidak dikenali.', [], 400);

