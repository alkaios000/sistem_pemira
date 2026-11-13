/**
 * APP.JS - Adminator 2026 Redesign Shell, Modal, Toast & Vanilla DataTable Engine
 */
const PemiraApp = (() => {
    function openModal(modalId) {
        const el = document.getElementById(modalId);
        if (el) el.classList.add('is-open');
    }

    function closeModal(modalId) {
        const el = document.getElementById(modalId);
        if (el) el.classList.remove('is-open');
    }

    function confirmAction({ title = 'Konfirmasi', message = 'Apakah Anda yakin?', onConfirm = null }) {
        const modal = document.getElementById('globalConfirmModal');
        if (!modal) {
            if (window.confirm(message) && typeof onConfirm === 'function') onConfirm();
            return;
        }
        document.getElementById('globalConfirmTitle').textContent = title;
        document.getElementById('globalConfirmMessage').textContent = message;
        const btnOk = document.getElementById('globalConfirmOkBtn');
        const newBtn = btnOk.cloneNode(true);
        btnOk.parentNode.replaceChild(newBtn, btnOk);
        newBtn.addEventListener('click', () => {
            closeModal('globalConfirmModal');
            if (typeof onConfirm === 'function') onConfirm();
        });
        openModal('globalConfirmModal');
    }

    function toast(message, type = 'info') {
        let container = document.getElementById('pemiraToastContainer');
        if (!container) {
            container = document.createElement('div');
            container.id = 'pemiraToastContainer';
            container.style.cssText = 'position:fixed;bottom:16px;right:16px;z-index:2000;display:flex;flex-direction:column;gap:6px;max-width:340px;';
            document.body.appendChild(container);
        }
        const item = document.createElement('div');
        item.className = `alert ${type}`;
        item.style.cssText = 'margin:0;box-shadow:var(--shadow-lg);background:var(--bg-card);border-left:3px solid var(--primary);padding:9px 13px;font-size:12px;';
        if (type === 'success') item.style.borderLeftColor = 'var(--success)';
        if (type === 'danger') item.style.borderLeftColor = 'var(--danger)';
        if (type === 'warning') item.style.borderLeftColor = 'var(--warning)';
        item.textContent = message;
        container.appendChild(item);
        setTimeout(() => item.remove(), 3500);
    }

    /**
     * Vanilla JS Adminator 2026 DataTable Enhancer
     * Enhances any container with [data-datatable-card] and supports live in-place row syncing
     */
    function initDataTables() {
        document.querySelectorAll('[data-datatable-card]').forEach(card => {
            if (card._dtInstance) return;
            const table = card.querySelector('table.data-table');
            if (!table || !table.tBodies[0]) return;

            const tbody = table.tBodies[0];
            let allRows = Array.from(tbody.querySelectorAll('tr.data-row'));

            const searchInput = card.querySelector('[data-dt-search]');
            const filters = Array.from(card.querySelectorAll('[data-dt-filter]'));
            const perPageSelect = card.querySelector('[data-dt-perpage]');
            const infoEl = card.querySelector('[data-dt-info]');
            const pagerEl = card.querySelector('[data-dt-pager]');
            const sortableHeaders = Array.from(table.querySelectorAll('thead th[data-sort]'));

            let currentPage = 1;
            let perPage = perPageSelect ? parseInt(perPageSelect.value, 10) || 10 : 15;
            let sortCol = null;
            let sortDir = 'asc';

            function getFilteredRows() {
                const q = searchInput ? searchInput.value.trim().toLowerCase() : '';
                return allRows.filter(row => {
                    if (q && !row.textContent.toLowerCase().includes(q)) {
                        return false;
                    }
                    for (const sel of filters) {
                        const val = sel.value;
                        const attrName = sel.getAttribute('data-dt-filter');
                        if (val && val !== 'ALL') {
                            const rowVal = (row.getAttribute(attrName) || '').toUpperCase();
                            if (rowVal !== val.toUpperCase()) return false;
                        }
                    }
                    return true;
                });
            }

            function render() {
                // Remove any static server-rendered empty row when we manage rows dynamically
                Array.from(tbody.querySelectorAll('tr:not(.data-row):not(.dt-empty-row)')).forEach(r => r.remove());

                let filtered = getFilteredRows();

                if (sortCol !== null) {
                    filtered.sort((a, b) => {
                        const cellA = a.children[sortCol]?.innerText.trim() || '';
                        const cellB = b.children[sortCol]?.innerText.trim() || '';
                        const numA = parseFloat(cellA.replace(/[^0-9.-]+/g, ''));
                        const numB = parseFloat(cellB.replace(/[^0-9.-]+/g, ''));
                        let cmp = 0;
                        if (!isNaN(numA) && !isNaN(numB) && /^[0-9.,\s#-]+$/.test(cellA)) {
                            cmp = numA - numB;
                        } else {
                            cmp = cellA.localeCompare(cellB, 'id', { numeric: true, sensitivity: 'base' });
                        }
                        return sortDir === 'asc' ? cmp : -cmp;
                    });
                }

                const total = filtered.length;
                const totalPages = Math.max(1, Math.ceil(total / perPage));
                if (currentPage > totalPages) currentPage = totalPages;

                const startIdx = (currentPage - 1) * perPage;
                const pageRows = filtered.slice(startIdx, startIdx + perPage);

                allRows.forEach(r => { r.style.display = 'none'; });
                pageRows.forEach(r => {
                    r.style.display = '';
                    tbody.appendChild(r);
                });

                // Manage dynamic empty row
                let emptyRow = tbody.querySelector('tr.dt-empty-row');
                if (total === 0) {
                    if (!emptyRow) {
                        emptyRow = document.createElement('tr');
                        emptyRow.className = 'dt-empty-row';
                        const td = document.createElement('td');
                        td.colSpan = table.querySelectorAll('thead th').length || 6;
                        td.className = 'table-empty';
                        td.textContent = allRows.length === 0
                            ? 'Belum ada data pada tabel ini.'
                            : 'Tidak ada data yang cocok dengan filter pencarian.';
                        emptyRow.appendChild(td);
                        tbody.appendChild(emptyRow);
                    } else {
                        const td = emptyRow.querySelector('td');
                        if (td) {
                            td.textContent = allRows.length === 0
                                ? 'Belum ada data pada tabel ini.'
                                : 'Tidak ada data yang cocok dengan filter pencarian.';
                        }
                    }
                    emptyRow.style.display = '';
                } else if (emptyRow) {
                    emptyRow.style.display = 'none';
                }

                if (infoEl) {
                    const from = total === 0 ? 0 : startIdx + 1;
                    const to = Math.min(startIdx + perPage, total);
                    infoEl.textContent = `Showing ${from}–${to} of ${total}`;
                }

                if (pagerEl) {
                    pagerEl.innerHTML = '';
                    const prevBtn = document.createElement('button');
                    prevBtn.type = 'button';
                    prevBtn.className = 'pager-btn';
                    prevBtn.disabled = currentPage <= 1;
                    prevBtn.textContent = '‹';
                    prevBtn.addEventListener('click', () => { currentPage--; render(); });
                    pagerEl.appendChild(prevBtn);

                    const maxButtons = 5;
                    let startP = Math.max(1, currentPage - 2);
                    let endP = Math.min(totalPages, startP + maxButtons - 1);
                    if (endP - startP < maxButtons - 1) {
                        startP = Math.max(1, endP - maxButtons + 1);
                    }

                    for (let p = startP; p <= endP; p++) {
                        const btn = document.createElement('button');
                        btn.type = 'button';
                        btn.className = 'pager-btn' + (p === currentPage ? ' is-active' : '');
                        btn.textContent = String(p);
                        btn.addEventListener('click', () => { currentPage = p; render(); });
                        pagerEl.appendChild(btn);
                    }

                    const nextBtn = document.createElement('button');
                    nextBtn.type = 'button';
                    nextBtn.className = 'pager-btn';
                    nextBtn.disabled = currentPage >= totalPages;
                    nextBtn.textContent = '›';
                    nextBtn.addEventListener('click', () => { currentPage++; render(); });
                    pagerEl.appendChild(nextBtn);
                }
            }

            function refreshFromCard(newCardEl) {
                if (!newCardEl) return;
                const curHead = card.querySelector('.card-head');
                const newHead = newCardEl.querySelector('.card-head');
                if (curHead && newHead && curHead.innerHTML !== newHead.innerHTML) {
                    curHead.innerHTML = newHead.innerHTML;
                }

                // Update filter select options while keeping user's selected filter value
                filters.forEach(sel => {
                    const attrName = sel.getAttribute('data-dt-filter');
                    const newSel = newCardEl.querySelector(`[data-dt-filter="${attrName}"]`);
                    if (newSel) {
                        const prevVal = sel.value;
                        if (sel.innerHTML !== newSel.innerHTML) {
                            sel.innerHTML = newSel.innerHTML;
                            const hasOption = Array.from(sel.options).some(o => o.value === prevVal);
                            sel.value = hasOption ? prevVal : 'ALL';
                        }
                    }
                });

                const newTbody = newCardEl.querySelector('table.data-table tbody');
                if (!newTbody) return;

                const incomingRows = Array.from(newTbody.querySelectorAll('tr.data-row'));
                tbody.innerHTML = '';
                incomingRows.forEach(r => tbody.appendChild(r.cloneNode(true)));
                allRows = Array.from(tbody.querySelectorAll('tr.data-row'));
                render();
            }

            if (searchInput) {
                searchInput.addEventListener('input', () => { currentPage = 1; render(); });
            }
            filters.forEach(sel => {
                sel.addEventListener('change', () => { currentPage = 1; render(); });
            });
            if (perPageSelect) {
                perPageSelect.addEventListener('change', () => {
                    perPage = parseInt(perPageSelect.value, 10) || 15;
                    currentPage = 1;
                    render();
                });
            }
            sortableHeaders.forEach(th => {
                th.addEventListener('click', () => {
                    const idx = Array.from(th.parentNode.children).indexOf(th);
                    if (sortCol === idx) {
                        sortDir = sortDir === 'asc' ? 'desc' : 'asc';
                    } else {
                        sortCol = idx;
                        sortDir = 'asc';
                    }
                    sortableHeaders.forEach(h => h.classList.remove('sorted-asc', 'sorted-desc'));
                    th.classList.add(sortDir === 'asc' ? 'sorted-asc' : 'sorted-desc');
                    render();
                });
            });

            card._dtInstance = { refreshFromCard, render };
            render();
        });
    }

    /**
     * Cross-Tab & In-Page Super Real-Time Sync Engine
     */
    const rtChannel = typeof BroadcastChannel !== 'undefined' ? new BroadcastChannel('pemira_realtime_sync') : null;
    let isSyncingPage = false;
    let pendingSyncUrl = undefined;

    function broadcastSync(reason = 'update') {
        const payload = { t: Date.now(), reason };
        if (rtChannel) {
            try { rtChannel.postMessage(payload); } catch (_) {}
        }
        try { localStorage.setItem('pemira_rt_ping', JSON.stringify(payload)); } catch (_) {}
        window.dispatchEvent(new CustomEvent('pemira:realtime-sync', { detail: payload }));
    }

    async function syncCurrentPage(targetUrl = null) {
        if (isSyncingPage) {
            pendingSyncUrl = targetUrl;
            return;
        }
        isSyncingPage = true;
        const fetchUrl = targetUrl || window.location.href;

        try {
            const res = await fetch(fetchUrl, {
                headers: {
                    'Cache-Control': 'no-cache',
                    'X-Pemira-Sync': '1'
                }
            });
            if (!res.ok) return;
            const html = await res.text();
            const doc = new DOMParser().parseFromString(html, 'text/html');
            if (!doc.querySelector('main.content') && !doc.getElementById('liveCountContainer')) return;

            // 1. Sync Kandidat & Timses Page
            const curPanelBem = document.getElementById('panelKandidatBEM');
            const newPanelBem = doc.getElementById('panelKandidatBEM');
            const curPanelBpm = document.getElementById('panelKandidatBPM');
            const newPanelBpm = doc.getElementById('panelKandidatBPM');

            if (curPanelBem && newPanelBem && curPanelBpm && newPanelBpm) {
                const curTabBem = document.getElementById('tabBtnBEM');
                const newTabBem = doc.getElementById('tabBtnBEM');
                const curTabBpm = document.getElementById('tabBtnBPM');
                const newTabBpm = doc.getElementById('tabBtnBPM');

                if (curTabBem && newTabBem) curTabBem.textContent = newTabBem.textContent;
                if (curTabBpm && newTabBpm) curTabBpm.textContent = newTabBpm.textContent;

                if (curPanelBem.innerHTML !== newPanelBem.innerHTML) {
                    curPanelBem.innerHTML = newPanelBem.innerHTML;
                }
                if (curPanelBpm.innerHTML !== newPanelBpm.innerHTML) {
                    curPanelBpm.innerHTML = newPanelBpm.innerHTML;
                }

                const activeTab = (typeof window.currentKandidatTab === 'string') ? window.currentKandidatTab : 'BEM';
                curPanelBem.style.display = (activeTab === 'BPM') ? 'none' : '';
                curPanelBpm.style.display = (activeTab === 'BPM') ? '' : 'none';
                if (curTabBem) curTabBem.classList.toggle('is-active', activeTab !== 'BPM');
                if (curTabBpm) curTabBpm.classList.toggle('is-active', activeTab === 'BPM');

                // If Detail modal is currently open, refresh its data live
                const detailModal = document.getElementById('detailKandidatModal');
                if (detailModal && detailModal.classList.contains('is-open') && window.currentDetailKandidatId) {
                    const btn = document.querySelector(`[data-kand-id="${window.currentDetailKandidatId}"]`);
                    if (btn && typeof window.openDetailKandidatModal === 'function') {
                        try {
                            const kData = JSON.parse(btn.getAttribute('data-kand-json') || '{}');
                            if (kData && kData.id) window.openDetailKandidatModal(kData, false);
                        } catch (_) {}
                    } else {
                        closeModal('detailKandidatModal');
                    }
                }
            }

            // 2. Sync KPI Grid (except on Live Count which has its own JSON polling)
            if (!document.getElementById('liveCountContainer')) {
                const curKpi = document.querySelector('.kpi-grid');
                const newKpi = doc.querySelector('.kpi-grid');
                if (curKpi && newKpi && curKpi.innerHTML !== newKpi.innerHTML) {
                    curKpi.innerHTML = newKpi.innerHTML;
                }
            }

            // 3. Sync Hero Controls
            const curBilikHero = document.getElementById('bilikHeroGlobalControls');
            const newBilikHero = doc.getElementById('bilikHeroGlobalControls');
            if (curBilikHero && newBilikHero && curBilikHero.innerHTML !== newBilikHero.innerHTML) {
                curBilikHero.innerHTML = newBilikHero.innerHTML;
            } else if (!curPanelBem && !document.getElementById('btnToggleLiveBilik') && !document.getElementById('btnToggleLiveLog') && !document.getElementById('liveCountContainer')) {
                const curHero = document.querySelector('.hero');
                const newHero = doc.querySelector('.hero');
                if (curHero && newHero && curHero.innerHTML !== newHero.innerHTML) {
                    curHero.innerHTML = newHero.innerHTML;
                }
            }

            // 4. Sync Fraud Breakdown on Log Keamanan
            const curFraudWrap = document.getElementById('fraudBreakdownWrapper');
            const newFraudWrap = doc.getElementById('fraudBreakdownWrapper');
            if (curFraudWrap && newFraudWrap && curFraudWrap.innerHTML !== newFraudWrap.innerHTML) {
                curFraudWrap.innerHTML = newFraudWrap.innerHTML;
            }

            // 5. Sync Master Kelas Table & Modal Selects on Manajemen Mahasiswa
            const curKelasCard = document.getElementById('kelasMasterCard');
            const newKelasCard = doc.getElementById('kelasMasterCard');
            if (curKelasCard && newKelasCard && curKelasCard.innerHTML !== newKelasCard.innerHTML) {
                curKelasCard.innerHTML = newKelasCard.innerHTML;
            }
            const mhsModal = document.getElementById('mahasiswaModal');
            if (mhsModal && !mhsModal.classList.contains('is-open')) {
                ['mhs_kelas', 'import_kelas_id'].forEach(selId => {
                    const curSel = document.getElementById(selId);
                    const newSel = doc.getElementById(selId);
                    if (curSel && newSel && curSel.innerHTML !== newSel.innerHTML) {
                        const prev = curSel.value;
                        curSel.innerHTML = newSel.innerHTML;
                        curSel.value = prev;
                    }
                });
            }

            // 6. Sync DataTable Cards (preserving search, filter, sort, and pagination)
            const curDtCards = Array.from(document.querySelectorAll('[data-datatable-card]'));
            const newDtCards = Array.from(doc.querySelectorAll('[data-datatable-card]'));
            if (curDtCards.length > 0 && curDtCards.length === newDtCards.length) {
                curDtCards.forEach((card, idx) => {
                    if (card._dtInstance) {
                        card._dtInstance.refreshFromCard(newDtCards[idx]);
                    }
                });
            }

            // 7. Sync Dashboard Grid (when page has no DataTable cards and no Kandidat panels)
            if (curDtCards.length === 0 && !curPanelBem && !document.getElementById('liveCountContainer') && !document.getElementById('portalQrRoot')) {
                const curGrid = document.querySelector('main.content > .grid');
                const newGrid = doc.querySelector('main.content > .grid');
                if (curGrid && newGrid && curGrid.innerHTML !== newGrid.innerHTML) {
                    curGrid.innerHTML = newGrid.innerHTML;
                }
            }
        } catch (_) {
            // Ignore transient network errors during background sync
        } finally {
            isSyncingPage = false;
            if (pendingSyncUrl !== undefined) {
                const nextUrl = pendingSyncUrl;
                pendingSyncUrl = undefined;
                syncCurrentPage(nextUrl);
            }
        }
    }

    async function submitFormAjax(form) {
        const submitBtn = form.querySelector('button[type="submit"], input[type="submit"]');
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.style.opacity = '0.65';
        }

        try {
            const formData = new FormData(form);
            const res = await fetch(form.action, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            const json = await res.json();
            const flashType = json.data?.flash_type || (json.success ? 'success' : 'danger');
            toast(json.message || 'Perubahan telah diproses.', flashType);

            if (json.success) {
                // Close any open form modals
                ['kandidatModal', 'mahasiswaModal', 'kelasModal', 'bilikModal'].forEach(mId => closeModal(mId));

                // Reset import preview box if import form was submitted
                if (form.id === 'confirmImportForm') {
                    const rawInput = document.getElementById('rawImportText');
                    const csvInput = document.getElementById('importCsvFile');
                    const previewBox = document.getElementById('importPreviewBox');
                    const validBadge = document.getElementById('importValidBadge');
                    if (rawInput) rawInput.value = '';
                    if (csvInput) csvInput.value = '';
                    if (previewBox) previewBox.style.display = 'none';
                    if (validBadge) validBadge.style.display = 'none';
                }

                let syncUrl = null;
                if (json.data?.redirect_url) {
                    try {
                        const rUrl = new URL(json.data.redirect_url, window.location.origin);
                        const cUrl = new URL(window.location.href);
                        if (rUrl.searchParams.get('page') === cUrl.searchParams.get('page')) {
                            // If kategori or kelas_id changed in redirect_url, update URL state
                            const rKat = rUrl.searchParams.get('kategori');
                            if (rKat && typeof window.switchKandidatTab === 'function') {
                                window.switchKandidatTab(rKat);
                            }
                            if (cUrl.searchParams.get('page') === 'manajemen_mahasiswa') {
                                window.history.replaceState({}, '', rUrl.toString());
                                syncUrl = rUrl.toString();
                            }
                        }
                    } catch (_) {}
                }

                await syncCurrentPage(syncUrl);
                broadcastSync('mutation');
            }
        } catch (_) {
            toast('Gagal menghubungi server. Periksa koneksi Anda.', 'danger');
        } finally {
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.style.opacity = '';
            }
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        // Mobile drawer toggle (Adminator 2026)
        document.querySelectorAll('[data-drawer-open]').forEach(btn => {
            btn.addEventListener('click', e => {
                e.preventDefault();
                document.body.classList.toggle('has-drawer-open');
            });
        });

        const backdrop = document.getElementById('drawerBackdrop');
        if (backdrop) {
            backdrop.addEventListener('click', () => {
                document.body.classList.remove('has-drawer-open');
            });
        }

        // Modal close bindings
        document.querySelectorAll('[data-modal-close]').forEach(btn => {
            btn.addEventListener('click', () => {
                const target = btn.getAttribute('data-modal-close');
                if (target) closeModal(target);
            });
        });

        document.querySelectorAll('.modal-backdrop').forEach(overlay => {
            overlay.addEventListener('click', e => {
                if (e.target === overlay) overlay.classList.remove('is-open');
            });
        });

        // Init Adminator 2026 DataTables
        initDataTables();

        // Global Zero-Refresh AJAX Form Interceptor (Tambah, Edit, Hapus, Check-In, Import, Kontrol Bilik)
        document.addEventListener('submit', e => {
            const form = e.target;
            if (!form || form.tagName !== 'FORM') return;
            const actionAttr = form.getAttribute('action') || '';
            if (!/action=(kandidat|mahasiswa|bilik)\b/i.test(actionAttr)) return;

            e.preventDefault();
            const confirmMsg = form.getAttribute('data-confirm');
            if (confirmMsg) {
                confirmAction({
                    title: 'Konfirmasi Tindakan',
                    message: confirmMsg,
                    onConfirm: () => submitFormAjax(form)
                });
            } else {
                submitFormAjax(form);
            }
        });

        // Global Zero-Refresh Navigation for Filter Links ([data-ajax-nav])
        document.addEventListener('click', e => {
            const navLink = e.target.closest('a[data-ajax-nav]');
            if (!navLink || e.metaKey || e.ctrlKey || e.shiftKey) return;
            e.preventDefault();
            const href = navLink.href;
            window.history.pushState({}, '', href);
            syncCurrentPage(href);
        });

        window.addEventListener('popstate', () => {
            syncCurrentPage(window.location.href);
        });

        // Listen for Cross-Tab Broadcasts
        if (rtChannel) {
            rtChannel.onmessage = ev => {
                window.dispatchEvent(new CustomEvent('pemira:realtime-sync', { detail: ev.data }));
                if (!window.PEMIRA_LIVE_SYNC_PAUSED) {
                    syncCurrentPage();
                }
            };
        }

        window.addEventListener('storage', ev => {
            if (ev.key === 'pemira_rt_ping' && ev.newValue) {
                window.dispatchEvent(new CustomEvent('pemira:realtime-sync'));
                if (!window.PEMIRA_LIVE_SYNC_PAUSED) {
                    syncCurrentPage();
                }
            }
        });

        // Background Super Real-Time Sync Loop (2.5s) for Admin Pages
        if (document.querySelector('main.content') && !document.getElementById('liveCountContainer') && !document.getElementById('portalQrRoot')) {
            setInterval(() => {
                if (document.hidden || window.PEMIRA_LIVE_SYNC_PAUSED) return;
                syncCurrentPage();
            }, 2500);
        }

        // Interactive Constellation / Plexus Blue Nodes on Sidebar
        const sidebar = document.getElementById('appSidebar');
        const canvas = document.getElementById('sidebarNodeCanvas');
        if (sidebar && canvas && canvas.getContext) {
            const ctx = canvas.getContext('2d');
            const navLinks = Array.from(sidebar.querySelectorAll('.nav-link'));
            const activeLink = sidebar.querySelector('.nav-link.is-active') || navLinks[0];

            let width = 0;
            let height = 0;
            let dpr = Math.min(window.devicePixelRatio || 1, 2);

            let targetX = 24;
            let targetY = 140;
            let focusX = 24;
            let focusY = 140;

            function getLinkFocusPoint(el) {
                if (!el) return { x: 18, y: 140 };
                const sRect = sidebar.getBoundingClientRect();
                const lRect = el.getBoundingClientRect();
                return {
                    x: 18,
                    y: (lRect.top - sRect.top) + sidebar.scrollTop + (lRect.height * 0.5)
                };
            }

            function resizeCanvas() {
                width = sidebar.clientWidth || 216;
                height = Math.max(sidebar.scrollHeight, sidebar.clientHeight || 600);
                canvas.width = width * dpr;
                canvas.height = height * dpr;
                canvas.style.width = width + 'px';
                canvas.style.height = height + 'px';
                ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
            }

            resizeCanvas();
            if (activeLink) {
                const pt = getLinkFocusPoint(activeLink);
                targetX = focusX = pt.x;
                targetY = focusY = pt.y;
            }

            // Create a delicate 6-node plexus cluster ONLY around the active/hovered menu item
            const nodes = [];
            const nodeCount = 6;

            for (let i = 0; i < nodeCount; i++) {
                const angle = (Math.PI * 2 * i) / nodeCount;
                const spread = 8 + Math.random() * 14;
                nodes.push({
                    x: focusX + Math.cos(angle) * spread,
                    y: focusY + Math.sin(angle) * spread,
                    vx: (Math.random() - 0.5) * 0.25,
                    vy: (Math.random() - 0.5) * 0.25,
                    orbitAngle: angle + Math.random() * 0.8,
                    orbitSpeed: (0.012 + Math.random() * 0.014) * (i % 2 === 0 ? 1 : -1),
                    orbitRadiusX: 6 + Math.random() * 13,
                    orbitRadiusY: 6 + Math.random() * 12,
                    offsetX: -4 + Math.random() * 14,
                    offsetY: (Math.random() - 0.5) * 16,
                    r: 1.0 + Math.random() * 0.55,
                    alpha: 0.5 + Math.random() * 0.35
                });
            }

            navLinks.forEach(link => {
                link.addEventListener('mouseenter', () => {
                    const pt = getLinkFocusPoint(link);
                    targetX = pt.x;
                    targetY = pt.y;
                });
                link.addEventListener('focus', () => {
                    const pt = getLinkFocusPoint(link);
                    targetX = pt.x;
                    targetY = pt.y;
                });
            });

            sidebar.addEventListener('mouseleave', () => {
                const pt = getLinkFocusPoint(activeLink);
                targetX = pt.x;
                targetY = pt.y;
            });

            window.addEventListener('resize', () => {
                resizeCanvas();
                const pt = getLinkFocusPoint(activeLink);
                targetX = pt.x;
                targetY = pt.y;
            });

            function animateNodes() {
                ctx.clearRect(0, 0, width, height);

                // Smoothly glide the attractor center toward the hovered/active menu item
                focusX += (targetX - focusX) * 0.1;
                focusY += (targetY - focusY) * 0.1;

                // Update node positions around the focused menu item only
                for (let i = 0; i < nodes.length; i++) {
                    const n = nodes[i];
                    n.orbitAngle += n.orbitSpeed;
                    const desiredX = Math.max(5, Math.min(30, focusX + n.offsetX + Math.cos(n.orbitAngle) * n.orbitRadiusX));
                    const desiredY = focusY + n.offsetY + Math.sin(n.orbitAngle * 1.15) * n.orbitRadiusY;
                    n.x += (desiredX - n.x) * 0.08 + n.vx;
                    n.y += (desiredY - n.y) * 0.08 + n.vy;
                }

                // Draw fine plexus connecting lines between the cluster nodes
                const maxDist = 36;
                for (let i = 0; i < nodes.length; i++) {
                    const a = nodes[i];
                    for (let j = i + 1; j < nodes.length; j++) {
                        const b = nodes[j];
                        const dist = Math.hypot(a.x - b.x, a.y - b.y);
                        if (dist < maxDist) {
                            const lineAlpha = (1 - (dist / maxDist)) * 0.36;
                            ctx.beginPath();
                            ctx.moveTo(a.x, a.y);
                            ctx.lineTo(b.x, b.y);
                            ctx.strokeStyle = `rgba(59, 130, 246, ${lineAlpha.toFixed(3)})`;
                            ctx.lineWidth = 0.65;
                            ctx.stroke();
                        }
                    }
                }

                // Draw each small node with subtle halo
                for (let i = 0; i < nodes.length; i++) {
                    const n = nodes[i];
                    ctx.beginPath();
                    ctx.arc(n.x, n.y, n.r + 0.9, 0, Math.PI * 2);
                    ctx.fillStyle = `rgba(147, 197, 253, ${(n.alpha * 0.25).toFixed(3)})`;
                    ctx.fill();

                    ctx.beginPath();
                    ctx.arc(n.x, n.y, n.r, 0, Math.PI * 2);
                    ctx.fillStyle = `rgba(37, 99, 235, ${n.alpha.toFixed(3)})`;
                    ctx.fill();
                }

                requestAnimationFrame(animateNodes);
            }

            requestAnimationFrame(animateNodes);
        }
    });

    return { openModal, closeModal, confirmAction, toast, initDataTables, syncCurrentPage, broadcastSync };
})();

