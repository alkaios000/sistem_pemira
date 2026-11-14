/**
 * PORTAL_QR.JS - Adminator 2026 Redesign Real-Time Booth QR Polling & Management
 */
let activeBoothNumber = 1;
let lastRenderedToken = '';
let pollTimer = null;

function escHtml(str) {
    const d = document.createElement('div');
    d.textContent = str == null ? '' : String(str);
    return d.innerHTML;
}

function openCreateBilikModal() {
    const rows = document.querySelectorAll('#portalQrAllBiliksTbody tr[data-booth-num]');
    let maxNum = 0;
    rows.forEach(r => {
        const n = parseInt(r.getAttribute('data-booth-num'), 10) || 0;
        if (n > maxNum) maxNum = n;
    });
    const nextNum = maxNum + 1;
    document.getElementById('bilikModalTitle').textContent = 'Tambah Bilik Suara';
    document.getElementById('bilik_sub_action').value = 'create_bilik';
    document.getElementById('bilik_id').value = 0;
    document.getElementById('bilik_nomor').value = nextNum;
    document.getElementById('bilik_nama').value = 'Bilik Suara ' + (nextNum < 10 ? '0' + nextNum : nextNum);
    PemiraApp.openModal('bilikModal');
}

function openEditBilikModal(b) {
    document.getElementById('bilikModalTitle').textContent = 'Edit Bilik #0' + b.nomor_bilik;
    document.getElementById('bilik_sub_action').value = 'update_bilik';
    document.getElementById('bilik_id').value = b.id;
    document.getElementById('bilik_nomor').value = b.nomor_bilik;
    document.getElementById('bilik_nama').value = b.nama_bilik || '';
    PemiraApp.openModal('bilikModal');
}

function copyBoothKioskUrl(url, btnEl) {
    if (!url) return;
    const done = () => {
        if (!btnEl) return;
        const orig = btnEl.getAttribute('data-orig-label') || btnEl.textContent;
        btnEl.setAttribute('data-orig-label', orig);
        btnEl.textContent = 'Tersalin!';
        setTimeout(() => { btnEl.textContent = orig; }, 1600);
    };
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(url).then(done).catch(() => {
            window.prompt('Salin tautan aman layar bilik ini:', url);
        });
    } else {
        window.prompt('Salin tautan aman layar bilik ini:', url);
    }
}

function syncSelectedBoothKioskControls(boothNum) {
    const canWrite = document.getElementById('portalQrRoot')?.getAttribute('data-can-write') === '1';
    const openBtn = document.getElementById('btnOpenBoothKiosk');
    const copyBtn = document.getElementById('btnCopyBoothKiosk');
    const rotateInp = document.getElementById('rotateKioskBilikId');
    const numPad = '#0' + boothNum;

    if (canWrite && window.PEMIRA_KIOSK_LINKS && window.PEMIRA_KIOSK_LINKS[boothNum]) {
        const info = window.PEMIRA_KIOSK_LINKS[boothNum];
        if (openBtn) {
            openBtn.href = info.url;
            openBtn.textContent = `Buka Layar Laptop Bilik ${numPad} ↗`;
        }
        if (copyBtn) {
            copyBtn.setAttribute('data-kiosk-url', info.url);
        }
        if (rotateInp && info.id) {
            rotateInp.value = String(info.id);
        }
    } else if (openBtn) {
        const baseUrl = (window.PEMIRA_CONFIG && window.PEMIRA_CONFIG.baseUrl) ? window.PEMIRA_CONFIG.baseUrl : '';
        openBtn.href = `${baseUrl}/index.php?page=layar_bilik&booth=${encodeURIComponent(boothNum)}`;
        openBtn.textContent = `Pratinjau Layar Bilik ${numPad} ↗`;
    }
}

function changeActiveBooth(boothNum) {
    activeBoothNumber = parseInt(boothNum, 10) || 1;
    lastRenderedToken = '';
    const sel = document.getElementById('portalBoothSelect');
    if (sel) sel.value = String(activeBoothNumber);

    syncSelectedBoothKioskControls(activeBoothNumber);

    const url = new URL(window.location.href);
    url.searchParams.set('booth', activeBoothNumber);
    window.history.replaceState({}, '', url.toString());

    pollBoothStatus(false);
}

function forceRotateBoothToken() {
    const root = document.getElementById('portalQrRoot');
    const canWrite = root && (root.getAttribute('data-can-write') === '1' || root.getAttribute('data-is-admin') === '1');
    if (!canWrite) return;

    const csrf = (window.PEMIRA_CONFIG && window.PEMIRA_CONFIG.csrfToken) ? window.PEMIRA_CONFIG.csrfToken : '';
    const url = `${window.PEMIRA_CONFIG.baseUrl}/index.php?action=voting&sub_action=force_rotate_qr`;
    const body = new URLSearchParams({
        booth: String(activeBoothNumber),
        csrf_token: csrf
    });

    fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',
            'X-CSRF-Token': csrf,
            'Cache-Control': 'no-cache'
        },
        body: body.toString()
    })
        .then(res => res.ok ? res.json() : null)
        .then(res => {
            if (!res || !res.success) return;
            lastRenderedToken = '';
            pollBoothStatus();
            if (window.PemiraApp && typeof window.PemiraApp.broadcastSync === 'function') {
                window.PemiraApp.broadcastSync('qr_rotate');
            }
        })
        .catch(() => {});
}

function renderAllBiliksTable(allBiliks, globalPortal, wajibCheckin) {
    if (!Array.isArray(allBiliks)) return;
    const root = document.getElementById('portalQrRoot');
    const canWrite = root && (root.getAttribute('data-can-write') === '1' || root.getAttribute('data-is-admin') === '1');
    const canViewLogs = root && root.getAttribute('data-can-view-logs') === '1';
    const tbody = document.getElementById('portalQrAllBiliksTbody');
    const sel = document.getElementById('portalBoothSelect');

    // Sync top global controls
    const inpWajib = document.getElementById('inputWajibCheckin');
    const btnWajib = document.getElementById('btnWajibCheckinLabel');
    if (inpWajib && btnWajib && wajibCheckin !== undefined) {
        const isWajib = String(wajibCheckin) === '1';
        inpWajib.value = isWajib ? '0' : '1';
        btnWajib.textContent = 'Check-In Meja: ' + (isWajib ? 'Wajib' : 'Bebas');
    }

    const inpPortal = document.getElementById('inputGlobalPortal');
    const btnPortal = document.getElementById('btnGlobalPortalLabel');
    if (inpPortal && btnPortal && globalPortal) {
        const isBuka = globalPortal === 'BUKA';
        inpPortal.value = isBuka ? 'TUTUP' : 'BUKA';
        btnPortal.className = 'btn ' + (isBuka ? 'btn--soft-danger' : 'btn--soft-success') + ' btn--sm';
        btnPortal.textContent = isBuka ? 'Tutup Semua Bilik (Global)' : 'Buka Semua Bilik (Global)';
    }

    if (sel) {
        const currentOpts = Array.from(sel.options).map(o => `${o.value}:${o.textContent.trim()}`).join('|');
        const nextOpts = allBiliks.map(b => `${b.nomor_bilik}:Bilik #0${b.nomor_bilik} · ${b.nama_bilik}`).join('|');
        if (currentOpts !== nextOpts) {
            sel.innerHTML = allBiliks.map(b =>
                `<option value="${b.nomor_bilik}" ${Number(b.nomor_bilik) === Number(activeBoothNumber) ? 'selected' : ''}>Bilik #0${b.nomor_bilik} · ${escHtml(b.nama_bilik)}</option>`
            ).join('');
        }
    }

    // Update summary badges
    let idleCount = 0, inUseCount = 0, closedCount = 0;
    allBiliks.forEach(b => {
        const st = (globalPortal && globalPortal !== 'BUKA') ? 'CLOSED' : b.status_bilik;
        if (st === 'IDLE') idleCount++;
        else if (st === 'IN_USE') inUseCount++;
        else closedCount++;
    });

    const elTotal = document.getElementById('qrSummaryTotal');
    const elIdle = document.getElementById('qrSummaryIdle');
    const elInUse = document.getElementById('qrSummaryInUse');
    const elClosed = document.getElementById('qrSummaryClosed');
    if (elTotal) elTotal.textContent = `Total: ${allBiliks.length}`;
    if (elIdle) elIdle.textContent = `IDLE: ${idleCount}`;
    if (elInUse) elInUse.textContent = `IN_USE: ${inUseCount}`;
    if (elClosed) elClosed.textContent = `CLOSED: ${closedCount}`;

    if (!tbody) return;
    const actionUrl = `${window.PEMIRA_CONFIG.baseUrl}/index.php?action=bilik`;
    const csrf = window.PEMIRA_CONFIG.csrfToken || '';

    tbody.innerHTML = allBiliks.map(b => {
        const st = (globalPortal && globalPortal !== 'BUKA') ? 'CLOSED' : b.status_bilik;
        let badgeHtml = '<span class="badge success dot">IDLE</span>';
        if (st === 'CLOSED') badgeHtml = '<span class="badge danger dot">CLOSED</span>';
        else if (st === 'IN_USE') badgeHtml = '<span class="badge primary dot">IN_USE</span>';

        const isSel = Number(b.nomor_bilik) === Number(activeBoothNumber);
        const tokShort = b.token_hint ? String(b.token_hint).toUpperCase() + '...' : (b.current_token ? String(b.current_token).substring(0, 8).toUpperCase() + '...' : '-');
        let subTokenHtml = '<div class="text-muted mono" style="font-size:10.5px;">Rotasi Otomatis 8s</div>';
        if (st === 'IN_USE') {
            const remLock = Number(b.remaining_lock_seconds || 0);
            const nimStr = b.active_nim_masked ? escHtml(b.active_nim_masked) : (b.active_nim ? escHtml(b.active_nim) : 'Verifikasi...');
            subTokenHtml = `<div class="mono" style="font-size:10.5px;color:var(--primary);">NIM: ${nimStr} · ${remLock}s</div>`;
        } else if (st === 'CLOSED') {
            subTokenHtml = '<div class="text-muted mono" style="font-size:10.5px;">Token Dijeda</div>';
        }

        const fraudCount = Number(b.total_fraud_bilik || 0);
        const fraudBadge = (canViewLogs && fraudCount > 0) ? `<span class="badge danger">${fraudCount} Fraud</span>` : '';

        let adminCell = '';
        if (canWrite) {
            const bEditPayload = JSON.stringify({
                id: b.id,
                nomor_bilik: b.nomor_bilik,
                nama_bilik: b.nama_bilik
            });
            const isRowClosed = b.status_bilik === 'CLOSED';
            const kioskEntry = (window.PEMIRA_KIOSK_LINKS && window.PEMIRA_KIOSK_LINKS[b.nomor_bilik]) ? window.PEMIRA_KIOSK_LINKS[b.nomor_bilik] : null;
            const rowKioskUrl = kioskEntry && kioskEntry.url
                ? kioskEntry.url
                : `${window.PEMIRA_CONFIG.baseUrl}/index.php?page=layar_bilik&booth=${encodeURIComponent(b.nomor_bilik)}`;
            adminCell = `
                <td class="text-right">
                    <div class="data-cell-actions">
                        <a href="${escHtml(rowKioskUrl)}" target="_blank" rel="noopener noreferrer" class="btn btn--soft-primary btn--xs" title="Buka layar kiosk khusus bilik ini">Layar ↗</a>
                        <button type="button" class="btn btn--ghost btn--xs btn-copy-kiosk" data-kiosk-url="${escHtml(rowKioskUrl)}" title="Salin tautan kiosk aman tanpa login admin">Salin Link</button>
                        <form action="${actionUrl}" method="POST" data-confirm="Reset token QR dan lepas kunci sesi bilik ini?">
                            <input type="hidden" name="csrf_token" value="${escHtml(csrf)}">
                            <input type="hidden" name="sub_action" value="force_reset_bilik">
                            <input type="hidden" name="id" value="${b.id}">
                            <button type="submit" class="btn ${st === 'IN_USE' ? 'btn--soft-warning' : 'btn--ghost'} btn--xs">
                                ${st === 'IN_USE' ? 'Lepas Kunci' : 'Reset'}
                            </button>
                        </form>
                        <form action="${actionUrl}" method="POST">
                            <input type="hidden" name="csrf_token" value="${escHtml(csrf)}">
                            <input type="hidden" name="sub_action" value="toggle_bilik_status">
                            <input type="hidden" name="id" value="${b.id}">
                            <input type="hidden" name="target_status" value="${isRowClosed ? 'IDLE' : 'CLOSED'}">
                            <button type="submit" class="btn btn--ghost btn--xs">
                                ${isRowClosed ? 'Buka' : 'Tutup'}
                            </button>
                        </form>
                        <button type="button" class="btn btn--ghost btn--xs btn-edit-bilik" data-bilik-edit="${escHtml(bEditPayload)}">Edit</button>
                        <form action="${actionUrl}" method="POST" data-confirm="Hapus bilik suara ini?">
                            <input type="hidden" name="csrf_token" value="${escHtml(csrf)}">
                            <input type="hidden" name="sub_action" value="delete_bilik">
                            <input type="hidden" name="id" value="${b.id}">
                            <button type="submit" class="btn btn--soft-danger btn--xs" title="Hapus Bilik">×</button>
                        </form>
                    </div>
                </td>
            `;
        }

        return `
            <tr class="data-row ${isSel ? 'is-selected' : ''}" data-booth-num="${b.nomor_bilik}" style="cursor:pointer;" title="Klik baris untuk menampilkan QR bilik ini">
                <td><span class="data-cell-mono fw-600">#0${b.nomor_bilik}</span></td>
                <td>
                    <div class="fw-600">${escHtml(b.nama_bilik)}</div>
                </td>
                <td>${badgeHtml}</td>
                <td>
                    <div class="data-cell-mono fw-600">${escHtml(tokShort)}</div>
                    ${subTokenHtml}
                </td>
                <td>
                    <div class="d-flex align-center gap-2">
                        <span class="data-cell-mono fw-600">${Number(b.total_suara_bilik || 0).toLocaleString('id-ID')} suara</span>
                        ${fraudBadge}
                    </div>
                </td>
                ${adminCell}
            </tr>
        `;
    }).join('');
}

function pollBoothStatus() {
    const url = `${window.PEMIRA_CONFIG.baseUrl}/index.php?action=voting&sub_action=poll_portal_qr&booth=${encodeURIComponent(activeBoothNumber)}`;

    fetch(url, { headers: { 'Cache-Control': 'no-cache' } })
        .then(res => res.ok ? res.json() : null)
        .then(res => {
            if (!res || !res.success || !res.data) return;
            const d = res.data;

            if (Number(d.nomor_bilik) !== Number(activeBoothNumber)) {
                activeBoothNumber = Number(d.nomor_bilik);
                lastRenderedToken = '';
            }

            if (Array.isArray(d.all_biliks)) {
                renderAllBiliksTable(d.all_biliks, d.global_portal, d.wajib_checkin);
            }

            document.getElementById('qrBoothEyebrow').textContent = `BILIK #0${d.nomor_bilik} · ${d.total_suara_bilik} SUARA`;
            document.getElementById('qrBoothTitle').textContent = d.nama_bilik;

            const badge = document.getElementById('qrBoothStatusBadge');
            const stateIdle = document.getElementById('qrStateIdle');
            const stateInUse = document.getElementById('qrStateInUse');
            const stateClosed = document.getElementById('qrStateClosed');

            if (d.status_bilik === 'IDLE') {
                badge.className = 'badge success dot';
                badge.textContent = 'IDLE · SIAP SCAN';
                stateIdle.style.display = 'block';
                stateInUse.style.display = 'none';
                stateClosed.style.display = 'none';

                document.getElementById('qrRemainingSec').textContent = `${d.remaining_qr_seconds}s`;
                document.getElementById('qrTokenShort').textContent = (d.qr_token || '').substring(0, 16) + '...';
                const simLink = document.getElementById('qrDirectSimulateLink');
                if (simLink && d.scan_url) simLink.href = d.scan_url;

                if (d.qr_token && d.qr_token !== lastRenderedToken) {
                    lastRenderedToken = d.qr_token;
                    const box = document.getElementById('qrCodeCanvas');
                    if (box && typeof QRCode !== 'undefined') {
                        box.innerHTML = '';
                        new QRCode(box, {
                            text: d.scan_url || d.qr_token,
                            width: 196,
                            height: 196,
                            colorDark: '#0f172a',
                            colorLight: '#ffffff',
                            correctLevel: QRCode.CorrectLevel.M
                        });
                    }
                }
            } else if (d.status_bilik === 'IN_USE') {
                lastRenderedToken = '';
                badge.className = 'badge primary dot';
                badge.textContent = 'IN_USE · TERKUNCI';
                stateIdle.style.display = 'none';
                stateInUse.style.display = 'block';
                stateClosed.style.display = 'none';

                document.getElementById('qrActiveNimMasked').textContent = d.active_nim_masked || 'Sedang verifikasi NIM...';
                document.getElementById('qrLockRemainingSec').textContent = `${d.remaining_lock_seconds}s`;
            } else {
                lastRenderedToken = '';
                badge.className = 'badge danger dot';
                badge.textContent = 'CLOSED';
                stateIdle.style.display = 'none';
                stateInUse.style.display = 'none';
                stateClosed.style.display = 'block';
            }
        })
        .catch(() => {});
}

document.addEventListener('DOMContentLoaded', () => {
    const root = document.getElementById('portalQrRoot');
    if (!root) return;
    activeBoothNumber = parseInt(root.getAttribute('data-active-booth'), 10) || 1;

    const btnCopyMain = document.getElementById('btnCopyBoothKiosk');
    if (btnCopyMain) {
        btnCopyMain.addEventListener('click', () => {
            copyBoothKioskUrl(btnCopyMain.getAttribute('data-kiosk-url') || '', btnCopyMain);
        });
    }

    const tbody = document.getElementById('portalQrAllBiliksTbody');
    if (tbody) {
        tbody.addEventListener('click', e => {
            const copyBtn = e.target.closest('.btn-copy-kiosk[data-kiosk-url]');
            if (copyBtn) {
                copyBoothKioskUrl(copyBtn.getAttribute('data-kiosk-url') || '', copyBtn);
                return;
            }
            const editBtn = e.target.closest('.btn-edit-bilik[data-bilik-edit]');
            if (editBtn) {
                try {
                    const parsed = JSON.parse(editBtn.getAttribute('data-bilik-edit') || '{}');
                    openEditBilikModal(parsed);
                } catch (err) {}
                return;
            }
            if (e.target.closest('.data-cell-actions, button, form, a, input')) return;
            const tr = e.target.closest('tr[data-booth-num]');
            if (!tr) return;
            const num = parseInt(tr.getAttribute('data-booth-num'), 10);
            if (num > 0) changeActiveBooth(num);
        });
    }

    pollBoothStatus(false);
    pollTimer = setInterval(() => pollBoothStatus(false), 1000);
    window.addEventListener('pemira:realtime-sync', () => pollBoothStatus(false));
});
