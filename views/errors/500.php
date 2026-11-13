<?php
$pageTitle = '500 Kesalahan Server';
$isFullScreenLayout = true;
require BASE_PATH . '/views/layouts/header.php';
?>
<div class="error-shell">
    <div class="error-card">
        <div class="error-code">500</div>
        <h1 class="error-title">Terjadi Kesalahan Internal</h1>
        <p class="error-sub"><?= e($errorMessage ?? 'Permintaan tidak dapat diproses saat ini. Silakan coba kembali.') ?></p>
        <div class="error-actions">
            <a href="<?= e(base_url('index.php')) ?>" class="btn btn--primary">Muat Ulang Beranda</a>
        </div>
    </div>
</div>
<?php require BASE_PATH . '/views/layouts/footer.php'; ?>
