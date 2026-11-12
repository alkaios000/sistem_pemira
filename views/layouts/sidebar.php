<?php
/**
 * SIDEBAR LAYOUT - Adminator 2026 Redesign Clean Sidebar (No Icons, Text Brand Only)
 */
$currentPage = preg_replace('/[^a-z0-9_]/', '', strtolower((string) ($_GET['page'] ?? 'dashboard')));
$role = $_SESSION['user_role'] ?? 'ADMIN';
$userName = $_SESSION['nama_lengkap'] ?? 'Panitia PEMIRA';
?>
<aside class="d-sidebar" id="appSidebar">
    <div class="brand">
        <div class="brand-text">
            <div class="brand-name">Sistem PEMIRA</div>
            <div class="brand-tag">E-Voting BEM & BPM</div>
        </div>
    </div>

    <nav class="nav-section">
        <div class="nav-label">Overview</div>
        <a href="<?= e(route_url('dashboard')) ?>" class="nav-link <?= $currentPage === 'dashboard' ? 'is-active' : '' ?>">
            <span>Dashboard</span>
        </a>
    </nav>

    <nav class="nav-section">
        <div class="nav-label">Master & Bilik</div>
        <a href="<?= e(route_url('manajemen_kandidat')) ?>" class="nav-link <?= $currentPage === 'manajemen_kandidat' ? 'is-active' : '' ?>">
            <span>Kandidat & Timses</span>
        </a>
        <a href="<?= e(route_url('manajemen_bilik')) ?>" class="nav-link <?= $currentPage === 'manajemen_bilik' ? 'is-active' : '' ?>">
            <span>Manajemen Bilik</span>
        </a>
        <a href="<?= e(route_url('manajemen_mahasiswa')) ?>" class="nav-link <?= $currentPage === 'manajemen_mahasiswa' ? 'is-active' : '' ?>">
            <span>Data DPT Mahasiswa</span>
        </a>
    </nav>

    <nav class="nav-section">
        <div class="nav-label">Operasional & Audit</div>
        <a href="<?= e(route_url('portal_qr')) ?>" class="nav-link <?= $currentPage === 'portal_qr' ? 'is-active' : '' ?>">
            <span>Portal QR Bilik</span>
            <span class="nav-badge">QR</span>
        </a>
        <a href="<?= e(route_url('log_keamanan')) ?>" class="nav-link <?= $currentPage === 'log_keamanan' ? 'is-active' : '' ?>">
            <span>Log Keamanan</span>
        </a>
    </nav>

    <nav class="nav-section">
        <div class="nav-label">Publik & Bilik</div>
        <a href="<?= e(route_url('live_count_bem')) ?>" class="nav-link <?= $currentPage === 'live_count_bem' ? 'is-active' : '' ?>">
            <span>Live Count BEM</span>
        </a>
        <a href="<?= e(route_url('live_count_bpm')) ?>" class="nav-link <?= $currentPage === 'live_count_bpm' ? 'is-active' : '' ?>">
            <span>Live Count BPM</span>
        </a>
        <a href="<?= e(route_url('bilik_pemilihan')) ?>" target="_blank" class="nav-link">
            <span>Layar Bilik Suara</span>
            <span class="nav-badge">↗</span>
        </a>
    </nav>

    <div class="sidebar-footer">
        <div class="workspace">
            <div class="workspace-text">
                <div class="workspace-name"><?= e($userName) ?></div>
                <div class="workspace-role"><?= e($role) ?></div>
            </div>
        </div>
    </div>
</aside>
