/**
 * Modul Live Real Count (Portal Perhitungan Suara Terpisah BEM / BPM)
 * Memperbarui jumlah suara per kandidat, persentase, dan total partisipasi pemilih secara real-time.
 */

document.addEventListener('DOMContentLoaded', function () {
    const liveRoot = document.getElementById('live_count_root');
    if (!liveRoot) {
        return;
    }

    const endpoint = liveRoot.getAttribute('data-api-url');
    const kategori = liveRoot.getAttribute('data-kategori');

    const elTotalSuaraMasuk = document.getElementById('live_total_suara_masuk');
    const elTotalDpt = document.getElementById('live_total_dpt');
    const elTotalBelum = document.getElementById('live_total_belum');
    const elPersenPartisipasi = document.getElementById('live_persen_partisipasi');
    const elBarPartisipasi = document.getElementById('live_bar_partisipasi');
    const elLastUpdated = document.getElementById('live_last_updated');

    function refreshLiveCount() {
        const sep = endpoint.indexOf('?') !== -1 ? '&' : '?';
        const url = endpoint + sep + 'kategori=' + encodeURIComponent(kategori) + '&_t=' + Date.now();

        fetch(url, { cache: 'no-store' })
            .then(function (r) {
                return r.json();
            })
            .then(function (res) {
                if (!res || !res.success || !res.data) {
                    return;
                }

                const d = res.data;
                if (elTotalSuaraMasuk) elTotalSuaraMasuk.textContent = d.total_sudah_memilih;
                if (elTotalDpt) elTotalDpt.textContent = d.total_dpt;
                if (elTotalBelum) elTotalBelum.textContent = d.total_belum_memilih;
                if (elPersenPartisipasi) elPersenPartisipasi.textContent = d.partisipasi_persen + '%';
                if (elBarPartisipasi) elBarPartisipasi.style.width = Math.min(100, d.partisipasi_persen) + '%';
                if (elLastUpdated) elLastUpdated.textContent = res.timestamp;

                (d.kandidats || []).forEach(function (k) {
                    const voteEl = document.getElementById('kandidat_suara_' + k.id);
                    const pctEl = document.getElementById('kandidat_persen_' + k.id);
                    const barEl = document.getElementById('kandidat_bar_' + k.id);

                    if (voteEl) voteEl.textContent = k.jumlah_suara;
                    if (pctEl) pctEl.textContent = '(' + k.persentase + '%)';
                    if (barEl) barEl.style.width = Math.min(100, k.persentase) + '%';
                });
            })
            .catch(function () {});
    }

    setInterval(refreshLiveCount, 2000);
});

