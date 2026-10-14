<?php
ini_set('display_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/init.php';

$pageTitle = 'Antrean & Order';

$allowedStatuses = [
    'waiting', 'washing', 'drying', 'ironing',
    'ready', 'completed', 'cancelled'
];

$statusLabels = [
    'waiting' => 'Menunggu',
    'washing' => 'Sedang Dicuci',
    'drying' => 'Pengeringan',
    'ironing' => 'Penyetrikaan',
    'ready' => 'Siap Diambil',
    'completed' => 'Selesai',
    'cancelled' => 'Dibatalkan'
];

$errors = [];

function antreanRupiah($nilai)
{
    return 'Rp ' . number_format((float)$nilai, 0, ',', '.');
}

function antreanTanggal($tanggal)
{
    if (!$tanggal) {
        return '-';
    }

    return date('d/m/Y H:i', strtotime($tanggal));
}

function antreanStatusClass($status)
{
    $classes = [
        'waiting' => 'waiting',
        'washing' => 'washing',
        'drying' => 'drying',
        'ironing' => 'ironing',
        'ready' => 'ready',
        'completed' => 'completed',
        'cancelled' => 'cancelled'
    ];

    return $classes[$status] ?? 'waiting';
}

function antreanE($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

/* =========================
   TAMBAH DAN EDIT TRANSAKSI
========================= */

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $action = $_POST['action'] ?? '';

    if (in_array($action, ['add_order', 'edit_order'], true)) {
        $code = trim($_POST['code'] ?? '');
        $customer = trim($_POST['customer'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $package = $_POST['package'] ?? 'Reguler';
        $weight = (float)($_POST['weight'] ?? 0);
        $dateRaw = str_replace('T', ' ', $_POST['dateIn'] ?? '');
        $dateObj = DateTime::createFromFormat('Y-m-d H:i', $dateRaw);
        $status = $_POST['status'] ?? 'waiting';
        $paid = isset($_POST['paid']);

        if ($customer === '') {
            $errors[] = 'Nama pelanggan wajib diisi.';
        }

        if (!in_array($package, ['Reguler', 'Express'], true)) {
            $errors[] = 'Paket layanan tidak valid.';
        }

        if ($weight <= 0 || $weight > 200) {
            $errors[] = 'Berat harus lebih dari 0 dan maksimal 200 kg.';
        }

        if (!$dateObj) {
            $errors[] = 'Tanggal masuk tidak valid.';
        }

        if (
            $action === 'edit_order' &&
            !in_array($status, $allowedStatuses, true)
        ) {
            $errors[] = 'Status transaksi tidak valid.';
        }

        $oldOrder = null;

        if ($action === 'edit_order') {
            if ($code === '') {
                $errors[] = 'Kode transaksi tidak valid.';
            } else {
                $check = $pdo->prepare(
                    'SELECT id, status FROM transaksi
                     WHERE kode_transaksi = ? LIMIT 1'
                );
                $check->execute([$code]);
                $oldOrder = $check->fetch(PDO::FETCH_ASSOC);

                if (!$oldOrder) {
                    $errors[] = 'Transaksi tidak ditemukan.';
                }
            }
        }

        if (!$errors) {
            $rate = $package === 'Express' ? 14000 : 7000;
            $hours = $package === 'Express' ? 12 : 48;

            $deadline = clone $dateObj;
            $deadline->modify('+' . $hours . ' hours');

            $tanggalMasuk = $dateObj->format('Y-m-d H:i:s');
            $tanggalDeadline = $deadline->format('Y-m-d H:i:s');
            $totalBiaya = round($weight * $rate, 2);

            try {
                $pdo->beginTransaction();

                if ($action === 'add_order') {
                    do {
                        $code = 'LD-' . $dateObj->format('Ymd')
                            . '-' . strtoupper(bin2hex(random_bytes(3)));

                        $check = $pdo->prepare(
                            'SELECT id FROM transaksi
                             WHERE kode_transaksi = ? LIMIT 1'
                        );
                        $check->execute([$code]);
                    } while ($check->fetch());

                    $stmt = $pdo->prepare("
                        INSERT INTO transaksi (
                            kode_transaksi,
                            pelanggan_id,
                            nama_pelanggan,
                            no_wa,
                            paket,
                            berat,
                            tarif_per_kg,
                            tanggal_masuk,
                            deadline,
                            status,
                            total_biaya,
                            pembayaran,
                            admin_id
                        ) VALUES (
                            ?, NULL, ?, ?, ?, ?, ?, ?, ?,
                            'waiting', ?, 'belum_lunas', ?
                        )
                    ");

                    $stmt->execute([
                        $code,
                        $customer,
                        $phone,
                        $package,
                        $weight,
                        $rate,
                        $tanggalMasuk,
                        $tanggalDeadline,
                        $totalBiaya,
                        $_SESSION['admin_id'] ?? null
                    ]);

                    $transaksiId = (int)$pdo->lastInsertId();

                    // Notifikasi bersifat tambahan; transaksi tetap disimpan
                    // jika tabel notifikasi belum tersedia.
                    try {
                        $notif = $pdo->prepare("
                            INSERT INTO notifikasi
                                (transaksi_id, tipe, pesan, dibaca, created_at)
                            VALUES (?, 'new', ?, 0, NOW())
                        ");
                        $notif->execute([
                            $transaksiId,
                            'Order baru dibuat untuk ' . $customer
                        ]);
                    } catch (PDOException $notifError) {
                        error_log($notifError->getMessage());
                    }

                    $pdo->commit();
                    flash('Transaksi ' . $code . ' berhasil ditambahkan.');
                } else {
                    $pembayaran = $paid ? 'lunas' : 'belum_lunas';

                    $stmt = $pdo->prepare("
                        UPDATE transaksi
                        SET nama_pelanggan = ?,
                            no_wa = ?,
                            paket = ?,
                            berat = ?,
                            tarif_per_kg = ?,
                            tanggal_masuk = ?,
                            deadline = ?,
                            status = ?,
                            total_biaya = ?,
                            pembayaran = ?
                        WHERE kode_transaksi = ?
                    ");

                    $stmt->execute([
                        $customer,
                        $phone,
                        $package,
                        $weight,
                        $rate,
                        $tanggalMasuk,
                        $tanggalDeadline,
                        $status,
                        $totalBiaya,
                        $pembayaran,
                        $code
                    ]);

                    if (
                        $status === 'ready' &&
                        $oldOrder['status'] !== 'ready'
                    ) {
                        try {
                            $notif = $pdo->prepare("
                                INSERT INTO notifikasi
                                    (transaksi_id, tipe, pesan, dibaca, created_at)
                                VALUES (?, 'ready', ?, 0, NOW())
                            ");
                            $notif->execute([
                                $oldOrder['id'],
                                'Cucian siap diambil: ' . $customer
                            ]);
                        } catch (PDOException $notifError) {
                            error_log($notifError->getMessage());
                        }
                    }

                    $pdo->commit();
                    flash('Transaksi ' . $code . ' berhasil diperbarui.');
                }

                header('Location: antrean.php');
                exit;

            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                error_log($e->getMessage());
                $errors[] = 'Transaksi gagal disimpan: ' . $e->getMessage();
            }
        }

        $_SESSION['queue_modal_error'] = [
            'errors' => $errors,
            'action' => $action,
            'post' => $_POST
        ];

        header(
            'Location: antrean.php?modal=' .
            ($action === 'add_order' ? 'add' : 'edit') .
            '&code=' . urlencode($code)
        );
        exit;
    }
}

/* =========================
   AMBIL DATA DATABASE
========================= */

$stmt = $pdo->query("
    SELECT *
    FROM transaksi
    ORDER BY tanggal_masuk DESC, id DESC
");

$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

$query = trim($_GET['q'] ?? '');
$statusFilter = $_GET['status'] ?? 'all';

if (
    $statusFilter !== 'all' &&
    !in_array($statusFilter, $allowedStatuses, true)
) {
    $statusFilter = 'all';
}

$filtered = array_values(array_filter(
    $orders,
    function ($order) use ($query, $statusFilter) {
        $matchQuery = $query === ''
            || stripos($order['kode_transaksi'] ?? '', $query) !== false
            || stripos($order['nama_pelanggan'] ?? '', $query) !== false
            || stripos($order['no_wa'] ?? '', $query) !== false;

        $matchStatus = $statusFilter === 'all'
            || ($order['status'] ?? '') === $statusFilter;

        return $matchQuery && $matchStatus;
    }
));

$perPage = 8;
$totalFiltered = count($filtered);
$totalPages = max(1, (int)ceil($totalFiltered / $perPage));
$page = max(1, min($totalPages, (int)($_GET['page'] ?? 1)));

$pageOrders = array_slice(
    $filtered,
    ($page - 1) * $perPage,
    $perPage
);

$totalOrders = count($orders);
$waitingCount = 0;
$processCount = 0;
$readyCount = 0;
$completedCount = 0;

foreach ($orders as $order) {
    if (($order['status'] ?? '') === 'waiting') {
        $waitingCount++;
    } elseif (in_array(
        $order['status'] ?? '',
        ['washing', 'drying', 'ironing'],
        true
    )) {
        $processCount++;
    } elseif (($order['status'] ?? '') === 'ready') {
        $readyCount++;
    } elseif (($order['status'] ?? '') === 'completed') {
        $completedCount++;
    }
}

$modalError = $_SESSION['queue_modal_error'] ?? null;
unset($_SESSION['queue_modal_error']);

$modal = $_GET['modal'] ?? '';
$editCode = $_GET['code'] ?? '';

$editOrder = null;

if ($modal === 'edit' && $editCode !== '') {
    foreach ($orders as $order) {
        if (($order['kode_transaksi'] ?? '') === $editCode) {
            $editOrder = $order;
            break;
        }
    }
}

$addPost = ($modalError['action'] ?? '') === 'add_order'
    ? ($modalError['post'] ?? [])
    : [];

$editPost = ($modalError['action'] ?? '') === 'edit_order'
    ? ($modalError['post'] ?? [])
    : [];

require __DIR__ . '/../includes/header.php';
?>

<style>
.queue-page {
    padding: 24px;
    color: #172033;
}
.queue-heading {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 16px;
    flex-wrap: wrap;
    margin-bottom: 24px;
}
.queue-heading h1 {
    margin: 0 0 6px;
    font-size: 28px;
}
.queue-muted {
    color: #778197;
    font-size: 14px;
}
.queue-button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 11px 16px;
    border: 0;
    border-radius: 10px;
    background: #5b55e7;
    color: white;
    text-decoration: none;
    cursor: pointer;
    font-size: 14px;
    font-weight: 600;
}
.queue-button:hover {
    opacity: .9;
}
.queue-button.secondary {
    background: #eef0f7;
    color: #30394d;
}
.queue-button.small {
    padding: 7px 10px;
    font-size: 12px;
}
.queue-stats {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 16px;
    margin-bottom: 24px;
}
.queue-stat {
    background: #fff;
    padding: 20px;
    border-radius: 15px;
    border: 1px solid #e9ecf4;
    box-shadow: 0 5px 18px rgba(28, 38, 70, .04);
}
.queue-stat span {
    display: block;
    color: #778197;
    font-size: 13px;
    margin-bottom: 10px;
}
.queue-stat strong {
    font-size: 27px;
}
.queue-panel {
    background: white;
    border: 1px solid #e9ecf4;
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 5px 18px rgba(28, 38, 70, .04);
}
.queue-panel-head {
    padding: 20px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 15px;
    flex-wrap: wrap;
    border-bottom: 1px solid #edf0f6;
}
.queue-panel-head h2 {
    margin: 0;
    font-size: 18px;
}
.queue-filters {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
}
.queue-filters input,
.queue-filters select,
.queue-form input,
.queue-form select {
    padding: 11px 12px;
    border: 1px solid #dfe3ed;
    border-radius: 9px;
    outline: none;
    background: white;
    width: 100%;
    box-sizing: border-box;
}
.queue-filters input {
    width: 220px;
}
.queue-filters select {
    width: 175px;
}
.queue-table-wrap {
    width: 100%;
    overflow-x: auto;
}
.queue-table {
    width: 100%;
    border-collapse: collapse;
    min-width: 950px;
}
.queue-table th {
    background: #f8f9fc;
    color: #778197;
    text-align: left;
    padding: 14px 16px;
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: .04em;
}
.queue-table td {
    padding: 16px;
    border-top: 1px solid #edf0f6;
    font-size: 13px;
    vertical-align: middle;
}
.queue-code {
    font-weight: 700;
    color: #5049d8;
}
.queue-customer {
    font-weight: 600;
    margin-bottom: 4px;
}
.queue-badge {
    display: inline-block;
    padding: 6px 9px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 700;
    white-space: nowrap;
}
.queue-badge.waiting { background: #fff1cf; color: #966400; }
.queue-badge.washing,
.queue-badge.drying,
.queue-badge.ironing { background: #e4efff; color: #245fc1; }
.queue-badge.ready { background: #d9f8e9; color: #137b4b; }
.queue-badge.completed { background: #e6e8ee; color: #4e5667; }
.queue-badge.cancelled { background: #ffe3e5; color: #b42332; }
.queue-paid { color: #16834e; font-weight: 700; }
.queue-unpaid { color: #bd6b13; font-weight: 700; }
.queue-empty {
    text-align: center;
    padding: 45px 20px !important;
    color: #778197;
}
.queue-pagination {
    padding: 18px 20px;
    border-top: 1px solid #edf0f6;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
    font-size: 13px;
    color: #778197;
}
.queue-pages {
    display: flex;
    gap: 6px;
}
.queue-pages a,
.queue-pages span {
    padding: 8px 11px;
    border-radius: 8px;
    background: #f1f2f8;
    color: #30394d;
    text-decoration: none;
}
.queue-pages .active {
    background: #5b55e7;
    color: white;
}
.queue-alert {
    padding: 14px 16px;
    margin-bottom: 18px;
    border-radius: 10px;
    background: #fff0f0;
    color: #ad2525;
    font-size: 13px;
}
.queue-success {
    padding: 14px 16px;
    margin-bottom: 18px;
    border-radius: 10px;
    background: #e1f8ec;
    color: #147448;
}
.queue-modal-backdrop {
    position: fixed;
    inset: 0;
    z-index: 9999;
    background: rgba(16, 24, 40, .58);
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 18px;
    overflow-y: auto;
}
.queue-modal {
    width: 100%;
    max-width: 560px;
    background: white;
    border-radius: 18px;
    box-shadow: 0 20px 80px rgba(0, 0, 0, .2);
    overflow: hidden;
    margin: auto;
}
.queue-modal-head {
    padding: 20px 24px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 1px solid #edf0f6;
}
.queue-modal-head h2 {
    margin: 0;
    font-size: 20px;
}
.queue-close {
    font-size: 25px;
    text-decoration: none;
    color: #778197;
}
.queue-form {
    padding: 24px;
}
.queue-form-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
}
.queue-field {
    margin-bottom: 16px;
}
.queue-field label {
    display: block;
    margin-bottom: 7px;
    font-size: 13px;
    font-weight: 600;
}
.queue-field.full {
    grid-column: 1 / -1;
}
.queue-form-actions {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    margin-top: 10px;
}
.queue-checkbox {
    display: flex;
    align-items: center;
    gap: 9px;
    font-size: 14px;
}
.queue-checkbox input {
    width: auto;
}
@media(max-width: 850px) {
    .queue-stats { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .queue-page { padding: 15px; }
}
@media(max-width: 520px) {
    .queue-stats { grid-template-columns: 1fr 1fr; gap: 10px; }
    .queue-stat { padding: 14px; }
    .queue-stat strong { font-size: 22px; }
    .queue-form-grid { grid-template-columns: 1fr; }
    .queue-field.full { grid-column: auto; }
    .queue-filters input,
    .queue-filters select { width: 100%; }
    .queue-filters { width: 100%; }
}
</style>

<div class="queue-page">
    <div class="queue-heading">
        <div>
            <h1>Antrean &amp; Order</h1>
            <div class="queue-muted">Kelola semua transaksi laundry CuciKu.</div>
        </div>
        <a class="queue-button" href="antrean.php?modal=add">
            + Tambah Transaksi
        </a>
    </div>

    <?php if (function_exists('showFlash')): ?>
        <?php showFlash(); ?>
    <?php endif; ?>

    <?php if ($modalError && !empty($modalError['errors'])): ?>
        <div class="queue-alert">
            <?php foreach ($modalError['errors'] as $error): ?>
                <div><?= antreanE($error) ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div class="queue-stats">
        <div class="queue-stat">
            <span>Total Transaksi</span>
            <strong><?= $totalOrders ?></strong>
        </div>
        <div class="queue-stat">
            <span>Menunggu</span>
            <strong><?= $waitingCount ?></strong>
        </div>
        <div class="queue-stat">
            <span>Sedang Diproses</span>
            <strong><?= $processCount ?></strong>
        </div>
        <div class="queue-stat">
            <span>Siap Diambil</span>
            <strong><?= $readyCount ?></strong>
        </div>
    </div>

    <section class="queue-panel">
        <div class="queue-panel-head">
            <h2>Daftar Antrean</h2>

            <form class="queue-filters" method="GET" action="antrean.php">
                <input
                    type="search"
                    name="q"
                    placeholder="Cari nama, kode, nomor WA..."
                    value="<?= antreanE($query) ?>"
                >

                <select name="status">
                    <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>
                        Semua Status
                    </option>
                    <?php foreach ($statusLabels as $key => $label): ?>
                        <option
                            value="<?= antreanE($key) ?>"
                            <?= $statusFilter === $key ? 'selected' : '' ?>
                        >
                            <?= antreanE($label) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <button class="queue-button" type="submit">Cari</button>
                <a class="queue-button secondary" href="antrean.php">Reset</a>
            </form>
        </div>

        <div class="queue-table-wrap">
            <table class="queue-table">
                <thead>
                    <tr>
                        <th>Kode / Pelanggan</th>
                        <th>Paket</th>
                        <th>Berat</th>
                        <th>Tanggal Masuk</th>
                        <th>Deadline</th>
                        <th>Total</th>
                        <th>Status</th>
                        <th>Pembayaran</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$pageOrders): ?>
                        <tr>
                            <td class="queue-empty" colspan="9">
                                Belum ada transaksi yang sesuai.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($pageOrders as $order): ?>
                            <tr>
                                <td>
                                    <div class="queue-code">
                                        <?= antreanE($order['kode_transaksi'] ?? '') ?>
                                    </div>
                                    <div class="queue-customer">
                                        <?= antreanE($order['nama_pelanggan'] ?? '') ?>
                                    </div>
                                    <div class="queue-muted">
                                        <?= antreanE($order['no_wa'] ?? '-') ?>
                                    </div>
                                </td>
                                <td><?= antreanE($order['paket'] ?? '-') ?></td>
                                <td><?= number_format((float)($order['berat'] ?? 0), 2, ',', '.') ?> kg</td>
                                <td><?= antreanTanggal($order['tanggal_masuk'] ?? null) ?></td>
                                <td><?= antreanTanggal($order['deadline'] ?? null) ?></td>
                                <td><strong><?= antreanRupiah($order['total_biaya'] ?? 0) ?></strong></td>
                                <td>
                                    <span class="queue-badge <?= antreanStatusClass($order['status'] ?? '') ?>">
                                        <?= antreanE($statusLabels[$order['status'] ?? ''] ?? ($order['status'] ?? '-')) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if (($order['pembayaran'] ?? '') === 'lunas'): ?>
                                        <span class="queue-paid">Lunas</span>
                                    <?php else: ?>
                                        <span class="queue-unpaid">Belum Lunas</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a
                                        class="queue-button small"
                                        href="antrean.php?modal=edit&amp;code=<?= urlencode($order['kode_transaksi'] ?? '') ?>"
                                    >
                                        Edit
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="queue-pagination">
            <span>
                Menampilkan <?= count($pageOrders) ?> dari <?= $totalFiltered ?> transaksi
            </span>

            <div class="queue-pages">
                <?php if ($page > 1): ?>
                    <a href="?q=<?= urlencode($query) ?>&amp;status=<?= urlencode($statusFilter) ?>&amp;page=<?= $page - 1 ?>">
                        Sebelumnya
                    </a>
                <?php endif; ?>

                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <?php if ($i === $page): ?>
                        <span class="active"><?= $i ?></span>
                    <?php else: ?>
                        <a href="?q=<?= urlencode($query) ?>&amp;status=<?= urlencode($statusFilter) ?>&amp;page=<?= $i ?>">
                            <?= $i ?>
                        </a>
                    <?php endif; ?>
                <?php endfor; ?>

                <?php if ($page < $totalPages): ?>
                    <a href="?q=<?= urlencode($query) ?>&amp;status=<?= urlencode($statusFilter) ?>&amp;page=<?= $page + 1 ?>">
                        Berikutnya
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </section>
</div>

<?php
$isAddModal = $modal === 'add';
$isEditModal = $modal === 'edit' && $editOrder !== null;
$showModal = $isAddModal || $isEditModal;

if ($showModal):
    $formOrder = $isEditModal ? $editOrder : [];

    if ($isAddModal && $addPost) {
        $formOrder = [
            'nama_pelanggan' => $addPost['customer'] ?? '',
            'no_wa' => $addPost['phone'] ?? '',
            'paket' => $addPost['package'] ?? 'Reguler',
            'berat' => $addPost['weight'] ?? '',
            'tanggal_masuk' => str_replace('T', ' ', $addPost['dateIn'] ?? ''),
            'status' => 'waiting',
            'pembayaran' => 'belum_lunas'
        ];
    } elseif ($isEditModal && $editPost) {
        $formOrder = array_merge($formOrder, [
            'nama_pelanggan' => $editPost['customer'] ?? ($formOrder['nama_pelanggan'] ?? ''),
            'no_wa' => $editPost['phone'] ?? ($formOrder['no_wa'] ?? ''),
            'paket' => $editPost['package'] ?? ($formOrder['paket'] ?? 'Reguler'),
            'berat' => $editPost['weight'] ?? ($formOrder['berat'] ?? ''),
            'tanggal_masuk' => str_replace('T', ' ', $editPost['dateIn'] ?? ($formOrder['tanggal_masuk'] ?? '')),
            'status' => $editPost['status'] ?? ($formOrder['status'] ?? 'waiting'),
            'pembayaran' => isset($editPost['paid']) ? 'lunas' : 'belum_lunas'
        ]);
    }

    $dateValue = '';
    if (!empty($formOrder['tanggal_masuk'])) {
        $timestamp = strtotime($formOrder['tanggal_masuk']);
        if ($timestamp !== false) {
            $dateValue = date('Y-m-d\TH:i', $timestamp);
        }
    }
?>
<div class="queue-modal-backdrop" id="queueModal">
    <div class="queue-modal">
        <div class="queue-modal-head">
            <h2><?= $isAddModal ? 'Tambah Transaksi' : 'Edit Transaksi' ?></h2>
            <a class="queue-close" href="antrean.php" aria-label="Tutup">&times;</a>
        </div>

        <form class="queue-form" method="POST" action="antrean.php">
            <input
                type="hidden"
                name="action"
                value="<?= $isAddModal ? 'add_order' : 'edit_order' ?>"
            >

            <?php if ($isEditModal): ?>
                <input
                    type="hidden"
                    name="code"
                    value="<?= antreanE($formOrder['kode_transaksi'] ?? '') ?>"
                >
                <div class="queue-field">
                    <label>Kode Transaksi</label>
                    <input
                        type="text"
                        value="<?= antreanE($formOrder['kode_transaksi'] ?? '') ?>"
                        disabled
                    >
                </div>
            <?php endif; ?>

            <div class="queue-form-grid">
                <div class="queue-field full">
                    <label>Nama Pelanggan *</label>
                    <input
                        type="text"
                        name="customer"
                        required
                        maxlength="100"
                        value="<?= antreanE($formOrder['nama_pelanggan'] ?? '') ?>"
                        placeholder="Masukkan nama pelanggan"
                    >
                </div>

                <div class="queue-field full">
                    <label>Nomor WhatsApp</label>
                    <input
                        type="text"
                        name="phone"
                        maxlength="20"
                        value="<?= antreanE($formOrder['no_wa'] ?? '') ?>"
                        placeholder="Contoh: 081234567890"
                    >
                </div>

                <div class="queue-field">
                    <label>Paket Layanan *</label>
                    <select name="package" required>
                        <option
                            value="Reguler"
                            <?= ($formOrder['paket'] ?? 'Reguler') === 'Reguler' ? 'selected' : '' ?>
                        >
                            Reguler - Rp7.000/kg
                        </option>
                        <option
                            value="Express"
                            <?= ($formOrder['paket'] ?? '') === 'Express' ? 'selected' : '' ?>
                        >
                            Express - Rp14.000/kg
                        </option>
                    </select>
                </div>

                <div class="queue-field">
                    <label>Berat (kg) *</label>
                    <input
                        type="number"
                        name="weight"
                        min="0.1"
                        max="200"
                        step="0.1"
                        required
                        value="<?= antreanE($formOrder['berat'] ?? '') ?>"
                        placeholder="Contoh: 3.5"
                    >
                </div>

                <div class="queue-field full">
                    <label>Tanggal Masuk *</label>
                    <input
                        type="datetime-local"
                        name="dateIn"
                        required
                        value="<?= antreanE($dateValue) ?>"
                    >
                </div>

                <?php if ($isEditModal): ?>
                    <div class="queue-field">
                        <label>Status Transaksi</label>
                        <select name="status" required>
                            <?php foreach ($statusLabels as $key => $label): ?>
                                <option
                                    value="<?= antreanE($key) ?>"
                                    <?= ($formOrder['status'] ?? '') === $key ? 'selected' : '' ?>
                                >
                                    <?= antreanE($label) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="queue-field">
                        <label>Pembayaran</label>
                        <label class="queue-checkbox">
                            <input
                                type="checkbox"
                                name="paid"
                                value="1"
                                <?= ($formOrder['pembayaran'] ?? '') === 'lunas' ? 'checked' : '' ?>
                            >
                            Sudah lunas
                        </label>
                    </div>
                <?php endif; ?>
            </div>

            <div class="queue-form-actions">
                <a class="queue-button secondary" href="antrean.php">Batal</a>
                <button class="queue-button" type="submit">
                    <?= $isAddModal ? 'Simpan Transaksi' : 'Simpan Perubahan' ?>
                </button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<script>
document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') {
        window.location.href = 'antrean.php';
    }
});
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>