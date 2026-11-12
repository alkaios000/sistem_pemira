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
     * Enhances any container with [data-datatable-card]
     */
    function initDataTables() {
        document.querySelectorAll('[data-datatable-card]').forEach(card => {
            const table = card.querySelector('table.data-table');
            if (!table || !table.tBodies[0]) return;

            const tbody = table.tBodies[0];
            const allRows = Array.from(tbody.querySelectorAll('tr.data-row'));
            if (allRows.length === 0) return;

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
                        td.textContent = 'Tidak ada data yang cocok dengan filter pencarian.';
                        emptyRow.appendChild(td);
                        tbody.appendChild(emptyRow);
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

            render();
        });
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
    });

    return { openModal, closeModal, confirmAction, toast, initDataTables };
})();

