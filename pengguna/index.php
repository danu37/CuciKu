<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
date_default_timezone_set('Asia/Jakarta');

require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/helpers.php';

if (
    empty($_SESSION['pelanggan_logged_in']) ||
    empty($_SESSION['pelanggan_id'])
) {
    header('Location: ../login.php');
    exit;
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
.btn-order{background:white;color:#075b66;font-weight:bold;padding:12px 18px;border-radius:10px;text-decoration:none;display:inline-block;transition:background-color .15s ease, transform .1s ease}
.btn-order:hover{background:#f0fbf8;color:#064e58;transform:translateY(-1px)}
.stat{background:white;padding:18px 20px 16px;border-radius:16px;border:1px solid #e4ebef;height:100%;display:flex;flex-direction:column;justify-content:space-between;box-shadow:0 2px 6px rgba(24,48,68,.03)}
.stat-top{display:flex;align-items:center;justify-content:space-between;margin-bottom:8px}
.stat-top span{color:#718096;font-size:13px;font-weight:600}
.stat-icon{width:36px;height:36px;border-radius:10px;display:grid;place-items:center;font-size:17px}
.icon-blue{color:#4a79c8;background:#edf3ff}
.icon-orange{color:#c88a35;background:#fff3df}
.icon-purple{color:#8972c8;background:#f2eeff}
.icon-green{color:#298672;background:#e7f6f0}
.stat .num{font-size:28px;font-weight:800;color:#172638;line-height:1.15}
.panel{background:white;padding:22px;border:1px solid #e4ebef;border-radius:16px;margin-top:25px}
.nota-lookup{display:flex;align-items:center;gap:18px;background:#fff;border:1px solid #e6edf2;border-radius:16px;padding:20px 22px;margin-top:22px;box-shadow:0 3px 12px #162c3e05}
.nota-lookup-icon{display:grid;place-items:center;flex:0 0 48px;height:48px;border-radius:14px;background:#e7f5f2;color:#267b72;font-size:22px}
.nota-lookup-copy{flex:1;min-width:0}
.nota-lookup-copy h2{font-size:15px;font-weight:800;margin:0 0 4px}
.nota-lookup-copy p{font-size:12px;color:#8793a1;margin:0}
.nota-lookup-form{display:flex;gap:8px;min-width:min(390px,48%)}
.nota-lookup-form .form-control{min-height:42px;border-color:#e0e6ed;border-radius:9px;font-size:12px}
.nota-lookup-form .form-control:focus{border-color:#267b72;box-shadow:0 0 0 3px #267b721c}
.nota-lookup-form .btn{white-space:nowrap}
.badge-status{display:inline-block;padding:7px 10px;border-radius:20px;font-size:12px;font-weight:bold;background:#e6f3ff;color:#1769aa}
.status-ready{background:#d8f8e8;color:#117548}
.status-completed{background:#e9e5ff;color:#5944ad}
.status-cancelled{background:#ffe2e2;color:#b42318}
@media(max-width:700px){.nota-lookup{align-items:flex-start;flex-wrap:wrap;padding:17px}.nota-lookup-icon{flex-basis:40px;height:40px}.nota-lookup-copy{flex-basis:calc(100% - 60px)}.nota-lookup-form{min-width:100%}}
@media(max-width:600px){.hero{padding:24px}.hero h1{font-size:24px}.panel{padding:14px}}
</style>

<div class="container-main">
    <section class="hero">
        <h1>Halo, <?= e($nama) ?>!</h1>
        <p>Selamat datang di CuciKu. Pesan laundry dan pantau status cucianmu dengan mudah.</p>
        <a class="btn-order" href="buat-pesanan.php"><i class="bi bi-plus-lg me-1"></i> Buat Pesanan Laundry</a>
    </section>

    <section class="nota-lookup" aria-labelledby="notaLookupTitle">
        <div class="nota-lookup-icon"><i class="bi bi-receipt-cutoff"></i></div>
        <div class="nota-lookup-copy">
            <h2 id="notaLookupTitle">Cari struk pesanan</h2>
            <p>Masukkan kode transaksi untuk membuka dan mencetak struk.</p>
        </div>
        <form class="nota-lookup-form" method="get" action="cek-nota.php">
            <label class="visually-hidden" for="kodeNotaDashboard">Kode nota</label>
            <input class="form-control" type="search" id="kodeNotaDashboard"
                   name="nota" placeholder="Kode transaksi, contoh LD-20261014-ABC123"
                   maxlength="40" required>
            <button class="btn btn-brand" type="submit">
                <i class="bi bi-receipt me-1"></i> Cari Struk
            </button>
        </form>
    </section>

    <h4 class="fw-bold mb-3 mt-4">Ringkasan Pesanan</h4>
    <div class="row g-3">
        <div class="col-6 col-lg-3">
            <div class="stat">
                <div class="stat-top">
                    <span>Total Pesanan</span>
                    <span class="stat-icon icon-blue"><i class="bi bi-stack"></i></span>
                </div>
                <div class="num"><?= $totalPesanan ?></div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="stat">
                <div class="stat-top">
                    <span>Diproses</span>
                    <span class="stat-icon icon-orange"><i class="bi bi-hourglass-split"></i></span>
                </div>
                <div class="num"><?= $diproses ?></div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="stat">
                <div class="stat-top">
                    <span>Siap Diambil</span>
                    <span class="stat-icon icon-green"><i class="bi bi-check2-circle"></i></span>
                </div>
                <div class="num"><?= $siapDiambil ?></div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="stat">
                <div class="stat-top">
                    <span>Selesai</span>
                    <span class="stat-icon icon-purple"><i class="bi bi-bag-check-fill"></i></span>
                </div>
                <div class="num"><?= $selesai ?></div>
            </div>
        </div>
    </div>

    <section class="panel">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="fw-bold mb-0">Pesanan Terbaru</h5>
            <a href="riwayat.php" class="text-success text-decoration-none fw-bold">Lihat semua →</a>
        </div>

        <?php if (!$pesanan): ?>
            <div class="text-center py-5">
                <i class="bi bi-inbox fs-1 text-muted d-block mb-2"></i>
                <h5>Belum Ada Pesanan</h5>
                <p class="text-secondary">Pesanan laundry kamu akan muncul di sini.</p>
                <a href="buat-pesanan.php" class="btn btn-brand">
                    <i class="bi bi-plus-lg me-1"></i> Pesan Sekarang
                </a>
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
                               class="btn btn-sm btn-outline-success">
                                <i class="bi bi-eye me-1"></i> Detail
                            </a>
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
