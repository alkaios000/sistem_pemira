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

    <!-- Adminator 2026 Modular CSS -->
    <link rel="stylesheet" href="<?= e(asset_url('css/base.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset_url('css/layout.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset_url('css/scrollbar.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset_url('css/components/buttons.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset_url('css/components/cards.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset_url('css/components/forms.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset_url('css/components/tables.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset_url('css/components/modals.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset_url('css/components/badges.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset_url('css/modules/dashboard.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset_url('css/modules/portal_qr.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset_url('css/modules/voting.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset_url('css/modules/live_count.css')) ?>">

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
                <span class="badge primary dot">2026/2027</span>
                <?php if (is_authenticated()): ?>
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
