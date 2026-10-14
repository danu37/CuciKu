<?php
require_once __DIR__ . '/includes/init.php';

$nama = 'Admin Laundry';
$username = 'admin';
$password = 'laundry123';

$stmt = $pdo->prepare(
    'SELECT id FROM admins WHERE username = ?'
);
$stmt->execute([$username]);

if ($stmt->fetch()) {
    exit('Akun admin sudah tersedia. Hapus file buat_admin.php.');
}

$hash = password_hash($password, PASSWORD_DEFAULT);

$stmt = $pdo->prepare(
    'INSERT INTO admins (nama, username, password)
     VALUES (?, ?, ?)'
);

$stmt->execute([$nama, $username, $hash]);

echo 'Akun admin berhasil dibuat. Hapus file buat_admin.php sekarang.';