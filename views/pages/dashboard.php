<?php
/**
 * DASHBOARD PAGE - Adminator 2026 Redesign Overview (Fitur 3)
 * Menampilkan KPI Global, Perolehan Suara BEM & BPM, Partisipasi per Kelas/Jurusan/Semester, dan Log Keamanan Terbaru.
 */
require_role(['superadmin', 'kprm', 'banwasra']);

require_once BASE_PATH . '/models/mahasiswa_model.php';
require_once BASE_PATH . '/models/kandidat_model.php';
require_once BASE_PATH . '/models/bilik_model.php';
require_once BASE_PATH . '/models/log_model.php';
require_once BASE_PATH . '/models/user_model.php';

$pdo = get_db_connection();
$pageTitle = 'Dashboard';

$canViewLogs = can_view_logs();
$excludeSuperadminLogs = is_banwasra();
$isViewerOnly = !can_manage_pemira();

$dptStats = get_dpt_global_stats($pdo);
$kelasStats = get_all_kelas_with_stats($pdo);
$biliks = get_all_biliks_sanitized($pdo, $canViewLogs, $excludeSuperadminLogs);
$logStats = $canViewLogs
    ? get_audit_log_stats($pdo, $excludeSuperadminLogs)
    : ['total_logs' => 0, 'total_normal' => 0, 'total_fraud' => 0, 'total_all' => 0, 'total_pemilih' => 0, 'total_admin' => 0, 'fraud_breakdown' => []];
$bemSummary = get_live_count_summary($pdo, 'BEM');
$bpmSummary = get_live_count_summary($pdo, 'BPM');
$recentLogsAll = $canViewLogs ? get_audit_logs($pdo, null, null, '', 8, $excludeSuperadminLogs) : [];
$recentLogsPemilih = $canViewLogs ? get_audit_logs($pdo, null, null, '', 8, $excludeSuperadminLogs, 'pemilih') : [];
$recentLogsAdmin = $canViewLogs ? get_audit_logs($pdo, null, null, '', 8, $excludeSuperadminLogs, 'admin') : [];
$globalPortal = get_setting_value($pdo, 'status_portal_global', 'BUKA');
$liveCountPublik = get_setting_value($pdo, 'live_count_publik', 'BUKA');

$totalBilik = count($biliks);
$bilikIdle = 0;
$bilikInUse = 0;
foreach ($biliks as $b) {
    if ($b['status_bilik'] === 'IDLE') $bilikIdle++;
    if ($b['status_bilik'] === 'IN_USE') $bilikInUse++;
}

require BASE_PATH . '/views/layouts/header.php';
?>

<section class="hero">
    <div class="hero-actions">
        <?php if ($isViewerOnly): ?>
            <span class="badge purple dot">Mode Viewer (Read-Only)</span>
        <?php endif; ?>
        <span class="badge <?= $globalPortal === 'BUKA' ? 'success' : 'danger' ?> dot">Portal <?= e($globalPortal) ?></span>
        <span class="badge <?= $liveCountPublik === 'BUKA' ? 'info' : 'warn' ?> dot">Live Count <?= e($liveCountPublik) ?></span>
        <a href="<?= e(route_url('portal_qr')) ?>" class="btn btn--soft-primary btn--sm">Portal QR Bilik</a>
        <a href="<?= e(route_url('bilik_pemilihan')) ?>" target="_blank" rel="noopener noreferrer" class="btn btn--soft-primary btn--sm">Layar Bilik Suara ↗</a>
        <a href="<?= e(route_url('live_count_bem')) ?>" class="btn btn--primary btn--sm">Live Count</a>
    </div>
</section>

<!-- KPI Row (Adminator 2026) -->
<div class="kpi-grid">
    <div class="kpi-card">
        <div class="kpi-top">
            <span class="kpi-label">Sudah Memilih</span>
            <span class="kpi-pill up"><?= e($dptStats['persen_partisipasi']) ?>%</span>
        </div>
        <div class="kpi-value"><?= number_format($dptStats['sudah_memilih']) ?><sup>/ <?= number_format($dptStats['total_dpt']) ?></sup></div>
        <div class="kpi-compare">
            <strong><?= number_format($dptStats['total_hadir']) ?></strong>
            <span>terverifikasi hadir</span>
        </div>
    </div>

    <div class="kpi-card">
        <div class="kpi-top">
            <span class="kpi-label">Belum Memilih</span>
            <span class="kpi-pill flat"><?= e($dptStats['persen_belum']) ?>%</span>
        </div>
        <div class="kpi-value"><?= number_format($dptStats['belum_memilih']) ?></div>
        <div class="kpi-compare">
            <strong><?= number_format($dptStats['total_kelas']) ?></strong>
            <span>total kelas terdaftar</span>
        </div>
    </div>

    <div class="kpi-card">
        <div class="kpi-top">
            <span class="kpi-label">Status Bilik Suara</span>
            <span class="kpi-pill primary"><?= $bilikInUse ?> dipakai</span>
        </div>
        <div class="kpi-value"><?= $bilikIdle ?><sup>/ <?= $totalBilik ?></sup></div>
        <div class="kpi-compare">
            <strong><?= $bilikIdle ?></strong>
            <span>bilik siap (IDLE)</span>
        </div>
    </div>

    <?php if ($canViewLogs): ?>
        <div class="kpi-card">
            <div class="kpi-top">
                <span class="kpi-label">Normal vs Fraud Log</span>
                <span class="kpi-pill <?= $logStats['total_fraud'] > 0 ? 'down' : 'up' ?>">
                    <?= $logStats['total_fraud'] ?> Fraud
                </span>
            </div>
            <div class="kpi-value"><?= number_format($logStats['total_normal']) ?><sup>Normal</sup></div>
            <div class="kpi-compare">
                <strong><?= number_format($logStats['total_logs']) ?></strong>
                <span>Pemilih: <?= number_format($logStats['total_pemilih']) ?> · Admin: <?= number_format($logStats['total_admin']) ?></span>
            </div>
        </div>
    <?php else: ?>
        <div class="kpi-card">
            <div class="kpi-top">
                <span class="kpi-label">Kandidat Terdaftar</span>
                <span class="kpi-pill info">BEM &amp; BPM</span>
            </div>
            <div class="kpi-value"><?= count($bemSummary['kandidats']) + count($bpmSummary['kandidats']) ?></div>
            <div class="kpi-compare">
                <strong><?= count($bemSummary['kandidats']) ?> BEM</strong>
                <span>· <?= count($bpmSummary['kandidats']) ?> BPM aktif</span>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Perolehan Suara Ringkas BEM & BPM -->
<div class="grid">
    <section class="col-6 card">
        <div class="card-head">
            <div class="card-title-wrap">
                <h3 class="card-title">Perolehan Suara BEM</h3>
            </div>
            <span class="badge primary"><?= number_format($bemSummary['total_suara_kandidat']) ?> Suara</span>
        </div>
        <div style="display:flex;flex-direction:column;gap:12px;">
            <?php foreach ($bemSummary['kandidats'] as $cand): ?>
                <div>
                    <div class="d-flex justify-between align-center mb-1">
                        <span class="fw-600">#0<?= (int)$cand['nomor_urut'] ?> · <?= e($cand['nama_calon']) ?></span>
                        <span class="mono"><?= number_format((int)$cand['jumlah_suara']) ?> (<?= e($cand['persentase']) ?>%)</span>
                    </div>
                    <div class="progress">
                        <div class="progress-bar" style="width: <?= e($cand['persentase']) ?>%;"></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="col-6 card">
        <div class="card-head">
            <div class="card-title-wrap">
                <h3 class="card-title">Perolehan Suara BPM</h3>
            </div>
            <span class="badge purple"><?= number_format($bpmSummary['total_suara_kandidat']) ?> Suara</span>
        </div>
        <div style="display:flex;flex-direction:column;gap:12px;">
            <?php foreach ($bpmSummary['kandidats'] as $cand): ?>
                <div>
                    <div class="d-flex justify-between align-center mb-1">
                        <span class="fw-600">#0<?= (int)$cand['nomor_urut'] ?> · <?= e($cand['nama_calon']) ?></span>
                        <span class="mono"><?= number_format((int)$cand['jumlah_suara']) ?> (<?= e($cand['persentase']) ?>%)</span>
                    </div>
                    <div class="progress">
                        <div class="progress-bar purple" style="width: <?= e($cand['persentase']) ?>%;"></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- Partisipasi Suara per Kelas / Jurusan / Semester (Sesuai app.md Poin 5.3) -->
    <section class="col-12 card">
        <div class="card-head">
            <div class="card-title-wrap">
                <h3 class="card-title">Partisipasi Suara per Kelas, Jurusan & Semester</h3>
            </div>
            <a href="<?= e(route_url('manajemen_mahasiswa')) ?>" class="btn btn--ghost btn--sm">Kelola DPT</a>
        </div>
        <div class="data-table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Kelas</th>
                        <th>Program Studi / Jurusan</th>
                        <th>Semester</th>
                        <th>Total DPT</th>
                        <th>Sudah Memilih</th>
                        <th>Belum Memilih</th>
                        <th style="min-width:160px;">Tingkat Partisipasi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($kelasStats)): ?>
                        <tr><td colspan="7" class="table-empty">Belum ada data kelas.</td></tr>
                    <?php else: ?>
                        <?php foreach ($kelasStats as $kl): ?>
                            <?php
                            $totMhs = (int)$kl['total_mahasiswa'];
                            $sudahMhs = (int)$kl['jumlah_sudah_memilih'];
                            $belumMhs = (int)$kl['jumlah_belum_memilih'];
                            $pctKelas = $totMhs > 0 ? round(($sudahMhs / $totMhs) * 100, 1) : 0.0;
                            ?>
                            <tr class="data-row">
                                <td>
                                    <a href="<?= e(route_url('manajemen_mahasiswa', ['kelas_id' => (int)$kl['id']])) ?>" class="badge primary">
                                        <?= e($kl['nama_kelas']) ?>
                                    </a>
                                </td>
                                <td class="fw-500"><?= e($kl['jurusan']) ?></td>
                                <td><span class="data-cell-mono">Semester <?= (int)$kl['semester'] ?></span></td>
                                <td><span class="data-cell-mono fw-600"><?= number_format($totMhs) ?></span></td>
                                <td><span class="badge success dot"><?= number_format($sudahMhs) ?></span></td>
                                <td><span class="badge neutral"><?= number_format($belumMhs) ?></span></td>
                                <td>
                                    <div class="d-flex align-center gap-2">
                                        <div class="progress" style="flex:1;">
                                            <div class="progress-bar success" style="width: <?= $pctKelas ?>%;"></div>
                                        </div>
                                        <span class="data-cell-mono fw-600" style="min-width:42px;text-align:right;"><?= $pctKelas ?>%</span>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <?php if ($canViewLogs): ?>
    <!-- Log Keamanan Terbaru (Eksklusif Superadmin & Banwasra) -->
    <section class="col-12 card" id="dashboardRecentLogsCard">
        <div class="card-head" style="flex-wrap:wrap;gap:12px;">
            <div class="card-title-wrap">
                <h3 class="card-title">Aktivitas &amp; Log Keamanan Terbaru</h3>
                <p class="card-sub"><?= $excludeSuperadminLogs ? 'Terpisah antara log aktivitas pemilih & pengguna manajemen (KPRM & Banwasra, tanpa Superadmin)' : 'Terpisah antara log aktivitas pemilih di bilik dan seluruh pengguna manajemen panel' ?></p>
            </div>
            <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                <div class="tabs pills" role="tablist" style="margin-bottom:0;">
                    <button type="button" class="tab is-active" data-dash-log-tab="all">Semua (<?= count($recentLogsAll) ?>)</button>
                    <button type="button" class="tab" data-dash-log-tab="pemilih">Pemilih (<?= number_format($logStats['total_pemilih']) ?>)</button>
                    <button type="button" class="tab" data-dash-log-tab="admin">Admin (<?= number_format($logStats['total_admin']) ?>)</button>
                </div>
                <a href="<?= e(route_url('log_keamanan', ['tab' => 'pemilih'])) ?>" class="btn btn--soft-primary btn--sm">Log Pemilih</a>
                <a href="<?= e(route_url('log_keamanan', ['tab' => 'admin'])) ?>" class="btn btn--ghost btn--sm">Log Admin</a>
            </div>
        </div>
        <?php
        $dashPanels = [
            'all'     => $recentLogsAll,
            'pemilih' => $recentLogsPemilih,
            'admin'   => $recentLogsAdmin,
        ];
        foreach ($dashPanels as $panelKey => $panelRows):
        ?>
        <div class="data-table-wrap" data-dash-log-panel="<?= e($panelKey) ?>" <?= $panelKey !== 'all' ? 'style="display:none;"' : '' ?>>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Waktu</th>
                        <th>Status</th>
                        <th>Jenis</th>
                        <th>Kode Event</th>
                        <th>Keterangan</th>
                        <th><?= $panelKey === 'admin' ? 'Pengguna Manajemen' : ($panelKey === 'pemilih' ? 'Pemilih (NIM / Nama)' : 'Aktor / Pemilih') ?></th>
                        <th>Bilik</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($panelRows)): ?>
                        <tr><td colspan="7" class="table-empty">Belum ada log pada kategori ini.</td></tr>
                    <?php else: ?>
                        <?php foreach ($panelRows as $log): ?>
                            <?php
                            $isFraud = ($log['status_log'] === 'FRAUD');
                            $logCategory = ($log['log_category'] ?? 'pemilih') === 'admin' ? 'admin' : 'pemilih';
                            $actorRoleRaw = strtolower((string) ($log['actor_role'] ?? ''));
                            $roleBadgeClass = match ($actorRoleRaw) {
                                'superadmin' => 'danger',
                                'kprm'       => 'primary',
                                'banwasra'   => 'purple',
                                default      => 'warning',
                            };
                            ?>
                            <tr class="data-row <?= $isFraud ? 'row-log-fraud' : 'row-log-normal' ?>">
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
                                <td><span class="data-cell-mono fw-600"><?= e($log['kode_event']) ?></span></td>
                                <td><?= e($log['keterangan']) ?></td>
                                <td>
                                    <?php if ($logCategory === 'admin'): ?>
                                        <span class="data-cell-mono fw-600"><?= e($log['actor_username'] ?: '-') ?></span>
                                        <span class="badge <?= $roleBadgeClass ?>">
                                            <?= e($actorRoleRaw !== '' ? strtoupper($actorRoleRaw) : 'PANEL') ?>
                                        </span>
                                    <?php elseif (!empty($log['nim'])): ?>
                                        <span class="data-cell-mono"><?= e($log['nim']) ?></span> <?= !empty($log['nama_mahasiswa']) ? '· ' . e($log['nama_mahasiswa']) : '' ?>
                                    <?php else: ?>
                                        <span class="text-muted">Anonim / Pra-Verifikasi</span>
                                    <?php endif; ?>
                                </td>
                                <td><span class="data-cell-mono"><?= e($log['nama_bilik'] ?? '-') ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php endforeach; ?>
    </section>
    <script>
    document.addEventListener('DOMContentLoaded', () => {
        const card = document.getElementById('dashboardRecentLogsCard');
        if (!card) return;
        card.addEventListener('click', e => {
            const btn = e.target.closest('[data-dash-log-tab]');
            if (!btn) return;
            const targetKey = btn.getAttribute('data-dash-log-tab');
            card.querySelectorAll('[data-dash-log-tab]').forEach(b => {
                b.classList.toggle('is-active', b === btn);
            });
            card.querySelectorAll('[data-dash-log-panel]').forEach(panel => {
                panel.style.display = panel.getAttribute('data-dash-log-panel') === targetKey ? '' : 'none';
            });
        });
    });
    </script>
    <?php endif; ?>
</div>

<?php require BASE_PATH . '/views/layouts/footer.php'; ?>
