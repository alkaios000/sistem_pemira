<?php
/**
 * SHARED PUBLIC LIVE COUNT BOARD (BEM & BPM)
 * Menampilkan foto kandidat, Visi & Misi, Flip Counter 3D real-time, dan grafik interaktif.
 */
require_once BASE_PATH . '/models/kandidat_model.php';
require_once BASE_PATH . '/models/user_model.php';

$pdo = get_db_connection();
$kategori = ($kategori ?? 'BEM') === 'BPM' ? 'BPM' : 'BEM';
$isBpm = ($kategori === 'BPM');
$pageTitle = 'Live Count ' . $kategori;
$isPublicParam = (isset($_GET['public']) && $_GET['public'] === '1');
$isFullScreenLayout = !is_authenticated() || $isPublicParam;
$liveCountPublik = get_setting_value($pdo, 'live_count_publik', 'BUKA');
$isLiveCountHidden = ($liveCountPublik !== 'BUKA' && !is_authenticated());
$liveData = $isLiveCountHidden
    ? ['kandidats' => [], 'total_suara_kandidat' => 0, 'total_dpt' => 0, 'partisipasi_persen' => 0]
    : get_live_count_summary($pdo, $kategori);

$extraScripts = $isLiveCountHidden
    ? []
    : [
        asset_url('js/vendor/chart.umd.min.js') . '?v=1',
        asset_url('js/modules/live_count_chart.js'),
    ];

$routeParams = $isPublicParam ? ['public' => '1'] : [];
$bemUrl = route_url('live_count_bem', $routeParams);
$bpmUrl = route_url('live_count_bpm', $routeParams);
$publicPreviewUrl = route_url($isBpm ? 'live_count_bpm' : 'live_count_bem', ['public' => '1']);
$adminBackUrl = route_url($isBpm ? 'live_count_bpm' : 'live_count_bem');

// Hitung suara tertinggi saat ini untuk indikator Unggul Sementara
$maxVotes = 0;
foreach ($liveData['kandidats'] as $candItem) {
    $v = (int) $candItem['jumlah_suara'];
    if ($v > $maxVotes) {
        $maxVotes = $v;
    }
}

$parseMisiLines = static function (string $misiText): array {
    $lines = preg_split('/\r\n|\r|\n/', trim($misiText)) ?: [];
    $cleaned = [];
    foreach ($lines as $line) {
        $line = trim((string) preg_replace('/^[\-\•\*\d\.\)\s]+/', '', trim($line)));
        if ($line !== '') {
            $cleaned[] = $line;
        }
    }
    return $cleaned;
};

require BASE_PATH . '/views/layouts/header.php';
?>

<?php if ($isFullScreenLayout): ?>
<div class="lc-standalone-shell">
    <header class="lc-standalone-topbar">
        <div class="lc-brand">
            <img src="<?= e(asset_url('img/ubw_logo.png')) ?>" alt="Logo UBW" class="brand-img-sm">
            <div>
                <div class="lc-brand-title">PEMIRA 2026 · Live Real Count</div>
                <div class="lc-brand-sub"><?= $isBpm ? 'Ketua Umum BPM' : 'Presiden BEM' ?> · Transparan & Real-Time</div>
            </div>
        </div>

        <nav class="lc-cat-switch" aria-label="Pilih Kategori Live Count">
            <a href="<?= e($bemUrl) ?>" class="lc-cat-pill is-bem <?= !$isBpm ? 'is-active' : '' ?>">Presiden BEM</a>
            <a href="<?= e($bpmUrl) ?>" class="lc-cat-pill is-bpm <?= $isBpm ? 'is-active' : '' ?>">Ketua BPM</a>
        </nav>

        <div class="d-flex align-center gap-2" style="flex-wrap:wrap;">
            <span class="lc-live-badge" id="lcLastUpdated">
                <span class="lc-live-dot"></span>
                <span>LIVE · <?= date('H:i:s') ?></span>
            </span>
            <button type="button" class="btn btn--ghost btn--xs" onclick="toggleLiveFullscreen()">Layar Penuh</button>
            <?php if (is_authenticated()): ?>
                <a href="<?= e($adminBackUrl) ?>" class="btn btn--soft-primary btn--xs">← Mode Admin</a>
            <?php else: ?>
                <a href="<?= e(route_url('login')) ?>" class="btn btn--ghost btn--xs">Login Panitia</a>
            <?php endif; ?>
        </div>
    </header>
    <div class="lc-public-content">
<?php endif; ?>

<div id="liveCountContainer" data-kategori="<?= e($kategori) ?>">
    <?php if (!$isFullScreenLayout): ?>
        <div class="lc-topbar-row">
            <nav class="lc-cat-switch" aria-label="Pilih Kategori Live Count">
                <a href="<?= e(route_url('live_count_bem')) ?>" class="lc-cat-pill is-bem <?= !$isBpm ? 'is-active' : '' ?>">Presiden BEM</a>
                <a href="<?= e(route_url('live_count_bpm')) ?>" class="lc-cat-pill is-bpm <?= $isBpm ? 'is-active' : '' ?>">Ketua BPM</a>
            </nav>
            <div class="d-flex align-center gap-2" style="flex-wrap:wrap;">
                <span class="lc-live-badge" id="lcLastUpdated">
                    <span class="lc-live-dot"></span>
                    <span>LIVE · <?= date('H:i:s') ?></span>
                </span>
                <button type="button" class="btn btn--ghost btn--sm" onclick="toggleLiveFullscreen()">Layar Penuh</button>
                <a href="<?= e($publicPreviewUrl) ?>" target="_blank" class="btn btn--soft-primary btn--sm">Mode Proyektor Publik ↗</a>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($isLiveCountHidden): ?>
        <section class="card text-center" style="padding:36px 24px;">
            <div class="mb-2"><span class="badge warn dot">Publikasi Sementara Ditutup</span></div>
            <h2 class="hero-title" style="font-size:20px;">Hasil Live Count <?= e($kategori) ?> Sedang Disembunyikan</h2>
            <p class="text-muted mt-2 mb-3">Panitia KPRM sedang menunda publikasi perolehan suara secara terbuka. Silakan cek kembali setelah pengumuman resmi dibuka.</p>
            <div>
                <a href="<?= e(route_url('bilik_pemilihan')) ?>" class="btn btn--soft-primary btn--sm">Ke Bilik Pemilihan</a>
            </div>
        </section>
    <?php else: ?>
    <!-- KPI Ringkas + Flip Counter Total Suara -->
    <div class="kpi-grid">
        <div class="kpi-card">
            <div class="kpi-top">
                <span class="kpi-label">Suara Sah <?= e($kategori) ?></span>
                <span class="kpi-pill <?= $isBpm ? 'flat' : 'primary' ?>">Real-Time</span>
            </div>
            <div class="mt-1 mb-1">
                <div class="flip-counter flip-counter--sm" id="lcTotalVotes" data-flip-value="<?= (int)$liveData['total_suara_kandidat'] ?>"></div>
            </div>
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
                <span class="kpi-label">Partisipasi Pemilih</span>
                <span class="kpi-pill up">Turnout</span>
            </div>
            <div class="kpi-value" id="lcTurnoutPct"><?= e($liveData['partisipasi_persen']) ?>%</div>
            <div class="progress mt-1" style="height:5px;">
                <div class="progress-bar success" id="lcTurnoutBar" style="width: <?= min(100, (float)$liveData['partisipasi_persen']) ?>%;"></div>
            </div>
        </div>
        <div class="kpi-card">
            <div class="kpi-top">
                <span class="kpi-label">Kandidat <?= e($kategori) ?></span>
                <span class="kpi-pill flat"><?= $isBpm ? 'Ketua BPM' : 'Presiden BEM' ?></span>
            </div>
            <div class="kpi-value" id="lcTotalCandidatCount"><?= count($liveData['kandidats']) ?></div>
            <div class="kpi-compare"><span>Calon resmi bertanding</span></div>
        </div>
    </div>

    <!-- Showcase Card Kandidat (Sesuai Jumlah Calon + Foto + Visi Misi + Flip Counter) -->
    <div class="lc-cand-grid mb-4" id="lcCandidateCards">
        <?php if (empty($liveData['kandidats'])): ?>
            <section class="card" style="grid-column: 1 / -1;">
                <div class="table-empty">Belum ada kandidat <?= e($kategori) ?> yang terdaftar.</div>
            </section>
        <?php else: ?>
            <?php foreach ($liveData['kandidats'] as $c): ?>
                <?php
                $fotoUrl = !empty($c['foto_path']) ? upload_url($c['foto_path']) : asset_url('img/default_candidate.svg');
                $suaraVal = (int) $c['jumlah_suara'];
                $isLeading = ($maxVotes > 0 && $suaraVal === $maxVotes);
                $misiItems = $parseMisiLines((string) ($c['misi'] ?? ''));
                $cJson = json_encode($c, JSON_HEX_APOS | JSON_HEX_QUOT);
                ?>
                <article class="lc-cand-card <?= $isBpm ? 'is-bpm' : '' ?> <?= $isLeading ? 'is-leading' : '' ?>"
                         data-cand-id="<?= (int)$c['id'] ?>"
                         data-cand-json='<?= $cJson ?>'>
                    <div class="lc-cand-ribbon">
                        <span class="kandidat-num-pill <?= $isBpm ? 'is-bpm' : '' ?>">CALON #0<?= (int)$c['nomor_urut'] ?></span>
                        <span class="lc-lead-tag" style="<?= $isLeading ? '' : 'display:none;' ?>">Unggul Sementara</span>
                    </div>

                    <div class="lc-cand-profile">
                        <div class="lc-cand-photo-wrap" onclick="openLiveCandDetail(<?= (int)$c['id'] ?>)" title="Klik untuk melihat detail Visi & Misi lengkap">
                            <img src="<?= e($fotoUrl) ?>" alt="<?= e($c['nama_calon']) ?>" class="lc-cand-photo">
                        </div>
                        <div class="lc-cand-identity">
                            <h3 class="lc-cand-name"><?= e($c['nama_calon']) ?></h3>
                            <span class="lc-cand-prodi"><?= e($c['jurusan_kelas'] ?: 'Program Studi') ?></span>
                        </div>
                    </div>

                    <div class="lc-vm-panel">
                        <div>
                            <div class="lc-vm-label">Visi</div>
                            <p class="lc-visi-text"><?= e($c['visi'] ?: '-') ?></p>
                        </div>
                        <div>
                            <div class="lc-vm-label">Misi</div>
                            <ul class="lc-misi-list">
                                <?php if (empty($misiItems)): ?>
                                    <li>-</li>
                                <?php else: ?>
                                    <?php foreach (array_slice($misiItems, 0, 3) as $mLine): ?>
                                        <li><?= e($mLine) ?></li>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </ul>
                        </div>
                        <div class="lc-vm-foot">
                            <button type="button" class="btn btn--ghost btn--xs" onclick="openLiveCandDetail(<?= (int)$c['id'] ?>)">
                                Visi & Misi Lengkap
                            </button>
                        </div>
                    </div>

                    <div class="lc-counter-box">
                        <div class="lc-counter-top">
                            <div>
                                <div class="lc-counter-label">Perolehan Suara</div>
                                <div class="lc-flip-row">
                                    <div class="flip-counter cand-flip-counter" data-flip-value="<?= $suaraVal ?>"></div>
                                    <span class="lc-vote-unit">suara</span>
                                    <span class="cand-delta-slot"></span>
                                </div>
                            </div>
                            <div class="lc-cand-pct cand-vote-pct"><?= e($c['persentase']) ?>%</div>
                        </div>
                        <div class="lc-progress-track">
                            <div class="lc-progress-fill cand-vote-bar" style="width: <?= min(100, (float)$c['persentase']) ?>%;"></div>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Grafik Perolehan Suara -->
    <div class="grid">
        <section class="col-7 card">
            <div class="card-head">
                <div class="card-title-wrap">
                    <h3 class="card-title">Grafik Perolehan Suara <?= e($kategori) ?></h3>
                </div>
            </div>
            <div class="chart-box">
                <canvas id="lcBarChart"></canvas>
            </div>
        </section>

        <section class="col-5 card">
            <div class="card-head">
                <div class="card-title-wrap">
                    <h3 class="card-title">Proporsi Suara Masuk <?= e($kategori) ?></h3>
                </div>
            </div>
            <div class="chart-box">
                <canvas id="lcDoughnutChart"></canvas>
            </div>
        </section>
    </div>
    <?php endif; ?>
</div>

<!-- Modal Detail Visi & Misi Lengkap (Publik) -->
<div class="modal-backdrop" id="lcCandidateDetailModal">
    <div class="modal-dialog modal-lg">
        <div class="modal-head">
            <h3 class="modal-title" id="lcDetailTitle">Profil Kandidat</h3>
            <button type="button" class="modal-close" data-modal-close="lcCandidateDetailModal">×</button>
        </div>
        <div class="modal-body">
            <div class="d-flex align-center gap-3 mb-3 pb-3" style="border-bottom:1px solid var(--border-soft);flex-wrap:wrap;">
                <img src="<?= e(asset_url('img/default_candidate.svg')) ?>"
                     data-default-src="<?= e(asset_url('img/default_candidate.svg')) ?>"
                     id="lcDetailPhoto"
                     alt="Foto Kandidat"
                     style="width:88px;height:88px;border-radius:14px;object-fit:cover;object-position:center top;border:1px solid var(--border);">
                <div style="flex:1;min-width:200px;">
                    <span class="kandidat-num-pill mb-1" id="lcDetailNumPill">CALON #01</span>
                    <div class="fw-700" id="lcDetailName" style="font-size:19px;font-family:var(--font-display);">-</div>
                    <div class="text-muted mt-1" id="lcDetailProdi" style="font-size:12px;">-</div>
                </div>
                <div class="text-right">
                    <div class="lc-counter-label" style="margin-bottom:2px;">Perolehan Saat Ini</div>
                    <div class="fw-700" id="lcDetailVotes" style="font-size:22px;font-family:var(--font-display);color:var(--primary);">0 suara</div>
                </div>
            </div>

            <div class="mb-3">
                <div class="lc-vm-label" style="font-size:10.5px;">Visi</div>
                <div id="lcDetailVisi" style="font-size:13px;line-height:1.6;padding:10px 12px;background:var(--bg-hover);border:1px solid var(--border-soft);border-radius:8px;white-space:pre-line;">-</div>
            </div>
            <div>
                <div class="lc-vm-label" style="font-size:10.5px;">Misi</div>
                <div id="lcDetailMisi" style="font-size:13px;line-height:1.65;padding:10px 12px;background:var(--bg-hover);border:1px solid var(--border-soft);border-radius:8px;white-space:pre-line;">-</div>
            </div>
        </div>
        <div class="modal-foot">
            <button type="button" class="btn btn--ghost btn--sm" data-modal-close="lcCandidateDetailModal">Tutup</button>
        </div>
    </div>
</div>

<?php if ($isFullScreenLayout): ?>
    </div>
</div>
<?php endif; ?>

<?php require BASE_PATH . '/views/layouts/footer.php'; ?>

