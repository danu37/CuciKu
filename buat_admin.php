    <?php
require_once __DIR__ . '/includes/database.php';

$nama = 'Admin Laundry';
$username = 'admin';
$password = 'laundry123';

try {
    $stmt = $pdo->prepare(
        'SELECT id FROM admins WHERE username = ? LIMIT 1'
    );
    $stmt->execute([$username]);

    $admin = $stmt->fetch();

    $hash = password_hash($password, PASSWORD_DEFAULT);

    if ($admin) {
        $stmt = $pdo->prepare(
            'UPDATE admins SET nama = ?, password = ? WHERE id = ?'
        );
        $stmt->execute([$nama, $hash, $admin['id']]);

        echo 'Password admin berhasil diperbarui.';
    } else {
        $stmt = $pdo->prepare(
            'INSERT INTO admins (nama, username, password)
             VALUES (?, ?, ?)'
        );
        $stmt->execute([$nama, $username, $hash]);

        echo 'Akun admin berhasil dibuat.';
    }
} catch (PDOException $e) {
    error_log($e->getMessage());
    echo 'Gagal membuat akun. Periksa tabel admins dan koneksi database.';
}
