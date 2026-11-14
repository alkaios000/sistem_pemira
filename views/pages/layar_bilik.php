<?php
/**
 * LAYAR KIOSK KHUSUS PER BILIK - Mode Terisolasi Laptop Bilik Suara
 * Menampilkan QR Code Dinamis hanya untuk 1 bilik terpilih secara layar penuh.
 * Dapat diakses melalui Tautan Kiosk kriptografis per bilik TANPA login panel admin
 * (sehingga laptop di bilik fisik tidak memiliki hak akses KPRM/Superadmin),
 * maupun pratinjau oleh panitia/pengawas yang sedang login.
 */
require_once BASE_PATH . '/models/bilik_model.php';
require_once BASE_PATH . '/models/user_model.php';
require_once BASE_PATH . '/models/log_model.php';

header('Cache-Control: no-store, no-cache, must-revalidate, private');
header('Pragma: no-cache');
header('Referrer-Policy: no-referrer');
header('X-Frame-Options: DENY');
header("Content-Security-Policy: frame-ancestors 'none'");

$pdo = get_db_connection();
$nomorBilik = isset($_GET['booth']) ? (int) $_GET['booth'] : 0;
$keyParam = trim((string) ($_GET['key'] ?? ''));
$ip = get_client_ip();

$bilik = $nomorBilik >= 1 ? find_bilik_by_nomor($pdo, $nomorBilik) : null;
$accessError = null;
$httpCode = 200;

$hasStaffPreview = is_authenticated() && in_array(current_user_role(), ['superadmin', 'kprm', 'banwasra'], true);
$hasKioskGrant = false;

if ($keyParam !== '') {
    $rateLimit = check_rate_limit($pdo, 'kiosk_key_ip', $ip, 300);
    if ($rateLimit['blocked']) {
        $httpCode = 429;
        $wait = max(60, (int) $rateLimit['remaining_seconds']);
        $accessError = "Terlalu banyak percobaan kunci akses layar bilik tidak valid. Silakan tunggu {$wait} detik.";
    } elseif (!$bilik) {
        record_rate_limit_hit($pdo, 'kiosk_key_ip', $ip, 10, 300, 300);
        record_audit_log($pdo, [
            'bilik_id'     => null,
            'log_category' => 'pemilih',
            'device_id'    => 'kiosk_unknown',
            'ip_address'   => $ip,
            'user_agent'   => get_client_user_agent(),
            'status_log'   => 'FRAUD',
            'kode_event'   => 'KIOSK_INVALID_KEY_ATTEMPT',
            'judul_event'  => 'Percobaan Akses Layar Bilik Tidak Valid',
            'keterangan'   => "IP {$ip} mencoba membuka layar bilik nomor #{$nomorBilik} yang tidak terdaftar menggunakan kunci kiosk.",
        ]);
        $httpCode = 403;
        $accessError = 'Akses layar bilik ditolak. Nomor bilik atau kunci akses tidak valid.';
    } else {
        $expectedKey = ensure_bilik_kiosk_token($pdo, $bilik);
        if (!hash_equals($expectedKey, $keyParam)) {
            record_rate_limit_hit($pdo, 'kiosk_key_ip', $ip, 10, 300, 300);
            unset($_SESSION['kiosk_booth_access'][(int) $bilik['id']]);
            record_audit_log($pdo, [
                'bilik_id'     => (int) $bilik['id'],
                'log_category' => 'pemilih',
                'device_id'    => 'kiosk_booth_' . (int) $bilik['nomor_bilik'],
                'ip_address'   => $ip,
                'user_agent'   => get_client_user_agent(),
                'status_log'   => 'FRAUD',
                'kode_event'   => 'KIOSK_INVALID_KEY_ATTEMPT',
                'judul_event'  => 'Percobaan Kunci Layar Bilik Salah',
                'keterangan'   => "IP {$ip} memasukkan kunci akses yang salah atau telah dicabut pada Bilik #0{$bilik['nomor_bilik']} ({$bilik['nama_bilik']}).",
            ]);
            $httpCode = 403;
            $accessError = 'Kunci akses layar bilik tidak cocok atau telah diputar oleh panitia.';
        } else {
            clear_rate_limit($pdo, 'kiosk_key_ip', $ip);
            if (!$hasStaffPreview && session_status() === PHP_SESSION_ACTIVE) {
                session_regenerate_id(true);
            }
            // Ikat sesi secara eksklusif HANYA untuk 1 bilik ini
            $_SESSION['kiosk_booth_access'] = [
                (int) $bilik['id'] => hash('sha256', $expectedKey),
            ];
            redirect(route_url('layar_bilik', ['booth' => (int) $bilik['nomor_bilik']]));
        }
    }
} else {
    if (!$bilik) {
        $httpCode = 404;
        $accessError = 'Bilik suara tidak ditemukan atau parameter nomor bilik belum ditentukan.';
    } else {
        $hasKioskGrant = verify_bilik_kiosk_session($pdo, $bilik);
        if (!$hasKioskGrant && !$hasStaffPreview) {
            unset($_SESSION['kiosk_booth_access'][(int) $bilik['id']]);
            $httpCode = 403;
            $accessError = 'Layar bilik suara terkunci. Buka menggunakan Tautan Aman Bilik resmi dari panel KPRM atau Superadmin.';
        }
    }
}

if ($httpCode !== 200) {
    http_response_code($httpCode);
}

$isFullScreenLayout = true;
$isAdminPreview = $hasStaffPreview;
$skipCsrfMeta = !$hasStaffPreview;
$pageTitle = $bilik ? ('Layar Bilik #0' . (int) $bilik['nomor_bilik']) : 'Layar Bilik Terkunci';

$extraScripts = $accessError === null
    ? [
        asset_url('js/vendor/qrcode.min.js') . '?v=1',
        asset_url('js/modules/layar_bilik.js') . '?v=1',
    ]
    : [];

require BASE_PATH . '/views/layouts/header.php';
?>

<div class="kiosk-shell"
     id="layarBilikRoot"
     data-booth="<?= (int) ($bilik['nomor_bilik'] ?? 0) ?>">

    <header class="kiosk-topbar">
        <div class="kiosk-brand">
            <img src="<?= e(asset_url('img/ubw_logo.png')) ?>" alt="Logo UBW" class="brand-img-sm">
            <div>
                <div class="kiosk-brand-title">
                    <?= $bilik ? e($bilik['nama_bilik']) . ' · BILIK #0' . (int) $bilik['nomor_bilik'] : 'Sistem E-Voting PEMIRA' ?>
                </div>
                <div class="kiosk-brand-sub">
                    <?= $bilik && !empty($bilik['lokasi_bilik']) ? e($bilik['lokasi_bilik']) : 'Terminal QR Pemilihan Kampus' ?>
                </div>
            </div>
        </div>

        <div class="d-flex align-center gap-2" style="flex-wrap:wrap;">
            <?php if ($accessError === null): ?>
                <span class="badge success dot" id="kioskConnBadge">SINKRON REAL-TIME</span>
                <button type="button" class="btn btn--ghost btn--sm" id="btnKioskFullscreen">Layar Penuh</button>
            <?php endif; ?>
            <?php if ($hasStaffPreview): ?>
                <a href="<?= e(route_url('portal_qr', ['booth' => (int) ($bilik['nomor_bilik'] ?? 1)])) ?>" class="btn btn--soft-primary btn--sm">← Portal QR</a>
            <?php endif; ?>
        </div>
    </header>

    <?php if ($isAdminPreview && $accessError === null): ?>
        <div class="kiosk-preview-notice">
            <strong>Mode Pratinjau Staf:</strong> Browser ini sedang login sebagai <?= e(strtoupper(current_user_role())) ?>. Untuk laptop khusus bilik di TPS, gunakan tombol <strong>Salin Link Kiosk Aman</strong> di Portal QR pada browser terpisah yang belum login admin.
        </div>
    <?php endif; ?>

    <main class="kiosk-main">
        <?php if ($accessError !== null): ?>
            <section class="card kiosk-card text-center">
                <div class="mb-2"><span class="badge danger dot">AKSES TERKUNCI (HTTP <?= (int) $httpCode ?>)</span></div>
                <h1 class="kiosk-heading">Layar Bilik Tidak Dapat Dibuka</h1>
                <p class="text-muted mt-2 mb-0" style="font-size:13.5px;line-height:1.6;">
                    <?= e($accessError) ?>
                </p>
            </section>
        <?php else: ?>
            <section class="card kiosk-card">
                <div class="kiosk-card-header">
                    <span class="badge primary" id="kioskBoothPill">BILIK #0<?= (int) $bilik['nomor_bilik'] ?></span>
                    <span class="badge success dot" id="kioskStatusBadge">MEMUAT...</span>
                </div>

                <!-- State 1: IDLE (QR Berotasi) -->
                <div id="kioskStateIdle" class="kiosk-state-box">
                    <h1 class="kiosk-heading">Pindai QR Code untuk Memilih</h1>
                    <p class="text-muted kiosk-lead">Arahkan kamera perangkat pemilih ke QR Code di bawah ini atau scan melalui halaman Bilik Pemilihan.</p>

                    <div class="kiosk-qr-box" id="kioskQrBox" oncontextmenu="return false;">
                        <div id="kioskQrCanvas"></div>
                    </div>

                    <div class="kiosk-timer-wrap">
                        <div class="qr-timer-pill" id="kioskTimerPill">
                            <span>Rotasi QR otomatis dalam</span>
                            <span id="kioskRemainingSec">8s</span>
                        </div>
                        <div class="progress kiosk-progress">
                            <div class="progress-bar" id="kioskProgressBar" style="width:100%;"></div>
                        </div>
                    </div>

                    <p class="kiosk-foot-note">QR Code otomatis hangus seketika saat berhasil dipindai dan hanya berlaku untuk 1 perangkat.</p>
                </div>

                <!-- State 2: IN_USE (0ms Hidden without exposing NIM) -->
                <div id="kioskStateInUse" class="kiosk-state-box" style="display:none;">
                    <div class="kiosk-lock-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>
                    </div>
                    <span class="badge primary dot mb-2">SESI PEMILIHAN SEDANG BERLANGSUNG</span>
                    <h1 class="kiosk-heading">Bilik Sedang Digunakan</h1>
                    <p class="text-muted kiosk-lead">Silakan selesaikan proses verifikasi NIM dan pemilihan kandidat BEM &amp; BPM di perangkat Anda. QR Code baru akan muncul otomatis setelah selesai.</p>
                    <div class="kiosk-lock-timer">
                        Sisa batas sesi: <strong class="mono text-primary" id="kioskLockRemainingSec">180s</strong>
                    </div>
                </div>

                <!-- State 3: CLOSED -->
                <div id="kioskStateClosed" class="kiosk-state-box" style="display:none;">
                    <span class="badge danger dot mb-2">PORTAL BILIK DITUTUP</span>
                    <h1 class="kiosk-heading">Bilik Suara Ditutup Sementara</h1>
                    <p class="text-muted kiosk-lead">Panitia sedang menonaktifkan sementara bilik suara ini atau portal pemungutan suara global sedang dijeda. Silakan hubungi meja panitia.</p>
                </div>

                <!-- State 4: REVOKED (Kunci kiosk diputar panitia saat layar terbuka) -->
                <div id="kioskStateRevoked" class="kiosk-state-box" style="display:none;">
                    <span class="badge danger dot mb-2">SESI LAYAR DICABUT</span>
                    <h1 class="kiosk-heading">Kunci Layar Bilik Telah Diputar</h1>
                    <p class="text-muted kiosk-lead" id="kioskRevokedMessage">Kunci akses layar kiosk bilik ini telah direset atau dicabut oleh panitia. Minta tautan kiosk baru kepada KPRM/Superadmin.</p>
                </div>
            </section>
        <?php endif; ?>
    </main>
</div>

<?php require BASE_PATH . '/views/layouts/footer.php'; ?>
