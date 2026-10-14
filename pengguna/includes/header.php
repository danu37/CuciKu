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

$namaPengguna = $_SESSION['pelanggan_nama'] ?? 'Pelanggan';
$halamanAktif = $halamanAktif ?? 'dashboard';
$inisialPengguna = strtoupper(substr($namaPengguna, 0, 1));

function eHeader($nilai)
{
    return htmlspecialchars((string) $nilai, ENT_QUOTES, 'UTF-8');
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
    <link href="/cuciKu/assets/css/style.css" rel="stylesheet">
    <link href="/cuciKu/assets/css/custom.css" rel="stylesheet">

    <style>
        .customer-brand {
            color: #182538;
            font-family: Manrope, sans-serif;
            font-size: 17px;
            font-weight: 800;
            letter-spacing: -.4px;
        }

        .customer-brand span {
            color: var(--brand);
        }

        .customer-logout {
            display: grid;
            place-items: center;
            color: #8a96a5;
            font-size: 16px;
            padding: 5px;
            text-decoration: none;
        }

        .customer-logout:hover {
            color: #c24141;
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
                <i class="bi bi-receipt"></i>
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
