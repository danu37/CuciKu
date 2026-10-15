<?php
/**
 * helpers.php — Fungsi-fungsi pembantu yang digunakan di seluruh proyek.
 *
 * Cukup require_once file ini satu kali. Semua fungsi helper
 * tersedia secara global tanpa perlu mendefinisikan ulang.
 */

// Cegah deklarasi ulang jika file di-require lebih dari sekali.
if (defined('CUCIKU_HELPERS_LOADED')) {
    return;
}

define('CUCIKU_HELPERS_LOADED', true);

/**
 * Escape string untuk output HTML.
 *
 * @param mixed $value Nilai yang akan di-escape.
 * @return string
 */
function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/**
 * Format angka menjadi format Rupiah Indonesia.
 *
 * @param mixed $nominal Nilai yang akan diformat.
 * @return string Contoh: "Rp 10.500"
 */
function rupiah($nominal): string
{
    return 'Rp ' . number_format((float) $nominal, 0, ',', '.');
}

/**
 * Ambil label status dalam Bahasa Indonesia.
 *
 * @param string $status Kode status (e.g. 'waiting', 'washing').
 * @return string Label yang ditampilkan.
 */
function statusLabel(string $status): string
{
    $labels = [
        'waiting'   => 'Menunggu',
        'washing'   => 'Sedang Dicuci',
        'drying'    => 'Pengeringan',
        'ironing'   => 'Penyetrikaan',
        'ready'     => 'Siap Diambil',
        'completed' => 'Selesai',
        'cancelled' => 'Dibatalkan',
    ];

    return $labels[$status] ?? ucfirst($status);
}

/**
 * Ambil CSS class berdasarkan status order.
 *
 * @param string $status Kode status.
 * @return string Nama CSS class.
 */
function statusClass(string $status): string
{
    $classes = [
        'waiting'   => 'status-waiting',
        'washing'   => 'status-process',
        'drying'    => 'status-process',
        'ironing'   => 'status-process',
        'ready'     => 'status-ready',
        'completed' => 'status-completed',
        'cancelled' => 'status-cancelled',
    ];

    return $classes[$status] ?? 'status-waiting';
}

/**
 * Format tanggal ke format Indonesia (d/m/Y H:i).
 *
 * @param string|null $tanggal Tanggal dalam format yang dikenali strtotime().
 * @return string
 */
function formatTanggal(?string $tanggal): string
{
    if (!$tanggal) {
        return '-';
    }

    $timestamp = strtotime($tanggal);

    return $timestamp ? date('d/m/Y H:i', $timestamp) : '-';
}

/**
 * Simpan pesan flash ke session.
 *
 * @param string $message Pesan yang ditampilkan.
 * @param string $type    Tipe pesan: 'success', 'warning', 'danger'.
 */
function flash(string $message, string $type = 'success'): void
{
    $_SESSION['flash'] = [
        'message' => $message,
        'type'    => $type,
    ];
}

/**
 * Tampilkan pesan flash jika ada, lalu hapus dari session.
 */
function showFlash(): void
{
    if (empty($_SESSION['flash'])) {
        return;
    }

    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);

    $type = e($flash['type'] ?? 'success');
    $message = e($flash['message'] ?? '');

    echo '<div class="alert alert-' . $type . ' alert-dismissible fade show">'
       . $message
       . '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>'
       . '</div>';
}

/**
 * Hitung total biaya order dari berat dan tarif per kg.
 *
 * @param array $order Array order dengan key 'weight'/'berat' dan 'rate'/'tarif_per_kg'.
 * @return int
 */
function totalOrder(array $order): int
{
    $weight = (float) ($order['berat'] ?? $order['weight'] ?? 0);
    $rate   = (int)   ($order['tarif_per_kg'] ?? $order['rate'] ?? 0);

    return (int) round($weight * $rate);
}
