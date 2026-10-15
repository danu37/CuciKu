<?php
/**
 * header.php — Header dan navigasi halaman admin.
 *
 * Memverifikasi session admin, mengambil jumlah notifikasi dari
 * database, dan menampilkan sidebar + topbar admin.
 */

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Proteksi: hanya admin yang boleh mengakses.
if (empty($_SESSION['admin_logged_in'])) {
    header('Location: ../login.php');
    exit;
}

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/helpers.php';

if (!isset($pageTitle)) {
    $pageTitle = 'LaundryKu';
}

$currentPage = basename($_SERVER['PHP_SELF']);
$adminName   = $_SESSION['admin_name'] ?? 'Admin';

// Hitung jumlah transaksi siap diambil dari database.
$readyCount = 0;

if (isset($pdo)) {
    try {
        $stmtReady = $pdo->query(
            "SELECT COUNT(*) FROM transaksi WHERE status = 'ready'"
        );
        $readyCount = (int) $stmtReady->fetchColumn();
    } catch (PDOException $e) {
        error_log($e->getMessage());
    }
}

// Hitung total order dari database untuk badge sidebar.
$totalOrderCount = 0;

if (isset($pdo)) {
    try {
        $stmtTotal = $pdo->query(
            "SELECT COUNT(*) FROM transaksi
             WHERE status NOT IN ('completed', 'cancelled')"
        );
        $totalOrderCount = (int) $stmtTotal->fetchColumn();
    } catch (PDOException $e) {
        error_log($e->getMessage());
    }
}

// Hitung total pelanggan terdaftar untuk badge sidebar.
$totalCustomerCount = 0;

if (isset($pdo)) {
    try {
        $stmtCustomers = $pdo->query("SELECT COUNT(*) FROM pelanggan");
        $totalCustomerCount = (int) $stmtCustomers->fetchColumn();
    } catch (PDOException $e) {
        error_log($e->getMessage());
    }
}
?>
<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="theme-color" content="#f5f7fb"><title><?= e($pageTitle) ?> | LaundryKu</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet"><link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="../assets/css/style.css?v=<?= filemtime(__DIR__ . '/../assets/css/style.css') ?>" rel="stylesheet">
<link href="../assets/css/custom.css?v=<?= filemtime(__DIR__ . '/../assets/css/custom.css') ?>" rel="stylesheet">
<style>
/* Modern Elegant Green Sidebar with Crisp White Hover */
.sidebar, aside.sidebar {
    width: 248px !important;
    background: linear-gradient(180deg, #1b6359 0%, #11473f 100%) !important;
    border-right: 1px solid rgba(255, 255, 255, 0.12) !important;
    color: rgba(255, 255, 255, 0.9) !important;
}
.sidebar::-webkit-scrollbar-thumb {
    background: rgba(255, 255, 255, 0.2) !important;
}
.sidebar .brand-lockup {
    border-bottom: 1px solid rgba(255, 255, 255, 0.15) !important;
}
.sidebar .brand-icon {
    background: rgba(255, 255, 255, 0.2) !important;
    border: 1px solid rgba(255, 255, 255, 0.35) !important;
    color: #ffffff !important;
    box-shadow: 0 4px 14px rgba(0, 0, 0, 0.15) !important;
}
.sidebar .brand-name {
    color: #ffffff !important;
}
.sidebar .brand-caption {
    color: rgba(255, 255, 255, 0.78) !important;
}
.sidebar .menu-label {
    color: rgba(255, 255, 255, 0.65) !important;
}
.sidebar-nav .nav-link {
    color: rgba(255, 255, 255, 0.88) !important;
    background: transparent !important;
    border: 1px solid transparent !important;
    transition: all .2s cubic-bezier(0.4, 0, 0.2, 1) !important;
}
.sidebar-nav .nav-link i {
    color: rgba(255, 255, 255, 0.78) !important;
    transition: color .2s ease, transform .2s ease !important;
}
.sidebar-nav .nav-link:hover {
    background: #ffffff !important;
    color: #11473f !important;
    box-shadow: 0 4px 14px rgba(0, 0, 0, 0.15) !important;
    transform: translateX(3px) !important;
}
.sidebar-nav .nav-link:hover i {
    color: #17665b !important;
    transform: scale(1.08) !important;
}
.sidebar-nav .nav-link:hover .nav-count {
    background: #d9f3ee !important;
    color: #0d3d36 !important;
}
.sidebar-nav .nav-link.active {
    background: #ffffff !important;
    color: #0e3f37 !important;
    font-weight: 700 !important;
    border-color: rgba(255, 255, 255, 0.9) !important;
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.18) !important;
}
.sidebar-nav .nav-link.active i {
    color: #165b51 !important;
}
.sidebar .nav-count {
    background: rgba(255, 255, 255, 0.2) !important;
    color: #ffffff !important;
}
.sidebar-nav .nav-link.active .nav-count {
    background: #cef0e8 !important;
    color: #0e3f37 !important;
}
.sidebar .nav-dot {
    background: #fbbf24 !important;
    box-shadow: 0 0 8px rgba(251, 191, 36, 0.8) !important;
}
.sidebar .help-card {
    background: rgba(255, 255, 255, 0.08) !important;
    border: 1px solid rgba(255, 255, 255, 0.16) !important;
    border-radius: 14px !important;
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.1) !important;
}
.sidebar .help-icon {
    background: rgba(255, 255, 255, 0.18) !important;
    color: #ffffff !important;
    border: 1px solid rgba(255, 255, 255, 0.28) !important;
}
.sidebar .help-title {
    color: #ffffff !important;
}
.sidebar .help-card p {
    color: rgba(255, 255, 255, 0.8) !important;
}
.sidebar .help-link {
    background: rgba(255, 255, 255, 0.16) !important;
    color: #ffffff !important;
    padding: 5px 11px !important;
    border-radius: 8px !important;
    font-weight: 700 !important;
    display: inline-flex !important;
    align-items: center !important;
    gap: 6px !important;
    transition: all .15s ease !important;
}
.sidebar .help-link:hover {
    background: #ffffff !important;
    color: #11473f !important;
    gap: 8px !important;
}
.sidebar .profile-mini {
    border-top: 1px solid rgba(255, 255, 255, 0.15) !important;
}
.sidebar .profile-avatar {
    background: #ffffff !important;
    color: #11473f !important;
    border: 2px solid rgba(255, 255, 255, 0.35) !important;
    font-weight: 800 !important;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15) !important;
}
.sidebar .profile-text strong {
    color: #ffffff !important;
}
.sidebar .profile-text small {
    color: rgba(255, 255, 255, 0.75) !important;
}
.sidebar .profile-more {
    color: rgba(255, 255, 255, 0.85) !important;
}
.sidebar .profile-more:hover {
    color: #ffffff !important;
    background: rgba(239, 68, 68, 0.28) !important;
    transform: translateX(2px) !important;
}
@media (max-width: 991.98px) {
    .sidebar {
        box-shadow: 18px 0 45px rgba(0, 0, 0, 0.35) !important;
    }
    .sidebar-overlay.show {
        background: rgba(12, 45, 40, 0.7) !important;
        backdrop-filter: blur(2px) !important;
    }
}
</style>
</head><body>
<div class="app-shell"><div class="sidebar-overlay" id="sidebarOverlay"></div><aside class="sidebar" id="sidebar">
<a href="index.php" class="brand-lockup text-decoration-none"><span class="brand-icon"><i class="bi bi-droplet-half"></i></span><span><span class="brand-name">LaundryKu</span><span class="brand-caption">Sistem antrean laundry</span></span></a>
<div class="menu-label">MENU UTAMA</div><nav class="sidebar-nav" aria-label="Navigasi utama">
<a class="nav-link <?= $currentPage==='index.php'?'active':'' ?>" href="index.php"><i class="bi bi-grid-1x2-fill"></i><span>Dashboard</span></a>
<a class="nav-link <?= in_array($currentPage,['antrean.php','edit.php'],true)?'active':'' ?>" href="antrean.php"><i class="bi bi-list-ol"></i><span>Antrean & Order</span><span class="nav-count"><?= $totalOrderCount ?></span></a>
<a class="nav-link <?= $currentPage==='pelanggan.php'?'active':'' ?>" href="pelanggan.php"><i class="bi bi-people-fill"></i><span>Pelanggan</span><span class="nav-count"><?= $totalCustomerCount ?></span></a>
<a class="nav-link <?= $currentPage==='notifikasi.php'?'active':'' ?>" href="notifikasi.php"><i class="bi bi-bell"></i><span>Notifikasi</span><?php if($readyCount): ?><span class="nav-dot"></span><?php endif; ?></a>
<a class="nav-link <?= $currentPage==='nota-hilang.php'?'active':'' ?>" href="nota-hilang.php"><i class="bi bi-receipt-cutoff"></i><span>Nota Hilang</span></a>
</nav><div class="sidebar-bottom"><div class="help-card"><div class="help-icon"><i class="bi bi-shield-check"></i></div><div class="help-title">Struk tetap aman</div><p>Gunakan kode transaksi untuk mencari kembali struk digital.</p><a href="nota-hilang.php" class="help-link">Cari struk <i class="bi bi-arrow-right"></i></a></div><div class="profile-mini"><div class="profile-avatar"><?= e(strtoupper(substr($adminName, 0, 2))) ?></div><div class="profile-text"><strong><?= e($adminName) ?></strong><small>Administrator</small></div><a class="profile-more" href="../logout.php" title="Keluar" aria-label="Keluar" onclick="return confirm('Yakin ingin keluar dari akun admin?');"><i class="bi bi-box-arrow-right"></i></a></div></div></aside>
<main class="main-content"><header class="topbar"><div class="d-flex align-items-center gap-3"><button class="mobile-menu-btn" id="mobileMenuBtn" type="button" aria-label="Buka menu"><i class="bi bi-list"></i></button><div><div class="topbar-eyebrow">HALO, <?= e(strtoupper($adminName)) ?></div><div class="topbar-date"><?= date('l, d F Y') ?></div></div></div><div class="topbar-actions"><div class="system-status"><span class="live-dot"></span> Sistem operasional</div><a class="notification-button position-relative" href="notifikasi.php" aria-label="Notifikasi"><i class="bi bi-bell"></i><?php if($readyCount): ?><span class="notification-count"><?= $readyCount ?></span><?php endif; ?></a><div class="topbar-admin"><div class="profile-avatar"><?= e(strtoupper(substr($adminName, 0, 2))) ?></div><span><?= e($adminName) ?></span><a href="../logout.php" class="topbar-logout" title="Keluar" aria-label="Keluar" onclick="return confirm('Yakin ingin keluar dari akun admin?');"><i class="bi bi-box-arrow-right"></i></a></div></div></header><div class="page-content">
