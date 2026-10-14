
<?php
session_start();
date_default_timezone_set('Asia/Jakarta');

require_once __DIR__ . '/../includes/database.php';

if (
    empty($_SESSION['pelanggan_logged_in']) ||
    empty($_SESSION['pelanggan_id'])
) {
    header('Location: ../login.php');
    exit;
}

function e($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function rupiah($angka) {
    return 'Rp ' . number_format((float) $angka, 0, ',', '.');
}

$nama = $_SESSION['pelanggan_nama'] ?? 'Pelanggan';
$idPelanggan = (int) $_SESSION['pelanggan_id'];

$stmt = $pdo->prepare("
    SELECT *
    FROM transaksi
    WHERE pelanggan_id = ?
    ORDER BY created_at DESC
");
$stmt->execute([$idPelanggan]);
$pesanan = $stmt->fetchAll();

$totalPesanan = count($pesanan);
$diproses = 0;
$siapDiambil = 0;
$selesai = 0;

foreach ($pesanan as $p) {
    if (in_array($p['status'], ['waiting', 'washing', 'drying', 'ironing'], true)) {
        $diproses++;
    }

    if ($p['status'] === 'ready') {
        $siapDiambil++;
    }

    if ($p['status'] === 'completed') {
        $selesai++;
    }
}

$statusLabel = [
    'waiting' => 'Menunggu',
    'washing' => 'Diproses',
    'drying' => 'Diproses',
    'ironing' => 'Diproses',
    'ready' => 'Siap Diambil',
    'completed' => 'Selesai',
    'cancelled' => 'Dibatalkan'
];
$judulHalaman = 'Dashboard Pengguna';
$halamanAktif = 'dashboard';
require __DIR__ . '/includes/header.php';
?>
<style>
.container-main{max-width:1150px;margin:auto;padding:30px 18px}
.hero{background:linear-gradient(120deg,#075b66,#087f8c,#19b5a5);color:white;padding:35px;border-radius:22px;margin-bottom:25px}
.hero h1{font-size:30px;font-weight:800}
.hero p{color:#e4fffc}
.btn-order{background:white;color:#075b66;font-weight:bold;padding:12px 18px;border-radius:10px;text-decoration:none;display:inline-block}
.stat{background:white;padding:22px;border-radius:16px;border:1px solid #e4ebef;height:100%}
.stat .num{font-size:30px;font-weight:800}
.stat small{color:#718096}
.panel{background:white;padding:22px;border:1px solid #e4ebef;border-radius:16px;margin-top:25px}
.badge-status{display:inline-block;padding:7px 10px;border-radius:20px;font-size:12px;font-weight:bold;background:#e6f3ff;color:#1769aa}
.status-ready{background:#d8f8e8;color:#117548}
.status-completed{background:#e9e5ff;color:#5944ad}
.status-cancelled{background:#ffe2e2;color:#b42318}
@media(max-width:600px){.hero{padding:24px}.hero h1{font-size:24px}.panel{padding:14px}}
</style>

<div class="container-main">
    <section class="hero">
        <h1>Halo, <?= e($nama) ?>! 👋</h1>
        <p>Selamat datang di CuciKu. Pesan laundry dan pantau status cucianmu dengan mudah.</p>
        <a class="btn-order" href="buat-pesanan.php">+ Buat Pesanan Laundry</a>
    </section>

    <h4 class="fw-bold mb-3">Ringkasan Pesanan</h4>
    <div class="row g-3">
        <div class="col-6 col-lg-3">
            <div class="stat"><div>🧺</div><div class="num"><?= $totalPesanan ?></div><small>Total Pesanan</small></div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="stat"><div>⏳</div><div class="num"><?= $diproses ?></div><small>Menunggu / Diproses</small></div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="stat"><div>📦</div><div class="num"><?= $siapDiambil ?></div><small>Siap Diambil</small></div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="stat"><div>✅</div><div class="num"><?= $selesai ?></div><small>Selesai</small></div>
        </div>
    </div>

    <section class="panel">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="fw-bold mb-0">Pesanan Terbaru</h5>
            <a href="riwayat.php" class="text-success text-decoration-none fw-bold">Lihat semua →</a>
        </div>

        <?php if (!$pesanan): ?>
            <div class="text-center py-5">
                <div style="font-size:40px">🧺</div>
                <h5>Belum Ada Pesanan</h5>
                <p class="text-secondary">Pesanan laundry kamu akan muncul di sini.</p>
                <a href="buat-pesanan.php" class="btn btn-success">Pesan Sekarang</a>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                    <tr>
                        <th>No. Nota</th>
                        <th>Tanggal Masuk</th>
                        <th>Paket</th>
                        <th>Berat</th>
                        <th>Total</th>
                        <th>Status</th>
                        <th>Detail</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach (array_slice($pesanan, 0, 5) as $p): ?>
                    <tr>
                        <td class="fw-bold"><?= e($p['kode_transaksi']) ?></td>
                        <td><?= e(date('d/m/Y H:i', strtotime($p['tanggal_masuk']))) ?></td>
                        <td><?= e($p['paket']) ?></td>
                        <td><?= e($p['berat']) ?> kg</td>
                        <td><?= rupiah($p['total_biaya']) ?></td>
                        <td>
                            <span class="badge-status status-<?= e($p['status']) ?>">
                                <?= e($statusLabel[$p['status']] ?? $p['status']) ?>
                            </span>
                        </td>
                        <td>
                            <a href="detail-pesanan.php?id=<?= (int) $p['id'] ?>"
                               class="btn btn-sm btn-outline-success">Detail</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
