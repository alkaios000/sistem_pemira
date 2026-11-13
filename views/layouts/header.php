<?php
/**
 * HEADER LAYOUT - Adminator 2026 Redesign Shell
 */
$pageTitle = $pageTitle ?? 'Dashboard';
$isFullScreenLayout = $isFullScreenLayout ?? false;
$flash = get_flash();
$appName = env_val('APP_NAME', 'Sistem E-Voting PEMIRA');
?>
<!DOCTYPE html>
<html lang="id" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <title><?= e($pageTitle) ?> · <?= e($appName) ?></title>

    <!-- App Icons & Favicon (Circular Transparent PNG) -->
    <link rel="icon" type="image/png" sizes="72x72" href="<?= e(asset_url('img/icons/icon-72.png')) ?>?v=2">
    <link rel="icon" type="image/png" sizes="96x96" href="<?= e(asset_url('img/icons/icon-96.png')) ?>?v=2">
    <link rel="icon" type="image/png" sizes="128x128" href="<?= e(asset_url('img/icons/icon-128.png')) ?>?v=2">
    <link rel="icon" type="image/png" sizes="192x192" href="<?= e(asset_url('img/icons/icon-192.png')) ?>?v=2">
    <link rel="apple-touch-icon" sizes="144x144" href="<?= e(asset_url('img/icons/icon-144.png')) ?>?v=2">
    <link rel="apple-touch-icon" sizes="192x192" href="<?= e(asset_url('img/icons/icon-192.png')) ?>?v=2">
    <link rel="apple-touch-icon" sizes="512x512" href="<?= e(asset_url('img/icons/icon-512.png')) ?>?v=2">

    <!-- Adminator 2026 Modular CSS -->
    <link rel="stylesheet" href="<?= e(asset_url('css/base.css')) ?>?v=11">
    <link rel="stylesheet" href="<?= e(asset_url('css/layout.css')) ?>?v=11">
    <link rel="stylesheet" href="<?= e(asset_url('css/scrollbar.css')) ?>?v=11">
    <link rel="stylesheet" href="<?= e(asset_url('css/components/buttons.css')) ?>?v=11">
    <link rel="stylesheet" href="<?= e(asset_url('css/components/cards.css')) ?>?v=11">
    <link rel="stylesheet" href="<?= e(asset_url('css/components/forms.css')) ?>?v=11">
    <link rel="stylesheet" href="<?= e(asset_url('css/components/tables.css')) ?>?v=11">
    <link rel="stylesheet" href="<?= e(asset_url('css/components/modals.css')) ?>?v=15">
    <link rel="stylesheet" href="<?= e(asset_url('css/components/badges.css')) ?>?v=11">
    <link rel="stylesheet" href="<?= e(asset_url('css/modules/dashboard.css')) ?>?v=13">
    <link rel="stylesheet" href="<?= e(asset_url('css/modules/portal_qr.css')) ?>?v=11">
    <link rel="stylesheet" href="<?= e(asset_url('css/modules/voting.css')) ?>?v=11">
    <link rel="stylesheet" href="<?= e(asset_url('css/modules/live_count.css')) ?>?v=11">

    <script>
        window.PEMIRA_CONFIG = {
            baseUrl: "<?= e(base_url()) ?>",
            csrfToken: "<?= e(csrf_token()) ?>"
        };
    </script>
</head>
<body>
<?php if (!$isFullScreenLayout): ?>
<div class="drawer-backdrop" id="drawerBackdrop" aria-hidden="true"></div>
<div class="shell">
    <?php require BASE_PATH . '/views/layouts/sidebar.php'; ?>
    <div class="main">
        <header class="d-topbar">
            <div class="crumbs">
                <button type="button" class="hamburger" data-drawer-open aria-label="Menu">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
                </button>
                <span>Sistem PEMIRA</span>
                <span class="sep">/</span>
                <span class="current"><?= e($pageTitle) ?></span>
            </div>
            <div class="topbar-actions">
                <?php if (is_authenticated()): ?>
                    <div class="topbar-user">
                        <span class="topbar-user-name"><?= e($_SESSION['nama_lengkap'] ?? 'Panitia PEMIRA') ?></span>
                        <span class="badge primary"><?= e($_SESSION['user_role'] ?? 'ADMIN') ?></span>
                    </div>
                    <a href="<?= e(route_url('logout')) ?>" class="btn btn--ghost btn--sm">Keluar</a>
                <?php endif; ?>
            </div>
        </header>
        <main class="content">
            <?php if ($flash): ?>
                <div class="alert <?= e($flash['type']) ?>">
                    <div class="alert-body"><?= e($flash['message']) ?></div>
                </div>
            <?php endif; ?>
<?php else: ?>
<div class="standalone-shell">
    <?php if ($flash): ?>
        <div style="max-width: 1080px; width: 100%; margin: 12px auto 0; padding: 0 24px;">
            <div class="alert <?= e($flash['type']) ?>">
                <div class="alert-body"><?= e($flash['message']) ?></div>
            </div>
        </div>
    <?php endif; ?>
<?php endif; ?>
