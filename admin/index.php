<?php
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/init.php';

$pageTitle = 'Dashboard';

// Ambil seluruh transaksi dari database
$stmt = $pdo->query("
    SELECT *
    FROM transaksi
    ORDER BY tanggal_masuk DESC, id DESC
");
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalOrders    = count($orders);
$waiting        = 0;
$processing     = 0;
$ready          = 0;
$completed      = 0;
$cancelled      = 0;
$paidRevenue    = 0;
$unpaidRevenue  = 0;
$paidCount      = 0;
$unpaidCount    = 0;
$totalWeight    = 0;
$pkgCounts      = ['Reguler' => 0, 'Express' => 0];
$pkgRevenues    = ['Reguler' => 0, 'Express' => 0];

foreach ($orders as $o) {
    $st     = $o['status'] ?? 'waiting';
    $cost   = (float) ($o['total_biaya'] ?? 0);
    $weight = (float) ($o['berat'] ?? 0);
    $isPaid = (($o['pembayaran'] ?? '') === 'lunas');
    $pkg    = ($o['paket'] ?? 'Reguler') === 'Express' ? 'Express' : 'Reguler';

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
        $pkgCounts[$pkg]++;
        $pkgRevenues[$pkg] += $cost;

        if ($isPaid) {
            $paidRevenue += $cost;
            $paidCount++;
        } else {
            $unpaidRevenue += $cost;
            $unpaidCount++;
        }
    }
}

$activeOrders     = $waiting + $processing + $ready;
$validOrdersCount = max(1, $totalOrders - $cancelled);
$avgWeight        = round($totalWeight / $validOrdersCount, 1);
$totalBilled      = $paidRevenue + $unpaidRevenue;
$collectionRate   = $totalBilled > 0 ? round(($paidRevenue / $totalBilled) * 100, 1) : 0;

// Data Tren 7 Hari Terakhir untuk Visualisasi Grafik
$daysMap = [];
$indoMonths = [
    '01' => 'Jan', '02' => 'Feb', '03' => 'Mar', '04' => 'Apr',
    '05' => 'Mei', '06' => 'Jun', '07' => 'Jul', '08' => 'Agu',
    '09' => 'Sep', '10' => 'Okt', '11' => 'Nov', '12' => 'Des'
];

for ($i = 6; $i >= 0; $i--) {
    $dateKey = date('Y-m-d', strtotime("-$i days"));
    $d = date('d', strtotime($dateKey));
    $m = date('m', strtotime($dateKey));
    $labelIndo = $d . ' ' . ($indoMonths[$m] ?? date('M', strtotime($dateKey)));

    $daysMap[$dateKey] = [
        'label'   => $labelIndo,
        'orders'  => 0,
        'revenue' => 0
    ];
}

$stmtDaily = $pdo->query("
    SELECT DATE(tanggal_masuk) as tgl,
           COUNT(*) as daily_orders,
           SUM(CASE WHEN pembayaran = 'lunas' AND status != 'cancelled' THEN total_biaya ELSE 0 END) as daily_revenue
    FROM transaksi
    WHERE tanggal_masuk >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
    GROUP BY DATE(tanggal_masuk)
");

while ($row = $stmtDaily->fetch(PDO::FETCH_ASSOC)) {
    if (isset($daysMap[$row['tgl']])) {
        $daysMap[$row['tgl']]['orders']  = (int) $row['daily_orders'];
        $daysMap[$row['tgl']]['revenue'] = (float) $row['daily_revenue'];
    }
}

$chartLabels  = array_column($daysMap, 'label');
$chartOrders  = array_column($daysMap, 'orders');
$chartRevenue = array_column($daysMap, 'revenue');

// Hitung persentase segmen status untuk progress bar visual
$pctWaiting    = $totalOrders > 0 ? round(($waiting / $totalOrders) * 100, 1) : 0;
$pctProcessing = $totalOrders > 0 ? round(($processing / $totalOrders) * 100, 1) : 0;
$pctReady      = $totalOrders > 0 ? round(($ready / $totalOrders) * 100, 1) : 0;
$pctCompleted  = $totalOrders > 0 ? round(($completed / $totalOrders) * 100, 1) : 0;

require __DIR__ . '/../includes/header.php';

function statusAdmin($status)
{
    return statusLabel($status);
}

function rupiahAdmin($nominal)
{
    return rupiah($nominal);
}
?>

<div class="page-heading">
    <div>
        <h1>Dashboard Operasional</h1>
        <p>Pantau metrik utama, ringkasan pendapatan, dan status antrean cucian dalam satu tempat.</p>
    </div>
    <div class="d-flex align-items-center gap-2 flex-wrap">
        <a class="btn btn-outline-brand" href="pelanggan.php?modal=add">
            <i class="bi bi-person-plus me-1"></i> Tambah Pelanggan
        </a>
        <a class="btn btn-brand" href="antrean.php?modal=add">
            <i class="bi bi-plus-lg me-1"></i> Tambah Transaksi
        </a>
    </div>
</div>

<?php if ($ready > 0): ?>
<div class="ready-alert">
    <div class="ready-alert-icon">
        <i class="bi bi-bell-fill"></i>
    </div>
    <div class="ready-alert-copy">
        <strong><?= $ready ?> cucian telah Siap Diambil</strong>
        <span>Terdapat order yang selesai diproses dan siap diserahkan kepada pelanggan.</span>
    </div>
    <a href="antrean.php?status=ready" class="ready-alert-link">
        Lihat antrean siap <i class="bi bi-arrow-right"></i>
    </a>
</div>
<?php endif; ?>

<!-- ========================================================
     DUA STATISTIK UTAMA (HERO STATS)
     ======================================================== -->
<section class="dash-hero-grid">
    <!-- STATISTIK 1: TOTAL ORDER -->
    <article class="dash-hero-card">
        <div class="dash-hero-top">
            <div class="dash-hero-eyebrow">
                <i class="bi bi-layers-fill text-primary"></i> TOTAL ORDER
            </div>
            <div class="dash-hero-icon icon-orders" title="Total seluruh pesanan">
                <i class="bi bi-bag-check-fill"></i>
            </div>
        </div>

        <div class="dash-hero-value">
            <?= number_format($totalOrders, 0, ',', '.') ?>
            <span class="dash-hero-unit">Pesanan</span>
        </div>

        <div class="dash-hero-desc">
            Akumulasi seluruh transaksi laundry yang tercatat di dalam sistem.
        </div>

        <div class="dash-hero-pills">
            <span class="dash-hero-pill pill-warning" title="Pesanan belum mulai dicuci">
                <i class="bi bi-hourglass-split"></i> <strong><?= $waiting ?></strong> Menunggu
            </span>
            <span class="dash-hero-pill pill-info" title="Sedang dicuci, dikeringkan, atau disetrika">
                <i class="bi bi-arrow-repeat"></i> <strong><?= $processing ?></strong> Dikerjakan
            </span>
            <span class="dash-hero-pill pill-success" title="Cucian selesai dan siap diambil">
                <i class="bi bi-bag-check"></i> <strong><?= $ready ?></strong> Siap Diambil
            </span>
            <span class="dash-hero-pill" title="Cucian sudah diserahkan ke pelanggan">
                <i class="bi bi-check2-circle text-muted"></i> <strong><?= $completed ?></strong> Selesai
            </span>
        </div>

        <!-- Segmen Bar Visual Distribusi Status -->
        <div class="dash-progress-track" title="Distribusi status pesanan: Kuning (Menunggu), Biru (Proses), Hijau (Siap), Toska (Selesai)">
            <div class="dash-progress-segment bg-warning" style="width: <?= $pctWaiting ?>%;"></div>
            <div class="dash-progress-segment bg-info" style="width: <?= $pctProcessing ?>%;"></div>
            <div class="dash-progress-segment bg-success" style="width: <?= $pctReady ?>%;"></div>
            <div class="dash-progress-segment" style="width: <?= $pctCompleted ?>%; background: var(--brand, #267b72);"></div>
        </div>
        <div class="d-flex justify-content-between text-muted mt-2" style="font-size: 11px;">
            <span>Aktif berjalan: <strong><?= $activeOrders ?> order</strong></span>
            <span>Total beban: <strong><?= number_format($totalWeight, 1, ',', '.') ?> kg</strong></span>
        </div>
    </article>

    <!-- STATISTIK 2: TOTAL PENDAPATAN (LUNAS) -->
    <article class="dash-hero-card">
        <div class="dash-hero-top">
            <div class="dash-hero-eyebrow">
                <i class="bi bi-cash-stack text-success"></i> TOTAL PENDAPATAN (LUNAS)
            </div>
            <div class="dash-hero-icon icon-revenue" title="Total pendapatan terverifikasi lunas">
                <i class="bi bi-wallet2"></i>
            </div>
        </div>

        <div class="dash-hero-value text-success">
            <?= rupiahAdmin($paidRevenue) ?>
        </div>

        <div class="dash-hero-desc">
            Penerimaan kas yang telah terverifikasi lunas dari transaksi valid.
        </div>

        <div class="dash-hero-pills">
            <span class="dash-hero-pill pill-success" title="Jumlah transaksi yang telah lunas dibayar">
                <i class="bi bi-check-circle-fill"></i> <strong><?= $paidCount ?> Order Lunas</strong> (<?= $collectionRate ?>%)
            </span>
            <span class="dash-hero-pill pill-warning" title="Total tagihan yang belum dibayar pelanggan">
                <i class="bi bi-clock-history"></i> Belum Lunas: <strong><?= rupiahAdmin($unpaidRevenue) ?></strong> (<?= $unpaidCount ?> order)
            </span>
        </div>

        <!-- Progress Bar Pelunasan Tagihan -->
        <div class="dash-progress-track" title="Tingkat pelunasan tagihan: <?= $collectionRate ?>%">
            <div class="dash-progress-segment bg-success" style="width: <?= $collectionRate ?>%;"></div>
            <div class="dash-progress-segment bg-warning" style="width: <?= 100 - $collectionRate ?>%;"></div>
        </div>
        <div class="d-flex justify-content-between text-muted mt-2" style="font-size: 11px;">
            <span>Tingkat pelunasan: <strong><?= $collectionRate ?>% terealisasi</strong></span>
            <span>Potensi omzet: <strong><?= rupiahAdmin($totalBilled) ?></strong></span>
        </div>
    </article>
</section>

<!-- ========================================================
     VISUALISASI RINGKAS & ANALITIK OPERASIONAL
     ======================================================== -->
<section class="dash-analytics-grid">
    <!-- Grafik Tren 7 Hari Terakhir -->
    <article class="dash-chart-card">
        <div class="dash-chart-header">
            <div>
                <h2 class="dash-chart-title">Tren Aktivitas &amp; Pendapatan</h2>
                <div class="dash-chart-subtitle">Realisasi omzet lunas dan volume order masuk 7 hari terakhir.</div>
            </div>
            <div class="dash-chart-legend">
                <span class="dash-chart-legend-item">
                    <span class="dash-chart-legend-dot" style="background: var(--brand, #267b72);"></span> Pendapatan (Rp)
                </span>
                <span class="dash-chart-legend-item">
                    <span class="dash-chart-legend-dot" style="background: #0284c7;"></span> Order Masuk
                </span>
            </div>
        </div>

        <div class="dash-chart-container">
            <canvas id="dashRevenueChart"></canvas>
        </div>
    </article>

    <!-- Ringkasan Layanan & Alur Antrean -->
    <article class="dash-side-card">
        <div>
            <div class="d-flex align-items-center justify-content-between mb-3">
                <h2 class="dash-chart-title">Komposisi Layanan</h2>
                <span class="badge bg-light text-secondary border">Paket Laundry</span>
            </div>

            <!-- Paket Reguler -->
            <div class="dash-package-item mb-2">
                <div>
                    <div class="dash-package-label">
                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1">Reguler</span>
                        <span><?= $pkgCounts['Reguler'] ?? 0 ?> Transaksi</span>
                    </div>
                    <small class="text-muted" style="font-size: 11px;">Estimasi standar 2 hari</small>
                </div>
                <div class="text-end">
                    <strong class="text-dark d-block" style="font-size: 13.5px;"><?= rupiahAdmin($pkgRevenues['Reguler'] ?? 0) ?></strong>
                    <small class="text-muted" style="font-size: 11px;"><?= $totalOrders > 0 ? round((($pkgCounts['Reguler'] ?? 0) / $totalOrders) * 100) : 0 ?>% porsi</small>
                </div>
            </div>

            <!-- Paket Express -->
            <div class="dash-package-item mb-3">
                <div>
                    <div class="dash-package-label">
                        <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle px-2 py-1">Express</span>
                        <span><?= $pkgCounts['Express'] ?? 0 ?> Transaksi</span>
                    </div>
                    <small class="text-muted" style="font-size: 11px;">Selesai kilat 24 jam</small>
                </div>
                <div class="text-end">
                    <strong class="text-dark d-block" style="font-size: 13.5px;"><?= rupiahAdmin($pkgRevenues['Express'] ?? 0) ?></strong>
                    <small class="text-muted" style="font-size: 11px;"><?= $totalOrders > 0 ? round((($pkgCounts['Express'] ?? 0) / $totalOrders) * 100) : 0 ?>% porsi</small>
                </div>
            </div>
        </div>

        <div>
            <div class="text-muted fw-bold mb-2" style="font-size: 11px; letter-spacing: .05em; text-transform: uppercase;">
                Alur Antrean Saat Ini
            </div>
            <div class="dash-status-strip mb-3">
                <div class="dash-status-strip-item">
                    <strong class="text-warning"><?= $waiting ?></strong>
                    <span>Menunggu</span>
                </div>
                <div class="dash-status-strip-item">
                    <strong class="text-info"><?= $processing ?></strong>
                    <span>Dicuci/Setrika</span>
                </div>
                <div class="dash-status-strip-item">
                    <strong class="text-success"><?= $ready ?></strong>
                    <span>Siap Ambil</span>
                </div>
                <div class="dash-status-strip-item">
                    <strong style="color: var(--brand, #267b72);"><?= $completed ?></strong>
                    <span>Selesai</span>
                </div>
            </div>

            <a href="antrean.php" class="btn btn-light border w-100 py-2 fw-semibold d-flex align-items-center justify-content-center gap-2" style="font-size: 12.5px;">
                <i class="bi bi-list-ol"></i> Buka Antrean &amp; Order Lengkap
                <i class="bi bi-arrow-right"></i>
            </a>
        </div>
    </article>
</section>

<!-- ========================================================
     TABEL ANTREAN TERDEKAT
     ======================================================== -->
<section class="queue-card">
    <div class="queue-card-header">
        <div>
            <div class="section-title-row">
                <h2>Antrean Terdekat</h2>
                <span class="table-count"><?= min(6, $activeOrders) ?> aktif</span>
            </div>
            <p>Daftar pesanan aktif dengan target deadline pengerjaan paling dekat.</p>
        </div>
        <a class="btn btn-outline-secondary btn-sm" href="antrean.php">
            Lihat semua antrean (<?= $totalOrders ?>) <i class="bi bi-arrow-right ms-1"></i>
        </a>
    </div>

    <div class="table-responsive">
        <table class="table orders-table align-middle mb-0">
            <thead>
                <tr>
                    <th>NO. TRANSAKSI</th>
                    <th>PELANGGAN</th>
                    <th>PAKET</th>
                    <th>BERAT</th>
                    <th>DEADLINE</th>
                    <th>TOTAL BIAYA</th>
                    <th>PEMBAYARAN</th>
                    <th>STATUS</th>
                    <th class="text-end">AKSI</th>
                </tr>
            </thead>
            <tbody>
            <?php
            $shown = 0;

            foreach ($orders as $o):
                if (in_array($o['status'], ['completed', 'cancelled'], true)) {
                    continue;
                }

                if ($shown >= 6) break;
                $shown++;

                $nama       = $o['nama_pelanggan'] ?? 'Pelanggan';
                $kode       = $o['kode_transaksi'] ?? '-';
                $tanggal    = $o['tanggal_masuk'] ?? null;
                $deadline   = $o['deadline'] ?? null;
                $paket      = $o['paket'] ?? 'Reguler';
                $berat      = (float) ($o['berat'] ?? 0);
                $totalBiaya = (float) ($o['total_biaya'] ?? 0);
                $status     = $o['status'] ?? 'waiting';
                $noWa       = $o['no_wa'] ?? '-';
                $isLunas    = (($o['pembayaran'] ?? '') === 'lunas');
            ?>
                <tr>
                    <td>
                        <span class="order-code"><?= e($kode) ?></span>
                        <small class="cell-subtext">
                            Masuk <?= $tanggal ? date('d/m H:i', strtotime($tanggal)) : '-' ?>
                        </small>
                    </td>

                    <td>
                        <div class="customer-cell">
                            <span class="customer-avatar">
                                <?= e(strtoupper(substr($nama, 0, 1))) ?>
                            </span>
                            <span>
                                <strong><?= e($nama) ?></strong>
                                <small><?= e($noWa) ?></small>
                            </span>
                        </div>
                    </td>

                    <td>
                        <span class="package-pill <?= $paket === 'Express' ? 'package-express' : 'package-regular' ?>">
                            <?= e($paket) ?>
                        </span>
                    </td>

                    <td><strong><?= number_format($berat, 1, ',', '.') ?> kg</strong></td>

                    <td>
                        <span class="deadline-text">
                            <?= $deadline ? e(date('d/m/Y H:i', strtotime($deadline))) : '-' ?>
                        </span>
                    </td>

                    <td>
                        <strong class="price-text">
                            <?= rupiahAdmin($totalBiaya) ?>
                        </strong>
                    </td>

                    <td>
                        <span class="badge <?= $isLunas ? 'bg-success-subtle text-success border border-success-subtle' : 'bg-warning-subtle text-warning-emphasis border border-warning-subtle' ?>" style="font-size: 11px;">
                            <i class="bi <?= $isLunas ? 'bi-check-circle-fill' : 'bi-clock-history' ?> me-1"></i>
                            <?= $isLunas ? 'Lunas' : 'Belum Lunas' ?>
                        </span>
                    </td>

                    <td>
                        <span class="status-badge">
                            <span class="status-dot"></span>
                            <?= e(statusAdmin($status)) ?>
                        </span>
                    </td>

                    <td class="text-end">
                        <a href="antrean.php?modal=edit&code=<?= urlencode($kode) ?>" class="btn btn-sm btn-light border py-1 px-2" title="Kelola rincian transaksi" style="font-size: 12px;">
                            <i class="bi bi-pencil-square me-1"></i> Kelola
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>

            <?php if ($shown === 0): ?>
                <tr>
                    <td colspan="9" class="text-center py-5">
                        <i class="bi bi-inbox fs-1 text-muted d-block mb-2 opacity-50"></i>
                        <strong class="d-block text-dark fs-6 mb-1">Belum Ada Pesanan Aktif</strong>
                        <span class="text-muted small">Semua transaksi cucian telah selesai diproses atau belum ada pesanan baru.</span>
                    </td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="table-footer">
        <span><i class="bi bi-info-circle me-1"></i>Data transaksi diambil langsung dari basis data sistem secara waktu-nyata.</span>
        <a href="antrean.php">Lihat semua order <i class="bi bi-arrow-right"></i></a>
    </div>
</section>

<!-- Chart.js Library untuk Visualisasi Tren -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('dashRevenueChart');
    if (!ctx) return;

    const chartLabels  = <?= json_encode($chartLabels) ?>;
    const chartRevenue = <?= json_encode($chartRevenue) ?>;
    const chartOrders  = <?= json_encode($chartOrders) ?>;

    new Chart(ctx, {
        data: {
            labels: chartLabels,
            datasets: [
                {
                    type: 'bar',
                    label: 'Pendapatan Lunas',
                    data: chartRevenue,
                    backgroundColor: 'rgba(38, 123, 114, 0.82)',
                    hoverBackgroundColor: 'rgba(31, 102, 95, 1)',
                    borderRadius: 6,
                    maxBarThickness: 34,
                    yAxisID: 'yRev',
                    order: 2
                },
                {
                    type: 'line',
                    label: 'Jumlah Order',
                    data: chartOrders,
                    borderColor: '#0284c7',
                    backgroundColor: '#0284c7',
                    borderWidth: 2.5,
                    tension: 0.35,
                    pointRadius: 4,
                    pointHoverRadius: 6,
                    yAxisID: 'yOrders',
                    order: 1
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: {
                mode: 'index',
                intersect: false
            },
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    backgroundColor: '#1e293b',
                    padding: 10,
                    cornerRadius: 8,
                    titleFont: { size: 12, weight: 'bold' },
                    bodyFont: { size: 11.5 },
                    callbacks: {
                        label: function(context) {
                            if (context.dataset.yAxisID === 'yRev') {
                                return ' Pendapatan: Rp ' + new Intl.NumberFormat('id-ID').format(context.raw);
                            }
                            return ' Order: ' + context.raw + ' pesanan';
                        }
                    }
                }
            },
            scales: {
                x: {
                    grid: {
                        display: false
                    },
                    ticks: {
                        color: '#64748b',
                        font: { size: 11 }
                    }
                },
                yRev: {
                    type: 'linear',
                    position: 'left',
                    beginAtZero: true,
                    grid: {
                        color: 'rgba(226, 232, 240, 0.7)'
                    },
                    ticks: {
                        color: '#64748b',
                        font: { size: 10.5 },
                        callback: function(val) {
                            if (val >= 1000000) return (val / 1000000).toFixed(1) + ' jt';
                            if (val >= 1000) return (val / 1000) + ' rb';
                            return 'Rp ' + val;
                        }
                    }
                },
                yOrders: {
                    type: 'linear',
                    position: 'right',
                    beginAtZero: true,
                    grid: {
                        display: false
                    },
                    ticks: {
                        stepSize: 1,
                        precision: 0,
                        color: '#0284c7',
                        font: { size: 10.5 }
                    }
                }
            }
        }
    });
});
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>