/**
 * PORTAL_QR.JS - Adminator 2026 Redesign Real-Time Booth QR Polling
 */
let activeBoothNumber = 1;
let lastRenderedToken = '';
let pollTimer = null;

function changeActiveBooth(boothNum) {
    window.location.href = `${window.PEMIRA_CONFIG.baseUrl}/index.php?page=portal_qr&booth=${encodeURIComponent(boothNum)}`;
}

function forceRotateBoothToken() {
    pollBoothStatus(true);
}

function pollBoothStatus(force = false) {
    const url = `${window.PEMIRA_CONFIG.baseUrl}/index.php?action=voting&sub_action=poll_portal_qr&booth=${encodeURIComponent(activeBoothNumber)}${force ? '&force=1' : ''}`;

    fetch(url)
        .then(res => res.json())
        .then(res => {
            if (!res.success || !res.data) return;
            const d = res.data;

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
});
