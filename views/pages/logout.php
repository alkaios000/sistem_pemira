<?php
/**
 * Konfirmasi Logout Aman (Wajib POST + CSRF Token)
 * Menghalau serangan Logout CSRF via request GET (<img src="?page=logout">).
 */

if (!is_authenticated()) {
    redirect(route_url('login'));
}

$pageTitle = 'Konfirmasi Keluar Sesi';
$isFullScreenLayout = true;
require BASE_PATH . '/views/layouts/header.php';
?>
<div class="d-flex justify-center align-center" style="min-height:100vh;padding:24px;">
    <section class="card" style="max-width:420px;width:100%;text-align:center;">
        <h2 class="card-title mb-1">Keluar dari Panel Admin</h2>
        <p class="text-muted mb-3">Tekan tombol di bawah untuk mengakhiri sesi akun <strong><?= e($_SESSION['username'] ?? '') ?></strong> dengan aman.</p>
        <form action="<?= e(action_url('auth')) ?>" method="POST" class="d-flex gap-2 justify-center">
            <?= csrf_field() ?>
            <input type="hidden" name="sub_action" value="logout">
            <a href="<?= e(route_url('dashboard')) ?>" class="btn btn--ghost btn--sm">Batal</a>
            <button type="submit" class="btn btn--soft-danger btn--sm">Ya, Keluar Sekarang</button>
        </form>
    </section>
</div>
<?php require BASE_PATH . '/views/layouts/footer.php'; ?>
