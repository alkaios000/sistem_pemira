<?php
/**
 * HEADER LAYOUT - Adminator 2026 Redesign Shell
 */
$pageTitle = $pageTitle ?? 'Dashboard';
$isFullScreenLayout = $isFullScreenLayout ?? false;
$deferFlashInPage = $deferFlashInPage ?? false;
$flash = get_flash();
$appName = env_val('APP_NAME', 'Sistem E-Voting PEMIRA');
?>
<!DOCTYPE html>
<html lang="id" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php if (empty($skipCsrfMeta)): ?>
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <?php endif; ?>
    <meta name="referrer" content="no-referrer">
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
    <link rel="stylesheet" href="<?= e(asset_url('css/base.css')) ?>?v=12">
    <link rel="stylesheet" href="<?= e(asset_url('css/layout.css')) ?>?v=19">
    <link rel="stylesheet" href="<?= e(asset_url('css/scrollbar.css')) ?>?v=11">
    <link rel="stylesheet" href="<?= e(asset_url('css/components/buttons.css')) ?>?v=11">
    <link rel="stylesheet" href="<?= e(asset_url('css/components/cards.css')) ?>?v=11">
    <link rel="stylesheet" href="<?= e(asset_url('css/components/forms.css')) ?>?v=12">
    <link rel="stylesheet" href="<?= e(asset_url('css/components/tables.css')) ?>?v=11">
    <link rel="stylesheet" href="<?= e(asset_url('css/components/modals.css')) ?>?v=15">
    <link rel="stylesheet" href="<?= e(asset_url('css/components/badges.css')) ?>?v=11">
    <link rel="stylesheet" href="<?= e(asset_url('css/modules/dashboard.css')) ?>?v=13">
    <link rel="stylesheet" href="<?= e(asset_url('css/modules/portal_qr.css')) ?>?v=12">
    <link rel="stylesheet" href="<?= e(asset_url('css/modules/voting.css')) ?>?v=12">
    <link rel="stylesheet" href="<?= e(asset_url('css/modules/live_count.css')) ?>?v=12">

    <script>
        window.PEMIRA_CONFIG = {
            baseUrl: "<?= e(base_url()) ?>",
            csrfToken: "<?= !empty($skipCsrfMeta) ? '' : e(csrf_token()) ?>"
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
                    <?php
                    $roleCode = current_user_role();
                    $roleBadgeLabel = match ($roleCode) {
                        'superadmin' => 'SUPERADMIN',
                        'banwasra'   => 'BANWASRA · VIEWER',
                        'kprm'       => 'KPRM',
                        default      => strtoupper($roleCode ?: 'PANEL'),
                    };
                    $roleBadgeClass = $roleCode === 'banwasra' ? 'purple' : ($roleCode === 'kprm' ? 'info' : 'primary');
                    ?>
                    <a href="<?= e(route_url('profil_akun')) ?>" class="topbar-user <?= ($currentPage ?? '') === 'profil_akun' ? 'is-active' : '' ?>" style="text-decoration:none;color:inherit;" title="Profil Akun &amp; Ganti Kata Sandi">
                        <span class="topbar-user-name"><?= e($_SESSION['nama_lengkap'] ?? 'Pengguna Panel') ?></span>
                        <span class="badge <?= $roleBadgeClass ?>"><?= e($roleBadgeLabel) ?></span>
                    </a>
                    <form action="<?= e(action_url('auth')) ?>" method="POST" style="display:inline;margin:0;" onsubmit="return confirm('Keluar dari sesi panel ini?');">
                        <?= csrf_field() ?>
                        <input type="hidden" name="sub_action" value="logout">
                        <button type="submit" class="btn btn--ghost btn--sm">Keluar</button>
                    </form>
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
    <?php if ($flash && !$deferFlashInPage): ?>
        <div style="max-width: 1080px; width: 100%; margin: 12px auto 0; padding: 0 24px;">
            <div class="alert <?= e($flash['type']) ?>">
                <div class="alert-body"><?= e($flash['message']) ?></div>
            </div>
        </div>
    <?php endif; ?>
<?php endif; ?>
