<?php
/**
 * Model Transaksi Pemungutan Suara & Validasi Anti-Kecurangan (Voting Engine Model)
 * Menangani klaim token QR bilik, pencegahan scan ganda per device, verifikasi NIM,
 * dan perhitungan suara atomik (Secret Ballot).
 */

require_once BASE_PATH . '/models/log_model.php';
require_once BASE_PATH . '/models/bilik_model.php';
require_once BASE_PATH . '/models/mahasiswa_model.php';
require_once BASE_PATH . '/models/user_model.php';

/**
 * Memeriksa apakah perangkat (berdasarkan device_id, session, atau fingerprint) sudah pernah memilih
 */
function check_device_already_voted(
    PDO $pdo,
    string $device_id,
    string $fingerprint_hash,
    string $browser_session_id
): ?array {
    $stmt = $pdo->prepare('
        SELECT ds.*, m.nama_lengkap AS nama_mahasiswa
        FROM device_sessions ds
        LEFT JOIN mahasiswas m ON m.nim = ds.nim_terkait
        WHERE ds.sudah_digunakan_vote = 1
          AND (
              ds.device_id = :device_id
              OR ds.browser_session_id = :browser_session_id
              OR (ds.fingerprint_hash = :fingerprint_hash AND ds.fingerprint_hash != "" AND ds.fingerprint_hash != "unknown_fp")
          )
        ORDER BY ds.id DESC
        LIMIT 1
    ');
    $stmt->execute([
        ':device_id'          => $device_id,
        ':browser_session_id' => $browser_session_id,
        ':fingerprint_hash'   => $fingerprint_hash,
    ]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/**
 * Memproses pemindaian QR Code bilik secara atomik:
 * 1. Cek portal global & status bilik
 * 2. Cek apakah device sudah pernah digunakan memilih (Anti Double Scan)
 * 3. Validasi token QR (menerima current_token atau previous_token dalam grace window)
 * 4. Kunci bilik menjadi IN_USE dan sembunyikan QR Code dari layar bilik seketika
 */
function claim_qr_token_for_device(
    PDO $pdo,
    int $nomor_bilik,
    string $qr_token,
    string $device_id,
    string $fingerprint_hash,
    string $browser_session_id,
    string $ip,
    string $ua,
    int $session_ttl_seconds = 180
): array {
    release_expired_bilik_locks($pdo);

    $bilik = find_bilik_by_nomor($pdo, $nomor_bilik);
    if (!$bilik) {
        return [
            'status'     => false,
            'kode_error' => 'INVALID_BOOTH',
            'message'    => 'Bilik suara tidak ditemukan di dalam sistem.',
        ];
    }

    $bilik_id = (int) $bilik['id'];

    // 1. Cek apakah perangkat ini sudah pernah melakukan voting sebelumnya
    $used_device = check_device_already_voted($pdo, $device_id, $fingerprint_hash, $browser_session_id);
    if ($used_device !== null) {
        $nim_lama = $used_device['nim_terkait'] ?? null;
        $nama_lama = $used_device['nama_mahasiswa'] ?? null;
        $info_pemilik = $nim_lama ? " (sebelumnya digunakan oleh NIM {$nim_lama} - {$nama_lama})" : '';

        record_audit_log($pdo, [
            'bilik_id'             => $bilik_id,
            'nim'                  => $nim_lama,
            'nama_mahasiswa_cache' => $nama_lama,
            'device_id'            => $device_id,
            'fingerprint_hash'     => $fingerprint_hash,
            'browser_session_id'   => $browser_session_id,
            'ip_address'           => $ip,
            'user_agent'           => $ua,
            'status_log'           => 'FRAUD',
            'kode_event'           => 'DOUBLE_SCAN_SAME_DEVICE',
            'judul_event'          => 'Percobaan Scan Ke-2 oleh Device yang Sama',
            'keterangan'           => "Perangkat dengan Device ID {$device_id}{$info_pemilik} mencoba memindai ulang QR Code di {$bilik['nama_bilik']}. Akses ditolak otomatis.",
        ]);

        return [
            'status'     => false,
            'kode_error' => 'DOUBLE_SCAN_SAME_DEVICE',
            'message'    => 'Perangkat dan browser ini tercatat sudah digunakan untuk memilih. Satu perangkat hanya diizinkan melakukan 1 kali pemilihan.',
        ];
    }

    // 2. Cek apakah portal global sedang ditutup oleh Admin
    $global_status = get_setting_value($pdo, 'status_portal_global', 'BUKA');
    if ($global_status !== 'BUKA' || $bilik['status_bilik'] === 'CLOSED') {
        return [
            'status'     => false,
            'kode_error' => 'BOOTH_CLOSED',
            'message'    => 'Bilik suara saat ini sedang ditutup sementara oleh panitia.',
        ];
    }

    // 3. Jika device yang SAMA me-refresh halaman saat bilik sedang IN_USE oleh dirinya sendiri
    if (
        $bilik['status_bilik'] === 'IN_USE'
        && !empty($bilik['active_device_id'])
        && hash_equals((string) $bilik['active_device_id'], $device_id)
        && !empty($bilik['active_session_token'])
    ) {
        return [
            'status'               => true,
            'bilik_id'             => $bilik_id,
            'nomor_bilik'          => (int) $bilik['nomor_bilik'],
            'nama_bilik'           => $bilik['nama_bilik'],
            'voting_session_token' => $bilik['active_session_token'],
            'active_nim'           => $bilik['active_nim'],
            'message'              => 'Melanjutkan sesi bilik suara aktif.',
        ];
    }

    // 4. Cek apakah bilik sedang digunakan oleh perangkat lain (misal scan dari hasil foto saat orang di dalam bilik)
    if ($bilik['status_bilik'] === 'IN_USE') {
        record_audit_log($pdo, [
            'bilik_id'           => $bilik_id,
            'device_id'          => $device_id,
            'fingerprint_hash'   => $fingerprint_hash,
            'browser_session_id' => $browser_session_id,
            'ip_address'         => $ip,
            'user_agent'         => $ua,
            'status_log'         => 'FRAUD',
            'kode_event'         => 'EXPIRED_OR_USED_TOKEN_SCAN',
            'judul_event'        => 'Percobaan Scan Saat Bilik Sedang Digunakan',
            'keterangan'         => "Perangkat {$device_id} mencoba memindai token pada {$bilik['nama_bilik']} yang sedang terkunci (IN_USE) oleh pemilih lain. Indikasi pemindaian dari foto/tangkapan layar.",
        ]);

        return [
            'status'     => false,
            'kode_error' => 'BOOTH_IN_USE',
            'message'    => 'Bilik suara ini sedang digunakan oleh pemilih lain atau token QR telah kadaluarsa.',
        ];
    }

    // 5. Validasi kecocokan token QR (current_token atau previous_token dalam grace window)
    $is_current = ($qr_token !== '' && hash_equals((string) $bilik['current_token'], $qr_token));
    $is_prev = ($qr_token !== '' && !empty($bilik['previous_token']) && hash_equals((string) $bilik['previous_token'], $qr_token));

    if (!$is_current && !$is_prev) {
        record_audit_log($pdo, [
            'bilik_id'           => $bilik_id,
            'device_id'          => $device_id,
            'fingerprint_hash'   => $fingerprint_hash,
            'browser_session_id' => $browser_session_id,
            'ip_address'         => $ip,
            'user_agent'         => $ua,
            'status_log'         => 'FRAUD',
            'kode_event'         => 'EXPIRED_OR_USED_TOKEN_SCAN',
            'judul_event'        => 'Pemindaian Token QR Kadaluarsa / Hasil Foto',
            'keterangan'         => "Perangkat {$device_id} mencoba mengakses {$bilik['nama_bilik']} menggunakan token QR yang sudah tidak berlaku (indikasi scan dari foto QR lama).",
        ]);

        return [
            'status'     => false,
            'kode_error' => 'TOKEN_EXPIRED',
            'message'    => 'Token QR Code sudah kadaluarsa atau tidak valid. Silakan pindai ulang langsung dari layar bilik suara.',
        ];
    }

    // 6. Kunci bilik secara atomik (0ms instant reset token & ubah status ke IN_USE)
    $voting_session_token = generate_secure_token(24);
    $new_idle_token = generate_secure_token(20);
    $locked_until = date('Y-m-d H:i:s', time() + $session_ttl_seconds);

    $pdo->beginTransaction();
    try {
        $stmt_lock = $pdo->prepare('
            UPDATE bilik_suaras
            SET status_bilik = "IN_USE",
                current_token = :new_idle_token,
                previous_token = NULL,
                token_generated_at = NOW(),
                active_session_token = :session_token,
                active_device_id = :device_id,
                active_nim = NULL,
                locked_until = :locked_until
            WHERE id = :id AND status_bilik = "IDLE"
        ');
        $stmt_lock->execute([
            ':new_idle_token' => $new_idle_token,
            ':session_token'  => $voting_session_token,
            ':device_id'      => $device_id,
            ':locked_until'   => $locked_until,
            ':id'             => $bilik_id,
        ]);

        if ($stmt_lock->rowCount() === 0) {
            $pdo->rollBack();
            return [
                'status'     => false,
                'kode_error' => 'RACE_CONDITION_LOCK',
                'message'    => 'Token QR baru saja diklaim oleh perangkat lain. Silakan pindai ulang.',
            ];
        }

        // Catat sesi perangkat aktif
        $stmt_dev = $pdo->prepare('
            INSERT INTO device_sessions (device_id, browser_session_id, fingerprint_hash, ip_address, user_agent, bilik_id, sudah_digunakan_vote, jumlah_percobaan_nim)
            VALUES (:device_id, :browser_session_id, :fingerprint_hash, :ip_address, :user_agent, :bilik_id, 0, 0)
        ');
        $stmt_dev->execute([
            ':device_id'          => $device_id,
            ':browser_session_id' => $browser_session_id,
            ':fingerprint_hash'   => $fingerprint_hash,
            ':ip_address'         => $ip,
            ':user_agent'         => $ua,
            ':bilik_id'           => $bilik_id,
        ]);

        $pdo->commit();

        return [
            'status'               => true,
            'bilik_id'             => $bilik_id,
            'nomor_bilik'          => (int) $bilik['nomor_bilik'],
            'nama_bilik'           => $bilik['nama_bilik'],
            'voting_session_token' => $voting_session_token,
            'active_nim'           => null,
            'locked_until'         => $locked_until,
            'message'              => 'QR Code berhasil dipindai. Bilik suara telah dikunci untuk perangkat Anda.',
        ];
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/**
 * Memverifikasi NIM yang diinput mahasiswa di dalam bilik suara:
 * - Memeriksa validitas sesi bilik & kecocokan device_id (Anti-Hijack / Direct URL Bypass)
 * - Membatasi percobaan salah input NIM maksimal 3 kali (Anti Brute-Force NIM)
 * - Memeriksa apakah NIM terdaftar, sudah check-in (jika diaktifkan), dan belum memilih
 */
function verify_voter_nim_in_session(
    PDO $pdo,
    int $bilik_id,
    string $voting_session_token,
    string $device_id,
    string $fingerprint_hash,
    string $browser_session_id,
    string $nim,
    string $ip,
    string $ua,
    int $max_attempts = 3
): array {
    $bilik = find_bilik_by_id($pdo, $bilik_id);
    if (!$bilik || $bilik['status_bilik'] !== 'IN_USE') {
        record_audit_log($pdo, [
            'bilik_id'           => $bilik_id ?: null,
            'nim'                => $nim,
            'device_id'          => $device_id,
            'fingerprint_hash'   => $fingerprint_hash,
            'browser_session_id' => $browser_session_id,
            'ip_address'         => $ip,
            'user_agent'         => $ua,
            'status_log'         => 'FRAUD',
            'kode_event'         => 'DIRECT_URL_BYPASS',
            'judul_event'        => 'Akses Verifikasi NIM Tanpa Sesi QR Aktif',
            'keterangan'         => "Perangkat {$device_id} mencoba memverifikasi NIM {$nim} tanpa melalui sesi QR bilik yang aktif.",
        ]);

        return [
            'status'     => false,
            'kode_error' => 'SESSION_INVALID',
            'message'    => 'Sesi bilik suara tidak valid atau telah berakhir. Silakan pindai ulang QR Code di layar bilik.',
        ];
    }

    if (
        empty($bilik['active_session_token'])
        || !hash_equals((string) $bilik['active_session_token'], $voting_session_token)
        || !hash_equals((string) $bilik['active_device_id'], $device_id)
    ) {
        record_audit_log($pdo, [
            'bilik_id'           => $bilik_id,
            'nim'                => $nim,
            'device_id'          => $device_id,
            'fingerprint_hash'   => $fingerprint_hash,
            'browser_session_id' => $browser_session_id,
            'ip_address'         => $ip,
            'user_agent'         => $ua,
            'status_log'         => 'FRAUD',
            'kode_event'         => 'SESSION_HIJACK_MISMATCH',
            'judul_event'        => 'Ketidakcocokan Token Sesi / Perangkat',
            'keterangan'         => "Perangkat {$device_id} mencoba mengambil alih sesi pemilihan milik perangkat lain di {$bilik['nama_bilik']}.",
        ]);

        return [
            'status'     => false,
            'kode_error' => 'SESSION_MISMATCH',
            'message'    => 'Token sesi tidak cocok dengan perangkat pemindai QR.',
        ];
    }

    // Cek apakah mahasiswa terdaftar di DPT
    $mahasiswa = find_mahasiswa_by_nim($pdo, $nim);
    if (!$mahasiswa) {
        $attempts = increment_device_nim_attempts($pdo, $device_id, $bilik_id);
        if ($attempts >= $max_attempts) {
            force_reset_bilik($pdo, $bilik_id);
            record_audit_log($pdo, [
                'bilik_id'           => $bilik_id,
                'nim'                => $nim,
                'device_id'          => $device_id,
                'fingerprint_hash'   => $fingerprint_hash,
                'browser_session_id' => $browser_session_id,
                'ip_address'         => $ip,
                'user_agent'         => $ua,
                'status_log'         => 'FRAUD',
                'kode_event'         => 'NIM_BRUTEFORCE_ATTEMPT',
                'judul_event'        => 'Percobaan Tebak NIM Berulang (Brute-Force)',
                'keterangan'         => "Perangkat {$device_id} di {$bilik['nama_bilik']} gagal memasukkan NIM valid sebanyak {$attempts} kali berturut-turut (terakhir: {$nim}). Sesi bilik dihentikan otomatis.",
            ]);

            return [
                'status'       => false,
                'session_dead' => true,
                'kode_error'   => 'NIM_BRUTEFORCE_LOCKED',
                'message'      => 'Anda telah salah memasukkan NIM sebanyak 3 kali. Demi keamanan, sesi bilik di-reset otomatis.',
            ];
        }

        return [
            'status'     => false,
            'kode_error' => 'NIM_NOT_FOUND',
            'message'    => "NIM {$nim} tidak terdaftar dalam Daftar Pemilih Tetap (DPT). Sisa kesempatan: " . ($max_attempts - $attempts) . " kali.",
        ];
    }

    // Cek apakah NIM tersebut SUDAH MEMILIH (sudah_memilih == 1)
    if ((int) $mahasiswa['sudah_memilih'] === 1) {
        $attempts = increment_device_nim_attempts($pdo, $device_id, $bilik_id);

        record_audit_log($pdo, [
            'bilik_id'             => $bilik_id,
            'nim'                  => $mahasiswa['nim'],
            'nama_mahasiswa_cache' => $mahasiswa['nama_lengkap'],
            'device_id'            => $device_id,
            'fingerprint_hash'     => $fingerprint_hash,
            'browser_session_id'   => $browser_session_id,
            'ip_address'           => $ip,
            'user_agent'           => $ua,
            'status_log'           => 'FRAUD',
            'kode_event'           => 'NIM_ALREADY_VOTED',
            'judul_event'          => 'Percobaan Memilih Ulang dengan NIM yang Sudah Vote',
            'keterangan'           => "Perangkat {$device_id} di {$bilik['nama_bilik']} memasukkan NIM {$mahasiswa['nim']} ({$mahasiswa['nama_lengkap']} - {$mahasiswa['nama_kelas']}) yang sudah pernah memilih pada {$mahasiswa['waktu_memilih']}.",
        ]);

        if ($attempts >= $max_attempts) {
            force_reset_bilik($pdo, $bilik_id);
        }

        return [
            'status'       => false,
            'session_dead' => ($attempts >= $max_attempts),
            'kode_error'   => 'NIM_ALREADY_VOTED',
            'message'      => "NIM {$mahasiswa['nim']} atas nama {$mahasiswa['nama_lengkap']} tercatat SUDAH MEMILIH. Percobaan ini telah direkam ke Log Keamanan.",
        ];
    }

    // Cek apakah mode Wajib Check-In Meja Panitia aktif
    $wajib_checkin = (get_setting_value($pdo, 'wajib_checkin_panitia', '0') === '1');
    if ($wajib_checkin && (int) $mahasiswa['status_hadir'] === 0) {
        record_audit_log($pdo, [
            'bilik_id'             => $bilik_id,
            'nim'                  => $mahasiswa['nim'],
            'nama_mahasiswa_cache' => $mahasiswa['nama_lengkap'],
            'device_id'            => $device_id,
            'fingerprint_hash'     => $fingerprint_hash,
            'browser_session_id'   => $browser_session_id,
            'ip_address'           => $ip,
            'user_agent'           => $ua,
            'status_log'           => 'FRAUD',
            'kode_event'           => 'NIM_NOT_CHECKED_IN',
            'judul_event'          => 'Penggunaan NIM yang Belum Check-In Registrasi',
            'keterangan'           => "Perangkat {$device_id} di {$bilik['nama_bilik']} mencoba menggunakan NIM {$mahasiswa['nim']} ({$mahasiswa['nama_lengkap']}) yang belum diaktifkan kehadirannya di meja panitia (indikasi memakai NIM orang lain).",
        ]);

        return [
            'status'     => false,
            'kode_error' => 'NIM_NOT_CHECKED_IN',
            'message'    => "NIM {$mahasiswa['nim']} ({$mahasiswa['nama_lengkap']}) belum melakukan verifikasi kehadiran di meja registrasi panitia.",
        ];
    }

    // Simpan NIM aktif pada bilik suara & sesi perangkat
    $stmt_bind = $pdo->prepare('UPDATE bilik_suaras SET active_nim = :nim WHERE id = :id');
    $stmt_bind->execute([
        ':nim' => $mahasiswa['nim'],
        ':id'  => $bilik_id,
    ]);

    $stmt_dev_nim = $pdo->prepare('
        UPDATE device_sessions
        SET nim_terkait = :nim
        WHERE device_id = :device_id AND bilik_id = :bilik_id
    ');
    $stmt_dev_nim->execute([
        ':nim'       => $mahasiswa['nim'],
        ':device_id' => $device_id,
        ':bilik_id'  => $bilik_id,
    ]);

    return [
        'status'    => true,
        'mahasiswa' => [
            'nim'          => $mahasiswa['nim'],
            'nama_lengkap' => $mahasiswa['nama_lengkap'],
            'nama_kelas'   => $mahasiswa['nama_kelas'],
            'jurusan'      => $mahasiswa['jurusan'],
            'semester'     => $mahasiswa['semester'],
        ],
        'message'   => 'Identitas mahasiswa terverifikasi. Silakan pilih calon Presiden BEM dan Ketua Umum BPM.',
    ];
}

/**
 * Menambah penghitung kesalahan input NIM pada sesi perangkat
 */
function increment_device_nim_attempts(PDO $pdo, string $device_id, int $bilik_id): int
{
    $stmt = $pdo->prepare('
        UPDATE device_sessions
        SET jumlah_percobaan_nim = jumlah_percobaan_nim + 1
        WHERE device_id = :device_id AND bilik_id = :bilik_id
        ORDER BY id DESC
        LIMIT 1
    ');
    $stmt->execute([
        ':device_id' => $device_id,
        ':bilik_id'  => $bilik_id,
    ]);

    $stmt_get = $pdo->prepare('
        SELECT jumlah_percobaan_nim
        FROM device_sessions
        WHERE device_id = :device_id AND bilik_id = :bilik_id
        ORDER BY id DESC
        LIMIT 1
    ');
    $stmt_get->execute([
        ':device_id' => $device_id,
        ':bilik_id'  => $bilik_id,
    ]);
    return (int) $stmt_get->fetchColumn();
}

/**
 * Menyimpan konfirmasi suara (Confirm Vote) secara atomik dengan prinsip Secret Ballot:
 * - Mengubah mahasiswas.sudah_memilih = 1 (tanpa merekam ID kandidat pada tabel mahasiswa)
 * - Menambahkan counter +1 pada kandidat BEM dan +1 pada kandidat BPM
 * - Menandai device_sessions.sudah_digunakan_vote = 1
 * - Mereset bilik kembali ke IDLE dengan token QR baru untuk pemilih berikutnya
 * - Mencatat aktivitas ke audit_logs dengan status NORMAL (Hijau)
 */
function submit_atomic_vote(
    PDO $pdo,
    int $bilik_id,
    string $voting_session_token,
    string $device_id,
    string $fingerprint_hash,
    string $browser_session_id,
    string $nim,
    int $kandidat_bem_id,
    int $kandidat_bpm_id,
    string $ip,
    string $ua
): array {
    $bilik = find_bilik_by_id($pdo, $bilik_id);
    if (
        !$bilik
        || $bilik['status_bilik'] !== 'IN_USE'
        || empty($bilik['active_session_token'])
        || !hash_equals((string) $bilik['active_session_token'], $voting_session_token)
        || !hash_equals((string) $bilik['active_device_id'], $device_id)
        || (string) $bilik['active_nim'] !== $nim
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
            'judul_event'        => 'Pengiriman Suara dengan Sesi Tidak Sah',
            'keterangan'         => "Perangkat {$device_id} mencoba mengirim konfirmasi suara untuk NIM {$nim} tanpa sesi bilik yang valid.",
        ]);

        return [
            'status'     => false,
            'kode_error' => 'INVALID_VOTING_SESSION',
            'message'    => 'Sesi pemilihan tidak valid atau telah berakhir.',
        ];
    }

    // Validasi bahwa kandidat BEM benar-benar kategori BEM dan kandidat BPM benar-benar kategori BPM
    $stmt_bem = $pdo->prepare('SELECT id, kategori FROM kandidats WHERE id = :id AND kategori = "BEM" LIMIT 1');
    $stmt_bem->execute([':id' => $kandidat_bem_id]);
    $valid_bem = $stmt_bem->fetch();

    $stmt_bpm = $pdo->prepare('SELECT id, kategori FROM kandidats WHERE id = :id AND kategori = "BPM" LIMIT 1');
    $stmt_bpm->execute([':id' => $kandidat_bpm_id]);
    $valid_bpm = $stmt_bpm->fetch();

    if (!$valid_bem || !$valid_bpm) {
        record_audit_log($pdo, [
            'bilik_id'           => $bilik_id,
            'nim'                => $nim,
            'device_id'          => $device_id,
            'fingerprint_hash'   => $fingerprint_hash,
            'browser_session_id' => $browser_session_id,
            'ip_address'         => $ip,
            'user_agent'         => $ua,
            'status_log'         => 'FRAUD',
            'kode_event'         => 'PAYLOAD_TAMPERING',
            'judul_event'        => 'Manipulasi Parameter Kandidat (Payload Tampering)',
            'keterangan'         => "Perangkat {$device_id} (NIM {$nim}) mengirimkan ID kandidat BEM ({$kandidat_bem_id}) atau BPM ({$kandidat_bpm_id}) yang tidak sesuai kategori.",
        ]);

        return [
            'status'     => false,
            'kode_error' => 'PAYLOAD_TAMPERING',
            'message'    => 'Pilihan kandidat tidak valid.',
        ];
    }

    // Mulai Transaksi Atomik
    $pdo->beginTransaction();
    try {
        // 1. Update status mahasiswa menjadi sudah_memilih = 1 HANYA JIKA masih 0 (Anti Race Condition Double Submit)
        $stmt_mhs = $pdo->prepare('
            UPDATE mahasiswas
            SET sudah_memilih = 1,
                waktu_memilih = NOW()
            WHERE nim = :nim AND sudah_memilih = 0
        ');
        $stmt_mhs->execute([':nim' => $nim]);

        if ($stmt_mhs->rowCount() === 0) {
            $pdo->rollBack();

            record_audit_log($pdo, [
                'bilik_id'           => $bilik_id,
                'nim'                => $nim,
                'device_id'          => $device_id,
                'fingerprint_hash'   => $fingerprint_hash,
                'browser_session_id' => $browser_session_id,
                'ip_address'         => $ip,
                'user_agent'         => $ua,
                'status_log'         => 'FRAUD',
                'kode_event'         => 'RACE_CONDITION_DOUBLE_SUBMIT',
                'judul_event'        => 'Percobaan Submit Suara Ganda (Double Submit)',
                'keterangan'         => "Perangkat {$device_id} (NIM {$nim}) mencoba mengirimkan konfirmasi suara lebih dari satu kali secara bersamaan di {$bilik['nama_bilik']}.",
            ]);

            return [
                'status'     => false,
                'kode_error' => 'ALREADY_SUBMITTED',
                'message'    => 'Suara untuk NIM ini sudah masuk sebelumnya. Pengiriman ganda dibatalkan.',
            ];
        }

        // 2. Tambahkan +1 pada counter kandidat Presiden BEM
        $stmt_inc_bem = $pdo->prepare('
            UPDATE kandidats
            SET jumlah_suara = jumlah_suara + 1
            WHERE id = :id AND kategori = "BEM"
        ');
        $stmt_inc_bem->execute([':id' => $kandidat_bem_id]);

        // 3. Tambahkan +1 pada counter kandidat Ketua Umum BPM
        $stmt_inc_bpm = $pdo->prepare('
            UPDATE kandidats
            SET jumlah_suara = jumlah_suara + 1
            WHERE id = :id AND kategori = "BPM"
        ');
        $stmt_inc_bpm->execute([':id' => $kandidat_bpm_id]);

        // 4. Tandai device session telah digunakan memilih (mencegah scan ke-2 kali)
        $stmt_dev_done = $pdo->prepare('
            UPDATE device_sessions
            SET sudah_digunakan_vote = 1,
                nim_terkait = :nim,
                browser_session_id = :browser_session_id,
                fingerprint_hash = :fingerprint_hash
            WHERE device_id = :device_id
        ');
        $stmt_dev_done->execute([
            ':nim'                => $nim,
            ':browser_session_id' => $browser_session_id,
            ':fingerprint_hash'   => $fingerprint_hash,
            ':device_id'          => $device_id,
        ]);

        // 5. Bersihkan sesi bilik suara (kembali ke IDLE dengan token QR baru) & tambah counter suara bilik
        $stmt_unlock_bilik = $pdo->prepare('
            UPDATE bilik_suaras
            SET status_bilik = "IDLE",
                current_token = :new_token,
                previous_token = NULL,
                token_generated_at = NOW(),
                active_session_token = NULL,
                active_device_id = NULL,
                active_nim = NULL,
                locked_until = NULL,
                last_vote_at = NOW(),
                total_suara_bilik = total_suara_bilik + 1
            WHERE id = :id
        ');
        $stmt_unlock_bilik->execute([
            ':new_token' => generate_secure_token(20),
            ':id'        => $bilik_id,
        ]);

        // 6. Catat Log Audit NORMAL (Baris Hijau) tanpa menyebutkan kandidat yang dipilih
        $mhs_detail = find_mahasiswa_by_nim($pdo, $nim);
        $nama_mhs = $mhs_detail['nama_lengkap'] ?? null;
        $info_kelas = isset($mhs_detail['nama_kelas']) ? " ({$mhs_detail['nama_kelas']})" : '';

        record_audit_log($pdo, [
            'bilik_id'             => $bilik_id,
            'nim'                  => $nim,
            'nama_mahasiswa_cache' => $nama_mhs,
            'device_id'            => $device_id,
            'fingerprint_hash'     => $fingerprint_hash,
            'browser_session_id'   => $browser_session_id,
            'ip_address'           => $ip,
            'user_agent'           => $ua,
            'status_log'           => 'NORMAL',
            'kode_event'           => 'VOTE_SUCCESS',
            'judul_event'          => 'Pemungutan Suara Berhasil',
            'keterangan'           => "Mahasiswa {$nama_mhs}{$info_kelas} dengan NIM {$nim} telah berhasil memberikan suara untuk Presiden BEM dan Ketua Umum BPM di {$bilik['nama_bilik']}.",
        ]);

        $pdo->commit();

        return [
            'status'  => true,
            'message' => 'Terima kasih! Suara Anda untuk pemilihan Presiden BEM dan Ketua Umum BPM telah berhasil direkam secara rahasia.',
        ];
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

