/**
 * LAYAR_BILIK.JS - Kiosk Per Bilik Terisolasi & Aman
 */
document.addEventListener('DOMContentLoaded', () => {
    const root = document.getElementById('layarBilikRoot');
    if (!root) return;

    const boothNum = parseInt(root.getAttribute('data-booth') || '0', 10);
    if (!boothNum) return;

    const connBadge = document.getElementById('kioskConnBadge');
    const statusBadge = document.getElementById('kioskStatusBadge');
    const stateIdle = document.getElementById('kioskStateIdle');
    const stateInUse = document.getElementById('kioskStateInUse');
    const stateClosed = document.getElementById('kioskStateClosed');
    const stateRevoked = document.getElementById('kioskStateRevoked');
    const qrCanvas = document.getElementById('kioskQrCanvas');
    const timerPill = document.getElementById('kioskTimerPill');
    const remainingEl = document.getElementById('kioskRemainingSec');
    const progressBar = document.getElementById('kioskProgressBar');
    const lockRemainingEl = document.getElementById('kioskLockRemainingSec');
    const revokedMsgEl = document.getElementById('kioskRevokedMessage');
    const btnFs = document.getElementById('btnKioskFullscreen');

    let lastToken = '';
    let pollTimer = null;
    let revoked = false;

    function clearQrDom() {
        lastToken = '';
        if (qrCanvas) {
            qrCanvas.innerHTML = '';
        }
    }

    function showOnlyState(targetEl) {
        [stateIdle, stateInUse, stateClosed, stateRevoked].forEach(el => {
            if (el) el.style.display = (el === targetEl) ? '' : 'none';
        });
    }

    function setConnectionStatus(online) {
        if (!connBadge) return;
        connBadge.className = online ? 'badge success dot' : 'badge warn dot';
        connBadge.textContent = online ? 'SINKRON REAL-TIME' : 'MENYAMBUNG ULANG...';
    }

    function handleRevoked(msg) {
        revoked = true;
        clearQrDom();
        if (pollTimer) {
            clearInterval(pollTimer);
            pollTimer = null;
        }
        if (statusBadge) {
            statusBadge.className = 'badge danger dot';
            statusBadge.textContent = 'TERKUNCI';
        }
        if (connBadge) {
            connBadge.className = 'badge danger dot';
            connBadge.textContent = 'SESI DICABUT';
        }
        if (revokedMsgEl && msg) {
            revokedMsgEl.textContent = msg;
        }
        showOnlyState(stateRevoked);
    }

    async function pollKioskQr() {
        if (revoked) return;
        const baseUrl = (window.PEMIRA_CONFIG && window.PEMIRA_CONFIG.baseUrl) ? window.PEMIRA_CONFIG.baseUrl : '';
        const url = `${baseUrl}/index.php?action=voting&sub_action=poll_kiosk_qr&booth=${encodeURIComponent(boothNum)}`;

        try {
            const res = await fetch(url, {
                credentials: 'same-origin',
                headers: { 'Cache-Control': 'no-cache' }
            });
            const payload = await res.json().catch(() => null);

            if (res.status === 403 || res.status === 401 || res.status === 404 || (payload && payload.data && payload.data.revoked)) {
                handleRevoked((payload && payload.message) ? payload.message : 'Sesi layar bilik tidak sah atau kunci akses telah diputar.');
                return;
            }

            if (!res.ok || !payload || !payload.success || !payload.data) {
                setConnectionStatus(false);
                return;
            }

            setConnectionStatus(true);
            const d = payload.data;

            if (d.status_bilik === 'IDLE') {
                if (statusBadge) {
                    statusBadge.className = 'badge success dot';
                    statusBadge.textContent = 'IDLE · SIAP SCAN';
                }
                showOnlyState(stateIdle);

                const rem = Number(d.remaining_qr_seconds || 0);
                const ttl = Math.max(1, Number(d.qr_ttl_seconds || 8));
                if (remainingEl) remainingEl.textContent = `${rem}s`;
                if (timerPill) timerPill.classList.toggle('expiring', rem <= 2);
                if (progressBar) {
                    const pct = Math.max(0, Math.min(100, Math.round((rem / ttl) * 100)));
                    progressBar.style.width = `${pct}%`;
                }

                if (d.qr_token && d.qr_token !== lastToken) {
                    lastToken = d.qr_token;
                    if (qrCanvas && typeof QRCode !== 'undefined') {
                        qrCanvas.innerHTML = '';
                        new QRCode(qrCanvas, {
                            text: d.scan_url || d.qr_token,
                            width: 268,
                            height: 268,
                            colorDark: '#0f172a',
                            colorLight: '#ffffff',
                            correctLevel: QRCode.CorrectLevel.M
                        });
                    }
                }
            } else if (d.status_bilik === 'IN_USE') {
                clearQrDom();
                if (statusBadge) {
                    statusBadge.className = 'badge primary dot';
                    statusBadge.textContent = 'IN_USE · TERKUNCI';
                }
                if (lockRemainingEl) {
                    lockRemainingEl.textContent = `${Number(d.remaining_lock_seconds || 0)}s`;
                }
                showOnlyState(stateInUse);
            } else {
                clearQrDom();
                if (statusBadge) {
                    statusBadge.className = 'badge danger dot';
                    statusBadge.textContent = 'CLOSED';
                }
                showOnlyState(stateClosed);
            }
        } catch (err) {
            setConnectionStatus(false);
        }
    }

    if (btnFs) {
        const updateFsLabel = () => {
            btnFs.textContent = document.fullscreenElement ? 'Keluar Layar Penuh' : 'Layar Penuh';
        };
        btnFs.addEventListener('click', () => {
            if (!document.fullscreenElement) {
                document.documentElement.requestFullscreen().catch(() => {});
            } else {
                document.exitFullscreen().catch(() => {});
            }
        });
        document.addEventListener('fullscreenchange', updateFsLabel);
    }

    pollKioskQr();
    pollTimer = setInterval(pollKioskQr, 1000);
    window.addEventListener('pemira:realtime-sync', pollKioskQr);
});
