/**
 * MAHASISWA_IMPORT.JS - Adminator 2026 Redesign CSV / Paste DPT Import Preview
 */
function fillSampleImport() {
    const rand = Math.floor(100 + Math.random() * 899);
    document.getElementById('rawImportText').value =
        `240101${rand}, Mahasiswa Baru ${rand}\n240101${rand + 1}, Mahasiswa Kedua ${rand + 1}`;
}

function previewImportBatch() {
    const rawText = document.getElementById('rawImportText')?.value || '';
    const fileInput = document.getElementById('importCsvFile');

    const formData = new FormData();
    formData.append('sub_action', 'preview_import_json');
    formData.append('csrf_token', window.PEMIRA_CONFIG.csrfToken);
    formData.append('raw_import_data', rawText);
    if (fileInput && fileInput.files[0]) {
        formData.append('csv_file', fileInput.files[0]);
    }

    fetch(`${window.PEMIRA_CONFIG.baseUrl}/index.php?action=mahasiswa`, {
        method: 'POST',
        body: formData
    })
        .then(res => res.json())
        .then(res => {
            if (!res.success) {
                PemiraApp.toast(res.message || 'Gagal memvalidasi data import.', 'danger');
                return;
            }
            const d = res.data || {};
            const rows = d.rows || [];
            const tbody = document.getElementById('importPreviewTbody');
            const hiddenBox = document.getElementById('importHiddenInputs');
            const badge = document.getElementById('importValidBadge');
            const previewBox = document.getElementById('importPreviewBox');
            const submitBtn = document.getElementById('btnConfirmImportSubmit');

            badge.style.display = 'inline-flex';
            badge.textContent = `${d.valid_count || 0} Valid · ${d.invalid_count || 0} Ditolak`;

            hiddenBox.innerHTML = '';
            tbody.innerHTML = rows.map(r => {
                if (r.is_valid) {
                    const iNim = document.createElement('input');
                    iNim.type = 'hidden';
                    iNim.name = 'import_nim[]';
                    iNim.value = r.nim;
                    const iNama = document.createElement('input');
                    iNama.type = 'hidden';
                    iNama.name = 'import_nama[]';
                    iNama.value = r.nama_lengkap;
                    hiddenBox.appendChild(iNim);
                    hiddenBox.appendChild(iNama);
                }
                return `
                    <tr class="data-row ${r.is_valid ? 'row-log-normal' : 'row-log-fraud'}">
                        <td><span class="data-cell-mono">${r.no}</span></td>
                        <td><span class="data-cell-mono fw-600">${r.nim}</span></td>
                        <td>${r.nama_lengkap}</td>
                        <td><span class="badge ${r.is_valid ? 'success' : 'danger'} dot">${r.catatan}</span></td>
                    </tr>
                `;
            }).join('');

            submitBtn.disabled = (d.valid_count || 0) < 1;
            previewBox.style.display = 'block';
        })
        .catch(() => {
            PemiraApp.toast('Kesalahan jaringan saat memvalidasi data.', 'danger');
        });
}
