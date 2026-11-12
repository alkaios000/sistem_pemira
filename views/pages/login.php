<?php
/**
 * LOGIN PAGE - Adminator 2026 Redesign Split Auth Shell
 */
redirect_if_authenticated();

$pageTitle = 'Login Panitia';
$isFullScreenLayout = true;
require BASE_PATH . '/views/layouts/header.php';
?>

<div class="auth-shell">
    <aside class="auth-aside">
        <div class="auth-aside-brand">
            <span>Sistem PEMIRA</span>
        </div>
        <div class="auth-aside-body">
            <div class="auth-aside-eyebrow">E-Voting · BEM & BPM</div>
            <h2 class="auth-aside-title">Sistem Pemilihan Raya Mahasiswa Terintegrasi.</h2>
            <p class="auth-aside-sub">Arsitektur Single-Use Dynamic QR Token per bilik, penguncian sesi otomatis (0ms), dan audit log deteksi kecurangan real-time.</p>
            <div class="auth-aside-stats">
                <div>
                    <div class="auth-stat-value">8s</div>
                    <div class="auth-stat-label">Rotasi QR</div>
                </div>
                <div>
                    <div class="auth-stat-value">0ms</div>
                    <div class="auth-stat-label">Instant Lock</div>
                </div>
                <div>
                    <div class="auth-stat-value">100%</div>
                    <div class="auth-stat-label">Secret Ballot</div>
                </div>
            </div>
        </div>
        <div class="auth-aside-foot">
            <span>KPURM · Periode 2026/2027</span>
            <span>Adminator 2026 UI</span>
        </div>
    </aside>

    <main class="auth-main">
        <div class="auth-card">
            <div class="auth-head">
                <span class="eyebrow">Otentikasi Panel</span>
                <h1 class="auth-title">Masuk ke Sistem</h1>
                <p class="auth-sub">Gunakan akun Administrator KPU atau Panitia Pengawas TPS.</p>
            </div>

            <form action="<?= e(action_url('auth')) ?>" method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="sub_action" value="login">

                <div class="field">
                    <label class="field-label" for="username">Username</label>
                    <input type="text" id="username" name="username" class="input" placeholder="admin" required autofocus>
                </div>

                <div class="field">
                    <label class="field-label" for="password">Password</label>
                    <input type="password" id="password" name="password" class="input" placeholder="••••••••" required>
                </div>

                <button type="submit" class="btn btn--primary btn-block mt-3" style="padding: 9px 14px;">Masuk Sistem</button>
            </form>

            <div class="card mt-4" style="padding: 12px 14px; background: var(--bg-hover);">
                <div class="eyebrow mb-2">Portal Publik & Bilik</div>
                <div class="d-flex flex-wrap gap-2">
                    <a href="<?= e(route_url('portal_qr')) ?>" class="btn btn--soft-primary btn--sm">Portal QR</a>
                    <a href="<?= e(route_url('bilik_pemilihan')) ?>" class="btn btn--ghost btn--sm">Bilik Suara</a>
                    <a href="<?= e(route_url('live_count_bem')) ?>" class="btn btn--ghost btn--sm">Live BEM</a>
                    <a href="<?= e(route_url('live_count_bpm')) ?>" class="btn btn--ghost btn--sm">Live BPM</a>
                </div>
                <div class="mt-2 pt-2" style="border-top: 1px dashed var(--border); font-size: 11px; color: var(--t-muted);">
                    Akun: <span class="mono">admin</span> (<span class="mono">admin123</span>) · <span class="mono">panitia</span> (<span class="mono">panitia123</span>)
                </div>
            </div>
        </div>
    </main>
</div>

<?php require BASE_PATH . '/views/layouts/footer.php'; ?>
