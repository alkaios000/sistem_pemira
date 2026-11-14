<?php
/**
 * MANAJEMEN BILIK - Adminator 2026 Redesign (Fitur 2)
 * Mengelola CRUD Bilik Suara, Pintasan Portal QR per Bilik, Kontrol Status Global/Per Bilik,
 * Monitoring Sesi & Token Real-Time, serta Force Reset Kunci Bilik.
 */
require_role(['superadmin', 'kprm', 'banwasra']);
require_once BASE_PATH . '/models/bilik_model.php';
require_once BASE_PATH . '/models/user_model.php';

$pdo = get_db_connection();
$pageTitle = 'Manajemen Bilik';
$canWrite = can_manage_pemira();
$canViewLogs = can_view_logs();
$excludeSuperadminLogs = is_banwasra();
$bilikList = get_all_biliks_sanitized($pdo, $canViewLogs, $excludeSuperadminLogs);
$globalPortal = get_setting_value($pdo, 'status_portal_global', 'BUKA');
$wajibCheckin = get_setting_value($pdo, 'wajib_checkin_panitia', '0');

$totalBilik = count($bilikList);
$bilikIdle = 0;
$bilikInUse = 0;
$bilikClosed = 0;
foreach ($bilikList as $b) {
    if ($b['status_bilik'] === 'IDLE') $bilikIdle++;
    elseif ($b['status_bilik'] === 'IN_USE') $bilikInUse++;
    else $bilikClosed++;
}

require BASE_PATH . '/views/layouts/header.php';
?>

<section class="hero">
    <div class="hero-actions">
        <button type="button" class="btn btn--soft-primary btn--sm" id="btnToggleLiveBilik" data-live="1">Live Sync: ON (2s)</button>

        <?php if ($canWrite): ?>
        <span id="bilikHeroGlobalControls" style="display:inline-flex;align-items:center;gap:6px;flex-wrap:wrap;">
            <form action="<?= e(action_url('bilik')) ?>" method="POST" style="display:inline;">
                <?= csrf_field() ?>
                <input type="hidden" name="sub_action" value="toggle_wajib_checkin">
                <input type="hidden" name="wajib_checkin_panitia" value="<?= $wajibCheckin === '1' ? '0' : '1' ?>">
                <button type="submit" class="btn btn--ghost btn--sm">
                    Check-In Meja: <?= $wajibCheckin === '1' ? 'Wajib' : 'Bebas' ?>
                </button>
            </form>

            <form action="<?= e(action_url('bilik')) ?>" method="POST" style="display:inline;">
                <?= csrf_field() ?>
                <input type="hidden" name="sub_action" value="toggle_global_portal">
                <input type="hidden" name="portal_status" value="<?= $globalPortal === 'BUKA' ? 'TUTUP' : 'BUKA' ?>">
                <button type="submit" class="btn <?= $globalPortal === 'BUKA' ? 'btn--soft-danger' : 'btn--soft-success' ?> btn--sm">
                    <?= $globalPortal === 'BUKA' ? 'Tutup Semua Bilik (Global)' : 'Buka Semua Bilik (Global)' ?>
                </button>
            </form>
        </span>

        <button type="button" class="btn btn--primary btn--sm" onclick="openCreateBilikModal()">+ Tambah Bilik</button>
        <?php else: ?>
            <span class="badge purple dot">Mode Viewer (Read-Only)</span>
        <?php endif; ?>
    </div>
</section>

<!-- KPI Row -->
<div class="kpi-grid">
    <div class="kpi-card">
        <div class="kpi-top">
            <span class="kpi-label">Total Bilik</span>
            <span class="kpi-pill info">TPS</span>
        </div>
        <div class="kpi-value" id="kpiTotalBilik"><?= $totalBilik ?></div>
        <div class="kpi-compare"><span>Terdaftar di database</span></div>
    </div>
    <div class="kpi-card">
        <div class="kpi-top">
            <span class="kpi-label">Bilik Siap (IDLE)</span>
            <span class="kpi-pill up">Online</span>
        </div>
        <div class="kpi-value" id="kpiBilikIdle"><?= $bilikIdle ?></div>
        <div class="kpi-compare"><span>Menunggu scan QR</span></div>
    </div>
    <div class="kpi-card">
        <div class="kpi-top">
            <span class="kpi-label">Sedang Dipakai</span>
            <span class="kpi-pill primary">IN_USE</span>
        </div>
        <div class="kpi-value" id="kpiBilikInUse"><?= $bilikInUse ?></div>
        <div class="kpi-compare"><span>Terkunci sesi aktif</span></div>
    </div>
    <div class="kpi-card">
        <div class="kpi-top">
            <span class="kpi-label">Ditutup (CLOSED)</span>
            <span class="kpi-pill down">Nonaktif</span>
        </div>
        <div class="kpi-value" id="kpiBilikClosed"><?= $bilikClosed ?></div>
        <div class="kpi-compare"><span>Tidak menerima scan</span></div>
    </div>
</div>

<!-- Filter & Grid Card Bilik Suara -->
<div class="grid">
    <section class="col-12 card">
        <div class="data-toolbar" style="padding: 0 0 14px 0; margin-bottom: 14px; border-bottom: 1px solid var(--border-soft);">
            <div class="data-toolbar-left">
                <div class="input-icon" style="flex:1;max-width:300px;">
                    <span class="ico"><svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg></span>
                    <input type="search" class="input" id="bilikSearchInput" placeholder="Cari nomor bilik, nama, atau lokasi...">
                </div>
            </div>
            <div class="data-toolbar-right">
                <select class="select" id="bilikStatusFilter" style="width:auto;padding:6px 28px 6px 10px;font-size:12px;">
                    <option value="ALL">Semua Status</option>
                    <option value="IDLE">IDLE (Siap)</option>
                    <option value="IN_USE">IN_USE (Dipakai)</option>
                    <option value="CLOSED">CLOSED (Ditutup)</option>
                </select>
            </div>
        </div>

        <div class="bilik-grid" id="bilikCardsGrid">
            <?php foreach ($bilikList as $b): ?>
                <?php
                $st = $b['status_bilik'];
                $statusClass = 'status-idle';
                if ($st === 'IN_USE') $statusClass = 'status-used';
                elseif ($st === 'CLOSED') $statusClass = 'status-locked';

                $tokenShort = !empty($b['token_hint']) ? strtoupper((string) $b['token_hint']) : '-';
                $remainLock = (int) ($b['remaining_lock_seconds'] ?? 0);
                $bJson = json_encode($b, JSON_HEX_APOS | JSON_HEX_QUOT);
                ?>
                <div class="bilik-card <?= $statusClass ?>" data-bilik-card="<?= (int)$b['id'] ?>" data-status="<?= e($st) ?>">
                    <!-- Bagian Atas: Nomor Bilik, Nama, Lokasi & Status Badge -->
                    <div class="bilik-card-head">
                        <div style="min-width:0;flex:1;">
                            <span class="kandidat-num-pill">BILIK #0<?= (int)$b['nomor_bilik'] ?></span>
                            <div class="bilik-card-title"><?= e($b['nama_bilik']) ?></div>
                            <div class="bilik-card-loc"><?= e($b['lokasi_bilik'] ?: 'Lokasi belum ditentukan') ?></div>
                        </div>
                        <div>
                            <?php if ($st === 'CLOSED'): ?>
                                <span class="badge danger dot">CLOSED</span>
                            <?php elseif ($st === 'IN_USE'): ?>
                                <span class="badge primary dot">IN_USE</span>
                            <?php else: ?>
                                <span class="badge success dot">IDLE</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Bagian Tengah: Telemetri Token, Sesi NIM, Suara Masuk & Fraud -->
                    <div class="bilik-telemetry">
                        <div class="bilik-telemetry-grid">
                            <div style="min-width:0;">
                                <div class="bilik-meta-label">Token QR & Sesi</div>
                                <div class="bilik-meta-val"><?= e($tokenShort) ?></div>
                                <?php if ($st === 'IN_USE'): ?>
                                    <div class="bilik-meta-sub" style="color:var(--primary);font-weight:600;">Sisa Sesi: <?= (int)$remainLock ?>s</div>
                                <?php elseif ($st === 'IDLE'): ?>
                                    <div class="bilik-meta-sub">Rotasi Otomatis 8s</div>
                                <?php else: ?>
                                    <div class="bilik-meta-sub" style="color:var(--danger);">Token Dijeda</div>
                                <?php endif; ?>
                            </div>
                            <div style="min-width:0;">
                                <div class="bilik-meta-label">NIM Sesi Aktif</div>
                                <div class="bilik-meta-val" style="color:<?= !empty($b['active_nim_masked']) ? 'var(--primary)' : 'var(--t-muted)' ?>;">
                                    <?= e($b['active_nim_masked'] ?: 'Menunggu Scan') ?>
                                </div>
                                <div class="bilik-meta-sub">
                                    <?= $st === 'IN_USE' ? 'Terkunci perangkat' : 'Siap klaim QR' ?>
                                </div>
                            </div>
                        </div>

                        <div class="bilik-telemetry-divider">
                            <div class="d-flex align-center gap-2">
                                <span class="bilik-meta-label" style="margin:0;">Suara Masuk:</span>
                                <span class="badge primary"><?= number_format((int)$b['total_suara_bilik']) ?> Suara</span>
                            </div>
                            <?php if ($canViewLogs): ?>
                            <div class="d-flex align-center gap-2">
                                <span class="bilik-meta-label" style="margin:0;">Fraud:</span>
                                <?php if ((int)$b['total_fraud_bilik'] > 0): ?>
                                    <span class="badge danger"><?= (int)$b['total_fraud_bilik'] ?> Alert</span>
                                <?php else: ?>
                                    <span class="badge neutral">0</span>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Bagian Bawah: Footer Aksi Bilik -->
                    <div class="bilik-card-foot">
                        <a href="<?= e(route_url('portal_qr', ['booth' => (int)$b['nomor_bilik']])) ?>" class="btn btn--soft-primary btn--xs">
                            Layar QR ↗
                        </a>
                        <?php if ($canWrite): ?>
                        <div class="data-cell-actions">
                            <form action="<?= e(action_url('bilik')) ?>" method="POST" data-confirm="Reset token QR dan lepas kunci sesi bilik ini?">
                                <?= csrf_field() ?>
                                <input type="hidden" name="sub_action" value="force_reset_bilik">
                                <input type="hidden" name="id" value="<?= (int)$b['id'] ?>">
                                <button type="submit" class="btn <?= $st === 'IN_USE' ? 'btn--soft-warning' : 'btn--ghost' ?> btn--xs">
                                    <?= $st === 'IN_USE' ? 'Lepas Kunci' : 'Reset Token' ?>
                                </button>
                            </form>
                            <form action="<?= e(action_url('bilik')) ?>" method="POST">
                                <?= csrf_field() ?>
                                <input type="hidden" name="sub_action" value="toggle_bilik_status">
                                <input type="hidden" name="id" value="<?= (int)$b['id'] ?>">
                                <input type="hidden" name="target_status" value="<?= $st === 'CLOSED' ? 'IDLE' : 'CLOSED' ?>">
                                <button type="submit" class="btn btn--ghost btn--xs">
                                    <?= $st === 'CLOSED' ? 'Buka' : 'Tutup' ?>
                                </button>
                            </form>
                            <button type="button" class="btn btn--ghost btn--xs" onclick='openEditBilikModal(<?= $bJson ?>)'>Edit</button>
                            <form action="<?= e(action_url('bilik')) ?>" method="POST" data-confirm="Hapus bilik suara ini?">
                                <?= csrf_field() ?>
                                <input type="hidden" name="sub_action" value="delete_bilik">
                                <input type="hidden" name="id" value="<?= (int)$b['id'] ?>">
                                <button type="submit" class="btn btn--soft-danger btn--xs" title="Hapus Bilik">×</button>
                            </form>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
</div>

<?php if ($canWrite): ?>
<!-- Modal Form Bilik -->
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
                <div class="field">
                    <label class="field-label">Nama Bilik</label>
                    <input type="text" name="nama_bilik" id="bilik_nama" class="input" placeholder="Bilik Suara 04" required>
                </div>
                <div class="field mb-0">
                    <label class="field-label">Lokasi TPS</label>
                    <input type="text" name="lokasi_bilik" id="bilik_lokasi" class="input" placeholder="Aula Utama Kampus" required>
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

<script>
function openCreateBilikModal() {
    const nextNum = document.querySelectorAll('[data-bilik-card]').length + 1;
    document.getElementById('bilikModalTitle').textContent = 'Tambah Bilik Suara';
    document.getElementById('bilik_sub_action').value = 'create_bilik';
    document.getElementById('bilik_id').value = 0;
    document.getElementById('bilik_nomor').value = nextNum;
    document.getElementById('bilik_nama').value = 'Bilik Suara 0' + nextNum;
    document.getElementById('bilik_lokasi').value = 'Aula Utama Kampus';
    PemiraApp.openModal('bilikModal');
}

function openEditBilikModal(b) {
    document.getElementById('bilikModalTitle').textContent = 'Edit Bilik #0' + b.nomor_bilik;
    document.getElementById('bilik_sub_action').value = 'update_bilik';
    document.getElementById('bilik_id').value = b.id;
    document.getElementById('bilik_nomor').value = b.nomor_bilik;
    document.getElementById('bilik_nama').value = b.nama_bilik || '';
    document.getElementById('bilik_lokasi').value = b.lokasi_bilik || '';
    PemiraApp.openModal('bilikModal');
}

function applyBilikCardFilter() {
    const q = (document.getElementById('bilikSearchInput')?.value || '').trim().toLowerCase();
    const st = document.getElementById('bilikStatusFilter')?.value || 'ALL';
    document.querySelectorAll('#bilikCardsGrid [data-bilik-card]').forEach(card => {
        const cardSt = (card.getAttribute('data-status') || '').toUpperCase();
        const matchSt = (st === 'ALL' || cardSt === st);
        const matchQ = (!q || card.textContent.toLowerCase().includes(q));
        card.style.display = (matchSt && matchQ) ? '' : 'none';
    });
}
window.applyBilikCardFilter = applyBilikCardFilter;

document.addEventListener('DOMContentLoaded', () => {
    const searchInput = document.getElementById('bilikSearchInput');
    const statusFilter = document.getElementById('bilikStatusFilter');
    if (searchInput) searchInput.addEventListener('input', applyBilikCardFilter);
    if (statusFilter) statusFilter.addEventListener('change', applyBilikCardFilter);

    const btnLive = document.getElementById('btnToggleLiveBilik');
    if (btnLive) {
        btnLive.addEventListener('click', () => {
            const active = btnLive.getAttribute('data-live') === '1';
            if (active) {
                btnLive.setAttribute('data-live', '0');
                btnLive.textContent = 'Live Sync: OFF';
                btnLive.classList.remove('btn--soft-primary');
                btnLive.classList.add('btn--ghost');
                window.PEMIRA_LIVE_SYNC_PAUSED = true;
            } else {
                btnLive.setAttribute('data-live', '1');
                btnLive.textContent = 'Live Sync: ON (2s)';
                btnLive.classList.remove('btn--ghost');
                btnLive.classList.add('btn--soft-primary');
                window.PEMIRA_LIVE_SYNC_PAUSED = false;
                if (typeof PemiraApp.syncCurrentPage === 'function') PemiraApp.syncCurrentPage();
            }
        });
    }
});
</script>

<?php require BASE_PATH . '/views/layouts/footer.php'; ?>
