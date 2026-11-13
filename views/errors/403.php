<?php
$pageTitle = '403 Akses Ditolak';
$isFullScreenLayout = true;
require BASE_PATH . '/views/layouts/header.php';
?>
<div class="error-shell">
    <div class="error-card">
        <div class="error-code">403</div>
        <h1 class="error-title">Akses Ditolak</h1>
        <p class="error-sub"><?= e($errorMessage ?? 'Anda tidak memiliki otorisasi untuk mengakses halaman ini.') ?></p>
        <div class="error-actions">
            <a href="<?= e(base_url('index.php')) ?>" class="btn btn--primary">Kembali ke Beranda</a>
            <a href="<?= e(route_url('login')) ?>" class="btn btn--ghost">Halaman Login</a>
        </div>
    </div>
</div>
<?php require BASE_PATH . '/views/layouts/footer.php'; ?>
