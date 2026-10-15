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
 * Simpan pesan flash ke session untuk notifikasi toast pop-up.
 *
 * @param string|array $message Pesan yang ditampilkan.
 * @param string       $type    Tipe pesan: 'success', 'warning', 'danger', 'info'.
 * @param string       $title   Judul notifikasi opsional.
 */
function flash(string|array $message, string $type = 'success', string $title = ''): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    if (!isset($_SESSION['flashes'])) {
        $_SESSION['flashes'] = [];
    }

    $msgHtml = is_array($message)
        ? '<ul class="mb-0 ps-3">' . implode('', array_map(fn($m) => '<li>' . e($m) . '</li>', $message)) . '</ul>'
        : e($message);

    $toastData = [
        'message' => $msgHtml,
        'type'    => $type,
        'title'   => $title
    ];

    $_SESSION['flashes'][] = $toastData;
    $_SESSION['flash'] = $toastData;
}

/**
 * Tampilkan pesan flash dalam bentuk Toast Notification modern di kanan atas layar.
 */
function showFlash(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    $items = [];
    if (!empty($_SESSION['flashes']) && is_array($_SESSION['flashes'])) {
        $items = $_SESSION['flashes'];
        unset($_SESSION['flashes']);
        unset($_SESSION['flash']);
    } elseif (!empty($_SESSION['flash']) && is_array($_SESSION['flash'])) {
        $items = [$_SESSION['flash']];
        unset($_SESSION['flash']);
    }

    if (empty($items)) {
        return;
    }

    $typeIcons = [
        'success' => 'bi-check-circle-fill',
        'danger'  => 'bi-x-circle-fill',
        'error'   => 'bi-x-circle-fill',
        'warning' => 'bi-exclamation-triangle-fill',
        'info'    => 'bi-info-circle-fill',
    ];

    $typeTitles = [
        'success' => 'Berhasil',
        'danger'  => 'Terjadi Kesalahan',
        'error'   => 'Terjadi Kesalahan',
        'warning' => 'Peringatan',
        'info'    => 'Informasi',
    ];

    echo '<div class="app-toast-container" id="appToastContainer" aria-live="polite" aria-atomic="true">';
    foreach ($items as $item) {
        $type = e($item['type'] ?? 'success');
        if (!isset($typeIcons[$type])) {
            $type = 'info';
        }
        $icon = $typeIcons[$type];
        $title = !empty($item['title']) ? e($item['title']) : ($typeTitles[$type] ?? 'Notifikasi');
        $message = $item['message'] ?? '';

        echo '<div class="app-toast app-toast-' . $type . '" role="alert">'
           . '<div class="app-toast-icon"><i class="bi ' . $icon . '"></i></div>'
           . '<div class="app-toast-content">'
           . '<div class="app-toast-title">' . $title . '</div>'
           . '<div class="app-toast-message">' . $message . '</div>'
           . '</div>'
           . '<button type="button" class="app-toast-close" aria-label="Tutup notifikasi"><i class="bi bi-x"></i></button>'
           . '<div class="app-toast-progress"><div class="app-toast-progress-bar"></div></div>'
           . '</div>';
    }
    echo '</div>';

    echo '<script>
        if (typeof window.initAppToasts === "function") {
            window.initAppToasts();
        } else {
            document.addEventListener("DOMContentLoaded", function() {
                if (typeof window.initAppToasts === "function") {
                    window.initAppToasts();
                }
            });
        }
    </script>';
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
