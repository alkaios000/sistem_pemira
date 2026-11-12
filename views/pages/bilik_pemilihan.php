<?php
/**
 * BILIK PEMILIHAN SUARA - Adminator 2026 Redesign (Fitur 4)
 * Tahap: 1. Klaim QR Bilik (Kamera/Link/Token) -> 2. Verifikasi NIM -> 3. Pilih BEM & BPM -> 4. Selesai
 */
require_once BASE_PATH . '/models/kandidat_model.php';
require_once BASE_PATH . '/models/bilik_model.php';
require_once BASE_PATH . '/models/log_model.php';

$pdo = get_db_connection();
$pageTitle = 'Bilik Pemilihan Suara';
$isFullScreenLayout = true;

$kandidatBem = get_kandidats_by_kategori($pdo, 'BEM');
$kandidatBpm = get_kandidats_by_kategori($pdo, 'BPM');
$bilikList = get_all_biliks($pdo);

$urlBooth = isset($_GET['booth']) ? (int)$_GET['booth'] : 0;
$urlToken = isset($_GET['token']) ? trim((string)$_GET['token']) : '';
$activeSession = $_SESSION['active_voting_session'] ?? null;

// Validasi apakah sesi PHP masih cocok dengan status IN_USE di database
if ($activeSession && !empty($activeSession['bilik_id'])) {
    $dbBilik = find_bilik_by_id($pdo, (int)$activeSession['bilik_id']);
    if (
        !$dbBilik
        || $dbBilik['status_bilik'] !== 'IN_USE'
        || empty($dbBilik['active_session_token'])
        || !hash_equals((string)$dbBilik['active_session_token'], (string)($activeSession['voting_session_token'] ?? ''))
    ) {
        unset($_SESSION['active_voting_session']);
        $activeSession = null;
    }
}

// Deteksi percobaan akses paksa step=2 atau step=3 lewat URL tanpa sesi QR aktif (DIRECT_URL_BYPASS)
if (isset($_GET['step']) && in_array((int)$_GET['step'], [2, 3], true) && !$activeSession) {
    record_audit_log($pdo, [
        'bilik_id'           => $urlBooth > 0 ? $urlBooth : null,
        'nim'                => null,
        'device_id'          => $_COOKIE['pemira_voted_device'] ?? 'direct_url_client',
        'fingerprint_hash'   => null,
        'browser_session_id' => $_SESSION['voter_browser_session_id'] ?? session_id(),
        'ip_address'         => get_client_ip(),
        'user_agent'         => get_client_user_agent(),
        'status_log'         => 'FRAUD',
        'kode_event'         => 'DIRECT_URL_BYPASS',
        'judul_event'        => 'Percobaan Bypass URL Langsung ke Form Pemilihan',
        'keterangan'         => 'Perangkat mencoba melompat langsung ke parameter step=' . (int)$_GET['step'] . ' tanpa klaim QR bilik yang sah.',
    ]);
    set_flash('danger', 'Peringatan Keamanan: Akses langsung tanpa memindai QR Code bilik ditolak dan telah dicatat ke Log Keamanan.');
}

$extraScripts = [
    'https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js',
    asset_url('js/modules/voting_client.js')
];

require BASE_PATH . '/views/layouts/header.php';
?>

<div class="voting-shell"
     id="votingRoot"
     data-url-booth="<?= (int)$urlBooth ?>"
     data-url-token="<?= e($urlToken) ?>"
     data-session-bilik-id="<?= (int)($activeSession['bilik_id'] ?? 0) ?>"
     data-session-nomor-bilik="<?= (int)($activeSession['nomor_bilik'] ?? 0) ?>"
     data-session-nama-bilik="<?= e($activeSession['nama_bilik'] ?? '') ?>"
     data-session-token="<?= e($activeSession['voting_session_token'] ?? '') ?>"
     data-session-device-id="<?= e($activeSession['device_id'] ?? '') ?>"
     data-verified-nim="<?= e($activeSession['verified_nim'] ?? '') ?>"
     data-verified-nama="<?= e($activeSession['verified_nama'] ?? '') ?>"
     data-verified-kelas="<?= e($activeSession['verified_kelas'] ?? '') ?>"
     data-verified-jurusan="<?= e($activeSession['verified_jurusan'] ?? '') ?>">

    <!-- Topbar Bilik (Tanpa Placeholder Logo) -->
    <header class="voting-topbar">
        <div class="d-flex align-center gap-2">
            <span class="fw-700" style="font-size:13.5px;">Sistem PEMIRA · Bilik Pemilihan Suara</span>
        </div>

        <div class="d-flex align-center gap-2">
            <a href="<?= e(route_url('portal_qr')) ?>" class="btn btn--soft-primary btn--xs">Layar Portal QR</a>
            <?php if (is_authenticated()): ?>
                <a href="<?= e(route_url('dashboard')) ?>" class="btn btn--ghost btn--xs">Panel Admin</a>
            <?php else: ?>
                <a href="<?= e(route_url('login')) ?>" class="btn btn--ghost btn--xs">Login Panitia</a>
            <?php endif; ?>
        </div>
    </header>

    <main class="voting-container">
        <!-- Stepper -->
        <div class="voting-steps" style="flex-wrap:wrap;">
            <div class="step-pill is-active" id="stepPill1">
                <span class="num">1</span>
                <span>Scan QR Bilik</span>
            </div>
            <span class="text-light">/</span>
            <div class="step-pill" id="stepPill2">
                <span class="num">2</span>
                <span>Verifikasi NIM</span>
            </div>
            <span class="text-light">/</span>
            <div class="step-pill" id="stepPill3">
                <span class="num">3</span>
                <span>Surat Suara BEM & BPM</span>
            </div>
        </div>

        <!-- PANEL 1: SCAN KAMERA QR / KLAIM TOKEN QR BILIK -->
        <div id="panelStep1" class="card scanner-card">
            <div class="card-head">
                <div class="card-title-wrap">
                    <span class="eyebrow">Tahap 01 · Otentikasi Bilik</span>
                    <h3 class="card-title">Pindai QR Code Bilik Suara</h3>
                </div>
                <span class="badge primary dot">One-Time QR</span>
            </div>

            <div id="qrCameraReader" class="scanner-viewport" style="display:none;"></div>

            <div class="d-flex gap-2 mb-3">
                <button type="button" class="btn btn--soft-primary btn--sm w-full" id="btnStartQrCamera" onclick="startQrCamera()">Buka Kamera Pemindai QR</button>
                <button type="button" class="btn btn--ghost btn--sm" id="btnStopQrCamera" onclick="stopQrCamera()" style="display:none;">Tutup Kamera</button>
            </div>

            <div style="border-top:1px dashed var(--border);padding-top:12px;">
                <div class="field">
                    <label class="field-label">Pilih Bilik Suara</label>
                    <select id="claimBoothSelect" class="select">
                        <?php foreach ($bilikList as $b): ?>
                            <option value="<?= (int)$b['nomor_bilik'] ?>" <?= ($urlBooth === (int)$b['nomor_bilik']) ? 'selected' : '' ?>>
                                Bilik #0<?= (int)$b['nomor_bilik'] ?> · <?= e($b['nama_bilik']) ?> (<?= e($b['status_bilik']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="field">
                    <label class="field-label">Token QR Bilik</label>
                    <input type="text" id="claimTokenInput" class="input mono" value="<?= e($urlToken) ?>" placeholder="Otomatis terisi saat scan QR...">
                </div>

                <button type="button" class="btn btn--primary btn-block mt-2" id="btnClaimQr" onclick="submitClaimQr()">Kunci Bilik & Lanjutkan →</button>
            </div>
        </div>

        <!-- PANEL 2: VERIFIKASI NIM MAHASISWA -->
        <div id="panelStep2" class="card scanner-card" style="display:none;">
            <div class="card-head">
                <div class="card-title-wrap">
                    <span class="eyebrow">Tahap 02 · Validasi DPT</span>
                    <h3 class="card-title">Masukkan NIM Anda</h3>
                </div>
                <span class="badge success dot" id="lockedBoothLabel">Bilik Terkunci</span>
            </div>

            <p class="text-muted mb-3">Masukkan Nomor Induk Mahasiswa (NIM) yang terdaftar di DPT. Maksimal 3 kali percobaan salah.</p>

            <div class="field">
                <label class="field-label">Nomor Induk Mahasiswa (NIM)</label>
                <input type="text" id="verifyNimInput" class="input mono" placeholder="Contoh: 240101003" autocomplete="off">
            </div>

            <button type="button" class="btn btn--primary btn-block mt-2" id="btnVerifyNim" onclick="submitVerifyNim()">Verifikasi NIM & Buka Surat Suara →</button>
        </div>

        <!-- PANEL 3: SURAT SUARA BEM & BPM -->
        <div id="panelStep3" style="display:none;">
            <div class="card mb-3" style="padding:12px 16px;background:var(--primary-soft);border-color:rgba(37,99,235,0.2);">
                <div class="d-flex justify-between align-center flex-wrap gap-2">
                    <div>
                        <span class="eyebrow">Pemilih Terverifikasi</span>
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
                        <span class="eyebrow">Surat Suara 01 · Eksekutif</span>
                        <h2 class="hero-title" style="font-size:17px;">Calon Presiden & Wakil Presiden BEM</h2>
                    </div>
                    <span class="badge primary">Pilih 1 Paslon</span>
                </div>

                <div class="ballot-grid">
                    <?php foreach ($kandidatBem as $kb): ?>
                        <?php $fotoUrl = !empty($kb['foto_path']) ? upload_url($kb['foto_path']) : asset_url('img/default_candidate.svg'); ?>
                        <div class="ballot-card"
                             data-bem-id="<?= (int)$kb['id'] ?>"
                             data-bem-label="Paslon #0<?= (int)$kb['nomor_urut'] ?> · <?= e($kb['nama_calon']) ?><?= !empty($kb['nama_wakil']) ? ' & ' . e($kb['nama_wakil']) : '' ?>"
                             onclick="selectBallotCard('BEM', <?= (int)$kb['id'] ?>, this)">
                            <div class="ballot-head">
                                <div class="ballot-num">0<?= (int)$kb['nomor_urut'] ?></div>
                                <img src="<?= e($fotoUrl) ?>" alt="Paslon" class="ballot-photo">
                                <div>
                                    <div class="ballot-names"><?= e($kb['nama_calon']) ?><?= !empty($kb['nama_wakil']) ? ' & ' . e($kb['nama_wakil']) : '' ?></div>
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
                        <span class="eyebrow" style="color:var(--purple);">Surat Suara 02 · Legislatif</span>
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
            <div class="d-flex justify-between gap-2">
                <a href="<?= e(route_url('live_count_bem')) ?>" class="btn btn--primary btn--sm w-full">Lihat Live Count</a>
                <a href="<?= e(route_url('bilik_pemilihan')) ?>" class="btn btn--ghost btn--sm w-full">Kembali ke Layar Awal</a>
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
