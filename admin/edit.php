<?php
/**
 * edit.php — Pengalihan ke modal edit antrean terpusat.
 *
 * Proses edit transaksi dikelola secara terpusat pada antrean.php
 * menggunakan koneksi database PDO yang aman.
 */

require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/init.php';

$code = trim($_GET['code'] ?? $_POST['code'] ?? '');

if ($code !== '') {
    header('Location: antrean.php?modal=edit&code=' . urlencode($code));
} else {
    header('Location: antrean.php');
}
exit;
