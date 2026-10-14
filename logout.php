<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Hapus data login dari session.
$_SESSION = [];

// Hapus session di server.
session_destroy();

// Kembali ke halaman login.
header('Location: index.php');
exit;