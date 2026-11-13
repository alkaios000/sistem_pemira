<?php
/**
 * MANAJEMEN KANDIDAT & TIMSES - Adminator 2026 Redesign (Fitur 1)
 */
require_auth();
require_once BASE_PATH . '/models/kandidat_model.php';

$pdo = get_db_connection();
$pageTitle = 'Kandidat & Timses';
$kandidatBem = get_kandidats_by_kategori($pdo, 'BEM');
$kandidatBpm = get_kandidats_by_kategori($pdo, 'BPM');
$jurusanList = get_all_jurusans($pdo);
$activeKategori = (strtoupper((string)($_GET['kategori'] ?? 'BEM')) === 'BPM') ? 'BPM' : 'BEM';

require BASE_PATH . '/views/layouts/header.php';
?>

<section class="hero" style="justify-content: space-between;">
    <div class="tabs pills" style="margin-bottom: 0;">
        <button type="button" class="tab <?= $activeKategori === 'BEM' ? 'is-active' : '' ?>" id="tabBtnBEM" onclick="switchKandidatTab('BEM')">
            Kandidat BEM (<?= count($kandidatBem) ?>)
        </button>
        <button type="button" class="tab <?= $activeKategori === 'BPM' ? 'is-active' : '' ?>" id="tabBtnBPM" onclick="switchKandidatTab('BPM')">
            Kandidat BPM (<?= count($kandidatBpm) ?>)
        </button>
    </div>
    <div class="hero-actions">
        <button type="button" class="btn btn--primary btn--sm" id="btnAddKandidatActive" onclick="openCreateKandidatModal(window.currentKandidatTab)">
            + Tambah Kandidat <?= e($activeKategori) ?>
        </button>
    </div>
</section>

<div class="grid">
    <!-- Kandidat BEM -->
    <section class="col-12 card" id="panelKandidatBEM" style="<?= $activeKategori === 'BEM' ? '' : 'display:none;' ?>">
        <div class="card-head">
            <div class="card-title-wrap">
                <h3 class="card-title">Calon Presiden BEM</h3>
            </div>
            <span class="badge primary"><?= count($kandidatBem) ?> Calon</span>
        </div>

        <div class="kandidat-grid">
            <?php foreach ($kandidatBem as $k): ?>
                <?php
                $fotoUrl = !empty($k['foto_path']) ? upload_url($k['foto_path']) : asset_url('img/default_candidate.svg');
                $kJson = json_encode($k, JSON_HEX_APOS | JSON_HEX_QUOT);
                $tsCount = is_array($k['tim_sukses'] ?? null) ? count($k['tim_sukses']) : 0;
                ?>
                <div class="kandidat-card">
                    <div class="kandidat-card-bar">
                        <span class="kandidat-num-pill">CALON #0<?= (int)$k['nomor_urut'] ?></span>
                        <div class="kandidat-stat-pills">
                            <span class="badge primary"><?= number_format((int)$k['jumlah_suara']) ?> Suara</span>
                            <span class="badge neutral"><?= $tsCount ?> Timses</span>
                        </div>
                    </div>

                    <div class="kandidat-card-top" onclick='openDetailKandidatModal(<?= $kJson ?>)' title="Klik untuk melihat detail Visi, Misi & Tim Sukses">
                        <img src="<?= e($fotoUrl) ?>" alt="Calon" class="kandidat-photo">
                        <div class="kandidat-meta">
                            <div class="kandidat-names"><?= e($k['nama_calon']) ?></div>
                            <div class="kandidat-classes"><?= e($k['jurusan_kelas'] ?: 'Program Studi belum diisi') ?></div>
                        </div>
                    </div>

                    <div class="kandidat-card-foot">
                        <button type="button" class="btn btn--soft-primary btn--xs" style="flex:1;" data-kand-id="<?= (int)$k['id'] ?>" data-kand-json='<?= $kJson ?>' onclick='openDetailKandidatModal(<?= $kJson ?>)'>
                            Detail Visi & Timses
                        </button>
                        <div class="data-cell-actions">
                            <button type="button" class="btn btn--ghost btn--xs" onclick='openEditKandidatModal(<?= $kJson ?>)'>Edit</button>
                            <form action="<?= e(action_url('kandidat')) ?>" method="POST" data-confirm="Hapus kandidat ini beserta tim suksesnya?">
                                <?= csrf_field() ?>
                                <input type="hidden" name="sub_action" value="delete">
                                <input type="hidden" name="id" value="<?= (int)$k['id'] ?>">
                                <input type="hidden" name="kategori" value="BEM">
                                <button type="submit" class="btn btn--soft-danger btn--xs" title="Hapus Kandidat">×</button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- Kandidat BPM -->
    <section class="col-12 card" id="panelKandidatBPM" style="<?= $activeKategori === 'BPM' ? '' : 'display:none;' ?>">
        <div class="card-head">
            <div class="card-title-wrap">
                <h3 class="card-title">Calon Ketua Umum BPM (Badan Perwakilan Mahasiswa)</h3>
            </div>
            <span class="badge purple"><?= count($kandidatBpm) ?> Calon</span>
        </div>

        <div class="kandidat-grid">
            <?php foreach ($kandidatBpm as $k): ?>
                <?php
                $fotoUrl = !empty($k['foto_path']) ? upload_url($k['foto_path']) : asset_url('img/default_candidate.svg');
                $kJson = json_encode($k, JSON_HEX_APOS | JSON_HEX_QUOT);
                $tsCount = is_array($k['tim_sukses'] ?? null) ? count($k['tim_sukses']) : 0;
                ?>
                <div class="kandidat-card">
                    <div class="kandidat-card-bar">
                        <span class="kandidat-num-pill is-bpm">CALON #0<?= (int)$k['nomor_urut'] ?></span>
                        <div class="kandidat-stat-pills">
                            <span class="badge purple"><?= number_format((int)$k['jumlah_suara']) ?> Suara</span>
                            <span class="badge neutral"><?= $tsCount ?> Timses</span>
                        </div>
                    </div>

                    <div class="kandidat-card-top" onclick='openDetailKandidatModal(<?= $kJson ?>)' title="Klik untuk melihat detail Visi, Misi & Tim Sukses">
                        <img src="<?= e($fotoUrl) ?>" alt="Calon" class="kandidat-photo">
                        <div class="kandidat-meta">
                            <div class="kandidat-names"><?= e($k['nama_calon']) ?></div>
                            <div class="kandidat-classes"><?= e($k['jurusan_kelas'] ?: 'Program Studi belum diisi') ?></div>
                        </div>
                    </div>

                    <div class="kandidat-card-foot">
                        <button type="button" class="btn btn--soft-primary btn--xs" style="flex:1;" data-kand-id="<?= (int)$k['id'] ?>" data-kand-json='<?= $kJson ?>' onclick='openDetailKandidatModal(<?= $kJson ?>)'>
                            Detail Visi & Timses
                        </button>
                        <div class="data-cell-actions">
                            <button type="button" class="btn btn--ghost btn--xs" onclick='openEditKandidatModal(<?= $kJson ?>)'>Edit</button>
                            <form action="<?= e(action_url('kandidat')) ?>" method="POST" data-confirm="Hapus kandidat ini beserta tim suksesnya?">
                                <?= csrf_field() ?>
                                <input type="hidden" name="sub_action" value="delete">
                                <input type="hidden" name="id" value="<?= (int)$k['id'] ?>">
                                <input type="hidden" name="kategori" value="BPM">
                                <button type="submit" class="btn btn--soft-danger btn--xs" title="Hapus Kandidat">×</button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
</div>

<!-- Modal Detail Kandidat (3 Bagian Kiri-Tengah-Kanan: Data Pribadi | Visi & Misi | Tim Sukses) -->
<div class="modal-backdrop" id="detailKandidatModal">
    <div class="modal-dialog modal-xl">
        <div class="modal-head">
            <h3 class="modal-title" id="detailKandTitle">Detail Kandidat</h3>
            <button type="button" class="modal-close" data-modal-close="detailKandidatModal">×</button>
        </div>
        <div class="modal-body">
            <div class="kand-modal-3col">
                <!-- Bagian Kiri: Data Pribadi -->
                <div class="kand-sec-panel">
                    <div class="kand-sec-head">
                        <span>Data Pribadi</span>
                    </div>
                    <div class="d-flex align-center gap-3 mb-3">
                        <img src="<?= e(asset_url('img/default_candidate.svg')) ?>"
                             data-default-src="<?= e(asset_url('img/default_candidate.svg')) ?>"
                             id="detailKandPhoto"
                             alt="Foto Kandidat"
                             class="photo-preview-img"
                             style="width:64px;height:64px;">
                        <div style="min-width:0;flex:1;">
                            <div class="kandidat-urut" id="detailKandMeta">CALON #01</div>
                            <div class="kandidat-names" id="detailKandName" style="font-size:14.5px;">-</div>
                        </div>
                    </div>
                    <div class="field mb-2">
                        <span class="field-label text-muted">Jurusan / Semester</span>
                        <div class="fw-600" id="detailKandJurusan" style="font-size:12px;">-</div>
                    </div>
                    <div class="field mb-0">
                        <span class="field-label text-muted">Perolehan Suara</span>
                        <div><span class="badge primary" id="detailKandSuaraBadge">0 Suara</span></div>
                    </div>
                </div>

                <!-- Bagian Tengah: Visi & Misi (Atas-Bawah) -->
                <div class="kand-sec-panel">
                    <div class="kand-sec-head">
                        <span>Visi & Misi</span>
                    </div>
                    <div class="field mb-2">
                        <span class="field-label">Visi</span>
                        <div class="kandidat-visi" id="detailKandVisi" style="background:var(--bg-card);">-</div>
                    </div>
                    <div class="field mb-0" style="flex:1;display:flex;flex-direction:column;">
                        <span class="field-label">Misi</span>
                        <div class="kandidat-visi" id="detailKandMisi" style="background:var(--bg-card);white-space:pre-line;flex:1;">-</div>
                    </div>
                </div>

                <!-- Bagian Kanan: Tim Sukses -->
                <div class="kand-sec-panel">
                    <div class="kand-sec-head">
                        <span id="detailKandTimsesCount">Tim Sukses (0)</span>
                    </div>
                    <div id="detailKandTimsesList" style="display:flex;flex-direction:column;gap:6px;max-height:220px;overflow-y:auto;"></div>
                </div>
            </div>
        </div>
        <div class="modal-foot">
            <button type="button" class="btn btn--ghost btn--sm" data-modal-close="detailKandidatModal">Tutup</button>
        </div>
    </div>
</div>

<!-- Modal Form Input/Edit Kandidat (3 Bagian Kiri-Tengah-Kanan: Data Pribadi | Visi & Misi | Tim Sukses) -->
<div class="modal-backdrop" id="kandidatModal">
    <div class="modal-dialog modal-xl">
        <div class="modal-head">
            <h3 class="modal-title" id="kandidatModalTitle">Tambah Kandidat</h3>
            <button type="button" class="modal-close" data-modal-close="kandidatModal">×</button>
        </div>
        <form action="<?= e(action_url('kandidat')) ?>" method="POST" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <input type="hidden" name="sub_action" id="kand_sub_action" value="create">
            <input type="hidden" name="id" id="kand_id" value="0">
            <div class="modal-body">
                <div class="kand-modal-3col">
                    <!-- Bagian Kiri: Data Pribadi & Custom Upload Foto + Preview -->
                    <div class="kand-sec-panel">
                        <div class="kand-sec-head">
                            <span>Data Pribadi</span>
                        </div>
                        <input type="file" name="foto_kandidat" id="kand_foto_input" accept=".jpg,.jpeg,.png,.webp" style="display:none;">
                        <label for="kand_foto_input" class="photo-upload-box" id="kandPhotoDropzone">
                            <img src="<?= e(asset_url('img/default_candidate.svg')) ?>"
                                 data-default-src="<?= e(asset_url('img/default_candidate.svg')) ?>"
                                 id="kandFotoPreview"
                                 alt="Preview Foto"
                                 class="photo-preview-img">
                            <div class="photo-upload-meta">
                                <span class="photo-upload-btn">Pilih / Ganti Foto</span>
                                <span class="photo-upload-name" id="kandFotoFileName">JPG/PNG/WEBP · Maks 2MB</span>
                            </div>
                        </label>

                        <div class="form-grid mb-2" style="grid-template-columns: 1.25fr 0.75fr;">
                            <div class="field">
                                <label class="field-label">Kategori</label>
                                <select name="kategori" id="kand_kategori" class="select" required>
                                    <option value="BEM">BEM (Presiden)</option>
                                    <option value="BPM">BPM (Ketua)</option>
                                </select>
                            </div>
                            <div class="field">
                                <label class="field-label">No. Urut</label>
                                <input type="number" name="nomor_urut" id="kand_urut" class="input mono" min="1" max="20" value="1" required>
                            </div>
                        </div>

                        <div class="field">
                            <label class="field-label">Nama Lengkap Calon</label>
                            <input type="text" name="nama_calon" id="kand_nama_calon" class="input" placeholder="Nama lengkap kandidat" required>
                        </div>

                        <div class="form-grid mb-0" style="grid-template-columns: 1.45fr 0.55fr;">
                            <div class="field mb-0">
                                <label class="field-label">
                                    <span>Jurusan</span>
                                    <a href="<?= e(route_url('manajemen_jurusan')) ?>" class="hint" style="color:var(--primary);text-decoration:none;">Kelola ↗</a>
                                </label>
                                <select name="jurusan_id" id="kand_jurusan_id" class="select" required>
                                    <option value="">-- Pilih Jurusan --</option>
                                    <?php foreach ($jurusanList as $jur): ?>
                                        <option value="<?= (int)$jur['id'] ?>" data-nama="<?= e($jur['nama_jurusan']) ?>">
                                            <?= e($jur['nama_jurusan']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="field mb-0">
                                <label class="field-label">Smt</label>
                                <input type="number" name="semester" id="kand_semester" class="input mono" min="1" max="14" placeholder="1-14">
                            </div>
                        </div>
                    </div>

                    <!-- Bagian Tengah: Visi & Misi (Atas-Bawah) -->
                    <div class="kand-sec-panel">
                        <div class="kand-sec-head">
                            <span>Visi & Misi</span>
                        </div>
                        <div class="field">
                            <label class="field-label">Visi</label>
                            <textarea name="visi" id="kand_visi" class="textarea" style="min-height:76px;" placeholder="Tuliskan visi utama kandidat..." required></textarea>
                        </div>

                        <div class="field mb-0" style="flex:1;display:flex;flex-direction:column;">
                            <label class="field-label">Misi</label>
                            <textarea name="misi" id="kand_misi" class="textarea" style="min-height:105px;flex:1;" placeholder="Tuliskan poin-poin misi kandidat..." required></textarea>
                        </div>
                    </div>

                    <!-- Bagian Kanan: Tim Sukses -->
                    <div class="kand-sec-panel">
                        <div class="kand-sec-head">
                            <span>Tim Sukses</span>
                            <button type="button" class="btn btn--soft-primary btn--xs" onclick="addTimsesRow()">+ Anggota</button>
                        </div>
                        <div id="timsesRowsContainer" style="display:flex;flex-direction:column;gap:6px;max-height:225px;overflow-y:auto;padding-right:2px;"></div>
                    </div>
                </div>
            </div>
            <div class="modal-foot">
                <button type="button" class="btn btn--ghost btn--sm" data-modal-close="kandidatModal">Batal</button>
                <button type="submit" class="btn btn--primary btn--sm">Simpan</button>
            </div>
        </form>
    </div>
</div>

<script>
window.currentKandidatTab = '<?= e($activeKategori) ?>';

function switchKandidatTab(kategori) {
    window.currentKandidatTab = (kategori === 'BPM') ? 'BPM' : 'BEM';
    const activeTab = window.currentKandidatTab;
    const panelBEM = document.getElementById('panelKandidatBEM');
    const panelBPM = document.getElementById('panelKandidatBPM');
    const tabBEM = document.getElementById('tabBtnBEM');
    const tabBPM = document.getElementById('tabBtnBPM');
    const btnAdd = document.getElementById('btnAddKandidatActive');

    if (activeTab === 'BPM') {
        if (panelBEM) panelBEM.style.display = 'none';
        if (panelBPM) panelBPM.style.display = '';
        if (tabBEM) tabBEM.classList.remove('is-active');
        if (tabBPM) tabBPM.classList.add('is-active');
    } else {
        if (panelBEM) panelBEM.style.display = '';
        if (panelBPM) panelBPM.style.display = 'none';
        if (tabBEM) tabBEM.classList.add('is-active');
        if (tabBPM) tabBPM.classList.remove('is-active');
    }

    if (btnAdd) {
        btnAdd.textContent = '+ Tambah Kandidat ' + activeTab;
    }

    const url = new URL(window.location.href);
    url.searchParams.set('kategori', activeTab);
    window.history.replaceState({}, '', url.toString());
}
window.switchKandidatTab = switchKandidatTab;

function addTimsesRow(nama = '', nim = '', jabatan = 'Koordinator Kampanye') {
    const container = document.getElementById('timsesRowsContainer');
    const row = document.createElement('div');
    row.style.cssText = 'display:grid; grid-template-columns:1fr 90px; gap:5px; padding:7px; background:#ffffff; border:1px solid var(--border); border-radius:8px; position:relative;';
    row.innerHTML = `
        <input type="text" name="timses_nama[]" class="input" placeholder="Nama Anggota" value="${nama.replace(/"/g, '&quot;')}" style="grid-column:1 / -1; padding-right:26px;">
        <input type="text" name="timses_jabatan[]" class="input" placeholder="Jabatan" value="${jabatan.replace(/"/g, '&quot;')}">
        <input type="text" name="timses_nim[]" class="input mono" placeholder="NIM" value="${nim.replace(/"/g, '&quot;')}">
        <button type="button" class="btn btn--soft-danger btn--xs" onclick="this.parentElement.remove()" style="position:absolute; top:9px; right:9px; padding:1px 6px; line-height:1.2;">×</button>
    `;
    container.appendChild(row);
}

function resetFotoPreview(photoUrl = '', labelText = 'JPG/PNG/WEBP · Maks 2MB') {
    const img = document.getElementById('kandFotoPreview');
    const nameEl = document.getElementById('kandFotoFileName');
    const fileInput = document.getElementById('kand_foto_input');
    if (fileInput) fileInput.value = '';
    if (img) {
        const def = img.getAttribute('data-default-src') || '';
        img.src = photoUrl || def;
    }
    if (nameEl) {
        nameEl.textContent = labelText;
    }
}

document.addEventListener('DOMContentLoaded', () => {
    const fileInput = document.getElementById('kand_foto_input');
    const dropzone = document.getElementById('kandPhotoDropzone');
    const img = document.getElementById('kandFotoPreview');
    const nameEl = document.getElementById('kandFotoFileName');

    function handlePreviewFile(file) {
        if (!file) return;
        if (nameEl) nameEl.textContent = file.name;
        const reader = new FileReader();
        reader.onload = e => {
            if (img && e.target?.result) img.src = e.target.result;
        };
        reader.readAsDataURL(file);
    }

    if (fileInput) {
        fileInput.addEventListener('change', () => {
            if (fileInput.files && fileInput.files[0]) {
                handlePreviewFile(fileInput.files[0]);
            }
        });
    }

    if (dropzone && fileInput) {
        ['dragenter', 'dragover'].forEach(ev => {
            dropzone.addEventListener(ev, e => {
                e.preventDefault();
                dropzone.classList.add('is-dragover');
            });
        });
        ['dragleave', 'drop'].forEach(ev => {
            dropzone.addEventListener(ev, e => {
                e.preventDefault();
                dropzone.classList.remove('is-dragover');
            });
        });
        dropzone.addEventListener('drop', e => {
            if (e.dataTransfer?.files?.length) {
                fileInput.files = e.dataTransfer.files;
                handlePreviewFile(e.dataTransfer.files[0]);
            }
        });
    }
});

function openDetailKandidatModal(k, openIt = true) {
    window.currentDetailKandidatId = k.id;
    document.getElementById('detailKandTitle').textContent = 'Detail Kandidat ' + (k.kategori || 'BEM') + ' #0' + k.nomor_urut;
    const photoEl = document.getElementById('detailKandPhoto');
    if (photoEl) {
        const def = photoEl.getAttribute('data-default-src') || '';
        photoEl.src = k.foto_path ? `${window.PEMIRA_CONFIG.baseUrl}/uploads/${k.foto_path}` : def;
    }
    const metaEl = document.getElementById('detailKandMeta');
    if (metaEl) {
        metaEl.textContent = `${k.kategori || 'BEM'} · CALON #0${k.nomor_urut}`;
        metaEl.style.color = (k.kategori === 'BPM') ? 'var(--purple)' : 'var(--primary)';
    }
    const suaraBadge = document.getElementById('detailKandSuaraBadge');
    if (suaraBadge) {
        suaraBadge.textContent = `${Number(k.jumlah_suara || 0).toLocaleString('id-ID')} SUARA`;
        suaraBadge.className = 'badge ' + ((k.kategori === 'BPM') ? 'badge--purple' : 'badge--blue');
    }
    document.getElementById('detailKandName').textContent = k.nama_calon || '-';
    document.getElementById('detailKandJurusan').textContent = k.jurusan_kelas || '-';
    document.getElementById('detailKandVisi').textContent = k.visi || '-';
    document.getElementById('detailKandMisi').textContent = k.misi || '-';

    const tsList = Array.isArray(k.tim_sukses) ? k.tim_sukses : [];
    document.getElementById('detailKandTimsesCount').textContent = `Tim Sukses (${tsList.length})`;
    const tsContainer = document.getElementById('detailKandTimsesList');
    tsContainer.innerHTML = '';
    if (tsList.length === 0) {
        tsContainer.innerHTML = '<span class="text-light" style="font-size:11px;">Belum ada anggota tim sukses</span>';
    } else {
        tsList.forEach(ts => {
            const item = document.createElement('div');
            item.style.cssText = 'padding:8px 10px; background:#ffffff; border:1px solid var(--border); border-radius:8px; display:flex; flex-direction:column; gap:2px;';
            const nameLine = document.createElement('div');
            nameLine.style.cssText = 'font-size:12px; font-weight:700; color:var(--text-main);';
            nameLine.textContent = ts.nama_anggota || '-';
            const subLine = document.createElement('div');
            subLine.className = 'mono text-muted';
            subLine.style.cssText = 'font-size:10.5px;';
            const nimPart = ts.nim_anggota ? ` · ${ts.nim_anggota}` : '';
            subLine.textContent = `${ts.jabatan_timses || 'Anggota'}${nimPart}`;
            item.appendChild(nameLine);
            item.appendChild(subLine);
            tsContainer.appendChild(item);
        });
    }

    if (openIt) {
        PemiraApp.openModal('detailKandidatModal');
    }
}

function openCreateKandidatModal(kategori = 'BEM') {
    document.getElementById('kandidatModalTitle').textContent = 'Tambah Kandidat ' + kategori;
    document.getElementById('kand_sub_action').value = 'create';
    document.getElementById('kand_id').value = 0;
    document.getElementById('kand_kategori').value = kategori;
    document.getElementById('kand_urut').value = 1;
    document.getElementById('kand_nama_calon').value = '';
    document.getElementById('kand_jurusan_id').value = '';
    document.getElementById('kand_semester').value = '';
    document.getElementById('kand_visi').value = '';
    document.getElementById('kand_misi').value = '';
    resetFotoPreview('', 'JPG/PNG/WEBP · Maks 2MB');
    document.getElementById('timsesRowsContainer').innerHTML = '';
    addTimsesRow('', '', 'Ketua Tim Sukses');
    PemiraApp.openModal('kandidatModal');
}

function openEditKandidatModal(k) {
    document.getElementById('kandidatModalTitle').textContent = 'Edit Kandidat ' + k.kategori + ' #0' + k.nomor_urut;
    document.getElementById('kand_sub_action').value = 'update';
    document.getElementById('kand_id').value = k.id;
    document.getElementById('kand_kategori').value = k.kategori;
    document.getElementById('kand_urut').value = k.nomor_urut;
    document.getElementById('kand_nama_calon').value = k.nama_calon || '';
    const jurSel = document.getElementById('kand_jurusan_id');
    const rawJurusanKelas = String(k.jurusan_kelas || '').trim();
    const smtMatch = rawJurusanKelas.match(/Smt\s*(\d+)/i);
    document.getElementById('kand_semester').value = smtMatch ? smtMatch[1] : '';
    if (jurSel) {
        if (k.jurusan_id && jurSel.querySelector(`option[value="${k.jurusan_id}"]`)) {
            jurSel.value = String(k.jurusan_id);
        } else {
            const baseJur = rawJurusanKelas.replace(/\s*[·\-]\s*Smt\s*\d+/i, '').trim().toLowerCase();
            const matchedOpt = Array.from(jurSel.options).find(o => {
                const optNama = (o.getAttribute('data-nama') || '').toLowerCase();
                return optNama && (baseJur === optNama || baseJur.startsWith(optNama));
            });
            jurSel.value = matchedOpt ? matchedOpt.value : '';
        }
    }
    document.getElementById('kand_visi').value = k.visi || '';
    document.getElementById('kand_misi').value = k.misi || '';
    const existingFoto = k.foto_path ? `${window.PEMIRA_CONFIG.baseUrl}/uploads/${k.foto_path}` : '';
    resetFotoPreview(existingFoto, k.foto_path ? 'Foto tersimpan (klik untuk ganti)' : 'JPG/PNG/WEBP · Maks 2MB');
    const container = document.getElementById('timsesRowsContainer');
    container.innerHTML = '';
    if (Array.isArray(k.tim_sukses) && k.tim_sukses.length > 0) {
        k.tim_sukses.forEach(ts => addTimsesRow(ts.nama_anggota || '', ts.nim_anggota || '', ts.jabatan_timses || ''));
    } else {
        addTimsesRow('', '', 'Ketua Tim Sukses');
    }
    PemiraApp.openModal('kandidatModal');
}
</script>

<?php require BASE_PATH . '/views/layouts/footer.php'; ?>
