<?php
/**
 * MANAJEMEN MAHASISWA (DPT) & HIERARKI KELAS - Adminator 2026 Redesign (Fitur 4)
 * Tanpa avatar placeholder inisial, mendukung CRUD Kelas, Check-In Massal per Kelas, dan Import Preview.
 */
require_auth();
require_once BASE_PATH . '/models/mahasiswa_model.php';

$pdo = get_db_connection();
$pageTitle = 'Data DPT Mahasiswa';

$kelasList = get_all_kelas_with_stats($pdo);
$selectedKelasId = isset($_GET['kelas_id']) ? (int)$_GET['kelas_id'] : 0;
$selectedKelasInfo = $selectedKelasId > 0 ? find_kelas_by_id($pdo, $selectedKelasId) : null;
$mahasiswaList = get_mahasiswas_by_filter($pdo, $selectedKelasId > 0 ? $selectedKelasId : null, '', null);
$dptStats = get_dpt_global_stats($pdo);

$extraScripts = [
    asset_url('js/modules/mahasiswa_import.js')
];

require BASE_PATH . '/views/layouts/header.php';
?>

<section class="hero">
    <div class="hero-actions">
        <?php if ($selectedKelasId > 0): ?>
            <a href="<?= e(route_url('manajemen_mahasiswa')) ?>" class="btn btn--ghost btn--sm" data-ajax-nav>Semua Kelas</a>
        <?php endif; ?>
        <button type="button" class="btn btn--ghost btn--sm" onclick="openCreateKelasModal()">+ Tambah Kelas</button>
        <button type="button" class="btn btn--primary btn--sm" onclick="openAddMahasiswaModal(<?= (int)$selectedKelasId ?>)">+ Tambah / Import DPT</button>
    </div>
</section>

<!-- KPI Summary -->
<div class="kpi-grid">
    <div class="kpi-card">
        <div class="kpi-top">
            <span class="kpi-label">Total DPT</span>
            <span class="kpi-pill info"><?= number_format($dptStats['total_kelas']) ?> Kelas</span>
        </div>
        <div class="kpi-value"><?= number_format($dptStats['total_dpt']) ?></div>
        <div class="kpi-compare"><span>Mahasiswa terdaftar</span></div>
    </div>
    <div class="kpi-card">
        <div class="kpi-top">
            <span class="kpi-label">Terverifikasi Hadir</span>
            <span class="kpi-pill primary">Check-In</span>
        </div>
        <div class="kpi-value"><?= number_format($dptStats['total_hadir']) ?></div>
        <div class="kpi-compare"><span>Siap memilih di bilik</span></div>
    </div>
    <div class="kpi-card">
        <div class="kpi-top">
            <span class="kpi-label">Sudah Memilih</span>
            <span class="kpi-pill up"><?= e($dptStats['persen_partisipasi']) ?>%</span>
        </div>
        <div class="kpi-value"><?= number_format($dptStats['sudah_memilih']) ?></div>
        <div class="kpi-compare"><span>Suara sah masuk</span></div>
    </div>
    <div class="kpi-card">
        <div class="kpi-top">
            <span class="kpi-label">Belum Memilih</span>
            <span class="kpi-pill flat"><?= e($dptStats['persen_belum']) ?>%</span>
        </div>
        <div class="kpi-value"><?= number_format($dptStats['belum_memilih']) ?></div>
        <div class="kpi-compare"><span>Belum menggunakan hak</span></div>
    </div>
</div>

<div class="grid">
    <!-- Hierarki Kelas (Sesuai app.md Poin 5.4: Kelola Kelas -> Masuk Detail Kelas) -->
    <section class="col-12 card" id="kelasMasterCard">
        <div class="card-head">
            <div class="card-title-wrap">
                <h3 class="card-title">Daftar Kelas (Klik Kelas untuk Filter Mahasiswa)</h3>
            </div>
            <span class="badge primary"><?= count($kelasList) ?> Kelas</span>
        </div>

        <div class="data-table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Kelas</th>
                        <th>Jurusan</th>
                        <th>Semester</th>
                        <th>Mahasiswa</th>
                        <th>Hadir (Check-In)</th>
                        <th>Sudah Memilih</th>
                        <th class="text-right">Aksi Kelas</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($kelasList)): ?>
                        <tr><td colspan="7" class="table-empty">Belum ada kelas. Buat kelas terlebih dahulu.</td></tr>
                    <?php else: ?>
                        <?php foreach ($kelasList as $kl): ?>
                            <?php $isSelected = ($selectedKelasId === (int)$kl['id']); ?>
                            <tr class="data-row <?= $isSelected ? 'is-selected' : '' ?>">
                                <td>
                                    <a href="<?= e(route_url('manajemen_mahasiswa', ['kelas_id' => (int)$kl['id']])) ?>" class="badge primary" data-ajax-nav>
                                        <?= e($kl['nama_kelas']) ?>
                                    </a>
                                </td>
                                <td class="fw-600"><?= e($kl['jurusan']) ?></td>
                                <td><span class="data-cell-mono">Smt <?= (int)$kl['semester'] ?></span></td>
                                <td><span class="data-cell-mono fw-600"><?= (int)$kl['total_mahasiswa'] ?></span></td>
                                <td><span class="badge info dot"><?= (int)$kl['jumlah_hadir'] ?> / <?= (int)$kl['total_mahasiswa'] ?></span></td>
                                <td>
                                    <span class="badge success dot"><?= (int)$kl['jumlah_sudah_memilih'] ?> Sudah</span>
                                    <span class="badge neutral"><?= (int)$kl['jumlah_belum_memilih'] ?> Belum</span>
                                </td>
                                <td>
                                    <div class="data-cell-actions">
                                        <a href="<?= e(route_url('manajemen_mahasiswa', ['kelas_id' => (int)$kl['id']])) ?>" class="btn btn--soft-primary btn--xs" data-ajax-nav>Buka Kelas</a>
                                        <form action="<?= e(action_url('mahasiswa')) ?>" method="POST" style="display:inline;">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="sub_action" value="set_all_hadir_kelas">
                                            <input type="hidden" name="kelas_id" value="<?= (int)$kl['id'] ?>">
                                            <input type="hidden" name="status_hadir" value="1">
                                            <button type="submit" class="btn btn--ghost btn--xs" title="Aktifkan Check-In seluruh mahasiswa kelas ini">Check-In Semua</button>
                                        </form>
                                        <button type="button" class="btn btn--ghost btn--xs" onclick='openEditKelasModal(<?= json_encode($kl, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>Edit</button>
                                        <form action="<?= e(action_url('mahasiswa')) ?>" method="POST" data-confirm="Hapus kelas ini beserta seluruh mahasiswa di dalamnya?">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="sub_action" value="delete_kelas">
                                            <input type="hidden" name="id" value="<?= (int)$kl['id'] ?>">
                                            <button type="submit" class="btn btn--soft-danger btn--xs">×</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <!-- Adminator 2026 Data Table Mahasiswa (Tanpa Avatar Placeholder) -->
    <section class="col-12 card" data-datatable-card>
        <div class="card-head">
            <div class="card-title-wrap">
                <h3 class="card-title">
                    <?php if ($selectedKelasInfo): ?>
                        Mahasiswa Kelas <?= e($selectedKelasInfo['nama_kelas']) ?> (<?= e($selectedKelasInfo['jurusan']) ?> · Semester <?= (int)$selectedKelasInfo['semester'] ?>)
                    <?php else: ?>
                        Seluruh Mahasiswa Terdaftar
                    <?php endif; ?>
                </h3>
            </div>
            <?php if ($selectedKelasInfo): ?>
                <button type="button" class="btn btn--primary btn--xs" onclick="openAddMahasiswaModal(<?= (int)$selectedKelasInfo['id'] ?>)">+ Mahasiswa ke <?= e($selectedKelasInfo['nama_kelas']) ?></button>
            <?php endif; ?>
        </div>

        <div class="data-toolbar">
            <div class="data-toolbar-left">
                <div class="input-icon" style="flex:1;max-width:320px;">
                    <span class="ico"><svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg></span>
                    <input type="search" class="input" data-dt-search placeholder="Cari nama lengkap, NIM, atau kelas...">
                </div>
            </div>
            <div class="data-toolbar-right">
                <select class="select" data-dt-filter="data-kelas" style="width:auto;padding:6px 28px 6px 10px;font-size:12px;">
                    <option value="ALL">Semua Kelas</option>
                    <?php foreach ($kelasList as $kl): ?>
                        <option value="<?= e($kl['nama_kelas']) ?>">
                            <?= e($kl['nama_kelas']) ?> (<?= (int)$kl['total_mahasiswa'] ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                <select class="select" data-dt-filter="data-status" style="width:auto;padding:6px 28px 6px 10px;font-size:12px;">
                    <option value="ALL">Semua Status</option>
                    <option value="SUDAH">Sudah Memilih</option>
                    <option value="BELUM">Belum Memilih</option>
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
                        <th data-sort>Mahasiswa <span class="sort">▾</span></th>
                        <th data-sort>NIM <span class="sort">▾</span></th>
                        <th data-sort>Kelas <span class="sort">▾</span></th>
                        <th>Check-In</th>
                        <th data-sort>Status Suara <span class="sort">▾</span></th>
                        <th data-sort>Waktu Memilih <span class="sort">▾</span></th>
                        <th class="text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($mahasiswaList)): ?>
                        <tr><td colspan="7" class="table-empty">Belum ada mahasiswa pada kelas ini.</td></tr>
                    <?php else: ?>
                        <?php foreach ($mahasiswaList as $m): ?>
                            <?php
                            $sudahVote = ((int)$m['sudah_memilih'] === 1);
                            $statusHadir = ((int)$m['status_hadir'] === 1);
                            ?>
                            <tr class="data-row" data-kelas="<?= e($m['nama_kelas']) ?>" data-status="<?= $sudahVote ? 'SUDAH' : 'BELUM' ?>">
                                <td>
                                    <div class="data-cell-user-meta">
                                        <div class="data-cell-user-name"><?= e($m['nama_lengkap']) ?></div>
                                        <div class="data-cell-user-email"><?= e($m['jurusan']) ?> · Smt <?= (int)$m['semester'] ?></div>
                                    </div>
                                </td>
                                <td><span class="data-cell-mono fw-600"><?= e($m['nim']) ?></span></td>
                                <td><span class="badge primary"><?= e($m['nama_kelas']) ?></span></td>
                                <td>
                                    <form action="<?= e(action_url('mahasiswa')) ?>" method="POST" style="display:inline;">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="sub_action" value="toggle_hadir">
                                        <input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
                                        <input type="hidden" name="kelas_id" value="<?= (int)$m['kelas_id'] ?>">
                                        <input type="hidden" name="status_hadir" value="<?= $statusHadir ? 0 : 1 ?>">
                                        <button type="submit" class="badge <?= $statusHadir ? 'info' : 'neutral' ?> dot" style="cursor:pointer;border:0;">
                                            <?= $statusHadir ? 'Aktif' : 'Nonaktif' ?>
                                        </button>
                                    </form>
                                </td>
                                <td>
                                    <?php if ($sudahVote): ?>
                                        <span class="badge success dot">Sudah Memilih</span>
                                    <?php else: ?>
                                        <span class="badge neutral">Belum Memilih</span>
                                    <?php endif; ?>
                                </td>
                                <td><span class="data-cell-mono"><?= !empty($m['waktu_memilih']) ? e(format_tanggal_indo($m['waktu_memilih'])) : '-' ?></span></td>
                                <td>
                                    <div class="data-cell-actions">
                                        <button type="button" class="btn btn--ghost btn--xs" onclick='openEditMahasiswaModal(<?= json_encode($m, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>Edit</button>
                                        <form action="<?= e(action_url('mahasiswa')) ?>" method="POST" data-confirm="Hapus mahasiswa ini dari DPT?">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="sub_action" value="delete_mahasiswa">
                                            <input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
                                            <input type="hidden" name="kelas_id" value="<?= (int)$m['kelas_id'] ?>">
                                            <button type="submit" class="btn btn--soft-danger btn--xs">×</button>
                                        </form>
                                    </div>
                                </td>
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

<!-- Modal Tambah / Import Mahasiswa -->
<div class="modal-backdrop" id="mahasiswaModal">
    <div class="modal-dialog modal-lg">
        <div class="modal-head">
            <h3 class="modal-title" id="mhsModalTitle">Tambah / Import DPT</h3>
            <button type="button" class="modal-close" data-modal-close="mahasiswaModal">×</button>
        </div>
        <div class="modal-body">
            <div class="tabs pills mb-3" id="mhsModalTabs">
                <button type="button" class="tab is-active" onclick="switchMhsTab('manual')" id="tabBtnManual">Input Manual</button>
                <button type="button" class="tab" onclick="switchMhsTab('import')" id="tabBtnImport">Import CSV / Paste Excel</button>
            </div>

            <!-- Tab 1: Manual -->
            <div id="tabPaneManual">
                <form action="<?= e(action_url('mahasiswa')) ?>" method="POST">
                    <?= csrf_field() ?>
                    <input type="hidden" name="sub_action" id="mhs_sub_action" value="create_mahasiswa">
                    <input type="hidden" name="id" id="mhs_id" value="0">
                    <div class="form-grid mb-3">
                        <div class="field">
                            <label class="field-label">NIM</label>
                            <input type="text" name="nim" id="mhs_nim" class="input mono" placeholder="240101099" required>
                        </div>
                        <div class="field">
                            <label class="field-label">Kelas</label>
                            <select name="kelas_id" id="mhs_kelas" class="select" required>
                                <option value="">-- Pilih Kelas --</option>
                                <?php foreach ($kelasList as $kl): ?>
                                    <option value="<?= (int)$kl['id'] ?>"><?= e($kl['nama_kelas']) ?> · <?= e($kl['jurusan']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="form-grid mb-3">
                        <div class="field">
                            <label class="field-label">Nama Lengkap</label>
                            <input type="text" name="nama_lengkap" id="mhs_nama" class="input" placeholder="Nama lengkap sesuai KTM" required>
                        </div>
                        <div class="field">
                            <label class="field-label">Status Check-In</label>
                            <select name="status_hadir" id="mhs_hadir" class="select">
                                <option value="1">Aktif / Hadir</option>
                                <option value="0">Belum Check-In</option>
                            </select>
                        </div>
                    </div>
                    <div class="d-flex justify-between align-center mt-3 pt-2" style="border-top:1px solid var(--border-soft);">
                        <button type="button" class="btn btn--ghost btn--sm" data-modal-close="mahasiswaModal">Batal</button>
                        <button type="submit" class="btn btn--primary btn--sm">Simpan Mahasiswa</button>
                    </div>
                </form>
            </div>

            <!-- Tab 2: Import CSV / Copy-Paste Excel -->
            <div id="tabPaneImport" style="display:none;">
                <form action="<?= e(action_url('mahasiswa')) ?>" method="POST" id="confirmImportForm">
                    <?= csrf_field() ?>
                    <input type="hidden" name="sub_action" value="confirm_import_batch">

                    <div class="form-grid mb-3">
                        <div class="field">
                            <label class="field-label">Kelas Tujuan Import</label>
                            <select name="kelas_id" id="import_kelas_id" class="select" required>
                                <?php foreach ($kelasList as $kl): ?>
                                    <option value="<?= (int)$kl['id'] ?>"><?= e($kl['nama_kelas']) ?> (<?= e($kl['jurusan']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="field">
                            <label class="field-label">Unggah CSV <span class="hint">Atau paste di bawah</span></label>
                            <input type="file" id="importCsvFile" class="input" accept=".csv,.txt">
                        </div>
                    </div>

                    <div class="field">
                        <label class="field-label">
                            <span>Paste Baris Excel / CSV (NIM, Nama Lengkap)</span>
                            <button type="button" class="btn btn--soft-primary btn--xs" onclick="fillSampleImport()">Contoh Data</button>
                        </label>
                        <textarea id="rawImportText" class="textarea mono" style="min-height:80px;" placeholder="240101088, Andi Pratama Wijaya&#10;240101089, Siti Rahmawati Putri"></textarea>
                    </div>

                    <div class="d-flex justify-between align-center mb-2">
                        <button type="button" class="btn btn--soft-primary btn--sm" onclick="previewImportBatch()">Validasi & Preview Data</button>
                        <span class="badge primary" id="importValidBadge" style="display:none;">0 Valid</span>
                    </div>

                    <div id="importPreviewBox" style="display:none;">
                        <div class="data-table-wrap" style="max-height:190px;overflow-y:auto;margin:0;border:1px solid var(--border);border-radius:8px;">
                            <table class="data-table" style="width:100%;margin:0;">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>NIM</th>
                                        <th>Nama Lengkap</th>
                                        <th>Status Validasi</th>
                                    </tr>
                                </thead>
                                <tbody id="importPreviewTbody"></tbody>
                            </table>
                        </div>
                        <div id="importHiddenInputs"></div>
                        <div class="d-flex justify-between align-center mt-3">
                            <button type="button" class="btn btn--ghost btn--sm" data-modal-close="mahasiswaModal">Batal</button>
                            <button type="submit" class="btn btn--primary btn--sm" id="btnConfirmImportSubmit">Simpan Baris Valid</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal Tambah / Edit Kelas -->
<div class="modal-backdrop" id="kelasModal">
    <div class="modal-dialog modal-sm">
        <div class="modal-head">
            <h3 class="modal-title" id="kelasModalTitle">Tambah Master Kelas</h3>
            <button type="button" class="modal-close" data-modal-close="kelasModal">×</button>
        </div>
        <form action="<?= e(action_url('mahasiswa')) ?>" method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="sub_action" id="kelas_sub_action" value="create_kelas">
            <input type="hidden" name="id" id="kelas_id_input" value="0">
            <div class="modal-body">
                <div class="field">
                    <label class="field-label">Kode / Nama Kelas</label>
                    <input type="text" name="nama_kelas" id="kelas_nama_input" class="input mono" placeholder="TI-3C" required>
                </div>
                <div class="field">
                    <label class="field-label">Program Studi / Jurusan</label>
                    <input type="text" name="jurusan" id="kelas_jurusan_input" class="input" placeholder="Teknik Informatika" required>
                </div>
                <div class="field mb-0">
                    <label class="field-label">Semester</label>
                    <input type="number" name="semester" id="kelas_semester_input" class="input" min="1" max="14" value="3" required>
                </div>
            </div>
            <div class="modal-foot">
                <button type="button" class="btn btn--ghost btn--sm" data-modal-close="kelasModal">Batal</button>
                <button type="submit" class="btn btn--primary btn--sm">Simpan</button>
            </div>
        </form>
    </div>
</div>

<script>
function switchMhsTab(tab) {
    const isManual = (tab === 'manual');
    document.getElementById('tabPaneManual').style.display = isManual ? 'block' : 'none';
    document.getElementById('tabPaneImport').style.display = isManual ? 'none' : 'block';
    document.getElementById('tabBtnManual').classList.toggle('is-active', isManual);
    document.getElementById('tabBtnImport').classList.toggle('is-active', !isManual);
}

function openCreateKelasModal() {
    document.getElementById('kelasModalTitle').textContent = 'Tambah Master Kelas';
    document.getElementById('kelas_sub_action').value = 'create_kelas';
    document.getElementById('kelas_id_input').value = 0;
    document.getElementById('kelas_nama_input').value = '';
    document.getElementById('kelas_jurusan_input').value = '';
    document.getElementById('kelas_semester_input').value = 3;
    PemiraApp.openModal('kelasModal');
}

function openEditKelasModal(kl) {
    document.getElementById('kelasModalTitle').textContent = 'Edit Kelas · ' + kl.nama_kelas;
    document.getElementById('kelas_sub_action').value = 'update_kelas';
    document.getElementById('kelas_id_input').value = kl.id;
    document.getElementById('kelas_nama_input').value = kl.nama_kelas || '';
    document.getElementById('kelas_jurusan_input').value = kl.jurusan || '';
    document.getElementById('kelas_semester_input').value = kl.semester || 1;
    PemiraApp.openModal('kelasModal');
}

function openAddMahasiswaModal(defaultKelasId = 0) {
    document.getElementById('mhsModalTitle').textContent = 'Tambah / Import DPT';
    document.getElementById('mhsModalTabs').style.display = 'inline-flex';
    document.getElementById('mhs_sub_action').value = 'create_mahasiswa';
    document.getElementById('mhs_id').value = 0;
    document.getElementById('mhs_nim').value = '';
    document.getElementById('mhs_nama').value = '';
    document.getElementById('mhs_hadir').value = '1';
    if (defaultKelasId > 0) {
        document.getElementById('mhs_kelas').value = defaultKelasId;
        document.getElementById('import_kelas_id').value = defaultKelasId;
    }
    switchMhsTab('manual');
    PemiraApp.openModal('mahasiswaModal');
}

function openEditMahasiswaModal(m) {
    document.getElementById('mhsModalTitle').textContent = 'Edit Mahasiswa · ' + m.nim;
    document.getElementById('mhsModalTabs').style.display = 'none';
    document.getElementById('mhs_sub_action').value = 'update_mahasiswa';
    document.getElementById('mhs_id').value = m.id;
    document.getElementById('mhs_nim').value = m.nim;
    document.getElementById('mhs_nama').value = m.nama_lengkap;
    document.getElementById('mhs_kelas').value = m.kelas_id;
    document.getElementById('mhs_hadir').value = m.status_hadir;
    switchMhsTab('manual');
    PemiraApp.openModal('mahasiswaModal');
}
</script>

<?php require BASE_PATH . '/views/layouts/footer.php'; ?>
