<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
date_default_timezone_set('Asia/Jakarta');

require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/helpers.php';

global $pdo;

// Otorisasi: Pastikan pelanggan telah login
if (
    empty($_SESSION['pelanggan_logged_in']) ||
    empty($_SESSION['pelanggan_id'])
) {
    header('Location: ../login.php');
    exit;
}

$nama = $_SESSION['pelanggan_nama'] ?? 'Pelanggan';
$idPelanggan = (int) $_SESSION['pelanggan_id'];

// Ambil seluruh data transaksi murni milik pelanggan yang sedang login
$stmt = $pdo->prepare("
    SELECT *
    FROM transaksi
    WHERE pelanggan_id = ?
    ORDER BY tanggal_masuk DESC, id DESC
");
$stmt->execute([$idPelanggan]);
$allOrders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Hitung metrik operasional & keuangan pelanggan
$totalOrders       = count($allOrders);
$waiting           = 0;
$processing        = 0;
$ready             = 0;
$completed         = 0;
$cancelled         = 0;
$unpaidCount       = 0;
$unpaidTotal       = 0;
$paidCount         = 0;
$paidTotal         = 0;
$totalWeight       = 0;

foreach ($allOrders as $o) {
    $st     = $o['status'] ?? 'waiting';
    $cost   = (float) ($o['total_biaya'] ?? 0);
    $weight = (float) ($o['berat'] ?? 0);
    $isPaid = (($o['pembayaran'] ?? '') === 'lunas');

    if ($st === 'waiting') {
        $waiting++;
    } elseif (in_array($st, ['washing', 'drying', 'ironing'], true)) {
        $processing++;
    } elseif ($st === 'ready') {
        $ready++;
    } elseif ($st === 'completed') {
        $completed++;
    } elseif ($st === 'cancelled') {
        $cancelled++;
    }

    if ($st !== 'cancelled') {
        $totalWeight += $weight;
        if ($isPaid) {
            $paidCount++;
            $paidTotal += $cost;
        } else {
            $unpaidCount++;
            $unpaidTotal += $cost;
        }
    }
}

$activeOrders     = $waiting + $processing + $ready;
$validOrdersCount = max(1, $totalOrders - $cancelled);
$paymentRate      = $totalOrders > 0 ? round(($paidCount / $validOrdersCount) * 100, 1) : 100;

// Segmentasi proporsi status untuk progress track visual
$pctWaiting    = $totalOrders > 0 ? round(($waiting / $totalOrders) * 100, 1) : 0;
$pctProcessing = $totalOrders > 0 ? round(($processing / $totalOrders) * 100, 1) : 0;
$pctReady      = $totalOrders > 0 ? round(($ready / $totalOrders) * 100, 1) : 0;
$pctCompleted  = $totalOrders > 0 ? round(($completed / $totalOrders) * 100, 1) : 0;

$judulHalaman = 'Dashboard Pelanggan';
$halamanAktif = 'dashboard';
require __DIR__ . '/includes/header.php';
?>

<div class="customer-dash-wrap">
    <!-- PAGE HEADER / GREETING & QUICK ACTIONS -->
    <div class="page-heading">
        <div>
            <div class="topbar-eyebrow text-uppercase text-muted fw-bold mb-1" style="font-size: 11px; letter-spacing: .06em;">
                <i class="bi bi-person-circle me-1 text-primary"></i> DASHBOARD PELANGGAN
            </div>
            <h1 class="h3 fw-bold mb-1">Halo, <?= e($nama) ?>! </h1>
            <p class="text-muted mb-0">Pantau status pencucian baju, rincian pembayaran, dan pesan laundry dengan mudah.</p>
        </div>
        <div class="d-flex align-items-center gap-2 flex-wrap mt-2 mt-md-0">
            <a class="btn btn-outline-brand" href="riwayat.php">
                <i class="bi bi-clock-history me-1"></i> Seluruh Riwayat
            </a>
            <a class="btn btn-brand" href="buat-pesanan.php">
                <i class="bi bi-plus-lg me-1"></i> Buat Pesanan Baru
            </a>
        </div>
    </div>

    <!-- NOTIFIKASI CUCIAN SIAP DIAMBIL (JIKA ADA) -->
    <?php if ($ready > 0): ?>
    <div class="ready-alert">
        <div class="ready-alert-icon">
            <i class="bi bi-bell-fill"></i>
        </div>
        <div class="ready-alert-copy">
            <strong>Hore! Terdapat <?= $ready ?> pesanan laundry kamu yang SIAP DIAMBIL.</strong>
            <span>Cucianmu sudah selesai diproses bersih, wangi, dan rapi. Silakan kunjungi outlet CuciKu dengan membawa nota transaksi.</span>
        </div>
        <a href="riwayat.php?status=ready" class="ready-alert-link">
            Lihat Cucian Siap <i class="bi bi-arrow-right ms-1"></i>
        </a>
    </div>
    <?php endif; ?>

    <!-- ========================================================
         DUA STATISTIK UTAMA PELANGGAN (HERO STATS)
         ======================================================== -->
    <section class="dash-hero-grid">
        <!-- STATISTIK 1: TOTAL ORDER SAYA -->
        <article class="dash-hero-card">
            <div class="dash-hero-top">
                <div class="dash-hero-eyebrow">
                    <i class="bi bi-layers-fill text-primary"></i> TOTAL ORDER SAYA
                </div>
                <div class="dash-hero-icon icon-orders" title="Total seluruh pesanan laundry Anda">
                    <i class="bi bi-basket3-fill"></i>
                </div>
            </div>

            <div class="dash-hero-value">
                <?= number_format($totalOrders, 0, ',', '.') ?>
                <span class="dash-hero-unit">Pesanan</span>
            </div>

            <div class="dash-hero-desc">
                Akumulasi seluruh transaksi laundry yang pernah Anda lakukan di CuciKu.
            </div>

            <div class="dash-hero-pills">
                <span class="dash-hero-pill pill-warning" title="Pesanan menunggu antrean dicuci">
                    <i class="bi bi-hourglass-split"></i> <strong><?= $waiting ?></strong> Menunggu
                </span>
                <span class="dash-hero-pill pill-info" title="Sedang dicuci, dikeringkan, atau disetrika">
                    <i class="bi bi-arrow-repeat"></i> <strong><?= $processing ?></strong> Diproses
                </span>
                <span class="dash-hero-pill pill-success" title="Cucian selesai dan siap diambil">
                    <i class="bi bi-bag-check"></i> <strong><?= $ready ?></strong> Siap Diambil
                </span>
                <span class="dash-hero-pill" title="Cucian sudah selesai dan diserahkan">
                    <i class="bi bi-check2-circle text-muted"></i> <strong><?= $completed ?></strong> Selesai
                </span>
            </div>

            <!-- Segmen Visual Bar Status Cucian -->
            <div class="dash-progress-track" title="Distribusi status pesanan: Kuning (Menunggu), Biru (Diproses), Hijau (Siap Diambil), Toska (Selesai)">
                <div class="dash-progress-segment bg-warning" style="width: <?= $pctWaiting ?>%;"></div>
                <div class="dash-progress-segment bg-info" style="width: <?= $pctProcessing ?>%;"></div>
                <div class="dash-progress-segment bg-success" style="width: <?= $pctReady ?>%;"></div>
                <div class="dash-progress-segment" style="width: <?= $pctCompleted ?>%; background: var(--brand, #267b72);"></div>
            </div>
            <div class="d-flex justify-content-between text-muted mt-2" style="font-size: 11px;">
                <span>Sedang aktif: <strong><?= $activeOrders ?> pesanan</strong></span>
                <span>Total cucian: <strong><?= number_format($totalWeight, 1, ',', '.') ?> kg</strong></span>
            </div>
        </article>

        <!-- STATISTIK 2: TOTAL ORDER BELUM LUNAS -->
        <article class="dash-hero-card">
            <div class="dash-hero-top">
                <div class="dash-hero-eyebrow">
                    <i class="bi bi-wallet2 <?= $unpaidCount > 0 ? 'text-warning' : 'text-success' ?>"></i> TOTAL ORDER BELUM LUNAS
                </div>
                <div class="dash-hero-icon <?= $unpaidCount > 0 ? 'icon-unpaid' : 'icon-all-paid' ?>" title="Status pesanan belum lunas">
                    <i class="bi <?= $unpaidCount > 0 ? 'bi-clock-history' : 'bi-check-circle-fill' ?>"></i>
                </div>
            </div>

            <div class="dash-hero-value <?= $unpaidCount > 0 ? 'text-warning-emphasis' : 'text-success' ?>">
                <?= number_format($unpaidCount, 0, ',', '.') ?>
                <span class="dash-hero-unit text-muted"><?= $unpaidCount > 0 ? 'Pesanan Belum Lunas' : 'Belum Lunas (Semua Lunas)' ?></span>
            </div>

            <div class="dash-hero-desc">
                <?php if ($unpaidCount > 0): ?>
                    Terdapat <strong><?= $unpaidCount ?> pesanan</strong> dengan tagihan tertunda sebesar <strong class="text-danger"><?= rupiah($unpaidTotal) ?></strong>.
                <?php else: ?>
                    Semua tagihan pesanan laundry Anda telah lunas terbayar. Terima kasih!
                <?php endif; ?>
            </div>

            <div class="dash-hero-pills">
                <span class="dash-hero-pill pill-success" title="Jumlah pesanan yang sudah lunas">
                    <i class="bi bi-check-circle-fill"></i> <strong><?= $paidCount ?> Order Lunas</strong> (<?= rupiah($paidTotal) ?>)
                </span>
                <?php if ($unpaidCount > 0): ?>
                <span class="dash-hero-pill pill-warning" title="Tagihan belum lunas">
                    <i class="bi bi-exclamation-circle-fill"></i> Tagihan: <strong><?= rupiah($unpaidTotal) ?></strong>
                </span>
                <?php else: ?>
                <span class="dash-hero-pill pill-success" title="Tidak ada tagihan tertunda">
                    <i class="bi bi-shield-check"></i> Bebas Tagihan (Rp 0)
                </span>
                <?php endif; ?>
            </div>

            <!-- Progress Bar Pelunasan -->
            <div class="dash-progress-track" title="Tingkat pelunasan tagihan: <?= $paymentRate ?>%">
                <div class="dash-progress-segment bg-success" style="width: <?= $paymentRate ?>%;"></div>
                <div class="dash-progress-segment bg-warning" style="width: <?= 100 - $paymentRate ?>%;"></div>
            </div>
            <div class="d-flex justify-content-between text-muted mt-2" style="font-size: 11px;">
                <span>Tingkat pelunasan: <strong><?= $paymentRate ?>% order lunas</strong></span>
                <?php if ($unpaidCount > 0): ?>
                    <span class="text-warning-emphasis fw-semibold">Tertunda: <?= rupiah($unpaidTotal) ?></span>
                <?php else: ?>
                    <span class="text-success fw-semibold"><i class="bi bi-check2"></i> Tidak ada tunggakan</span>
                <?php endif; ?>
            </div>
        </article>
    </section>

    <!-- CARI STRUK PESANAN (QUICK LOOKUP) -->
    <section class="nota-lookup-card" aria-labelledby="notaLookupTitle">
        <div class="nota-lookup-info">
            <div class="nota-lookup-icon">
                <i class="bi bi-receipt-cutoff"></i>
            </div>
            <div class="nota-lookup-text">
                <h2 id="notaLookupTitle">Cari &amp; Cetak Struk Pesanan</h2>
                <p>Masukkan kode nota transaksi untuk melihat rincian pemesanan atau mencetak struk laundry.</p>
            </div>
        </div>
        <form class="nota-lookup-form" method="get" action="cek-nota.php">
            <label class="visually-hidden" for="kodeNotaDashboard">Kode nota transaksi</label>
            <input class="form-control" type="search" id="kodeNotaDashboard"
                   name="nota" placeholder="Contoh: LD-20261014-ABC123"
                   maxlength="40" required>
            <button class="btn btn-brand" type="submit">
                <i class="bi bi-search me-1"></i> Cari Struk
            </button>
        </form>
    </section>

    <!-- ========================================================
         RINGKASAN PESANAN TERBARU
         ======================================================== -->
    <section class="queue-card">
        <div class="queue-card-header">
            <div>
                <div class="section-title-row">
                    <h2>Pesanan Terbaru</h2>
                    <span class="table-count"><?= min(5, $totalOrders) ?> dari <?= $totalOrders ?> pesanan</span>
                </div>
                <p>Daftar pesanan terkini beserta status pengerjaan dan pelunasan pembayaran Anda.</p>
            </div>
            <div class="d-flex align-items-center gap-2">
                <a class="btn btn-outline-brand btn-sm" href="riwayat.php">
                    Lihat Seluruh Riwayat (<?= $totalOrders ?>) <i class="bi bi-arrow-right ms-1"></i>
                </a>
            </div>
        </div>

        <?php if ($totalOrders === 0): ?>
            <!-- EMPTY STATE MENARIK JIKA BELUM ADA PESANAN -->
            <div class="dash-empty-state">
                <div class="dash-empty-icon">
                    <i class="bi bi-basket3"></i>
                </div>
                <h3 class="dash-empty-title">Belum Ada Riwayat Pesanan</h3>
                <p class="dash-empty-desc">
                    Halo <?= e($nama) ?>! Anda belum memiliki riwayat pesanan laundry di CuciKu. Buat pesanan pertamamu sekarang dan nikmati pakaian bersih, wangi, serta rapi tanpa repot.
                </p>
                <a href="buat-pesanan.php" class="btn btn-brand px-4 py-2">
                    <i class="bi bi-plus-lg me-1"></i> Buat Pesanan Sekarang
                </a>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table orders-table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>NO. TRANSAKSI</th>
                            <th>PAKET</th>
                            <th>BERAT</th>
                            <th>TANGGAL MASUK</th>
                            <th>TOTAL PEMBAYARAN</th>
                            <th>STATUS PEMBAYARAN</th>
                            <th>STATUS PENGERJAAN</th>
                            <th class="text-end">AKSI</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach (array_slice($allOrders, 0, 5) as $p):
                        $kode        = $p['kode_transaksi'] ?? '-';
                        $paket       = $p['paket'] ?? 'Reguler';
                        $berat       = (float) ($p['berat'] ?? 0);
                        $tglMasuk    = $p['tanggal_masuk'] ?? null;
                        $totalBiaya  = (float) ($p['total_biaya'] ?? 0);
                        $isLunas     = (($p['pembayaran'] ?? '') === 'lunas');
                        $status      = $p['status'] ?? 'waiting';
                        $idPesanan   = (int) ($p['id'] ?? 0);
                    ?>
                        <tr>
                            <td>
                                <span class="order-code"><?= e($kode) ?></span>
                                <small class="cell-subtext">ID Transaksi #<?= $idPesanan ?></small>
                            </td>

                            <td>
                                <span class="package-pill <?= $paket === 'Express' ? 'package-express' : 'package-regular' ?>">
                                    <i class="bi <?= $paket === 'Express' ? 'bi-lightning-charge-fill' : 'bi-clock' ?> me-1"></i>
                                    <?= e($paket) ?>
                                </span>
                            </td>

                            <td>
                                <strong><?= number_format($berat, 1, ',', '.') ?> kg</strong>
                            </td>

                            <td>
                                <span><?= $tglMasuk ? e(date('d/m/Y H:i', strtotime($tglMasuk))) : '-' ?></span>
                            </td>

                            <td>
                                <strong class="price-text text-dark"><?= rupiah($totalBiaya) ?></strong>
                            </td>

                            <td>
                                <span class="badge <?= $isLunas ? 'bg-success-subtle text-success border border-success-subtle' : 'bg-warning-subtle text-warning-emphasis border border-warning-subtle' ?>" style="font-size: 11px;">
                                    <i class="bi <?= $isLunas ? 'bi-check-circle-fill' : 'bi-clock-history' ?> me-1"></i>
                                    <?= $isLunas ? 'Lunas' : 'Belum Lunas' ?>
                                </span>
                            </td>

                            <td>
                                <span class="status-badge <?= statusClass($status) ?>">
                                    <span class="status-dot"></span>
                                    <?= e(statusLabel($status)) ?>
                                </span>
                            </td>

                            <td class="text-end">
                                <div class="d-inline-flex gap-1">
                                    <a href="detail-pesanan.php?id=<?= $idPesanan ?>" class="btn btn-sm btn-light border py-1 px-2" title="Lihat rincian pesanan" style="font-size: 12px;">
                                        <i class="bi bi-eye me-1"></i> Detail
                                    </a>
                                    <a href="cek-nota.php?nota=<?= urlencode($kode) ?>" class="btn btn-sm btn-light border py-1 px-2" title="Buka struk pesanan" style="font-size: 12px;">
                                        <i class="bi bi-receipt"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="table-footer">
                <span><i class="bi bi-info-circle me-1"></i>Menampilkan <?= min(5, $totalOrders) ?> transaksi terbaru dari akun Anda.</span>
                <a href="riwayat.php">Buka seluruh riwayat pesanan (<?= $totalOrders ?>) <i class="bi bi-arrow-right ms-1"></i></a>
            </div>
        <?php endif; ?>
    </section>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
