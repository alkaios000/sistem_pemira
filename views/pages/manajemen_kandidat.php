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

require BASE_PATH . '/views/layouts/header.php';
?>

<section class="hero">
    <div class="hero-text">
        <span class="eyebrow">Master Data · Fitur 1</span>
        <h1 class="hero-title">Kandidat & Tim Sukses</h1>
    </div>
    <div class="hero-actions">
        <button type="button" class="btn btn--primary btn--sm" onclick="openCreateKandidatModal('BEM')">+ Kandidat BEM</button>
        <button type="button" class="btn btn--soft-primary btn--sm" onclick="openCreateKandidatModal('BPM')">+ Kandidat BPM</button>
    </div>
</section>

<div class="grid">
    <!-- Kandidat BEM -->
    <section class="col-12 card">
        <div class="card-head">
            <div class="card-title-wrap">
                <span class="eyebrow">Kategori 01 · Eksekutif</span>
                <h3 class="card-title">Pasangan Calon Presiden & Wakil Presiden BEM</h3>
            </div>
            <span class="badge primary"><?= count($kandidatBem) ?> Paslon</span>
        </div>

        <div class="kandidat-grid">
            <?php foreach ($kandidatBem as $k): ?>
                <?php
                $timses = $k['tim_sukses'] ?? [];
                $fotoUrl = !empty($k['foto_path']) ? upload_url($k['foto_path']) : asset_url('img/default_candidate.svg');
                ?>
                <div class="kandidat-card">
                    <div class="kandidat-card-top">
                        <img src="<?= e($fotoUrl) ?>" alt="Paslon" class="kandidat-photo">
                        <div class="kandidat-meta">
                            <div class="kandidat-urut">PASLON #0<?= (int)$k['nomor_urut'] ?> · <?= number_format((int)$k['jumlah_suara']) ?> Suara</div>
                            <div class="kandidat-names"><?= e($k['nama_calon']) ?><?= !empty($k['nama_wakil']) ? ' & ' . e($k['nama_wakil']) : '' ?></div>
                            <div class="kandidat-classes"><?= e($k['jurusan_kelas'] ?? '-') ?></div>
                        </div>
                        <div class="data-cell-actions">
                            <button type="button" class="btn btn--ghost btn--xs" onclick='openEditKandidatModal(<?= json_encode($k, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>Edit</button>
                            <form action="<?= e(action_url('kandidat')) ?>" method="POST" onsubmit="return confirm('Hapus kandidat ini beserta tim suksesnya?');">
                                <?= csrf_field() ?>
                                <input type="hidden" name="sub_action" value="delete">
                                <input type="hidden" name="id" value="<?= (int)$k['id'] ?>">
                                <input type="hidden" name="kategori" value="BEM">
                                <button type="submit" class="btn btn--soft-danger btn--xs">×</button>
                            </form>
                        </div>
                    </div>

                    <div class="kandidat-visi">
                        <div><strong>Visi:</strong> <?= e($k['visi']) ?></div>
                        <div class="mt-1"><strong>Misi:</strong> <?= nl2br(e($k['misi'])) ?></div>
                    </div>

                    <div class="timses-block">
                        <div class="timses-head">
                            <span>Tim Sukses (<?= count($timses) ?>)</span>
                        </div>
                        <div class="timses-chips">
                            <?php if (empty($timses)): ?>
                                <span class="text-light" style="font-size:11px;">Belum ada anggota timses</span>
                            <?php else: ?>
                                <?php foreach ($timses as $ts): ?>
                                    <span class="timses-chip">
                                        <span><?= e($ts['nama_anggota']) ?> (<?= e($ts['jabatan_timses']) ?>)</span>
                                    </span>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- Kandidat BPM -->
    <section class="col-12 card">
        <div class="card-head">
            <div class="card-title-wrap">
                <span class="eyebrow" style="color:var(--purple);">Kategori 02 · Legislatif</span>
                <h3 class="card-title">Calon Ketua Umum BPM (Badan Perwakilan Mahasiswa)</h3>
            </div>
            <span class="badge purple"><?= count($kandidatBpm) ?> Calon</span>
        </div>

        <div class="kandidat-grid">
            <?php foreach ($kandidatBpm as $k): ?>
                <?php
                $timses = $k['tim_sukses'] ?? [];
                $fotoUrl = !empty($k['foto_path']) ? upload_url($k['foto_path']) : asset_url('img/default_candidate.svg');
                ?>
                <div class="kandidat-card">
                    <div class="kandidat-card-top">
                        <img src="<?= e($fotoUrl) ?>" alt="Calon" class="kandidat-photo">
                        <div class="kandidat-meta">
                            <div class="kandidat-urut" style="color:var(--purple);">CALON #0<?= (int)$k['nomor_urut'] ?> · <?= number_format((int)$k['jumlah_suara']) ?> Suara</div>
                            <div class="kandidat-names"><?= e($k['nama_calon']) ?></div>
                            <div class="kandidat-classes"><?= e($k['jurusan_kelas'] ?? '-') ?></div>
                        </div>
                        <div class="data-cell-actions">
                            <button type="button" class="btn btn--ghost btn--xs" onclick='openEditKandidatModal(<?= json_encode($k, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>Edit</button>
                            <form action="<?= e(action_url('kandidat')) ?>" method="POST" onsubmit="return confirm('Hapus kandidat ini beserta tim suksesnya?');">
                                <?= csrf_field() ?>
                                <input type="hidden" name="sub_action" value="delete">
                                <input type="hidden" name="id" value="<?= (int)$k['id'] ?>">
                                <input type="hidden" name="kategori" value="BPM">
                                <button type="submit" class="btn btn--soft-danger btn--xs">×</button>
                            </form>
                        </div>
                    </div>

                    <div class="kandidat-visi">
                        <div><strong>Visi:</strong> <?= e($k['visi']) ?></div>
                        <div class="mt-1"><strong>Misi:</strong> <?= nl2br(e($k['misi'])) ?></div>
                    </div>

                    <div class="timses-block">
                        <div class="timses-head">
                            <span>Tim Sukses (<?= count($timses) ?>)</span>
                        </div>
                        <div class="timses-chips">
                            <?php if (empty($timses)): ?>
                                <span class="text-light" style="font-size:11px;">Belum ada anggota timses</span>
                            <?php else: ?>
                                <?php foreach ($timses as $ts): ?>
                                    <span class="timses-chip">
                                        <span><?= e($ts['nama_anggota']) ?> (<?= e($ts['jabatan_timses']) ?>)</span>
                                    </span>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
</div>

<!-- Modal Form Kandidat & Tim Sukses -->
<div class="modal-backdrop" id="kandidatModal">
    <div class="modal-dialog modal-lg">
        <div class="modal-head">
            <h3 class="modal-title" id="kandidatModalTitle">Tambah Kandidat</h3>
            <button type="button" class="modal-close" data-modal-close="kandidatModal">×</button>
        </div>
        <form action="<?= e(action_url('kandidat')) ?>" method="POST" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <input type="hidden" name="sub_action" id="kand_sub_action" value="create">
            <input type="hidden" name="id" id="kand_id" value="0">
            <div class="modal-body">
                <div class="form-grid mb-3">
                    <div class="field">
                        <label class="field-label">Kategori Pemilihan</label>
                        <select name="kategori" id="kand_kategori" class="select" onchange="toggleWakilField()" required>
                            <option value="BEM">BEM (Presiden & Wapres)</option>
                            <option value="BPM">BPM (Ketua Umum)</option>
                        </select>
                    </div>
                    <div class="field">
                        <label class="field-label">Nomor Urut</label>
                        <input type="number" name="nomor_urut" id="kand_urut" class="input" min="1" max="20" value="1" required>
                    </div>
                </div>

                <div class="form-grid mb-3">
                    <div class="field">
                        <label class="field-label">Nama Calon Utama</label>
                        <input type="text" name="nama_calon" id="kand_nama_calon" class="input" required>
                    </div>
                    <div class="field" id="wakilFieldBox">
                        <label class="field-label">Nama Calon Wakil <span class="hint">Khusus BEM</span></label>
                        <input type="text" name="nama_wakil" id="kand_nama_wakil" class="input">
                    </div>
                </div>

                <div class="form-grid mb-3">
                    <div class="field">
                        <label class="field-label">Jurusan / Kelas</label>
                        <input type="text" name="jurusan_kelas" id="kand_jurusan_kelas" class="input" placeholder="Teknik Informatika - Semester 5" required>
                    </div>
                    <div class="field">
                        <label class="field-label">Foto Kandidat <span class="hint">JPG/PNG/WEBP Maks 2MB</span></label>
                        <input type="file" name="foto_kandidat" class="input" accept=".jpg,.jpeg,.png,.webp">
                    </div>
                </div>

                <div class="field">
                    <label class="field-label">Visi</label>
                    <textarea name="visi" id="kand_visi" class="textarea" style="min-height:54px;" required></textarea>
                </div>

                <div class="field">
                    <label class="field-label">Misi</label>
                    <textarea name="misi" id="kand_misi" class="textarea" style="min-height:70px;" required></textarea>
                </div>

                <div style="border-top:1px dashed var(--border);padding-top:10px;margin-top:8px;">
                    <div class="d-flex justify-between align-center mb-2">
                        <span class="eyebrow">Daftar Anggota Tim Sukses</span>
                        <button type="button" class="btn btn--soft-primary btn--xs" onclick="addTimsesRow()">+ Baris Timses</button>
                    </div>
                    <div id="timsesRowsContainer" style="display:flex;flex-direction:column;gap:6px;"></div>
                </div>
            </div>
            <div class="modal-foot">
                <button type="button" class="btn btn--ghost btn--sm" data-modal-close="kandidatModal">Batal</button>
                <button type="submit" class="btn btn--primary btn--sm">Simpan Kandidat</button>
            </div>
        </form>
    </div>
</div>

<script>
function toggleWakilField() {
    const kat = document.getElementById('kand_kategori').value;
    document.getElementById('wakilFieldBox').style.display = (kat === 'BEM') ? 'flex' : 'none';
}

function addTimsesRow(nama = '', nim = '', jabatan = 'Koordinator Kampanye') {
    const container = document.getElementById('timsesRowsContainer');
    const row = document.createElement('div');
    row.className = 'd-flex gap-2 align-center';
    row.innerHTML = `
        <input type="text" name="timses_nama[]" class="input" placeholder="Nama Anggota" value="${nama.replace(/"/g, '&quot;')}" style="flex:1.2;">
        <input type="text" name="timses_nim[]" class="input mono" placeholder="NIM" value="${nim.replace(/"/g, '&quot;')}" style="flex:0.8;">
        <input type="text" name="timses_jabatan[]" class="input" placeholder="Jabatan" value="${jabatan.replace(/"/g, '&quot;')}" style="flex:1;">
        <button type="button" class="btn btn--soft-danger btn--xs" onclick="this.parentElement.remove()">×</button>
    `;
    container.appendChild(row);
}

function openCreateKandidatModal(kategori = 'BEM') {
    document.getElementById('kandidatModalTitle').textContent = 'Tambah Kandidat ' + kategori;
    document.getElementById('kand_sub_action').value = 'create';
    document.getElementById('kand_id').value = 0;
    document.getElementById('kand_kategori').value = kategori;
    document.getElementById('kand_urut').value = 1;
    document.getElementById('kand_nama_calon').value = '';
    document.getElementById('kand_nama_wakil').value = '';
    document.getElementById('kand_jurusan_kelas').value = '';
    document.getElementById('kand_visi').value = '';
    document.getElementById('kand_misi').value = '';
    document.getElementById('timsesRowsContainer').innerHTML = '';
    addTimsesRow('', '', 'Ketua Tim Sukses');
    toggleWakilField();
    PemiraApp.openModal('kandidatModal');
}

function openEditKandidatModal(k) {
    document.getElementById('kandidatModalTitle').textContent = 'Edit Kandidat ' + k.kategori + ' #0' + k.nomor_urut;
    document.getElementById('kand_sub_action').value = 'update';
    document.getElementById('kand_id').value = k.id;
    document.getElementById('kand_kategori').value = k.kategori;
    document.getElementById('kand_urut').value = k.nomor_urut;
    document.getElementById('kand_nama_calon').value = k.nama_calon || '';
    document.getElementById('kand_nama_wakil').value = k.nama_wakil || '';
    document.getElementById('kand_jurusan_kelas').value = k.jurusan_kelas || '';
    document.getElementById('kand_visi').value = k.visi || '';
    document.getElementById('kand_misi').value = k.misi || '';
    const container = document.getElementById('timsesRowsContainer');
    container.innerHTML = '';
    if (Array.isArray(k.tim_sukses) && k.tim_sukses.length > 0) {
        k.tim_sukses.forEach(ts => addTimsesRow(ts.nama_anggota || '', ts.nim_anggota || '', ts.jabatan_timses || ''));
    } else {
        addTimsesRow('', '', 'Ketua Tim Sukses');
    }
    toggleWakilField();
    PemiraApp.openModal('kandidatModal');
}
</script>

<?php require BASE_PATH . '/views/layouts/footer.php'; ?>
