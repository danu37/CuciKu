<?php
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

$statusIcons = [
    'waiting'   => 'bi-hourglass-split',
    'washing'   => 'bi-water',
    'drying'    => 'bi-wind',
    'ironing'   => 'bi-fire',
    'ready'     => 'bi-box-seam-fill',
    'completed' => 'bi-check-circle-fill',
    'cancelled' => 'bi-x-circle-fill'
];

$errors = [];

function antreanRupiah($nilai)
{
    return rupiah($nilai);
}

function antreanTanggal($tanggal)
{
    return formatTanggal($tanggal);
}

function antreanStatusClass($status)
{
    return statusClass($status);
}

function antreanE($value)
{
    return e($value);
}

function antreanUrl(array $changes = []): string
{
    global $query, $statusFilter, $pembayaranFilter, $sort, $order, $page;

    $params = [];
    if (!empty($query)) {
        $params['q'] = $query;
    }
    if (!empty($statusFilter) && $statusFilter !== 'all') {
        $params['status'] = $statusFilter;
    }
    if (!empty($pembayaranFilter) && $pembayaranFilter !== 'all') {
        $params['pembayaran'] = $pembayaranFilter;
    }
    if (!empty($sort)) {
        $params['sort'] = $sort;
        $params['order'] = $order ?: 'desc';
    }
    if (!empty($page) && (int)$page > 1) {
        $params['page'] = (int)$page;
    }

    foreach ($changes as $k => $v) {
        if ($v === null || $v === '' || $v === 'all' || ($k === 'page' && (int)$v <= 1)) {
            unset($params[$k]);
        } else {
            $params[$k] = $v;
        }
    }

    return 'antrean.php' . (!empty($params) ? '?' . http_build_query($params) : '');
}

/* =========================
   TAMBAH DAN EDIT TRANSAKSI
========================= */

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $action = $_POST['action'] ?? '';

    // Aksi Cepat 1: Ubah Status Pembayaran (Lunas <-> Belum Lunas)
    if ($action === 'toggle_payment') {
        $code = trim($_POST['code'] ?? '');
        $targetPayment = $_POST['target_payment'] ?? '';

        if ($code !== '') {
            $stmt = $pdo->prepare('SELECT id, kode_transaksi, pembayaran FROM transaksi WHERE kode_transaksi = ? LIMIT 1');
            $stmt->execute([$code]);
            $transaksi = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($transaksi) {
                $newPayment = in_array($targetPayment, ['lunas', 'belum_lunas'], true)
                    ? $targetPayment
                    : (($transaksi['pembayaran'] === 'lunas') ? 'belum_lunas' : 'lunas');

                $update = $pdo->prepare('UPDATE transaksi SET pembayaran = ? WHERE id = ?');
                $update->execute([$newPayment, $transaksi['id']]);

                $label = ($newPayment === 'lunas') ? 'Lunas' : 'Belum Lunas';
                flash("Status pembayaran transaksi {$code} berhasil diubah menjadi {$label}.", 'success', 'Pembayaran Diperbarui');
            } else {
                flash('Transaksi tidak ditemukan.', 'danger', 'Gagal Memperbarui');
            }
        }

        $redirectQuery = $_POST['redirect_query'] ?? '';
        $redirectUrl = 'antrean.php' . ($redirectQuery !== '' ? '?' . $redirectQuery : '');
        header('Location: ' . $redirectUrl);
        exit;
    }

    // Aksi Cepat 2: Ubah Status Pesanan (Otomatis ke Siap Diambil saat selesai)
    if ($action === 'toggle_status') {
        $code = trim($_POST['code'] ?? '');
        $targetStatus = $_POST['target_status'] ?? '';

        if ($code !== '') {
            $stmt = $pdo->prepare('SELECT id, kode_transaksi, status, nama_pelanggan FROM transaksi WHERE kode_transaksi = ? LIMIT 1');
            $stmt->execute([$code]);
            $transaksi = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($transaksi) {
                // Tentukan alur status:
                // Jika orderan aktif diselesaikan, otomatis jadi 'ready' (Siap Diambil).
                if (in_array($targetStatus, $allowedStatuses, true)) {
                    // Jika order masih aktif (belum ready/completed) dan diminta selesai, arahkan otomatis ke 'ready'
                    if (!in_array($transaksi['status'], ['ready', 'completed'], true) && $targetStatus === 'completed') {
                        $newStatus = 'ready';
                    } else {
                        $newStatus = $targetStatus;
                    }
                } else {
                    if ($transaksi['status'] === 'ready') {
                        $newStatus = 'completed';
                    } elseif ($transaksi['status'] === 'completed') {
                        $newStatus = 'waiting';
                    } else {
                        $newStatus = 'ready';
                    }
                }

                $update = $pdo->prepare('UPDATE transaksi SET status = ? WHERE id = ?');
                $update->execute([$newStatus, $transaksi['id']]);

                // Buat notifikasi otomatis ketika status menjadi 'ready' (Siap Diambil)
                if ($newStatus === 'ready' && $transaksi['status'] !== 'ready') {
                    try {
                        $notif = $pdo->prepare("
                            INSERT INTO notifikasi
                                (transaksi_id, tipe, pesan, dibaca, created_at)
                            VALUES (?, 'ready', ?, 0, NOW())
                        ");
                        $notif->execute([
                            $transaksi['id'],
                            'Cucian siap diambil: ' . $transaksi['nama_pelanggan']
                        ]);
                    } catch (PDOException $notifError) {
                        error_log($notifError->getMessage());
                    }
                }

                $statusText = $statusLabels[$newStatus] ?? $newStatus;
                if ($newStatus === 'ready') {
                    flash("Orderan {$code} telah selesai diproses dan otomatis diubah menjadi Siap Diambil.", 'success', 'Status Berhasil Diubah');
                } elseif ($newStatus === 'completed') {
                    flash("Status transaksi {$code} berhasil diselesaikan.", 'success', 'Transaksi Selesai');
                } elseif ($newStatus === 'cancelled') {
                    flash("Transaksi {$code} berhasil dibatalkan.", 'warning', 'Pesanan Dibatalkan');
                } else {
                    flash("Status transaksi {$code} berhasil diubah menjadi {$statusText}.", 'success', 'Status Berhasil Diubah');
                }
            } else {
                flash('Transaksi tidak ditemukan.', 'danger', 'Gagal Mengubah Status');
            }
        }

        $redirectQuery = $_POST['redirect_query'] ?? '';
        $redirectUrl = 'antrean.php' . ($redirectQuery !== '' ? '?' . $redirectQuery : '');
        header('Location: ' . $redirectUrl);
        exit;
    }

    // Aksi Cepat 3: Hapus Data Transaksi
    if ($action === 'delete_order') {
        $code = trim($_POST['code'] ?? '');

        if ($code !== '') {
            $stmt = $pdo->prepare('SELECT id, kode_transaksi, nama_pelanggan FROM transaksi WHERE kode_transaksi = ? LIMIT 1');
            $stmt->execute([$code]);
            $transaksi = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($transaksi) {
                try {
                    $pdo->beginTransaction();

                    // Hapus notifikasi terkait jika tabel notifikasi ada
                    try {
                        $delNotif = $pdo->prepare('DELETE FROM notifikasi WHERE transaksi_id = ?');
                        $delNotif->execute([$transaksi['id']]);
                    } catch (PDOException $notifErr) {
                        error_log($notifErr->getMessage());
                    }

                    $delStmt = $pdo->prepare('DELETE FROM transaksi WHERE id = ?');
                    $delStmt->execute([$transaksi['id']]);

                    $pdo->commit();
                    flash("Transaksi {$code} atas nama {$transaksi['nama_pelanggan']} berhasil dihapus.", 'success', 'Transaksi Dihapus');
                } catch (Throwable $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    error_log($e->getMessage());
                    flash('Gagal menghapus transaksi: ' . $e->getMessage(), 'danger', 'Gagal Menghapus Transaksi');
                }
            } else {
                flash('Transaksi tidak ditemukan.', 'danger', 'Gagal Menghapus Transaksi');
            }
        }

        $redirectQuery = $_POST['redirect_query'] ?? '';
        $redirectUrl = 'antrean.php' . ($redirectQuery !== '' ? '?' . $redirectQuery : '');
        header('Location: ' . $redirectUrl);
        exit;
    }

    if (in_array($action, ['add_order', 'edit_order'], true)) {
        $code = trim($_POST['code'] ?? '');
        $customer = trim($_POST['customer'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $pelangganId = !empty($_POST['pelanggan_id']) ? (int)$_POST['pelanggan_id'] : null;
        $package = $_POST['package'] ?? 'Reguler';
        $weight = (float)($_POST['weight'] ?? 0);
        $dateRaw = str_replace('T', ' ', $_POST['dateIn'] ?? '');
        $dateObj = DateTime::createFromFormat('Y-m-d H:i', $dateRaw);
        $status = $_POST['status'] ?? 'waiting';
        $paid = isset($_POST['paid']);

        if ($action === 'add_order') {
            if (!$pelangganId) {
                $errors[] = 'Silakan pilih pelanggan yang terdaftar.';
            } else {
                $stmtCustomer = $pdo->prepare('
                    SELECT id_pelanggan, nama, no_hp
                    FROM pelanggan
                    WHERE id_pelanggan = ?
                    LIMIT 1
                ');
                $stmtCustomer->execute([$pelangganId]);
                $selectedCustomer = $stmtCustomer->fetch(PDO::FETCH_ASSOC);

                if (!$selectedCustomer) {
                    $errors[] = 'Pelanggan yang dipilih tidak terdaftar di sistem.';
                } else {
                    $customer = $selectedCustomer['nama'];
                    $phone = $selectedCustomer['no_hp'] ?? '';
                }
            }
        } else {
            if ($customer === '') {
                $errors[] = 'Nama pelanggan wajib diisi.';
            }
        }

        if (!in_array($package, ['Reguler', 'Express'], true)) {
            $errors[] = 'Paket layanan tidak valid.';
        }

        if ($weight <= 0 || $weight > 25) {
            $errors[] = 'Berat harus lebih dari 0 dan maksimal 25 kg.';
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
                            ?, ?, ?, ?, ?, ?, ?, ?, ?,
                            'waiting', ?, 'belum_lunas', ?
                        )
                    ");

                    $stmt->execute([
                        $code,
                        $pelangganId,
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
                    flash('Transaksi ' . $code . ' berhasil ditambahkan.', 'success', 'Transaksi Ditambahkan');
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
                    if ($status === 'cancelled' && ($oldOrder['status'] ?? '') !== 'cancelled') {
                        flash("Transaksi {$code} berhasil dibatalkan.", 'warning', 'Pesanan Dibatalkan');
                    } elseif ($status === 'ready' && ($oldOrder['status'] ?? '') !== 'ready') {
                        flash("Orderan {$code} telah siap diambil oleh pelanggan.", 'success', 'Siap Diambil');
                    } else {
                        flash('Transaksi ' . $code . ' berhasil diperbarui.', 'success', 'Transaksi Diperbarui');
                    }
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
        flash($errors, 'danger', $action === 'add_order' ? 'Gagal Menambah Transaksi' : 'Gagal Memperbarui Transaksi');

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

$stmtCustomers = $pdo->query("
    SELECT id_pelanggan, nama, no_hp, email
    FROM pelanggan
    ORDER BY nama ASC
");

$registeredCustomers = $stmtCustomers->fetchAll(PDO::FETCH_ASSOC);

$query = trim($_GET['q'] ?? '');
$statusFilter = $_GET['status'] ?? 'all';

if (
    $statusFilter !== 'all' &&
    !in_array($statusFilter, $allowedStatuses, true)
) {
    $statusFilter = 'all';
}

$pembayaranFilter = trim($_GET['pembayaran'] ?? ($_GET['bayar'] ?? 'all'));
if ($pembayaranFilter === 'belum-lunas' || $pembayaranFilter === 'unpaid') {
    $pembayaranFilter = 'belum_lunas';
}
if (!in_array($pembayaranFilter, ['all', 'lunas', 'belum_lunas'], true)) {
    $pembayaranFilter = 'all';
}

$allowedSortColumns = [
    'tanggal_masuk', 'deadline', 'total_biaya', 'berat',
    'nama_pelanggan', 'status', 'pembayaran', 'kode_transaksi'
];
$sort = trim($_GET['sort'] ?? '');
if (!in_array($sort, $allowedSortColumns, true)) {
    $sort = '';
}
$order = strtolower(trim($_GET['order'] ?? 'desc'));
if ($order !== 'asc') {
    $order = 'desc';
}

$filtered = array_values(array_filter(
    $orders,
    function ($order) use ($query, $statusFilter, $pembayaranFilter) {
        $matchQuery = $query === ''
            || stripos($order['kode_transaksi'] ?? '', $query) !== false
            || stripos($order['nama_pelanggan'] ?? '', $query) !== false
            || stripos($order['no_wa'] ?? '', $query) !== false;

        $matchStatus = $statusFilter === 'all'
            || ($order['status'] ?? '') === $statusFilter;

        $matchPembayaran = $pembayaranFilter === 'all'
            || ($order['pembayaran'] ?? '') === $pembayaranFilter;

        return $matchQuery && $matchStatus && $matchPembayaran;
    }
));

if ($sort !== '') {
    usort($filtered, function ($a, $b) use ($sort, $order) {
        $valA = $a[$sort] ?? '';
        $valB = $b[$sort] ?? '';

        if (in_array($sort, ['total_biaya', 'berat', 'id', 'tarif_per_kg'], true)) {
            $cmp = ((float)$valA <=> (float)$valB);
        } elseif (in_array($sort, ['tanggal_masuk', 'deadline', 'created_at'], true)) {
            $cmp = strtotime((string)$valA) <=> strtotime((string)$valB);
        } else {
            $cmp = strcasecmp((string)$valA, (string)$valB);
        }

        return $order === 'asc' ? $cmp : -$cmp;
    });
}

$perPage = 10;
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
$lunasCount = 0;
$belumLunasCount = 0;

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

    if (($order['pembayaran'] ?? '') === 'lunas') {
        $lunasCount++;
    } else {
        $belumLunasCount++;
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

$customerIdParam = isset($_GET['customer_id']) ? (int)$_GET['customer_id'] : 0;
$addPost = ($modalError['action'] ?? '') === 'add_order'
    ? ($modalError['post'] ?? [])
    : [];

if (empty($addPost['pelanggan_id']) && $customerIdParam > 0) {
    $addPost['pelanggan_id'] = $customerIdParam;
}

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
    background: var(--brand, #267b72);
    color: white;
    text-decoration: none;
    cursor: pointer;
    font-size: 14px;
    font-weight: 600;
    transition: background-color .15s ease, transform .1s ease;
}
.queue-button:hover {
    background: var(--brand-dark, #1f665f);
    color: white;
}
.queue-button.secondary {
    background: #edf1f6;
    color: #30394d;
}
.queue-button.secondary:hover {
    background: #e2e7ef;
    color: #172033;
}
.queue-button.small {
    padding: 7px 11px;
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
    padding: 18px 20px 16px;
    border-radius: 14px;
    border: 1px solid #e9ecf4;
    box-shadow: 0 2px 6px rgba(28, 38, 70, .03);
    display: flex;
    flex-direction: column;
    justify-content: space-between;
}
.queue-stat-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 8px;
}
.queue-stat-top span {
    color: #778197;
    font-size: 13px;
    font-weight: 600;
}
.queue-stat-icon {
    width: 36px;
    height: 36px;
    border-radius: 10px;
    display: grid;
    place-items: center;
    font-size: 17px;
}
.icon-blue { color: #4a79c8; background: #edf3ff; }
.icon-orange { color: #c88a35; background: #fff3df; }
.icon-purple { color: #8972c8; background: #f2eeff; }
.icon-green { color: #298672; background: #e7f6f0; }
.queue-stat strong {
    font-size: 28px;
    font-weight: 800;
    color: #172638;
    line-height: 1.15;
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
    align-items: center;
    flex-wrap: wrap;
    gap: 8px;
}
.queue-search-group {
    position: relative;
    display: inline-flex;
    align-items: center;
    background: #ffffff;
    border: 1px solid #dfe3ed;
    border-radius: 10px;
    padding: 3px 4px 3px 12px;
    transition: border-color .15s ease, box-shadow .15s ease;
    width: 290px;
    max-width: 100%;
    box-sizing: border-box;
}
.queue-search-group:focus-within {
    border-color: var(--brand, #267b72);
    box-shadow: 0 0 0 3px rgba(38, 123, 114, .12);
}
.queue-search-group .queue-search-icon {
    color: #8c97ab;
    font-size: 14px;
    margin-right: 6px;
    flex-shrink: 0;
}
.queue-search-group .queue-search-input {
    border: none !important;
    outline: none !important;
    padding: 6px 4px !important;
    font-size: 13px !important;
    color: #172033;
    background: transparent !important;
    width: 100% !important;
    box-shadow: none !important;
}
.queue-search-group .queue-search-input::placeholder {
    color: #9aa3b5;
}
.queue-search-group .queue-search-submit {
    border: none;
    border-radius: 7px;
    background: var(--brand, #267b72);
    color: #ffffff;
    padding: 6px 12px;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
    flex-shrink: 0;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    transition: background-color .15s ease, transform .1s ease;
    line-height: 1.2;
}
.queue-search-group .queue-search-submit:hover {
    background: var(--brand-dark, #1c615a);
}
.queue-search-group .queue-search-submit:active {
    transform: scale(0.97);
}
.queue-filters .queue-status-select {
    width: 165px;
    padding: 7px 12px;
    border: 1px solid #dfe3ed;
    border-radius: 10px;
    font-size: 13px;
    color: #172033;
    background: #ffffff;
    outline: none;
    height: 38px;
    box-sizing: border-box;
    transition: border-color .15s ease, box-shadow .15s ease;
}
.queue-filters .queue-status-select:focus {
    border-color: var(--brand, #267b72);
    box-shadow: 0 0 0 3px rgba(38, 123, 114, .12);
}
.queue-filters .queue-reset-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    height: 38px;
    padding: 0 12px;
    border: 1px solid #dfe3ed;
    border-radius: 10px;
    background: #f4f6fa;
    color: #5a6478;
    text-decoration: none;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
    transition: background-color .15s ease, color .15s ease;
    box-sizing: border-box;
}
.queue-filters .queue-reset-btn:hover {
    background: #e6ebf3;
    color: #172033;
}
.queue-filters .queue-payment-select {
    width: 175px;
}
.queue-subbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    flex-wrap: wrap;
    padding: 12px 20px;
    background: #fafbfd;
    border-bottom: 1px solid #edf0f6;
}
.queue-pay-segmented {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}
.queue-segmented-title {
    font-size: 12px;
    font-weight: 700;
    color: #637085;
    display: inline-flex;
    align-items: center;
}
.queue-segmented-group {
    display: inline-flex;
    background: #edf1f7;
    padding: 3px;
    border-radius: 10px;
    gap: 3px;
}
.queue-seg-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 5px 12px;
    border-radius: 8px;
    font-size: 12px;
    font-weight: 600;
    color: #556277;
    text-decoration: none;
    transition: all .15s ease;
    border: 1px solid transparent;
}
.queue-seg-btn:hover {
    color: #172033;
    background: rgba(255, 255, 255, 0.7);
}
.queue-seg-btn.active {
    background: #ffffff;
    color: var(--brand, #267b72);
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
    border-color: #e2e8f0;
}
.queue-seg-btn.is-lunas.active {
    color: #047857;
    background: #ffffff;
}
.queue-seg-btn.is-unpaid.active {
    color: #c2410c;
    background: #ffffff;
}
.queue-seg-btn .seg-badge {
    display: inline-block;
    padding: 1px 6px;
    font-size: 11px;
    font-weight: 700;
    border-radius: 12px;
    background: #e2e8f0;
    color: #475569;
}
.queue-seg-btn.active .seg-badge {
    background: var(--brand-light, #e7f5f2);
    color: var(--brand, #267b72);
}
.queue-seg-btn.is-lunas.active .seg-badge {
    background: #d1fae5;
    color: #047857;
}
.queue-seg-btn.is-unpaid.active .seg-badge {
    background: #ffedd5;
    color: #c2410c;
}

.queue-active-filters {
    display: flex;
    align-items: center;
    gap: 6px;
    flex-wrap: wrap;
}
.filter-chip {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 3px 9px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 600;
    background: #f1f5f9;
    color: #334155;
    border: 1px solid #e2e8f0;
}
.filter-chip a {
    color: #64748b;
    text-decoration: none;
    font-size: 13px;
    line-height: 1;
    display: inline-flex;
    align-items: center;
}
.filter-chip a:hover {
    color: #ef4444;
}
.filter-chip.chip-lunas {
    background: #ecfdf5;
    color: #047857;
    border-color: #a7f3d0;
}
.filter-chip.chip-unpaid {
    background: #fff7ed;
    color: #c2410c;
    border-color: #fed7aa;
}

.sort-header {
    color: inherit;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    transition: color .15s ease;
}
.sort-header:hover {
    color: var(--brand, #267b72);
}
.sort-header.active-sort {
    color: var(--brand, #267b72);
    font-weight: 700;
}
.sort-header i {
    font-size: 11px;
    opacity: 0.6;
}
.sort-header.active-sort i {
    opacity: 1;
}
.queue-form input,
.queue-form select {
    padding: 11px 12px;
    border: 1px solid #dfe3ed;
    border-radius: 9px;
    outline: none;
    background: white;
    width: 100%;
    box-sizing: border-box;
    transition: border-color .15s ease, box-shadow .15s ease;
}
.queue-form input:focus,
.queue-form select:focus {
    border-color: var(--brand, #267b72);
    box-shadow: 0 0 0 3px rgba(38, 123, 114, .12);
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
    color: var(--brand, #267b72);
}
.queue-customer {
    font-weight: 600;
    margin-bottom: 4px;
}
.queue-status-cell {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: 6px;
    min-width: 135px;
}
.queue-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 5px 12px;
    border-radius: 30px;
    font-size: 11.5px;
    font-weight: 700;
    line-height: 1.25;
    white-space: nowrap;
    border: 1px solid transparent;
    letter-spacing: .01em;
    box-shadow: 0 1px 2px rgba(0, 0, 0, .04);
}
.queue-badge i {
    font-size: 12px;
    line-height: 1;
}

/* Status Menunggu */
.queue-badge.status-waiting,
.queue-badge.waiting {
    background: #fffbeb;
    color: #b45309;
    border-color: #fde68a;
}
.queue-badge.status-waiting i,
.queue-badge.waiting i {
    color: #d97706;
}

/* Status Proses (Dicuci / Kering / Setrika) */
.queue-badge.status-process,
.queue-badge.washing {
    background: #eff6ff;
    color: #1d4ed8;
    border-color: #bfdbfe;
}
.queue-badge.status-process i,
.queue-badge.washing i {
    color: #2563eb;
}
.queue-badge.drying {
    background: #f0fdfa;
    color: #0f766e;
    border-color: #99f6e4;
}
.queue-badge.drying i {
    color: #14b8a6;
}
.queue-badge.ironing {
    background: #faf5ff;
    color: #7e22ce;
    border-color: #e9d5ff;
}
.queue-badge.ironing i {
    color: #9333ea;
}

/* Status Siap Diambil */
.queue-badge.status-ready,
.queue-badge.ready {
    background: #f0fdf4;
    color: #15803d;
    border-color: #bbf7d0;
}
.queue-badge.status-ready i,
.queue-badge.ready i {
    color: #16a34a;
}

/* Status Selesai */
.queue-badge.status-completed,
.queue-badge.completed {
    background: #ecfdf5;
    color: #047857;
    border-color: #a7f3d0;
}
.queue-badge.status-completed i,
.queue-badge.completed i {
    color: #059669;
}

/* Status Dibatalkan */
.queue-badge.status-cancelled,
.queue-badge.cancelled {
    background: #fef2f2;
    color: #b91c1c;
    border-color: #fecaca;
}
.queue-badge.status-cancelled i,
.queue-badge.cancelled i {
    color: #ef4444;
}

/* Tombol Cepat Aksi Status (Siap Diambil / Selesai / Ke Menunggu) */
.queue-status-btn {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 3.5px 10px;
    border-radius: 6px;
    font-size: 11px;
    font-weight: 600;
    cursor: pointer;
    text-decoration: none;
    line-height: 1.25;
    white-space: nowrap;
    transition: all .15s ease;
    border: 1px dashed;
}
.queue-status-btn i {
    font-size: 12px;
}
.queue-status-btn.to-ready {
    background: #f0fdf4;
    color: #15803d;
    border-color: #86efac;
}
.queue-status-btn.to-ready:hover {
    background: #16a34a;
    color: #ffffff;
    border: 1px solid #16a34a;
    transform: translateY(-1px);
    box-shadow: 0 2px 6px rgba(22, 163, 74, .25);
}
.queue-status-btn.to-completed {
    background: #eff6ff;
    color: #1d4ed8;
    border-color: #93c5fd;
}
.queue-status-btn.to-completed:hover {
    background: #2563eb;
    color: #ffffff;
    border: 1px solid #2563eb;
    transform: translateY(-1px);
    box-shadow: 0 2px 6px rgba(37, 99, 235, .25);
}
.queue-status-btn.to-waiting {
    background: #f8fafc;
    color: #475569;
    border-color: #cbd5e1;
}
.queue-status-btn.to-waiting:hover {
    background: #475569;
    color: #ffffff;
    border: 1px solid #475569;
    transform: translateY(-1px);
    box-shadow: 0 2px 6px rgba(71, 85, 105, .25);
}

/* Tombol Interaktif Status Pembayaran */
.queue-pay-btn {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 5px 12px;
    border-radius: 30px;
    font-size: 11.5px;
    font-weight: 700;
    cursor: pointer;
    border: 1px solid;
    text-decoration: none;
    line-height: 1.25;
    white-space: nowrap;
    transition: all .15s ease;
    box-shadow: 0 1px 2px rgba(0, 0, 0, .04);
}
.queue-pay-btn i:first-child {
    font-size: 13px;
    line-height: 1;
}

/* Pembayaran Lunas */
.queue-pay-btn.is-paid {
    background: #ecfdf5;
    color: #047857;
    border-color: #a7f3d0;
}
.queue-pay-btn.is-paid i:first-child {
    color: #059669;
}
.queue-pay-btn.is-paid .pay-toggle-hint {
    background: rgba(4, 120, 87, 0.12);
    color: #047857;
}
.queue-pay-btn.is-paid:hover {
    background: #d1fae5;
    border-color: #6ee7b7;
    color: #065f46;
    transform: translateY(-1px);
    box-shadow: 0 3px 8px rgba(5, 150, 105, .2);
}

/* Pembayaran Belum Lunas */
.queue-pay-btn.is-unpaid {
    background: #fff7ed;
    color: #c2410c;
    border-color: #fed7aa;
}
.queue-pay-btn.is-unpaid i:first-child {
    color: #ea580c;
}
.queue-pay-btn.is-unpaid .pay-toggle-hint {
    background: rgba(194, 65, 12, 0.12);
    color: #c2410c;
}
.queue-pay-btn.is-unpaid:hover {
    background: #ffedd5;
    border-color: #fdba74;
    color: #9a3412;
    transform: translateY(-1px);
    box-shadow: 0 3px 8px rgba(194, 65, 12, .2);
}

/* Lingkaran Hint Toggle Pembayaran */
.queue-pay-btn .pay-toggle-hint {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 18px;
    height: 18px;
    border-radius: 50%;
    font-size: 10px;
    margin-left: 2px;
    transition: transform .25s ease;
}
.queue-pay-btn:hover .pay-toggle-hint {
    transform: rotate(180deg);
}

/* Tombol Aksi Edit & Hapus */
.queue-actions-cell {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    white-space: nowrap;
}
.queue-button.small {
    padding: 5px 11px;
    font-size: 11.5px;
    font-weight: 600;
    border-radius: 8px;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    line-height: 1.25;
}
.queue-btn-danger-soft {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 5px 11px;
    font-size: 11.5px;
    font-weight: 600;
    border-radius: 8px;
    line-height: 1.25;
    background: #fef2f2;
    color: #dc2626;
    border: 1px solid #fecaca;
    cursor: pointer;
    text-decoration: none;
    transition: all .15s ease;
}
.queue-btn-danger-soft:hover {
    background: #dc2626;
    color: #ffffff;
    border-color: #dc2626;
    transform: translateY(-1px);
    box-shadow: 0 2px 6px rgba(220, 38, 38, .2);
}

/* Styling Pop-up Modal Konfirmasi Modern */
.confirm-modal-custom {
    border: none;
    border-radius: 20px;
    box-shadow: 0 20px 60px rgba(18, 38, 63, .22);
    overflow: hidden;
}
.confirm-icon-bubble {
    width: 64px;
    height: 64px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 30px;
    transition: transform .2s ease;
}
.confirm-icon-bubble.type-delete {
    background: #fee2e2;
    color: #dc2626;
}
.confirm-icon-bubble.type-status-ready {
    background: #dcfce7;
    color: #15803d;
}
.confirm-icon-bubble.type-status-complete {
    background: #dbeafe;
    color: #1d4ed8;
}
.confirm-icon-bubble.type-status-waiting {
    background: #f1f5f9;
    color: #475569;
}
.confirm-icon-bubble.type-payment-paid {
    background: #d1fae5;
    color: #047857;
}
.confirm-icon-bubble.type-payment-unpaid {
    background: #fef3c7;
    color: #b45309;
}
.confirm-info-box {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
}
.confirm-badge-alert {
    background: #f1f5f9;
    color: #334155;
    font-size: 11.5px;
    line-height: 1.35;
}
.confirm-badge-alert.alert-delete {
    background: #fef2f2;
    color: #991b1b;
    border: 1px solid #fee2e2;
}
.confirm-badge-alert.alert-ready {
    background: #f0fdf4;
    color: #166534;
    border: 1px solid #dcfce7;
}
.confirm-badge-alert.alert-complete {
    background: #eff6ff;
    color: #1e40af;
    border: 1px solid #dbeafe;
}
.confirm-badge-alert.alert-waiting {
    background: #f8fafc;
    color: #334155;
    border: 1px solid #e2e8f0;
}
.confirm-badge-alert.alert-paid {
    background: #ecfdf5;
    color: #065f46;
    border: 1px solid #d1fae5;
}
.confirm-badge-alert.alert-unpaid {
    background: #fffbeb;
    color: #92400e;
    border: 1px solid #fef3c7;
}
.queue-btn-danger-soft {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 7px 11px;
    font-size: 12px;
    font-weight: 600;
    border-radius: 8px;
    background: #fee2e2;
    color: #dc2626;
    border: 1px solid #fecaca;
    cursor: pointer;
    text-decoration: none;
    transition: all .15s ease;
}
.queue-btn-danger-soft:hover {
    background: #dc2626;
    color: #ffffff;
    border-color: #dc2626;
    transform: translateY(-1px);
    box-shadow: 0 2px 6px rgba(220, 38, 38, .2);
}
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
    background: var(--brand, #267b72);
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
.queue-add-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 11px 18px;
    border: 0;
    border-radius: 10px;
    background: var(--brand, #267b72);
    color: white;
    font-size: 14px;
    font-weight: 700;
    cursor: pointer;
    text-decoration: none;
    box-shadow: 0 4px 14px rgba(38, 123, 114, .25);
    transition: background-color .15s ease, transform .1s ease, box-shadow .15s ease;
}
.queue-add-btn:hover, .queue-add-btn:focus {
    background: var(--brand-dark, #1f665f);
    color: white;
    transform: translateY(-1px);
    box-shadow: 0 6px 18px rgba(38, 123, 114, .3);
}
.order-modal-custom {
    border: 0;
    border-radius: 18px;
    overflow: hidden;
    box-shadow: 0 24px 80px rgba(24, 48, 68, .2);
}
.order-modal-custom .modal-header {
    padding: 20px 24px;
    border-bottom: 1px solid #edf0f5;
    background: #fff;
}
.order-modal-custom .modal-body {
    padding: 22px 24px;
}
.order-modal-custom .modal-footer {
    padding: 16px 24px;
    border-top: 1px solid #edf0f5;
    background: #fbfcfd;
}
.order-modal-custom .form-control,
.order-modal-custom .form-select {
    min-height: 44px;
    border-radius: 10px;
    border-color: #dce2ec;
    font-size: 13.5px;
}
.order-modal-custom .form-control:focus,
.order-modal-custom .form-select:focus {
    border-color: var(--brand, #267b72);
    box-shadow: 0 0 0 3px rgba(38, 123, 114, .15);
}
.live-calc-box {
    background: #f5fbf9;
    border: 1px solid #d4ece6;
    border-radius: 12px;
    padding: 14px 16px;
}
.btn-brand {
    background: var(--brand, #267b72);
    border: 1px solid var(--brand, #267b72);
    color: white;
    font-weight: 600;
    border-radius: 10px;
    padding: 9px 18px;
    transition: all .15s ease;
}
.btn-brand:hover, .btn-brand:focus, .btn-brand:active {
    background: var(--brand-dark, #1f665f);
    border-color: var(--brand-dark, #1f665f);
    color: white;
}
@media(max-width: 850px) {
    .queue-stats { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .queue-page { padding: 15px; }
}
@media(max-width: 520px) {
    .queue-stats { grid-template-columns: 1fr 1fr; gap: 10px; }
    .queue-stat { padding: 14px; }
    .queue-stat strong { font-size: 22px; }
    .queue-search-group { width: 100%; }
    .queue-filters .queue-status-select { width: 100%; }
    .queue-filters .queue-payment-select { width: 100%; }
    .queue-filters { width: 100%; }
    .queue-subbar { padding: 10px 14px; }
    .queue-pay-segmented { width: 100%; }
    .queue-segmented-group { width: 100%; display: flex; }
    .queue-seg-btn { flex: 1; justify-content: center; padding: 6px 8px; font-size: 11.5px; }
}
</style>

<div class="queue-page">
    <div class="queue-heading">
        <div>
            <h1>Antrean &amp; Order</h1>
            <div class="queue-muted">Kelola semua transaksi laundry CuciKu.</div>
        </div>
        <button type="button" class="queue-add-btn" id="btnOpenTambahData" data-bs-toggle="modal" data-bs-target="#modalTambahData">
            <i class="bi bi-plus-lg me-1"></i> Tambah Data
        </button>
    </div>

    <?php if ($modalError && !empty($modalError['errors'])): ?>
        <div class="queue-alert">
            <?php foreach ($modalError['errors'] as $error): ?>
                <div><?= antreanE($error) ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div class="queue-stats">
        <div class="queue-stat">
            <div class="queue-stat-top">
                <span>Total Transaksi</span>
                <span class="queue-stat-icon icon-blue"><i class="bi bi-receipt"></i></span>
            </div>
            <strong><?= $totalOrders ?></strong>
        </div>
        <div class="queue-stat">
            <div class="queue-stat-top">
                <span>Menunggu</span>
                <span class="queue-stat-icon icon-orange"><i class="bi bi-hourglass-split"></i></span>
            </div>
            <strong><?= $waitingCount ?></strong>
        </div>
        <div class="queue-stat">
            <div class="queue-stat-top">
                <span>Sedang Diproses</span>
                <span class="queue-stat-icon icon-purple"><i class="bi bi-arrow-repeat"></i></span>
            </div>
            <strong><?= $processCount ?></strong>
        </div>
        <div class="queue-stat">
            <div class="queue-stat-top">
                <span>Siap Diambil</span>
                <span class="queue-stat-icon icon-green"><i class="bi bi-check2-circle"></i></span>
            </div>
            <strong><?= $readyCount ?></strong>
        </div>
    </div>

    <section class="queue-panel">
        <div class="queue-panel-head">
            <h2>Daftar Antrean</h2>

            <form class="queue-filters" method="GET" action="antrean.php">
                <?php if ($sort !== ''): ?>
                    <input type="hidden" name="sort" value="<?= antreanE($sort) ?>">
                    <input type="hidden" name="order" value="<?= antreanE($order) ?>">
                <?php endif; ?>

                <div class="queue-search-group">
                    <i class="bi bi-search queue-search-icon"></i>
                    <input
                        type="search"
                        name="q"
                        class="queue-search-input"
                        placeholder="Cari nama, kode, nomor WA..."
                        value="<?= antreanE($query) ?>"
                        autocomplete="off"
                    >
                    <button class="queue-search-submit" type="submit" title="Cari data antrean">
                        Cari
                    </button>
                </div>

                <select name="status" class="queue-status-select" onchange="this.form.submit()" title="Filter status pesanan">
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

                <select name="pembayaran" class="queue-status-select queue-payment-select" onchange="this.form.submit()" title="Filter status pembayaran">
                    <option value="all" <?= $pembayaranFilter === 'all' ? 'selected' : '' ?>>
                        Semua Pembayaran
                    </option>
                    <option value="lunas" <?= $pembayaranFilter === 'lunas' ? 'selected' : '' ?>>
                        Lunas (<?= $lunasCount ?>)
                    </option>
                    <option value="belum_lunas" <?= $pembayaranFilter === 'belum_lunas' ? 'selected' : '' ?>>
                        Belum Lunas (<?= $belumLunasCount ?>)
                    </option>
                </select>

                <?php if ($query !== '' || $statusFilter !== 'all' || $pembayaranFilter !== 'all' || $sort !== ''): ?>
                    <a class="queue-reset-btn" href="antrean.php" title="Reset filter antrean">
                        <i class="bi bi-arrow-counterclockwise me-1"></i> Reset
                    </a>
                <?php endif; ?>
            </form>
        </div>

        <div class="queue-subbar">
            <div class="queue-pay-segmented">
                <span class="queue-segmented-title">
                    <i class="bi bi-cash-stack me-1"></i>Status Bayar:
                </span>
                <div class="queue-segmented-group">
                    <a href="<?= antreanUrl(['pembayaran' => 'all', 'page' => 1]) ?>"
                       class="queue-seg-btn <?= $pembayaranFilter === 'all' ? 'active' : '' ?>">
                        Semua
                        <span class="seg-badge"><?= $totalOrders ?></span>
                    </a>
                    <a href="<?= antreanUrl(['pembayaran' => 'lunas', 'page' => 1]) ?>"
                       class="queue-seg-btn is-lunas <?= $pembayaranFilter === 'lunas' ? 'active' : '' ?>">
                        <i class="bi bi-check-circle-fill"></i> Lunas
                        <span class="seg-badge"><?= $lunasCount ?></span>
                    </a>
                    <a href="<?= antreanUrl(['pembayaran' => 'belum_lunas', 'page' => 1]) ?>"
                       class="queue-seg-btn is-unpaid <?= $pembayaranFilter === 'belum_lunas' ? 'active' : '' ?>">
                        <i class="bi bi-exclamation-circle-fill"></i> Belum Lunas
                        <span class="seg-badge"><?= $belumLunasCount ?></span>
                    </a>
                </div>
            </div>

            <?php if ($query !== '' || $statusFilter !== 'all' || $pembayaranFilter !== 'all' || $sort !== ''): ?>
            <div class="queue-active-filters">
                <span class="text-muted small me-1">Filter aktif:</span>
                <?php if ($query !== ''): ?>
                    <span class="filter-chip">
                        Cari: "<?= antreanE($query) ?>"
                        <a href="<?= antreanUrl(['q' => '', 'page' => 1]) ?>" title="Hapus filter pencarian"><i class="bi bi-x"></i></a>
                    </span>
                <?php endif; ?>
                <?php if ($statusFilter !== 'all'): ?>
                    <span class="filter-chip">
                        Status: <?= antreanE($statusLabels[$statusFilter] ?? $statusFilter) ?>
                        <a href="<?= antreanUrl(['status' => 'all', 'page' => 1]) ?>" title="Hapus filter status"><i class="bi bi-x"></i></a>
                    </span>
                <?php endif; ?>
                <?php if ($pembayaranFilter !== 'all'): ?>
                    <span class="filter-chip <?= $pembayaranFilter === 'lunas' ? 'chip-lunas' : 'chip-unpaid' ?>">
                        Bayar: <?= $pembayaranFilter === 'lunas' ? 'Lunas' : 'Belum Lunas' ?>
                        <a href="<?= antreanUrl(['pembayaran' => 'all', 'page' => 1]) ?>" title="Hapus filter pembayaran"><i class="bi bi-x"></i></a>
                    </span>
                <?php endif; ?>
                <?php if ($sort !== ''): ?>
                    <span class="filter-chip">
                        Urut: <?= antreanE($sort) ?> (<?= strtoupper($order) ?>)
                        <a href="<?= antreanUrl(['sort' => '', 'order' => '', 'page' => 1]) ?>" title="Reset urutan"><i class="bi bi-x"></i></a>
                    </span>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>

        <div class="queue-table-wrap">
            <table class="queue-table">
                <thead>
                    <tr>
                        <th>
                            <a href="<?= antreanUrl(['sort' => 'nama_pelanggan', 'order' => ($sort === 'nama_pelanggan' && $order === 'asc' ? 'desc' : 'asc'), 'page' => 1]) ?>" class="sort-header <?= $sort === 'nama_pelanggan' ? 'active-sort' : '' ?>" title="Urutkan berdasarkan pelanggan">
                                Kode / Pelanggan
                                <i class="bi <?= $sort === 'nama_pelanggan' ? ($order === 'asc' ? 'bi-sort-alpha-down' : 'bi-sort-alpha-up-alt') : 'bi-arrow-down-up' ?>"></i>
                            </a>
                        </th>
                        <th>Paket</th>
                        <th>
                            <a href="<?= antreanUrl(['sort' => 'berat', 'order' => ($sort === 'berat' && $order === 'asc' ? 'desc' : 'asc'), 'page' => 1]) ?>" class="sort-header <?= $sort === 'berat' ? 'active-sort' : '' ?>" title="Urutkan berdasarkan berat">
                                Berat
                                <i class="bi <?= $sort === 'berat' ? ($order === 'asc' ? 'bi-sort-numeric-down' : 'bi-sort-numeric-up-alt') : 'bi-arrow-down-up' ?>"></i>
                            </a>
                        </th>
                        <th>
                            <a href="<?= antreanUrl(['sort' => 'tanggal_masuk', 'order' => ($sort === 'tanggal_masuk' && $order === 'asc' ? 'desc' : 'asc'), 'page' => 1]) ?>" class="sort-header <?= $sort === 'tanggal_masuk' ? 'active-sort' : '' ?>" title="Urutkan berdasarkan tanggal masuk">
                                Tanggal Masuk
                                <i class="bi <?= $sort === 'tanggal_masuk' ? ($order === 'asc' ? 'bi-sort-down' : 'bi-sort-up-alt') : 'bi-arrow-down-up' ?>"></i>
                            </a>
                        </th>
                        <th>
                            <a href="<?= antreanUrl(['sort' => 'deadline', 'order' => ($sort === 'deadline' && $order === 'asc' ? 'desc' : 'asc'), 'page' => 1]) ?>" class="sort-header <?= $sort === 'deadline' ? 'active-sort' : '' ?>" title="Urutkan berdasarkan deadline">
                                Deadline
                                <i class="bi <?= $sort === 'deadline' ? ($order === 'asc' ? 'bi-sort-down' : 'bi-sort-up-alt') : 'bi-arrow-down-up' ?>"></i>
                            </a>
                        </th>
                        <th>
                            <a href="<?= antreanUrl(['sort' => 'total_biaya', 'order' => ($sort === 'total_biaya' && $order === 'asc' ? 'desc' : 'asc'), 'page' => 1]) ?>" class="sort-header <?= $sort === 'total_biaya' ? 'active-sort' : '' ?>" title="Urutkan berdasarkan total biaya">
                                Total
                                <i class="bi <?= $sort === 'total_biaya' ? ($order === 'asc' ? 'bi-sort-numeric-down' : 'bi-sort-numeric-up-alt') : 'bi-arrow-down-up' ?>"></i>
                            </a>
                        </th>
                        <th style="min-width: 145px;">
                            <a href="<?= antreanUrl(['sort' => 'status', 'order' => ($sort === 'status' && $order === 'asc' ? 'desc' : 'asc'), 'page' => 1]) ?>" class="sort-header <?= $sort === 'status' ? 'active-sort' : '' ?>" title="Urutkan berdasarkan status order">
                                Status
                                <i class="bi <?= $sort === 'status' ? ($order === 'asc' ? 'bi-sort-down' : 'bi-sort-up-alt') : 'bi-arrow-down-up' ?>"></i>
                            </a>
                        </th>
                        <th style="min-width: 140px;">
                            <a href="<?= antreanUrl(['sort' => 'pembayaran', 'order' => ($sort === 'pembayaran' && $order === 'asc' ? 'desc' : 'asc'), 'page' => 1]) ?>" class="sort-header <?= $sort === 'pembayaran' ? 'active-sort' : '' ?>" title="Urutkan berdasarkan status pembayaran">
                                Pembayaran
                                <i class="bi <?= $sort === 'pembayaran' ? ($order === 'asc' ? 'bi-sort-down' : 'bi-sort-up-alt') : 'bi-arrow-down-up' ?>"></i>
                            </a>
                        </th>
                        <th style="min-width: 135px;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$pageOrders): ?>
                        <tr>
                            <td class="queue-empty text-center py-5" colspan="9">
                                <i class="bi bi-inbox fs-1 text-muted d-block mb-2 opacity-50"></i>
                                <?php if ($query !== '' || $statusFilter !== 'all' || $pembayaranFilter !== 'all'): ?>
                                    <strong class="d-block text-dark fs-6 mb-1">Tidak ada transaksi yang cocok</strong>
                                    <span class="text-muted small d-block mb-3">Tidak ditemukan data dengan kriteria filter saat ini.</span>
                                    <a href="antrean.php" class="btn btn-sm btn-outline-brand">
                                        <i class="bi bi-arrow-counterclockwise me-1"></i> Reset Semua Filter
                                    </a>
                                <?php else: ?>
                                    <strong class="d-block text-dark fs-6 mb-1">Belum Ada Transaksi</strong>
                                    <span class="text-muted small">Belum ada data transaksi yang tercatat di sistem.</span>
                                <?php endif; ?>
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
                                    <?php
                                    $orderStatus = $order['status'] ?? '';
                                    $isReady = ($orderStatus === 'ready');
                                    $isCompleted = ($orderStatus === 'completed');
                                    $statusIcon = $statusIcons[$orderStatus] ?? 'bi-tag-fill';
                                    $statusText = $statusLabels[$orderStatus] ?? ($orderStatus ?: '-');

                                    if ($isCompleted) {
                                        $btnClass = 'to-waiting';
                                        $btnIcon = 'bi-arrow-counterclockwise';
                                        $btnLabel = 'Ke Menunggu';
                                        $targetStatus = 'waiting';
                                        $modalTitle = 'Kembalikan ke Menunggu?';
                                        $modalBadge = 'Status pesanan akan dikembalikan menjadi antrean Menunggu.';
                                        $modalType = 'status-waiting';
                                        $btnTooltip = 'Kembalikan status transaksi ke antrean Menunggu';
                                    } elseif ($isReady) {
                                        $btnClass = 'to-completed';
                                        $btnIcon = 'bi-check2-all';
                                        $btnLabel = 'Tandai Selesai';
                                        $targetStatus = 'completed';
                                        $modalTitle = 'Selesaikan Transaksi?';
                                        $modalBadge = 'Cucian telah diserahkan ke pelanggan dan transaksi dinyatakan selesai.';
                                        $modalType = 'status-complete';
                                        $btnTooltip = 'Tandai cucian telah diserahkan dan transaksi selesai';
                                    } else {
                                        $btnClass = 'to-ready';
                                        $btnIcon = 'bi-bag-check-fill';
                                        $btnLabel = 'Siap Diambil';
                                        $targetStatus = 'ready';
                                        $modalTitle = 'Ubah ke Siap Diambil?';
                                        $modalBadge = 'Cucian telah selesai diproses dan status otomatis diubah menjadi Siap Diambil.';
                                        $modalType = 'status-ready';
                                        $btnTooltip = 'Orderan selesai: ubah status otomatis menjadi Siap Diambil';
                                    }
                                    ?>
                                    <div class="queue-status-cell">
                                        <span class="queue-badge <?= antreanStatusClass($orderStatus) ?> <?= antreanE($orderStatus) ?>">
                                            <i class="bi <?= antreanE($statusIcon) ?>"></i>
                                            <span><?= antreanE($statusText) ?></span>
                                        </span>
                                        <button
                                            type="button"
                                            class="queue-status-btn <?= $btnClass ?> btn-action-confirm"
                                            data-action="toggle_status"
                                            data-code="<?= antreanE($order['kode_transaksi'] ?? '') ?>"
                                            data-target="<?= $targetStatus ?>"
                                            data-title="<?= $modalTitle ?>"
                                            data-customer="<?= antreanE($order['nama_pelanggan'] ?? '') ?>"
                                            data-total="<?= antreanRupiah($order['total_biaya'] ?? 0) ?>"
                                            data-badge="<?= $modalBadge ?>"
                                            data-type="<?= $modalType ?>"
                                            title="<?= $btnTooltip ?>"
                                        >
                                            <i class="bi <?= $btnIcon ?>"></i>
                                            <span><?= $btnLabel ?></span>
                                        </button>
                                    </div>
                                </td>
                                <td>
                                    <?php $isLunas = (($order['pembayaran'] ?? '') === 'lunas'); ?>
                                    <button
                                        type="button"
                                        class="queue-pay-btn <?= $isLunas ? 'is-paid' : 'is-unpaid' ?> btn-action-confirm"
                                        data-action="toggle_payment"
                                        data-code="<?= antreanE($order['kode_transaksi'] ?? '') ?>"
                                        data-target="<?= $isLunas ? 'belum_lunas' : 'lunas' ?>"
                                        data-title="<?= $isLunas ? 'Ubah Menjadi Belum Lunas?' : 'Tandai Pembayaran Lunas?' ?>"
                                        data-customer="<?= antreanE($order['nama_pelanggan'] ?? '') ?>"
                                        data-total="<?= antreanRupiah($order['total_biaya'] ?? 0) ?>"
                                        data-badge="<?= $isLunas ? 'Status pembayaran transaksi ini akan diubah kembali menjadi Belum Lunas.' : 'Pembayaran sebesar ' . antreanRupiah($order['total_biaya'] ?? 0) . ' telah diterima lunas.' ?>"
                                        data-type="<?= $isLunas ? 'payment-unpaid' : 'payment-paid' ?>"
                                        title="Klik untuk mengubah status pembayaran menjadi <?= $isLunas ? 'Belum Lunas' : 'Lunas' ?>"
                                    >
                                        <i class="bi <?= $isLunas ? 'bi-check-circle-fill' : 'bi-exclamation-circle-fill' ?>"></i>
                                        <span><?= $isLunas ? 'Lunas' : 'Belum Lunas' ?></span>
                                        <span class="pay-toggle-hint" title="Ubah status pembayaran">
                                            <i class="bi bi-arrow-repeat"></i>
                                        </span>
                                    </button>
                                </td>
                                <td>
                                    <div class="queue-actions-cell">
                                        <a
                                            class="queue-button small"
                                            href="antrean.php?modal=edit&amp;code=<?= urlencode($order['kode_transaksi'] ?? '') ?>"
                                            title="Edit rincian data transaksi"
                                        >
                                            <i class="bi bi-pencil-square"></i>
                                            <span>Edit</span>
                                        </a>
                                        <button
                                            type="button"
                                            class="queue-btn-danger-soft btn-action-confirm"
                                            data-action="delete_order"
                                            data-code="<?= antreanE($order['kode_transaksi'] ?? '') ?>"
                                            data-title="Hapus Transaksi Laundry?"
                                            data-customer="<?= antreanE($order['nama_pelanggan'] ?? '') ?>"
                                            data-total="<?= antreanRupiah($order['total_biaya'] ?? 0) ?>"
                                            data-badge="PERINGATAN: Transaksi ini akan dihapus secara permanen dari basis data dan antrean."
                                            data-type="delete"
                                            title="Hapus transaksi ini"
                                        >
                                            <i class="bi bi-trash3"></i>
                                            <span>Hapus</span>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="queue-pagination">
            <span>
                Menampilkan <strong><?= $totalFiltered > 0 ? (($page - 1) * $perPage + 1) : 0 ?></strong> – <strong><?= min($totalFiltered, $page * $perPage) ?></strong> dari <strong><?= $totalFiltered ?></strong> transaksi (10 per halaman)
            </span>

            <?php if ($totalPages > 1): ?>
                <div class="queue-pages">
                    <?php if ($page > 1): ?>
                        <a href="<?= antreanUrl(['page' => $page - 1]) ?>">
                            Sebelumnya
                        </a>
                    <?php endif; ?>

                    <?php
                    $startPage = max(1, $page - 2);
                    $endPage = min($totalPages, $page + 2);
                    if ($startPage > 1): ?>
                        <a href="<?= antreanUrl(['page' => 1]) ?>">1</a>
                        <?php if ($startPage > 2): ?><span>…</span><?php endif; ?>
                    <?php endif; ?>

                    <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                        <?php if ($i === $page): ?>
                            <span class="active"><?= $i ?></span>
                        <?php else: ?>
                            <a href="<?= antreanUrl(['page' => $i]) ?>">
                                <?= $i ?>
                            </a>
                        <?php endif; ?>
                    <?php endfor; ?>

                    <?php if ($endPage < $totalPages): ?>
                        <?php if ($endPage < $totalPages - 1): ?><span>…</span><?php endif; ?>
                        <a href="<?= antreanUrl(['page' => $totalPages]) ?>"><?= $totalPages ?></a>
                    <?php endif; ?>

                    <?php if ($page < $totalPages): ?>
                        <a href="<?= antreanUrl(['page' => $page + 1]) ?>">
                            Berikutnya
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>
</div>

<?php
$isAddModal = ($modal === 'add') || (!empty($addPost) && !empty($modalError['errors'])) || ($customerIdParam > 0);
$isEditModal = ($modal === 'edit' && $editOrder !== null);

// Data untuk Edit Modal
$formEditOrder = $isEditModal ? $editOrder : [];
if ($isEditModal && $editPost) {
    $formEditOrder = array_merge($formEditOrder, [
        'nama_pelanggan' => $editPost['customer'] ?? ($formEditOrder['nama_pelanggan'] ?? ''),
        'no_wa'          => $editPost['phone'] ?? ($formEditOrder['no_wa'] ?? ''),
        'paket'          => $editPost['package'] ?? ($formEditOrder['paket'] ?? 'Reguler'),
        'berat'          => $editPost['weight'] ?? ($formEditOrder['berat'] ?? ''),
        'tanggal_masuk'  => str_replace('T', ' ', $editPost['dateIn'] ?? ($formEditOrder['tanggal_masuk'] ?? '')),
        'status'         => $editPost['status'] ?? ($formEditOrder['status'] ?? 'waiting'),
        'pembayaran'     => isset($editPost['paid']) ? 'lunas' : 'belum_lunas'
    ]);
}

$editDateValue = '';
if (!empty($formEditOrder['tanggal_masuk'])) {
    $editTs = strtotime($formEditOrder['tanggal_masuk']);
    if ($editTs !== false) {
        $editDateValue = date('Y-m-d\TH:i', $editTs);
    }
}

// Data default untuk Tambah Modal
$defaultDateIn = date('Y-m-d\TH:i');
if (!empty($addPost['dateIn'])) {
    $ts = strtotime($addPost['dateIn']);
    if ($ts !== false) {
        $defaultDateIn = date('Y-m-d\TH:i', $ts);
    }
}
?>

<!-- ==========================================
     MODAL TAMBAH DATA (BOOTSTRAP 5)
=========================================== -->
<div class="modal fade" id="modalTambahData" tabindex="-1" aria-labelledby="modalTambahDataLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg">
        <div class="modal-content order-modal-custom">
            <div class="modal-header">
                <div class="d-flex align-items-center gap-2">
                    <span class="d-inline-flex align-items-center justify-content-center rounded-3 p-2 bg-success-subtle text-success">
                        <i class="bi bi-plus-circle-fill fs-5" style="color: var(--brand, #267b72) !important;"></i>
                    </span>
                    <div>
                        <h5 class="modal-title fw-bold text-dark mb-0" id="modalTambahDataLabel">Tambah Transaksi Baru</h5>
                        <p class="text-muted small mb-0">Isi rincian transaksi laundry untuk memasukkan antrean baru.</p>
                    </div>
                </div>
                <button type="button" class="btn-close" id="btnTambahCloseTop" aria-label="Tutup"></button>
            </div>

            <form id="formTambahData" method="POST" action="antrean.php" novalidate>
                <input type="hidden" name="action" value="add_order">

                <div class="modal-body py-3 px-4">
                    <?php if ($addPost && !empty($modalError['errors'])): ?>
                        <div class="alert alert-danger py-2 px-3 mb-3 small d-flex align-items-start gap-2" role="alert">
                            <i class="bi bi-exclamation-triangle-fill fs-6 mt-1 flex-shrink-0"></i>
                            <div>
                                <strong>Gagal menyimpan transaksi:</strong>
                                <ul class="mb-0 mt-1 ps-3">
                                    <?php foreach ($modalError['errors'] as $err): ?>
                                        <li><?= antreanE($err) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="row g-3">
                        <div class="col-12 col-md-7">
                            <label for="addCustomerSelect" class="form-label fw-semibold text-dark small mb-1">
                                Nama Pelanggan <span class="text-danger">*</span>
                            </label>
                            <div class="input-group input-group-sm mb-2">
                                <span class="input-group-text bg-light text-muted border-end-0 py-1 px-2">
                                    <i class="bi bi-search small"></i>
                                </span>
                                <input
                                    type="text"
                                    class="form-control border-start-0 ps-1 py-1"
                                    id="searchCustomerBox"
                                    placeholder="Ketik untuk memfilter pelanggan..."
                                    autocomplete="off"
                                >
                                <button class="btn btn-outline-secondary btn-sm py-0 px-2" type="button" id="btnClearCustomerSearch" title="Reset pencarian" style="display:none;">
                                    <i class="bi bi-x small"></i>
                                </button>
                            </div>
                            <select
                                class="form-select"
                                id="addCustomerSelect"
                                name="pelanggan_id"
                                required
                            >
                                <option value="" disabled <?= empty($addPost['pelanggan_id']) ? 'selected' : '' ?>>-- Pilih Pelanggan Terdaftar --</option>
                                <?php foreach ($registeredCustomers as $cust): ?>
                                    <?php
                                        $isSelected = ((string)($addPost['pelanggan_id'] ?? '') === (string)$cust['id_pelanggan']);
                                        $searchKeywords = strtolower(trim(($cust['nama'] ?? '') . ' ' . ($cust['no_hp'] ?? '') . ' ' . ($cust['email'] ?? '')));
                                    ?>
                                    <option
                                        value="<?= antreanE($cust['id_pelanggan']) ?>"
                                        data-name="<?= antreanE($cust['nama']) ?>"
                                        data-phone="<?= antreanE($cust['no_hp'] ?? '') ?>"
                                        data-keywords="<?= antreanE($searchKeywords) ?>"
                                        <?= $isSelected ? 'selected' : '' ?>
                                    >
                                        <?= antreanE($cust['nama']) ?><?= !empty($cust['no_hp']) ? ' — ' . antreanE($cust['no_hp']) : '' ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <input type="hidden" name="customer" id="addCustomer" value="<?= antreanE($addPost['customer'] ?? '') ?>">
                            <div class="invalid-feedback small">Silakan pilih salah satu pelanggan terdaftar.</div>
                            <div class="d-flex justify-content-between align-items-center mt-1">
                                <span class="text-muted small" style="font-size: 11px;">
                                    <i class="bi bi-people me-1"></i><?= count($registeredCustomers) ?> pelanggan terdaftar
                                </span>
                                <span class="text-muted small" style="font-size: 11px;" id="customerMatchHint"></span>
                            </div>
                        </div>

                        <div class="col-12 col-md-5">
                            <label for="addPhone" class="form-label fw-semibold text-dark small mb-1">
                                Nomor WhatsApp
                            </label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-whatsapp text-success"></i></span>
                                <input
                                    type="tel"
                                    class="form-control border-start-0 ps-0 bg-light"
                                    id="addPhone"
                                    name="phone"
                                    maxlength="20"
                                    placeholder="Otomatis terisi dari user"
                                    value="<?= antreanE($addPost['phone'] ?? '') ?>"
                                    readonly
                                >
                            </div>
                            <div class="form-text text-muted small" style="font-size: 11px;">
                                <i class="bi bi-shield-check text-success me-1"></i>Otomatis tersinkron dari database pelanggan.
                            </div>
                        </div>

                        <div class="col-12 col-md-6">
                            <label for="addPackage" class="form-label fw-semibold text-dark small mb-1">
                                Paket Layanan <span class="text-danger">*</span>
                            </label>
                            <select class="form-select" id="addPackage" name="package" required>
                                <option value="Reguler" <?= ($addPost['package'] ?? 'Reguler') === 'Reguler' ? 'selected' : '' ?>>
                                    Reguler (Rp 7.000 / kg - 48 jam)
                                </option>
                                <option value="Express" <?= ($addPost['package'] ?? '') === 'Express' ? 'selected' : '' ?>>
                                    Express (Rp 14.000 / kg - 12 jam)
                                </option>
                            </select>
                        </div>

                        <div class="col-12 col-md-6">
                            <label for="addWeight" class="form-label fw-semibold text-dark small mb-1">
                                Berat Cucian (kg) <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <input
                                    type="number"
                                    class="form-control"
                                    id="addWeight"
                                    name="weight"
                                    step="0.1"
                                    min="0.1"
                                    max="25"
                                    required
                                    placeholder="Contoh: 3.5 (maks. 25 kg)"
                                    value="<?= antreanE($addPost['weight'] ?? '') ?>"
                                >
                                <span class="input-group-text bg-light text-muted">kg</span>
                                <div class="invalid-feedback small">Masukkan berat cucian antara 0.1 - 25 kg.</div>
                            </div>
                        </div>

                        <div class="col-12">
                            <label for="addDateIn" class="form-label fw-semibold text-dark small mb-1">
                                Tanggal &amp; Waktu Masuk <span class="text-danger">*</span>
                            </label>
                            <input
                                type="datetime-local"
                                class="form-control"
                                id="addDateIn"
                                name="dateIn"
                                required
                                value="<?= antreanE($defaultDateIn) ?>"
                            >
                            <div class="invalid-feedback small">Tanggal &amp; waktu masuk wajib ditentukan.</div>
                        </div>

                        <div class="col-12">
                            <div class="live-calc-box">
                                <div class="d-flex align-items-center justify-content-between mb-2">
                                    <span class="small fw-bold text-uppercase text-secondary" style="letter-spacing: .04em;">
                                        <i class="bi bi-calculator me-1"></i> Estimasi &amp; Rincian
                                    </span>
                                    <span class="badge bg-light text-dark border fw-medium px-2 py-1" id="previewRateBadge">
                                        Tarif: Rp 7.000 / kg
                                    </span>
                                </div>
                                <div class="row g-2 align-items-center">
                                    <div class="col-12 col-sm-6">
                                        <div class="text-muted small">Estimasi Selesai (Deadline):</div>
                                        <div class="fw-semibold text-dark" id="previewDeadlineText">-</div>
                                    </div>
                                    <div class="col-12 col-sm-6 text-sm-end">
                                        <div class="text-muted small">Total Estimasi Biaya:</div>
                                        <div class="fs-5 fw-bold" style="color: var(--brand, #267b72);" id="previewTotalText">Rp 0</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer bg-light px-4 py-3 d-flex justify-content-between align-items-center">
                    <button type="button" class="btn btn-outline-secondary px-3" id="btnTambahBatal">
                        <i class="bi bi-x-lg me-1"></i> Batal
                    </button>
                    <button type="submit" class="btn btn-brand px-4" id="btnTambahSubmit">
                        <i class="bi bi-check-lg me-1"></i> Simpan Transaksi
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ==========================================
     MODAL EDIT DATA (BOOTSTRAP 5)
=========================================== -->
<?php if ($isEditModal): ?>
<div class="modal fade" id="modalEditData" tabindex="-1" aria-labelledby="modalEditDataLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg">
        <div class="modal-content order-modal-custom">
            <div class="modal-header">
                <div class="d-flex align-items-center gap-2">
                    <span class="d-inline-flex align-items-center justify-content-center rounded-3 p-2 bg-primary-subtle text-primary">
                        <i class="bi bi-pencil-square fs-5" style="color: var(--brand, #267b72) !important;"></i>
                    </span>
                    <div>
                        <h5 class="modal-title fw-bold text-dark mb-0" id="modalEditDataLabel">Edit Transaksi</h5>
                        <p class="text-muted small mb-0">Ubah rincian transaksi atau perbarui status cucian.</p>
                    </div>
                </div>
                <a href="antrean.php" class="btn-close" aria-label="Tutup"></a>
            </div>

            <form id="formEditData" method="POST" action="antrean.php" onsubmit="const btn=document.getElementById('btnEditSubmit'); if(btn){btn.disabled=true; btn.innerHTML='<span class=\'spinner-border spinner-border-sm me-1\'></span> Menyimpan...';}">
                <input type="hidden" name="action" value="edit_order">
                <input type="hidden" name="code" value="<?= antreanE($formEditOrder['kode_transaksi'] ?? '') ?>">

                <div class="modal-body py-3 px-4">
                    <?php if ($editPost && !empty($modalError['errors'])): ?>
                        <div class="alert alert-danger py-2 px-3 mb-3 small d-flex align-items-start gap-2" role="alert">
                            <i class="bi bi-exclamation-triangle-fill fs-6 mt-1 flex-shrink-0"></i>
                            <div>
                                <strong>Gagal memperbarui transaksi:</strong>
                                <ul class="mb-0 mt-1 ps-3">
                                    <?php foreach ($modalError['errors'] as $err): ?>
                                        <li><?= antreanE($err) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="row g-3">
                        <div class="col-12 col-md-4">
                            <label class="form-label fw-semibold text-dark small mb-1">Kode Transaksi</label>
                            <input
                                type="text"
                                class="form-control bg-light fw-bold text-secondary"
                                value="<?= antreanE($formEditOrder['kode_transaksi'] ?? '') ?>"
                                readonly
                            >
                        </div>

                        <div class="col-12 col-md-8">
                            <label for="editCustomer" class="form-label fw-semibold text-dark small mb-1">
                                Nama Pelanggan <span class="text-danger">*</span>
                            </label>
                            <input
                                type="text"
                                class="form-control"
                                id="editCustomer"
                                name="customer"
                                required
                                maxlength="100"
                                value="<?= antreanE($formEditOrder['nama_pelanggan'] ?? '') ?>"
                            >
                        </div>

                        <div class="col-12 col-md-6">
                            <label for="editPhone" class="form-label fw-semibold text-dark small mb-1">
                                Nomor WhatsApp
                            </label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-whatsapp text-success"></i></span>
                                <input
                                    type="tel"
                                    class="form-control border-start-0 ps-0"
                                    id="editPhone"
                                    name="phone"
                                    maxlength="20"
                                    value="<?= antreanE($formEditOrder['no_wa'] ?? '') ?>"
                                >
                            </div>
                        </div>

                        <div class="col-12 col-md-6">
                            <label for="editPackage" class="form-label fw-semibold text-dark small mb-1">
                                Paket Layanan <span class="text-danger">*</span>
                            </label>
                            <select class="form-select" id="editPackage" name="package" required>
                                <option value="Reguler" <?= ($formEditOrder['paket'] ?? 'Reguler') === 'Reguler' ? 'selected' : '' ?>>
                                    Reguler (Rp 7.000 / kg - 48 jam)
                                </option>
                                <option value="Express" <?= ($formEditOrder['paket'] ?? '') === 'Express' ? 'selected' : '' ?>>
                                    Express (Rp 14.000 / kg - 12 jam)
                                </option>
                            </select>
                        </div>

                        <div class="col-12 col-md-6">
                            <label for="editWeight" class="form-label fw-semibold text-dark small mb-1">
                                Berat Cucian (kg) <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <input
                                    type="number"
                                    class="form-control"
                                    id="editWeight"
                                    name="weight"
                                    step="0.1"
                                    min="0.1"
                                    max="25"
                                    required
                                    value="<?= antreanE($formEditOrder['berat'] ?? '') ?>"
                                >
                                <span class="input-group-text bg-light text-muted">kg</span>
                                <div class="invalid-feedback small">Masukkan berat cucian antara 0.1 - 25 kg.</div>
                            </div>
                        </div>

                        <div class="col-12 col-md-6">
                            <label for="editDateIn" class="form-label fw-semibold text-dark small mb-1">
                                Tanggal &amp; Waktu Masuk <span class="text-danger">*</span>
                            </label>
                            <input
                                type="datetime-local"
                                class="form-control"
                                id="editDateIn"
                                name="dateIn"
                                required
                                value="<?= antreanE($editDateValue) ?>"
                            >
                        </div>

                        <div class="col-12 col-md-6">
                            <label for="editStatus" class="form-label fw-semibold text-dark small mb-1">
                                Status Transaksi <span class="text-danger">*</span>
                            </label>
                            <select class="form-select" id="editStatus" name="status" required>
                                <?php foreach ($statusLabels as $k => $lbl): ?>
                                    <option value="<?= antreanE($k) ?>" <?= ($formEditOrder['status'] ?? '') === $k ? 'selected' : '' ?>>
                                        <?= antreanE($lbl) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-12 col-md-6 d-flex align-items-center">
                            <div class="form-check form-switch pt-md-3">
                                <input
                                    class="form-check-input"
                                    type="checkbox"
                                    role="switch"
                                    id="editPaid"
                                    name="paid"
                                    value="1"
                                    <?= ($formEditOrder['pembayaran'] ?? '') === 'lunas' ? 'checked' : '' ?>
                                    style="width: 2.5em; height: 1.3em;"
                                >
                                <label class="form-check-label fw-semibold text-dark small ms-2" for="editPaid">
                                    Status Pembayaran: Sudah Lunas
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer bg-light px-4 py-3 d-flex justify-content-between align-items-center">
                    <a href="antrean.php" class="btn btn-outline-secondary px-3">
                        <i class="bi bi-x-lg me-1"></i> Batal
                    </a>
                    <button type="submit" class="btn btn-brand px-4" id="btnEditSubmit">
                        <i class="bi bi-check-lg me-1"></i> Simpan Perubahan
                    </button>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>

<!-- Modal Konfirmasi Aksi (Hapus Data / Ubah Selesai / Ubah Lunas) -->
<div class="modal fade" id="modalKonfirmasiAksi" tabindex="-1" aria-labelledby="modalKonfirmasiLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 440px;">
        <div class="modal-content confirm-modal-custom">
            <form id="formKonfirmasiAksi" method="POST" action="antrean.php">
                <input type="hidden" name="action" id="confirmActionInput" value="">
                <input type="hidden" name="code" id="confirmCodeInput" value="">
                <input type="hidden" name="target_status" id="confirmTargetStatusInput" value="">
                <input type="hidden" name="target_payment" id="confirmTargetPaymentInput" value="">
                <input type="hidden" name="redirect_query" value="<?= antreanE($_SERVER['QUERY_STRING'] ?? '') ?>">

                <div class="modal-body text-center p-4">
                    <button type="button" class="btn-close position-absolute top-0 end-0 m-3" data-bs-dismiss="modal" aria-label="Tutup"></button>

                    <!-- Bubble Icon Dinamis -->
                    <div class="confirm-icon-bubble mx-auto mb-3" id="confirmIconWrapper">
                        <i class="bi bi-question-circle" id="confirmIcon"></i>
                    </div>

                    <h5 class="fw-bold text-dark mb-1" id="modalKonfirmasiLabel">Konfirmasi Tindakan</h5>
                    <p class="text-muted small mb-3 px-2" id="confirmMessageText">
                        Apakah Anda yakin ingin memproses transaksi ini?
                    </p>

                    <!-- Box Rincian Transaksi -->
                    <div class="confirm-info-box text-start p-3 mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-1.5">
                            <span class="text-muted small">Kode Transaksi:</span>
                            <span class="fw-bold text-dark font-monospace small" id="confirmCodeText">-</span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mb-1.5">
                            <span class="text-muted small">Nama Pelanggan:</span>
                            <strong class="text-dark small text-truncate" style="max-width: 200px;" id="confirmCustomerText">-</strong>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="text-muted small">Total Tagihan:</span>
                            <span class="fw-bold text-dark small" id="confirmTotalText">-</span>
                        </div>
                        <div class="confirm-badge-alert p-2 rounded-2 small" id="confirmBadgeAlert">
                            <i class="bi bi-info-circle me-1" id="confirmAlertIcon"></i>
                            <span id="confirmBadgeText">-</span>
                        </div>
                    </div>

                    <!-- Tombol Aksi -->
                    <div class="d-flex gap-2 justify-content-center pt-1">
                        <button type="button" class="btn btn-light border px-4 flex-grow-1" data-bs-dismiss="modal">
                            Batal
                        </button>
                        <button type="submit" class="btn btn-primary px-4 flex-grow-1 fw-semibold" id="confirmSubmitBtn">
                            Konfirmasi
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const modalTambahEl = document.getElementById('modalTambahData');
    const formTambah = document.getElementById('formTambahData');
    const btnSubmit = document.getElementById('btnTambahSubmit');
    const btnBatal = document.getElementById('btnTambahBatal');
    const btnCloseTop = document.getElementById('btnTambahCloseTop');

    const customerSelect = document.getElementById('addCustomerSelect');
    const customerSearch = document.getElementById('searchCustomerBox');
    const btnClearCustomerSearch = document.getElementById('btnClearCustomerSearch');
    const customerMatchHint = document.getElementById('customerMatchHint');
    const customerHidden = document.getElementById('addCustomer');
    const phoneInput = document.getElementById('addPhone');

    const packageSelect = document.getElementById('addPackage');
    const weightInput = document.getElementById('addWeight');
    const dateInInput = document.getElementById('addDateIn');

    const previewRateBadge = document.getElementById('previewRateBadge');
    const previewDeadlineText = document.getElementById('previewDeadlineText');
    const previewTotalText = document.getElementById('previewTotalText');

    function syncCustomerSelection() {
        if (!customerSelect) return;
        const selected = customerSelect.options[customerSelect.selectedIndex];
        if (selected && selected.value) {
            const phone = selected.getAttribute('data-phone') || '';
            const name = selected.getAttribute('data-name') || '';
            if (phoneInput) phoneInput.value = phone;
            if (customerHidden) customerHidden.value = name;
        } else {
            if (phoneInput) phoneInput.value = '';
            if (customerHidden) customerHidden.value = '';
        }
    }

    if (customerSelect) {
        customerSelect.addEventListener('change', function () {
            syncCustomerSelection();
            isTambahDirty = true;
        });
        syncCustomerSelection();
    }

    if (customerSearch && customerSelect) {
        customerSearch.addEventListener('input', function () {
            const query = this.value.trim().toLowerCase();
            let matches = 0;
            if (btnClearCustomerSearch) {
                btnClearCustomerSearch.style.display = query !== '' ? 'inline-block' : 'none';
            }

            for (let i = 0; i < customerSelect.options.length; i++) {
                const opt = customerSelect.options[i];
                if (!opt.value) continue;
                const keywords = (opt.getAttribute('data-keywords') || '').toLowerCase();
                const text = opt.textContent.toLowerCase();
                const isMatch = query === '' || keywords.includes(query) || text.includes(query);
                opt.hidden = !isMatch;
                opt.disabled = !isMatch;
                if (isMatch) matches++;
            }

            if (customerMatchHint) {
                customerMatchHint.textContent = query !== '' ? (matches + ' ditemukan') : '';
            }
        });

        customerSearch.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                for (let i = 0; i < customerSelect.options.length; i++) {
                    const opt = customerSelect.options[i];
                    if (opt.value && !opt.disabled && !opt.hidden) {
                        customerSelect.selectedIndex = i;
                        customerSelect.dispatchEvent(new Event('change'));
                        customerSelect.focus();
                        break;
                    }
                }
            }
        });

        if (btnClearCustomerSearch) {
            btnClearCustomerSearch.addEventListener('click', function () {
                customerSearch.value = '';
                btnClearCustomerSearch.style.display = 'none';
                customerSearch.dispatchEvent(new Event('input'));
                customerSearch.focus();
            });
        }
    }

    let isTambahDirty = false;
    let isSubmitting = false;
    let bsModalTambah = null;

    if (modalTambahEl && typeof bootstrap !== 'undefined') {
        bsModalTambah = new bootstrap.Modal(modalTambahEl, {
            backdrop: 'static',
            keyboard: false
        });
    }

    // Auto buka jika query ?modal=add aktif
    <?php if ($isAddModal): ?>
    if (bsModalTambah) {
        bsModalTambah.show();
    }
    <?php endif; ?>

    // Auto buka jika modal edit aktif
    <?php if ($isEditModal): ?>
    const modalEditEl = document.getElementById('modalEditData');
    if (modalEditEl && typeof bootstrap !== 'undefined') {
        const bsModalEdit = new bootstrap.Modal(modalEditEl, {
            backdrop: 'static',
            keyboard: false
        });
        bsModalEdit.show();
    }
    <?php endif; ?>

    // Hitung estimasi biaya & deadline secara real-time
    function updateTambahPreview() {
        if (!packageSelect || !weightInput || !dateInInput) return;

        const pkg = packageSelect.value || 'Reguler';
        const rate = (pkg === 'Express') ? 14000 : 7000;
        const hours = (pkg === 'Express') ? 12 : 48;
        const weight = parseFloat(weightInput.value) || 0;
        const total = Math.round(weight * rate);

        if (previewRateBadge) {
            previewRateBadge.textContent = 'Tarif: Rp ' + rate.toLocaleString('id-ID') + ' / kg';
        }

        if (previewTotalText) {
            previewTotalText.textContent = 'Rp ' + total.toLocaleString('id-ID');
        }

        if (previewDeadlineText) {
            const dateVal = dateInInput.value;
            if (dateVal) {
                const dateObj = new Date(dateVal);
                if (!isNaN(dateObj.getTime())) {
                    dateObj.setHours(dateObj.getHours() + hours);
                    const formatted = new Intl.DateTimeFormat('id-ID', {
                        weekday: 'short',
                        day: 'numeric',
                        month: 'short',
                        year: 'numeric',
                        hour: '2-digit',
                        minute: '2-digit'
                    }).format(dateObj);
                    previewDeadlineText.textContent = formatted + ' (' + (pkg === 'Express' ? '12 Jam' : '2 Hari') + ')';
                } else {
                    previewDeadlineText.textContent = '-';
                }
            } else {
                previewDeadlineText.textContent = '-';
            }
        }
    }

    if (packageSelect) packageSelect.addEventListener('change', updateTambahPreview);
    if (weightInput) weightInput.addEventListener('input', updateTambahPreview);
    if (dateInInput) dateInInput.addEventListener('input', updateTambahPreview);
    updateTambahPreview();

    // Deteksi perubahan form untuk proteksi data tidak sengaja tertutup
    if (formTambah) {
        formTambah.addEventListener('input', function () {
            isTambahDirty = true;
        });
        formTambah.addEventListener('change', function () {
            isTambahDirty = true;
        });
    }

    function cleanUrlModalParam() {
        if (window.location.search.includes('modal=')) {
            const url = new URL(window.location.href);
            url.searchParams.delete('modal');
            url.searchParams.delete('code');
            window.history.replaceState({}, document.title, url.toString());
        }
    }

    function attemptCloseTambahModal() {
        if (isTambahDirty && !isSubmitting) {
            const confirmed = window.confirm('Ada perubahan data yang belum disimpan. Yakin ingin membatalkan dan menutup form?');
            if (confirmed) {
                isTambahDirty = false;
                formTambah.reset();
                if (customerSearch) {
                    customerSearch.value = '';
                    customerSearch.dispatchEvent(new Event('input'));
                }
                syncCustomerSelection();
                formTambah.classList.remove('was-validated');
                updateTambahPreview();
                if (bsModalTambah) bsModalTambah.hide();
                cleanUrlModalParam();
            }
        } else {
            isTambahDirty = false;
            if (bsModalTambah) bsModalTambah.hide();
            cleanUrlModalParam();
        }
    }

    if (btnBatal) {
        btnBatal.addEventListener('click', attemptCloseTambahModal);
    }
    if (btnCloseTop) {
        btnCloseTop.addEventListener('click', attemptCloseTambahModal);
    }

    // Keyboard Escape handling
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            if (modalTambahEl && modalTambahEl.classList.contains('show')) {
                attemptCloseTambahModal();
            }
        }
    });

    // Auto fokus field pencarian / nama pelanggan ketika modal terbuka
    if (modalTambahEl) {
        modalTambahEl.addEventListener('shown.bs.modal', function () {
            if (customerSearch) {
                customerSearch.focus();
            } else if (customerSelect) {
                customerSelect.focus();
            }
        });
    }

    // Validasi form & cegah submit ganda
    if (formTambah) {
        formTambah.addEventListener('submit', function (e) {
            if (!formTambah.checkValidity()) {
                e.preventDefault();
                e.stopPropagation();
                formTambah.classList.add('was-validated');
                return;
            }

            if (isSubmitting) {
                e.preventDefault();
                return;
            }

            isSubmitting = true;
            if (btnSubmit) {
                btnSubmit.disabled = true;
                btnSubmit.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Menyimpan Data...';
            }
            if (btnBatal) {
                btnBatal.disabled = true;
            }
            if (btnCloseTop) {
                btnCloseTop.disabled = true;
            }
        });
    }

    // ==========================================
    // MODAL KONFIRMASI AKSI MODERN
    // (Hapus Data, Ubah Selesai, Ubah Lunas)
    // ==========================================
    const modalConfirmEl = document.getElementById('modalKonfirmasiAksi');
    const formConfirm = document.getElementById('formKonfirmasiAksi');
    const confirmActionInput = document.getElementById('confirmActionInput');
    const confirmCodeInput = document.getElementById('confirmCodeInput');
    const confirmTargetStatusInput = document.getElementById('confirmTargetStatusInput');
    const confirmTargetPaymentInput = document.getElementById('confirmTargetPaymentInput');
    const modalConfirmTitle = document.getElementById('modalKonfirmasiLabel');
    const confirmMessage = document.getElementById('confirmMessageText');
    const confirmCodeText = document.getElementById('confirmCodeText');
    const confirmCustomerText = document.getElementById('confirmCustomerText');
    const confirmTotalText = document.getElementById('confirmTotalText');
    const confirmBadgeText = document.getElementById('confirmBadgeText');
    const confirmBadgeAlert = document.getElementById('confirmBadgeAlert');
    const confirmIconWrapper = document.getElementById('confirmIconWrapper');
    const confirmIcon = document.getElementById('confirmIcon');
    const confirmAlertIcon = document.getElementById('confirmAlertIcon');
    const confirmSubmitBtn = document.getElementById('confirmSubmitBtn');

    let bsModalConfirm = null;
    if (modalConfirmEl && typeof bootstrap !== 'undefined') {
        bsModalConfirm = new bootstrap.Modal(modalConfirmEl, {
            backdrop: 'static',
            keyboard: true
        });
    }

    document.querySelectorAll('.btn-action-confirm').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            const action = this.getAttribute('data-action') || '';
            const code = this.getAttribute('data-code') || '';
            const target = this.getAttribute('data-target') || '';
            const title = this.getAttribute('data-title') || 'Konfirmasi Aksi';
            const customer = this.getAttribute('data-customer') || '-';
            const total = this.getAttribute('data-total') || '-';
            const badge = this.getAttribute('data-badge') || '';
            const type = this.getAttribute('data-type') || '';

            if (confirmActionInput) confirmActionInput.value = action;
            if (confirmCodeInput) confirmCodeInput.value = code;
            if (confirmTargetStatusInput) confirmTargetStatusInput.value = (action === 'toggle_status') ? target : '';
            if (confirmTargetPaymentInput) confirmTargetPaymentInput.value = (action === 'toggle_payment') ? target : '';

            if (modalConfirmTitle) modalConfirmTitle.textContent = title;
            if (confirmCodeText) confirmCodeText.textContent = code;
            if (confirmCustomerText) confirmCustomerText.textContent = customer;
            if (confirmTotalText) confirmTotalText.textContent = total;
            if (confirmBadgeText) confirmBadgeText.textContent = badge;

            // Reset dynamic classes
            if (confirmIconWrapper) confirmIconWrapper.className = 'confirm-icon-bubble mx-auto mb-3';
            if (confirmBadgeAlert) confirmBadgeAlert.className = 'confirm-badge-alert p-2 rounded-2 small';
            if (confirmSubmitBtn) confirmSubmitBtn.className = 'btn px-4 flex-grow-1 fw-semibold';

            if (type === 'delete') {
                if (confirmIconWrapper) confirmIconWrapper.classList.add('type-delete');
                if (confirmIcon) confirmIcon.className = 'bi bi-trash3-fill';
                if (confirmAlertIcon) confirmAlertIcon.className = 'bi bi-exclamation-triangle-fill text-danger me-1';
                if (confirmBadgeAlert) confirmBadgeAlert.classList.add('alert-delete');
                if (confirmMessage) confirmMessage.textContent = 'Apakah Anda yakin ingin menghapus data transaksi ini secara permanen?';
                if (confirmSubmitBtn) {
                    confirmSubmitBtn.classList.add('btn-danger');
                    confirmSubmitBtn.innerHTML = '<i class="bi bi-trash3 me-1"></i> Ya, Hapus Transaksi';
                }
            } else if (type === 'status-ready') {
                if (confirmIconWrapper) confirmIconWrapper.classList.add('type-status-ready');
                if (confirmIcon) confirmIcon.className = 'bi bi-bag-check-fill';
                if (confirmAlertIcon) confirmAlertIcon.className = 'bi bi-check-circle-fill text-success me-1';
                if (confirmBadgeAlert) confirmBadgeAlert.classList.add('alert-ready');
                if (confirmMessage) confirmMessage.textContent = 'Orderan telah selesai diproses? Status akan otomatis diubah menjadi Siap Diambil dan siap diambil pelanggan.';
                if (confirmSubmitBtn) {
                    confirmSubmitBtn.classList.add('btn-success');
                    confirmSubmitBtn.innerHTML = '<i class="bi bi-bag-check-fill me-1"></i> Ya, Siap Diambil';
                }
            } else if (type === 'status-complete') {
                if (confirmIconWrapper) confirmIconWrapper.classList.add('type-status-complete');
                if (confirmIcon) confirmIcon.className = 'bi bi-check2-all';
                if (confirmAlertIcon) confirmAlertIcon.className = 'bi bi-check-circle-fill text-primary me-1';
                if (confirmBadgeAlert) confirmBadgeAlert.classList.add('alert-complete');
                if (confirmMessage) confirmMessage.textContent = 'Cucian telah diserahkan kepada pelanggan? Transaksi ini akan ditandai rampung dan selesai.';
                if (confirmSubmitBtn) {
                    confirmSubmitBtn.classList.add('btn-primary');
                    confirmSubmitBtn.innerHTML = '<i class="bi bi-check2-circle me-1"></i> Ya, Tandai Selesai';
                }
            } else if (type === 'status-waiting') {
                if (confirmIconWrapper) confirmIconWrapper.classList.add('type-status-waiting');
                if (confirmIcon) confirmIcon.className = 'bi bi-arrow-counterclockwise';
                if (confirmAlertIcon) confirmAlertIcon.className = 'bi bi-info-circle-fill text-secondary me-1';
                if (confirmBadgeAlert) confirmBadgeAlert.classList.add('alert-waiting');
                if (confirmMessage) confirmMessage.textContent = 'Kembalikan status pesanan ini menjadi antrean Menunggu?';
                if (confirmSubmitBtn) {
                    confirmSubmitBtn.classList.add('btn-secondary');
                    confirmSubmitBtn.innerHTML = '<i class="bi bi-arrow-counterclockwise me-1"></i> Ya, Ke Menunggu';
                }
            } else if (type === 'payment-paid') {
                if (confirmIconWrapper) confirmIconWrapper.classList.add('type-payment-paid');
                if (confirmIcon) confirmIcon.className = 'bi bi-cash-coin';
                if (confirmAlertIcon) confirmAlertIcon.className = 'bi bi-check-circle-fill text-success me-1';
                if (confirmBadgeAlert) confirmBadgeAlert.classList.add('alert-paid');
                if (confirmMessage) confirmMessage.textContent = 'Pastikan pembayaran sebesar ' + total + ' telah diterima penuh oleh kasir/admin.';
                if (confirmSubmitBtn) {
                    confirmSubmitBtn.classList.add('btn-success');
                    confirmSubmitBtn.innerHTML = '<i class="bi bi-check2-circle me-1"></i> Ya, Tandai Lunas';
                }
            } else if (type === 'payment-unpaid') {
                if (confirmIconWrapper) confirmIconWrapper.classList.add('type-payment-unpaid');
                if (confirmIcon) confirmIcon.className = 'bi bi-clock-history';
                if (confirmAlertIcon) confirmAlertIcon.className = 'bi bi-exclamation-circle-fill text-warning me-1';
                if (confirmBadgeAlert) confirmBadgeAlert.classList.add('alert-unpaid');
                if (confirmMessage) confirmMessage.textContent = 'Ubah status pembayaran transaksi ini kembali menjadi Belum Lunas?';
                if (confirmSubmitBtn) {
                    confirmSubmitBtn.classList.add('btn-warning', 'text-dark');
                    confirmSubmitBtn.innerHTML = '<i class="bi bi-arrow-repeat me-1"></i> Ya, Ubah Belum Lunas';
                }
            }

            if (bsModalConfirm) {
                bsModalConfirm.show();
            }
        });
    });

    if (formConfirm) {
        formConfirm.addEventListener('submit', function () {
            if (confirmSubmitBtn) {
                confirmSubmitBtn.disabled = true;
                confirmSubmitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Memproses...';
            }
        });
    }
});
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>