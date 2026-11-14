<?php
/**
 * Halaman Profil & Ganti Password Akun Sendiri (Superadmin, KPRM, dan Banwasra)
 */

require_auth();

require_once BASE_PATH . '/models/user_model.php';

$pdo = get_db_connection();
$currentUser = find_user_by_id($pdo, (int) ($_SESSION['user_id'] ?? 0));
if (!$currentUser) {
    redirect(route_url('login'));
}

$roleDesc = match ($currentUser['role']) {
    'superadmin' => 'Superadmin memiliki kendali penuh atas seluruh fitur PEMIRA, manajemen akun panel, serta seluruh catatan Log Aktivitas & Keamanan.',
    'kprm'       => 'KPRM memiliki akses pengelolaan penuh operasional PEMIRA (Bilik & QR, Kandidat, DPT Mahasiswa, Master Jurusan), namun dibatasi tidak dapat mengakses Log Aktivitas maupun Manajemen Akun.',
    'banwasra'   => 'Banwasra memiliki akses pengawasan menyeluruh (mode Viewer / baca-saja di semua modul) serta dapat meninjau Log Aktivitas selain aktivitas yang dilakukan Superadmin.',
    default      => 'Akun panel terautentikasi.',
};

$pageTitle = 'Profil & Keamanan Akun';
$active_menu = 'profil_akun';

require BASE_PATH . '/views/layouts/header.php';
?>

<div class="grid">
    <section class="col-5 card">
        <div class="card-head">
            <div class="card-title-wrap">
                <h2 class="card-title">Informasi Akun Aktif</h2>
                <p class="card-subtitle">Detail sesi dan cakupan hak akses Anda</p>
            </div>
            <span class="badge primary"><?= e(strtoupper((string) $currentUser['role'])) ?></span>
        </div>

        <div class="d-flex flex-col gap-2">
            <div class="p-3" style="background:var(--bg-hover);border:1px solid var(--border-soft);border-radius:8px;">
                <span class="eyebrow">Username</span>
                <div class="mono fw-700 mt-1"><?= e($currentUser['username']) ?></div>
            </div>
            <div class="p-3" style="background:var(--bg-hover);border:1px solid var(--border-soft);border-radius:8px;">
                <span class="eyebrow">Nama Lengkap</span>
                <div class="fw-700 mt-1"><?= e($currentUser['nama_lengkap']) ?></div>
            </div>
            <div class="p-3" style="background:var(--bg-hover);border:1px solid var(--border-soft);border-radius:8px;">
                <span class="eyebrow">Login Terakhir</span>
                <div class="mono mt-1"><?= !empty($currentUser['last_login_at']) ? e(format_tanggal_indo($currentUser['last_login_at'])) : 'Sesi aktif pertama kali' ?></div>
            </div>
            <div class="p-3" style="border:1px solid var(--border);border-radius:8px;">
                <span class="eyebrow">Cakupan Hak Akses</span>
                <p class="text-muted mt-1 mb-0" style="font-size:12.5px;line-height:1.5;"><?= e($roleDesc) ?></p>
            </div>
        </div>
    </section>

    <section class="col-7 card">
        <div class="card-head">
            <div class="card-title-wrap">
                <h2 class="card-title">Ganti Kata Sandi</h2>
                <p class="card-subtitle">Gunakan kombinasi huruf besar, huruf kecil, angka, dan simbol minimal 8 karakter</p>
            </div>
        </div>

        <form action="<?= e(action_url('user')) ?>" method="POST" autocomplete="off">
            <?= csrf_field() ?>
            <input type="hidden" name="sub_action" value="change_own_password">

            <div class="field">
                <label class="field-label">Kata Sandi Saat Ini</label>
                <input type="password" name="current_password" class="input" required placeholder="Masukkan kata sandi Anda sekarang">
            </div>

            <div class="form-grid mb-3" style="grid-template-columns: 1fr 1fr;">
                <div class="field">
                    <label class="field-label">Kata Sandi Baru</label>
                    <input type="password" name="new_password" class="input" minlength="8" maxlength="128" required placeholder="Minimal 8 karakter">
                </div>
                <div class="field">
                    <label class="field-label">Konfirmasi Kata Sandi Baru</label>
                    <input type="password" name="confirm_password" class="input" minlength="8" maxlength="128" required placeholder="Ulangi kata sandi baru">
                </div>
            </div>

            <div class="d-flex justify-end pt-2" style="border-top:1px solid var(--border-soft);">
                <button type="submit" class="btn btn--primary btn--sm">Perbarui Kata Sandi</button>
            </div>
        </form>
    </section>
</div>

<?php require BASE_PATH . '/views/layouts/footer.php'; ?>
