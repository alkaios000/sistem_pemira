<?php
/**
 * LOG KEAMANAN - Adminator 2026 Redesign Audit Trail (Fitur 5)
 * Hijau = Normal | Merah = Fraud / Anomali
 */
require_role(['superadmin', 'banwasra']);
require_once BASE_PATH . '/models/log_model.php';
require_once BASE_PATH . '/models/bilik_model.php';

$pdo = get_db_connection();
$activeTab = strtolower(trim((string) ($_GET['tab'] ?? $_GET['category'] ?? 'all')));
if (!in_array($activeTab, ['all', 'pemilih', 'admin'], true)) {
    $activeTab = 'all';
}
$categoryFilter = $activeTab === 'all' ? null : $activeTab;

$pageTitle = match ($activeTab) {
    'pemilih' => 'Log Pemilih',
    'admin'   => 'Log Admin',
    default   => 'Log Keamanan',
};

$excludeSuperadmin = is_banwasra();
$logs = get_audit_logs($pdo, null, null, '', 300, $excludeSuperadmin, $categoryFilter);
$logStats = get_audit_log_stats($pdo, $excludeSuperadmin, $categoryFilter);
$bilikList = get_all_biliks_sanitized($pdo, true, $excludeSuperadmin);

$exportParams = ['sub_action' => 'export_csv'];
if ($categoryFilter !== null) {
    $exportParams['category'] = $categoryFilter;
}

$tabSummaryLabel = match ($activeTab) {
    'pemilih' => 'Aktivitas bilik & verifikasi pemilih',
    'admin'   => $excludeSuperadmin
        ? 'Aktivitas manajemen KPRM & Banwasra'
        : 'Aktivitas manajemen Superadmin, KPRM & Banwasra',
    default   => 'Pemilih: ' . number_format($logStats['total_pemilih']) . ' · Admin: ' . number_format($logStats['total_admin']),
};

$searchPlaceholder = match ($activeTab) {
    'pemilih' => 'Cari NIM, nama pemilih, kode event, atau Device ID...',
    'admin'   => 'Cari username aktor, role, kode event, atau keterangan...',
    default   => 'Cari NIM, aktor, kode event, keterangan, atau Device ID...',
};

$actorColumnLabel = match ($activeTab) {
    'pemilih' => 'Pemilih (NIM / Nama)',
    'admin'   => 'Pengguna Manajemen / Role',
    default   => 'Aktor / Pemilih',
};

require BASE_PATH . '/views/layouts/header.php';
?>

<section class="hero" style="justify-content:space-between;flex-wrap:wrap;margin-bottom:14px;">
    <div class="tabs pills" role="tablist" aria-label="Kategori Log Audit">
        <a href="<?= e(route_url('log_keamanan')) ?>"
           class="tab <?= $activeTab === 'all' ? 'is-active' : '' ?>"
           style="text-decoration:none;display:inline-flex;align-items:center;gap:6px;">
            <span>Semua Log</span>
            <span class="badge neutral"><?= number_format($logStats['total_all']) ?></span>
        </a>
        <a href="<?= e(route_url('log_keamanan', ['tab' => 'pemilih'])) ?>"
           class="tab <?= $activeTab === 'pemilih' ? 'is-active' : '' ?>"
           style="text-decoration:none;display:inline-flex;align-items:center;gap:6px;">
            <span>Log Pemilih</span>
            <span class="badge info"><?= number_format($logStats['total_pemilih']) ?></span>
        </a>
        <a href="<?= e(route_url('log_keamanan', ['tab' => 'admin'])) ?>"
           class="tab <?= $activeTab === 'admin' ? 'is-active' : '' ?>"
           style="text-decoration:none;display:inline-flex;align-items:center;gap:6px;">
            <span>Log Admin</span>
            <span class="badge purple"><?= number_format($logStats['total_admin']) ?></span>
        </a>
    </div>
    <div class="hero-actions">
        <?php if ($excludeSuperadmin): ?>
            <span class="badge purple dot">Pengawasan Banwasra · Non-Superadmin Log</span>
        <?php endif; ?>
        <button type="button" class="btn btn--soft-primary btn--sm" id="btnToggleLiveLog" data-live="1">Live Sync: ON (2s)</button>
        <a href="<?= e(action_url('log', $exportParams)) ?>" class="btn btn--soft-primary btn--sm">Export CSV</a>
        <button type="button" class="btn btn--ghost btn--sm" onclick="PemiraApp.syncCurrentPage()">Sinkronkan Sekarang</button>
    </div>
</section>

<!-- KPI Row -->
<div class="kpi-grid" style="grid-template-columns: repeat(3, minmax(0, 1fr));">
    <div class="kpi-card">
        <div class="kpi-top">
            <span class="kpi-label"><?= e($activeTab === 'pemilih' ? 'Total Log Pemilih' : ($activeTab === 'admin' ? 'Total Log Admin' : 'Total Event Terekam')) ?></span>
            <span class="kpi-pill info"><?= e($activeTab === 'pemilih' ? 'Pemilih' : ($activeTab === 'admin' ? 'Manajemen' : 'Audit')) ?></span>
        </div>
        <div class="kpi-value" id="kpiTotalLogs"><?= number_format($logStats['total_logs']) ?></div>
        <div class="kpi-compare"><span><?= e($tabSummaryLabel) ?></span></div>
    </div>
    <div class="kpi-card">
        <div class="kpi-top">
            <span class="kpi-label">Aktivitas Normal (Hijau)</span>
            <span class="kpi-pill up">Valid</span>
        </div>
        <div class="kpi-value" id="kpiTotalNormal"><?= number_format($logStats['total_normal']) ?></div>
        <div class="kpi-compare"><span><?= e($activeTab === 'admin' ? 'Operasi panel & login manajemen sah' : 'Suara sah & aktivitas tervalidasi') ?></span></div>
    </div>
    <div class="kpi-card">
        <div class="kpi-top">
            <span class="kpi-label">Anomali / Fraud (Merah)</span>
            <span class="kpi-pill down">Alert</span>
        </div>
        <div class="kpi-value" id="kpiTotalFraud"><?= number_format($logStats['total_fraud']) ?></div>
        <div class="kpi-compare"><span><?= e($activeTab === 'admin' ? 'Login gagal & pemblokiran brute-force' : 'Double scan, foto QR & anomali akses') ?></span></div>
    </div>
</div>

<div id="fraudBreakdownWrapper">
<?php if (!empty($logStats['fraud_breakdown'])): ?>
<div class="grid" style="margin-bottom: 16px;">
    <section class="col-12 card" style="padding: 12px 18px;">
        <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;">
            <div style="display:flex;align-items:center;gap:8px;">
                <span class="badge danger dot">Rincian Deteksi Fraud</span>
                <span class="text-muted" style="font-size:11.5px;">Klik kode event untuk memfilter tabel di bawah:</span>
            </div>
            <div style="display:flex;align-items:center;flex-wrap:wrap;gap:6px;">
                <?php foreach ($logStats['fraud_breakdown'] as $fb): ?>
                    <button type="button" class="btn btn--ghost btn--xs" data-fraud-quick="<?= e($fb['kode_event']) ?>" title="<?= e($fb['judul_event']) ?>">
                        <span class="data-cell-mono" style="color:var(--danger);font-weight:700;"><?= e($fb['kode_event']) ?></span>
                        <span class="badge danger"><?= (int) $fb['jumlah'] ?></span>
                    </button>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
</div>
<?php endif; ?>
</div>

<!-- Adminator 2026 Data Table -->
<div class="grid">
    <section class="col-12 card" data-datatable-card>
        <div class="data-toolbar">
            <div class="data-toolbar-left">
                <div class="input-icon" style="flex:1;max-width:340px;">
                    <span class="ico"><svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg></span>
                    <input type="search" class="input" id="logSearchInput" data-dt-search placeholder="<?= e($searchPlaceholder) ?>">
                </div>
            </div>
            <div class="data-toolbar-right">
                <?php if ($activeTab === 'all'): ?>
                    <select class="select" data-dt-filter="data-log-cat" style="width:auto;padding:6px 28px 6px 10px;font-size:12px;">
                        <option value="ALL">Semua Jenis Log</option>
                        <option value="pemilih">Log Pemilih</option>
                        <option value="admin">Log Admin (Manajemen)</option>
                    </select>
                <?php endif; ?>
                <?php if ($activeTab !== 'pemilih'): ?>
                    <select class="select" data-dt-filter="data-actor-role" style="width:auto;padding:6px 28px 6px 10px;font-size:12px;">
                        <option value="ALL">Semua Role Manajemen</option>
                        <?php if (!$excludeSuperadmin): ?>
                            <option value="superadmin">Superadmin</option>
                        <?php endif; ?>
                        <option value="kprm">KPRM</option>
                        <option value="banwasra">Banwasra</option>
                        <option value="guest">Gagal Login / Tamu</option>
                    </select>
                <?php endif; ?>
                <?php if ($activeTab !== 'admin'): ?>
                    <select class="select" data-dt-filter="data-bilik" style="width:auto;padding:6px 28px 6px 10px;font-size:12px;">
                        <option value="ALL">Semua Bilik</option>
                        <?php foreach ($bilikList as $b): ?>
                            <option value="<?= e($b['nama_bilik']) ?>"><?= e($b['nama_bilik']) ?> (#<?= (int) $b['nomor_bilik'] ?>)</option>
                        <?php endforeach; ?>
                        <option value="TANPA_BILIK">Tanpa Bilik / Global</option>
                    </select>
                <?php endif; ?>
                <select class="select" data-dt-filter="data-kategori" style="width:auto;padding:6px 28px 6px 10px;font-size:12px;">
                    <option value="ALL">Semua Status</option>
                    <option value="NORMAL">Normal (Hijau)</option>
                    <option value="FRAUD">Fraud / Anomali (Merah)</option>
                </select>
                <select class="select" data-dt-perpage style="width:auto;padding:6px 26px 6px 10px;font-size:12px;">
                    <option value="15" selected>15 / hal</option>
                    <option value="30">30 / hal</option>
                    <option value="50">50 / hal</option>
                </select>
            </div>
        </div>

        <div class="data-table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th data-sort>Waktu <span class="sort">▾</span></th>
                        <th data-sort>Status <span class="sort">▾</span></th>
                        <th data-sort>Jenis <span class="sort">▾</span></th>
                        <th data-sort>Kode Event <span class="sort">▾</span></th>
                        <th>Keterangan Aktivitas</th>
                        <th data-sort><?= e($actorColumnLabel) ?> <span class="sort">▾</span></th>
                        <th data-sort>Bilik <span class="sort">▾</span></th>
                        <th>Device / IP</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $log): ?>
                        <?php
                        $isFraud = ($log['status_log'] === 'FRAUD');
                        $logCategory = ($log['log_category'] ?? 'pemilih') === 'admin' ? 'admin' : 'pemilih';
                        $actorRoleRaw = strtolower((string) ($log['actor_role'] ?? ''));
                        $actorRoleAttr = $actorRoleRaw !== '' ? $actorRoleRaw : ($logCategory === 'admin' ? 'guest' : 'pemilih');
                        $bilikAttr = !empty($log['nama_bilik']) ? $log['nama_bilik'] : 'TANPA_BILIK';
                        $roleBadgeClass = match ($actorRoleRaw) {
                            'superadmin' => 'danger',
                            'kprm'       => 'primary',
                            'banwasra'   => 'purple',
                            default      => 'warning',
                        };
                        ?>
                        <tr class="data-row <?= $isFraud ? 'row-log-fraud' : 'row-log-normal' ?>"
                            data-kategori="<?= e($log['status_log']) ?>"
                            data-log-cat="<?= e($logCategory) ?>"
                            data-actor-role="<?= e($actorRoleAttr) ?>"
                            data-bilik="<?= e($bilikAttr) ?>">
                            <td><span class="data-cell-mono"><?= e(format_tanggal_indo($log['created_at'])) ?></span></td>
                            <td>
                                <span class="badge <?= $isFraud ? 'danger' : 'success' ?> dot">
                                    <?= $isFraud ? 'FRAUD' : 'NORMAL' ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge <?= $logCategory === 'admin' ? 'purple' : 'info' ?>">
                                    <?= $logCategory === 'admin' ? 'ADMIN' : 'PEMILIH' ?>
                                </span>
                            </td>
                            <td>
                                <div class="data-cell-mono fw-600"><?= e($log['kode_event']) ?></div>
                                <div class="text-muted" style="font-size:11px;"><?= e($log['judul_event']) ?></div>
                            </td>
                            <td><?= e($log['keterangan']) ?></td>
                            <td>
                                <?php if ($logCategory === 'admin'): ?>
                                    <div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap;">
                                        <span class="data-cell-mono fw-600"><?= e($log['actor_username'] ?: '-') ?></span>
                                        <span class="badge <?= $roleBadgeClass ?>">
                                            <?= e($actorRoleRaw !== '' ? strtoupper($actorRoleRaw) : 'GAGAL LOGIN') ?>
                                        </span>
                                    </div>
                                    <?php if (!empty($log['nim'])): ?>
                                        <div class="text-muted data-cell-mono" style="font-size:11px;margin-top:2px;">Target NIM: <?= e($log['nim']) ?></div>
                                    <?php endif; ?>
                                <?php elseif (!empty($log['nim'])): ?>
                                    <div class="data-cell-mono fw-600"><?= e($log['nim']) ?></div>
                                    <div class="text-muted" style="font-size:11px;"><?= e($log['nama_mahasiswa'] ?: '') ?></div>
                                <?php else: ?>
                                    <span class="text-muted">Anonim / Pra-Verifikasi</span>
                                <?php endif; ?>
                            </td>
                            <td><span class="data-cell-mono"><?= e($log['nama_bilik'] ?: '-') ?></span></td>
                            <td>
                                <div class="data-cell-mono"><?= e($log['ip_address'] ?: '-') ?></div>
                                <div class="data-cell-mono text-light" style="font-size:10px;"><?= e(substr((string)$log['device_id'], 0, 18)) ?></div>
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

<script>
document.addEventListener('DOMContentLoaded', () => {
    const searchInput = document.getElementById('logSearchInput');
    document.addEventListener('click', e => {
        const btn = e.target.closest('[data-fraud-quick]');
        if (!btn || !searchInput) return;
        const code = btn.getAttribute('data-fraud-quick') || '';
        searchInput.value = searchInput.value === code ? '' : code;
        searchInput.dispatchEvent(new Event('input'));
    });

    const btnLive = document.getElementById('btnToggleLiveLog');
    if (btnLive) {
        btnLive.addEventListener('click', () => {
            const isLive = btnLive.getAttribute('data-live') === '1';
            if (isLive) {
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
