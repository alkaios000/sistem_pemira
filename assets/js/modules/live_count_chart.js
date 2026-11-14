/**
 * LIVE_COUNT_CHART.JS - Public Real-Time Polling, 3D Flip Counter & Chart Updates
 */
let lcBarChartInstance = null;
let lcDoughnutChartInstance = null;
let currentOpenDetailCandId = null;
const CHART_COLORS = ['#2563eb', '#7c3aed', '#10b981', '#0ea5e9', '#f59e0b'];

function escapeLiveHtml(str) {
    return String(str ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

function parseMisiLines(text) {
    return String(text || '')
        .split(/\r\n|\r|\n/)
        .map(line => line.replace(/^[\-\•\*\d\.\)\s]+/, '').trim())
        .filter(Boolean);
}

/**
 * Render angka ke dalam ubin Split-Flap 3D dan jalankan animasi flip saat nilai berubah.
 */
function renderFlipCounter(el, newValue, animateOnInit = false) {
    if (!el) return { delta: 0 };

    const numVal = Math.max(0, parseInt(newValue, 10) || 0);
    const formatted = numVal.toLocaleString('id-ID');
    const prevRaw = el.dataset.prevVal;
    const prevVal = prevRaw !== undefined ? parseInt(prevRaw, 10) : null;
    const prevFormatted = el.dataset.prevStr || '';

    const isInitial = (prevVal === null);
    const delta = (!isInitial && numVal > prevVal) ? (numVal - prevVal) : 0;

    // Skip DOM rewrite if exact same formatted string already mounted
    if (!isInitial && prevFormatted === formatted) {
        return { delta: 0 };
    }

    const oldChars = prevFormatted.split('');
    const newChars = formatted.split('');
    const lenDelta = newChars.length - oldChars.length;

    el.innerHTML = newChars.map((ch, idx) => {
        if (ch === '.' || ch === ',') {
            return `<span class="flip-sep">${ch}</span>`;
        }

        const oldIdx = idx - lenDelta;
        const oldCh = (oldIdx >= 0 && oldIdx < oldChars.length) ? oldChars[oldIdx] : null;
        const shouldFlip = isInitial
            ? (animateOnInit && numVal > 0)
            : (oldCh !== ch || delta > 0);
        const gainClass = (!isInitial && delta > 0 && oldCh !== ch) ? 'is-gain' : '';
        const flipClass = shouldFlip ? 'is-flipping' : '';

        return `<span class="flip-digit ${flipClass} ${gainClass}">${escapeLiveHtml(ch)}</span>`;
    }).join('');

    el.dataset.prevVal = String(numVal);
    el.dataset.prevStr = formatted;

    // Bersihkan kelas animasi sementara agar siap memicu animasi lagi saat perubahan berikutnya
    if (delta > 0) {
        setTimeout(() => {
            el.querySelectorAll('.flip-digit.is-gain').forEach(tile => tile.classList.remove('is-gain'));
        }, 900);
    }

    return { delta };
}

function showVoteDeltaEffect(cardEl, delta) {
    if (!cardEl || delta <= 0) return;

    cardEl.classList.remove('is-vote-pulse');
    void cardEl.offsetWidth;
    cardEl.classList.add('is-vote-pulse');

    const slot = cardEl.querySelector('.cand-delta-slot');
    if (slot) {
        const pop = document.createElement('span');
        pop.className = 'lc-delta-pop';
        pop.textContent = `+${delta}`;
        slot.appendChild(pop);
        setTimeout(() => pop.remove(), 1550);
    }

    setTimeout(() => cardEl.classList.remove('is-vote-pulse'), 1200);
}

function initAllFlipCountersOnLoad() {
    const totalEl = document.getElementById('lcTotalVotes');
    if (totalEl && !totalEl.dataset.prevStr) {
        renderFlipCounter(totalEl, totalEl.getAttribute('data-flip-value') || 0, true);
    }
    document.querySelectorAll('.cand-flip-counter').forEach(el => {
        if (!el.dataset.prevStr) {
            renderFlipCounter(el, el.getAttribute('data-flip-value') || 0, true);
        }
    });
}

function buildCandidateCardHtml(c, isBpm, maxVotes) {
    const fotoUrl = c.foto_path
        ? `${window.PEMIRA_CONFIG.baseUrl}/uploads/${c.foto_path}`
        : `${window.PEMIRA_CONFIG.baseUrl}/assets/img/default_candidate.svg`;
    const suaraVal = parseInt(c.jumlah_suara, 10) || 0;
    const isLeading = (maxVotes > 0 && suaraVal === maxVotes);
    const misiItems = parseMisiLines(c.misi || '');
    const misiHtml = misiItems.length > 0
        ? misiItems.slice(0, 3).map(line => `<li>${escapeLiveHtml(line)}</li>`).join('')
        : '<li>-</li>';
    const cJsonAttr = escapeLiveHtml(JSON.stringify(c));

    return `
        <article class="lc-cand-card ${isBpm ? 'is-bpm' : ''} ${isLeading ? 'is-leading' : ''}"
                 data-cand-id="${c.id}"
                 data-cand-json='${cJsonAttr}'>
            <div class="lc-cand-ribbon">
                <span class="kandidat-num-pill ${isBpm ? 'is-bpm' : ''}">CALON #0${Number(c.nomor_urut)}</span>
                <span class="lc-lead-tag" style="${isLeading ? '' : 'display:none;'}">Unggul Sementara</span>
            </div>

            <div class="lc-cand-profile">
                <div class="lc-cand-photo-wrap" onclick="openLiveCandDetail(${Number(c.id)})" title="Klik untuk melihat detail Visi & Misi lengkap">
                    <img src="${escapeLiveHtml(fotoUrl)}" alt="${escapeLiveHtml(c.nama_calon)}" class="lc-cand-photo">
                </div>
                <div class="lc-cand-identity">
                    <h3 class="lc-cand-name">${escapeLiveHtml(c.nama_calon || '-')}</h3>
                    <span class="lc-cand-prodi">${escapeLiveHtml(c.jurusan_kelas || 'Program Studi')}</span>
                </div>
            </div>

            <div class="lc-vm-panel">
                <div>
                    <div class="lc-vm-label">Visi</div>
                    <p class="lc-visi-text">${escapeLiveHtml(c.visi || '-')}</p>
                </div>
                <div>
                    <div class="lc-vm-label">Misi</div>
                    <ul class="lc-misi-list">${misiHtml}</ul>
                </div>
                <div class="lc-vm-foot">
                    <button type="button" class="btn btn--ghost btn--xs" onclick="openLiveCandDetail(${Number(c.id)})">
                        Visi & Misi Lengkap
                    </button>
                </div>
            </div>

            <div class="lc-counter-box">
                <div class="lc-counter-top">
                    <div>
                        <div class="lc-counter-label">Perolehan Suara</div>
                        <div class="lc-flip-row">
                            <div class="flip-counter cand-flip-counter" data-flip-value="${suaraVal}"></div>
                            <span class="lc-vote-unit">suara</span>
                            <span class="cand-delta-slot"></span>
                        </div>
                    </div>
                    <div class="lc-cand-pct cand-vote-pct">${Number(c.persentase || 0)}%</div>
                </div>
                <div class="lc-progress-track">
                    <div class="lc-progress-fill cand-vote-bar" style="width: ${Math.min(100, Number(c.persentase || 0))}%;"></div>
                </div>
            </div>
        </article>
    `;
}

function openLiveCandDetail(candId, openModalNow = true) {
    const card = document.querySelector(`#lcCandidateCards [data-cand-id="${candId}"]`);
    if (!card) return;

    let c = null;
    try {
        c = JSON.parse(card.getAttribute('data-cand-json') || '{}');
    } catch (_) {
        return;
    }
    if (!c || !c.id) return;

    currentOpenDetailCandId = Number(c.id);
    const isBpm = (c.kategori === 'BPM');

    const titleEl = document.getElementById('lcDetailTitle');
    const photoEl = document.getElementById('lcDetailPhoto');
    const pillEl = document.getElementById('lcDetailNumPill');
    const nameEl = document.getElementById('lcDetailName');
    const prodiEl = document.getElementById('lcDetailProdi');
    const votesEl = document.getElementById('lcDetailVotes');
    const visiEl = document.getElementById('lcDetailVisi');
    const misiEl = document.getElementById('lcDetailMisi');

    const defSrc = photoEl?.getAttribute('data-default-src') || '';
    const fotoUrl = c.foto_path ? `${window.PEMIRA_CONFIG.baseUrl}/uploads/${c.foto_path}` : defSrc;

    if (titleEl) titleEl.textContent = `Profil Calon #0${c.nomor_urut} (${c.kategori || 'BEM'})`;
    if (photoEl) photoEl.src = fotoUrl;
    if (pillEl) {
        pillEl.textContent = `CALON #0${c.nomor_urut}`;
        pillEl.className = 'kandidat-num-pill mb-1' + (isBpm ? ' is-bpm' : '');
    }
    if (nameEl) nameEl.textContent = c.nama_calon || '-';
    if (prodiEl) prodiEl.textContent = c.jurusan_kelas || 'Program Studi';
    if (votesEl) {
        votesEl.textContent = `${Number(c.jumlah_suara || 0).toLocaleString('id-ID')} suara (${c.persentase || 0}%)`;
        votesEl.style.color = isBpm ? 'var(--purple)' : 'var(--primary)';
    }
    if (visiEl) visiEl.textContent = c.visi || '-';
    if (misiEl) misiEl.textContent = c.misi || '-';

    if (openModalNow && window.PemiraApp) {
        window.PemiraApp.openModal('lcCandidateDetailModal');
    }
}
window.openLiveCandDetail = openLiveCandDetail;

function toggleLiveFullscreen() {
    if (!document.fullscreenElement) {
        document.documentElement.requestFullscreen?.().catch(() => {});
    } else {
        document.exitFullscreen?.().catch(() => {});
    }
}
window.toggleLiveFullscreen = toggleLiveFullscreen;

function initOrUpdateCharts(candidates, isBpm) {
    if (typeof Chart === 'undefined') return;

    Chart.defaults.font.family = "'Inter', system-ui, sans-serif";
    Chart.defaults.font.size = 11.5;
    Chart.defaults.color = '#64748b';

    const palette = isBpm
        ? ['#7c3aed', '#ec4899', '#2563eb', '#10b981', '#f59e0b']
        : CHART_COLORS;

    const labels = candidates.map(c => `#0${c.nomor_urut} ${(c.nama_calon || '').split(' ')[0]}`);
    const values = candidates.map(c => parseInt(c.jumlah_suara, 10) || 0);
    const colors = candidates.map((_, idx) => palette[idx % palette.length]);

    const barCtx = document.getElementById('lcBarChart');
    if (barCtx) {
        if (lcBarChartInstance) {
            lcBarChartInstance.data.labels = labels;
            lcBarChartInstance.data.datasets[0].data = values;
            lcBarChartInstance.data.datasets[0].backgroundColor = colors;
            lcBarChartInstance.update();
        } else {
            lcBarChartInstance = new Chart(barCtx, {
                type: 'bar',
                data: {
                    labels,
                    datasets: [{
                        label: 'Jumlah Suara',
                        data: values,
                        backgroundColor: colors,
                        borderRadius: 7,
                        maxBarThickness: 52
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: { precision: 0, color: '#94a3b8' },
                            grid: { color: '#eef1f5' }
                        },
                        x: {
                            ticks: { color: '#64748b' },
                            grid: { display: false }
                        }
                    }
                }
            });
        }
    }

    const doughnutCtx = document.getElementById('lcDoughnutChart');
    if (doughnutCtx) {
        if (lcDoughnutChartInstance) {
            lcDoughnutChartInstance.data.labels = labels;
            lcDoughnutChartInstance.data.datasets[0].data = values;
            lcDoughnutChartInstance.data.datasets[0].backgroundColor = colors;
            lcDoughnutChartInstance.update();
        } else {
            lcDoughnutChartInstance = new Chart(doughnutCtx, {
                type: 'doughnut',
                data: {
                    labels,
                    datasets: [{
                        data: values,
                        backgroundColor: colors,
                        borderWidth: 3,
                        borderColor: '#ffffff'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '66%',
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: { boxWidth: 10, boxHeight: 10, usePointStyle: true, padding: 14 }
                        }
                    }
                }
            });
        }
    }
}

function fetchLiveCountData() {
    const container = document.getElementById('liveCountContainer');
    if (!container) return;
    const kategori = container.getAttribute('data-kategori') || 'BEM';
    const isBpm = (kategori === 'BPM');

    fetch(`${window.PEMIRA_CONFIG.baseUrl}/index.php?action=live_count&kategori=${encodeURIComponent(kategori)}&_t=${Date.now()}`, {
        headers: { 'Cache-Control': 'no-cache' }
    })
        .then(res => {
            if (res.status === 403) {
                window.location.reload();
                return null;
            }
            return res.json();
        })
        .then(res => {
            if (!res || !res.success || !res.data) return;
            const d = res.data;
            const cands = d.kandidats || [];

            const elTotal = document.getElementById('lcTotalVotes');
            const elDpt = document.getElementById('lcTotalDpt');
            const elPct = document.getElementById('lcTurnoutPct');
            const elTurnoutBar = document.getElementById('lcTurnoutBar');
            const elCandCount = document.getElementById('lcTotalCandidatCount');
            const elUpdated = document.getElementById('lcLastUpdated');

            if (elTotal) renderFlipCounter(elTotal, d.total_suara_kandidat || 0);
            if (elDpt) elDpt.textContent = Number(d.total_dpt || 0).toLocaleString('id-ID');
            if (elPct) elPct.textContent = `${d.partisipasi_persen || 0}%`;
            if (elTurnoutBar) elTurnoutBar.style.width = `${Math.min(100, Number(d.partisipasi_persen || 0))}%`;
            if (elCandCount) elCandCount.textContent = cands.length;
            if (elUpdated) {
                const timePart = (res.timestamp || '').split(' ')[1] || '';
                const textSpan = elUpdated.querySelector('span:last-child');
                if (textSpan) textSpan.textContent = `LIVE · ${timePart}`;
            }

            const maxVotes = cands.reduce((mx, item) => Math.max(mx, parseInt(item.jumlah_suara, 10) || 0), 0);
            const cardsWrap = document.getElementById('lcCandidateCards');

            if (cardsWrap) {
                if (cands.length === 0) {
                    cardsWrap.innerHTML = `<section class="card" style="grid-column: 1 / -1;"><div class="table-empty">Belum ada kandidat ${escapeLiveHtml(kategori)} yang terdaftar.</div></section>`;
                } else {
                    const existingIds = Array.from(cardsWrap.querySelectorAll('[data-cand-id]')).map(el => String(el.getAttribute('data-cand-id'))).join(',');
                    const incomingIds = cands.map(c => String(c.id)).join(',');

                    if (existingIds !== incomingIds) {
                        cardsWrap.innerHTML = cands.map(c => buildCandidateCardHtml(c, isBpm, maxVotes)).join('');
                        cardsWrap.querySelectorAll('.cand-flip-counter').forEach(fc => {
                            renderFlipCounter(fc, fc.getAttribute('data-flip-value') || 0, true);
                        });
                    } else {
                        cands.forEach(c => {
                            const card = cardsWrap.querySelector(`[data-cand-id="${c.id}"]`);
                            if (!card) return;

                            card.setAttribute('data-cand-json', JSON.stringify(c));

                            const suaraVal = parseInt(c.jumlah_suara, 10) || 0;
                            const isLeading = (maxVotes > 0 && suaraVal === maxVotes);
                            card.classList.toggle('is-leading', isLeading);

                            const leadTag = card.querySelector('.lc-lead-tag');
                            if (leadTag) leadTag.style.display = isLeading ? '' : 'none';

                            const nameEl = card.querySelector('.lc-cand-name');
                            const prodiEl = card.querySelector('.lc-cand-prodi');
                            const urutEl = card.querySelector('.kandidat-num-pill');
                            const imgEl = card.querySelector('.lc-cand-photo');
                            const visiEl = card.querySelector('.lc-visi-text');
                            const misiListEl = card.querySelector('.lc-misi-list');
                            const flipEl = card.querySelector('.cand-flip-counter');
                            const pctEl = card.querySelector('.cand-vote-pct');
                            const barEl = card.querySelector('.cand-vote-bar');

                            const fotoUrl = c.foto_path
                                ? `${window.PEMIRA_CONFIG.baseUrl}/uploads/${c.foto_path}`
                                : `${window.PEMIRA_CONFIG.baseUrl}/assets/img/default_candidate.svg`;

                            if (nameEl && nameEl.textContent !== (c.nama_calon || '-')) nameEl.textContent = c.nama_calon || '-';
                            if (prodiEl && prodiEl.textContent !== (c.jurusan_kelas || 'Program Studi')) prodiEl.textContent = c.jurusan_kelas || 'Program Studi';
                            if (urutEl) urutEl.textContent = `CALON #0${c.nomor_urut}`;
                            if (imgEl && imgEl.getAttribute('src') !== fotoUrl) imgEl.setAttribute('src', fotoUrl);
                            if (visiEl && visiEl.textContent !== (c.visi || '-')) visiEl.textContent = c.visi || '-';

                            if (misiListEl) {
                                const newMisiHtml = (parseMisiLines(c.misi || '').slice(0, 3).map(l => `<li>${escapeLiveHtml(l)}</li>`).join('')) || '<li>-</li>';
                                if (misiListEl.innerHTML !== newMisiHtml) misiListEl.innerHTML = newMisiHtml;
                            }

                            if (flipEl) {
                                const { delta } = renderFlipCounter(flipEl, suaraVal, false);
                                if (delta > 0) showVoteDeltaEffect(card, delta);
                            }

                            if (pctEl) pctEl.textContent = `${c.persentase}%`;
                            if (barEl) barEl.style.width = `${Math.min(100, Number(c.persentase || 0))}%`;
                        });
                    }
                }
            }

            // Refresh open Visi/Misi modal live if currently open
            const detailModal = document.getElementById('lcCandidateDetailModal');
            if (detailModal && detailModal.classList.contains('is-open') && currentOpenDetailCandId) {
                openLiveCandDetail(currentOpenDetailCandId, false);
            }

            initOrUpdateCharts(cands, isBpm);
        })
        .catch(() => {});
}

document.addEventListener('DOMContentLoaded', () => {
    initAllFlipCountersOnLoad();
    fetchLiveCountData();
    setInterval(fetchLiveCountData, 2000);
    window.addEventListener('pemira:realtime-sync', fetchLiveCountData);
});
