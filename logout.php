
<?php
$https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Hapus seluruh data session.
$_SESSION = [];

// Hapus cookie session PHP.
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();

    setcookie(session_name(), '', [
        'expires' => time() - 3600,
        'path' => $params['path'],
        'domain' => $params['domain'],
        'secure' => $params['secure'],
        'httponly' => $params['httponly'],
        'samesite' => $params['samesite'] ?? 'Lax'
    ]);
}

// Hapus session dari server.
session_destroy();

// Hapus cookie penanda jenis akun.
setcookie('cuciku_role', '', [
    'expires' => time() - 3600,
    'path' => '/cuciKu/',
    'secure' => $https,
    'httponly' => true,
    'samesite' => 'Lax'
]);

header('Location: /cuciKu/login.php');
exit;
