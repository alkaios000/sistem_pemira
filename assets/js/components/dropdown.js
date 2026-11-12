/**
 * Dropdown Component JS
 */

document.addEventListener('DOMContentLoaded', function () {
    document.addEventListener('click', function (e) {
        const trigger = e.target.closest('[data-dropdown-toggle]');
        const allMenus = document.querySelectorAll('.dropdown-menu.open');

        if (!trigger) {
            allMenus.forEach(function (m) {
                m.classList.remove('open');
            });
            return;
        }

        e.preventDefault();
        const targetId = trigger.getAttribute('data-dropdown-toggle');
        const menu = document.getElementById(targetId);
        if (menu) {
            menu.classList.toggle('open');
        }
    });
});

