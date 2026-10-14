
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

function eHeader($nilai) {
    return htmlspecialchars((string) $nilai, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= eHeader($judulHalaman ?? 'Dashboard') ?> | CuciKu</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        :root {
            --ck-primary: #087f8c;
            --ck-dark: #075b66;
            --ck-accent: #19b5a5;
            --ck-bg: #f3f7fa;
            --ck-text: #183044;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: var(--ck-bg);
            color: var(--ck-text);
            font-family: 'DM Sans', sans-serif;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .ck-navbar {
            background: rgba(255, 255, 255, .96);
            border-bottom: 1px solid #e5edf1;
            box-shadow: 0 4px 18px #18304405;
            padding: 13px 0;
        }

        .ck-brand {
            color: var(--ck-dark);
            text-decoration: none;
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 27px;
            font-weight: 800;
            letter-spacing: -.8px;
        }

        .ck-brand span {
            color: var(--ck-accent);
        }

        .ck-nav-link {
            color: #637582;
            text-decoration: none;
            font-size: 14px;
            font-weight: 700;
            padding: 10px 13px;
            border-radius: 10px;
            transition: .2s;
        }

        .ck-nav-link:hover {
            color: var(--ck-primary);
            background: #eaf8f6;
        }

        .ck-nav-link.active {
            color: var(--ck-primary);
            background: #e4f7f4;
        }

        .ck-user {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 5px 12px 5px 5px;
            border: 1px solid #e5edf1;
            border-radius: 30px;
            margin-left: 12px;
        }

        .ck-avatar {
            display: grid;
            place-items: center;
            width: 35px;
            height: 35px;
            border-radius: 50%;
            color: white;
            background: linear-gradient(135deg, var(--ck-primary), var(--ck-accent));
            font-weight: 800;
        }

        .ck-user-name {
            max-width: 135px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            font-size: 12px;
            font-weight: 800;
        }

        .ck-user-role {
            color: #84929c;
            font-size: 10px;
        }

        .ck-logout {
            color: #c24141;
            border: 1px solid #f0dada;
            border-radius: 10px;
            text-decoration: none;
            font-weight: 700;
            font-size: 13px;
            padding: 10px 13px;
            margin-left: 12px;
            transition: .2s;
        }

        .ck-logout:hover {
            color: #a32121;
            background: #fff0f0;
        }

        .ck-main {
            flex: 1;
            width: 100%;
        }

        @media (max-width: 991px) {
            .ck-nav-link {
                display: block;
                margin-top: 5px;
            }

            .ck-user {
                margin: 12px 0 5px;
                width: fit-content;
            }

            .ck-logout {
                display: inline-block;
                margin: 8px 0;
            }
        }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg ck-navbar">
    <div class="container">
        <a class="ck-brand" href="index.php">Cuci<span>Ku.</span></a>

        <button class="navbar-toggler" type="button"
                data-bs-toggle="collapse"
                data-bs-target="#ckMenu"
                aria-controls="ckMenu"
                aria-expanded="false"
                aria-label="Buka menu">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="ckMenu">
            <div class="navbar-nav ms-auto align-items-lg-center">
                <a class="ck-nav-link <?= $halamanAktif === 'dashboard' ? 'active' : '' ?>"
                   href="index.php">Dashboard</a>

                <a class="ck-nav-link <?= $halamanAktif === 'pesan' ? 'active' : '' ?>"
                   href="buat-pesanan.php">Pesan Laundry</a>

                <a class="ck-nav-link <?= $halamanAktif === 'riwayat' ? 'active' : '' ?>"
                   href="riwayat.php">Riwayat Pesanan</a>

                <div class="ck-user">
                    <div class="ck-avatar">
                        <?= eHeader(strtoupper(substr($namaPengguna, 0, 1))) ?>
                    </div>
                    <div>
                        <div class="ck-user-name"><?= eHeader($namaPengguna) ?></div>
                        <div class="ck-user-role">Pelanggan CuciKu</div>
                    </div>
                </div>

                <a class="ck-logout"
                   href="../logout.php"
                   onclick="return confirm('Yakin ingin keluar dari akun?')">
                    ↗ Logout
                </a>
            </div>
        </div>
    </div>
</nav>

<main class="ck-main">
