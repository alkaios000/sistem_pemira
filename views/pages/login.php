<?php
/**
 * LOGIN PAGE - Open White Canvas dengan Animasi Node Interaktif
 */
redirect_if_authenticated();

$pageTitle = 'Login';
$isFullScreenLayout = true;
$deferFlashInPage = true;
require BASE_PATH . '/views/layouts/header.php';
?>

<main class="auth-main">
    <canvas class="auth-node-canvas" id="authNodeCanvas" aria-hidden="true"></canvas>

    <header class="auth-banner">
        <img src="<?= e(asset_url('img/ubw_logo.png')) ?>" alt="Logo Universitas Bhina Widyantara" class="auth-banner-logo">
        <div class="auth-banner-text">
            <span class="auth-banner-title">Sistem PEMIRA</span>
            <span class="auth-banner-sub">Universitas Bhina Widyantara</span>
        </div>
    </header>

    <div class="auth-wrap">
        <div class="auth-error-msg<?= !empty($flash) ? ' is-visible' : '' ?>" id="authErrorMsg" role="alert" aria-live="polite"><?= !empty($flash) ? e($flash['message']) : '' ?></div>

        <form class="auth-form" id="authLoginForm" action="<?= e(action_url('auth')) ?>" method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="sub_action" value="login">

            <div class="input-icon auth-input-group">
                <span class="ico" aria-hidden="true">
                    <svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                </span>
                <input type="text"
                       id="username"
                       name="username"
                       class="input auth-input"
                       placeholder="Username"
                       aria-label="Username"
                       autocomplete="username"
                       required
                       autofocus>
            </div>

            <div class="input-icon auth-input-group has-suffix">
                <span class="ico" aria-hidden="true">
                    <svg viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                </span>
                <input type="password"
                       id="password"
                       name="password"
                       class="input auth-input"
                       placeholder="Password"
                       aria-label="Password"
                       autocomplete="current-password"
                       required>
                <button type="button"
                        class="auth-eye-btn"
                        data-toggle-password="password"
                        aria-label="Tampilkan atau sembunyikan password"
                        title="Tampilkan / Sembunyikan Password">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                </button>
            </div>

            <button type="submit" class="btn btn--primary btn-block auth-submit">Masuk</button>
        </form>
    </div>

    <nav class="auth-shortcuts" aria-label="Pintasan Halaman">
        <a href="<?= e(route_url('bilik_pemilihan')) ?>" class="auth-shortcut-link">Bilik Suara</a>
        <a href="<?= e(route_url('live_count_bem')) ?>" class="auth-shortcut-link">Live BEM</a>
        <a href="<?= e(route_url('live_count_bpm')) ?>" class="auth-shortcut-link">Live BPM</a>
    </nav>
</main>

<?php require BASE_PATH . '/views/layouts/footer.php'; ?>
