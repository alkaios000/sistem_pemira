<?php
$pageTitle = '404 Halaman Tidak Ditemukan';
$isFullScreenLayout = true;
require BASE_PATH . '/views/layouts/header.php';
?>
<div class="error-shell">
    <div class="error-card">
        <div class="error-code">404</div>
        <h1 class="error-title">Halaman Tidak Ditemukan</h1>
        <p class="error-sub"><?= e($errorMessage ?? 'Rute atau halaman yang Anda tuju tidak tersedia di sistem PEMIRA.') ?></p>
        <div class="error-actions">
            <a href="<?= e(base_url('index.php')) ?>" class="btn btn--primary">Kembali ke Beranda</a>
        </div>
    </div>
</div>
<?php require BASE_PATH . '/views/layouts/footer.php'; ?>
