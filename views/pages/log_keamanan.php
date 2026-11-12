<?php
/**
 * LOG KEAMANAN - Adminator 2026 Redesign Audit Trail (Fitur 5)
 * Hijau = Normal | Merah = Fraud / Anomali
 */
require_auth();
require_once BASE_PATH . '/models/log_model.php';

$pdo = get_db_connection();
$pageTitle = 'Log Keamanan';
$logs = get_audit_logs($pdo, null, null, '', 300);
$logStats = get_audit_log_stats($pdo);

require BASE_PATH . '/views/layouts/header.php';
?>

<section class="hero">
    <div class="hero-text">
        <span class="eyebrow">Audit Trail · Fitur 5</span>
        <h1 class="hero-title">Log Keamanan & Deteksi Fraud</h1>
    </div>
    <div class="hero-actions">
        <a href="<?= e(action_url('log', ['sub_action' => 'export_csv'])) ?>" class="btn btn--soft-primary btn--sm">Export CSV</a>
        <a href="<?= e(route_url('log_keamanan')) ?>" class="btn btn--ghost btn--sm">Refresh</a>
    </div>
</section>

<!-- KPI Row -->
<div class="kpi-grid" style="grid-template-columns: repeat(3, minmax(0, 1fr));">
    <div class="kpi-card">
        <div class="kpi-top">
            <span class="kpi-label">Total Event Terekam</span>
            <span class="kpi-pill info">Audit</span>
        </div>
        <div class="kpi-value"><?= number_format($logStats['total_logs']) ?></div>
        <div class="kpi-compare"><span>Seluruh aktivitas sistem</span></div>
    </div>
    <div class="kpi-card">
        <div class="kpi-top">
            <span class="kpi-label">Aktivitas Normal (Hijau)</span>
            <span class="kpi-pill up">Valid</span>
        </div>
        <div class="kpi-value"><?= number_format($logStats['total_normal']) ?></div>
        <div class="kpi-compare"><span>Suara sah terekam</span></div>
    </div>
    <div class="kpi-card">
        <div class="kpi-top">
            <span class="kpi-label">Anomali / Fraud (Merah)</span>
            <span class="kpi-pill down">Alert</span>
        </div>
        <div class="kpi-value"><?= number_format($logStats['total_fraud']) ?></div>
        <div class="kpi-compare"><span>Double scan, foto QR & NIM ulang</span></div>
    </div>
</div>

<!-- Adminator 2026 Data Table -->
<div class="grid">
    <section class="col-12 card" data-datatable-card>
        <div class="data-toolbar">
            <div class="data-toolbar-left">
                <div class="input-icon" style="flex:1;max-width:340px;">
                    <span class="ico"><svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg></span>
                    <input type="search" class="input" data-dt-search placeholder="Cari kode event, keterangan, NIM, atau Device ID...">
                </div>
            </div>
            <div class="data-toolbar-right">
                <select class="select" data-dt-filter="data-kategori" style="width:auto;padding:6px 28px 6px 10px;font-size:12px;">
                    <option value="ALL">Semua Kategori</option>
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
                        <th data-sort>Kode Event <span class="sort">▾</span></th>
                        <th>Keterangan Aktivitas</th>
                        <th data-sort>NIM / Mahasiswa <span class="sort">▾</span></th>
                        <th data-sort>Bilik <span class="sort">▾</span></th>
                        <th>Device / IP</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $log): ?>
                        <?php $isFraud = ($log['status_log'] === 'FRAUD'); ?>
                        <tr class="data-row <?= $isFraud ? 'row-log-fraud' : 'row-log-normal' ?>" data-kategori="<?= e($log['status_log']) ?>">
                            <td><span class="data-cell-mono"><?= e(format_tanggal_indo($log['created_at'])) ?></span></td>
                            <td>
                                <span class="badge <?= $isFraud ? 'danger' : 'success' ?> dot">
                                    <?= $isFraud ? 'FRAUD' : 'NORMAL' ?>
                                </span>
                            </td>
                            <td>
                                <div class="data-cell-mono fw-600"><?= e($log['kode_event']) ?></div>
                                <div class="text-muted" style="font-size:11px;"><?= e($log['judul_event']) ?></div>
                            </td>
                            <td><?= e($log['keterangan']) ?></td>
                            <td>
                                <div class="data-cell-mono fw-600"><?= e($log['nim'] ?: '-') ?></div>
                                <div class="text-muted" style="font-size:11px;"><?= e($log['nama_mahasiswa'] ?: '') ?></div>
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

<?php require BASE_PATH . '/views/layouts/footer.php'; ?>
