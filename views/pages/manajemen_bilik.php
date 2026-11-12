<?php
/**
 * MANAJEMEN BILIK - Adminator 2026 Redesign (Fitur 2)
 * Mengelola CRUD Bilik Suara, Pintasan Portal QR per Bilik, Kontrol Status Global/Per Bilik,
 * Monitoring Sesi & Token Real-Time, serta Force Reset Kunci Bilik.
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
        <span class="eyebrow">Infrastruktur TPS & Kontrol Token · Fitur 2</span>
        <h1 class="hero-title">Manajemen Bilik Suara & Portal Token</h1>
    </div>
    <div class="hero-actions">
        <button type="button" class="btn btn--soft-primary btn--sm" id="btnToggleLiveBilik" data-live="1">Live Monitor: ON (4s)</button>

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
                        <th data-sort>Nama Bilik & Lokasi <span class="sort">▾</span></th>
                        <th>Status Real-Time</th>
                        <th>Token QR & Durasi Sesi</th>
                        <th>NIM Sesi Aktif</th>
                        <th data-sort>Suara Masuk <span class="sort">▾</span></th>
                        <th>Fraud</th>
                        <th class="text-right">Kontrol & Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($bilikList as $b): ?>
                        <?php
                        $st = $b['status_bilik'];
                        $tokenShort = !empty($b['current_token']) ? strtoupper(substr((string) $b['current_token'], 0, 10)) . '...' : '-';
                        $remainLock = 0;
                        if ($st === 'IN_USE' && !empty($b['locked_until'])) {
                            $remainLock = max(0, strtotime($b['locked_until']) - time());
                        }
                        ?>
                        <tr class="data-row" data-status="<?= e($st) ?>" data-bilik-row="<?= (int)$b['id'] ?>">
                            <td><span class="data-cell-mono fw-600">BILIK #0<?= (int)$b['nomor_bilik'] ?></span></td>
                            <td>
                                <div class="fw-600"><?= e($b['nama_bilik']) ?></div>
                                <div class="text-muted" style="font-size:11px;"><?= e($b['lokasi_bilik'] ?? '-') ?></div>
                            </td>
                            <td class="cell-bilik-status">
                                <?php if ($st === 'CLOSED'): ?>
                                    <span class="badge danger dot">CLOSED</span>
                                <?php elseif ($st === 'IN_USE'): ?>
                                    <span class="badge primary dot">IN_USE</span>
                                <?php else: ?>
                                    <span class="badge success dot">IDLE</span>
                                <?php endif; ?>
                            </td>
                            <td class="cell-bilik-token">
                                <div class="data-cell-mono fw-600"><?= e($tokenShort) ?></div>
                                <?php if ($st === 'IN_USE'): ?>
                                    <div class="text-muted mono" style="font-size:10.5px;color:var(--primary);">Sisa Sesi: <?= (int)$remainLock ?>s</div>
                                <?php elseif ($st === 'IDLE'): ?>
                                    <div class="text-muted mono" style="font-size:10.5px;">Rotasi Otomatis 8s</div>
                                <?php else: ?>
                                    <div class="text-muted mono" style="font-size:10.5px;">Token Dijeda</div>
                                <?php endif; ?>
                            </td>
                            <td class="cell-bilik-nim">
                                <span class="data-cell-mono fw-600"><?= e($b['active_nim'] ?: '-') ?></span>
                            </td>
                            <td class="cell-bilik-suara">
                                <span class="data-cell-mono fw-600"><?= number_format((int)$b['total_suara_bilik']) ?></span>
                            </td>
                            <td class="cell-bilik-fraud">
                                <?php if ((int)$b['total_fraud_bilik'] > 0): ?>
                                    <span class="badge danger"><?= (int)$b['total_fraud_bilik'] ?></span>
                                <?php else: ?>
                                    <span class="data-cell-mono">0</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="data-cell-actions">
                                    <a href="<?= e(route_url('portal_qr', ['booth' => (int)$b['nomor_bilik']])) ?>" class="btn btn--soft-primary btn--xs">Buka Layar QR</a>
                                    <form action="<?= e(action_url('bilik')) ?>" method="POST" onsubmit="return confirm('Reset token QR dan lepas kunci sesi bilik ini?');">
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
                                    <button type="button" class="btn btn--ghost btn--xs" onclick='openEditBilikModal(<?= json_encode($b, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>Edit</button>
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

document.addEventListener('DOMContentLoaded', () => {
    const btnLive = document.getElementById('btnToggleLiveBilik');
    let timer = null;

    async function pollBilikStatus() {
        try {
            const res = await fetch(`${window.PEMIRA_CONFIG.baseUrl}/index.php?action=bilik&sub_action=status_list_json`, {
                headers: { 'Accept': 'application/json' }
            });
            const json = await res.json();
            if (!json.status || !json.data || !Array.isArray(json.data.biliks)) return;

            let idle = 0, inUse = 0, closed = 0;
            json.data.biliks.forEach(b => {
                if (b.status_bilik === 'IDLE') idle++;
                else if (b.status_bilik === 'IN_USE') inUse++;
                else closed++;

                const tr = document.querySelector(`tr[data-bilik-row="${b.id}"]`);
                if (!tr) return;
                tr.setAttribute('data-status', b.status_bilik);

                const statusTd = tr.querySelector('.cell-bilik-status');
                if (statusTd) {
                    if (b.status_bilik === 'CLOSED') {
                        statusTd.innerHTML = '<span class="badge danger dot">CLOSED</span>';
                    } else if (b.status_bilik === 'IN_USE') {
                        statusTd.innerHTML = '<span class="badge primary dot">IN_USE</span>';
                    } else {
                        statusTd.innerHTML = '<span class="badge success dot">IDLE</span>';
                    }
                }

                const tokenTd = tr.querySelector('.cell-bilik-token');
                if (tokenTd) {
                    const tok = b.current_token ? String(b.current_token).substring(0, 10).toUpperCase() + '...' : '-';
                    let sub = '<div class="text-muted mono" style="font-size:10.5px;">Rotasi Otomatis 8s</div>';
                    if (b.status_bilik === 'IN_USE') {
                        sub = '<div class="text-muted mono" style="font-size:10.5px;color:var(--primary);">Sesi Aktif Terkunci</div>';
                    } else if (b.status_bilik === 'CLOSED') {
                        sub = '<div class="text-muted mono" style="font-size:10.5px;">Token Dijeda</div>';
                    }
                    tokenTd.innerHTML = `<div class="data-cell-mono fw-600">${tok}</div>${sub}`;
                }

                const nimTd = tr.querySelector('.cell-bilik-nim');
                if (nimTd) {
                    nimTd.innerHTML = `<span class="data-cell-mono fw-600">${b.active_nim || '-'}</span>`;
                }

                const suaraTd = tr.querySelector('.cell-bilik-suara');
                if (suaraTd) {
                    suaraTd.innerHTML = `<span class="data-cell-mono fw-600">${Number(b.total_suara_bilik || 0).toLocaleString('id-ID')}</span>`;
                }
            });

            const elIdle = document.getElementById('kpiBilikIdle');
            const elInUse = document.getElementById('kpiBilikInUse');
            const elClosed = document.getElementById('kpiBilikClosed');
            if (elIdle) elIdle.textContent = idle;
            if (elInUse) elInUse.textContent = inUse;
            if (elClosed) elClosed.textContent = closed;
        } catch (err) {
            // Silent ignore on polling
        }
    }

    timer = setInterval(pollBilikStatus, 4000);

    if (btnLive) {
        btnLive.addEventListener('click', () => {
            const active = btnLive.getAttribute('data-live') === '1';
            if (active) {
                btnLive.setAttribute('data-live', '0');
                btnLive.textContent = 'Live Monitor: OFF';
                btnLive.classList.remove('btn--soft-primary');
                btnLive.classList.add('btn--ghost');
                if (timer) clearInterval(timer);
            } else {
                btnLive.setAttribute('data-live', '1');
                btnLive.textContent = 'Live Monitor: ON (4s)';
                btnLive.classList.remove('btn--ghost');
                btnLive.classList.add('btn--soft-primary');
                pollBilikStatus();
                timer = setInterval(pollBilikStatus, 4000);
            }
        });
    }
});
</script>

<?php require BASE_PATH . '/views/layouts/footer.php'; ?>
