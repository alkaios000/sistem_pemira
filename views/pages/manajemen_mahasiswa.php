<?php
/**
 * MANAJEMEN MAHASISWA (DPT) & HIERARKI KELAS - Adminator 2026 Redesign (Fitur 4)
 * Daftar Kelas berbasis Card Grid & Modal Besar Detail Mahasiswa per Kelas (Super Real-Time).
 */
require_auth();
require_once BASE_PATH . '/models/mahasiswa_model.php';

$pdo = get_db_connection();
$pageTitle = 'Data DPT Mahasiswa';

$kelasList = get_all_kelas_with_stats($pdo);
$jurusanList = get_all_jurusans($pdo);
$selectedKelasId = isset($_GET['kelas_id']) ? (int)$_GET['kelas_id'] : 0;
$allMahasiswa = get_mahasiswas_by_filter($pdo, null, '', null);
$dptStats = get_dpt_global_stats($pdo);

// Group mahasiswa by kelas_id and attach formatted Indonesian vote timestamp for real-time modal rendering
$mahasiswaByKelas = [];
foreach ($allMahasiswa as $m) {
    $kid = (int)$m['kelas_id'];
    if (!isset($mahasiswaByKelas[$kid])) {
        $mahasiswaByKelas[$kid] = [];
    }
    $m['waktu_memilih_indo'] = !empty($m['waktu_memilih']) ? format_tanggal_indo($m['waktu_memilih']) : '-';
    $mahasiswaByKelas[$kid][] = $m;
}

$kelasMap = [];
foreach ($kelasList as $kl) {
    $kelasMap[(int)$kl['id']] = $kl;
}

$extraScripts = [
    asset_url('js/modules/mahasiswa_import.js')
];

require BASE_PATH . '/views/layouts/header.php';
?>

<section class="hero">
    <div class="hero-actions">
        <button type="button" class="btn btn--ghost btn--sm" onclick="openCreateKelasModal()">+ Tambah Kelas</button>
        <button type="button" class="btn btn--primary btn--sm" onclick="openAddMahasiswaModal(0)">+ Tambah / Import DPT</button>
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

<!-- JSON Payload for Real-Time Detail Kelas Modal -->
<script type="application/json" id="kelasDataPayload"><?= json_encode([
    'kelas' => $kelasMap,
    'mahasiswaByKelas' => $mahasiswaByKelas,
    'actionUrl' => action_url('mahasiswa'),
    'csrfToken' => csrf_token()
], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?></script>

<!-- Card Grid Daftar Kelas -->
<section class="card">
    <div class="card-head" style="flex-wrap:wrap;gap:12px;">
        <div class="card-title-wrap">
            <h3 class="card-title">Daftar Kelas</h3>
        </div>
        <div class="d-flex align-center gap-2" style="flex-wrap:wrap;">
            <div class="input-icon" style="width:230px;">
                <span class="ico"><svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg></span>
                <input type="search" id="kelasSearchInput" class="input" placeholder="Cari kelas atau jurusan..." oninput="applyKelasCardFilter()">
            </div>
            <span class="badge primary" id="kelasCountBadge"><?= count($kelasList) ?> Kelas</span>
        </div>
    </div>

    <div class="kelas-grid" id="kelasCardsGrid">
        <?php if (empty($kelasList)): ?>
            <div class="table-empty" style="grid-column: 1 / -1;">Belum ada data kelas. Klik <strong>+ Tambah Kelas</strong> untuk membuat kelas baru.</div>
        <?php else: ?>
            <?php foreach ($kelasList as $kl): ?>
                <?php
                $klId = (int)$kl['id'];
                $totalMhs = (int)$kl['total_mahasiswa'];
                $jmlHadir = (int)$kl['jumlah_hadir'];
                $jmlSudah = (int)$kl['jumlah_sudah_memilih'];
                $pctVote = $totalMhs > 0 ? round(($jmlSudah / $totalMhs) * 100) : 0;
                $searchHaystack = strtolower($kl['nama_kelas'] . ' ' . $kl['jurusan'] . ' semester ' . $kl['semester']);
                ?>
                <article class="kelas-card" data-kelas-card data-search="<?= e($searchHaystack) ?>">
                    <div class="kelas-card-head" onclick="openDetailKelasModal(<?= $klId ?>)">
                        <span class="kelas-card-code"><?= e($kl['nama_kelas']) ?></span>
                        <span class="kelas-card-total"><?= $totalMhs ?> Mahasiswa</span>
                    </div>

                    <div class="kelas-card-info" onclick="openDetailKelasModal(<?= $klId ?>)">
                        <div class="kelas-card-major" title="<?= e($kl['jurusan']) ?>"><?= e($kl['jurusan']) ?></div>
                        <div class="kelas-card-sem">Semester <?= (int)$kl['semester'] ?></div>
                    </div>

                    <div class="kelas-card-progress" onclick="openDetailKelasModal(<?= $klId ?>)">
                        <div class="kelas-card-metrics">
                            <span>Hadir <strong><?= $jmlHadir ?>/<?= $totalMhs ?></strong></span>
                            <span>Memilih <strong><?= $jmlSudah ?>/<?= $totalMhs ?></strong> (<?= $pctVote ?>%)</span>
                        </div>
                        <div class="progress" style="height:5px;">
                            <div class="progress-bar <?= $pctVote >= 100 ? 'success' : '' ?>" style="width: <?= min(100, $pctVote) ?>%;"></div>
                        </div>
                    </div>

                    <div class="kelas-card-foot">
                        <button type="button" class="btn btn--soft-primary btn--xs" style="flex:1;" onclick="openDetailKelasModal(<?= $klId ?>)">
                            Buka Kelas
                        </button>
                        <div class="data-cell-actions">
                            <button type="button" class="btn btn--ghost btn--xs" onclick='openEditKelasModal(<?= json_encode($kl, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>Edit</button>
                            <form action="<?= e(action_url('mahasiswa')) ?>" method="POST" data-confirm="Hapus kelas <?= e($kl['nama_kelas']) ?> beserta seluruh mahasiswa di dalamnya?" style="display:inline;">
                                <?= csrf_field() ?>
                                <input type="hidden" name="sub_action" value="delete_kelas">
                                <input type="hidden" name="id" value="<?= $klId ?>">
                                <button type="submit" class="btn btn--soft-danger btn--xs" title="Hapus Kelas">×</button>
                            </form>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</section>

<!-- Modal Besar Detail Mahasiswa per Kelas -->
<div class="modal-backdrop" id="detailKelasModal">
    <div class="modal-dialog modal-xl">
        <div class="modal-head">
            <div>
                <h3 class="modal-title" id="dkTitle">Detail Kelas</h3>
                <div class="hint" id="dkSummaryText" style="margin-top:2px;">-</div>
            </div>
            <button type="button" class="modal-close" data-modal-close="detailKelasModal">×</button>
        </div>

        <div class="modal-body">
            <!-- Toolbar Minimalis -->
            <div class="data-toolbar">
                <div class="data-toolbar-left" style="flex:1;min-width:220px;">
                    <div class="input-icon" style="flex:1;max-width:260px;">
                        <span class="ico"><svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg></span>
                        <input type="search" id="dkSearchInput" class="input" placeholder="Cari nama atau NIM..." oninput="renderDetailKelasRows()">
                    </div>
                    <select id="dkFilterStatus" class="select" style="width:auto;padding:6px 28px 6px 10px;font-size:12px;" onchange="renderDetailKelasRows()">
                        <option value="ALL">Semua Status</option>
                        <option value="SUDAH">Sudah Memilih</option>
                        <option value="BELUM">Belum Memilih</option>
                        <option value="NON_HADIR">Belum Check-In</option>
                    </select>
                </div>
                <div class="data-toolbar-right" style="gap:6px;">
                    <form action="<?= e(action_url('mahasiswa')) ?>" method="POST" style="display:inline;">
                        <?= csrf_field() ?>
                        <input type="hidden" name="sub_action" value="set_all_hadir_kelas">
                        <input type="hidden" name="kelas_id" id="dk_checkall_kelas_id" value="0">
                        <input type="hidden" name="status_hadir" value="1">
                        <button type="submit" class="btn btn--ghost btn--xs">Check-In Semua</button>
                    </form>
                    <button type="button" class="btn btn--primary btn--xs" onclick="openAddMahasiswaModal(window.activeDetailKelasId || 0)">
                        + Tambah Mahasiswa
                    </button>
                </div>
            </div>

            <!-- Tabel Daftar Mahasiswa Kelas -->
            <div class="data-table-wrap dk-table-scroll">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>NIM</th>
                            <th>Nama Lengkap</th>
                            <th>Check-In</th>
                            <th>Status Suara</th>
                            <th class="text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody id="dkMahasiswaTbody">
                        <tr><td colspan="5" class="table-empty">Pilih kelas untuk melihat daftar mahasiswa.</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="modal-foot" style="justify-content:space-between;">
            <span class="hint" id="dkFooterInfo">0 mahasiswa</span>
            <button type="button" class="btn btn--ghost btn--sm" data-modal-close="detailKelasModal">Tutup</button>
        </div>
    </div>
</div>

<!-- Modal Tambah / Import Mahasiswa -->
<div class="modal-backdrop" id="mahasiswaModal">
    <div class="modal-dialog modal-md">
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
                    <label class="field-label">
                        <span>Program Studi / Jurusan</span>
                        <a href="<?= e(route_url('manajemen_jurusan')) ?>" class="hint" style="color:var(--primary);text-decoration:none;">Kelola Jurusan ↗</a>
                    </label>
                    <select name="jurusan_id" id="kelas_jurusan_input" class="select" required>
                        <option value="">-- Pilih Jurusan --</option>
                        <?php foreach ($jurusanList as $jur): ?>
                            <option value="<?= (int)$jur['id'] ?>" data-nama="<?= e($jur['nama_jurusan']) ?>">
                                <?= e($jur['nama_jurusan']) ?> (<?= e($jur['kode_jurusan']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
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
window.activeDetailKelasId = 0;

function getKelasPayload() {
    const el = document.getElementById('kelasDataPayload');
    if (!el) return { kelas: {}, mahasiswaByKelas: {}, actionUrl: '', csrfToken: '' };
    try {
        return JSON.parse(el.textContent || '{}');
    } catch (_) {
        return { kelas: {}, mahasiswaByKelas: {}, actionUrl: '', csrfToken: '' };
    }
}

function escapeHtmlStr(str) {
    return String(str ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

function applyKelasCardFilter() {
    const q = (document.getElementById('kelasSearchInput')?.value || '').trim().toLowerCase();
    const cards = document.querySelectorAll('#kelasCardsGrid [data-kelas-card]');
    let visibleCount = 0;
    cards.forEach(card => {
        const hay = (card.getAttribute('data-search') || '').toLowerCase();
        const match = !q || hay.includes(q);
        card.style.display = match ? '' : 'none';
        if (match) visibleCount++;
    });
    const badge = document.getElementById('kelasCountBadge');
    if (badge) {
        badge.textContent = visibleCount + ' Kelas';
    }
}
window.applyKelasCardFilter = applyKelasCardFilter;

function openDetailKelasModal(kelasId, resetFilter = true) {
    const payload = getKelasPayload();
    const kl = payload.kelas?.[kelasId];
    if (!kl) {
        PemiraApp.closeModal('detailKelasModal');
        window.activeDetailKelasId = 0;
        return;
    }

    window.activeDetailKelasId = Number(kelasId);

    const total = Number(kl.total_mahasiswa || 0);
    const hadir = Number(kl.jumlah_hadir || 0);
    const sudah = Number(kl.jumlah_sudah_memilih || 0);

    document.getElementById('dkTitle').textContent = (kl.nama_kelas || '-') + ' · ' + (kl.jurusan || '-');
    document.getElementById('dkSummaryText').textContent = `Semester ${kl.semester || '-'} · ${total} Mahasiswa · ${hadir} Hadir · ${sudah} Sudah Memilih`;
    document.getElementById('dk_checkall_kelas_id').value = kelasId;

    if (resetFilter) {
        const searchEl = document.getElementById('dkSearchInput');
        const filterEl = document.getElementById('dkFilterStatus');
        if (searchEl) searchEl.value = '';
        if (filterEl) filterEl.value = 'ALL';
    }

    renderDetailKelasRows();
    PemiraApp.openModal('detailKelasModal');
}
window.openDetailKelasModal = openDetailKelasModal;

function refreshOpenDetailKelasModal() {
    const modal = document.getElementById('detailKelasModal');
    if (modal && modal.classList.contains('is-open') && window.activeDetailKelasId > 0) {
        openDetailKelasModal(window.activeDetailKelasId, false);
    }
}
window.refreshOpenDetailKelasModal = refreshOpenDetailKelasModal;

function renderDetailKelasRows() {
    const kelasId = window.activeDetailKelasId;
    const payload = getKelasPayload();
    const list = payload.mahasiswaByKelas?.[kelasId] || [];
    const actionUrl = escapeHtmlStr(payload.actionUrl || '');
    const csrfToken = escapeHtmlStr(payload.csrfToken || '');

    const q = (document.getElementById('dkSearchInput')?.value || '').trim().toLowerCase();
    const statusFilter = document.getElementById('dkFilterStatus')?.value || 'ALL';

    const filtered = list.filter(m => {
        const matchQ = !q ||
            String(m.nama_lengkap || '').toLowerCase().includes(q) ||
            String(m.nim || '').toLowerCase().includes(q);
        if (!matchQ) return false;

        const sudahVote = Number(m.sudah_memilih) === 1;
        const statusHadir = Number(m.status_hadir) === 1;

        if (statusFilter === 'SUDAH' && !sudahVote) return false;
        if (statusFilter === 'BELUM' && sudahVote) return false;
        if (statusFilter === 'NON_HADIR' && statusHadir) return false;
        return true;
    });

    const tbody = document.getElementById('dkMahasiswaTbody');
    const footerInfo = document.getElementById('dkFooterInfo');
    if (footerInfo) {
        footerInfo.textContent = `${filtered.length} dari ${list.length} mahasiswa`;
    }

    if (!tbody) return;

    if (filtered.length === 0) {
        tbody.innerHTML = `<tr><td colspan="5" class="table-empty">${
            list.length === 0
                ? 'Belum ada mahasiswa di kelas ini.'
                : 'Tidak ada mahasiswa yang cocok.'
        }</td></tr>`;
        return;
    }

    tbody.innerHTML = filtered.map(m => {
        const sudahVote = Number(m.sudah_memilih) === 1;
        const statusHadir = Number(m.status_hadir) === 1;
        const nextHadir = statusHadir ? 0 : 1;
        const voteTime = m.waktu_memilih_indo && m.waktu_memilih_indo !== '-' ? ` (${m.waktu_memilih_indo})` : '';
        const mJsonAttr = escapeHtmlStr(JSON.stringify({
            id: m.id,
            nim: m.nim,
            nama_lengkap: m.nama_lengkap,
            kelas_id: m.kelas_id,
            status_hadir: m.status_hadir
        }));

        return `
            <tr class="data-row">
                <td><span class="data-cell-mono fw-600">${escapeHtmlStr(m.nim)}</span></td>
                <td><span class="fw-600">${escapeHtmlStr(m.nama_lengkap)}</span></td>
                <td>
                    <form action="${actionUrl}" method="POST" style="display:inline;">
                        <input type="hidden" name="csrf_token" value="${csrfToken}">
                        <input type="hidden" name="sub_action" value="toggle_hadir">
                        <input type="hidden" name="id" value="${Number(m.id)}">
                        <input type="hidden" name="kelas_id" value="${Number(m.kelas_id)}">
                        <input type="hidden" name="status_hadir" value="${nextHadir}">
                        <button type="submit" class="badge ${statusHadir ? 'info' : 'neutral'} dot" style="cursor:pointer;border:0;" title="Klik untuk ubah status Check-In">
                            ${statusHadir ? 'Hadir' : 'Belum'}
                        </button>
                    </form>
                </td>
                <td>
                    ${sudahVote
                        ? `<span class="badge success dot" title="Memilih${escapeHtmlStr(voteTime)}">Sudah Memilih</span>`
                        : '<span class="badge neutral">Belum</span>'}
                </td>
                <td>
                    <div class="data-cell-actions">
                        <button type="button" class="btn btn--ghost btn--xs" data-mhs-edit="${mJsonAttr}" onclick="openEditMahasiswaFromBtn(this)">Edit</button>
                        <form action="${actionUrl}" method="POST" data-confirm="Hapus mahasiswa ${escapeHtmlStr(m.nama_lengkap)} (${escapeHtmlStr(m.nim)}) dari DPT?" style="display:inline;">
                            <input type="hidden" name="csrf_token" value="${csrfToken}">
                            <input type="hidden" name="sub_action" value="delete_mahasiswa">
                            <input type="hidden" name="id" value="${Number(m.id)}">
                            <input type="hidden" name="kelas_id" value="${Number(m.kelas_id)}">
                            <button type="submit" class="btn btn--soft-danger btn--xs" title="Hapus Mahasiswa">×</button>
                        </form>
                    </div>
                </td>
            </tr>
        `;
    }).join('');
}

function openEditMahasiswaFromBtn(btn) {
    try {
        const m = JSON.parse(btn.getAttribute('data-mhs-edit') || '{}');
        if (m && m.id) openEditMahasiswaModal(m);
    } catch (_) {}
}

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
    const jurSel = document.getElementById('kelas_jurusan_input');
    if (jurSel) {
        if (kl.jurusan_id && jurSel.querySelector(`option[value="${kl.jurusan_id}"]`)) {
            jurSel.value = String(kl.jurusan_id);
        } else {
            const matchedOpt = Array.from(jurSel.options).find(o => (o.getAttribute('data-nama') || '').toLowerCase() === String(kl.jurusan || '').trim().toLowerCase());
            jurSel.value = matchedOpt ? matchedOpt.value : '';
        }
    }
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
    const isDetailOpen = document.getElementById('detailKelasModal')?.classList.contains('is-open');
    const targetKelasId = defaultKelasId > 0 ? defaultKelasId : (isDetailOpen ? (window.activeDetailKelasId || 0) : 0);
    document.getElementById('mhs_kelas').value = targetKelasId > 0 ? targetKelasId : '';
    if (targetKelasId > 0) {
        document.getElementById('import_kelas_id').value = targetKelasId;
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

document.addEventListener('DOMContentLoaded', () => {
    const initialKelasId = <?= (int)$selectedKelasId ?>;
    if (initialKelasId > 0) {
        openDetailKelasModal(initialKelasId);
    }
});
</script>

<?php require BASE_PATH . '/views/layouts/footer.php'; ?>
