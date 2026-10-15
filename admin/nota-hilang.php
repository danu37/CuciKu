<?php
/**
 * nota-hilang.php — Pencarian dan cetak struk digital transaksi laundry.
 *
 * Mengambil data transaksi langsung dari database MySQL dan memungkinkan
 * admin mencetak kembali nota fisik saat pelanggan kehilangan struk.
 */

require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/init.php';

$pageTitle = 'Cari Struk Digital';

$query = trim($_GET['q'] ?? $_GET['code'] ?? '');
$matches = [];
$selected = null;

// Cari transaksi berdasarkan kode transaksi, nama pelanggan, atau nomor WhatsApp
if ($query !== '') {
    try {
        $searchTerm = '%' . $query . '%';
        $stmt = $pdo->prepare("
            SELECT id, kode_transaksi, nama_pelanggan, no_wa, paket, berat,
                   tarif_per_kg, tanggal_masuk, deadline, status, total_biaya, pembayaran
            FROM transaksi
            WHERE kode_transaksi LIKE ?
               OR nama_pelanggan LIKE ?
               OR no_wa LIKE ?
            ORDER BY tanggal_masuk DESC, id DESC
            LIMIT 50
        ");
        $stmt->execute([$searchTerm, $searchTerm, $searchTerm]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as $row) {
            $matches[] = [
                'code'      => $row['kode_transaksi'],
                'customer'  => $row['nama_pelanggan'],
                'phone'     => $row['no_wa'],
                'package'   => $row['paket'],
                'weight'    => (float) $row['berat'],
                'dateIn'    => $row['tanggal_masuk'],
                'deadline'  => $row['deadline'],
                'status'    => $row['status'],
                'rate'      => (int) $row['tarif_per_kg'],
                'total'     => (float) $row['total_biaya'],
                'paid'      => ($row['pembayaran'] === 'lunas'),
            ];
        }
    } catch (PDOException $e) {
        error_log('Gagal mencari transaksi: ' . $e->getMessage());
    }

    // Fallback pencarian pada data sesi demo jika belum ada di database
    if (empty($matches) && !empty($_SESSION['laundry_orders'])) {
        foreach ($_SESSION['laundry_orders'] as $order) {
            if (
                stripos($order['code'], $query) !== false ||
                stripos($order['customer'], $query) !== false ||
                stripos($order['phone'], $query) !== false
            ) {
                $matches[] = $order;
            }
        }
    }
}

// Pagination hasil pencarian (10 per halaman)
$perPage = 10;
$totalMatches = count($matches);
$totalPages = max(1, (int) ceil($totalMatches / $perPage));
$page = max(1, min($totalPages, (int) ($_GET['page'] ?? 1)));
$pageMatches = array_slice($matches, ($page - 1) * $perPage, $perPage);

// Ambil detail struk jika kode transaksi dipilih
$selectedCode = trim($_GET['code'] ?? '');
if ($selectedCode !== '') {
    try {
        $stmtSelected = $pdo->prepare("
            SELECT id, kode_transaksi, nama_pelanggan, no_wa, paket, berat,
                   tarif_per_kg, tanggal_masuk, deadline, status, total_biaya, pembayaran
            FROM transaksi
            WHERE kode_transaksi = ?
            LIMIT 1
        ");
        $stmtSelected->execute([$selectedCode]);
        $row = $stmtSelected->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            $selected = [
                'code'      => $row['kode_transaksi'],
                'customer'  => $row['nama_pelanggan'],
                'phone'     => $row['no_wa'],
                'package'   => $row['paket'],
                'weight'    => (float) $row['berat'],
                'dateIn'    => $row['tanggal_masuk'],
                'deadline'  => $row['deadline'],
                'status'    => $row['status'],
                'rate'      => (int) $row['tarif_per_kg'],
                'total'     => (float) $row['total_biaya'],
                'paid'      => ($row['pembayaran'] === 'lunas'),
            ];
        }
    } catch (PDOException $e) {
        error_log('Gagal memuat detail transaksi: ' . $e->getMessage());
    }

    // Fallback jika ada di sesi demo
    if (!$selected && !empty($_SESSION['laundry_orders'])) {
        foreach ($_SESSION['laundry_orders'] as $order) {
            if ($order['code'] === $selectedCode) {
                $selected = $order;
                break;
            }
        }
    }
}

require __DIR__ . '/../includes/header.php';
?>

<div class="page-heading no-print">
    <div>
        <h1>Nota Hilang</h1>
        <p>Cari kembali struk digital saat pelanggan kehilangan nota kertas.</p>
    </div>
    <span class="notification-summary">
        <i class="bi bi-shield-check me-2"></i>Arsip transaksi
    </span>
</div>

<section class="queue-card no-print">
    <div class="queue-card-header">
        <div>
            <div class="section-title-row">
                <h2><i class="bi bi-search me-2"></i>Cari transaksi</h2>
            </div>
            <p>Masukkan kode transaksi, nama pelanggan, atau nomor WhatsApp.</p>
        </div>
    </div>

    <form method="get" class="receipt-search-form">
        <label class="search-box flex-grow-1" for="receiptSearch">
            <i class="bi bi-search"></i>
            <input id="receiptSearch" name="q" value="<?= e($query) ?>"
                   placeholder="Contoh: LD-20261014-001 atau nama pelanggan" autofocus>
        </label>
        <button class="btn btn-brand" type="submit">Cari struk</button>
    </form>

    <?php if ($query !== ''): ?>
        <div class="receipt-results-title">
            Hasil pencarian: <?= count($matches) ?> transaksi
        </div>

        <?php if (!$matches): ?>
            <div class="notification-empty">
                <i class="bi bi-receipt"></i>
                <strong>Struk tidak ditemukan</strong>
                <span>Periksa kembali kode transaksi atau nama pelanggan.</span>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table orders-table align-middle mb-0">
                    <thead>
                    <tr>
                        <th>NO. TRANSAKSI</th>
                        <th>PELANGGAN</th>
                        <th>PAKET</th>
                        <th>BERAT</th>
                        <th>TOTAL</th>
                        <th>STATUS</th>
                        <th></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($pageMatches as $order): ?>
                        <tr>
                            <td>
                                <span class="order-code"><?= e($order['code']) ?></span>
                                <small class="cell-subtext">
                                    <?= date('d/m/Y', strtotime($order['dateIn'])) ?>
                                </small>
                            </td>
                            <td>
                                <strong><?= e($order['customer']) ?></strong>
                                <small class="cell-subtext"><?= e($order['phone'] ?: '-') ?></small>
                            </td>
                            <td>
                                <span class="package-pill <?= $order['package'] === 'Express' ? 'package-express' : 'package-regular' ?>">
                                    <?= e($order['package']) ?>
                                </span>
                            </td>
                            <td><?= e($order['weight']) ?> kg</td>
                            <td>
                                <strong class="price-text">
                                    <?= rupiah($order['total'] ?? totalOrder($order)) ?>
                                </strong>
                            </td>
                            <td>
                                <span class="status-badge <?= e(statusClass($order['status'])) ?>">
                                    <?= e(statusLabel($order['status'])) ?>
                                </span>
                            </td>
                            <td class="text-end">
                                <a class="btn btn-sm btn-brand"
                                   href="nota-hilang.php?code=<?= urlencode($order['code']) ?>">
                                    <i class="bi bi-receipt me-1"></i>Buka struk
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($totalMatches > 0): ?>
                <div class="d-flex justify-content-between align-items-center p-3 border-top flex-wrap gap-2 bg-light">
                    <span class="text-muted small">
                        Menampilkan <strong><?= (($page - 1) * $perPage + 1) ?></strong> – <strong><?= min($totalMatches, $page * $perPage) ?></strong> dari <strong><?= $totalMatches ?></strong> transaksi (10 per halaman)
                    </span>
                    <?php if ($totalPages > 1): ?>
                        <nav aria-label="Navigasi halaman struk">
                            <ul class="pagination pagination-sm mb-0">
                                <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                                    <a class="page-link" href="?q=<?= urlencode($query) ?>&amp;page=<?= $page - 1 ?>"><i class="bi bi-chevron-left"></i></a>
                                </li>
                                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                    <?php if ($i === 1 || $i === $totalPages || ($i >= $page - 2 && $i <= $page + 2)): ?>
                                        <li class="page-item <?= ($i === $page) ? 'active' : '' ?>">
                                            <a class="page-link" href="?q=<?= urlencode($query) ?>&amp;page=<?= $i ?>"><?= $i ?></a>
                                        </li>
                                    <?php elseif ($i === $page - 3 || $i === $page + 3): ?>
                                        <li class="page-item disabled"><span class="page-link">&hellip;</span></li>
                                    <?php endif; ?>
                                <?php endfor; ?>
                                <li class="page-item <?= ($page >= $totalPages) ? 'disabled' : '' ?>">
                                    <a class="page-link" href="?q=<?= urlencode($query) ?>&amp;page=<?= $page + 1 ?>"><i class="bi bi-chevron-right"></i></a>
                                </li>
                            </ul>
                        </nav>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    <?php else: ?>
        <div class="notification-empty">
            <i class="bi bi-receipt-cutoff"></i>
            <strong>Struk digital tersimpan pada database transaksi</strong>
            <span>Cari menggunakan kode nota, nama pelanggan, atau nomor WhatsApp.</span>
        </div>
    <?php endif; ?>
</section>

<?php if ($selected): ?>
    <section class="queue-card receipt-detail-card">
        <div class="queue-card-header no-print">
            <div>
                <div class="section-title-row">
                    <h2>Pratinjau struk digital</h2>
                </div>
                <p>Struk bisa dicetak kembali sebagai nota kertas.</p>
            </div>
            <button class="btn btn-brand" type="button" onclick="window.print()">
                <i class="bi bi-printer me-2"></i>Cetak struk
            </button>
        </div>

        <div class="receipt-paper receipt-paper-page" id="printReceipt">
            <div class="receipt-brand-mark"><i class="bi bi-droplet-half"></i></div>
            <h3>CuciKu</h3>
            <p class="receipt-tagline">Bukti transaksi laundry</p>
            <div class="receipt-divider"></div>

            <div class="receipt-line">
                <span>No. transaksi</span>
                <strong><?= e($selected['code']) ?></strong>
            </div>
            <div class="receipt-line">
                <span>Tanggal masuk</span>
                <strong><?= e(date('d/m/Y H:i', strtotime($selected['dateIn']))) ?></strong>
            </div>
            <div class="receipt-line">
                <span>Pelanggan</span>
                <strong><?= e($selected['customer']) ?></strong>
            </div>
            <div class="receipt-line">
                <span>WhatsApp</span>
                <strong><?= e($selected['phone'] ?: '-') ?></strong>
            </div>
            <div class="receipt-divider"></div>

            <div class="receipt-line">
                <span>Paket</span>
                <strong><?= e($selected['package']) ?></strong>
            </div>
            <div class="receipt-line">
                <span>Berat pakaian</span>
                <strong><?= e($selected['weight']) ?> kg</strong>
            </div>
            <div class="receipt-line">
                <span>Deadline</span>
                <strong><?= e(date('d/m/Y H:i', strtotime($selected['deadline']))) ?></strong>
            </div>
            <div class="receipt-line">
                <span>Status</span>
                <strong><?= e(statusLabel($selected['status'])) ?></strong>
            </div>
            <div class="receipt-divider"></div>

            <div class="receipt-total">
                <span>Total biaya</span>
                <strong><?= rupiah($selected['total'] ?? totalOrder($selected)) ?></strong>
            </div>
            <div class="receipt-line mt-2">
                <span>Pembayaran</span>
                <strong><?= $selected['paid'] ? 'LUNAS' : 'BELUM LUNAS' ?></strong>
            </div>

            <p class="receipt-thanks">
                Simpan struk ini untuk pengecekan order.<br>
                Terima kasih sudah menggunakan layanan kami.
            </p>
        </div>
    </section>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
