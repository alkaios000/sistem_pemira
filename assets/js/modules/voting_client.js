/**
 * VOTING_CLIENT.JS - Adminator 2026 Redesign Bilik Pemilihan Client Engine
 * Mendukung pemindai kamera QR (html5-qrcode), pemulihan sesi otomatis saat refresh, dan transaksi atomik.
 */
let html5QrScanner = null;
let votingState = {
    bilikId: 0,
    nomorBilik: 1,
    sessionToken: '',
    deviceId: '',
    fingerprintHash: '',
    verifiedNim: '',
    bemId: 0,
    bemLabel: '',
    bpmId: 0,
    bpmLabel: ''
};

function getOrCreateDeviceIdentity() {
    let devId = localStorage.getItem('pemira_device_id');
    if (!devId) {
        devId = 'dev_' + Math.random().toString(36).substring(2, 12) + '_' + Date.now().toString(36);
        localStorage.setItem('pemira_device_id', devId);
    }
    const rawFp = [
        navigator.userAgent,
        screen.width + 'x' + screen.height,
        navigator.language || 'id',
        Intl.DateTimeFormat().resolvedOptions().timeZone || 'Asia/Jakarta'
    ].join('|');
    let hash = 0;
    for (let i = 0; i < rawFp.length; i++) {
        hash = ((hash << 5) - hash) + rawFp.charCodeAt(i);
        hash |= 0;
    }
    const fpHash = 'fp_' + Math.abs(hash).toString(16);
    votingState.deviceId = devId;
    votingState.fingerprintHash = fpHash;
}

function showVotingStep(step) {
    document.getElementById('panelStep1').style.display = (step === 1) ? 'flex' : 'none';
    document.getElementById('panelStep2').style.display = (step === 2) ? 'flex' : 'none';
    document.getElementById('panelStep3').style.display = (step === 3) ? 'block' : 'none';
    const p4 = document.getElementById('panelStep4');
    if (p4) p4.style.display = (step === 4) ? 'flex' : 'none';

    const p1 = document.getElementById('stepPill1');
    const p2 = document.getElementById('stepPill2');
    const p3 = document.getElementById('stepPill3');

    p1.className = 'step-pill ' + (step === 1 ? 'is-active' : 'is-done');
    p2.className = 'step-pill ' + (step === 2 ? 'is-active' : (step > 2 ? 'is-done' : ''));
    p3.className = 'step-pill ' + (step === 3 ? 'is-active' : (step > 3 ? 'is-done' : ''));
}

function startQrCamera() {
    if (typeof Html5Qrcode === 'undefined') {
        PemiraApp.toast('Pustaka kamera QR tidak tersedia. Gunakan link atau input token.', 'warning');
        return;
    }
    const readerEl = document.getElementById('qrCameraReader');
    if (!readerEl) return;
    readerEl.style.display = 'flex';
    readerEl.innerHTML = '';

    html5QrScanner = new Html5Qrcode('qrCameraReader');
    html5QrScanner.start(
        { facingMode: 'environment' },
        { fps: 10, qrbox: { width: 200, height: 200 } },
        decodedText => {
            stopQrCamera();
            handleDecodedQrText(decodedText);
        },
        () => {}
    ).then(() => {
        document.getElementById('btnStartQrCamera').style.display = 'none';
        document.getElementById('btnStopQrCamera').style.display = 'inline-flex';
    }).catch(() => {
        readerEl.style.display = 'none';
        PemiraApp.toast('Kamera tidak dapat diakses. Gunakan link scan atau tempel token.', 'warning');
    });
}

function stopQrCamera() {
    if (html5QrScanner) {
        html5QrScanner.stop().then(() => {
            html5QrScanner.clear();
            html5QrScanner = null;
        }).catch(() => {});
    }
    const readerEl = document.getElementById('qrCameraReader');
    if (readerEl) readerEl.style.display = 'none';
    const btnStart = document.getElementById('btnStartQrCamera');
    const btnStop = document.getElementById('btnStopQrCamera');
    if (btnStart) btnStart.style.display = 'inline-flex';
    if (btnStop) btnStop.style.display = 'none';
}

function handleDecodedQrText(text) {
    try {
        const url = new URL(text);
        const boothParam = url.searchParams.get('booth');
        const tokenParam = url.searchParams.get('token');
        if (boothParam) {
            const sel = document.getElementById('claimBoothSelect');
            if (sel) sel.value = boothParam;
        }
        if (tokenParam) {
            document.getElementById('claimTokenInput').value = tokenParam;
            submitClaimQr();
            return;
        }
    } catch (_) {
        // Bukan URL, anggap string token langsung
    }
    document.getElementById('claimTokenInput').value = text.trim();
    submitClaimQr();
}

function submitClaimQr() {
    const booth = document.getElementById('claimBoothSelect')?.value || 1;
    const token = (document.getElementById('claimTokenInput')?.value || '').trim();

    if (!token) {
        PemiraApp.toast('Masukkan atau pindai token QR bilik terlebih dahulu.', 'warning');
        return;
    }

    const formData = new FormData();
    formData.append('sub_action', 'claim_qr_scan');
    formData.append('csrf_token', window.PEMIRA_CONFIG.csrfToken);
    formData.append('nomor_bilik', booth);
    formData.append('qr_token', token);
    formData.append('device_id', votingState.deviceId);
    formData.append('fingerprint_hash', votingState.fingerprintHash);

    fetch(`${window.PEMIRA_CONFIG.baseUrl}/index.php?action=voting`, {
        method: 'POST',
        body: formData
    })
        .then(res => res.json())
        .then(res => {
            if (!res.success) {
                PemiraApp.toast(res.message || 'Gagal mengklaim QR Code bilik.', 'danger');
                return;
            }
            const d = res.data;
            votingState.bilikId = d.bilik_id;
            votingState.nomorBilik = d.nomor_bilik;
            votingState.sessionToken = d.voting_session_token;
            votingState.deviceId = d.device_id || votingState.deviceId;
            document.getElementById('lockedBoothLabel').textContent = d.nama_bilik || `Bilik #0${d.nomor_bilik}`;
            PemiraApp.toast(res.message, 'success');
            showVotingStep(2);
            document.getElementById('verifyNimInput')?.focus();
        })
        .catch(() => {
            PemiraApp.toast('Kesalahan jaringan saat mengklaim QR bilik.', 'danger');
        });
}

function submitVerifyNim() {
    const nim = (document.getElementById('verifyNimInput')?.value || '').trim();
    if (!nim) {
        PemiraApp.toast('Masukkan NIM Anda terlebih dahulu.', 'warning');
        return;
    }

    const formData = new FormData();
    formData.append('sub_action', 'verify_nim');
    formData.append('csrf_token', window.PEMIRA_CONFIG.csrfToken);
    formData.append('bilik_id', votingState.bilikId);
    formData.append('voting_session_token', votingState.sessionToken);
    formData.append('device_id', votingState.deviceId);
    formData.append('fingerprint_hash', votingState.fingerprintHash);
    formData.append('nim', nim);

    fetch(`${window.PEMIRA_CONFIG.baseUrl}/index.php?action=voting`, {
        method: 'POST',
        body: formData
    })
        .then(res => res.json())
        .then(res => {
            if (!res.success) {
                PemiraApp.toast(res.message || 'Verifikasi NIM gagal.', 'danger');
                if (res.data && res.data.session_dead) {
                    setTimeout(() => {
                        window.location.href = `${window.PEMIRA_CONFIG.baseUrl}/index.php?page=bilik_pemilihan`;
                    }, 1500);
                }
                return;
            }
            const m = res.data.mahasiswa;
            votingState.verifiedNim = m.nim;
            document.getElementById('voterInfoName').textContent = m.nama_lengkap;
            document.getElementById('voterInfoSub').textContent = `NIM ${m.nim} · Kelas ${m.nama_kelas} (${m.jurusan})`;
            PemiraApp.toast(res.message, 'success');
            showVotingStep(3);
        })
        .catch(() => {
            PemiraApp.toast('Kesalahan jaringan saat memverifikasi NIM.', 'danger');
        });
}

function selectBallotCard(kategori, id, cardEl) {
    if (kategori === 'BEM') {
        votingState.bemId = id;
        votingState.bemLabel = cardEl.getAttribute('data-bem-label') || '';
        document.querySelectorAll('[data-bem-id]').forEach(el => {
            el.classList.remove('is-selected');
            const st = el.querySelector('.select-status-text');
            if (st) st.textContent = 'Klik untuk memilih';
        });
    } else {
        votingState.bpmId = id;
        votingState.bpmLabel = cardEl.getAttribute('data-bpm-label') || '';
        document.querySelectorAll('[data-bpm-id]').forEach(el => {
            el.classList.remove('is-selected');
            const st = el.querySelector('.select-status-text');
            if (st) st.textContent = 'Klik untuk memilih';
        });
    }

    cardEl.classList.add('is-selected');
    const activeText = cardEl.querySelector('.select-status-text');
    if (activeText) activeText.textContent = 'Terpilih';

    const btnConfirm = document.getElementById('btnOpenConfirmVote');
    if (btnConfirm) {
        btnConfirm.disabled = !(votingState.bemId > 0 && votingState.bpmId > 0);
    }
}

function openConfirmVoteModal() {
    if (!votingState.bemId || !votingState.bpmId) {
        PemiraApp.toast('Pilih 1 kandidat BEM dan 1 kandidat BPM terlebih dahulu.', 'warning');
        return;
    }
    document.getElementById('confirmBemLabel').textContent = votingState.bemLabel;
    document.getElementById('confirmBpmLabel').textContent = votingState.bpmLabel;
    PemiraApp.openModal('confirmVoteModal');
}

function submitConfirmVote() {
    const btn = document.getElementById('btnSubmitAtomicVote');
    if (btn) {
        btn.disabled = true;
        btn.textContent = 'Menyimpan...';
    }

    const formData = new FormData();
    formData.append('sub_action', 'confirm_vote');
    formData.append('csrf_token', window.PEMIRA_CONFIG.csrfToken);
    formData.append('bilik_id', votingState.bilikId);
    formData.append('voting_session_token', votingState.sessionToken);
    formData.append('device_id', votingState.deviceId);
    formData.append('fingerprint_hash', votingState.fingerprintHash);
    formData.append('nim', votingState.verifiedNim);
    formData.append('kandidat_bem_id', votingState.bemId);
    formData.append('kandidat_bpm_id', votingState.bpmId);

    fetch(`${window.PEMIRA_CONFIG.baseUrl}/index.php?action=voting`, {
        method: 'POST',
        body: formData
    })
        .then(res => res.json())
        .then(res => {
            if (!res.success) {
                PemiraApp.toast(res.message || 'Gagal menyimpan suara.', 'danger');
                if (btn) {
                    btn.disabled = false;
                    btn.textContent = 'Kirim Suara Sah';
                }
                return;
            }
            PemiraApp.closeModal('confirmVoteModal');
            PemiraApp.toast(res.message, 'success');
            showVotingStep(4);
        })
        .catch(() => {
            PemiraApp.toast('Kesalahan jaringan saat menyimpan suara.', 'danger');
            if (btn) {
                btn.disabled = false;
                btn.textContent = 'Kirim Suara Sah';
            }
        });
}

document.addEventListener('DOMContentLoaded', () => {
    getOrCreateDeviceIdentity();
    const root = document.getElementById('votingRoot');
    if (!root) return;

    const sessBilikId = parseInt(root.getAttribute('data-session-bilik-id'), 10) || 0;
    const sessNomorBilik = parseInt(root.getAttribute('data-session-nomor-bilik'), 10) || 1;
    const sessNamaBilik = root.getAttribute('data-session-nama-bilik') || '';
    const sessToken = root.getAttribute('data-session-token') || '';
    const sessDeviceId = root.getAttribute('data-session-device-id') || '';
    const verifiedNim = root.getAttribute('data-verified-nim') || '';
    const verifiedNama = root.getAttribute('data-verified-nama') || '';
    const verifiedKelas = root.getAttribute('data-verified-kelas') || '';
    const verifiedJurusan = root.getAttribute('data-verified-jurusan') || '';

    // Pemulihan sesi aktif saat halaman di-refresh
    if (sessBilikId > 0 && sessToken !== '') {
        votingState.bilikId = sessBilikId;
        votingState.nomorBilik = sessNomorBilik;
        votingState.sessionToken = sessToken;
        if (sessDeviceId) votingState.deviceId = sessDeviceId;
        document.getElementById('lockedBoothLabel').textContent = sessNamaBilik || `Bilik #0${sessNomorBilik}`;

        if (verifiedNim !== '') {
            votingState.verifiedNim = verifiedNim;
            document.getElementById('voterInfoName').textContent = verifiedNama || verifiedNim;
            document.getElementById('voterInfoSub').textContent = `NIM ${verifiedNim} · Kelas ${verifiedKelas} (${verifiedJurusan})`;
            showVotingStep(3);
            return;
        }
        showVotingStep(2);
        return;
    }

    const urlBooth = parseInt(root.getAttribute('data-url-booth'), 10) || 0;
    const urlToken = root.getAttribute('data-url-token') || '';

    if (urlBooth > 0 && urlToken !== '') {
        submitClaimQr();
    }
});
