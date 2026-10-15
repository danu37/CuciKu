<?php
/**
 * logout.php — Penanganan keluar akun pelanggan dan admin.
 *
 * Menghapus seluruh sesi dan cookie autentikasi secara aman.
 */

$https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Hapus seluruh data session
$_SESSION = [];

// Hapus cookie session PHP
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();

    setcookie(session_name(), '', [
        'expires'  => time() - 3600,
        'path'     => $params['path'] ?? '/',
        'domain'   => $params['domain'] ?? '',
        'secure'   => $params['secure'] ?? $https,
        'httponly' => $params['httponly'] ?? true,
        'samesite' => $params['samesite'] ?? 'Lax',
    ]);
}

// Hapus session dari server
session_destroy();

// Hapus cookie penanda jenis akun
setcookie('cuciku_role', '', [
    'expires'  => time() - 3600,
    'path'     => '/',
    'secure'   => $https,
    'httponly' => true,
    'samesite' => 'Lax',
]);

header('Location: login.php');
exit;
