<?php
/**
 * pelanggan.php — Manajemen Data Pelanggan / User (CRUD)
 *
 * Mengelola data pelanggan terdaftar: daftar pelanggan, tambah baru,
 * lihat rincian & riwayat transaksi, edit data, serta hapus pelanggan
 * dengan otorisasi admin dan perlindungan integritas data.
 */

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Proteksi Otorisasi: Hanya admin yang boleh mengakses
if (empty($_SESSION['admin_logged_in'])) {
    header('Location: ../login.php');
    exit;
}

require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/init.php';

$pageTitle = 'Manajemen Pelanggan';

// -------------------------------------------------------------
// 1. PROSES AKSI BACKEND (POST: Tambah, Edit, Hapus)
// -------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ==========================================
    // AKSI 1: TAMBAH PELANGGAN BARU
    // ==========================================
    if ($action === 'add_customer') {
        $nama     = trim($_POST['nama'] ?? '');
        $no_hp    = trim($_POST['no_hp'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $alamat   = trim($_POST['alamat'] ?? '');

        $errors = [];

        // Validasi Nama
        if ($nama === '') {
            $errors[] = 'Nama lengkap pelanggan wajib diisi.';
        } elseif (mb_strlen($nama) > 100) {
            $errors[] = 'Nama pelanggan maksimal 100 karakter.';
        }

        // Validasi No. HP
        if ($no_hp === '') {
            $errors[] = 'Nomor WhatsApp / HP wajib diisi.';
        } elseif (!preg_match('/^[0-9+\s\-]{8,20}$/', $no_hp)) {
            $errors[] = 'Format nomor HP tidak valid (gunakan 8–20 digit angka/tanda +).';
        }

        // Validasi Email
        if ($email === '') {
            $errors[] = 'Alamat email wajib diisi.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Format alamat email tidak valid.';
        } elseif (mb_strlen($email) > 100) {
            $errors[] = 'Alamat email maksimal 100 karakter.';
        }

        // Validasi Password
        if ($password === '') {
            $errors[] = 'Password akun pelanggan wajib diisi.';
        } elseif (strlen($password) < 8) {
            $errors[] = 'Password minimal harus 8 karakter.';
        }

        // Cek Keunikan Email & No HP di Database
        if (empty($errors)) {
            try {
                $checkStmt = $pdo->prepare('SELECT id_pelanggan, email, no_hp FROM pelanggan WHERE email = ? OR no_hp = ? LIMIT 1');
                $checkStmt->execute([$email, $no_hp]);
                $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);

                if ($existing) {
                    if (strcasecmp($existing['email'], $email) === 0) {
                        $errors[] = "Email '{$email}' sudah terdaftar pada pelanggan lain.";
                    }
                    if ($existing['no_hp'] === $no_hp) {
                        $errors[] = "Nomor HP '{$no_hp}' sudah terdaftar pada pelanggan lain.";
                    }
                }
            } catch (PDOException $e) {
                error_log('Gagal memeriksa keunikan pelanggan: ' . $e->getMessage());
                $errors[] = 'Terjadi kesalahan sistem saat memeriksa data pelanggan.';
            }
        }

        // Simpan Data jika tidak ada error
        if (empty($errors)) {
            try {
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                $insertStmt = $pdo->prepare('
                    INSERT INTO pelanggan (nama, no_hp, email, password, alamat, created_at, updated_at)
                    VALUES (?, ?, ?, ?, ?, NOW(), NOW())
                ');
                $insertStmt->execute([
                    $nama,
                    $no_hp,
                    $email,
                    $hashedPassword,
                    $alamat !== '' ? $alamat : null
                ]);

                flash("Pelanggan baru '{$nama}' berhasil ditambahkan ke sistem.", 'success', 'Pelanggan Ditambahkan');
                header('Location: pelanggan.php');
                exit;
            } catch (PDOException $e) {
                error_log('Gagal menambah pelanggan: ' . $e->getMessage());
                if ($e->getCode() === '23000' || ($e->errorInfo[1] ?? 0) == 1062) {
                    if (stripos($e->getMessage(), 'no_hp') !== false) {
                        $errors[] = "Nomor WhatsApp / HP '{$no_hp}' sudah terdaftar pada pelanggan lain.";
                    } else {
                        $errors[] = "Email '{$email}' sudah terdaftar pada pelanggan lain.";
                    }
                } else {
                    $errors[] = 'Gagal menyimpan pelanggan: ' . $e->getMessage();
                }
            }
        }

        // Simpan error ke session agar form bisa menampilkan pesan kesalahan
        $_SESSION['customer_modal_error'] = [
            'action' => 'add_customer',
            'errors' => $errors,
            'post'   => $_POST
        ];
        flash($errors, 'danger', 'Gagal Menambah Pelanggan');

        header('Location: pelanggan.php?modal=add');
        exit;
    }

    // ==========================================
    // AKSI 2: EDIT DATA PELANGGAN
    // ==========================================
    if ($action === 'edit_customer') {
        $id_pelanggan = (int)($_POST['id_pelanggan'] ?? 0);
        $nama         = trim($_POST['nama'] ?? '');
        $no_hp        = trim($_POST['no_hp'] ?? '');
        $email        = trim($_POST['email'] ?? '');
        $password     = $_POST['password'] ?? '';
        $alamat       = trim($_POST['alamat'] ?? '');

        $errors = [];

        if ($id_pelanggan <= 0) {
            flash('ID pelanggan tidak valid.', 'danger');
            header('Location: pelanggan.php');
            exit;
        }

        // Periksa apakah pelanggan ada
        $checkCust = $pdo->prepare('SELECT id_pelanggan, nama FROM pelanggan WHERE id_pelanggan = ? LIMIT 1');
        $checkCust->execute([$id_pelanggan]);
        $targetCustomer = $checkCust->fetch(PDO::FETCH_ASSOC);

        if (!$targetCustomer) {
            flash('Data pelanggan tidak ditemukan.', 'danger');
            header('Location: pelanggan.php');
            exit;
        }

        // Validasi Nama
        if ($nama === '') {
            $errors[] = 'Nama lengkap pelanggan wajib diisi.';
        } elseif (mb_strlen($nama) > 100) {
            $errors[] = 'Nama pelanggan maksimal 100 karakter.';
        }

        // Validasi No. HP
        if ($no_hp === '') {
            $errors[] = 'Nomor WhatsApp / HP wajib diisi.';
        } elseif (!preg_match('/^[0-9+\s\-]{8,20}$/', $no_hp)) {
            $errors[] = 'Format nomor HP tidak valid (gunakan 8–20 digit angka/tanda +).';
        }

        // Validasi Email
        if ($email === '') {
            $errors[] = 'Alamat email wajib diisi.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Format alamat email tidak valid.';
        } elseif (mb_strlen($email) > 100) {
            $errors[] = 'Alamat email maksimal 100 karakter.';
        }

        // Validasi Password Baru (opsional)
        if ($password !== '' && strlen($password) < 8) {
            $errors[] = 'Password baru minimal harus 8 karakter (atau kosongkan jika tidak ingin diubah).';
        }

        // Cek Keunikan Email & No HP terhadap pelanggan LAIN
        if (empty($errors)) {
            try {
                $checkStmt = $pdo->prepare('
                    SELECT id_pelanggan, email, no_hp
                    FROM pelanggan
                    WHERE (email = ? OR no_hp = ?) AND id_pelanggan != ?
                    LIMIT 1
                ');
                $checkStmt->execute([$email, $no_hp, $id_pelanggan]);
                $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);

                if ($existing) {
                    if (strcasecmp($existing['email'], $email) === 0) {
                        $errors[] = "Email '{$email}' sudah digunakan oleh pelanggan lain.";
                    }
                    if ($existing['no_hp'] === $no_hp) {
                        $errors[] = "Nomor HP '{$no_hp}' sudah digunakan oleh pelanggan lain.";
                    }
                }
            } catch (PDOException $e) {
                error_log('Gagal validasi keunikan pelanggan: ' . $e->getMessage());
                $errors[] = 'Terjadi kesalahan sistem saat memeriksa data pelanggan.';
            }
        }

        // Update Data jika tidak ada error
        if (empty($errors)) {
            try {
                if ($password !== '') {
                    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                    $updateStmt = $pdo->prepare('
                        UPDATE pelanggan
                        SET nama = ?, no_hp = ?, email = ?, password = ?, alamat = ?, updated_at = NOW()
                        WHERE id_pelanggan = ?
                    ');
                    $updateStmt->execute([$nama, $no_hp, $email, $hashedPassword, $alamat !== '' ? $alamat : null, $id_pelanggan]);
                } else {
                    $updateStmt = $pdo->prepare('
                        UPDATE pelanggan
                        SET nama = ?, no_hp = ?, email = ?, alamat = ?, updated_at = NOW()
                        WHERE id_pelanggan = ?
                    ');
                    $updateStmt->execute([$nama, $no_hp, $email, $alamat !== '' ? $alamat : null, $id_pelanggan]);
                }

                flash("Data pelanggan '{$nama}' berhasil diperbarui.", 'success', 'Pelanggan Diperbarui');
                header('Location: pelanggan.php');
                exit;
            } catch (PDOException $e) {
                error_log('Gagal memperbarui pelanggan: ' . $e->getMessage());
                if ($e->getCode() === '23000' || ($e->errorInfo[1] ?? 0) == 1062) {
                    if (stripos($e->getMessage(), 'no_hp') !== false) {
                        $errors[] = "Nomor WhatsApp / HP '{$no_hp}' sudah digunakan oleh pelanggan lain.";
                    } else {
                        $errors[] = "Email '{$email}' sudah digunakan oleh pelanggan lain.";
                    }
                } else {
                    $errors[] = 'Gagal menyimpan perubahan: ' . $e->getMessage();
                }
            }
        }

        $_SESSION['customer_modal_error'] = [
            'action' => 'edit_customer',
            'errors' => $errors,
            'post'   => $_POST
        ];
        flash($errors, 'danger', 'Gagal Memperbarui Pelanggan');

        header('Location: pelanggan.php?modal=edit&id=' . $id_pelanggan);
        exit;
    }

    // ==========================================
    // AKSI 3: HAPUS PELANGGAN
    // ==========================================
    if ($action === 'delete_customer') {
        $id_pelanggan = (int)($_POST['id_pelanggan'] ?? 0);

        if ($id_pelanggan <= 0) {
            flash('ID pelanggan tidak valid.', 'danger', 'Gagal Menghapus');
            header('Location: pelanggan.php');
            exit;
        }

        // Ambil data pelanggan yang akan dihapus
        $stmtFind = $pdo->prepare('SELECT id_pelanggan, nama, email FROM pelanggan WHERE id_pelanggan = ? LIMIT 1');
        $stmtFind->execute([$id_pelanggan]);
        $cust = $stmtFind->fetch(PDO::FETCH_ASSOC);

        if (!$cust) {
            flash('Pelanggan tidak ditemukan.', 'danger', 'Gagal Menghapus');
            header('Location: pelanggan.php');
            exit;
        }

        // Otorisasi & Perlindungan Akun Sistem:
        // Cegah admin menghapus akun dengan email admin sendiri jika terhubung
        $currentAdminUser = $_SESSION['admin_username'] ?? '';
        if ($currentAdminUser !== '' && strcasecmp($cust['email'], $currentAdminUser) === 0) {
            flash('Akses ditolak: Anda tidak dapat menghapus akun Anda sendiri.', 'danger', 'Akses Ditolak');
            header('Location: pelanggan.php');
            exit;
        }

        try {
            // Catat info transaksi pelanggan untuk laporan
            $stmtCount = $pdo->prepare('SELECT COUNT(*) FROM transaksi WHERE pelanggan_id = ?');
            $stmtCount->execute([$id_pelanggan]);
            $transCount = (int)$stmtCount->fetchColumn();

            // Eksekusi penghapusan pelanggan
            // Catatan: fk_transaksi_pelanggan memiliki aturan ON DELETE SET NULL,
            // sehingga riwayat transaksi tetap aman tersimpan tanpa menyebabkan orphaned errors.
            $delStmt = $pdo->prepare('DELETE FROM pelanggan WHERE id_pelanggan = ?');
            $delStmt->execute([$id_pelanggan]);

            $transInfo = $transCount > 0 ? " ({$transCount} riwayat transaksi diarsipkan)" : '';
            flash("Pelanggan '{$cust['nama']}' berhasil dihapus{$transInfo}.", 'success', 'Pelanggan Dihapus');
        } catch (PDOException $e) {
            error_log('Gagal menghapus pelanggan: ' . $e->getMessage());
            flash('Gagal menghapus pelanggan: ' . $e->getMessage(), 'danger', 'Gagal Menghapus');
        }

        header('Location: pelanggan.php');
        exit;
    }
}

// -------------------------------------------------------------
// 2. QUERY DATA & STATISTIK PELANGGAN
// -------------------------------------------------------------
$searchKeyword = trim($_GET['q'] ?? '');
$sortOption    = trim($_GET['sort'] ?? 'terbaru');

// Query statistik ringkasan
$totalCustomers = 0;
$activeCustomers = 0;
$newCustomersThisMonth = 0;
$totalTransactionsCount = 0;

try {
    // 1. Total semua pelanggan
    $stmt = $pdo->query('SELECT COUNT(*) FROM pelanggan');
    $totalCustomers = (int) $stmt->fetchColumn();

    // 2. Pelanggan dengan pesanan aktif yang sedang diproses
    $stmtActive = $pdo->query("
        SELECT COUNT(DISTINCT pelanggan_id)
        FROM transaksi
        WHERE status NOT IN ('completed', 'cancelled')
          AND pelanggan_id IS NOT NULL
    ");
    $activeCustomers = (int) $stmtActive->fetchColumn();

    // 3. Pelanggan baru bulan ini
    $stmtNew = $pdo->query("
        SELECT COUNT(*)
        FROM pelanggan
        WHERE created_at >= DATE_FORMAT(NOW(), '%Y-%m-01 00:00:00')
    ");
    $newCustomersThisMonth = (int) $stmtNew->fetchColumn();

    // 4. Total pesanan laundry yang terhubung
    $stmtTrans = $pdo->query('SELECT COUNT(*) FROM transaksi WHERE pelanggan_id IS NOT NULL');
    $totalTransactionsCount = (int) $stmtTrans->fetchColumn();
} catch (PDOException $e) {
    error_log('Gagal memuat statistik pelanggan: ' . $e->getMessage());
}

// Query daftar pelanggan dengan agregasi pesanan
$querySql = "
    SELECT
        p.id_pelanggan,
        p.nama,
        p.no_hp,
        p.email,
        p.alamat,
        p.created_at,
        p.updated_at,
        COUNT(t.id) AS total_pesanan,
        COALESCE(SUM(CASE WHEN t.pembayaran = 'lunas' THEN t.total_biaya ELSE 0 END), 0) AS total_pengeluaran,
        COUNT(CASE WHEN t.status NOT IN ('completed', 'cancelled') THEN 1 END) AS pesanan_aktif
    FROM pelanggan p
    LEFT JOIN transaksi t ON t.pelanggan_id = p.id_pelanggan
";

$params = [];

if ($searchKeyword !== '') {
    $querySql .= "
        WHERE (
            p.nama LIKE ? OR
            p.no_hp LIKE ? OR
            p.email LIKE ? OR
            p.alamat LIKE ?
        )
    ";
    $searchWildcard = '%' . $searchKeyword . '%';
    $params = [$searchWildcard, $searchWildcard, $searchWildcard, $searchWildcard];
}

$querySql .= ' GROUP BY p.id_pelanggan, p.nama, p.no_hp, p.email, p.alamat, p.created_at, p.updated_at';

// Penentuan Pengurutan Data
switch ($sortOption) {
    case 'nama_asc':
        $querySql .= ' ORDER BY p.nama ASC';
        break;
    case 'nama_desc':
        $querySql .= ' ORDER BY p.nama DESC';
        break;
    case 'order_terbanyak':
        $querySql .= ' ORDER BY total_pesanan DESC, p.id_pelanggan DESC';
        break;
    case 'terlama':
        $querySql .= ' ORDER BY p.created_at ASC, p.id_pelanggan ASC';
        break;
    case 'terbaru':
    default:
        $querySql .= ' ORDER BY p.created_at DESC, p.id_pelanggan DESC';
        $sortOption = 'terbaru';
        break;
}

$stmtCustomersList = $pdo->prepare($querySql);
$stmtCustomersList->execute($params);
$allCustomers = $stmtCustomersList->fetchAll(PDO::FETCH_ASSOC);

// Pagination
$perPage = 10;
$totalRows = count($allCustomers);
$totalPages = max(1, (int) ceil($totalRows / $perPage));
$page = max(1, min($totalPages, (int) ($_GET['page'] ?? 1)));
$offset = ($page - 1) * $perPage;
$pageCustomers = array_slice($allCustomers, $offset, $perPage);

// Ambil riwayat pesanan terbaru untuk detail modal
$recentOrdersByCustomer = [];
if (!empty($pageCustomers)) {
    $custIds = array_column($pageCustomers, 'id_pelanggan');
    if (!empty($custIds)) {
        $inPlaceholders = implode(',', array_fill(0, count($custIds), '?'));
        try {
            $stmtRecent = $pdo->prepare("
                SELECT id, kode_transaksi, pelanggan_id, nama_pelanggan, paket, berat, total_biaya, status, pembayaran, tanggal_masuk, deadline
                FROM transaksi
                WHERE pelanggan_id IN ({$inPlaceholders})
                ORDER BY tanggal_masuk DESC, id DESC
            ");
            $stmtRecent->execute($custIds);
            $allRecent = $stmtRecent->fetchAll(PDO::FETCH_ASSOC);
            foreach ($allRecent as $ro) {
                $cId = (int)$ro['pelanggan_id'];
                if (!isset($recentOrdersByCustomer[$cId])) {
                    $recentOrdersByCustomer[$cId] = [];
                }
                if (count($recentOrdersByCustomer[$cId]) < 5) {
                    $recentOrdersByCustomer[$cId][] = $ro;
                }
            }
        } catch (PDOException $e) {
            error_log('Gagal memuat pesanan pelanggan: ' . $e->getMessage());
        }
    }
}

// Penanganan error modal dari sesi
$modalError = $_SESSION['customer_modal_error'] ?? null;
unset($_SESSION['customer_modal_error']);

$modal = $_GET['modal'] ?? '';
$editId = (int)($_GET['id'] ?? 0);
$editCustomerData = null;

if ($modal === 'edit' && $editId > 0) {
    foreach ($allCustomers as $c) {
        if ((int)$c['id_pelanggan'] === $editId) {
            $editCustomerData = $c;
            break;
        }
    }
}

$isAddModal  = ($modal === 'add') || (($modalError['action'] ?? '') === 'add_customer' && !empty($modalError['errors']));
$isEditModal = ($modal === 'edit' && $editCustomerData !== null) || (($modalError['action'] ?? '') === 'edit_customer' && !empty($modalError['errors']));

$addPostData  = (($modalError['action'] ?? '') === 'add_customer') ? ($modalError['post'] ?? []) : [];
$editPostData = (($modalError['action'] ?? '') === 'edit_customer') ? ($modalError['post'] ?? []) : [];

require __DIR__ . '/../includes/header.php';
?>

<style>
.customer-page {
    padding: 24px;
    color: #172033;
}
.customer-heading {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 16px;
    flex-wrap: wrap;
    margin-bottom: 24px;
}
.customer-heading h1 {
    font-size: 28px;
    font-weight: 800;
    margin: 0 0 6px;
    letter-spacing: -.5px;
    color: #111e2f;
}
.customer-muted {
    color: #64748b;
    font-size: 14px;
}
.customer-add-btn {
    background: var(--brand, #267b72);
    border: 1px solid var(--brand, #267b72);
    color: #ffffff;
    font-size: 13.5px;
    font-weight: 700;
    padding: 10px 18px;
    border-radius: 10px;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    box-shadow: 0 4px 14px rgba(38, 123, 114, .25);
    transition: all .15s ease;
    text-decoration: none;
    cursor: pointer;
}
.customer-add-btn:hover, .customer-add-btn:focus {
    background: var(--brand-dark, #1f665f);
    border-color: var(--brand-dark, #1f665f);
    color: #ffffff;
    transform: translateY(-1px);
    box-shadow: 0 6px 18px rgba(38, 123, 114, .32);
}
.customer-stats {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 16px;
    margin-bottom: 24px;
}
.customer-stat {
    background: #ffffff;
    border: 1px solid #e7ebf2;
    border-radius: 14px;
    padding: 18px 20px;
    box-shadow: 0 2px 6px rgba(18, 38, 63, .03);
    transition: transform .15s ease, box-shadow .15s ease;
}
.customer-stat:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(18, 38, 63, .06);
}
.customer-stat-top {
    display: flex;
    justify-content: space-between;
    align-items: center;
    color: #64748b;
    font-size: 12px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .5px;
}
.customer-stat strong {
    display: block;
    font-size: 28px;
    font-weight: 800;
    color: #0f172a;
    margin: 8px 0 4px;
    line-height: 1.15;
}
.customer-stat small {
    color: #94a3b8;
    font-size: 11.5px;
}
.customer-stat-icon {
    width: 38px;
    height: 38px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
}
.customer-panel {
    background: #ffffff;
    border: 1px solid #e7ebf2;
    border-radius: 16px;
    box-shadow: 0 4px 18px rgba(18, 38, 63, .04);
    overflow: hidden;
}
.customer-panel-head {
    padding: 18px 22px;
    border-bottom: 1px solid #edf0f5;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 16px;
    flex-wrap: wrap;
    background: #fbfcfd;
}
.customer-panel-head h2 {
    font-size: 16.5px;
    font-weight: 800;
    margin: 0;
    color: #1e293b;
    display: flex;
    align-items: center;
    gap: 8px;
}
.customer-count-badge {
    background: #e2e8f0;
    color: #475569;
    font-size: 11.5px;
    font-weight: 700;
    padding: 3px 8px;
    border-radius: 20px;
}
.customer-filters {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}
.customer-search-group {
    position: relative;
    width: 280px;
}
.customer-search-icon {
    position: absolute;
    left: 12px;
    top: 50%;
    transform: translateY(-50%);
    color: #94a3b8;
    font-size: 13.5px;
    pointer-events: none;
}
.customer-search-input {
    width: 100%;
    padding: 7.5px 12px 7.5px 34px;
    border-radius: 10px;
    border: 1px solid #cbd5e1;
    font-size: 12.5px;
    background: #ffffff;
    color: #1e293b;
    outline: none;
    transition: all .15s ease;
}
.customer-search-input:focus {
    border-color: var(--brand, #267b72);
    box-shadow: 0 0 0 3px rgba(38, 123, 114, .15);
}
.customer-select-filter {
    padding: 7.5px 12px;
    border-radius: 10px;
    border: 1px solid #cbd5e1;
    font-size: 12.5px;
    background: #ffffff;
    color: #1e293b;
    outline: none;
    cursor: pointer;
    transition: all .15s ease;
}
.customer-select-filter:focus {
    border-color: var(--brand, #267b72);
    box-shadow: 0 0 0 3px rgba(38, 123, 114, .15);
}
.customer-reset-btn {
    padding: 7.5px 12px;
    border-radius: 10px;
    border: 1px solid #e2e8f0;
    background: #f1f5f9;
    color: #475569;
    font-size: 12px;
    font-weight: 600;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    transition: all .15s ease;
}
.customer-reset-btn:hover {
    background: #e2e8f0;
    color: #1e293b;
}
.customer-table-wrap {
    width: 100%;
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
}
.customer-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
    font-size: 13px;
    color: #334155;
}
.customer-table th {
    background: #f8fafc;
    color: #64748b;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .6px;
    padding: 12px 16px;
    border-bottom: 1px solid #e2e8f0;
    white-space: nowrap;
}
.customer-table td {
    padding: 14px 16px;
    border-bottom: 1px solid #f1f5f9;
    vertical-align: middle;
}
.customer-table tbody tr {
    transition: background-color .15s ease;
}
.customer-table tbody tr:hover {
    background-color: #f8fafc;
}
.customer-profile-cell {
    display: flex;
    align-items: center;
    gap: 12px;
}
.cust-avatar {
    width: 38px;
    height: 38px;
    border-radius: 50%;
    background: linear-gradient(135deg, #e7f5f2 0%, #d1fae5 100%);
    color: #0f766e;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 800;
    font-size: 13px;
    flex-shrink: 0;
    border: 2px solid #ffffff;
    box-shadow: 0 2px 6px rgba(15, 118, 110, .15);
}
.cust-name {
    font-weight: 700;
    color: #0f172a;
    font-size: 13.5px;
    line-height: 1.3;
}
.cust-id-pill {
    display: inline-block;
    font-size: 10px;
    font-weight: 700;
    color: #64748b;
    background: #f1f5f9;
    padding: 1px 6px;
    border-radius: 4px;
    margin-top: 2px;
    font-family: monospace;
}
.wa-link {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    color: #15803d;
    background: #f0fdf4;
    border: 1px solid #bbf7d0;
    padding: 3px 9px;
    border-radius: 20px;
    font-size: 11.5px;
    font-weight: 700;
    text-decoration: none;
    transition: all .15s ease;
}
.wa-link:hover {
    background: #16a34a;
    color: #ffffff;
    border-color: #16a34a;
}
.email-text {
    color: #64748b;
    font-size: 12px;
    margin-top: 3px;
    display: flex;
    align-items: center;
    gap: 4px;
}
.order-badge-pill {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 3px 9px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 700;
    background: #eff6ff;
    color: #1d4ed8;
    border: 1px solid #bfdbfe;
}
.active-order-pill {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 2px 7px;
    border-radius: 12px;
    font-size: 10px;
    font-weight: 700;
    background: #fff7ed;
    color: #c2410c;
    border: 1px solid #fed7aa;
    margin-left: 4px;
}
.action-btns {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    white-space: nowrap;
}
.btn-action-view {
    background: #f0fdf4;
    color: #0f766e;
    border: 1px solid #a7f3d0;
    padding: 4.5px 9px;
    border-radius: 7px;
    font-size: 11.5px;
    font-weight: 600;
    cursor: pointer;
    text-decoration: none;
    transition: all .15s ease;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}
.btn-action-view:hover {
    background: #0f766e;
    color: #ffffff;
    border-color: #0f766e;
}
.btn-action-edit {
    background: #f8fafc;
    color: #334155;
    border: 1px solid #cbd5e1;
    padding: 4.5px 9px;
    border-radius: 7px;
    font-size: 11.5px;
    font-weight: 600;
    cursor: pointer;
    text-decoration: none;
    transition: all .15s ease;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}
.btn-action-edit:hover {
    background: #475569;
    color: #ffffff;
    border-color: #475569;
}
.btn-action-delete {
    background: #fef2f2;
    color: #dc2626;
    border: 1px solid #fecaca;
    padding: 4.5px 9px;
    border-radius: 7px;
    font-size: 11.5px;
    font-weight: 600;
    cursor: pointer;
    transition: all .15s ease;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}
.btn-action-delete:hover {
    background: #dc2626;
    color: #ffffff;
    border-color: #dc2626;
}
.cust-pagination-wrap {
    padding: 16px 22px;
    border-top: 1px solid #edf0f5;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
    background: #fbfcfd;
}
.cust-page-info {
    font-size: 12px;
    color: #64748b;
}

/* Modal Custom Styling */
.customer-modal-custom {
    border: none;
    border-radius: 18px;
    box-shadow: 0 20px 60px rgba(18, 38, 63, .2);
    overflow: hidden;
}
.customer-modal-custom .modal-header {
    padding: 20px 24px;
    border-bottom: 1px solid #edf0f5;
    background: #ffffff;
}
.customer-modal-custom .modal-body {
    padding: 22px 24px;
}
.customer-modal-custom .modal-footer {
    padding: 16px 24px;
    border-top: 1px solid #edf0f5;
    background: #fbfcfd;
}
.customer-modal-custom .form-control,
.customer-modal-custom .form-select {
    min-height: 42px;
    border-radius: 9px;
    border-color: #dce2ec;
    font-size: 13px;
}
.customer-modal-custom .form-control:focus,
.customer-modal-custom .form-select:focus {
    border-color: var(--brand, #267b72);
    box-shadow: 0 0 0 3px rgba(38, 123, 114, .15);
}

/* Detail Modal Specifics */
.detail-header-card {
    background: linear-gradient(135deg, #1b6359 0%, #0d463e 100%);
    color: #ffffff;
    border-radius: 14px;
    padding: 20px;
    margin-bottom: 20px;
}
.detail-avatar-large {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    background: #ffffff;
    color: #11473f;
    font-size: 22px;
    font-weight: 800;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 3px solid rgba(255, 255, 255, .3);
    box-shadow: 0 4px 12px rgba(0, 0, 0, .2);
}
.detail-metrics-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 12px;
    margin-bottom: 20px;
}
.detail-metric-card {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 14px;
    text-align: center;
}
.detail-metric-card strong {
    display: block;
    font-size: 18px;
    font-weight: 800;
    color: #0f172a;
    margin-top: 4px;
}
.detail-metric-card span {
    font-size: 11px;
    color: #64748b;
    font-weight: 600;
}

@media(max-width: 991px) {
    .customer-stats {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}
@media(max-width: 575px) {
    .customer-page {
        padding: 14px;
    }
    .customer-stats {
        grid-template-columns: 1fr 1fr;
        gap: 10px;
    }
    .customer-stat {
        padding: 12px 14px;
    }
    .customer-stat strong {
        font-size: 20px;
    }
    .customer-search-group {
        width: 100%;
    }
    .customer-select-filter {
        width: 100%;
    }
    .detail-metrics-grid {
        grid-template-columns: 1fr;
        gap: 8px;
    }
}
</style>

<div class="customer-page">
    <!-- Header Halaman -->
    <div class="customer-heading">
        <div>
            <h1>Manajemen Pelanggan</h1>
            <div class="customer-muted">Kelola data pelanggan terdaftar, pantau riwayat order, dan tambahkan akun pelanggan baru.</div>
        </div>
        <button type="button" class="customer-add-btn" id="btnOpenTambahModal" data-bs-toggle="modal" data-bs-target="#modalTambahPelanggan">
            <i class="bi bi-person-plus-fill fs-6"></i> Tambah Pelanggan
        </button>
    </div>

    <!-- Alert Kesalahan Modal -->
    <?php if ($modalError && !empty($modalError['errors'])): ?>
        <div class="alert alert-danger alert-dismissible fade show mb-4 d-flex align-items-start gap-2 shadow-sm" role="alert">
            <i class="bi bi-exclamation-triangle-fill fs-5 flex-shrink-0 text-danger mt-1"></i>
            <div class="flex-grow-1">
                <strong class="d-block mb-1">Gagal memproses data pelanggan:</strong>
                <ul class="mb-0 ps-3 small">
                    <?php foreach ($modalError['errors'] as $err): ?>
                        <li><?= e($err) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
        </div>
    <?php endif; ?>

    <!-- Kartu Statistik -->
    <div class="customer-stats">
        <div class="customer-stat">
            <div class="customer-stat-top">
                <span>Total Pelanggan</span>
                <span class="customer-stat-icon icon-blue"><i class="bi bi-people-fill"></i></span>
            </div>
            <strong><?= $totalCustomers ?></strong>
            <small>Terdaftar di database</small>
        </div>

        <div class="customer-stat">
            <div class="customer-stat-top">
                <span>Order Aktif</span>
                <span class="customer-stat-icon icon-orange"><i class="bi bi-hourglass-split"></i></span>
            </div>
            <strong><?= $activeCustomers ?></strong>
            <small>Pelanggan dalam antrean</small>
        </div>

        <div class="customer-stat">
            <div class="customer-stat-top">
                <span>Pelanggan Baru</span>
                <span class="customer-stat-icon icon-green"><i class="bi bi-person-check-fill"></i></span>
            </div>
            <strong><?= $newCustomersThisMonth ?></strong>
            <small>Mendaftar bulan ini</small>
        </div>

        <div class="customer-stat">
            <div class="customer-stat-top">
                <span>Total Transaksi</span>
                <span class="customer-stat-icon icon-purple"><i class="bi bi-receipt-cutoff"></i></span>
            </div>
            <strong><?= $totalTransactionsCount ?></strong>
            <small>Pesanan terhubung</small>
        </div>
    </div>

    <!-- Panel Tabel Pelanggan -->
    <section class="customer-panel">
        <div class="customer-panel-head">
            <h2>
                <span>Daftar Pelanggan</span>
                <span class="customer-count-badge"><?= $totalRows ?> pelanggan</span>
            </h2>

            <form class="customer-filters" method="GET" action="pelanggan.php">
                <div class="customer-search-group">
                    <i class="bi bi-search customer-search-icon"></i>
                    <input
                        type="search"
                        name="q"
                        class="customer-search-input"
                        placeholder="Cari nama, no HP, email, alamat..."
                        value="<?= e($searchKeyword) ?>"
                        autocomplete="off"
                    >
                </div>

                <select name="sort" class="customer-select-filter" onchange="this.form.submit()">
                    <option value="terbaru" <?= $sortOption === 'terbaru' ? 'selected' : '' ?>>Pendaftaran Terbaru</option>
                    <option value="terlama" <?= $sortOption === 'terlama' ? 'selected' : '' ?>>Pendaftaran Terlama</option>
                    <option value="nama_asc" <?= $sortOption === 'nama_asc' ? 'selected' : '' ?>>Nama (A &rarr; Z)</option>
                    <option value="nama_desc" <?= $sortOption === 'nama_desc' ? 'selected' : '' ?>>Nama (Z &rarr; A)</option>
                    <option value="order_terbanyak" <?= $sortOption === 'order_terbanyak' ? 'selected' : '' ?>>Pesanan Terbanyak</option>
                </select>

                <?php if ($searchKeyword !== '' || $sortOption !== 'terbaru'): ?>
                    <a href="pelanggan.php" class="customer-reset-btn" title="Reset filter">
                        <i class="bi bi-arrow-counterclockwise"></i> Reset
                    </a>
                <?php endif; ?>
            </form>
        </div>

        <div class="customer-table-wrap">
            <table class="customer-table">
                <thead>
                    <tr>
                        <th style="min-width: 220px;">Pelanggan</th>
                        <th style="min-width: 210px;">Kontak &amp; Email</th>
                        <th style="min-width: 180px;">Alamat</th>
                        <th style="min-width: 160px;">Total Pesanan</th>
                        <th style="min-width: 140px;">Terdaftar</th>
                        <th style="min-width: 155px; text-align: center;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($pageCustomers)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-5">
                                <div class="py-4 text-muted">
                                    <i class="bi bi-person-x fs-1 d-block mb-2 text-secondary opacity-50"></i>
                                    <h5 class="fw-bold text-dark mb-1">Tidak ada data pelanggan</h5>
                                    <p class="small text-muted mb-3">
                                        <?= $searchKeyword !== '' ? 'Tidak ditemukan pelanggan yang cocok dengan kata kunci pencarian.' : 'Belum ada data pelanggan yang terdaftar di sistem.' ?>
                                    </p>
                                    <?php if ($searchKeyword !== ''): ?>
                                        <a href="pelanggan.php" class="btn btn-outline-secondary btn-sm px-3">
                                            <i class="bi bi-arrow-counterclockwise me-1"></i> Reset Pencarian
                                        </a>
                                    <?php else: ?>
                                        <button type="button" class="btn btn-brand btn-sm px-3" data-bs-toggle="modal" data-bs-target="#modalTambahPelanggan">
                                            <i class="bi bi-person-plus-fill me-1"></i> Tambah Pelanggan Pertama
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($pageCustomers as $cust): ?>
                            <?php
                                $initials = strtoupper(mb_substr(trim($cust['nama']), 0, 2));
                                $waClean = preg_replace('/[^0-9]/', '', $cust['no_hp']);
                                if (strpos($waClean, '0') === 0) {
                                    $waClean = '62' . substr($waClean, 1);
                                }
                                $custOrders = $recentOrdersByCustomer[$cust['id_pelanggan']] ?? [];
                                $ordersJson = htmlspecialchars(json_encode($custOrders), ENT_QUOTES, 'UTF-8');
                            ?>
                            <tr>
                                <td>
                                    <div class="customer-profile-cell">
                                        <div class="cust-avatar"><?= e($initials) ?></div>
                                        <div>
                                            <div class="cust-name"><?= e($cust['nama']) ?></div>
                                            <span class="cust-id-pill">ID #<?= (int)$cust['id_pelanggan'] ?></span>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div>
                                        <?php if (!empty($cust['no_hp'])): ?>
                                            <a href="https://wa.me/<?= e($waClean) ?>" target="_blank" rel="noopener noreferrer" class="wa-link" title="Kirim pesan WhatsApp">
                                                <i class="bi bi-whatsapp"></i> <?= e($cust['no_hp']) ?>
                                            </a>
                                        <?php else: ?>
                                            <span class="text-muted small">-</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="email-text">
                                        <i class="bi bi-envelope"></i> <?= e($cust['email']) ?>
                                    </div>
                                </td>
                                <td>
                                    <?php if (!empty($cust['alamat'])): ?>
                                        <span class="text-truncate d-inline-block" style="max-width: 220px;" title="<?= e($cust['alamat']) ?>">
                                            <i class="bi bi-geo-alt text-muted me-1"></i><?= e($cust['alamat']) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted small fst-italic">Belum diisi</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div>
                                        <span class="order-badge-pill">
                                            <i class="bi bi-receipt"></i> <?= (int)$cust['total_pesanan'] ?> order
                                        </span>
                                        <?php if ((int)$cust['pesanan_aktif'] > 0): ?>
                                            <span class="active-order-pill" title="<?= (int)$cust['pesanan_aktif'] ?> order aktif dalam proses">
                                                <i class="bi bi-hourglass-split"></i> <?= (int)$cust['pesanan_aktif'] ?> aktif
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <?php if ((float)$cust['total_pengeluaran'] > 0): ?>
                                        <div class="small text-muted mt-1" style="font-size: 11px;">
                                            Total: <strong class="text-success"><?= rupiah($cust['total_pengeluaran']) ?></strong>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="text-dark small"><?= formatTanggal($cust['created_at']) ?></span>
                                </td>
                                <td style="text-align: center;">
                                    <div class="action-btns">
                                        <!-- Tombol Detail -->
                                        <button
                                            type="button"
                                            class="btn-action-view btn-customer-detail"
                                            data-id="<?= (int)$cust['id_pelanggan'] ?>"
                                            data-name="<?= e($cust['nama']) ?>"
                                            data-phone="<?= e($cust['no_hp']) ?>"
                                            data-email="<?= e($cust['email']) ?>"
                                            data-address="<?= e($cust['alamat'] ?? '') ?>"
                                            data-created="<?= e(formatTanggal($cust['created_at'])) ?>"
                                            data-total-orders="<?= (int)$cust['total_pesanan'] ?>"
                                            data-active-orders="<?= (int)$cust['pesanan_aktif'] ?>"
                                            data-total-spent="<?= e(rupiah($cust['total_pengeluaran'])) ?>"
                                            data-orders="<?= $ordersJson ?>"
                                            title="Lihat detail & riwayat pelanggan"
                                        >
                                            <i class="bi bi-eye"></i> Detail
                                        </button>

                                        <!-- Tombol Edit -->
                                        <button
                                            type="button"
                                            class="btn-action-edit btn-customer-edit"
                                            data-id="<?= (int)$cust['id_pelanggan'] ?>"
                                            data-name="<?= e($cust['nama']) ?>"
                                            data-phone="<?= e($cust['no_hp']) ?>"
                                            data-email="<?= e($cust['email']) ?>"
                                            data-address="<?= e($cust['alamat'] ?? '') ?>"
                                            title="Edit data pelanggan"
                                        >
                                            <i class="bi bi-pencil-square"></i> Edit
                                        </button>

                                        <!-- Tombol Hapus -->
                                        <button
                                            type="button"
                                            class="btn-action-delete btn-customer-delete"
                                            data-id="<?= (int)$cust['id_pelanggan'] ?>"
                                            data-name="<?= e($cust['nama']) ?>"
                                            data-phone="<?= e($cust['no_hp']) ?>"
                                            data-email="<?= e($cust['email']) ?>"
                                            data-orders-count="<?= (int)$cust['total_pesanan'] ?>"
                                            title="Hapus pelanggan"
                                        >
                                            <i class="bi bi-trash3"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
            <div class="cust-pagination-wrap">
                <div class="cust-page-info">
                    Menampilkan <strong><?= $offset + 1 ?></strong> – <strong><?= min($totalRows, $offset + $perPage) ?></strong> dari <strong><?= $totalRows ?></strong> pelanggan
                </div>
                <nav aria-label="Navigasi halaman pelanggan">
                    <ul class="pagination pagination-sm mb-0">
                        <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">
                                <i class="bi bi-chevron-left"></i>
                            </a>
                        </li>
                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <?php if ($i === 1 || $i === $totalPages || ($i >= $page - 2 && $i <= $page + 2)): ?>
                                <li class="page-item <?= ($i === $page) ? 'active' : '' ?>">
                                    <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"><?= $i ?></a>
                                </li>
                            <?php elseif ($i === $page - 3 || $i === $page + 3): ?>
                                <li class="page-item disabled"><span class="page-link">&hellip;</span></li>
                            <?php endif; ?>
                        <?php endfor; ?>
                        <li class="page-item <?= ($page >= $totalPages) ? 'disabled' : '' ?>">
                            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">
                                <i class="bi bi-chevron-right"></i>
                            </a>
                        </li>
                    </ul>
                </nav>
            </div>
        <?php endif; ?>
    </section>
</div>

<!-- ========================================================
     MODAL 1: TAMBAH PELANGGAN BARU (BOOTSTRAP 5)
     ======================================================== -->
<div class="modal fade" id="modalTambahPelanggan" tabindex="-1" aria-labelledby="modalTambahPelangganLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable" style="max-width: 520px;">
        <div class="modal-content customer-modal-custom">
            <div class="modal-header">
                <div class="d-flex align-items-center gap-2">
                    <span class="d-inline-flex align-items-center justify-content-center rounded-3 p-2 bg-success-subtle text-success">
                        <i class="bi bi-person-plus-fill fs-5" style="color: var(--brand, #267b72) !important;"></i>
                    </span>
                    <div>
                        <h5 class="modal-title fw-bold text-dark mb-0" id="modalTambahPelangganLabel">Tambah Pelanggan Baru</h5>
                        <p class="text-muted small mb-0">Daftarkan pelanggan ke sistem agar dapat dipilih saat order.</p>
                    </div>
                </div>
                <button type="button" class="btn-close" id="btnTambahCloseTop" aria-label="Tutup"></button>
            </div>

            <form id="formTambahPelanggan" method="POST" action="pelanggan.php" novalidate>
                <input type="hidden" name="action" value="add_customer">

                <div class="modal-body py-3 px-4">
                    <div class="mb-3">
                        <label for="addNama" class="form-label fw-semibold text-dark small mb-1">
                            Nama Lengkap <span class="text-danger">*</span>
                        </label>
                        <div class="input-group">
                            <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-person"></i></span>
                            <input
                                type="text"
                                class="form-control border-start-0 ps-0"
                                id="addNama"
                                name="nama"
                                placeholder="Contoh: Budi Santoso"
                                maxlength="100"
                                required
                                value="<?= e($addPostData['nama'] ?? '') ?>"
                            >
                        </div>
                        <div class="invalid-feedback small">Nama lengkap wajib diisi (maks. 100 karakter).</div>
                    </div>

                    <div class="mb-3">
                        <label for="addNoHp" class="form-label fw-semibold text-dark small mb-1">
                            Nomor WhatsApp / HP <span class="text-danger">*</span>
                        </label>
                        <div class="input-group">
                            <span class="input-group-text bg-light text-success border-end-0"><i class="bi bi-whatsapp"></i></span>
                            <input
                                type="tel"
                                class="form-control border-start-0 ps-0"
                                id="addNoHp"
                                name="no_hp"
                                placeholder="Contoh: 081234567890"
                                maxlength="20"
                                required
                                value="<?= e($addPostData['no_hp'] ?? '') ?>"
                            >
                        </div>
                        <div class="invalid-feedback small">Nomor HP wajib diisi (8-20 digit angka).</div>
                        <div class="form-text small" style="font-size: 11px;">Nomor ini akan otomatis sinkron saat membuat pesanan atas nama pelanggan.</div>
                    </div>

                    <div class="mb-3">
                        <label for="addEmail" class="form-label fw-semibold text-dark small mb-1">
                            Alamat Email <span class="text-danger">*</span>
                        </label>
                        <div class="input-group">
                            <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-envelope"></i></span>
                            <input
                                type="email"
                                class="form-control border-start-0 ps-0"
                                id="addEmail"
                                name="email"
                                placeholder="Contoh: budi@gmail.com"
                                maxlength="100"
                                required
                                value="<?= e($addPostData['email'] ?? '') ?>"
                            >
                        </div>
                        <div class="invalid-feedback small">Email wajib diisi dengan format valid.</div>
                    </div>

                    <div class="mb-3">
                        <label for="addPassword" class="form-label fw-semibold text-dark small mb-1">
                            Password Akun <span class="text-danger">*</span>
                        </label>
                        <div class="input-group">
                            <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-lock"></i></span>
                            <input
                                type="password"
                                class="form-control border-start-0 border-end-0 ps-0"
                                id="addPassword"
                                name="password"
                                placeholder="Minimal 8 karakter"
                                minlength="8"
                                required
                            >
                            <button class="btn btn-outline-secondary border-start-0 text-muted" type="button" id="toggleAddPassword" title="Tampilkan/sembunyikan password">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                        <div class="invalid-feedback small">Password wajib diisi minimal 8 karakter.</div>
                        <div class="form-text small" style="font-size: 11px;">Pelanggan dapat menggunakan email dan password ini untuk login di halaman pelacakan pesanan.</div>
                    </div>

                    <div class="mb-2">
                        <label for="addAlamat" class="form-label fw-semibold text-dark small mb-1">
                            Alamat Pelanggan <span class="text-muted fw-normal">(Opsional)</span>
                        </label>
                        <textarea
                            class="form-control"
                            id="addAlamat"
                            name="alamat"
                            rows="2"
                            placeholder="Contoh: Jl. Mawar No. 12, RT 01/RW 03..."
                        ><?= e($addPostData['alamat'] ?? '') ?></textarea>
                    </div>
                </div>

                <div class="modal-footer d-flex justify-content-between align-items-center">
                    <button type="button" class="btn btn-outline-secondary px-3" id="btnTambahBatal">
                        <i class="bi bi-x-lg me-1"></i> Batal
                    </button>
                    <button type="submit" class="btn btn-brand px-4" id="btnTambahSubmit">
                        <i class="bi bi-check-lg me-1"></i> Simpan Pelanggan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ========================================================
     MODAL 2: EDIT DATA PELANGGAN
     ======================================================== -->
<div class="modal fade" id="modalEditPelanggan" tabindex="-1" aria-labelledby="modalEditPelangganLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable" style="max-width: 520px;">
        <div class="modal-content customer-modal-custom">
            <div class="modal-header">
                <div class="d-flex align-items-center gap-2">
                    <span class="d-inline-flex align-items-center justify-content-center rounded-3 p-2 bg-primary-subtle text-primary">
                        <i class="bi bi-pencil-square fs-5"></i>
                    </span>
                    <div>
                        <h5 class="modal-title fw-bold text-dark mb-0" id="modalEditPelangganLabel">Edit Data Pelanggan</h5>
                        <p class="text-muted small mb-0">Perbarui rincian kontak dan profil pelanggan.</p>
                    </div>
                </div>
                <button type="button" class="btn-close" id="btnEditCloseTop" aria-label="Tutup"></button>
            </div>

            <form id="formEditPelanggan" method="POST" action="pelanggan.php" novalidate>
                <input type="hidden" name="action" value="edit_customer">
                <input type="hidden" name="id_pelanggan" id="editIdPelanggan" value="<?= (int)($editPostData['id_pelanggan'] ?? ($editCustomerData['id_pelanggan'] ?? 0)) ?>">

                <div class="modal-body py-3 px-4">
                    <div class="mb-3">
                        <label for="editNama" class="form-label fw-semibold text-dark small mb-1">
                            Nama Lengkap <span class="text-danger">*</span>
                        </label>
                        <div class="input-group">
                            <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-person"></i></span>
                            <input
                                type="text"
                                class="form-control border-start-0 ps-0"
                                id="editNama"
                                name="nama"
                                maxlength="100"
                                required
                                value="<?= e($editPostData['nama'] ?? ($editCustomerData['nama'] ?? '')) ?>"
                            >
                        </div>
                        <div class="invalid-feedback small">Nama lengkap wajib diisi.</div>
                    </div>

                    <div class="mb-3">
                        <label for="editNoHp" class="form-label fw-semibold text-dark small mb-1">
                            Nomor WhatsApp / HP <span class="text-danger">*</span>
                        </label>
                        <div class="input-group">
                            <span class="input-group-text bg-light text-success border-end-0"><i class="bi bi-whatsapp"></i></span>
                            <input
                                type="tel"
                                class="form-control border-start-0 ps-0"
                                id="editNoHp"
                                name="no_hp"
                                maxlength="20"
                                required
                                value="<?= e($editPostData['no_hp'] ?? ($editCustomerData['no_hp'] ?? '')) ?>"
                            >
                        </div>
                        <div class="invalid-feedback small">Nomor HP wajib diisi (8-20 digit).</div>
                    </div>

                    <div class="mb-3">
                        <label for="editEmail" class="form-label fw-semibold text-dark small mb-1">
                            Alamat Email <span class="text-danger">*</span>
                        </label>
                        <div class="input-group">
                            <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-envelope"></i></span>
                            <input
                                type="email"
                                class="form-control border-start-0 ps-0"
                                id="editEmail"
                                name="email"
                                maxlength="100"
                                required
                                value="<?= e($editPostData['email'] ?? ($editCustomerData['email'] ?? '')) ?>"
                            >
                        </div>
                        <div class="invalid-feedback small">Email wajib diisi dengan format valid.</div>
                    </div>

                    <div class="mb-3">
                        <label for="editPassword" class="form-label fw-semibold text-dark small mb-1">
                            Ganti Password <span class="text-muted fw-normal">(Opsional)</span>
                        </label>
                        <div class="input-group">
                            <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-lock"></i></span>
                            <input
                                type="password"
                                class="form-control border-start-0 border-end-0 ps-0"
                                id="editPassword"
                                name="password"
                                placeholder="Kosongkan jika tidak ingin mengubah password"
                                minlength="8"
                            >
                            <button class="btn btn-outline-secondary border-start-0 text-muted" type="button" id="toggleEditPassword" title="Tampilkan/sembunyikan password">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                        <div class="form-text small" style="font-size: 11px;">Biarkan kolom password kosong jika tidak ada perubahan kata sandi.</div>
                    </div>

                    <div class="mb-2">
                        <label for="editAlamat" class="form-label fw-semibold text-dark small mb-1">
                            Alamat Pelanggan <span class="text-muted fw-normal">(Opsional)</span>
                        </label>
                        <textarea
                            class="form-control"
                            id="editAlamat"
                            name="alamat"
                            rows="2"
                        ><?= e($editPostData['alamat'] ?? ($editCustomerData['alamat'] ?? '')) ?></textarea>
                    </div>
                </div>

                <div class="modal-footer d-flex justify-content-between align-items-center">
                    <button type="button" class="btn btn-outline-secondary px-3" id="btnEditBatal">
                        <i class="bi bi-x-lg me-1"></i> Batal
                    </button>
                    <button type="submit" class="btn btn-brand px-4" id="btnEditSubmit">
                        <i class="bi bi-check-lg me-1"></i> Simpan Perubahan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ========================================================
     MODAL 3: DETAIL LENGKAP PELANGGAN & RIWAYAT PESANAN
     ======================================================== -->
<div class="modal fade" id="modalDetailPelanggan" tabindex="-1" aria-labelledby="modalDetailPelangganLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg">
        <div class="modal-content customer-modal-custom">
            <div class="modal-header">
                <div class="d-flex align-items-center gap-2">
                    <span class="d-inline-flex align-items-center justify-content-center rounded-3 p-2 bg-info-subtle text-info">
                        <i class="bi bi-person-vcard-fill fs-5"></i>
                    </span>
                    <div>
                        <h5 class="modal-title fw-bold text-dark mb-0" id="modalDetailPelangganLabel">Detail Profil Pelanggan</h5>
                        <p class="text-muted small mb-0">Informasi data diri dan riwayat transaksi laundry.</p>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>

            <div class="modal-body py-3 px-4">
                <!-- Banner Profil Header -->
                <div class="detail-header-card d-flex align-items-center justify-content-between flex-wrap gap-3">
                    <div class="d-flex align-items-center gap-3">
                        <div class="detail-avatar-large" id="detailAvatar">-</div>
                        <div>
                            <h4 class="fw-bold mb-1" id="detailNama">-</h4>
                            <div class="d-flex align-items-center gap-2 small opacity-75">
                                <span><i class="bi bi-hash"></i> ID #<span id="detailId">-</span></span>
                                <span>&bull;</span>
                                <span><i class="bi bi-calendar-event me-1"></i>Bergabung: <span id="detailCreated">-</span></span>
                            </div>
                        </div>
                    </div>
                    <div>
                        <a href="#" id="detailBtnBuatOrder" class="btn btn-light btn-sm fw-bold px-3 py-2 text-dark shadow-sm">
                            <i class="bi bi-plus-circle-fill text-success me-1"></i> Buat Pesanan Baru
                        </a>
                    </div>
                </div>

                <!-- 3 Kotak Metrik Ringkasan -->
                <div class="detail-metrics-grid">
                    <div class="detail-metric-card">
                        <span>TOTAL ORDER</span>
                        <strong id="detailTotalOrders">0</strong>
                    </div>
                    <div class="detail-metric-card">
                        <span>PESANAN AKTIF</span>
                        <strong class="text-warning" id="detailActiveOrders">0</strong>
                    </div>
                    <div class="detail-metric-card">
                        <span>TOTAL TRANSAKSI</span>
                        <strong class="text-success" id="detailTotalSpent">Rp 0</strong>
                    </div>
                </div>

                <!-- Rincian Kontak & Alamat -->
                <div class="card border rounded-3 p-3 mb-4 bg-light">
                    <div class="row g-3">
                        <div class="col-12 col-md-4">
                            <div class="text-muted small mb-1">Nomor WhatsApp / HP</div>
                            <div class="fw-bold text-dark" id="detailPhone">-</div>
                            <div class="mt-1" id="detailWaBtnWrapper"></div>
                        </div>
                        <div class="col-12 col-md-4">
                            <div class="text-muted small mb-1">Alamat Email</div>
                            <div class="fw-bold text-dark text-break" id="detailEmail">-</div>
                        </div>
                        <div class="col-12 col-md-4">
                            <div class="text-muted small mb-1">Alamat Lengkap</div>
                            <div class="fw-bold text-dark text-break" id="detailAddress">-</div>
                        </div>
                    </div>
                </div>

                <!-- Riwayat Transaksi Terakhir -->
                <div>
                    <h6 class="fw-bold text-dark mb-2 d-flex align-items-center gap-2">
                        <i class="bi bi-clock-history text-secondary"></i> Riwayat 5 Pesanan Terakhir
                    </h6>
                    <div class="table-responsive border rounded-3 overflow-hidden bg-white">
                        <table class="table table-sm align-middle mb-0" style="font-size: 12.5px;">
                            <thead class="table-light">
                                <tr>
                                    <th>KODE</th>
                                    <th>PAKET</th>
                                    <th>BERAT</th>
                                    <th>TOTAL</th>
                                    <th>STATUS</th>
                                    <th>TANGGAL</th>
                                </tr>
                            </thead>
                            <tbody id="detailOrdersTableBody">
                                <tr>
                                    <td colspan="6" class="text-center py-3 text-muted">Belum ada riwayat transaksi.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="modal-footer bg-light d-flex justify-content-between align-items-center">
                <button type="button" class="btn btn-outline-secondary px-3" data-bs-dismiss="modal">
                    Tutup
                </button>
                <button type="button" class="btn btn-primary px-3" id="detailBtnEditDirect">
                    <i class="bi bi-pencil-square me-1"></i> Edit Data Pelanggan
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ========================================================
     MODAL 4: KONFIRMASI HAPUS PELANGGAN (MODERN POPUP)
     ======================================================== -->
<div class="modal fade" id="modalKonfirmasiHapus" tabindex="-1" aria-labelledby="modalKonfirmasiHapusLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 440px;">
        <div class="modal-content confirm-modal-custom">
            <form id="formHapusPelanggan" method="POST" action="pelanggan.php">
                <input type="hidden" name="action" value="delete_customer">
                <input type="hidden" name="id_pelanggan" id="deleteIdPelanggan" value="">

                <div class="modal-body text-center p-4">
                    <button type="button" class="btn-close position-absolute top-0 end-0 m-3" data-bs-dismiss="modal" aria-label="Tutup"></button>

                    <!-- Bubble Icon Hapus -->
                    <div class="confirm-icon-bubble mx-auto mb-3 type-delete">
                        <i class="bi bi-trash3-fill"></i>
                    </div>

                    <h5 class="fw-bold text-dark mb-1" id="modalKonfirmasiHapusLabel">Hapus Data Pelanggan?</h5>
                    <p class="text-muted small mb-3 px-2">
                        Apakah Anda yakin ingin menghapus akun pelanggan ini secara permanen dari sistem?
                    </p>

                    <!-- Box Rincian Pelanggan yang akan dihapus -->
                    <div class="confirm-info-box text-start p-3 mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="text-muted small">Nama Pelanggan:</span>
                            <strong class="text-dark small text-truncate" style="max-width: 200px;" id="deleteNamaText">-</strong>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="text-muted small">Nomor WhatsApp:</span>
                            <span class="fw-bold text-dark small" id="deletePhoneText">-</span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="text-muted small">Email:</span>
                            <span class="text-secondary small text-truncate" style="max-width: 200px;" id="deleteEmailText">-</span>
                        </div>
                        <div class="confirm-badge-alert alert-delete p-2 rounded-2 small">
                            <i class="bi bi-exclamation-triangle-fill text-danger me-1"></i>
                            <span id="deleteAlertText">Data pelanggan akan dihapus. Riwayat transaksi sebelumnya tetap tersimpan sebagai arsip.</span>
                        </div>
                    </div>

                    <!-- Tombol Aksi Hapus -->
                    <div class="d-flex gap-2 justify-content-center pt-1">
                        <button type="button" class="btn btn-light border px-4 flex-grow-1" data-bs-dismiss="modal">
                            Batal
                        </button>
                        <button type="submit" class="btn btn-danger px-4 flex-grow-1 fw-semibold" id="btnDeleteSubmit">
                            <i class="bi bi-trash3 me-1"></i> Ya, Hapus Pelanggan
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // ==========================================
    // 1. MODAL TAMBAH PELANGGAN LOGIC
    // ==========================================
    const modalTambahEl = document.getElementById('modalTambahPelanggan');
    const formTambah    = document.getElementById('formTambahPelanggan');
    const btnTambahSubmit = document.getElementById('btnTambahSubmit');
    const btnTambahBatal  = document.getElementById('btnTambahBatal');
    const btnTambahCloseTop = document.getElementById('btnTambahCloseTop');
    const toggleAddPassword = document.getElementById('toggleAddPassword');
    const addPasswordInput = document.getElementById('addPassword');

    let isTambahDirty = false;
    let isSubmitting = false;

    let bsModalTambah = null;
    if (modalTambahEl && typeof bootstrap !== 'undefined') {
        bsModalTambah = new bootstrap.Modal(modalTambahEl, {
            backdrop: 'static',
            keyboard: false
        });
    }

    // Buka otomatis jika diminta lewat query URL (?modal=add)
    <?php if ($isAddModal): ?>
    if (bsModalTambah) {
        bsModalTambah.show();
    }
    <?php endif; ?>

    // Toggle lihat password tambah
    if (toggleAddPassword && addPasswordInput) {
        toggleAddPassword.addEventListener('click', function () {
            const isPassword = addPasswordInput.type === 'password';
            addPasswordInput.type = isPassword ? 'text' : 'password';
            this.innerHTML = isPassword ? '<i class="bi bi-eye-slash"></i>' : '<i class="bi bi-eye"></i>';
        });
    }

    if (formTambah) {
        formTambah.addEventListener('input', function () {
            isTambahDirty = true;
        });
        formTambah.addEventListener('change', function () {
            isTambahDirty = true;
        });

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
            if (btnTambahSubmit) {
                btnTambahSubmit.disabled = true;
                btnTambahSubmit.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Menyimpan...';
            }
        });
    }

    function cleanUrlModalParams() {
        if (window.location.search.includes('modal=')) {
            const url = new URL(window.location.href);
            url.searchParams.delete('modal');
            url.searchParams.delete('id');
            window.history.replaceState({}, document.title, url.toString());
        }
    }

    function attemptCloseTambahModal() {
        if (isTambahDirty && !isSubmitting) {
            const confirmed = window.confirm('Ada perubahan yang belum disimpan. Yakin ingin membatalkan pendaftaran pelanggan?');
            if (confirmed) {
                isTambahDirty = false;
                formTambah.reset();
                formTambah.classList.remove('was-validated');
                if (bsModalTambah) bsModalTambah.hide();
                cleanUrlModalParams();
            }
        } else {
            isTambahDirty = false;
            if (bsModalTambah) bsModalTambah.hide();
            cleanUrlModalParams();
        }
    }

    if (btnTambahBatal) btnTambahBatal.addEventListener('click', attemptCloseTambahModal);
    if (btnTambahCloseTop) btnTambahCloseTop.addEventListener('click', attemptCloseTambahModal);

    // ==========================================
    // 2. MODAL EDIT PELANGGAN LOGIC
    // ==========================================
    const modalEditEl   = document.getElementById('modalEditPelanggan');
    const formEdit      = document.getElementById('formEditPelanggan');
    const btnEditSubmit = document.getElementById('btnEditSubmit');
    const btnEditBatal  = document.getElementById('btnEditBatal');
    const btnEditCloseTop = document.getElementById('btnEditCloseTop');
    const toggleEditPassword = document.getElementById('toggleEditPassword');
    const editPasswordInput = document.getElementById('editPassword');

    const editIdInput   = document.getElementById('editIdPelanggan');
    const editNamaInput = document.getElementById('editNama');
    const editPhoneInput = document.getElementById('editNoHp');
    const editEmailInput = document.getElementById('editEmail');
    const editAddressInput = document.getElementById('editAlamat');

    let bsModalEdit = null;
    if (modalEditEl && typeof bootstrap !== 'undefined') {
        bsModalEdit = new bootstrap.Modal(modalEditEl, {
            backdrop: 'static',
            keyboard: false
        });
    }

    <?php if ($isEditModal): ?>
    if (bsModalEdit) {
        bsModalEdit.show();
    }
    <?php endif; ?>

    if (toggleEditPassword && editPasswordInput) {
        toggleEditPassword.addEventListener('click', function () {
            const isPassword = editPasswordInput.type === 'password';
            editPasswordInput.type = isPassword ? 'text' : 'password';
            this.innerHTML = isPassword ? '<i class="bi bi-eye-slash"></i>' : '<i class="bi bi-eye"></i>';
        });
    }

    let isEditDirty = false;
    if (formEdit) {
        formEdit.addEventListener('input', function () {
            isEditDirty = true;
        });
        formEdit.addEventListener('submit', function (e) {
            if (!formEdit.checkValidity()) {
                e.preventDefault();
                e.stopPropagation();
                formEdit.classList.add('was-validated');
                return;
            }

            if (isSubmitting) {
                e.preventDefault();
                return;
            }

            isSubmitting = true;
            if (btnEditSubmit) {
                btnEditSubmit.disabled = true;
                btnEditSubmit.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Menyimpan Perubahan...';
            }
        });
    }

    function attemptCloseEditModal() {
        if (isEditDirty && !isSubmitting) {
            const confirmed = window.confirm('Ada perubahan data yang belum disimpan. Yakin ingin membatalkan pengeditan?');
            if (confirmed) {
                isEditDirty = false;
                formEdit.classList.remove('was-validated');
                if (bsModalEdit) bsModalEdit.hide();
                cleanUrlModalParams();
            }
        } else {
            isEditDirty = false;
            if (bsModalEdit) bsModalEdit.hide();
            cleanUrlModalParams();
        }
    }

    if (btnEditBatal) btnEditBatal.addEventListener('click', attemptCloseEditModal);
    if (btnEditCloseTop) btnEditCloseTop.addEventListener('click', attemptCloseEditModal);

    // Event listener klik tombol Edit pada baris tabel
    document.querySelectorAll('.btn-customer-edit').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const id = this.getAttribute('data-id') || '';
            const name = this.getAttribute('data-name') || '';
            const phone = this.getAttribute('data-phone') || '';
            const email = this.getAttribute('data-email') || '';
            const address = this.getAttribute('data-address') || '';

            if (editIdInput) editIdInput.value = id;
            if (editNamaInput) editNamaInput.value = name;
            if (editPhoneInput) editPhoneInput.value = phone;
            if (editEmailInput) editEmailInput.value = email;
            if (editAddressInput) editAddressInput.value = address;
            if (editPasswordInput) editPasswordInput.value = '';

            if (formEdit) formEdit.classList.remove('was-validated');
            isEditDirty = false;

            if (bsModalEdit) bsModalEdit.show();
        });
    });

    // ==========================================
    // 3. MODAL DETAIL PELANGGAN LOGIC
    // ==========================================
    const modalDetailEl = document.getElementById('modalDetailPelanggan');
    const detailAvatar = document.getElementById('detailAvatar');
    const detailNama   = document.getElementById('detailNama');
    const detailId     = document.getElementById('detailId');
    const detailCreated = document.getElementById('detailCreated');
    const detailPhone  = document.getElementById('detailPhone');
    const detailEmail  = document.getElementById('detailEmail');
    const detailAddress = document.getElementById('detailAddress');
    const detailTotalOrders = document.getElementById('detailTotalOrders');
    const detailActiveOrders = document.getElementById('detailActiveOrders');
    const detailTotalSpent = document.getElementById('detailTotalSpent');
    const detailBtnBuatOrder = document.getElementById('detailBtnBuatOrder');
    const detailBtnEditDirect = document.getElementById('detailBtnEditDirect');
    const detailWaBtnWrapper = document.getElementById('detailWaBtnWrapper');
    const detailOrdersTableBody = document.getElementById('detailOrdersTableBody');

    let bsModalDetail = null;
    if (modalDetailEl && typeof bootstrap !== 'undefined') {
        bsModalDetail = new bootstrap.Modal(modalDetailEl);
    }

    let activeDetailData = null;

    document.querySelectorAll('.btn-customer-detail').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const id = this.getAttribute('data-id') || '';
            const name = this.getAttribute('data-name') || '-';
            const phone = this.getAttribute('data-phone') || '-';
            const email = this.getAttribute('data-email') || '-';
            const address = this.getAttribute('data-address') || '-';
            const created = this.getAttribute('data-created') || '-';
            const totalOrders = this.getAttribute('data-total-orders') || '0';
            const activeOrders = this.getAttribute('data-active-orders') || '0';
            const totalSpent = this.getAttribute('data-total-spent') || 'Rp 0';
            let orders = [];

            try {
                orders = JSON.parse(this.getAttribute('data-orders') || '[]');
            } catch (e) {
                orders = [];
            }

            activeDetailData = { id, name, phone, email, address };

            const initials = name.trim().slice(0, 2).toUpperCase();
            if (detailAvatar) detailAvatar.textContent = initials;
            if (detailNama) detailNama.textContent = name;
            if (detailId) detailId.textContent = id;
            if (detailCreated) detailCreated.textContent = created;
            if (detailPhone) detailPhone.textContent = phone;
            if (detailEmail) detailEmail.textContent = email;
            if (detailAddress) detailAddress.textContent = address || 'Belum diisi';
            if (detailTotalOrders) detailTotalOrders.textContent = totalOrders;
            if (detailActiveOrders) detailActiveOrders.textContent = activeOrders;
            if (detailTotalSpent) detailTotalSpent.textContent = totalSpent;

            // Link Buat Order Langsung ke antrean.php
            if (detailBtnBuatOrder) {
                detailBtnBuatOrder.href = 'antrean.php?modal=add&customer_id=' + encodeURIComponent(id);
            }

            // Tombol WA
            if (detailWaBtnWrapper) {
                if (phone && phone !== '-') {
                    let cleanPhone = phone.replace(/[^0-9]/g, '');
                    if (cleanPhone.startsWith('0')) cleanPhone = '62' + cleanPhone.slice(1);
                    detailWaBtnWrapper.innerHTML = `
                        <a href="https://wa.me/${cleanPhone}" target="_blank" class="btn btn-outline-success btn-sm py-0 px-2 fw-semibold" style="font-size: 11px;">
                            <i class="bi bi-whatsapp me-1"></i> Chat WhatsApp
                        </a>
                    `;
                } else {
                    detailWaBtnWrapper.innerHTML = '';
                }
            }

            // Render riwayat transaksi
            if (detailOrdersTableBody) {
                if (!orders || orders.length === 0) {
                    detailOrdersTableBody.innerHTML = '<tr><td colspan="6" class="text-center py-3 text-muted">Belum ada riwayat transaksi tercatat.</td></tr>';
                } else {
                    let rowsHtml = '';
                    orders.forEach(function (o) {
                        const statusLabels = {
                            'waiting': 'Menunggu',
                            'washing': 'Sedang Dicuci',
                            'drying': 'Pengeringan',
                            'ironing': 'Penyetrikaan',
                            'ready': 'Siap Diambil',
                            'completed': 'Selesai',
                            'cancelled': 'Dibatalkan'
                        };
                        const statusBadgeClass = {
                            'waiting': 'bg-warning-subtle text-warning border-warning-subtle',
                            'washing': 'bg-primary-subtle text-primary border-primary-subtle',
                            'drying': 'bg-info-subtle text-info border-info-subtle',
                            'ironing': 'bg-primary-subtle text-primary border-primary-subtle',
                            'ready': 'bg-success-subtle text-success border-success-subtle',
                            'completed': 'bg-success-subtle text-success border-success-subtle',
                            'cancelled': 'bg-danger-subtle text-danger border-danger-subtle'
                        };
                        const sClass = statusBadgeClass[o.status] || 'bg-light text-dark';
                        const sLabel = statusLabels[o.status] || o.status;
                        const totalRp = 'Rp ' + Math.round(parseFloat(o.total_biaya) || 0).toLocaleString('id-ID');
                        const dateFormatted = o.tanggal_masuk ? o.tanggal_masuk.slice(0, 16) : '-';

                        rowsHtml += `
                            <tr>
                                <td>
                                    <strong class="font-monospace text-dark">${o.kode_transaksi}</strong>
                                </td>
                                <td>${o.paket || '-'}</td>
                                <td>${parseFloat(o.berat || 0).toFixed(1)} kg</td>
                                <td><strong>${totalRp}</strong></td>
                                <td><span class="badge ${sClass} border">${sLabel}</span></td>
                                <td class="text-muted small">${dateFormatted}</td>
                            </tr>
                        `;
                    });
                    detailOrdersTableBody.innerHTML = rowsHtml;
                }
            }

            if (bsModalDetail) bsModalDetail.show();
        });
    });

    if (detailBtnEditDirect) {
        detailBtnEditDirect.addEventListener('click', function () {
            if (bsModalDetail) bsModalDetail.hide();
            if (activeDetailData && bsModalEdit) {
                if (editIdInput) editIdInput.value = activeDetailData.id;
                if (editNamaInput) editNamaInput.value = activeDetailData.name;
                if (editPhoneInput) editPhoneInput.value = activeDetailData.phone;
                if (editEmailInput) editEmailInput.value = activeDetailData.email;
                if (editAddressInput) editAddressInput.value = activeDetailData.address === '-' ? '' : activeDetailData.address;
                if (editPasswordInput) editPasswordInput.value = '';
                bsModalEdit.show();
            }
        });
    }

    // ==========================================
    // 4. MODAL KONFIRMASI HAPUS LOGIC
    // ==========================================
    const modalDeleteEl   = document.getElementById('modalKonfirmasiHapus');
    const deleteIdInput   = document.getElementById('deleteIdPelanggan');
    const deleteNamaText  = document.getElementById('deleteNamaText');
    const deletePhoneText = document.getElementById('deletePhoneText');
    const deleteEmailText = document.getElementById('deleteEmailText');
    const deleteAlertText = document.getElementById('deleteAlertText');
    const btnDeleteSubmit = document.getElementById('btnDeleteSubmit');

    let bsModalDelete = null;
    if (modalDeleteEl && typeof bootstrap !== 'undefined') {
        bsModalDelete = new bootstrap.Modal(modalDeleteEl);
    }

    document.querySelectorAll('.btn-customer-delete').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const id = this.getAttribute('data-id') || '';
            const name = this.getAttribute('data-name') || '-';
            const phone = this.getAttribute('data-phone') || '-';
            const email = this.getAttribute('data-email') || '-';
            const ordersCount = parseInt(this.getAttribute('data-orders-count') || '0', 10);

            if (deleteIdInput) deleteIdInput.value = id;
            if (deleteNamaText) deleteNamaText.textContent = name;
            if (deletePhoneText) deletePhoneText.textContent = phone;
            if (deleteEmailText) deleteEmailText.textContent = email;

            if (deleteAlertText) {
                if (ordersCount > 0) {
                    deleteAlertText.textContent = `Perhatian: Pelanggan ini memiliki ${ordersCount} riwayat transaksi. Seluruh riwayat transaksi akan tetap tersimpan aman sebagai arsip.`;
                } else {
                    deleteAlertText.textContent = 'Data pelanggan ini akan dihapus permanen dari sistem database.';
                }
            }

            if (bsModalDelete) bsModalDelete.show();
        });
    });

    const formHapus = document.getElementById('formHapusPelanggan');
    if (formHapus) {
        formHapus.addEventListener('submit', function () {
            if (btnDeleteSubmit) {
                btnDeleteSubmit.disabled = true;
                btnDeleteSubmit.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Menghapus...';
            }
        });
    }
});
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>

