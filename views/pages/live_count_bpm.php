<?php
/**
 * LIVE COUNT BPM - Adminator 2026 Redesign (Fitur 6)
 */
require_once BASE_PATH . '/models/kandidat_model.php';

$pdo = get_db_connection();
$pageTitle = 'Live Count BPM';
$isFullScreenLayout = !is_authenticated();
$liveData = get_live_count_summary($pdo, 'BPM');

$extraScripts = [
    'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js',
    asset_url('js/modules/live_count_chart.js')
];

require BASE_PATH . '/views/layouts/header.php';
?>

<?php if ($isFullScreenLayout): ?>
<header class="lc-standalone-topbar">
    <div class="d-flex align-center gap-2">
        <span class="fw-700">Sistem PEMIRA · Live Count BPM</span>
    </div>
    <div class="d-flex align-center gap-2">
        <a href="<?= e(route_url('live_count_bem')) ?>" class="btn btn--soft-primary btn--xs">Live Count BEM</a>
        <a href="<?= e(route_url('login')) ?>" class="btn btn--ghost btn--xs">Login</a>
    </div>
</header>
<div class="content">
<?php endif; ?>

<div id="liveCountContainer" data-kategori="BPM">
    <section class="hero">
        <div class="hero-text">
            <span class="eyebrow" style="color:var(--purple);">Real-Time · Fitur 6</span>
            <h1 class="hero-title">Live Count Ketua Umum BPM</h1>
        </div>
        <div class="hero-actions">
            <span class="badge success dot" id="lcLastUpdated">LIVE · <?= date('H:i:s') ?></span>
            <a href="<?= e(route_url('live_count_bem')) ?>" class="btn btn--ghost btn--sm">← Lihat BEM</a>
        </div>
    </section>

    <!-- KPI Row -->
    <div class="kpi-grid">
        <div class="kpi-card">
            <div class="kpi-top">
                <span class="kpi-label">Suara Sah BPM</span>
                <span class="kpi-pill flat">Total</span>
            </div>
            <div class="kpi-value" id="lcTotalVotes"><?= number_format($liveData['total_suara_kandidat']) ?></div>
            <div class="kpi-compare"><span>Terhitung otomatis</span></div>
        </div>
        <div class="kpi-card">
            <div class="kpi-top">
                <span class="kpi-label">Total DPT</span>
                <span class="kpi-pill info">Terdaftar</span>
            </div>
            <div class="kpi-value" id="lcTotalDpt"><?= number_format($liveData['total_dpt']) ?></div>
            <div class="kpi-compare"><span>Mahasiswa aktif</span></div>
        </div>
        <div class="kpi-card">
            <div class="kpi-top">
                <span class="kpi-label">Partisipasi</span>
                <span class="kpi-pill up">Turnout</span>
            </div>
            <div class="kpi-value" id="lcTurnoutPct"><?= e($liveData['partisipasi_persen']) ?>%</div>
            <div class="kpi-compare"><span>Rasio pemilih</span></div>
        </div>
        <div class="kpi-card">
            <div class="kpi-top">
                <span class="kpi-label">Calon Ketua BPM</span>
                <span class="kpi-pill flat">BPM</span>
            </div>
            <div class="kpi-value"><?= count($liveData['kandidats']) ?></div>
            <div class="kpi-compare"><span>Kandidat bertanding</span></div>
        </div>
    </div>

    <!-- Candidate Summary Cards -->
    <div class="lc-cand-grid mb-4" id="lcCandidateCards">
        <?php foreach ($liveData['kandidats'] as $c): ?>
            <?php $fotoUrl = !empty($c['foto_path']) ? upload_url($c['foto_path']) : asset_url('img/default_candidate.svg'); ?>
            <div class="lc-cand-card" data-cand-id="<?= (int)$c['id'] ?>">
                <div class="lc-cand-top">
                    <img src="<?= e($fotoUrl) ?>" alt="Calon" class="lc-cand-photo">
                    <div>
                        <span class="eyebrow" style="color:var(--purple);">CALON #0<?= (int)$c['nomor_urut'] ?></span>
                        <div class="fw-700" style="font-size:13.5px;"><?= e($c['nama_calon']) ?></div>
                    </div>
                </div>
                <div class="lc-cand-stat">
                    <span class="lc-cand-votes"><span class="cand-vote-num"><?= number_format((int)$c['jumlah_suara']) ?></span> <small class="text-muted" style="font-size:11px;font-weight:500;">suara</small></span>
                    <span class="lc-cand-pct cand-vote-pct" style="color:var(--purple);"><?= e($c['persentase']) ?>%</span>
                </div>
                <div class="progress">
                    <div class="progress-bar purple cand-vote-bar" style="width: <?= e($c['persentase']) ?>%;"></div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Charts Grid -->
    <div class="grid">
        <section class="col-7 card">
            <div class="card-head">
                <div class="card-title-wrap">
                    <span class="eyebrow" style="color:var(--purple);">Distribusi Suara</span>
                    <h3 class="card-title">Grafik Batang Perolehan Suara BPM</h3>
                </div>
            </div>
            <div class="chart-box">
                <canvas id="lcBarChart"></canvas>
            </div>
        </section>

        <section class="col-5 card">
            <div class="card-head">
                <div class="card-title-wrap">
                    <span class="eyebrow" style="color:var(--purple);">Proporsi Persentase</span>
                    <h3 class="card-title">Rasio Suara Masuk BPM</h3>
                </div>
            </div>
            <div class="chart-box">
                <canvas id="lcDoughnutChart"></canvas>
            </div>
        </section>
    </div>
</div>

<?php if ($isFullScreenLayout): ?>
</div>
<?php endif; ?>

<?php require BASE_PATH . '/views/layouts/footer.php'; ?>
