<?php
/**
 * init.php — Inisialisasi session, timezone, dan data contoh.
 *
 * File ini menangani:
 * - Start session jika belum aktif
 * - Set timezone Asia/Jakarta
 * - Muat fungsi helper terpusat
 * - Seeding data contoh ke session (untuk prototipe/demo)
 */

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

date_default_timezone_set('Asia/Jakarta');

// Muat fungsi helper terpusat (e, rupiah, statusLabel, dll.)
require_once __DIR__ . '/helpers.php';

// =============================
// Data contoh untuk prototipe
// =============================
if (!isset($_SESSION['laundry_orders'])) {
    $today     = new DateTimeImmutable('today');
    $base      = $today->format('Y-m-d');
    $tomorrow  = $today->modify('+1 day')->format('Y-m-d');
    $yesterday = $today->modify('-1 day')->format('Y-m-d');

    $_SESSION['laundry_orders'] = [
        ['code'=>'LD-'.$today->format('Ymd').'-001','customer'=>'Rina Amelia','phone'=>'081234567890','package'=>'Express','weight'=>3.5,'dateIn'=>$base.' 09:10:00','deadline'=>$base.' 14:00:00','status'=>'ready','rate'=>14000,'paid'=>false],
        ['code'=>'LD-'.$today->format('Ymd').'-002','customer'=>'Dedi Saputra','phone'=>'081298765432','package'=>'Reguler','weight'=>5,'dateIn'=>$base.' 09:35:00','deadline'=>$base.' 17:00:00','status'=>'washing','rate'=>7000,'paid'=>false],
        ['code'=>'LD-'.$today->format('Ymd').'-003','customer'=>'Sari Wulandari','phone'=>'081355667788','package'=>'Reguler','weight'=>2.5,'dateIn'=>$base.' 10:15:00','deadline'=>$tomorrow.' 12:00:00','status'=>'waiting','rate'=>7000,'paid'=>false],
        ['code'=>'LD-'.$today->format('Ymd').'-004','customer'=>'Maya Putri','phone'=>'082112223333','package'=>'Express','weight'=>4,'dateIn'=>$base.' 08:50:00','deadline'=>$base.' 15:00:00','status'=>'ironing','rate'=>14000,'paid'=>false],
        ['code'=>'LD-'.$today->format('Ymd').'-005','customer'=>'Nanda Pratama','phone'=>'085700112233','package'=>'Reguler','weight'=>3,'dateIn'=>$yesterday.' 08:20:00','deadline'=>$yesterday.' 16:00:00','status'=>'completed','rate'=>7000,'paid'=>true],
        ['code'=>'LD-'.$today->format('Ymd').'-006','customer'=>'Fajar Ramadhan','phone'=>'081900112233','package'=>'Reguler','weight'=>6,'dateIn'=>$base.' 10:40:00','deadline'=>$tomorrow.' 10:00:00','status'=>'drying','rate'=>7000,'paid'=>false],
        ['code'=>'LD-'.$today->format('Ymd').'-007','customer'=>'Nadia Putri','phone'=>'082233445566','package'=>'Express','weight'=>2,'dateIn'=>$base.' 11:00:00','deadline'=>$base.' 23:00:00','status'=>'waiting','rate'=>14000,'paid'=>false],
        ['code'=>'LD-'.$today->format('Ymd').'-008','customer'=>'Bima Saputra','phone'=>'085611223344','package'=>'Reguler','weight'=>4.5,'dateIn'=>$base.' 11:20:00','deadline'=>$tomorrow.' 15:00:00','status'=>'ready','rate'=>7000,'paid'=>false],
        ['code'=>'LD-'.$today->format('Ymd').'-009','customer'=>'Ayu Lestari','phone'=>'081277889900','package'=>'Reguler','weight'=>3,'dateIn'=>$base.' 12:00:00','deadline'=>$tomorrow.' 16:00:00','status'=>'waiting','rate'=>7000,'paid'=>false],
        ['code'=>'LD-'.$today->format('Ymd').'-010','customer'=>'Rafi Maulana','phone'=>'081234556677','package'=>'Express','weight'=>1.5,'dateIn'=>$base.' 12:30:00','deadline'=>$tomorrow.' 00:30:00','status'=>'washing','rate'=>14000,'paid'=>false],
    ];
}

/**
 * Hitung label deadline relatif (Hari ini / Besok / tanggal).
 */
function deadlineLabel(string $value): string
{
    $d = new DateTimeImmutable($value);
    $today = new DateTimeImmutable('today');
    $tomorrow = $today->modify('+1 day');
    $time = $d->format('H.i');

    if ($d->format('Y-m-d') === $today->format('Y-m-d')) {
        return 'Hari ini, ' . $time;
    }

    if ($d->format('Y-m-d') === $tomorrow->format('Y-m-d')) {
        return 'Besok, ' . $time;
    }

    return $d->format('d/m/Y') . ', ' . $time;
}

/**
 * Urutkan array orders: yang aktif di atas, diurutkan berdasarkan deadline.
 */
function sortOrders(array &$orders): void
{
    usort($orders, function ($a, $b) {
        $aDone = in_array($a['status'], ['completed', 'cancelled'], true);
        $bDone = in_array($b['status'], ['completed', 'cancelled'], true);

        if ($aDone !== $bDone) {
            return $aDone ? 1 : -1;
        }

        return strtotime($a['deadline']) <=> strtotime($b['deadline']);
    });
}

/**
 * Cari index order berdasarkan kode transaksi di session demo.
 */
function findOrderIndex(string $code): ?int
{
    foreach ($_SESSION['laundry_orders'] as $i => $order) {
        if ($order['code'] === $code) {
            return $i;
        }
    }

    return null;
}
