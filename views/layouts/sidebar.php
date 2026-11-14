<?php
/**
 * SIDEBAR LAYOUT - Adminator 2026 Redesign Clean Sidebar (No Icons, Text Brand Only)
 */
$currentPage = preg_replace('/[^a-z0-9_]/', '', strtolower((string) ($_GET['page'] ?? 'dashboard')));
$role = $_SESSION['user_role'] ?? 'ADMIN';
$userName = $_SESSION['nama_lengkap'] ?? 'Panitia PEMIRA';
?>
<aside class="d-sidebar" id="appSidebar">
    <canvas class="sidebar-node-canvas" id="sidebarNodeCanvas" aria-hidden="true"></canvas>
    <div class="brand">
        <img src="<?= e(asset_url('img/ubw_logo.png')) ?>" alt="Logo UBW" class="brand-img">
        <div class="brand-text">
            <div class="brand-name">Sistem PEMIRA</div>
            <div class="brand-tag">E-Voting BEM & BPM</div>
        </div>
    </div>

    <nav class="nav-section">
        <div class="nav-label"><?= is_banwasra() ? 'Overview (Viewer)' : 'Overview' ?></div>
        <a href="<?= e(route_url('dashboard')) ?>" class="nav-link <?= $currentPage === 'dashboard' ? 'is-active' : '' ?>">
            <span>Dashboard</span>
            <?php if (is_banwasra()): ?>
                <span class="nav-badge">VIEWER</span>
            <?php endif; ?>
        </a>
    </nav>

    <nav class="nav-section">
        <div class="nav-label">Master &amp; Bilik</div>
        <a href="<?= e(route_url('manajemen_kandidat')) ?>" class="nav-link <?= $currentPage === 'manajemen_kandidat' ? 'is-active' : '' ?>">
            <span>Kandidat &amp; Timses</span>
        </a>
        <a href="<?= e(route_url('portal_qr')) ?>" class="nav-link <?= in_array($currentPage, ['portal_qr', 'manajemen_bilik'], true) ? 'is-active' : '' ?>">
            <span>Portal QR Bilik</span>
            <span class="nav-badge">QR</span>
        </a>
        <a href="<?= e(route_url('manajemen_mahasiswa')) ?>" class="nav-link <?= $currentPage === 'manajemen_mahasiswa' ? 'is-active' : '' ?>">
            <span>Data DPT Mahasiswa</span>
        </a>
        <a href="<?= e(route_url('manajemen_jurusan')) ?>" class="nav-link <?= $currentPage === 'manajemen_jurusan' ? 'is-active' : '' ?>">
            <span>Manajemen Jurusan</span>
        </a>
    </nav>

    <?php if (can_view_logs() || is_superadmin()): ?>
        <nav class="nav-section">
            <div class="nav-label">Pengawasan &amp; Akses</div>
            <?php if (can_view_logs()): ?>
                <?php $activeLogTab = strtolower(trim((string) ($_GET['tab'] ?? $_GET['category'] ?? ''))); ?>
                <a href="<?= e(route_url('log_keamanan', ['tab' => 'pemilih'])) ?>" class="nav-link <?= ($currentPage === 'log_keamanan' && $activeLogTab === 'pemilih') ? 'is-active' : '' ?>">
                    <span>Log Pemilih</span>
                    <span class="nav-badge">BILIK</span>
                </a>
                <a href="<?= e(route_url('log_keamanan', ['tab' => 'admin'])) ?>" class="nav-link <?= ($currentPage === 'log_keamanan' && $activeLogTab === 'admin') ? 'is-active' : '' ?>">
                    <span>Log Admin</span>
                    <span class="nav-badge"><?= is_banwasra() ? 'AUDIT' : 'PANEL' ?></span>
                </a>
            <?php endif; ?>
            <?php if (is_superadmin()): ?>
                <a href="<?= e(route_url('manajemen_user')) ?>" class="nav-link <?= $currentPage === 'manajemen_user' ? 'is-active' : '' ?>">
                    <span>Manajemen Akun</span>
                </a>
            <?php endif; ?>
        </nav>
    <?php endif; ?>

    <nav class="nav-section">
        <div class="nav-label">Publik</div>
        <a href="<?= e(route_url('live_count_bem')) ?>" class="nav-link <?= in_array($currentPage, ['live_count_bem', 'live_count_bpm'], true) ? 'is-active' : '' ?>">
            <span>Live Count</span>
            <span class="nav-badge">BEM/BPM</span>
        </a>
    </nav>
</aside>
