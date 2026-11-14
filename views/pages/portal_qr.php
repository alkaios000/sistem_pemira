<?php
/**
 * PORTAL QR & MANAJEMEN BILIK - Adminator 2026 Redesign (Fitur 2 & 3 Terpadu)
 * Menampilkan QR Code Dinamis per Bilik Suara yang berotasi otomatis & terkunci seketika saat di-scan,
 * sekaligus menyediakan kontrol penuh Manajemen Bilik Suara secara real-time.
 */
require_role(['superadmin', 'kprm', 'banwasra']);
require_once BASE_PATH . '/models/bilik_model.php';
require_once BASE_PATH . '/models/user_model.php';

$pdo = get_db_connection();
$pageTitle = 'Portal QR Bilik';
$active_menu = 'portal_qr';
$canWrite = can_manage_pemira();
$canViewLogs = can_view_logs();
$excludeSuperadminLogs = is_banwasra();
$isFullScreenLayout = false;

$bilikList = get_all_biliks_sanitized($pdo, $canViewLogs, $excludeSuperadminLogs);
$selectedBooth = isset($_GET['booth']) ? (int) $_GET['booth'] : (int) ($bilikList[0]['nomor_bilik'] ?? 1);
$globalPortal = get_setting_value($pdo, 'status_portal_global', 'BUKA');
$wajibCheckin = get_setting_value($pdo, 'wajib_checkin_panitia', '0');
$liveCountPublik = get_setting_value($pdo, 'live_count_publik', 'BUKA');

$totalBilik = count($bilikList);
$bilikIdle = 0;
$bilikInUse = 0;
$bilikClosed = 0;
foreach ($bilikList as $b) {
    $effSt = ($globalPortal !== 'BUKA') ? 'CLOSED' : $b['status_bilik'];
    if ($effSt === 'IDLE') $bilikIdle++;
    elseif ($effSt === 'IN_USE') $bilikInUse++;
    else $bilikClosed++;
}

$extraScripts = [
    asset_url('js/vendor/qrcode.min.js') . '?v=1',
    asset_url('js/modules/portal_qr.js') . '?v=2',
];

require BASE_PATH . '/views/layouts/header.php';
?>

<section class="hero" style="justify-content:space-between;flex-wrap:wrap;gap:8px;">
    <div class="d-flex align-center gap-2 flex-wrap">
        <select id="portalBoothSelect" class="select" style="width:auto;padding:6px 28px 6px 10px;font-size:12px;" onchange="changeActiveBooth(this.value)">
            <?php foreach ($bilikList as $b): ?>
                <option value="<?= (int)$b['nomor_bilik'] ?>" <?= (int)$b['nomor_bilik'] === $selectedBooth ? 'selected' : '' ?>>
                    Bilik #0<?= (int)$b['nomor_bilik'] ?> · <?= e($b['nama_bilik']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php if ($canWrite): ?>
            <button type="button" class="btn btn--soft-primary btn--sm" onclick="forceRotateBoothToken()">Rotasi Token Baru</button>
        <?php endif; ?>
    </div>

    <?php if ($canWrite): ?>
    <div class="hero-actions">
        <span id="bilikHeroGlobalControls" style="display:inline-flex;align-items:center;gap:6px;flex-wrap:wrap;">
            <form action="<?= e(action_url('bilik')) ?>" method="POST" style="display:inline;">
                <?= csrf_field() ?>
                <input type="hidden" name="sub_action" value="toggle_live_count_publik">
                <input type="hidden" name="live_count_publik" value="<?= $liveCountPublik === 'BUKA' ? 'TUTUP' : 'BUKA' ?>">
                <button type="submit" class="btn <?= $liveCountPublik === 'BUKA' ? 'btn--ghost' : 'btn--soft-warning' ?> btn--sm">
                    Live Count Publik: <?= $liveCountPublik === 'BUKA' ? 'Terbuka' : 'Tertutup' ?>
                </button>
            </form>

            <form action="<?= e(action_url('bilik')) ?>" method="POST" style="display:inline;">
                <?= csrf_field() ?>
                <input type="hidden" name="sub_action" value="toggle_wajib_checkin">
                <input type="hidden" name="wajib_checkin_panitia" id="inputWajibCheckin" value="<?= $wajibCheckin === '1' ? '0' : '1' ?>">
                <button type="submit" class="btn btn--ghost btn--sm" id="btnWajibCheckinLabel">
                    Check-In Meja: <?= $wajibCheckin === '1' ? 'Wajib' : 'Bebas' ?>
                </button>
            </form>

            <form action="<?= e(action_url('bilik')) ?>" method="POST" style="display:inline;">
                <?= csrf_field() ?>
                <input type="hidden" name="sub_action" value="toggle_global_portal">
                <input type="hidden" name="portal_status" id="inputGlobalPortal" value="<?= $globalPortal === 'BUKA' ? 'TUTUP' : 'BUKA' ?>">
                <button type="submit" class="btn <?= $globalPortal === 'BUKA' ? 'btn--soft-danger' : 'btn--soft-success' ?> btn--sm" id="btnGlobalPortalLabel">
                    <?= $globalPortal === 'BUKA' ? 'Tutup Semua Bilik (Global)' : 'Buka Semua Bilik (Global)' ?>
                </button>
            </form>
        </span>

        <button type="button" class="btn btn--primary btn--sm" onclick="openCreateBilikModal()">+ Tambah Bilik</button>
    </div>
    <?php else: ?>
    <div class="hero-actions">
        <span class="badge purple dot">Mode Viewer (Read-Only · Banwasra)</span>
        <span class="badge <?= $globalPortal === 'BUKA' ? 'success' : 'danger' ?> dot">Portal <?= e($globalPortal) ?></span>
    </div>
    <?php endif; ?>
</section>

<div class="qr-layout"
     id="portalQrRoot"
     data-active-booth="<?= (int)$selectedBooth ?>"
     data-is-admin="<?= $canWrite ? '1' : '0' ?>"
     data-can-write="<?= $canWrite ? '1' : '0' ?>"
     data-can-view-logs="<?= $canViewLogs ? '1' : '0' ?>">
    <!-- Kolom Kiri: Layar QR Code Bilik Aktif -->
    <div class="card">
        <div class="card-head">
            <div class="card-title-wrap">
                <span class="kandidat-urut" id="qrBoothEyebrow">BILIK #0<?= (int)$selectedBooth ?></span>
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

    <!-- Kolom Kanan: Status & Kontrol Seluruh Terminal Bilik Suara -->
    <div class="card">
        <div class="card-head" style="flex-wrap:wrap;gap:8px;">
            <div class="card-title-wrap">
                <h3 class="card-title">Status & Kontrol Terminal Bilik Suara</h3>
            </div>
            <div class="d-flex align-center gap-2 flex-wrap">
                <span class="badge neutral" id="qrSummaryTotal">Total: <?= $totalBilik ?></span>
                <span class="badge success dot" id="qrSummaryIdle">IDLE: <?= $bilikIdle ?></span>
                <span class="badge primary dot" id="qrSummaryInUse">IN_USE: <?= $bilikInUse ?></span>
                <span class="badge danger dot" id="qrSummaryClosed">CLOSED: <?= $bilikClosed ?></span>
            </div>
        </div>

        <div class="data-table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Bilik</th>
                        <th>Nama Bilik</th>
                        <th>Status</th>
                        <th>Token &amp; Sesi Aktif</th>
                        <th><?= $canViewLogs ? 'Suara &amp; Fraud' : 'Suara Masuk' ?></th>
                        <?php if ($canWrite): ?>
                            <th class="text-right">Kontrol Aksi</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody id="portalQrAllBiliksTbody">
                    <?php foreach ($bilikList as $b): ?>
                        <?php
                        $st = ($globalPortal !== 'BUKA') ? 'CLOSED' : $b['status_bilik'];
                        $isSel = ((int)$b['nomor_bilik'] === $selectedBooth);
                        $tokShort = !empty($b['token_hint']) ? strtoupper((string)$b['token_hint']) : '-';
                        $remLock = (int)($b['remaining_lock_seconds'] ?? 0);
                        $bJson = json_encode($b, JSON_HEX_APOS | JSON_HEX_QUOT);
                        ?>
                        <tr class="data-row <?= $isSel ? 'is-selected' : '' ?>" data-booth-num="<?= (int)$b['nomor_bilik'] ?>" style="cursor:pointer;" title="Klik baris untuk menampilkan QR bilik ini">
                            <td><span class="data-cell-mono fw-600">#0<?= (int)$b['nomor_bilik'] ?></span></td>
                            <td>
                                <div class="fw-600"><?= e($b['nama_bilik']) ?></div>
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
                            <td>
                                <div class="data-cell-mono fw-600"><?= e($tokShort) ?></div>
                                <?php if ($st === 'IN_USE'): ?>
                                    <div class="mono" style="font-size:10.5px;color:var(--primary);">
                                        NIM: <?= e($b['active_nim_masked'] ?: 'Verifikasi...') ?> · <?= (int)$remLock ?>s
                                    </div>
                                <?php elseif ($st === 'IDLE'): ?>
                                    <div class="text-muted mono" style="font-size:10.5px;">Rotasi Otomatis 8s</div>
                                <?php else: ?>
                                    <div class="text-muted mono" style="font-size:10.5px;">Token Dijeda</div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="d-flex align-center gap-2">
                                    <span class="data-cell-mono fw-600"><?= number_format((int)$b['total_suara_bilik']) ?> suara</span>
                                    <?php if ($canViewLogs && (int)$b['total_fraud_bilik'] > 0): ?>
                                        <span class="badge danger"><?= (int)$b['total_fraud_bilik'] ?> Fraud</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <?php if ($canWrite): ?>
                            <td class="text-right">
                                <div class="data-cell-actions">
                                    <form action="<?= e(action_url('bilik')) ?>" method="POST" data-confirm="Reset token QR dan lepas kunci sesi bilik ini?">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="sub_action" value="force_reset_bilik">
                                        <input type="hidden" name="id" value="<?= (int)$b['id'] ?>">
                                        <button type="submit" class="btn <?= $st === 'IN_USE' ? 'btn--soft-warning' : 'btn--ghost' ?> btn--xs">
                                            <?= $st === 'IN_USE' ? 'Lepas Kunci' : 'Reset' ?>
                                        </button>
                                    </form>
                                    <form action="<?= e(action_url('bilik')) ?>" method="POST">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="sub_action" value="toggle_bilik_status">
                                        <input type="hidden" name="id" value="<?= (int)$b['id'] ?>">
                                        <input type="hidden" name="target_status" value="<?= $b['status_bilik'] === 'CLOSED' ? 'IDLE' : 'CLOSED' ?>">
                                        <button type="submit" class="btn btn--ghost btn--xs">
                                            <?= $b['status_bilik'] === 'CLOSED' ? 'Buka' : 'Tutup' ?>
                                        </button>
                                    </form>
                                    <button type="button" class="btn btn--ghost btn--xs btn-edit-bilik" data-bilik-edit="<?= e($bJson) ?>">Edit</button>
                                    <form action="<?= e(action_url('bilik')) ?>" method="POST" data-confirm="Hapus bilik suara ini?">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="sub_action" value="delete_bilik">
                                        <input type="hidden" name="id" value="<?= (int)$b['id'] ?>">
                                        <button type="submit" class="btn btn--soft-danger btn--xs" title="Hapus Bilik">×</button>
                                    </form>
                                </div>
                            </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php if ($canWrite): ?>
<!-- Modal Form Tambah / Edit Bilik Suara -->
<div class="modal-backdrop" id="bilikModal">
    <div class="modal-dialog modal-sm">
        <div class="modal-head">
            <h3 class="modal-title" id="bilikModalTitle">Tambah Bilik Suara</h3>
            <button type="button" class="modal-close" data-modal-close="bilikModal">×</button>
        </div>
        <form action="<?= e(action_url('bilik')) ?>" method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="sub_action" id="bilik_sub_action" value="create_bilik">
            <input type="hidden" name="id" id="bilik_id" value="0">
            <div class="modal-body">
                <div class="field">
                    <label class="field-label">Nomor Urut Bilik</label>
                    <input type="number" name="nomor_bilik" id="bilik_nomor" class="input mono" min="1" value="<?= $totalBilik + 1 ?>" required>
                </div>
                <div class="field mb-0">
                    <label class="field-label">Nama Bilik</label>
                    <input type="text" name="nama_bilik" id="bilik_nama" class="input" placeholder="Bilik Suara 04" required>
                </div>
            </div>
            <div class="modal-foot">
                <button type="button" class="btn btn--ghost btn--sm" data-modal-close="bilikModal">Batal</button>
                <button type="submit" class="btn btn--primary btn--sm">Simpan</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php if ($isFullScreenLayout): ?>
</div>
<?php endif; ?>

<?php require BASE_PATH . '/views/layouts/footer.php'; ?>
