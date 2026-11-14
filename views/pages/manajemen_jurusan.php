<?php
/**
 * MANAJEMEN JURUSAN / PROGRAM STUDI - Adminator 2026 Redesign
 * Master data jurusan terpusat yang terhubung ke Data DPT (Kelas) dan Kandidat.
 */
require_role(['superadmin', 'kprm', 'banwasra']);
require_once BASE_PATH . '/models/jurusan_model.php';

$pdo = get_db_connection();
$canWrite = can_manage_pemira();
$pageTitle = 'Manajemen Jurusan';

$jurusanList = get_all_jurusans_with_stats($pdo);

require BASE_PATH . '/views/layouts/header.php';
?>

<section class="hero">
    <div class="hero-actions">
        <?php if ($canWrite): ?>
            <button type="button" class="btn btn--primary btn--sm" onclick="openCreateJurusanModal()">+ Tambah Jurusan</button>
        <?php else: ?>
            <span class="badge purple dot">Mode Viewer (Read-Only · Banwasra)</span>
        <?php endif; ?>
    </div>
</section>

<!-- Tabel Daftar Master Jurusan -->
<div class="grid">
    <section class="col-12 card" data-datatable-card>
        <div class="card-head">
            <div class="card-title-wrap">
                <h3 class="card-title">Daftar Master Jurusan / Program Studi</h3>
            </div>
            <span class="badge primary"><?= count($jurusanList) ?> Jurusan</span>
        </div>

        <div class="data-toolbar">
            <div class="data-toolbar-left">
                <div class="input-icon" style="flex:1;max-width:300px;">
                    <span class="ico"><svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg></span>
                    <input type="search" class="input" data-dt-search placeholder="Cari kode atau nama jurusan...">
                </div>
            </div>
            <div class="data-toolbar-right">
                <select class="select" data-dt-filter="data-jenjang" style="width:auto;padding:6px 28px 6px 10px;font-size:12px;">
                    <option value="ALL">Semua Jenjang</option>
                    <option value="S1">S1</option>
                    <option value="D4">D4</option>
                    <option value="D3">D3</option>
                    <option value="S2">S2</option>
                </select>
                <select class="select" data-dt-perpage style="width:auto;padding:6px 26px 6px 10px;font-size:12px;">
                    <option value="10">10 / hal</option>
                    <option value="20" selected>20 / hal</option>
                    <option value="50">50 / hal</option>
                </select>
            </div>
        </div>

        <div class="data-table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th data-sort>Kode <span class="sort">▾</span></th>
                        <th data-sort>Nama Jurusan / Program Studi <span class="sort">▾</span></th>
                        <th data-sort>Jenjang <span class="sort">▾</span></th>
                        <th data-sort>Kelas DPT <span class="sort">▾</span></th>
                        <th data-sort>Mahasiswa <span class="sort">▾</span></th>
                        <th data-sort>Kandidat <span class="sort">▾</span></th>
                        <?php if ($canWrite): ?>
                            <th class="text-right">Aksi</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($jurusanList)): ?>
                        <tr><td colspan="<?= $canWrite ? 7 : 6 ?>" class="table-empty">Belum ada data jurusan.</td></tr>
                    <?php else: ?>
                        <?php foreach ($jurusanList as $j): ?>
                            <?php $jJson = json_encode($j, JSON_HEX_APOS | JSON_HEX_QUOT); ?>
                            <tr class="data-row" data-jenjang="<?= e($j['jenjang']) ?>">
                                <td><span class="kelas-card-code"><?= e($j['kode_jurusan']) ?></span></td>
                                <td><span class="fw-600"><?= e($j['nama_jurusan']) ?></span></td>
                                <td><span class="badge neutral"><?= e($j['jenjang']) ?></span></td>
                                <td><span class="data-cell-mono fw-600"><?= (int)$j['total_kelas'] ?></span></td>
                                <td><span class="data-cell-mono"><?= number_format((int)$j['total_mahasiswa']) ?></span></td>
                                <td><span class="data-cell-mono"><?= (int)$j['total_kandidat'] ?></span></td>
                                <?php if ($canWrite): ?>
                                <td>
                                    <div class="data-cell-actions">
                                        <button type="button" class="btn btn--ghost btn--xs" onclick='openEditJurusanModal(<?= $jJson ?>)'>Edit</button>
                                        <form action="<?= e(action_url('jurusan')) ?>" method="POST" data-confirm="Hapus jurusan <?= e($j['nama_jurusan']) ?>? Perubahan tidak dapat dibatalkan." style="display:inline;">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="sub_action" value="delete_jurusan">
                                            <input type="hidden" name="id" value="<?= (int)$j['id'] ?>">
                                            <button type="submit" class="btn btn--soft-danger btn--xs" title="Hapus Jurusan">×</button>
                                        </form>
                                    </div>
                                </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="data-foot">
            <div class="data-foot-info">
                <span data-dt-info>Showing 0 of 0</span>
            </div>
            <div class="pager" data-dt-pager></div>
        </div>
    </section>
</div>

<!-- Modal Tambah / Edit Jurusan -->
<?php if ($canWrite): ?>
<div class="modal-backdrop" id="jurusanModal">
    <div class="modal-dialog modal-sm">
        <div class="modal-head">
            <h3 class="modal-title" id="jurusanModalTitle">Tambah Jurusan</h3>
            <button type="button" class="modal-close" data-modal-close="jurusanModal">×</button>
        </div>
        <form action="<?= e(action_url('jurusan')) ?>" method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="sub_action" id="jur_sub_action" value="create_jurusan">
            <input type="hidden" name="id" id="jur_id_input" value="0">
            <div class="modal-body">
                <div class="form-grid mb-2" style="grid-template-columns: 1fr 1fr;">
                    <div class="field">
                        <label class="field-label">Kode Jurusan</label>
                        <input type="text" name="kode_jurusan" id="jur_kode_input" class="input mono" placeholder="TI" maxlength="20" style="text-transform:uppercase;" required>
                    </div>
                    <div class="field">
                        <label class="field-label">Jenjang</label>
                        <select name="jenjang" id="jur_jenjang_input" class="select" required>
                            <option value="S1">S1</option>
                            <option value="D4">D4</option>
                            <option value="D3">D3</option>
                            <option value="S2">S2</option>
                        </select>
                    </div>
                </div>
                <div class="field mb-0">
                    <label class="field-label">Nama Jurusan / Program Studi</label>
                    <input type="text" name="nama_jurusan" id="jur_nama_input" class="input" placeholder="Teknik Informatika" required>
                    <div class="hint mt-1">Mengubah nama jurusan akan memperbarui seluruh kelas & kandidat yang terhubung.</div>
                </div>
            </div>
            <div class="modal-foot">
                <button type="button" class="btn btn--ghost btn--sm" data-modal-close="jurusanModal">Batal</button>
                <button type="submit" class="btn btn--primary btn--sm">Simpan</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<script>
function openCreateJurusanModal() {
    document.getElementById('jurusanModalTitle').textContent = 'Tambah Jurusan';
    document.getElementById('jur_sub_action').value = 'create_jurusan';
    document.getElementById('jur_id_input').value = 0;
    document.getElementById('jur_kode_input').value = '';
    document.getElementById('jur_jenjang_input').value = 'S1';
    document.getElementById('jur_nama_input').value = '';
    PemiraApp.openModal('jurusanModal');
}

function openEditJurusanModal(j) {
    document.getElementById('jurusanModalTitle').textContent = 'Edit Jurusan · ' + j.kode_jurusan;
    document.getElementById('jur_sub_action').value = 'update_jurusan';
    document.getElementById('jur_id_input').value = j.id;
    document.getElementById('jur_kode_input').value = j.kode_jurusan || '';
    document.getElementById('jur_jenjang_input').value = j.jenjang || 'S1';
    document.getElementById('jur_nama_input').value = j.nama_jurusan || '';
    PemiraApp.openModal('jurusanModal');
}
</script>

<?php require BASE_PATH . '/views/layouts/footer.php'; ?>

