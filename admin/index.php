<?php
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/init.php';

$pageTitle = 'Dashboard';

// Ambil transaksi dari database
$stmt = $pdo->query("
    SELECT *
    FROM transaksi
    ORDER BY tanggal_masuk DESC, id DESC
");

$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $total = count($orders);
    $waiting = 0;
    $processing = 0;
    $ready = 0;
    $completed = 0;
    $revenue = 0;

    foreach ($orders as $o) {
        if ($o['status'] === 'waiting') $waiting++;

        if (in_array($o['status'], ['washing', 'drying', 'ironing'], true)) {
            $processing++;
        }

        if ($o['status'] === 'ready') $ready++;
        if ($o['status'] === 'completed') $completed++;

        if (($o['pembayaran'] ?? '') === 'lunas') {
            $revenue += (float) ($o['total_biaya'] ?? 0);
        }
    }

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
            <h1>Dashboard</h1>
            <p>Pantau transaksi, antrean, dan status cucian dalam satu tempat.</p>
        </div>
        <a class="btn btn-brand" href="antrean.php?modal=add">
            <i class="bi bi-plus-lg me-2"></i>Tambah transaksi
        </a>
    </div>

    <?php showFlash(); ?>

    <?php if ($ready > 0): ?>
    <div class="ready-alert">
        <div class="ready-alert-icon">
            <i class="bi bi-bell-fill"></i>
        </div>
        <div class="ready-alert-copy">
            <strong><?= $ready ?> cucian siap diambil</strong>
            <span>Periksa order yang siap diserahkan kepada pelanggan.</span>
        </div>
        <a href="notifikasi.php" class="ready-alert-link">
            Lihat notifikasi <i class="bi bi-arrow-right"></i>
        </a>
    </div>
    <?php endif; ?>

    <section class="stats-grid">
        <article class="stat-card">
            <div class="stat-top">
                <span>Total transaksi</span>
                <span class="stat-icon icon-blue"><i class="bi bi-receipt"></i></span>
            </div>
            <div class="stat-number"><?= $total ?></div>
            <div class="stat-foot"><span class="foot-muted">Semua order tercatat</span></div>
        </article>

        <article class="stat-card">
            <div class="stat-top">
                <span>Menunggu proses</span>
                <span class="stat-icon icon-orange"><i class="bi bi-hourglass-split"></i></span>
            </div>
            <div class="stat-number"><?= $waiting ?></div>
            <div class="stat-foot"><span class="foot-muted">Belum mulai dicuci</span></div>
        </article>

        <article class="stat-card">
            <div class="stat-top">
                <span>Sedang diproses</span>
                <span class="stat-icon icon-purple"><i class="bi bi-arrow-repeat"></i></span>
            </div>
            <div class="stat-number"><?= $processing ?></div>
            <div class="stat-foot"><span class="foot-muted">Dalam pengerjaan</span></div>
        </article>

        <article class="stat-card">
            <div class="stat-top">
                <span>Siap diambil</span>
                <span class="stat-icon icon-green"><i class="bi bi-bag-check"></i></span>
            </div>
            <div class="stat-number"><?= $ready ?></div>
            <div class="stat-foot">
                <span class="foot-positive">
                    <i class="bi bi-check-circle me-1"></i>Menunggu penyerahan
                </span>
            </div>
        </article>
    </section>

    <section class="queue-card">
        <div class="queue-card-header">
            <div>
                <div class="section-title-row">
                    <h2>Antrean terdekat</h2>
                    <span class="table-count"><?= min(5, $total) ?> order</span>
                </div>
                <p>Order aktif dengan deadline paling dekat.</p>
            </div>
            <a class="btn btn-outline-secondary btn-sm" href="antrean.php">
                Lihat semua order <i class="bi bi-arrow-right ms-1"></i>
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
                        <th>STATUS</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                $shown = 0;

                foreach ($orders as $o):
                    if (in_array($o['status'], ['completed', 'cancelled'], true)) {
                        continue;
                    }

                    if ($shown >= 5) break;
                    $shown++;

                    $nama = $o['nama_pelanggan'] ?? 'Pelanggan';
                    $kode = $o['kode_transaksi'] ?? '-';
                    $tanggal = $o['tanggal_masuk'] ?? null;
                    $deadline = $o['deadline'] ?? null;
                    $paket = $o['paket'] ?? 'Reguler';
                    $berat = $o['berat'] ?? 0;
                    $totalBiaya = $o['total_biaya'] ?? 0;
                    $status = $o['status'] ?? 'waiting';
                    $noWa = $o['no_wa'] ?? '-';
                ?>
                    <tr>
                        <td>
                            <span class="order-code"><?= e($kode) ?></span>
                            <small class="cell-subtext">
                                Masuk <?= $tanggal ? date('H.i', strtotime($tanggal)) : '-' ?>
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

                        <td><strong><?= e($berat) ?> kg</strong></td>

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
                            <span class="status-badge">
                                <span class="status-dot"></span>
                                <?= e(statusAdmin($status)) ?>
                            </span>
                        </td>
                    </tr>
                <?php endforeach; ?>

                <?php if ($shown === 0): ?>
                    <tr>
                        <td colspan="7" class="text-center py-5">
                            <i class="bi bi-inbox fs-1 text-muted d-block mb-2 opacity-50"></i>
                            <strong class="d-block text-dark fs-6 mb-1">Belum Ada Pesanan Aktif</strong>
                            <span class="text-muted small">Semua transaksi telah selesai atau belum ada pesanan baru yang masuk.</span>
                        </td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="table-footer">
            <span><i class="bi bi-info-circle me-1"></i>Data transaksi diambil dari database.</span>
            <a href="antrean.php">Lihat antrean <i class="bi bi-arrow-right"></i></a>
        </div>
    </section>

    <?php require __DIR__ . '/../includes/footer.php'; ?>