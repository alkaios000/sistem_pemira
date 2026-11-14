<?php
/**
 * BILIK PEMILIHAN SUARA - Adminator 2026 Redesign (Fitur 4)
 * Tahap: 1. Klaim QR Bilik (Kamera/Link/Token) -> 2. Verifikasi NIM -> 3. Pilih BEM & BPM -> 4. Selesai
 */
require_once BASE_PATH . '/models/kandidat_model.php';
require_once BASE_PATH . '/models/bilik_model.php';
require_once BASE_PATH . '/models/voting_model.php';
require_once BASE_PATH . '/models/log_model.php';

header('Cache-Control: no-store, no-cache, must-revalidate, private');
header('Pragma: no-cache');
header('Referrer-Policy: no-referrer');

$pdo = get_db_connection();
$pageTitle = 'Bilik Pemilihan Suara';
$isFullScreenLayout = true;

$urlBooth = isset($_GET['booth']) ? (int) $_GET['booth'] : 0;
$urlToken = isset($_GET['token']) ? trim((string) $_GET['token']) : '';

// Pulihkan sesi aktif dari $_SESSION atau cookie persisten berproteksi HMAC bila browser sempat ditutup
$activeSession = restore_active_voting_session($pdo);

// Jika sudah memiliki sesi bilik aktif dan masih ada parameter token/booth di URL, bersihkan URL seketika
if ($activeSession !== null && ($urlToken !== '' || $urlBooth > 0)) {
    redirect(route_url('bilik_pemilihan'));
}

// Periksa apakah perangkat ini sudah menuntaskan pemilihan sebelumnya (anti double scan / double vote)
$alreadyVotedBlocked = false;
if ($activeSession === null) {
    $votedDeviceCookie = get_verified_voted_device_cookie();
    if ($votedDeviceCookie !== null && has_device_already_voted_record($pdo, $votedDeviceCookie)) {
        $alreadyVotedBlocked = true;
    }
}

// Deteksi percobaan akses paksa step=2 atau step=3 lewat URL tanpa sesi QR aktif (DIRECT_URL_BYPASS)
if (isset($_GET['step']) && in_array((int) $_GET['step'], [2, 3], true) && !$activeSession) {
    record_audit_log($pdo, [
        'bilik_id'           => $urlBooth > 0 ? $urlBooth : null,
        'log_category'       => 'pemilih',
        'nim'                => null,
        'device_id'          => $_COOKIE['pemira_voted_device'] ?? ($_COOKIE['pemira_device_id'] ?? 'direct_url_client'),
        'fingerprint_hash'   => null,
        'browser_session_id' => $_SESSION['voter_browser_session_id'] ?? session_id(),
        'ip_address'         => get_client_ip(),
        'user_agent'         => get_client_user_agent(),
        'status_log'         => 'FRAUD',
        'kode_event'         => 'DIRECT_URL_BYPASS',
        'judul_event'        => 'Percobaan Bypass URL Langsung ke Form Pemilihan',
        'keterangan'         => 'Perangkat mencoba melompat langsung ke parameter step=' . (int) $_GET['step'] . ' tanpa klaim QR bilik yang sah.',
    ]);
    set_flash('danger', 'Peringatan Keamanan: Akses langsung tanpa memindai QR Code bilik ditolak dan telah dicatat ke Log Keamanan.');
}

$initialStep = 1;
if ($alreadyVotedBlocked) {
    $initialStep = 0;
} elseif ($activeSession !== null && !empty($activeSession['verified_nim'])) {
    $initialStep = 3;
} elseif ($activeSession !== null) {
    $initialStep = 2;
}

$hasUrlScan = ($initialStep === 1 && $urlToken !== '');

$kandidatBem = get_kandidats_by_kategori($pdo, 'BEM');
$kandidatBpm = get_kandidats_by_kategori($pdo, 'BPM');

$extraScripts = [
    asset_url('js/vendor/html5-qrcode.min.js') . '?v=1',
    asset_url('js/modules/voting_client.js') . '?v=3',
];

require BASE_PATH . '/views/layouts/header.php';
?>

<div class="voting-shell"
     id="votingRoot"
     data-initial-step="<?= (int) $initialStep ?>"
     data-has-url-scan="<?= $hasUrlScan ? '1' : '0' ?>"
     data-session-bilik-id="<?= (int) ($activeSession['bilik_id'] ?? 0) ?>"
     data-session-nomor-bilik="<?= (int) ($activeSession['nomor_bilik'] ?? 0) ?>"
     data-session-nama-bilik="<?= e($activeSession['nama_bilik'] ?? '') ?>"
     data-session-token="<?= e($activeSession['voting_session_token'] ?? '') ?>"
     data-session-device-id="<?= e($activeSession['device_id'] ?? '') ?>"
     data-verified-nim="<?= e($activeSession['verified_nim'] ?? '') ?>"
     data-verified-nama="<?= e($activeSession['verified_nama'] ?? '') ?>"
     data-verified-kelas="<?= e($activeSession['verified_kelas'] ?? '') ?>"
     data-verified-jurusan="<?= e($activeSession['verified_jurusan'] ?? '') ?>">

    <!-- Topbar Bilik -->
    <header class="voting-topbar">
        <div class="d-flex align-center gap-2">
            <img src="<?= e(asset_url('img/ubw_logo.png')) ?>" alt="Logo UBW" class="brand-img-sm">
            <span class="fw-700" style="font-size:13.5px;">Sistem PEMIRA · Bilik Pemilihan Suara</span>
        </div>
    </header>

    <main class="voting-container">
        <?php if ($alreadyVotedBlocked): ?>
            <div id="panelAlreadyVoted" class="card scanner-card text-center">
                <div class="mb-2">
                    <span class="badge danger dot">PERANGKAT SUDAH MEMILIH</span>
                </div>
                <h2 class="hero-title" style="font-size:19px;">Hak Pilih Pada Perangkat Ini Sudah Digunakan</h2>
                <p class="text-muted mt-2 mb-3" style="font-size:12.5px;line-height:1.55;">
                    Sistem mendeteksi perangkat dan browser ini telah menyelesaikan pemilihan pada sesi PEMIRA saat ini. Satu perangkat hanya dapat digunakan 1 kali untuk menjaga keadilan suara.
                </p>
                <a href="<?= e(route_url('live_count_bem')) ?>" class="btn btn--primary btn--sm w-full">Lihat Live Count Hasil Sementara</a>
            </div>
        <?php endif; ?>

        <!-- Stepper -->
        <div class="voting-steps" id="votingStepper" style="<?= $alreadyVotedBlocked ? 'display:none;' : '' ?>flex-wrap:wrap;">
            <div class="step-pill <?= $initialStep === 1 ? 'is-active' : ($initialStep > 1 ? 'is-done' : '') ?>" id="stepPill1">
                <span class="num">1</span>
                <span>Scan QR Bilik</span>
            </div>
            <span class="text-light">/</span>
            <div class="step-pill <?= $initialStep === 2 ? 'is-active' : ($initialStep > 2 ? 'is-done' : '') ?>" id="stepPill2">
                <span class="num">2</span>
                <span>Verifikasi NIM</span>
            </div>
            <span class="text-light">/</span>
            <div class="step-pill <?= $initialStep === 3 ? 'is-active' : ($initialStep > 3 ? 'is-done' : '') ?>" id="stepPill3">
                <span class="num">3</span>
                <span>Surat Suara BEM & BPM</span>
            </div>
        </div>

        <!-- PANEL 1: SCAN KAMERA QR OTOMATIS (TANPA PILIH BILIK & TANPA TAMPILKAN TOKEN) -->
        <div id="panelStep1" class="card scanner-card" style="<?= $initialStep === 1 ? 'display:flex;' : 'display:none;' ?>">
            <div class="card-head">
                <div class="card-title-wrap">
                    <h3 class="card-title">Pindai QR Code Bilik Suara</h3>
                </div>
                <span class="badge primary dot">Deteksi Otomatis</span>
            </div>

            <div id="qrScanIdleView" style="<?= $hasUrlScan ? 'display:none;' : 'display:block;' ?>">
                <p class="text-muted mb-2" style="font-size:12.5px;line-height:1.5;">
                    Arahkan kamera ponsel ke QR Code pada layar bilik suara. Sistem akan otomatis mendeteksi bilik dan membawa Anda ke tahap verifikasi NIM.
                </p>

                <div id="qrCameraReader" class="scanner-viewport" style="display:flex;">
                    <span>Meminta izin akses kamera...</span>
                </div>

                <div id="qrCameraPermissionMsg" class="text-muted text-center mt-2 mb-2" style="display:none;font-size:12px;line-height:1.45;"></div>

                <div class="d-flex gap-2 mt-2">
                    <button type="button" class="btn btn--primary btn--sm w-full" id="btnStartQrCamera" onclick="startQrCamera(true)" style="display:none;">Izinkan &amp; Buka Kamera Pemindai QR</button>
                    <button type="button" class="btn btn--ghost btn--sm w-full" id="btnStopQrCamera" onclick="stopQrCamera()" style="display:none;">Tutup Kamera</button>
                </div>
            </div>

            <div id="qrAutoProcessing" class="voting-auto-status" style="<?= $hasUrlScan ? 'display:flex;' : 'display:none;' ?>">
                <div class="voting-spinner" aria-hidden="true"></div>
                <div class="fw-700" style="font-size:14px;">QR Code Terdeteksi</div>
                <div class="text-muted" id="qrAutoProcessingText" style="font-size:12px;">Mendeteksi bilik suara dan mengaktifkan sesi Anda...</div>
            </div>

            <div id="qrScanErrorBox" class="alert danger" style="display:none;margin-top:12px;margin-bottom:0;">
                <div class="alert-body" style="width:100%;">
                    <div id="qrScanErrorMsg">-</div>
                    <button type="button" class="btn btn--soft-primary btn--xs mt-2" id="btnRetryQrScan" onclick="retryQrScan()">Pindai Ulang QR Bilik</button>
                </div>
            </div>
        </div>

        <!-- PANEL 2: VERIFIKASI NIM MAHASISWA -->
        <div id="panelStep2" class="card scanner-card" style="<?= $initialStep === 2 ? 'display:flex;' : 'display:none;' ?>">
            <div class="card-head">
                <div class="card-title-wrap">
                    <h3 class="card-title">Masukkan NIM Anda</h3>
                </div>
                <span class="badge success dot" id="lockedBoothLabel"><?= e($activeSession['nama_bilik'] ?? 'Bilik Terkunci') ?></span>
            </div>

            <p class="text-muted mb-3">Masukkan Nomor Induk Mahasiswa (NIM) yang terdaftar di DPT. Maksimal 3 kali percobaan salah.</p>

            <div class="field">
                <label class="field-label">Nomor Induk Mahasiswa (NIM)</label>
                <input type="text" id="verifyNimInput" class="input mono" placeholder="Contoh: 240101003" autocomplete="off" inputmode="numeric">
            </div>

            <button type="button" class="btn btn--primary btn-block mt-2" id="btnVerifyNim" onclick="submitVerifyNim()">Verifikasi NIM & Buka Surat Suara →</button>
        </div>

        <!-- PANEL 3: SURAT SUARA BEM & BPM -->
        <div id="panelStep3" style="<?= $initialStep === 3 ? 'display:block;' : 'display:none;' ?>">
            <div class="card mb-3" style="padding:12px 16px;background:var(--primary-soft);border-color:rgba(37,99,235,0.2);">
                <div class="d-flex justify-between align-center flex-wrap gap-2">
                    <div>
                        <div class="fw-700" style="font-size:14px;" id="voterInfoName">-</div>
                        <div class="mono text-muted" id="voterInfoSub">-</div>
                    </div>
                    <span class="badge primary dot">Secret Ballot</span>
                </div>
            </div>

            <!-- KATEGORI 1: BEM -->
            <div class="mb-4">
                <div class="d-flex justify-between align-center mb-2">
                    <div>
                        <h2 class="hero-title" style="font-size:17px;">Calon Presiden BEM</h2>
                    </div>
                    <span class="badge primary">Pilih 1 Calon</span>
                </div>

                <div class="ballot-grid">
                    <?php foreach ($kandidatBem as $kb): ?>
                        <?php $fotoUrl = !empty($kb['foto_path']) ? upload_url($kb['foto_path']) : asset_url('img/default_candidate.svg'); ?>
                        <div class="ballot-card"
                             data-bem-id="<?= (int)$kb['id'] ?>"
                             data-bem-label="Calon #0<?= (int)$kb['nomor_urut'] ?> · <?= e($kb['nama_calon']) ?>"
                             onclick="selectBallotCard('BEM', <?= (int)$kb['id'] ?>, this)">
                            <div class="ballot-head">
                                <div class="ballot-num">0<?= (int)$kb['nomor_urut'] ?></div>
                                <img src="<?= e($fotoUrl) ?>" alt="Calon" class="ballot-photo">
                                <div>
                                    <div class="ballot-names"><?= e($kb['nama_calon']) ?></div>
                                    <div class="ballot-sub"><?= e($kb['jurusan_kelas'] ?? '-') ?></div>
                                </div>
                            </div>
                            <div class="ballot-visi">
                                <strong>Visi:</strong> <?= e($kb['visi']) ?>
                            </div>
                            <div class="ballot-action-indicator">
                                <span class="select-status-text">Klik untuk memilih</span>
                                <span>→</span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- KATEGORI 2: BPM -->
            <div class="mb-4">
                <div class="d-flex justify-between align-center mb-2">
                    <div>
                        <h2 class="hero-title" style="font-size:17px;">Calon Ketua Umum BPM</h2>
                    </div>
                    <span class="badge purple">Pilih 1 Calon</span>
                </div>

                <div class="ballot-grid">
                    <?php foreach ($kandidatBpm as $kp): ?>
                        <?php $fotoUrl = !empty($kp['foto_path']) ? upload_url($kp['foto_path']) : asset_url('img/default_candidate.svg'); ?>
                        <div class="ballot-card"
                             data-bpm-id="<?= (int)$kp['id'] ?>"
                             data-bpm-label="Calon #0<?= (int)$kp['nomor_urut'] ?> · <?= e($kp['nama_calon']) ?>"
                             onclick="selectBallotCard('BPM', <?= (int)$kp['id'] ?>, this)">
                            <div class="ballot-head">
                                <div class="ballot-num">0<?= (int)$kp['nomor_urut'] ?></div>
                                <img src="<?= e($fotoUrl) ?>" alt="Calon" class="ballot-photo">
                                <div>
                                    <div class="ballot-names"><?= e($kp['nama_calon']) ?></div>
                                    <div class="ballot-sub"><?= e($kp['jurusan_kelas'] ?? '-') ?></div>
                                </div>
                            </div>
                            <div class="ballot-visi">
                                <strong>Visi:</strong> <?= e($kp['visi']) ?>
                            </div>
                            <div class="ballot-action-indicator">
                                <span class="select-status-text">Klik untuk memilih</span>
                                <span>→</span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="card" style="padding:14px 18px;flex-direction:row;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;">
                <span class="text-muted">Pastikan Anda telah memilih 1 kandidat BEM dan 1 kandidat BPM.</span>
                <button type="button" class="btn btn--primary" id="btnOpenConfirmVote" onclick="openConfirmVoteModal()" disabled>Tinjau & Konfirmasi Suara →</button>
            </div>
        </div>

        <!-- PANEL 4: SUARA BERHASIL MASUK (SESUAI ALUR APP.MD) -->
        <div id="panelStep4" class="card scanner-card text-center" style="display:none;">
            <div class="mb-2">
                <span class="badge success dot">TRANSAKSI ATOMIK BERHASIL</span>
            </div>
            <h2 class="hero-title" style="font-size:20px;">Suara Berhasil Masuk</h2>
            <p class="text-muted mt-2 mb-3">Terima kasih! Hak pilih Anda untuk Presiden BEM dan Ketua Umum BPM telah direkam secara rahasia dan sesi bilik telah ditutup kembali.</p>
            <div class="d-flex justify-center">
                <a href="<?= e(route_url('live_count_bem')) ?>" class="btn btn--primary btn--sm w-full">Lihat Live Count Hasil Sementara</a>
            </div>
        </div>
    </main>
</div>

<!-- Modal Konfirmasi Pilihan Akhir -->
<div class="modal-backdrop" id="confirmVoteModal">
    <div class="modal-dialog modal-sm">
        <div class="modal-head">
            <h3 class="modal-title">Konfirmasi Surat Suara</h3>
            <button type="button" class="modal-close" data-modal-close="confirmVoteModal">×</button>
        </div>
        <div class="modal-body">
            <p class="text-muted mb-2">Pilihan baru akan direkam secara atomik setelah Anda menekan tombol <strong>Kirim Suara Sah</strong>.</p>
            <div class="card mb-2" style="padding:10px 12px;background:var(--bg-hover);">
                <span class="eyebrow">Pilihan BEM</span>
                <div class="fw-700 mt-1" id="confirmBemLabel">-</div>
            </div>
            <div class="card" style="padding:10px 12px;background:var(--bg-hover);">
                <span class="eyebrow" style="color:var(--purple);">Pilihan BPM</span>
                <div class="fw-700 mt-1" id="confirmBpmLabel">-</div>
            </div>
        </div>
        <div class="modal-foot">
            <button type="button" class="btn btn--ghost btn--sm" data-modal-close="confirmVoteModal">Ubah Pilihan</button>
            <button type="button" class="btn btn--success btn--sm" id="btnSubmitAtomicVote" onclick="submitConfirmVote()">Kirim Suara Sah</button>
        </div>
    </div>
</div>

<?php require BASE_PATH . '/views/layouts/footer.php'; ?>
