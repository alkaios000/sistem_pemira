<?php
/**
 * PORTAL QR BILIK - Adminator 2026 Redesign (Fitur 3)
 * Menampilkan QR Code Dinamis per Bilik Suara yang berotasi otomatis & terkunci seketika saat di-scan.
 */
require_once BASE_PATH . '/models/bilik_model.php';
require_once BASE_PATH . '/models/user_model.php';

$pdo = get_db_connection();
$pageTitle = 'Portal QR Bilik';
$isFullScreenLayout = !is_authenticated();

$bilikList = get_all_biliks($pdo);
$selectedBooth = isset($_GET['booth']) ? (int)$_GET['booth'] : (int)($bilikList[0]['nomor_bilik'] ?? 1);
$globalPortal = get_setting_value($pdo, 'status_portal_global', 'BUKA');

$extraScripts = [
    'https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js',
    asset_url('js/modules/portal_qr.js')
];

require BASE_PATH . '/views/layouts/header.php';
?>

<?php if ($isFullScreenLayout): ?>
<header class="lc-standalone-topbar">
    <div class="d-flex align-center gap-2">
        <span class="fw-700">Sistem PEMIRA · Portal QR Bilik Suara</span>
    </div>
    <div class="d-flex align-center gap-2">
        <a href="<?= e(route_url('bilik_pemilihan')) ?>" class="btn btn--soft-primary btn--xs">Bilik Pemilihan</a>
        <a href="<?= e(route_url('login')) ?>" class="btn btn--ghost btn--xs">Login Panitia</a>
    </div>
</header>
<div class="content">
<?php endif; ?>

<section class="hero">
    <div class="hero-text">
        <span class="eyebrow">Dynamic Single-Use Token · Fitur 3</span>
        <h1 class="hero-title">Portal QR Code Bilik Suara</h1>
    </div>
    <div class="hero-actions">
        <select id="portalBoothSelect" class="select" style="width:auto;padding:6px 28px 6px 10px;font-size:12px;" onchange="changeActiveBooth(this.value)">
            <?php foreach ($bilikList as $b): ?>
                <option value="<?= (int)$b['nomor_bilik'] ?>" <?= (int)$b['nomor_bilik'] === $selectedBooth ? 'selected' : '' ?>>
                    Bilik #0<?= (int)$b['nomor_bilik'] ?> · <?= e($b['nama_bilik']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <button type="button" class="btn btn--soft-primary btn--sm" onclick="forceRotateBoothToken()">Rotasi Token Baru</button>
    </div>
</section>

<div class="qr-layout" id="portalQrRoot" data-active-booth="<?= (int)$selectedBooth ?>">
    <!-- Kolom Kiri: Layar QR Code Bilik Aktif -->
    <div class="card">
        <div class="card-head">
            <div class="card-title-wrap">
                <span class="eyebrow" id="qrBoothEyebrow">BILIK #0<?= (int)$selectedBooth ?></span>
                <h3 class="card-title" id="qrBoothTitle">Memuat Bilik...</h3>
            </div>
            <span class="badge success dot" id="qrBoothStatusBadge">IDLE</span>
        </div>

        <div class="qr-stage">
            <!-- State 1: IDLE (QR Tampil & Berotasi) -->
            <div id="qrStateIdle" style="width:100%;">
                <div class="qr-box">
                    <div id="qrCodeCanvas"></div>
                </div>

                <div class="mt-2">
                    <div class="qr-timer-pill" id="qrTimerPill">
                        <span>Rotasi Otomatis:</span>
                        <span id="qrRemainingSec">8s</span>
                    </div>
                </div>

                <div class="mt-2">
                    <span class="mono text-light" style="font-size:10.5px;">Token: <strong class="text-primary" id="qrTokenShort">-</strong></span>
                </div>

                <div class="mt-3 pt-2" style="border-top:1px dashed var(--border);">
                    <a href="#" id="qrDirectSimulateLink" target="_blank" class="btn btn--soft-primary btn--sm w-full">Simulasi Scan / Buka Link Bilik ↗</a>
                </div>
            </div>

            <!-- State 2: IN_USE (QR Disembunyikan Seketika 0ms Saat Sedang Dipakai) -->
            <div id="qrStateInUse" style="display:none;width:100%;">
                <span class="badge primary dot mb-2">SESI AKTIF TERKUNCI</span>
                <div class="fw-700" style="font-size:15px;">Bilik Sedang Digunakan</div>
                <p class="text-muted mt-1" style="font-size:12px;">QR Code disembunyikan otomatis agar tidak dapat dipindai perangkat kedua.</p>
                <div class="card mt-3" style="padding:10px 12px;background:var(--bg-card);">
                    <div class="mono text-muted">NIM Aktif: <strong class="text-primary" id="qrActiveNimMasked">Menunggu input NIM...</strong></div>
                    <div class="mono text-light mt-1">Batas Sesi: <span id="qrLockRemainingSec">180s</span></div>
                </div>
            </div>

            <!-- State 3: CLOSED -->
            <div id="qrStateClosed" style="display:none;width:100%;">
                <span class="badge danger dot mb-2">PORTAL DITUTUP</span>
                <div class="fw-700" style="font-size:15px;">Bilik Suara Ditutup Sementara</div>
                <p class="text-muted mt-1" style="font-size:12px;">Panitia sedang menutup sesi pemindaian pada bilik ini.</p>
            </div>
        </div>
    </div>

    <!-- Kolom Kanan: Status Seluruh Bilik Suara -->
    <div class="card">
        <div class="card-head">
            <div class="card-title-wrap">
                <span class="eyebrow">Multi-Booth Monitor</span>
                <h3 class="card-title">Status Terminal Bilik Suara</h3>
            </div>
            <span class="badge info">Real-Time</span>
        </div>

        <div class="data-table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Bilik</th>
                        <th>Nama & Lokasi</th>
                        <th>Status</th>
                        <th>Suara Masuk</th>
                        <th class="text-right">Pilih Layar</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($bilikList as $b): ?>
                        <?php $st = $b['status_bilik']; ?>
                        <tr class="data-row <?= (int)$b['nomor_bilik'] === $selectedBooth ? 'is-selected' : '' ?>">
                            <td><span class="data-cell-mono fw-600">#0<?= (int)$b['nomor_bilik'] ?></span></td>
                            <td>
                                <div class="fw-600"><?= e($b['nama_bilik']) ?></div>
                                <div class="text-muted" style="font-size:11px;"><?= e($b['lokasi_bilik'] ?? '-') ?></div>
                            </td>
                            <td>
                                <?php if ($st === 'CLOSED'): ?>
                                    <span class="badge danger dot">CLOSED</span>
                                <?php elseif ($st === 'IN_USE'): ?>
                                    <span class="badge primary dot">IN_USE</span>
                                <?php else: ?>
                                    <span class="badge success dot">IDLE</span>
                                <?php endif; ?>
                            </td>
                            <td><span class="data-cell-mono fw-600"><?= number_format((int)$b['total_suara_bilik']) ?></span></td>
                            <td class="text-right">
                                <a href="<?= e(route_url('portal_qr', ['booth' => (int)$b['nomor_bilik']])) ?>" class="btn btn--ghost btn--xs">Tampilkan QR</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php if ($isFullScreenLayout): ?>
</div>
<?php endif; ?>

<?php require BASE_PATH . '/views/layouts/footer.php'; ?>
