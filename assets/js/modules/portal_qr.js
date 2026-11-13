/**
 * PORTAL_QR.JS - Adminator 2026 Redesign Real-Time Booth QR Polling
 */
let activeBoothNumber = 1;
let lastRenderedToken = '';
let pollTimer = null;

function changeActiveBooth(boothNum) {
    activeBoothNumber = parseInt(boothNum, 10) || 1;
    lastRenderedToken = '';
    const sel = document.getElementById('portalBoothSelect');
    if (sel) sel.value = String(activeBoothNumber);

    const url = new URL(window.location.href);
    url.searchParams.set('booth', activeBoothNumber);
    window.history.replaceState({}, '', url.toString());

    pollBoothStatus(false);
}

function forceRotateBoothToken() {
    pollBoothStatus(true);
    if (window.PemiraApp && typeof window.PemiraApp.broadcastSync === 'function') {
        window.PemiraApp.broadcastSync('qr_rotate');
    }
}

function renderAllBiliksTable(allBiliks, globalPortal) {
    if (!Array.isArray(allBiliks)) return;
    const tbody = document.getElementById('portalQrAllBiliksTbody');
    const sel = document.getElementById('portalBoothSelect');

    if (sel) {
        const currentOpts = Array.from(sel.options).map(o => `${o.value}:${o.textContent.trim()}`).join('|');
        const nextOpts = allBiliks.map(b => `${b.nomor_bilik}:Bilik #0${b.nomor_bilik} · ${b.nama_bilik}`).join('|');
        if (currentOpts !== nextOpts) {
            sel.innerHTML = allBiliks.map(b =>
                `<option value="${b.nomor_bilik}" ${Number(b.nomor_bilik) === Number(activeBoothNumber) ? 'selected' : ''}>Bilik #0${b.nomor_bilik} · ${b.nama_bilik}</option>`
            ).join('');
        }
    }

    if (!tbody) return;
    tbody.innerHTML = allBiliks.map(b => {
        const st = (globalPortal && globalPortal !== 'BUKA') ? 'CLOSED' : b.status_bilik;
        let badgeHtml = '<span class="badge success dot">IDLE</span>';
        if (st === 'CLOSED') badgeHtml = '<span class="badge danger dot">CLOSED</span>';
        else if (st === 'IN_USE') badgeHtml = '<span class="badge primary dot">IN_USE</span>';

        const isSel = Number(b.nomor_bilik) === Number(activeBoothNumber);
        const divNama = document.createElement('div');
        divNama.textContent = b.nama_bilik || '';
        const divLokasi = document.createElement('div');
        divLokasi.textContent = b.lokasi_bilik || '-';

        return `
            <tr class="data-row ${isSel ? 'is-selected' : ''}" data-booth-num="${b.nomor_bilik}">
                <td><span class="data-cell-mono fw-600">#0${b.nomor_bilik}</span></td>
                <td>
                    <div class="fw-600">${divNama.innerHTML}</div>
                    <div class="text-muted" style="font-size:11px;">${divLokasi.innerHTML}</div>
                </td>
                <td>${badgeHtml}</td>
                <td><span class="data-cell-mono fw-600">${Number(b.total_suara_bilik || 0).toLocaleString('id-ID')}</span></td>
                <td class="text-right">
                    <button type="button" class="btn ${isSel ? 'btn--soft-primary' : 'btn--ghost'} btn--xs" onclick="changeActiveBooth(${b.nomor_bilik})">Tampilkan QR</button>
                </td>
            </tr>
        `;
    }).join('');
}

function pollBoothStatus(force = false) {
    const url = `${window.PEMIRA_CONFIG.baseUrl}/index.php?action=voting&sub_action=poll_portal_qr&booth=${encodeURIComponent(activeBoothNumber)}${force ? '&force=1' : ''}`;

    fetch(url, { headers: { 'Cache-Control': 'no-cache' } })
        .then(res => res.json())
        .then(res => {
            if (!res.success || !res.data) return;
            const d = res.data;

            if (Array.isArray(d.all_biliks)) {
                renderAllBiliksTable(d.all_biliks, d.global_portal);
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
    pollBoothStatus(false);
    pollTimer = setInterval(() => pollBoothStatus(false), 1000);
    window.addEventListener('pemira:realtime-sync', () => pollBoothStatus(false));
});
