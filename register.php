<?php
/**
 * register.php — Halaman registrasi akun pelanggan baru.
 */

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/includes/database.php';
require_once __DIR__ . '/includes/helpers.php';

$error = '';
$nama = '';
$no_hp = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama        = trim($_POST['nama'] ?? '');
    $no_hp       = trim($_POST['no_hp'] ?? '');
    $email       = trim($_POST['email'] ?? '');
    $password    = $_POST['password'] ?? '';
    $konfirmasi  = $_POST['konfirmasi_password'] ?? '';

    if ($nama === '' || $no_hp === '' || $email === '' || $password === '' || $konfirmasi === '') {
        $error = 'Semua kolom wajib diisi.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Format email tidak valid.';
    } elseif (strlen($password) < 8) {
        $error = 'Password minimal 8 karakter.';
    } elseif ($password !== $konfirmasi) {
        $error = 'Konfirmasi password tidak sama.';
    } else {
        try {
            $stmt = $pdo->prepare(
                'SELECT id_pelanggan FROM pelanggan WHERE email = ? OR no_hp = ? LIMIT 1'
            );
            $stmt->execute([$email, $no_hp]);

            if ($stmt->fetch()) {
                $error = 'Email atau nomor HP sudah terdaftar. Silakan gunakan data lain.';
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);

                $stmt = $pdo->prepare(
                    'INSERT INTO pelanggan (nama, no_hp, email, password) VALUES (?, ?, ?, ?)'
                );
                $stmt->execute([$nama, $no_hp, $email, $hash]);

                header('Location: login.php?daftar=berhasil');
                exit;
            }
        } catch (PDOException $e) {
            error_log('Registrasi gagal: ' . $e->getMessage());
            $error = 'Terjadi kesalahan sistem saat mendaftar. Silakan coba lagi.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Register Pelanggan | CuciKu</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@600;700;800&display=swap" rel="stylesheet">

    <style>
        :root {
            --brand: #267b72;
            --brand-dark: #1f665f;
            --brand-light: #e7f5f2;
        }

        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            background: linear-gradient(135deg, #e7f5f2, #f5f8fa, #edf2f7);
            font-family: 'DM Sans', system-ui, sans-serif;
            color: #1e293b;
        }

        .register-card {
            width: 100%;
            max-width: 480px;
            padding: 34px 30px;
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 20px;
            box-shadow: 0 15px 45px rgba(24, 48, 68, .07);
        }

        .brand {
            color: var(--brand);
            font-family: 'Manrope', sans-serif;
            font-size: 26px;
            font-weight: 800;
            text-align: center;
            margin-bottom: 6px;
            letter-spacing: -.5px;
        }

        .register-title {
            font-family: 'Manrope', sans-serif;
            font-weight: 800;
            text-align: center;
            font-size: 22px;
            margin-bottom: 4px;
        }

        .subtitle {
            color: #64748b;
            text-align: center;
            font-size: 13px;
            margin-bottom: 24px;
        }

        .form-label {
            font-weight: 600;
            font-size: 13px;
            color: #334155;
            margin-bottom: 6px;
        }

        .form-control {
            min-height: 46px;
            padding: 10px 14px;
            border-radius: 10px;
            border: 1px solid #cbd5e1;
            font-size: 14px;
        }

        .form-control:focus {
            border-color: var(--brand);
            box-shadow: 0 0 0 3px rgba(38, 123, 114, .15);
        }

        .btn-register {
            min-height: 48px;
            border: none;
            border-radius: 10px;
            background: var(--brand);
            color: white;
            font-weight: 700;
            font-size: 14px;
            box-shadow: 0 8px 18px rgba(38, 123, 114, .2);
            transition: background-color .15s ease, transform .1s ease;
        }

        .btn-register:hover {
            background: var(--brand-dark);
            color: white;
            transform: translateY(-1px);
        }

        .login-link {
            margin-top: 22px;
            text-align: center;
            color: #64748b;
            font-size: 13px;
        }

        .login-link a {
            color: var(--brand);
            font-weight: 700;
            text-decoration: none;
        }

        .login-link a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>

<div class="register-card">
    <div class="brand"><i class="bi bi-droplet-half me-1"></i> CuciKu</div>
    <h1 class="register-title">Buat Akun</h1>
    <p class="subtitle">Daftar untuk memesan laundry dengan mudah.</p>

    <?php if ($error !== ''): ?>
        <div class="alert alert-danger py-2 px-3 small">
            <?= e($error) ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="register.php" onsubmit="const btn=this.querySelector('button[type=submit]'); if(btn){btn.disabled=true; btn.innerHTML='<span class=\'spinner-border spinner-border-sm me-2\'></span>Mendaftarkan...';}">
        <div class="mb-3">
            <label for="nama" class="form-label">Nama Lengkap *</label>
            <input type="text" id="nama" name="nama"
                   class="form-control" required maxlength="100"
                   autocomplete="name"
                   placeholder="Masukkan nama lengkap"
                   value="<?= e($nama) ?>">
        </div>

        <div class="mb-3">
            <label for="no_hp" class="form-label">Nomor WhatsApp / HP *</label>
            <input type="tel" id="no_hp" name="no_hp"
                   class="form-control" required maxlength="20"
                   autocomplete="tel"
                   placeholder="Contoh: 081234567890"
                   value="<?= e($no_hp) ?>">
        </div>

        <div class="mb-3">
            <label for="email" class="form-label">Email *</label>
            <input type="email" id="email" name="email"
                   class="form-control" required maxlength="100"
                   autocomplete="email"
                   placeholder="nama@email.com"
                   value="<?= e($email) ?>">
        </div>

        <div class="mb-3">
            <label for="password" class="form-label">Password *</label>
            <input type="password" id="password" name="password"
                   class="form-control" required minlength="8"
                   placeholder="Minimal 8 karakter"
                   autocomplete="new-password">
        </div>

        <div class="mb-4">
            <label for="konfirmasi_password" class="form-label">
                Konfirmasi Password *
            </label>
            <input type="password" id="konfirmasi_password"
                   name="konfirmasi_password" class="form-control"
                   placeholder="Ulangi password"
                   required minlength="8" autocomplete="new-password">
        </div>

        <button type="submit" class="btn btn-register w-100">
            <i class="bi bi-person-plus me-1"></i> Daftar Sekarang
        </button>
    </form>

    <div class="login-link">
        Sudah punya akun?
        <a href="login.php">Login di sini</a>
    </div>
</div>

</body>
</html>
