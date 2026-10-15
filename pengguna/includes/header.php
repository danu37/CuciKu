<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (
    empty($_SESSION['pelanggan_logged_in']) ||
    empty($_SESSION['pelanggan_id'])
) {
    header('Location: ../login.php');
    exit;
}

require_once __DIR__ . '/../../includes/helpers.php';

$namaPengguna = $_SESSION['pelanggan_nama'] ?? 'Pelanggan';
$halamanAktif = $halamanAktif ?? 'dashboard';
$inisialPengguna = strtoupper(substr($namaPengguna, 0, 1));

function eHeader($nilai)
{
    return e($nilai);
}
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#f5f7fb">
    <title><?= eHeader($judulHalaman ?? 'Dashboard') ?> | CuciKu</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="../assets/css/style.css?v=<?= filemtime(__DIR__ . '/../../assets/css/style.css') ?>" rel="stylesheet">
    <link href="../assets/css/custom.css?v=<?= filemtime(__DIR__ . '/../../assets/css/custom.css') ?>" rel="stylesheet">

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

        .customer-brand {
            color: #ffffff !important;
            font-family: Manrope, sans-serif;
            font-size: 17px;
            font-weight: 800;
            letter-spacing: -.4px;
        }

        .customer-brand span {
            color: #a7f3d0 !important;
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

        .customer-logout {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: rgba(255, 255, 255, 0.85) !important;
            font-size: 16px;
            width: 32px;
            height: 32px;
            border-radius: 8px;
            text-decoration: none;
            transition: all .15s ease;
        }

        .customer-logout:hover {
            color: #ffffff !important;
            background: rgba(239, 68, 68, 0.28) !important;
            transform: translateX(2px);
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

        @media (max-height: 720px) {
            .customer-help-card {
                display: none;
            }
        }
    </style>
</head>
<body>
<div class="app-shell">
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <aside class="sidebar" id="sidebar">
        <a href="index.php" class="brand-lockup text-decoration-none">
            <span class="brand-icon"><i class="bi bi-droplet-half"></i></span>
            <span>
                <span class="brand-name customer-brand">Cuci<span>Ku</span></span>
                <span class="brand-caption">Layanan laundry pelanggan</span>
            </span>
        </a>

        <div class="menu-label">MENU PELANGGAN</div>
        <nav class="sidebar-nav" aria-label="Navigasi pelanggan">
            <a class="nav-link <?= $halamanAktif === 'dashboard' ? 'active' : '' ?>"
               href="index.php">
                <i class="bi bi-grid-1x2-fill"></i>
                <span>Dashboard</span>
            </a>
            <a class="nav-link <?= $halamanAktif === 'pesan' ? 'active' : '' ?>"
               href="buat-pesanan.php">
                <i class="bi bi-bag-plus"></i>
                <span>Pesan Laundry</span>
            </a>
            <a class="nav-link <?= $halamanAktif === 'struk' ? 'active' : '' ?>"
               href="cek-nota.php">
                <i class="bi bi-receipt-cutoff"></i>
                <span>Struk</span>
            </a>
            <a class="nav-link <?= $halamanAktif === 'riwayat' ? 'active' : '' ?>"
               href="riwayat.php">
                <i class="bi bi-clock-history"></i>
                <span>Riwayat Pesanan</span>
            </a>
        </nav>

        <div class="sidebar-bottom">
            <div class="help-card customer-help-card">
                <div class="help-icon"><i class="bi bi-shield-check"></i></div>
                <div class="help-title">Pesanan terpantau</div>
                <p>Pantau proses laundry dan simpan nomor nota untuk melihat detail pesanan.</p>
                <a href="riwayat.php" class="help-link">
                    Lihat pesanan <i class="bi bi-arrow-right"></i>
                </a>
            </div>

            <div class="profile-mini">
                <div class="profile-avatar"><?= eHeader($inisialPengguna) ?></div>
                <div class="profile-text">
                    <strong><?= eHeader($namaPengguna) ?></strong>
                    <small>Pelanggan CuciKu</small>
                </div>
                <a class="customer-logout" href="../logout.php"
                   title="Keluar" aria-label="Keluar"
                   onclick="return confirm('Yakin ingin keluar dari akun?')">
                    <i class="bi bi-box-arrow-right"></i>
                </a>
            </div>
        </div>
    </aside>

    <main class="main-content">
        <header class="topbar">
            <div class="d-flex align-items-center gap-3">
                <button class="mobile-menu-btn" id="mobileMenuBtn" type="button"
                        aria-label="Buka menu">
                    <i class="bi bi-list"></i>
                </button>
                <div>
                    <div class="topbar-eyebrow">HALO, <?= eHeader(strtoupper($namaPengguna)) ?></div>
                    <div class="topbar-date"><?= date('l, d F Y') ?></div>
                </div>
            </div>

            <div class="topbar-actions">
                <div class="system-status">
                    <span class="live-dot"></span> Akun pelanggan aktif
                </div>
                <div class="topbar-admin">
                    <div class="profile-avatar"><?= eHeader($inisialPengguna) ?></div>
                    <span><?= eHeader($namaPengguna) ?></span>
                </div>
            </div>
        </header>

        <div class="page-content">
