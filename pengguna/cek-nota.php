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

function hCekNota($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function rupiahCekNota($value)
{
    return 'Rp ' . number_format((float) $value, 0, ',', '.');
}

$idPelanggan = (int) $_SESSION['pelanggan_id'];
$kodeNota = isset($_GET['nota']) && is_string($_GET['nota'])
    ? trim($_GET['nota'])
    : '';
$pesanan = null;

if ($kodeNota !== '') {
    $stmt = $pdo->prepare("
        SELECT id, kode_transaksi, paket, berat, total_biaya,
               tanggal_masuk, deadline, status, pembayaran
        FROM transaksi
        WHERE kode_transaksi = ?
          AND pelanggan_id = ?
        LIMIT 1
    ");
    $stmt->execute([$kodeNota, $idPelanggan]);
    $pesanan = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

$statusList = [
    'waiting' => 'Menunggu',
    'washing' => 'Sedang Dicuci',
    'drying' => 'Pengeringan',
    'ironing' => 'Disetrika',
    'ready' => 'Siap Diambil',
    'completed' => 'Selesai',
    'cancelled' => 'Dibatalkan',
];

$judulHalaman = 'Struk';
$halamanAktif = 'struk';
require __DIR__ . '/includes/header.php';
?>

<style>
.receipt-check-page{max-width:1050px;margin:0 auto}
.receipt-check-heading{display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap;margin-bottom:22px}
.receipt-check-heading h1{font:800 24px Manrope,sans-serif;letter-spacing:-.6px;margin:0 0 7px}
.receipt-check-heading p{color:#8a96a5;font-size:12px;margin:0}
.receipt-check-card{overflow:hidden;background:#fff;border:1px solid #e9edf2;border-radius:14px;box-shadow:0 2px 5px #162c3e03}
.receipt-check-form{display:flex;gap:10px;padding:22px}
.receipt-check-input{position:relative;flex:1}
.receipt-check-input i{position:absolute;left:14px;top:50%;transform:translateY(-50%);color:#8a96a5}
.receipt-check-input input{min-height:44px;padding-left:40px;border:1px solid #dfe5ec;border-radius:9px;font-size:12px}
.receipt-check-input input:focus{border-color:#267b72;box-shadow:0 0 0 3px #267b721c}
.receipt-check-result{border-top:1px solid #edf0f4;padding:22px}
.receipt-check-result-head{display:flex;justify-content:space-between;align-items:center;gap:14px;flex-wrap:wrap;margin-bottom:17px}
.receipt-check-result-head > div > span{color:#8a96a5;font-size:11px}
.receipt-check-result-head strong{display:block;color:#267b72;font-size:17px;overflow-wrap:anywhere}
.receipt-check-status{display:inline-flex;align-items:center;gap:7px;padding:7px 11px;border-radius:20px;background:#e7f6f0;color:#298672;font-size:11px;font-weight:700}
.receipt-check-status.status-waiting{background:#fff3df;color:#a46b19}
.receipt-check-status.status-progress{background:#edf3ff;color:#4a69a8}
.receipt-check-status.status-cancelled{background:#fff0ed;color:#bf4d43}
.receipt-check-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px}
.receipt-check-item{padding:13px;border:1px solid #edf0f4;border-radius:10px}
.receipt-check-item span{display:block;color:#8a96a5;font-size:10px;margin-bottom:5px}
.receipt-check-item strong{display:block;color:#263348;font-size:12px;overflow-wrap:anywhere}
.receipt-check-actions{display:flex;gap:9px;flex-wrap:wrap;margin-top:18px}
.receipt-check-empty{padding:38px 20px;text-align:center}
.receipt-check-empty i{display:block;color:#9aa7bc;font-size:30px;margin-bottom:10px}
.receipt-check-empty strong{display:block;color:#46536b;font-size:13px}
.receipt-check-empty p{color:#8a96a5;font-size:11px;margin:6px 0 0}
.receipt-check-tip{display:flex;align-items:flex-start;gap:10px;color:#718096;font-size:11px;line-height:1.6;margin-top:14px}
.receipt-check-tip i{color:#267b72;margin-top:1px}
@media(max-width:767px){.receipt-check-form{padding:16px}.receipt-check-result{padding:16px}.receipt-check-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:480px){.receipt-check-form{flex-direction:column}.receipt-check-form .btn{width:100%}.receipt-check-heading h1{font-size:21px}}
</style>

<div class="receipt-check-page">
    <div class="receipt-check-heading">
        <div>
            <h1>Struk Digital</h1>
            <p>Lihat dan cetak bukti transaksi laundry kamu.</p>
        </div>
        <span class="notification-summary">
            <i class="bi bi-shield-check me-2"></i>Struk pribadi dan aman
        </span>
    </div>

    <section class="receipt-check-card">
        <form class="receipt-check-form" method="get" action="cek-nota.php">
            <div class="receipt-check-input">
                <i class="bi bi-search"></i>
                <label class="visually-hidden" for="kodeNota">Kode transaksi</label>
                <input type="search" class="form-control" id="kodeNota"
                       name="nota" value="<?= hCekNota($kodeNota) ?>"
                       placeholder="Masukkan kode nota, contoh LD-20261014-ABC123"
                       maxlength="40" required>
            </div>
            <button type="submit" class="btn btn-brand">
                <i class="bi bi-search me-1"></i> Cari Nota
            </button>
        </form>

        <?php if ($kodeNota === ''): ?>
            <div class="receipt-check-empty">
                <i class="bi bi-receipt-cutoff"></i>
                <strong>Cari struk transaksi</strong>
                <p>Masukkan kode transaksi yang tertera pada pesanan untuk membuka struk digital.</p>
            </div>
        <?php elseif (!$pesanan): ?>
            <div class="receipt-check-empty" role="status">
                <i class="bi bi-search"></i>
                <strong>Struk tidak ditemukan</strong>
                <p>Periksa kembali kode transaksi. Pencarian hanya menampilkan pesanan milik akun kamu.</p>
            </div>
        <?php else: ?>
            <?php
            $statusPesanan = $statusList[$pesanan['status']] ?? ucfirst($pesanan['status']);
            $statusClass = match ($pesanan['status']) {
                'waiting' => 'status-waiting',
                'washing', 'drying', 'ironing' => 'status-progress',
                'cancelled' => 'status-cancelled',
                default => '',
            };
            $tanggalMasuk = $pesanan['tanggal_masuk']
                ? date('d/m/Y H:i', strtotime($pesanan['tanggal_masuk']))
                : '-';
            ?>
            <div class="receipt-check-result">
                <div class="receipt-check-result-head">
                    <div>
                        <span>STRUK DITEMUKAN</span>
                        <strong><?= hCekNota($pesanan['kode_transaksi']) ?></strong>
                    </div>
                    <span class="receipt-check-status <?= hCekNota($statusClass) ?>">
                        <i class="bi bi-check-circle-fill"></i>
                        <?= hCekNota($statusPesanan) ?>
                    </span>
                </div>

                <div class="receipt-check-grid">
                    <div class="receipt-check-item">
                        <span>Tanggal masuk</span>
                        <strong><?= hCekNota($tanggalMasuk) ?></strong>
                    </div>
                    <div class="receipt-check-item">
                        <span>Paket laundry</span>
                        <strong><?= hCekNota($pesanan['paket']) ?></strong>
                    </div>
                    <div class="receipt-check-item">
                        <span>Berat cucian</span>
                        <strong><?= hCekNota($pesanan['berat']) ?> kg</strong>
                    </div>
                    <div class="receipt-check-item">
                        <span>Total biaya</span>
                        <strong><?= rupiahCekNota($pesanan['total_biaya']) ?></strong>
                    </div>
                </div>

                <div class="receipt-check-actions">
                    <a class="btn btn-brand"
                       href="detail-pesanan.php?id=<?= (int) $pesanan['id'] ?>">
                        <i class="bi bi-receipt me-1"></i> Buka &amp; Cetak Struk
                    </a>
                    <a class="btn btn-outline-secondary"
                       href="cek-nota.php">
                         Cari struk lain
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </section>

    <div class="receipt-check-tip">
        <i class="bi bi-info-circle-fill"></i>
        <span>Kode transaksi biasanya diawali dengan <strong>LD-</strong>. Struk lengkap dapat dibuka dan dicetak dari halaman detail.</span>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
