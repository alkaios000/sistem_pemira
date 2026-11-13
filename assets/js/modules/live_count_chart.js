/**
 * LIVE_COUNT_CHART.JS - Adminator 2026 Redesign Live Count Chart.js Polling
 */
let lcBarChartInstance = null;
let lcDoughnutChartInstance = null;
const CHART_COLORS = ['#2563eb', '#7c3aed', '#10b981', '#0ea5e9', '#f59e0b'];

function initOrUpdateCharts(candidates) {
    if (typeof Chart === 'undefined') return;

    Chart.defaults.font.family = "'Inter', system-ui, sans-serif";
    Chart.defaults.font.size = 11.5;
    Chart.defaults.color = '#64748b';

    const labels = candidates.map(c => `#0${c.nomor_urut} ${(c.nama_calon || '').split(' ')[0]}`);
    const values = candidates.map(c => parseInt(c.jumlah_suara, 10) || 0);
    const colors = candidates.map((_, idx) => CHART_COLORS[idx % CHART_COLORS.length]);

    const barCtx = document.getElementById('lcBarChart');
    if (barCtx) {
        if (lcBarChartInstance) {
            lcBarChartInstance.data.labels = labels;
            lcBarChartInstance.data.datasets[0].data = values;
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
                        borderRadius: 6,
                        maxBarThickness: 46
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

    fetch(`${window.PEMIRA_CONFIG.baseUrl}/index.php?action=live_count&kategori=${encodeURIComponent(kategori)}`, {
        headers: { 'Cache-Control': 'no-cache' }
    })
        .then(res => res.json())
        .then(res => {
            if (!res.success || !res.data) return;
            const d = res.data;

            const elTotal = document.getElementById('lcTotalVotes');
            const elDpt = document.getElementById('lcTotalDpt');
            const elPct = document.getElementById('lcTurnoutPct');
            const elCandCount = document.getElementById('lcTotalCandidatCount');
            const elUpdated = document.getElementById('lcLastUpdated');

            const cands = d.kandidats || [];

            if (elTotal) elTotal.textContent = Number(d.total_suara_kandidat || 0).toLocaleString('id-ID');
            if (elDpt) elDpt.textContent = Number(d.total_dpt || 0).toLocaleString('id-ID');
            if (elPct) elPct.textContent = `${d.partisipasi_persen || 0}%`;
            if (elCandCount) elCandCount.textContent = cands.length;
            if (elUpdated) elUpdated.textContent = `LIVE · ${(res.timestamp || '').split(' ')[1] || ''}`;

            const cardsWrap = document.getElementById('lcCandidateCards');
            if (cardsWrap) {
                const existingIds = Array.from(cardsWrap.querySelectorAll('[data-cand-id]')).map(el => String(el.getAttribute('data-cand-id'))).join(',');
                const incomingIds = cands.map(c => String(c.id)).join(',');

                if (existingIds !== incomingIds) {
                    cardsWrap.innerHTML = cands.map(c => {
                        const fotoUrl = c.foto_path
                            ? `${window.PEMIRA_CONFIG.baseUrl}/uploads/${c.foto_path}`
                            : `${window.PEMIRA_CONFIG.baseUrl}/assets/img/default_candidate.svg`;
                        const accentStyle = isBpm ? 'style="color:var(--purple);"' : '';
                        const barClass = isBpm ? 'progress-bar purple cand-vote-bar' : 'progress-bar cand-vote-bar';
                        return `
                            <div class="lc-cand-card" data-cand-id="${c.id}">
                                <div class="lc-cand-top">
                                    <img src="${fotoUrl}" alt="Calon" class="lc-cand-photo">
                                    <div>
                                        <span class="kandidat-urut" ${accentStyle}>CALON #0${c.nomor_urut}</span>
                                        <div class="fw-700 cand-name-label" style="font-size:13.5px;"></div>
                                    </div>
                                </div>
                                <div class="lc-cand-stat">
                                    <span class="lc-cand-votes"><span class="cand-vote-num">${Number(c.jumlah_suara || 0).toLocaleString('id-ID')}</span> <small class="text-muted" style="font-size:11px;font-weight:500;">suara</small></span>
                                    <span class="lc-cand-pct cand-vote-pct" ${accentStyle}>${c.persentase}%</span>
                                </div>
                                <div class="progress">
                                    <div class="${barClass}" style="width: ${c.persentase}%;"></div>
                                </div>
                            </div>
                        `;
                    }).join('');
                }

                cands.forEach(c => {
                    const card = cardsWrap.querySelector(`[data-cand-id="${c.id}"]`);
                    if (!card) return;
                    const nameEl = card.querySelector('.cand-name-label') || card.querySelector('.lc-cand-top .fw-700');
                    const urutEl = card.querySelector('.kandidat-urut, .eyebrow');
                    const imgEl = card.querySelector('.lc-cand-photo');
                    const numEl = card.querySelector('.cand-vote-num');
                    const pctEl = card.querySelector('.cand-vote-pct');
                    const barEl = card.querySelector('.cand-vote-bar');
                    const fotoUrl = c.foto_path
                        ? `${window.PEMIRA_CONFIG.baseUrl}/uploads/${c.foto_path}`
                        : `${window.PEMIRA_CONFIG.baseUrl}/assets/img/default_candidate.svg`;

                    if (nameEl) nameEl.textContent = c.nama_calon || '-';
                    if (urutEl) urutEl.textContent = `CALON #0${c.nomor_urut}`;
                    if (imgEl && imgEl.getAttribute('src') !== fotoUrl) imgEl.setAttribute('src', fotoUrl);
                    if (numEl) numEl.textContent = Number(c.jumlah_suara || 0).toLocaleString('id-ID');
                    if (pctEl) pctEl.textContent = `${c.persentase}%`;
                    if (barEl) barEl.style.width = `${c.persentase}%`;
                });
            }

            initOrUpdateCharts(cands);
        })
        .catch(() => {});
}

document.addEventListener('DOMContentLoaded', () => {
    fetchLiveCountData();
    setInterval(fetchLiveCountData, 2000);
    window.addEventListener('pemira:realtime-sync', fetchLiveCountData);
});
