/**
 * VOTING_CLIENT.JS - Adminator 2026 Redesign Bilik Pemilihan Client Engine
 * Mendukung deteksi bilik otomatis tanpa memilih bilik/menampilkan token,
 * auto-lanjut ke verifikasi NIM, pemulihan sesi persisten, dan transaksi atomik.
 */
let html5QrScanner = null;
let isCameraStarting = false;
let isClaimingQr = false;
let isVerifyingNim = false;

let votingState = {
    bilikId: 0,
    nomorBilik: 0,
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

function setScannerUiState(mode, message = '', allowRetry = true) {
    const idleView = document.getElementById('qrScanIdleView');
    const processingBox = document.getElementById('qrAutoProcessing');
    const processingText = document.getElementById('qrAutoProcessingText');
    const errorBox = document.getElementById('qrScanErrorBox');
    const errorMsg = document.getElementById('qrScanErrorMsg');
    const retryBtn = document.getElementById('btnRetryQrScan');

    if (idleView) idleView.style.display = (mode === 'idle') ? 'block' : 'none';
    if (processingBox) processingBox.style.display = (mode === 'processing') ? 'flex' : 'none';
    if (processingText && message) processingText.textContent = message;

    if (errorBox) errorBox.style.display = (mode === 'error') ? 'flex' : 'none';
    if (errorMsg && mode === 'error') errorMsg.textContent = message;
    if (retryBtn) retryBtn.style.display = allowRetry ? 'inline-flex' : 'none';
}

function retryQrScan() {
    setScannerUiState('idle');
    startQrCamera(true);
}

function showVotingStep(step) {
    const p1 = document.getElementById('panelStep1');
    const p2 = document.getElementById('panelStep2');
    const p3 = document.getElementById('panelStep3');
    const p4 = document.getElementById('panelStep4');

    if (p1) p1.style.display = (step === 1) ? 'flex' : 'none';
    if (p2) p2.style.display = (step === 2) ? 'flex' : 'none';
    if (p3) p3.style.display = (step === 3) ? 'block' : 'none';
    if (p4) p4.style.display = (step === 4) ? 'flex' : 'none';

    const pill1 = document.getElementById('stepPill1');
    const pill2 = document.getElementById('stepPill2');
    const pill3 = document.getElementById('stepPill3');

    if (pill1) pill1.className = 'step-pill ' + (step === 1 ? 'is-active' : 'is-done');
    if (pill2) pill2.className = 'step-pill ' + (step === 2 ? 'is-active' : (step > 2 ? 'is-done' : ''));
    if (pill3) pill3.className = 'step-pill ' + (step === 3 ? 'is-active' : (step > 3 ? 'is-done' : ''));
}

function showCameraPermissionMessage(msg, showStartBtn = true) {
    const msgEl = document.getElementById('qrCameraPermissionMsg');
    const readerEl = document.getElementById('qrCameraReader');
    const btnStart = document.getElementById('btnStartQrCamera');
    const btnStop = document.getElementById('btnStopQrCamera');

    if (readerEl) readerEl.style.display = 'none';
    if (msgEl) {
        msgEl.textContent = msg;
        msgEl.style.display = msg ? 'block' : 'none';
    }
    if (btnStart) btnStart.style.display = showStartBtn ? 'inline-flex' : 'none';
    if (btnStop) btnStop.style.display = 'none';
}

function startQrCamera(fromUserClick = false) {
    if (isCameraStarting) return;

    const readerEl = document.getElementById('qrCameraReader');
    const btnStart = document.getElementById('btnStartQrCamera');
    const btnStop = document.getElementById('btnStopQrCamera');
    const msgEl = document.getElementById('qrCameraPermissionMsg');
    if (!readerEl) return;

    if (typeof Html5Qrcode === 'undefined') {
        showCameraPermissionMessage('Pustaka kamera QR tidak tersedia. Gunakan kamera bawaan ponsel untuk memindai QR Code pada layar bilik.', false);
        return;
    }

    if (!window.isSecureContext || !navigator.mediaDevices || typeof navigator.mediaDevices.getUserMedia !== 'function') {
        showCameraPermissionMessage('Akses kamera langsung di browser memerlukan koneksi HTTPS atau localhost. Anda dapat memindai QR Code memakai aplikasi kamera ponsel.', false);
        return;
    }

    setScannerUiState('idle');
    if (msgEl) {
        msgEl.textContent = '';
        msgEl.style.display = 'none';
    }
    if (btnStart) btnStart.style.display = 'none';
    if (btnStop) btnStop.style.display = 'none';

    readerEl.style.display = 'flex';
    readerEl.innerHTML = '<span>Meminta izin akses kamera...</span>';

    isCameraStarting = true;
    const scanConfig = { fps: 10, qrbox: { width: 210, height: 210 } };
    const onSuccess = decodedText => {
        stopQrCamera();
        handleDecodedQrText(decodedText);
    };

    const onStarted = () => {
        isCameraStarting = false;
        if (btnStart) btnStart.style.display = 'none';
        if (btnStop) btnStop.style.display = 'inline-flex';
    };

    const onFailed = err => {
        isCameraStarting = false;
        html5QrScanner = null;
        const errText = String(err || '');
        const denied = /NotAllowedError|Permission|denied|dismissed/i.test(errText);
        const msg = denied
            ? 'Izin kamera belum diberikan. Izinkan akses kamera pada browser lalu tekan tombol di bawah untuk memindai QR bilik.'
            : 'Kamera tidak terdeteksi atau sedang digunakan aplikasi lain. Pastikan izin kamera aktif lalu coba lagi.';
        showCameraPermissionMessage(msg, true);
        if (fromUserClick) {
            PemiraApp.toast(msg, 'warning');
        }
    };

    html5QrScanner = new Html5Qrcode('qrCameraReader');
    html5QrScanner.start({ facingMode: 'environment' }, scanConfig, onSuccess, () => {})
        .then(onStarted)
        .catch(err => {
            const errText = String(err || '');
            // Jika constraint kamera belakang ditolak (mis. webcam laptop), coba kamera pertama yang tersedia
            if (/OverconstrainedError|NotFoundError|ConstraintNotSatisfied/i.test(errText)) {
                Html5Qrcode.getCameras()
                    .then(cameras => {
                        if (!cameras || cameras.length === 0) {
                            onFailed(err);
                            return;
                        }
                        html5QrScanner = new Html5Qrcode('qrCameraReader');
                        html5QrScanner.start(cameras[0].id, scanConfig, onSuccess, () => {})
                            .then(onStarted)
                            .catch(onFailed);
                    })
                    .catch(onFailed);
                return;
            }
            onFailed(err);
        });
}

function stopQrCamera() {
    isCameraStarting = false;
    if (html5QrScanner) {
        const currentScanner = html5QrScanner;
        html5QrScanner = null;
        try {
            currentScanner.stop().then(() => {
                currentScanner.clear();
            }).catch(() => {});
        } catch (_) {}
    }
    const readerEl = document.getElementById('qrCameraReader');
    if (readerEl) readerEl.style.display = 'none';
    const btnStart = document.getElementById('btnStartQrCamera');
    const btnStop = document.getElementById('btnStopQrCamera');
    if (btnStart) btnStart.style.display = 'inline-flex';
    if (btnStop) btnStop.style.display = 'none';
}

function handleDecodedQrText(text) {
    let detectedBooth = 0;
    let detectedToken = '';

    try {
        const url = new URL(text.trim());
        const boothParam = parseInt(url.searchParams.get('booth') || '0', 10);
        const tokenParam = (url.searchParams.get('token') || '').trim();
        if (boothParam > 0) detectedBooth = boothParam;
        if (tokenParam !== '') detectedToken = tokenParam;
    } catch (_) {
        detectedToken = String(text || '').trim();
    }

    if (!detectedToken) {
        setScannerUiState('error', 'QR Code yang dipindai bukan merupakan QR Bilik PEMIRA yang valid.', true);
        PemiraApp.toast('QR Code tidak dikenali.', 'warning');
        return;
    }

    submitClaimQr(detectedBooth, detectedToken);
}

function submitClaimQr(boothNumber = 0, qrToken = '') {
    const token = String(qrToken || '').trim();
    const booth = parseInt(boothNumber, 10) || 0;

    if (!token || isClaimingQr) {
        if (!token) {
            setScannerUiState('error', 'Data QR Bilik tidak valid. Silakan pindai ulang QR Code.', true);
        }
        return;
    }

    isClaimingQr = true;
    setScannerUiState('processing', 'Mendeteksi bilik suara & mengamankan sesi Anda...');

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
            isClaimingQr = false;
            if (window.PemiraApp && typeof window.PemiraApp.broadcastSync === 'function') {
                window.PemiraApp.broadcastSync('claim_qr');
            }
            if (!res.success) {
                const errCode = res.data?.kode_error || '';
                if (errCode === 'DEVICE_HAS_ACTIVE_SESSION') {
                    PemiraApp.toast(res.message || 'Melanjutkan sesi bilik aktif Anda...', 'info');
                    setTimeout(() => {
                        window.location.href = `${window.PEMIRA_CONFIG.baseUrl}/index.php?page=bilik_pemilihan`;
                    }, 500);
                    return;
                }
                const allowRetry = errCode !== 'DOUBLE_SCAN_SAME_DEVICE';
                setScannerUiState('error', res.message || 'Gagal mengklaim QR Code bilik.', allowRetry);
                PemiraApp.toast(res.message || 'Gagal mengklaim QR Code bilik.', 'danger');
                return;
            }

            const d = res.data;
            votingState.bilikId = d.bilik_id;
            votingState.nomorBilik = d.nomor_bilik;
            votingState.sessionToken = d.voting_session_token;
            votingState.deviceId = d.device_id || votingState.deviceId;
            if (votingState.deviceId) {
                localStorage.setItem('pemira_device_id', votingState.deviceId);
            }

            const lockedLabel = document.getElementById('lockedBoothLabel');
            if (lockedLabel) {
                lockedLabel.textContent = d.nama_bilik || `Bilik #0${d.nomor_bilik}`;
            }

            if (d.next_step === 3 && d.verified_mahasiswa) {
                const m = d.verified_mahasiswa;
                votingState.verifiedNim = m.nim;
                document.getElementById('voterInfoName').textContent = m.nama_lengkap;
                document.getElementById('voterInfoSub').textContent = `NIM ${m.nim} · Kelas ${m.nama_kelas} (${m.jurusan})`;
                PemiraApp.toast(res.message || 'Melanjutkan ke surat suara.', 'success');
                showVotingStep(3);
                return;
            }

            PemiraApp.toast(res.message, 'success');
            showVotingStep(2);
            const nimInput = document.getElementById('verifyNimInput');
            if (nimInput) {
                nimInput.value = '';
                nimInput.focus();
            }
        })
        .catch(() => {
            isClaimingQr = false;
            setScannerUiState('error', 'Kesalahan jaringan saat menghubungi server bilik. Silakan coba pindai lagi.', true);
            PemiraApp.toast('Kesalahan jaringan saat mengklaim QR bilik.', 'danger');
        });
}

function submitVerifyNim() {
    if (isVerifyingNim) return;

    const nimInput = document.getElementById('verifyNimInput');
    const nim = (nimInput?.value || '').trim();
    if (!nim) {
        PemiraApp.toast('Masukkan NIM Anda terlebih dahulu.', 'warning');
        nimInput?.focus();
        return;
    }

    const btn = document.getElementById('btnVerifyNim');
    isVerifyingNim = true;
    if (btn) {
        btn.disabled = true;
        btn.textContent = 'Memverifikasi NIM...';
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
            isVerifyingNim = false;
            if (btn) {
                btn.disabled = false;
                btn.textContent = 'Verifikasi NIM & Buka Surat Suara →';
            }
            if (window.PemiraApp && typeof window.PemiraApp.broadcastSync === 'function') {
                window.PemiraApp.broadcastSync('verify_nim');
            }
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
            isVerifyingNim = false;
            if (btn) {
                btn.disabled = false;
                btn.textContent = 'Verifikasi NIM & Buka Surat Suara →';
            }
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
            if (window.PemiraApp && typeof window.PemiraApp.broadcastSync === 'function') {
                window.PemiraApp.broadcastSync('confirm_vote');
            }
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

    const nimInput = document.getElementById('verifyNimInput');
    if (nimInput) {
        nimInput.addEventListener('keydown', e => {
            if (e.key === 'Enter') {
                e.preventDefault();
                submitVerifyNim();
            }
        });
    }

    const urlParams = new URLSearchParams(window.location.search);
    const urlBooth = parseInt(urlParams.get('booth') || '0', 10) || 0;
    const urlToken = (urlParams.get('token') || '').trim();

    if (urlToken !== '' && window.history && typeof window.history.replaceState === 'function') {
        urlParams.delete('token');
        urlParams.delete('booth');
        const cleanQuery = urlParams.toString();
        const cleanUrl = window.location.pathname + (cleanQuery ? '?' + cleanQuery : '');
        window.history.replaceState(null, document.title, cleanUrl);
    }

    if (document.getElementById('panelAlreadyVoted')) {
        return;
    }

    const sessBilikId = parseInt(root.getAttribute('data-session-bilik-id'), 10) || 0;
    const sessNomorBilik = parseInt(root.getAttribute('data-session-nomor-bilik'), 10) || 0;
    const sessNamaBilik = root.getAttribute('data-session-nama-bilik') || '';
    const sessToken = root.getAttribute('data-session-token') || '';
    const sessDeviceId = root.getAttribute('data-session-device-id') || '';
    const verifiedNim = root.getAttribute('data-verified-nim') || '';
    const verifiedNama = root.getAttribute('data-verified-nama') || '';
    const verifiedKelas = root.getAttribute('data-verified-kelas') || '';
    const verifiedJurusan = root.getAttribute('data-verified-jurusan') || '';

    // Pemulihan sesi aktif saat refresh atau membuka kembali browser
    if (sessBilikId > 0 && sessToken !== '') {
        votingState.bilikId = sessBilikId;
        votingState.nomorBilik = sessNomorBilik;
        votingState.sessionToken = sessToken;
        if (sessDeviceId) {
            votingState.deviceId = sessDeviceId;
            localStorage.setItem('pemira_device_id', sessDeviceId);
        }
        const lockedLabel = document.getElementById('lockedBoothLabel');
        if (lockedLabel) {
            lockedLabel.textContent = sessNamaBilik || `Bilik #0${sessNomorBilik}`;
        }

        if (verifiedNim !== '') {
            votingState.verifiedNim = verifiedNim;
            document.getElementById('voterInfoName').textContent = verifiedNama || verifiedNim;
            document.getElementById('voterInfoSub').textContent = `NIM ${verifiedNim} · Kelas ${verifiedKelas} (${verifiedJurusan})`;
            showVotingStep(3);
            return;
        }
        showVotingStep(2);
        nimInput?.focus();
        return;
    }

    if (urlToken !== '') {
        submitClaimQr(urlBooth, urlToken);
        return;
    }

    // Otomatis minta izin & aktifkan kamera begitu halaman scanner terbuka pada Tahap 1
    startQrCamera(false);
});
