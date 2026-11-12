/**
 * Main JS - Inisialisasi Global Sistem PEMIRA
 */

document.addEventListener('DOMContentLoaded', function () {
    // Penutup pesan notifikasi flash (alert-box)
    const closeButtons = document.querySelectorAll('.alert-close');
    closeButtons.forEach(function (btn) {
        btn.addEventListener('click', function () {
            const box = btn.closest('.alert-box');
            if (box) {
                box.remove();
            }
        });
    });

    // Handler tombol konfirmasi hapus global
    document.addEventListener('click', function (e) {
        const deleteTrigger = e.target.closest('[data-confirm-delete]');
        if (!deleteTrigger) {
            return;
        }
        e.preventDefault();

        const formAction = deleteTrigger.getAttribute('data-action') || '';
        const itemId = deleteTrigger.getAttribute('data-id') || '';
        const subAction = deleteTrigger.getAttribute('data-sub-action') || 'delete';
        const message = deleteTrigger.getAttribute('data-confirm-delete') || 'Apakah Anda yakin ingin menghapus data ini?';
        const extraField = deleteTrigger.getAttribute('data-extra-name') || '';
        const extraValue = deleteTrigger.getAttribute('data-extra-value') || '';

        const modal = document.getElementById('modal_konfirmasi_hapus');
        if (!modal) {
            return;
        }

        const form = modal.querySelector('#form_konfirmasi_hapus');
        const msgEl = modal.querySelector('#teks_konfirmasi_hapus');
        const idInput = modal.querySelector('#input_hapus_id');
        const subInput = modal.querySelector('#input_hapus_sub_action');
        const extraInput = modal.querySelector('#input_hapus_extra');

        if (form) form.setAttribute('action', formAction);
        if (msgEl) msgEl.textContent = message;
        if (idInput) idInput.value = itemId;
        if (subInput) subInput.value = subAction;
        if (extraInput) {
            if (extraField !== '') {
                extraInput.setAttribute('name', extraField);
                extraInput.value = extraValue;
            } else {
                extraInput.removeAttribute('name');
                extraInput.value = '';
            }
        }

        if (typeof window.openModal === 'function') {
            window.openModal('modal_konfirmasi_hapus');
        }
    });
});

