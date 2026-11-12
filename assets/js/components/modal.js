/**
 * Modal Component JS - Pengelola Dialog Modal Reusable
 */

window.openModal = function (modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.add('active');
    }
};

window.closeModal = function (modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.remove('active');
    }
};

document.addEventListener('DOMContentLoaded', function () {
    document.addEventListener('click', function (e) {
        const openBtn = e.target.closest('[data-modal-open]');
        if (openBtn) {
            e.preventDefault();
            const targetId = openBtn.getAttribute('data-modal-open');
            window.openModal(targetId);
            return;
        }

        const closeBtn = e.target.closest('[data-modal-close]');
        if (closeBtn) {
            e.preventDefault();
            const targetId = closeBtn.getAttribute('data-modal-close');
            if (targetId) {
                window.closeModal(targetId);
            } else {
                const parentModal = closeBtn.closest('.modal-backdrop');
                if (parentModal) {
                    parentModal.classList.remove('active');
                }
            }
            return;
        }

        if (e.target.classList.contains('modal-backdrop')) {
            e.target.classList.remove('active');
        }
    });
});

