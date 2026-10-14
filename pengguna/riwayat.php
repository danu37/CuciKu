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

function hRiwayat($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function rupiahRiwayat($value)
{
    return 'Rp ' . number_format((float) $value, 0, ',', '.');
}

$statusList = [
    'waiting'   => ['Menunggu', 'warning'],
    'washing'   => ['Sedang Dicuci', 'primary'],
    'drying'    => ['Pengeringan', 'info'],
    'ironing'   => ['Disetrika', 'primary'],
    'ready'     => ['Siap Diambil', 'success'],
    'completed' => ['Selesai', 'success'],
    'cancelled' => ['Dibatalkan', 'danger'],
];

$idPelanggan = (int) $_SESSION['pelanggan_id'];
$kataKunci = trim($_GET['cari'] ?? '');
$filterStatus = trim($_GET['status'] ?? '');

$statusValid = array_keys($statusList);
if (!in_array($filterStatus, $statusValid, true)) {
    $filterStatus = '';
}

$sql = 'SELECT id, kode_transaksi, paket, berat, total_biaya,
               tanggal_masuk, deadline, status, pembayaran
        FROM transaksi
        WHERE pelanggan_id = ?';

$params = [$idPelanggan];

if ($filterStatus !== '') {
    $sql .= ' AND status = ?';
    $params[] = $filterStatus;
}

if ($kataKunci !== '') {
    $sql .= ' AND kode_transaksi LIKE ?';
    $params[] = '%' . $kataKunci . '%';
}

$sql .= ' ORDER BY tanggal_masuk DESC, id DESC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$pesananList = $stmt->fetchAll();

$totalPesanan = count($pesananList);

$judulHalaman = 'Riwayat Pesanan';
$halamanAktif = 'riwayat';

require_once __DIR__ . '/includes/header.php';
?>

<style>
.riwayat-wrap {
    max-width: 1150px;
    margin: 35px auto;
}

.riwayat-title {
    font-size: 28px;
    font-weight: 800;
    color: #183044;
}

.riwayat-subtitle {
    font-size: 14px;
    color: #82909b;
}

.history-card {
    background: #fff;
    border: 1px solid #e5edf1;
    border-radius: 18px;
    box-shadow: 0 8px 30px rgba(24,48,68,.04);
    overflow: hidden;
}

.history-toolbar {
    padding: 22px;
    border-bottom: 1px solid #edf1f4;
}

.history-count {
    display: inline-block;
    padding: 6px 11px;
    border-radius: 20px;
    background: #e4f7f4;
    color: #087f8c;
    font-size: 12px;
    font-weight: 800;
}

.search-input,
.filter-select {
    min-height: 44px;
    border: 1px solid #dfe8ed;
    border-radius: 10px;
    font-size: 13px;
}

.search-input:focus,
.filter-select:focus {
    border-color: #19b5a5;
    box-shadow: 0 0 0 3px #19b5a51c;
}

.history-table {
    min-width: 850px;
    margin-bottom: 0;
}

.history-table thead th {
    padding: 16px;
    background: #f8fafb;
    color: #82909b;
    font-size: 10px;
    letter-spacing: .6px;
    white-space: nowrap;
    border-bottom: 1px solid #e5edf1;
}

.history-table tbody td {
    padding: 17px 16px;
    border-bottom: 1px solid #edf1f4;
    vertical-align: middle;
    font-size: 13px;
}

.history-table tbody tr:last-child td {
    border-bottom: 0;
}

.history-table tbody tr:hover {
    background: #fbfefe;
}

.order-code {
    color: #087f8c;
    font-weight: 800;
    overflow-wrap: anywhere;
}

.cell-muted {
    display: block;
    margin-top: 5px;
    color: #8a98a2;
    font-size: 11px;
}

.package-tag {
    display: inline-block;
    padding: 6px 10px;
    border-radius: 8px;
    background: #eaf3ff;
    color: #2766ad;
    font-size: 11px;
    font-weight: 800;
}

.package-express {
    background: #fff0e4;
    color: #c16b20;
}

.status-tag {
    display: inline-block;
    padding: 7px 10px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 800;
    white-space: nowrap;
}

.status-warning {
    background: #fff4d6;
    color: #936400;
}

.status-primary {
    background: #e7efff;
    color: #315db4;
}

.status-info {
    background: #e0f7fc;
    color: #16718a;
}

.status-success {
    background: #e0f7e9;
    color: #16804b;
}

.status-danger {
    background: #ffe7e7;
    color: #b83232;
}

.status-secondary {
    background: #edf1f4;
    color: #5f6d77;
}

.btn-detail {
    display: inline-block;
    padding: 9px 12px;
    border: 1px solid #cfe9e5;
    border-radius: 9px;
    background: #f0fbf8;
    color: #087f8c;
    font-size: 12px;
    font-weight: 800;
    text-decoration: none;
    white-space: nowrap;
}

.btn-detail:hover {
    background: #087f8c;
    color: #fff;
}

.history-actions {
    display: flex;
    align-items: center;
    gap: 7px;
    white-space: nowrap;
}

.btn-receipt {
    display: inline-block;
    padding: 9px 12px;
    border: 1px solid #dce3ef;
    border-radius: 9px;
    background: #fff;
    color: #53627a;
    font-size: 12px;
    font-weight: 800;
    text-decoration: none;
}

.btn-receipt:hover {
    border-color: #267b72;
    background: #f0fbf8;
    color: #267b72;
}

.empty-history {
    padding: 65px 20px;
    text-align: center;
}

.empty-icon {
    display: grid;
    place-items: center;
    width: 76px;
    height: 76px;
    margin: 0 auto 20px;
    border-radius: 24px;
    background: #eaf8f5;
    font-size: 32px;
}

.btn-order {
    display: inline-block;
    margin-top: 12px;
    padding: 12px 17px;
    border-radius: 10px;
    background: #087f8c;
    color: white;
    font-size: 13px;
    font-weight: 800;
    text-decoration: none;
}

.btn-order:hover {
    background: #075b66;
    color: white;
}

@media (max-width: 576px) {
    .riwayat-wrap {
        margin: 22px auto;
    }

    .riwayat-title {
        font-size: 24px;
    }

    .history-toolbar {
        padding: 17px;
    }
}
</style>

<div class="container riwayat-wrap">

    <div class="d-flex flex-wrap justify-content-between
                align-items-center gap-3 mb-4">
        <div>
            <h1 class="riwayat-title mb-2">Riwayat Pesanan</h1>
            <p class="riwayat-subtitle mb-0">
                Lihat semua pesanan laundry dan perkembangan statusnya.
            </p>
        </div>

        <a href="buat-pesanan.php" class="btn-order mt-0">
            + Pesan Laundry
        </a>
    </div>

    <section class="history-card">

        <div class="history-toolbar">
            <div class="d-flex flex-wrap justify-content-between
                        align-items-center gap-3 mb-3">
                <h2 class="h6 fw-bold mb-0">Daftar Pesanan Kamu</h2>
                <span class="history-count">
                    <?= $totalPesanan ?> pesanan ditemukan
                </span>
            </div>

            <form method="GET" action="riwayat.php"
                  class="row g-2 align-items-center">

                <div class="col-md-6">
                    <input
                        type="search"
                        name="cari"
                        class="form-control search-input"
                        placeholder="Cari nomor nota..."
                        value="<?= hRiwayat($kataKunci) ?>"
                    >
                </div>

                <div class="col-md-4">
                    <select name="status"
                            class="form-select filter-select">
                        <option value="">Semua Status</option>

                        <?php foreach ($statusList as $kode => $info): ?>
                            <option
                                value="<?= hRiwayat($kode) ?>"
                                <?= $filterStatus === $kode ? 'selected' : '' ?>
                            >
                                <?= hRiwayat($info[0]) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-2 d-grid">
                    <button type="submit"
                            class="btn btn-dark"
                            style="min-height:44px;border-radius:10px">
                        Cari
                    </button>
                </div>

                <?php if ($kataKunci !== '' || $filterStatus !== ''): ?>
                    <div class="col-12">
                        <a href="riwayat.php"
                           class="small text-decoration-none">
                            Hapus pencarian dan filter
                        </a>
                    </div>
                <?php endif; ?>

            </form>
        </div>

        <?php if (empty($pesananList)): ?>

            <div class="empty-history">
                <div class="empty-icon">🧺</div>
                <h3 class="h5 fw-bold">Belum ada pesanan</h3>
                <p class="text-secondary small">
                    Pesanan yang sesuai dengan pencarian kamu belum ditemukan.
                </p>

                <?php if ($kataKunci !== '' || $filterStatus !== ''): ?>
                    <a href="riwayat.php" class="btn-order">
                        Tampilkan Semua Pesanan
                    </a>
                <?php else: ?>
                    <a href="buat-pesanan.php" class="btn-order">
                        Buat Pesanan Pertama
                    </a>
                <?php endif; ?>
            </div>

        <?php else: ?>

            <div class="table-responsive">
                <table class="table history-table align-middle">
                    <thead>
                        <tr>
                            <th>NOMOR NOTA</th>
                            <th>PAKET</th>
                            <th>BERAT</th>
                            <th>TANGGAL MASUK</th>
                            <th>TOTAL BIAYA</th>
                            <th>STATUS</th>
                            <th>PEMBAYARAN</th>
                            <th>AKSI</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php foreach ($pesananList as $pesanan): ?>
                            <?php
                            $status = $statusList[$pesanan['status']]
                                ?? ['Tidak diketahui', 'secondary'];

                            $kelasStatus = 'status-' . $status[1];

                            $tanggal = $pesanan['tanggal_masuk']
                                ? date(
                                    'd M Y, H:i',
                                    strtotime($pesanan['tanggal_masuk'])
                                )
                                : '-';

                            $kelasPaket = $pesanan['paket'] === 'Express'
                                ? 'package-express'
                                : '';
                            ?>

                            <tr>
                                <td>
                                    <span class="order-code">
                                        <?= hRiwayat($pesanan['kode_transaksi']) ?>
                                    </span>
                                    <span class="cell-muted">
                                        <?= hRiwayat($tanggal) ?>
                                    </span>
                                </td>

                                <td>
                                    <span class="package-tag <?= $kelasPaket ?>">
                                        <?= hRiwayat($pesanan['paket']) ?>
                                    </span>
                                </td>

                                <td>
                                    <strong>
                                        <?= number_format(
                                            (float) $pesanan['berat'],
                                            2,
                                            ',',
                                            '.'
                                        ) ?> kg
                                    </strong>
                                </td>

                                <td><?= hRiwayat($tanggal) ?></td>

                                <td>
                                    <strong>
                                        <?= rupiahRiwayat($pesanan['total_biaya']) ?>
                                    </strong>
                                </td>

                                <td>
                                    <span class="status-tag <?= hRiwayat($kelasStatus) ?>">
                                        <?= hRiwayat($status[0]) ?>
                                    </span>
                                </td>

                                <td>
                                    <?php if ($pesanan['pembayaran'] === 'lunas'): ?>
                                        <span class="text-success fw-bold">
                                            Lunas
                                        </span>
                                    <?php else: ?>
                                        <span class="text-warning fw-bold">
                                            Belum lunas
                                        </span>
                                    <?php endif; ?>
                                </td>

                                <td>
                                    <div class="history-actions">
                                        <a
                                            href="detail-pesanan.php?id=<?= (int) $pesanan['id'] ?>"
                                            class="btn-detail"
                                        >
                                            Detail
                                        </a>
                                        <a
                                            href="cek-nota.php?nota=<?= urlencode($pesanan['kode_transaksi']) ?>"
                                            class="btn-receipt"
                                        >
                                            <i class="bi bi-receipt me-1"></i>Struk
                                        </a>
                                    </div>
                                </td>
                            </tr>

                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

        <?php endif; ?>

    </section>

    <p class="small text-secondary mt-3 mb-0">
        <i class="bi bi-shield-check me-1"></i>
        Riwayat hanya menampilkan pesanan milik akun yang sedang login.
    </p>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>