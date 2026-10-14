
<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/includes/database.php';

$error = '';
$nama = '';
$no_hp = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama = trim($_POST['nama'] ?? '');
    $no_hp = trim($_POST['no_hp'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $konfirmasi = $_POST['konfirmasi_password'] ?? '';

    if ($nama === '' || $no_hp === '' || $email === ''
        || $password === '' || $konfirmasi === '') {
        $error = 'Semua kolom wajib diisi.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Format email tidak valid.';
    } elseif (strlen($password) < 8) {
        $error = 'Password minimal 8 karakter.';
    } elseif ($password !== $konfirmasi) {
        $error = 'Konfirmasi password tidak sama.';
    } else {
        $stmt = $pdo->prepare(
            'SELECT id_pelanggan FROM pelanggan WHERE email = ? LIMIT 1'
        );
        $stmt->execute([$email]);

        if ($stmt->fetch()) {
            $error = 'Email sudah terdaftar. Silakan gunakan email lain.';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $pdo->prepare(
                'INSERT INTO pelanggan (nama, no_hp, email, password)
                 VALUES (?, ?, ?, ?)'
            );

            $stmt->execute([$nama, $no_hp, $email, $hash]);

            header('Location: login.php?daftar=berhasil');
            exit;
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

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css"
          rel="stylesheet">

    <style>
        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            background: linear-gradient(135deg, #e0f2fe, #f0fdfa, #f8fafc);
            font-family: Arial, sans-serif;
        }

        .register-card {
            width: 100%;
            max-width: 480px;
            padding: 32px;
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 20px;
            box-shadow: 0 15px 45px rgba(15, 23, 42, .09);
        }

        .brand {
            color: #0891b2;
            font-size: 28px;
            font-weight: 800;
            text-align: center;
            margin-bottom: 8px;
        }

        .subtitle {
            color: #64748b;
            text-align: center;
            margin-bottom: 25px;
        }

        .form-label {
            font-weight: 600;
            color: #334155;
        }

        .form-control {
            padding: 11px 13px;
            border-radius: 10px;
        }

        .btn-register {
            padding: 12px;
            border: none;
            border-radius: 10px;
            background: #0891b2;
            color: white;
            font-weight: 700;
        }

        .btn-register:hover {
            background: #0e7490;
            color: white;
        }

        .login-link {
            margin-top: 20px;
            text-align: center;
            color: #64748b;
        }

        .login-link a {
            color: #0891b2;
            font-weight: 700;
            text-decoration: none;
        }
    </style>
</head>
<body>

<div class="register-card">
    <div class="brand">💧 CuciKu</div>
    <?php if (isset($_GET['daftar']) && $_GET['daftar'] === 'berhasil'): ?>
    <div class="alert alert-success">
        Registrasi berhasil. Silakan login.
    </div>
<?php endif; ?>
    <h3 class="text-center fw-bold">Buat Akun</h3>
    <p class="subtitle">Daftar untuk memesan laundry dengan mudah.</p>

    <?php if ($error !== ''): ?>
        <div class="alert alert-danger">
            <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <form method="POST">
        <div class="mb-3">
            <label for="nama" class="form-label">Nama Lengkap</label>
            <input type="text" id="nama" name="nama"
                   class="form-control" required maxlength="100"
                   autocomplete="name"
                   value="<?= htmlspecialchars($nama, ENT_QUOTES, 'UTF-8') ?>">
        </div>

        <div class="mb-3">
            <label for="no_hp" class="form-label">Nomor HP</label>
            <input type="tel" id="no_hp" name="no_hp"
                   class="form-control" required maxlength="20"
                   autocomplete="tel"
                   value="<?= htmlspecialchars($no_hp, ENT_QUOTES, 'UTF-8') ?>">
        </div>

        <div class="mb-3">
            <label for="email" class="form-label">Email</label>
            <input type="email" id="email" name="email"
                   class="form-control" required maxlength="100"
                   autocomplete="email"
                   value="<?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?>">
        </div>

        <div class="mb-3">
            <label for="password" class="form-label">Password</label>
            <input type="password" id="password" name="password"
                   class="form-control" required minlength="8"
                   autocomplete="new-password">
        </div>

        <div class="mb-4">
            <label for="konfirmasi_password" class="form-label">
                Konfirmasi Password
            </label>
            <input type="password" id="konfirmasi_password"
                   name="konfirmasi_password" class="form-control"
                   required minlength="8" autocomplete="new-password">
        </div>

        <button type="submit" class="btn btn-register w-100">
            Daftar Sekarang
        </button>
    </form>

    <div class="login-link">
        Sudah punya akun?
        <a href="pengguna/login.php">Login di sini</a>
    </div>
</div>

</body>
</html>
