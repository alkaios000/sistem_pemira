/**
 * Sidebar Component JS - Navigasi Responsif Tablet & Mobile
 */

document.addEventListener('DOMContentLoaded', function () {
    const toggleBtn = document.getElementById('sidebar_toggle_btn');
    const sidebar = document.getElementById('app_sidebar');
    const backdrop = document.getElementById('sidebar_backdrop');

    if (!toggleBtn || !sidebar) {
        return;
    }

    function closeSidebar() {
        sidebar.classList.remove('open');
        if (backdrop) {
            backdrop.classList.remove('show');
        }
    }

    toggleBtn.addEventListener('click', function () {
        sidebar.classList.toggle('open');
        if (backdrop) {
            backdrop.classList.toggle('show');
        }
    });

    if (backdrop) {
        backdrop.addEventListener('click', closeSidebar);
    }
});

