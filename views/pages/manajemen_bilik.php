<?php
/**
 * MANAJEMEN BILIK - Adminator 2026 Redesign (Fitur 2)
 */
require_auth();
require_once BASE_PATH . '/models/bilik_model.php';
require_once BASE_PATH . '/models/user_model.php';

$pdo = get_db_connection();
$pageTitle = 'Manajemen Bilik';
$bilikList = get_all_biliks($pdo);
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
    <div class="hero-text">
        <span class="eyebrow">Infrastruktur TPS · Fitur 2</span>
        <h1 class="hero-title">Manajemen Bilik Suara</h1>
    </div>
    <div class="hero-actions">
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
                <?= $globalPortal === 'BUKA' ? 'Tutup Portal Global' : 'Buka Portal Global' ?>
            </button>
        </form>

        <button type="button" class="btn btn--primary btn--sm" onclick="openCreateBilikModal()">+ Tambah Bilik</button>
    </div>
</section>

<!-- KPI Row -->
<div class="kpi-grid">
    <div class="kpi-card">
        <div class="kpi-top">
            <span class="kpi-label">Total Bilik</span>
            <span class="kpi-pill info">TPS</span>
        </div>
        <div class="kpi-value"><?= $totalBilik ?></div>
        <div class="kpi-compare"><span>Terdaftar di database</span></div>
    </div>
    <div class="kpi-card">
        <div class="kpi-top">
            <span class="kpi-label">Bilik Siap (IDLE)</span>
            <span class="kpi-pill up">Online</span>
        </div>
        <div class="kpi-value"><?= $bilikIdle ?></div>
        <div class="kpi-compare"><span>Menunggu scan QR</span></div>
    </div>
    <div class="kpi-card">
        <div class="kpi-top">
            <span class="kpi-label">Sedang Dipakai</span>
            <span class="kpi-pill primary">IN_USE</span>
        </div>
        <div class="kpi-value"><?= $bilikInUse ?></div>
        <div class="kpi-compare"><span>Terkunci sesi aktif</span></div>
    </div>
    <div class="kpi-card">
        <div class="kpi-top">
            <span class="kpi-label">Ditutup (CLOSED)</span>
            <span class="kpi-pill down">Nonaktif</span>
        </div>
        <div class="kpi-value"><?= $bilikClosed ?></div>
        <div class="kpi-compare"><span>Tidak menerima scan</span></div>
    </div>
</div>

<!-- Adminator 2026 Data Table -->
<div class="grid">
    <section class="col-12 card" data-datatable-card>
        <div class="data-toolbar">
            <div class="data-toolbar-left">
                <div class="input-icon" style="flex:1;max-width:300px;">
                    <span class="ico"><svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg></span>
                    <input type="search" class="input" data-dt-search placeholder="Cari nomor bilik, nama, atau lokasi...">
                </div>
            </div>
            <div class="data-toolbar-right">
                <select class="select" data-dt-filter="data-status" style="width:auto;padding:6px 28px 6px 10px;font-size:12px;">
                    <option value="ALL">Semua Status</option>
                    <option value="IDLE">IDLE (Siap)</option>
                    <option value="IN_USE">IN_USE (Dipakai)</option>
                    <option value="CLOSED">CLOSED (Ditutup)</option>
                </select>
            </div>
        </div>

        <div class="data-table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th data-sort>No. Bilik <span class="sort">▾</span></th>
                        <th data-sort>Nama Bilik <span class="sort">▾</span></th>
                        <th data-sort>Lokasi TPS <span class="sort">▾</span></th>
                        <th>Status</th>
                        <th>NIM Aktif</th>
                        <th data-sort>Suara Masuk <span class="sort">▾</span></th>
                        <th>Fraud</th>
                        <th class="text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($bilikList as $b): ?>
                        <?php $st = $b['status_bilik']; ?>
                        <tr class="data-row" data-status="<?= e($st) ?>">
                            <td><span class="data-cell-mono fw-600">BILIK #0<?= (int)$b['nomor_bilik'] ?></span></td>
                            <td><span class="fw-600"><?= e($b['nama_bilik']) ?></span></td>
                            <td><?= e($b['lokasi_bilik'] ?? '-') ?></td>
                            <td>
                                <?php if ($st === 'CLOSED'): ?>
                                    <span class="badge danger dot">CLOSED</span>
                                <?php elseif ($st === 'IN_USE'): ?>
                                    <span class="badge primary dot">IN_USE</span>
                                <?php else: ?>
                                    <span class="badge success dot">IDLE</span>
                                <?php endif; ?>
                            </td>
                            <td><span class="data-cell-mono"><?= e($b['active_nim'] ?: '-') ?></span></td>
                            <td><span class="data-cell-mono fw-600"><?= number_format((int)$b['total_suara_bilik']) ?></span></td>
                            <td>
                                <?php if ((int)$b['total_fraud_bilik'] > 0): ?>
                                    <span class="badge danger"><?= (int)$b['total_fraud_bilik'] ?></span>
                                <?php else: ?>
                                    <span class="data-cell-mono">0</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="data-cell-actions">
                                    <a href="<?= e(route_url('portal_qr', ['booth' => (int)$b['nomor_bilik']])) ?>" class="btn btn--soft-primary btn--xs">Layar QR</a>
                                    <button type="button" class="btn btn--ghost btn--xs" onclick='openEditBilikModal(<?= json_encode($b, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>Edit</button>
                                    <?php if ($st === 'IN_USE'): ?>
                                        <form action="<?= e(action_url('bilik')) ?>" method="POST" onsubmit="return confirm('Reset paksa kunci sesi bilik ini?');">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="sub_action" value="force_reset_bilik">
                                            <input type="hidden" name="id" value="<?= (int)$b['id'] ?>">
                                            <button type="submit" class="btn btn--soft-warning btn--xs">Reset Kunci</button>
                                        </form>
                                    <?php endif; ?>
                                    <form action="<?= e(action_url('bilik')) ?>" method="POST">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="sub_action" value="toggle_bilik_status">
                                        <input type="hidden" name="id" value="<?= (int)$b['id'] ?>">
                                        <input type="hidden" name="target_status" value="<?= $st === 'CLOSED' ? 'IDLE' : 'CLOSED' ?>">
                                        <button type="submit" class="btn btn--ghost btn--xs">
                                            <?= $st === 'CLOSED' ? 'Buka' : 'Tutup' ?>
                                        </button>
                                    </form>
                                    <form action="<?= e(action_url('bilik')) ?>" method="POST" onsubmit="return confirm('Hapus bilik suara ini?');">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="sub_action" value="delete_bilik">
                                        <input type="hidden" name="id" value="<?= (int)$b['id'] ?>">
                                        <button type="submit" class="btn btn--soft-danger btn--xs">×</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="data-foot">
            <div class="data-foot-info">
                <span data-dt-info>Showing 0 of 0</span>
            </div>
            <div class="pager" data-dt-pager></div>
        </div>
    </section>
</div>

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

<script>
function openCreateBilikModal() {
    document.getElementById('bilikModalTitle').textContent = 'Tambah Bilik Suara';
    document.getElementById('bilik_sub_action').value = 'create_bilik';
    document.getElementById('bilik_id').value = 0;
    document.getElementById('bilik_nomor').value = <?= $totalBilik + 1 ?>;
    document.getElementById('bilik_nama').value = '';
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
</script>

<?php require BASE_PATH . '/views/layouts/footer.php'; ?>
