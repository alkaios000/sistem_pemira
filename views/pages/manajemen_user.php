<?php
/**
 * Manajemen Akun Panel & RBAC (Eksklusif Superadmin)
 * Mengatur akun Superadmin, Operator KPRM, dan Pengawas Banwasra.
 */

require_role(['superadmin']);

require_once BASE_PATH . '/models/user_model.php';

$pdo = get_db_connection();
$users = get_all_users($pdo);
$currentUid = (int) ($_SESSION['user_id'] ?? 0);

$totalUsers = count($users);
$countSuperadmin = 0;
$countKprm = 0;
$countBanwasra = 0;
foreach ($users as $u) {
    if ((int) ($u['is_active'] ?? 1) !== 1) {
        continue;
    }
    if ($u['role'] === 'superadmin') $countSuperadmin++;
    elseif ($u['role'] === 'kprm') $countKprm++;
    elseif ($u['role'] === 'banwasra') $countBanwasra++;
}

$pageTitle = 'Manajemen Akun & Hak Akses';
$active_menu = 'manajemen_user';

require BASE_PATH . '/views/layouts/header.php';
?>

<div class="kpi-grid">
    <div class="kpi-card">
        <div class="kpi-top">
            <span class="kpi-label">Total Akun Panel</span>
            <span class="kpi-pill info">Terdaftar</span>
        </div>
        <div class="kpi-value"><?= $totalUsers ?></div>
        <div class="kpi-compare"><span>Seluruh peran sistem</span></div>
    </div>
    <div class="kpi-card">
        <div class="kpi-top">
            <span class="kpi-label">Superadmin Aktif</span>
            <span class="kpi-pill up">Akses Penuh</span>
        </div>
        <div class="kpi-value"><?= $countSuperadmin ?></div>
        <div class="kpi-compare"><span>Seluruh fitur + semua log</span></div>
    </div>
    <div class="kpi-card">
        <div class="kpi-top">
            <span class="kpi-label">Operator KPRM Aktif</span>
            <span class="kpi-pill info">Operasional</span>
        </div>
        <div class="kpi-value"><?= $countKprm ?></div>
        <div class="kpi-compare"><span>Manajemen pemira (tanpa log)</span></div>
    </div>
    <div class="kpi-card">
        <div class="kpi-top">
            <span class="kpi-label">Banwasra Aktif</span>
            <span class="kpi-pill warn">Viewer + Log</span>
        </div>
        <div class="kpi-value"><?= $countBanwasra ?></div>
        <div class="kpi-compare"><span>Hanya lihat + log non-superadmin</span></div>
    </div>
</div>

<div class="grid">
    <section class="col-12 card" data-datatable-card>
        <div class="data-toolbar">
            <div class="data-toolbar-left">
                <div class="input-icon">
                    <span class="ico"><svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg></span>
                    <input type="search" class="input" placeholder="Cari username, nama lengkap, atau peran..." data-dt-search>
                </div>
                <select class="select" style="width:auto;" data-dt-filter="data-role">
                    <option value="ALL">Semua Peran</option>
                    <option value="superadmin">Superadmin</option>
                    <option value="kprm">KPRM</option>
                    <option value="banwasra">Banwasra</option>
                </select>
                <select class="select" data-dt-perpage style="width:auto;">
                    <option value="15" selected>15 / hal</option>
                    <option value="30">30 / hal</option>
                </select>
            </div>
            <div class="data-toolbar-right">
                <button type="button" class="btn btn--primary btn--sm" id="btnOpenCreateUser">+ Tambah Akun Panel</button>
            </div>
        </div>

        <div class="data-table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th data-sort>Username <span class="sort">▾</span></th>
                        <th data-sort>Nama Lengkap <span class="sort">▾</span></th>
                        <th data-sort>Hak Akses (Role) <span class="sort">▾</span></th>
                        <th data-sort>Status Akun <span class="sort">▾</span></th>
                        <th data-sort>Login Terakhir <span class="sort">▾</span></th>
                        <th style="width: 260px;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $u): ?>
                        <?php
                        $uid = (int) $u['id'];
                        $isActive = (int) ($u['is_active'] ?? 1) === 1;
                        $isSelf = ($uid === $currentUid);
                        $roleBadgeClass = 'primary';
                        $roleLabel = strtoupper((string) $u['role']);
                        if ($u['role'] === 'kprm') {
                            $roleBadgeClass = 'info';
                        } elseif ($u['role'] === 'banwasra') {
                            $roleBadgeClass = 'purple';
                        }
                        $uPayload = htmlspecialchars(json_encode([
                            'id'           => $uid,
                            'username'     => $u['username'],
                            'nama_lengkap' => $u['nama_lengkap'],
                            'role'         => $u['role'],
                            'is_active'    => $isActive ? 1 : 0,
                        ], JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
                        ?>
                        <tr class="data-row" data-role="<?= e($u['role']) ?>">
                            <td class="mono fw-700">
                                <?= e($u['username']) ?>
                                <?php if ($isSelf): ?>
                                    <span class="badge soft-primary" style="margin-left:4px;">Anda</span>
                                <?php endif; ?>
                            </td>
                            <td><?= e($u['nama_lengkap']) ?></td>
                            <td><span class="badge <?= $roleBadgeClass ?>"><?= e($roleLabel) ?></span></td>
                            <td>
                                <?php if ($isActive): ?>
                                    <span class="badge success dot">Aktif</span>
                                <?php else: ?>
                                    <span class="badge danger dot">Nonaktif</span>
                                <?php endif; ?>
                            </td>
                            <td class="mono text-muted">
                                <?= !empty($u['last_login_at']) ? e(format_tanggal_indo($u['last_login_at'])) : 'Belum pernah' ?>
                            </td>
                            <td>
                                <div class="d-flex gap-1" style="flex-wrap:wrap;">
                                    <button type="button" class="btn btn--soft-primary btn--xs btn-edit-user" data-user-json="<?= $uPayload ?>">Edit</button>
                                    <button type="button" class="btn btn--ghost btn--xs btn-reset-user" data-user-id="<?= $uid ?>" data-username="<?= e($u['username']) ?>">Reset Sandi</button>
                                    <?php if (!$isSelf): ?>
                                        <form action="<?= e(action_url('user')) ?>" method="POST" style="display:inline;">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="sub_action" value="toggle_active">
                                            <input type="hidden" name="id" value="<?= $uid ?>">
                                            <button type="submit" class="btn <?= $isActive ? 'btn--soft-warning' : 'btn--soft-success' ?> btn--xs">
                                                <?= $isActive ? 'Nonaktifkan' : 'Aktifkan' ?>
                                            </button>
                                        </form>
                                        <form action="<?= e(action_url('user')) ?>" method="POST" style="display:inline;" onsubmit="return confirm('Hapus permanen akun <?= e($u['username']) ?>?');">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="sub_action" value="delete">
                                            <input type="hidden" name="id" value="<?= $uid ?>">
                                            <button type="submit" class="btn btn--soft-danger btn--xs">Hapus</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="data-foot">
            <div class="data-foot-info"><span data-dt-info>Menampilkan 0 data</span></div>
            <div class="pager" data-dt-pager></div>
        </div>
    </section>
</div>

<!-- Modal Tambah / Edit Akun Panel -->
<div class="modal-backdrop" id="userModal">
    <div class="modal-dialog modal-sm">
        <div class="modal-head">
            <h3 class="modal-title" id="userModalTitle">Tambah Akun Panel</h3>
            <button type="button" class="modal-close" data-modal-close="userModal">×</button>
        </div>
        <form action="<?= e(action_url('user')) ?>" method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="sub_action" id="user_sub_action" value="create">
            <input type="hidden" name="id" id="user_id_input" value="0">
            <div class="modal-body">
                <div class="field">
                    <label class="field-label">Username Login</label>
                    <input type="text" name="username" id="user_username_input" class="input mono" placeholder="kprm_utama" minlength="3" maxlength="50" required>
                </div>
                <div class="field">
                    <label class="field-label">Nama Lengkap</label>
                    <input type="text" name="nama_lengkap" id="user_nama_input" class="input" placeholder="Ketua KPRM PEMIRA" maxlength="120" required>
                </div>
                <div class="form-grid mb-2" style="grid-template-columns: 1fr 1fr;">
                    <div class="field">
                        <label class="field-label">Hak Akses (Role)</label>
                        <select name="role" id="user_role_input" class="select" required>
                            <option value="kprm">KPRM (Operasional)</option>
                            <option value="banwasra">Banwasra (Viewer + Log)</option>
                            <option value="superadmin">Superadmin (Penuh)</option>
                        </select>
                    </div>
                    <div class="field">
                        <label class="field-label">Status Akun</label>
                        <select name="is_active" id="user_active_input" class="select">
                            <option value="1">Aktif</option>
                            <option value="0">Nonaktif</option>
                        </select>
                    </div>
                </div>
                <div class="field mb-0" id="userPasswordGroup">
                    <label class="field-label">Password Awal (Min. 8 karakter)</label>
                    <input type="password" name="password" id="user_password_input" class="input" minlength="8" maxlength="128" placeholder="Minimal 8 karakter">
                </div>
            </div>
            <div class="modal-foot">
                <button type="button" class="btn btn--ghost btn--sm" data-modal-close="userModal">Batal</button>
                <button type="submit" class="btn btn--primary btn--sm">Simpan Akun</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Reset Password Akun -->
<div class="modal-backdrop" id="resetPasswordModal">
    <div class="modal-dialog modal-sm">
        <div class="modal-head">
            <h3 class="modal-title" id="resetModalTitle">Reset Password Akun</h3>
            <button type="button" class="modal-close" data-modal-close="resetPasswordModal">×</button>
        </div>
        <form action="<?= e(action_url('user')) ?>" method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="sub_action" value="reset_password">
            <input type="hidden" name="id" id="reset_user_id" value="0">
            <div class="modal-body">
                <p class="text-muted mb-2">Masukkan kata sandi baru untuk akun <strong class="mono" id="resetUsernameLabel">-</strong>.</p>
                <div class="field mb-0">
                    <label class="field-label">Password Baru (Min. 8 karakter)</label>
                    <input type="password" name="new_password" id="reset_new_password" class="input" minlength="8" maxlength="128" required placeholder="Minimal 8 karakter">
                </div>
            </div>
            <div class="modal-foot">
                <button type="button" class="btn btn--ghost btn--sm" data-modal-close="resetPasswordModal">Batal</button>
                <button type="submit" class="btn btn--primary btn--sm">Simpan Password Baru</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const btnCreate = document.getElementById('btnOpenCreateUser');
    if (btnCreate) {
        btnCreate.addEventListener('click', () => {
            document.getElementById('userModalTitle').textContent = 'Tambah Akun Panel';
            document.getElementById('user_sub_action').value = 'create';
            document.getElementById('user_id_input').value = '0';
            document.getElementById('user_username_input').value = '';
            document.getElementById('user_nama_input').value = '';
            document.getElementById('user_role_input').value = 'kprm';
            document.getElementById('user_active_input').value = '1';
            document.getElementById('userPasswordGroup').style.display = 'block';
            const pwInput = document.getElementById('user_password_input');
            pwInput.value = '';
            pwInput.required = true;
            PemiraApp.openModal('userModal');
        });
    }

    document.addEventListener('click', (ev) => {
        const editBtn = ev.target.closest('.btn-edit-user');
        if (editBtn) {
            try {
                const u = JSON.parse(editBtn.getAttribute('data-user-json') || '{}');
                document.getElementById('userModalTitle').textContent = 'Edit Akun · ' + (u.username || '');
                document.getElementById('user_sub_action').value = 'update';
                document.getElementById('user_id_input').value = u.id || 0;
                document.getElementById('user_username_input').value = u.username || '';
                document.getElementById('user_nama_input').value = u.nama_lengkap || '';
                document.getElementById('user_role_input').value = u.role || 'kprm';
                document.getElementById('user_active_input').value = String(u.is_active ?? 1);
                document.getElementById('userPasswordGroup').style.display = 'none';
                const pwInput = document.getElementById('user_password_input');
                pwInput.value = '';
                pwInput.required = false;
                PemiraApp.openModal('userModal');
            } catch (_) {}
            return;
        }

        const resetBtn = ev.target.closest('.btn-reset-user');
        if (resetBtn) {
            document.getElementById('reset_user_id').value = resetBtn.getAttribute('data-user-id') || '0';
            document.getElementById('resetUsernameLabel').textContent = resetBtn.getAttribute('data-username') || '-';
            document.getElementById('reset_new_password').value = '';
            PemiraApp.openModal('resetPasswordModal');
        }
    });
});
</script>

<?php require BASE_PATH . '/views/layouts/footer.php'; ?>
