<?php

$https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $https,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);

    session_start();
}



require_once __DIR__ . '/includes/database.php';
require_once __DIR__ . '/includes/helpers.php';

// Arahkan pengguna yang sudah login ke dashboard masing-masing.
if (!empty($_SESSION['admin_logged_in'])) {
    header('Location: admin/index.php');
    exit;
}

if (!empty($_SESSION['pelanggan_logged_in'])) {
    header('Location: pengguna/index.php');
    exit;
}

$error = '';
$berhasil = '';

if (isset($_GET['daftar']) && $_GET['daftar'] === 'berhasil') {
    $berhasil = 'Registrasi berhasil! Silakan login menggunakan akun kamu.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usernameEmail = trim($_POST['username_email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($usernameEmail === '' || $password === '') {
        $error = 'Username/email dan password wajib diisi.';
    } else {
        try {
            // 1. Coba login sebagai admin.
            $stmt = $pdo->prepare("
                SELECT id, nama, username, password
                FROM admins
                WHERE username = ?
                LIMIT 1
            ");
            $stmt->execute([$usernameEmail]);
            $admin = $stmt->fetch();

            if ($admin && password_verify($password, $admin['password'])) {
                session_regenerate_id(true);

                $_SESSION['admin_logged_in'] = true;
                $_SESSION['admin_id'] = $admin['id'];
                $_SESSION['admin_name'] = $admin['nama'];

                $_SESSION['admin_username'] = $admin['username'];

                // Cookie penanda login admin selama 7 hari.
                // Jangan menyimpan password di cookie.
                setcookie('cuciku_role', 'admin', [
                    'expires' => time() + (7 * 24 * 60 * 60),
                    'path' => '/',
                    'secure' => $https,
                    'httponly' => true,
                    'samesite' => 'Lax'
                ]);

                header('Location: admin/index.php');
                exit;
            }

            // 2. Jika bukan admin, coba login sebagai pelanggan.
            $stmt = $pdo->prepare("
                SELECT id_pelanggan, nama, email, password
                FROM pelanggan
                WHERE email = ? OR no_hp = ? OR nama = ?
                LIMIT 1
            ");
            $stmt->execute([$usernameEmail, $usernameEmail, $usernameEmail]);
            $pelanggan = $stmt->fetch();

            if ($pelanggan && password_verify($password, $pelanggan['password'])) {
                session_regenerate_id(true);

                $_SESSION['pelanggan_logged_in'] = true;
                $_SESSION['pelanggan_id'] = $pelanggan['id_pelanggan'];
                $_SESSION['pelanggan_nama'] = $pelanggan['nama'];
                $_SESSION['pelanggan_email'] = $pelanggan['email'];

                setcookie('cuciku_role', 'pelanggan', [
                    'expires' => time() + (7 * 24 * 60 * 60),
                    'path' => '/',
                    'secure' => $https,
                    'httponly' => true,
                    'samesite' => 'Lax'
                ]);

                header('Location: pengguna/index.php');
                exit;
            }

            $error = 'Username/email atau password salah.';
        } catch (PDOException $e) {
            error_log($e->getMessage());
            $error = 'Login gagal diproses. Periksa koneksi database dan struktur tabel.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | CuciKu</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            font-family: 'DM Sans', sans-serif;
            background: #f3f8fa;
            color: #183044;
        }

        .page {
            min-height: 100vh;
            display: grid;
            grid-template-columns: 1.05fr 0.95fr;
        }

        .left-panel {
            background: linear-gradient(145deg, #064e58, #087f8c 55%, #20b7a6);
            color: white;
            padding: 55px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            position: relative;
            overflow: hidden;
        }

        .left-panel::before,
        .left-panel::after {
            content: "";
            position: absolute;
            border: 1px solid #ffffff20;
            border-radius: 50%;
            pointer-events: none;
        }

        .left-panel::before {
            width: 400px;
            height: 400px;
            right: -160px;
            top: 100px;
        }

        .left-panel::after {
            width: 280px;
            height: 280px;
            right: -100px;
            top: 160px;
        }

        .brand {
            position: relative;
            z-index: 1;
            color: white;
            text-decoration: none;
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 30px;
            font-weight: 800;
        }

        .brand span {
            color: #7ef2d8;
        }

        .hero-content {
            max-width: 510px;
            position: relative;
            z-index: 1;
            margin: 55px 0;
        }

        .eyebrow {
            display: inline-block;
            padding: 8px 13px;
            border: 1px solid #ffffff45;
            background: #ffffff12;
            border-radius: 30px;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 1px;
        }

        .hero-content h1 {
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: clamp(34px, 4vw, 52px);
            line-height: 1.2;
            font-weight: 800;
            margin: 24px 0 18px;
        }

        .hero-content p {
            color: #dcf8f5;
            font-size: 16px;
            line-height: 1.8;
        }

        .features {
            display: grid;
            gap: 15px;
            margin-top: 32px;
        }

        .feature {
            display: flex;
            align-items: center;
            gap: 13px;
            color: #effffb;
            font-size: 14px;
        }

        .feature-icon {
            width: 39px;
            height: 39px;
            display: grid;
            place-items: center;
            flex-shrink: 0;
            border-radius: 12px;
            background: #ffffff1b;
            font-size: 18px;
        }

        .left-footer {
            position: relative;
            z-index: 1;
            color: #d0f0ec;
            font-size: 12px;
        }

        .right-panel {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 45px 30px;
        }

        .login-card {
            width: 100%;
            max-width: 425px;
        }

        .mobile-brand {
            display: none;
            color: #087f8c;
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 28px;
            font-weight: 800;
            text-decoration: none;
            margin-bottom: 35px;
        }

        .mobile-brand span {
            color: #19b5a5;
        }

        .login-heading {
            margin-bottom: 30px;
        }

        .login-heading .small-title {
            color: #087f8c;
            font-size: 12px;
            font-weight: 800;
            letter-spacing: 1.4px;
            text-transform: uppercase;
        }

        .login-heading h2 {
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 32px;
            font-weight: 800;
            margin: 12px 0;
        }

        .login-heading p {
            color: #718096;
            font-size: 14px;
            line-height: 1.7;
        }

        .form-label {
            font-weight: 700;
            font-size: 13px;
            margin-bottom: 9px;
        }

        .form-control {
            min-height: 51px;
            border-radius: 12px;
            border: 1px solid #dfe8ed;
            background: #fbfdfe;
            padding: 13px 15px;
            font-size: 14px;
        }

        .form-control:focus {
            border-color: #18a99c;
            box-shadow: 0 0 0 4px #18a99c18;
            background: white;
        }

        .password-wrap {
            position: relative;
        }

        .password-wrap .form-control {
            padding-right: 65px;
        }

        .show-password {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            border: 0;
            background: transparent;
            color: #087f8c;
            font-size: 12px;
            font-weight: 700;
        }

        .btn-login {
            width: 100%;
            min-height: 52px;
            background: linear-gradient(100deg, #087f8c, #15a997);
            color: white;
            border: 0;
            border-radius: 12px;
            font-weight: 800;
            margin-top: 8px;
            box-shadow: 0 9px 20px #087f8c22;
            transition: .2s;
        }

        .btn-login:hover {
            color: white;
            transform: translateY(-1px);
            box-shadow: 0 12px 25px #087f8c35;
        }

        .register-text {
            text-align: center;
            color: #718096;
            font-size: 13px;
            margin-top: 25px;
        }

        .register-text a {
            color: #087f8c;
            text-decoration: none;
            font-weight: 800;
        }

        .secure-note {
            margin-top: 28px;
            padding: 13px;
            background: #eaf8f5;
            border-radius: 12px;
            color: #34776f;
            font-size: 12px;
            text-align: center;
        }

        @media (max-width: 850px) {
            .page {
                grid-template-columns: 1fr;
            }

            .left-panel {
                display: none;
            }

            .right-panel {
                min-height: 100vh;
                padding: 35px 22px;
            }

            .login-card {
                max-width: 460px;
            }

            .mobile-brand {
                display: inline-block;
            }
        }

        @media (max-width: 480px) {
            .login-heading h2 {
                font-size: 28px;
            }
        }
    </style>
</head>

<body>

    <div class="page">

        <aside class="left-panel">
            <a class="brand" href="login.php">Cuci<span>Ku.</span></a>

            <div class="hero-content">
                <span class="eyebrow">LAUNDRY SERVICE</span>
                <h1>Pakaian bersih,<br>hari lebih nyaman.</h1>
                <p>
                    Kelola pesanan laundry dengan mudah. Pesan layanan,
                    pantau proses pencucian, dan cek riwayat dalam satu tempat.
                </p>

                <div class="features">
                    <div class="feature">
                        <div class="feature-icon"><i class="bi bi-bag-check"></i></div>
                        <span>Pemesanan laundry lebih praktis</span>
                    </div>
                    <div class="feature">
                        <div class="feature-icon"><i class="bi bi-clock-history"></i></div>
                        <span>Pantau status dan estimasi selesai</span>
                    </div>
                    <div class="feature">
                        <div class="feature-icon"><i class="bi bi-receipt-cutoff"></i></div>
                        <span>Nomor nota dan riwayat pesanan</span>
                    </div>
                </div>
            </div>

            <div class="left-footer">
                © <?= date('Y') ?> CuciKu. Laundry Bersih, Hidup Lebih Praktis.
            </div>
        </aside>

        <main class="right-panel">
            <div class="login-card">

                <a class="mobile-brand" href="login.php">Cuci<span>Ku.</span></a>

                <div class="login-heading">
                    <div class="small-title">Selamat Datang Kembali</div>
                    <h2>Masuk ke CuciKu</h2>
                    <p>Masukkan akun kamu untuk melanjutkan ke dashboard.</p>
                </div>

                <?php if ($berhasil !== ''): ?>
                    <div class="alert alert-success">
                        <?= e($berhasil) ?>
                    </div>
                <?php endif; ?>

                <?php if ($error !== ''): ?>
                    <div class="alert alert-danger">
                        <?= e($error) ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="login.php" onsubmit="const btn=this.querySelector('button[type=submit]'); if(btn){btn.disabled=true; btn.innerHTML='<span class=\'spinner-border spinner-border-sm me-2\'></span>Memproses...';}">
                    <div class="mb-4">
                        <label for="username_email" class="form-label">
                            Username Admin / Email Pengguna
                        </label>
                        <input
                            type="text"
                            name="username_email"
                            id="username_email"
                            class="form-control"
                            placeholder="Masukkan username atau email"
                            value="<?= e($_POST['username_email'] ?? '') ?>"
                            autocomplete="username"
                            required
                            autofocus>
                    </div>

                    <div class="mb-4">
                        <label for="password" class="form-label">Password</label>
                        <div class="password-wrap">
                            <input
                                type="password"
                                name="password"
                                id="password"
                                class="form-control"
                                placeholder="Masukkan password"
                                autocomplete="current-password"
                                required>
                            <button type="button" class="show-password"
                                id="togglePassword" aria-label="Tampilkan atau sembunyikan password">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-login">
                        <i class="bi bi-box-arrow-in-right me-2"></i> Masuk ke Akun
                    </button>
                </form>

                <div class="register-text">
                    Belum punya akun pengguna?
                    <a href="register.php">Daftar sekarang</a>
                </div>

                <div class="secure-note">
                    <i class="bi bi-shield-check me-1"></i> Akun kamu dilindungi melalui proses autentikasi.
                </div>
            </div>
        </main>

    </div>

    <script>
        const passwordInput = document.getElementById('password');
        const togglePassword = document.getElementById('togglePassword');

        togglePassword.addEventListener('click', function() {
            const sedangTampil = passwordInput.type === 'text';

            passwordInput.type = sedangTampil ? 'password' : 'text';
            togglePassword.innerHTML = sedangTampil
                ? '<i class="bi bi-eye"></i>'
                : '<i class="bi bi-eye-slash"></i>';
        });
    </script>

</body>

</html>