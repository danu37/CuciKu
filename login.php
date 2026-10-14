    <?php

require_once __DIR__ . '/includes/init.php';

// Jika admin sudah login, langsung ke dashboard.
if (!empty($_SESSION['admin_logged_in'])) {
    header('Location: index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = 'Username dan password wajib diisi.';
    } else {
        // Cari akun admin berdasarkan username.
        $stmt = $pdo->prepare(
            'SELECT id, nama, username, password
             FROM admins
             WHERE username = ?
             LIMIT 1'
        );

        $stmt->execute([$username]);
        $admin = $stmt->fetch();

        // Validasi password yang sudah di-hash.
        if ($admin && password_verify($password, $admin['password'])) {
            session_regenerate_id(true);

            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_id'] = $admin['id'];
            $_SESSION['admin_name'] = $admin['nama'];
            $_SESSION['admin_username'] = $admin['username'];

            header('Location: index.php');
            exit;
        }

        $error = 'Username atau password tidak sesuai.';
    }
}

?>
<!doctype html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Login Admin | LaundryKu</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
        rel="stylesheet"
    >

    <link href="assets/css/style.css" rel="stylesheet">
    <link href="assets/css/custom.css" rel="stylesheet">
</head>

<body class="login-page">

<main class="login-layout">

    <!-- Bagian sambutan -->
    <section class="login-welcome">

        <a class="login-brand" href="login.php">
            <span class="login-brand-icon">
                <i class="bi bi-droplet-half"></i>
            </span>
            <span>LaundryKu</span>
        </a>

        <div class="login-welcome-copy">

            <span class="login-eyebrow">
                LAUNDRY MANAGEMENT SYSTEM
            </span>

            <h1>
                Urus cucian,<br>
                <em>lebih mudah.</em>
            </h1>

            <p>
                Kelola transaksi, pantau antrean, dan pastikan
                setiap pesanan selesai tepat waktu dari satu tempat.
            </p>

            <div class="login-feature-list">
                <div>
                    <i class="bi bi-check-circle-fill"></i>
                    Antrean tertata dan mudah dipantau
                </div>

                <div>
                    <i class="bi bi-check-circle-fill"></i>
                    Status cucian selalu terlihat
                </div>

                <div>
                    <i class="bi bi-check-circle-fill"></i>
                    Nota digital mudah ditemukan kembali
                </div>
            </div>

        </div>

        <div class="login-decoration decor-one"></div>
        <div class="login-decoration decor-two"></div>

        <div class="login-welcome-foot">
            <i class="bi bi-stars"></i>
            Biar urusan laundry terasa ringan.
        </div>

    </section>

    <!-- Bagian formulir login -->
    <section class="login-form-side">

        <div class="login-form-wrap">

            <div class="login-mobile-brand">
                <span class="login-brand-icon">
                    <i class="bi bi-droplet-half"></i>
                </span>
                <strong>LaundryKu</strong>
            </div>

            <div class="login-form-heading">

                <span class="login-small-label">
                    SELAMAT DATANG KEMBALI
                </span>

                <h2>Login Admin</h2>

                <p>
                    Masuk untuk mengelola operasional laundry.
                </p>

            </div>

            <!-- Pesan kesalahan -->
            <?php if ($error !== ''): ?>
                <div class="alert alert-danger py-2" role="alert">
                    <?= e($error) ?>
                </div>
            <?php endif; ?>

            <form method="post" class="login-form">

                <label for="username" class="form-label">
                    Username
                </label>

                <div class="login-input-wrap">
                    <i class="bi bi-person"></i>

                    <input
                        type="text"
                        id="username"
                        name="username"
                        class="form-control"
                        placeholder="Masukkan username"
                        autocomplete="username"
                        required
                        value="<?= e($_POST['username'] ?? '') ?>"
                    >
                </div>

                <label for="password" class="form-label mt-3">
                    Password
                </label>

                <div class="login-input-wrap">
                    <i class="bi bi-lock"></i>

                    <input
                        type="password"
                        id="password"
                        name="password"
                        class="form-control"
                        placeholder="Masukkan password"
                        autocomplete="current-password"
                        required
                    >

                    <button
                        type="button"
                        class="password-toggle"
                        id="togglePassword"
                        aria-label="Tampilkan password"
                    >
                        <i class="bi bi-eye"></i>
                    </button>
                </div>

                <button type="submit" class="btn login-submit w-100">
                    Masuk ke Dashboard
                    <i class="bi bi-arrow-right ms-2"></i>
                </button>

            </form>

            <p class="login-privacy">
                <i class="bi bi-shield-lock me-1"></i>
                Halaman khusus administrator LaundryKu
            </p>

        </div>

        <div class="login-copyright">
            &copy; <?= date('Y') ?> LaundryKu · Sistem antrean laundry
        </div>

    </section>

</main>

<script>
    document
        .getElementById('togglePassword')
        .addEventListener('click', function () {
            const input = document.getElementById('password');
            const icon = this.querySelector('i');

            if (input.type === 'password') {
                input.type = 'text';
                icon.className = 'bi bi-eye-slash';
                this.setAttribute('aria-label', 'Sembunyikan password');
            } else {
                input.type = 'password';
                icon.className = 'bi bi-eye';
                this.setAttribute('aria-label', 'Tampilkan password');
            }
        });
</script>

</body>
</html>