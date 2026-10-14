<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/../includes/database.php';

if (
    empty($_SESSION['pelanggan_logged_in']) ||
    empty($_SESSION['pelanggan_id'])
) {
    header('Location: ../login.php');
    exit;
}

function hDetail($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function rupiahDetail($value)
{
    return 'Rp ' . number_format((float) $value, 0, ',', '.');
}

$statusList = [
    'waiting'   => ['Menunggu', 'warning'],
    'washing'   => ['Sedang Dicuci', 'primary'],
    'drying'    => ['Sedang Dikeringkan', 'info'],
    'ironing'   => ['Sedang Disetrika', 'primary'],
    'ready'     => ['Siap Diambil', 'success'],
    'completed' => ['Selesai', 'success'],
    'cancelled' => ['Dibatalkan', 'danger'],
];

$idPelanggan = (int) $_SESSION['pelanggan_id'];
$kodeNota = trim($_GET['nota'] ?? '');
$idTransaksi = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if ($kodeNota === '' && !$idTransaksi) {
    header('Location: riwayat.php');
    exit;
}

if ($kodeNota !== '') {
    $stmt = $pdo->prepare(
        'SELECT * FROM transaksi
         WHERE kode_transaksi = ? AND pelanggan_id = ?
         LIMIT 1'
    );
    $stmt->execute([$kodeNota, $idPelanggan]);
} else {
    $stmt = $pdo->prepare(
        'SELECT * FROM transaksi
         WHERE id = ? AND pelanggan_id = ?
         LIMIT 1'
    );
    $stmt->execute([$idTransaksi, $idPelanggan]);
}

$pesanan = $stmt->fetch();

if (!$pesanan) {
    http_response_code(404);
}

$judulHalaman = 'Detail Pemesanan';
$halamanAktif = 'riwayat';

require_once __DIR__ . '/includes/header.php';
?>

<style>
.detail-wrap {
    max-width: 1050px;
    margin: 35px auto;
}
.detail-title {
    font-size: 28px;
    font-weight: 800;
    color: #183044;
}
.detail-subtitle {
    color: #7b8b97;
    font-size: 14px;
}
.detail-card {
    background: #fff;
    border: 1px solid #e5edf1;
    border-radius: 18px;
    padding: 25px;
    box-shadow: 0 8px 30px rgba(24,48,68,.04);
}
.invoice-banner {
    background: linear-gradient(120deg,#075b66,#0a8d91,#19b5a5);
    color: #fff;
    padding: 28px;
    border-radius: 18px;
    margin-bottom: 22px;
}
.invoice-code {
    font-size: 22px;
    font-weight: 800;
    letter-spacing: .5px;
    overflow-wrap: anywhere;
}
.invoice-label {
    color: #d7f7f0;
    font-size: 12px;
    margin-bottom: 6px;
}
.detail-section-title {
    font-size: 16px;
    font-weight: 800;
    margin-bottom: 20px;
}
.detail-row {
    display: flex;
    justify-content: space-between;
    gap: 18px;
    padding: 14px 0;
    border-bottom: 1px solid #edf1f4;
    font-size: 14px;
}
.detail-row:last-child {
    border-bottom: 0;
}
.detail-label {
    color: #82909b;
}
.detail-value {
    text-align: right;
    font-weight: 700;
    overflow-wrap: anywhere;
}
.total-box {
    background: #eaf8f5;
    border-radius: 14px;
    padding: 20px;
}
.total-amount {
    color: #087f8c;
    font-size: 27px;
    font-weight: 800;
}
.status-pill {
    display: inline-block;
    padding: 8px 13px;
    border-radius: 30px;
    font-size: 12px;
    font-weight: 800;
}
.timeline {
    display: grid;
    gap: 16px;
}
.timeline-item {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    color: #627482;
    font-size: 13px;
}
.timeline-dot {
    width: 11px;
    height: 11px;
    border-radius: 50%;
    margin-top: 4px;
    flex-shrink: 0;
    background: #19b5a5;
    box-shadow: 0 0 0 4px #e4f7f4;
}
.btn-cuciku {
    display: inline-block;
    background: #087f8c;
    color: white;
    border: 0;
    border-radius: 10px;
    padding: 11px 17px;
    font-size: 13px;
    font-weight: 700;
    text-decoration: none;
}
.btn-cuciku:hover {
    background: #075b66;
    color: white;
}
.btn-light-outline {
    display: inline-block;
    border: 1px solid #dfe8ed;
    color: #405564;
    background: white;
    border-radius: 10px;
    padding: 11px 17px;
    font-size: 13px;
    font-weight: 700;
    text-decoration: none;
}
@media(max-width:576px) {
    .detail-wrap { margin: 22px auto; }
    .detail-card { padding: 18px; }
    .invoice-banner { padding: 22px; }
    .detail-title { font-size: 23px; }
    .detail-row { font-size: 13px; }
}
@media print {
    nav, footer, .no-print, .sidebar, .topbar { display: none !important; }
    body { background: white !important; }
    .app-shell, .main-content, .page-content { display: block !important; margin: 0 !important; padding: 0 !important; }
    .detail-wrap { margin: 0 auto; }
    .detail-card, .invoice-banner { box-shadow: none; }
}
</style>

<div class="container detail-wrap">

<?php if (!$pesanan): ?>

    <div class="detail-card text-center py-5">
        <div class="display-5 mb-3">🔎</div>
        <h2 class="h4 fw-bold">Pesanan tidak ditemukan</h2>
        <p class="text-secondary">
            Pesanan tidak tersedia atau bukan milik akun kamu.
        </p>
        <a href="riwayat.php" class="btn-cuciku">Kembali ke Riwayat</a>
    </div>

<?php else: ?>

    <?php
    $status = $statusList[$pesanan['status']]
        ?? ['Status tidak diketahui', 'secondary'];

    $tanggalMasuk = $pesanan['tanggal_masuk']
        ? date('d M Y, H:i', strtotime($pesanan['tanggal_masuk']))
        : '-';

    $deadline = $pesanan['deadline']
        ? date('d M Y, H:i', strtotime($pesanan['deadline']))
        : '-';

    $paket = $pesanan['paket'];
    $tarif = (float) $pesanan['tarif_per_kg'];
    $berat = (float) $pesanan['berat'];
    $totalBiaya = (float) $pesanan['total_biaya'];
    ?>

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <h1 class="detail-title mb-2">Detail Pemesanan</h1>
            <p class="detail-subtitle mb-0">
                Lihat informasi lengkap dan status laundry kamu.
            </p>
        </div>
        <div class="d-flex gap-2 flex-wrap no-print">
            <a href="riwayat.php" class="btn-light-outline">
                ← Riwayat
            </a>
            <button type="button" onclick="window.print()" class="btn-cuciku">
                Cetak Nota
            </button>
        </div>
    </div>

    <section class="invoice-banner">
        <div class="row align-items-center g-3">
            <div class="col-md-7">
                <div class="invoice-label">NOMOR NOTA</div>
                <div class="invoice-code">
                    <?= hDetail($pesanan['kode_transaksi']) ?>
                </div>
                <div class="small mt-3">
                    Tanggal masuk: <?= hDetail($tanggalMasuk) ?>
                </div>
            </div>
            <div class="col-md-5 text-md-end">
                <div class="invoice-label">STATUS PESANAN</div>
                <span class="status-pill bg-<?= hDetail($status[1]) ?> text-<?= $status[1] === 'warning' || $status[1] === 'info' ? 'dark' : 'white' ?>">
                    <?= hDetail($status[0]) ?>
                </span>
                <div class="small mt-3">
                    Pembayaran:
                    <strong>
                        <?= $pesanan['pembayaran'] === 'lunas' ? 'Lunas' : 'Belum Lunas' ?>
                    </strong>
                </div>
            </div>
        </div>
    </section>

    <div class="row g-4">
        <div class="col-lg-7">
            <section class="detail-card h-100">
                <h2 class="detail-section-title">Informasi Laundry</h2>

                <div class="detail-row">
                    <span class="detail-label">Nama pelanggan</span>
                    <span class="detail-value"><?= hDetail($pesanan['nama_pelanggan']) ?></span>
                </div>

                <div class="detail-row">
                    <span class="detail-label">Nomor WhatsApp</span>
                    <span class="detail-value"><?= hDetail($pesanan['no_wa'] ?: '-') ?></span>
                </div>

                <div class="detail-row">
                    <span class="detail-label">Paket laundry</span>
                    <span class="detail-value"><?= hDetail($paket) ?></span>
                </div>

                <div class="detail-row">
                    <span class="detail-label">Berat cucian</span>
                    <span class="detail-value"><?= number_format($berat, 2, ',', '.') ?> kg</span>
                </div>

                <div class="detail-row">
                    <span class="detail-label">Tarif per kilogram</span>
                    <span class="detail-value"><?= rupiahDetail($tarif) ?></span>
                </div>

                <div class="detail-row">
                    <span class="detail-label">Tanggal masuk</span>
                    <span class="detail-value"><?= hDetail($tanggalMasuk) ?></span>
                </div>

                <div class="detail-row">
                    <span class="detail-label">Estimasi selesai</span>
                    <span class="detail-value"><?= hDetail($deadline) ?></span>
                </div>

                <div class="total-box mt-4">
                    <div class="detail-label mb-1">Total biaya laundry</div>
                    <div class="total-amount"><?= rupiahDetail($totalBiaya) ?></div>
                    <div class="small text-secondary mt-1">
                        <?= number_format($berat, 2, ',', '.') ?> kg ×
                        <?= rupiahDetail($tarif) ?>/kg
                    </div>
                </div>
            </section>
        </div>

        <div class="col-lg-5">
            <section class="detail-card h-100">
                <h2 class="detail-section-title">Status Pengerjaan</h2>

                <div class="timeline">
                    <div class="timeline-item">
                        <span class="timeline-dot"></span>
                        <div>
                            <strong class="d-block text-dark">Pesanan dibuat</strong>
                            <span><?= hDetail($tanggalMasuk) ?></span>
                        </div>
                    </div>

                    <div class="timeline-item">
                        <span class="timeline-dot"></span>
                        <div>
                            <strong class="d-block text-dark">Status saat ini</strong>
                            <span><?= hDetail($status[0]) ?></span>
                        </div>
                    </div>

                    <div class="timeline-item">
                        <span class="timeline-dot"></span>
                        <div>
                            <strong class="d-block text-dark">Estimasi selesai</strong>
                            <span><?= hDetail($deadline) ?></span>
                        </div>
                    </div>
                </div>

                <?php if ($pesanan['status'] === 'ready'): ?>
                    <div class="alert alert-success mt-4 mb-0">
                        Cucian kamu sudah siap diambil. Silakan hubungi petugas
                        jika perlu memastikan waktu pengambilan.
                    </div>
                <?php elseif ($pesanan['status'] === 'completed'): ?>
                    <div class="alert alert-success mt-4 mb-0">
                        Pesanan ini sudah selesai. Terima kasih telah menggunakan CuciKu!
                    </div>
                <?php elseif ($pesanan['status'] === 'cancelled'): ?>
                    <div class="alert alert-danger mt-4 mb-0">
                        Pesanan ini telah dibatalkan.
                    </div>
                <?php else: ?>
                    <div class="alert alert-info mt-4 mb-0">
                        Pesanan sedang menunggu atau dalam proses pengerjaan.
                        Periksa kembali halaman ini untuk melihat status terbaru.
                    </div>
                <?php endif; ?>
            </section>
        </div>
    </div>

<?php endif; ?>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>