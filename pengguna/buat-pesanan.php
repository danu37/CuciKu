<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

date_default_timezone_set('Asia/Jakarta');

require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/helpers.php';

// =====================================
// 1. VALIDASI LOGIN
// =====================================
if (
    empty($_SESSION['pelanggan_logged_in']) ||
    empty($_SESSION['pelanggan_id'])
) {
    header('Location: ../login.php');
    exit;
}

$idPelanggan = (int) $_SESSION['pelanggan_id'];
$namaPelanggan = $_SESSION['pelanggan_nama'] ?? 'Pelanggan';

if (empty($_SESSION['csrf_pesanan'])) {
    $_SESSION['csrf_pesanan'] = bin2hex(random_bytes(32));
}

$tarifPaket = [
    'Reguler' => 7000,
    'Express' => 14000,
];

$pesanError = '';
$pesananBerhasil = null;

// =====================================
// 2. AMBIL DATA PELANGGAN
// =====================================
try {
    $stmtPelanggan = $pdo->prepare("
        SELECT id_pelanggan, nama, no_hp, email
        FROM pelanggan
        WHERE id_pelanggan = ?
        LIMIT 1
    ");
    $stmtPelanggan->execute([$idPelanggan]);
    $pelanggan = $stmtPelanggan->fetch();

    if (!$pelanggan) {
        session_unset();
        session_destroy();
        header('Location: ../login.php');
        exit;
    }
} catch (PDOException $e) {
    error_log($e->getMessage());
    http_response_code(500);
    exit('Data pelanggan gagal dimuat. Periksa struktur tabel pelanggan.');
}

// =====================================
// 3. AMBIL NOTA SETELAH REDIRECT
// =====================================
if (isset($_GET['nota'])) {
    $stmtNota = $pdo->prepare("
        SELECT *
        FROM transaksi
        WHERE kode_transaksi = ?
          AND pelanggan_id = ?
        LIMIT 1
    ");

    $stmtNota->execute([
        trim($_GET['nota']),
        $idPelanggan
    ]);

    $pesananBerhasil = $stmtNota->fetch() ?: null;
}

// =====================================
// 4. PROSES PEMESANAN
// =====================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';

    if (
        !is_string($token) ||
        !hash_equals($_SESSION['csrf_pesanan'], $token)
    ) {
        $pesanError = 'Permintaan tidak valid. Silakan muat ulang halaman.';
    } else {
        $paket = $_POST['paket'] ?? '';
        $beratInput = $_POST['berat'] ?? '';

        if (!isset($tarifPaket[$paket])) {
            $pesanError = 'Pilih paket laundry yang tersedia.';
        } elseif (
            !is_string($beratInput) ||
            !is_numeric($beratInput)
        ) {
            $pesanError = 'Berat pakaian harus berupa angka.';
        } else {
            $berat = (float) $beratInput;

            if ($berat <= 0 || $berat > 25) {
                $pesanError = 'Berat harus lebih dari 0 kg dan maksimal 25 kg.';
            } elseif (strlen($beratInput) > 10) {
                $pesanError = 'Format berat tidak valid.';
            } else {
                $tarif = $tarifPaket[$paket];
                $totalBiaya = (int) round($berat * $tarif);

                $tanggalMasuk = new DateTimeImmutable('now');

                // Estimasi awal: Reguler 2 hari, Express 1 hari.
                $jumlahHari = ($paket === 'Express') ? 1 : 2;
                $deadline = $tanggalMasuk->modify("+{$jumlahHari} days");

                try {
                    // Nomor nota dibuat unik dan tetap diperiksa
                    // oleh constraint UNIQUE di database.
                    $tersimpan = false;
                    $kodeTransaksi = '';

                    for ($percobaan = 0; $percobaan < 3; $percobaan++) {
                        $kodeTransaksi = 'LD-' .
                            $tanggalMasuk->format('Ymd') . '-' .
                            strtoupper(bin2hex(random_bytes(3)));

                        try {
                            $stmtInsert = $pdo->prepare("
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
                                    pembayaran
                                ) VALUES (
                                    :kode,
                                    :pelanggan_id,
                                    :nama,
                                    :no_wa,
                                    :paket,
                                    :berat,
                                    :tarif,
                                    :tanggal_masuk,
                                    :deadline,
                                    'waiting',
                                    :total,
                                    'belum_lunas'
                                )
                            ");

                            $stmtInsert->execute([
                                ':kode' => $kodeTransaksi,
                                ':pelanggan_id' => $idPelanggan,
                                ':nama' => $pelanggan['nama'],
                                ':no_wa' => $pelanggan['no_hp'],
                                ':paket' => $paket,
                                ':berat' => $berat,
                                ':tarif' => $tarif,
                                ':tanggal_masuk' => $tanggalMasuk->format('Y-m-d H:i:s'),
                                ':deadline' => $deadline->format('Y-m-d H:i:s'),
                                ':total' => $totalBiaya,
                            ]);

                            $tersimpan = true;
                            break;
                        } catch (PDOException $e) {
                            // Ulangi hanya jika kode nota bentrok.
                            if (
                                $e->getCode() === '23000' &&
                                str_contains(strtolower($e->getMessage()), 'kode_transaksi')
                            ) {
                                continue;
                            }

                            throw $e;
                        }
                    }

                    if (!$tersimpan) {
                        throw new RuntimeException('Nomor nota gagal dibuat.');
                    }

                    // Ganti token setelah pesanan berhasil dibuat.
                    $_SESSION['csrf_pesanan'] = bin2hex(random_bytes(32));

                    flash("Pesanan #{$kodeTransaksi} berhasil dibuat! Berat: {$berat} kg, Total: " . formatRupiah($totalBiaya), 'success', 'Pesanan Berhasil');

                    // Redirect agar refresh tidak mengirim formulir lagi.
                    header(
                        'Location: buat-pesanan.php?nota=' .
                        urlencode($kodeTransaksi)
                    );
                    exit;

                } catch (Throwable $e) {
                    error_log($e->getMessage());
                    $pesanError = 'Pesanan gagal disimpan. Periksa database dan coba kembali.';
                }
            }
        }
    }
}

function h($nilai) {
    return e($nilai);
}

function formatRupiah($nilai) {
    return rupiah($nilai);
}

function labelStatus($status) {
    return statusLabel($status);
}
$judulHalaman = 'Pesan Laundry';
$halamanAktif = 'pesan';
require __DIR__ . '/includes/header.php';
?>

<style>
        .main {
            max-width: 1000px;
            margin: auto;
            padding: 35px 18px;
        }

        .hero {
            background: linear-gradient(120deg, #075b66, #087f8c, #19b5a5);
            padding: 28px;
            border-radius: 20px;
            color: white;
            margin-bottom: 25px;
        }

        .hero h1 {
            font-size: 28px;
            font-weight: 800;
        }

        .card-custom {
            background: white;
            padding: 26px;
            border: 1px solid #e4ebef;
            border-radius: 18px;
            box-shadow: 0 8px 25px #18304406;
        }

        .form-label { font-weight: 700; }

        .form-control, .form-select {
            padding: 12px;
            border-radius: 10px;
        }

        .btn-primary-custom {
            background: var(--brand, #267b72);
            color: white;
            padding: 12px 18px;
            border: none;
            border-radius: 10px;
            font-weight: 700;
            transition: background-color .15s ease, transform .1s ease;
        }

        .btn-primary-custom:hover {
            background: var(--brand-dark, #1f665f);
            color: white;
            transform: translateY(-1px);
        }

        .total-box {
            background: #e9f9f6;
            padding: 20px;
            border-radius: 14px;
            border: 1px solid #c9eee7;
        }

        .total-number {
            font-size: 28px;
            font-weight: 800;
            color: #087f8c;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            gap: 15px;
            padding: 12px 0;
            border-bottom: 1px solid #edf1f4;
        }

        .info-row:last-child { border-bottom: 0; }

        .muted { color: #718096; }

        @media(max-width: 600px) {
            .card-custom { padding: 18px; }
            .hero h1 { font-size: 23px; }
        }

        @media print {
            * {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            nav, footer, .sidebar, .topbar, .mobile-menu-btn, .page-footer, .hero, .no-print, .alert {
                display: none !important;
            }
            body {
                background: #fff !important;
                color: #183044 !important;
                margin: 0 !important;
                padding: 0 !important;
            }
            .app-shell, .main-content, .page-content, .main {
                display: block !important;
                margin: 0 !important;
                padding: 0 !important;
                border: 0 !important;
                box-shadow: none !important;
                width: 100% !important;
                max-width: 100% !important;
            }
            .card-custom {
                max-width: 480px !important;
                margin: 0 auto !important;
                border: 1px dashed #087f8c !important;
                box-shadow: none !important;
                padding: 24px !important;
                page-break-inside: avoid;
            }
            .total-box {
                border: 1px solid #c9eee7 !important;
                background: #e9f9f6 !important;
            }
        }
</style>

<div class="main">
    <section class="hero">
        <h1><i class="bi bi-bag-plus me-2"></i>Pesan Laundry</h1>
        <p class="mb-0">Halo, <?= h($namaPelanggan) ?>. Isi formulir berikut untuk membuat pesanan baru.</p>
    </section>

    <?php if ($pesanError !== ''): ?>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                if (typeof window.createAppToast === 'function') {
                    window.createAppToast(<?= json_encode($pesanError) ?>, 'danger', 'Validasi Gagal');
                }
            });
        </script>
        <div class="alert alert-danger"><?= h($pesanError) ?></div>
    <?php endif; ?>

    <?php if ($pesananBerhasil): ?>
        <section class="card-custom mb-4">
            <div class="text-center mb-4">
                <div class="mb-3 text-success fs-1"><i class="bi bi-check-circle-fill"></i></div>
                <h2 class="fw-bold">Pesanan Berhasil Dibuat!</h2>
                <p class="muted">Simpan nomor nota berikut untuk memeriksa pesananmu.</p>
            </div>

            <div class="total-box text-center mb-4">
                <div class="muted">Nomor Nota</div>
                <h3 class="fw-bold"><?= h($pesananBerhasil['kode_transaksi']) ?></h3>
                <div class="total-number">
                    <?= formatRupiah($pesananBerhasil['total_biaya']) ?>
                </div>
            </div>

            <div class="info-row">
                <span class="muted">Nama pelanggan</span>
                <strong><?= h($pesananBerhasil['nama_pelanggan']) ?></strong>
            </div>
            <div class="info-row">
                <span class="muted">Paket</span>
                <strong><?= h($pesananBerhasil['paket']) ?></strong>
            </div>
            <div class="info-row">
                <span class="muted">Berat</span>
                <strong><?= h($pesananBerhasil['berat']) ?> kg</strong>
            </div>
            <div class="info-row">
                <span class="muted">Tarif per kg</span>
                <strong><?= formatRupiah($pesananBerhasil['tarif_per_kg']) ?></strong>
            </div>
            <div class="info-row">
                <span class="muted">Tanggal masuk</span>
                <strong><?= h(date('d/m/Y H:i', strtotime($pesananBerhasil['tanggal_masuk']))) ?></strong>
            </div>
            <div class="info-row">
                <span class="muted">Estimasi selesai</span>
                <strong><?= h(date('d/m/Y H:i', strtotime($pesananBerhasil['deadline']))) ?></strong>
            </div>
            <div class="info-row">
                <span class="muted">Status</span>
                <strong><?= h(labelStatus($pesananBerhasil['status'])) ?></strong>
            </div>
            <div class="info-row">
                <span class="muted">Pembayaran</span>
                <strong>Belum Lunas</strong>
            </div>

            <div class="d-flex flex-wrap gap-2 mt-4 no-print">
                <a href="riwayat.php" class="btn btn-primary-custom">
                    <i class="bi bi-clock-history me-1"></i> Lihat Riwayat Pesanan
                </a>
                <a href="buat-pesanan.php" class="btn btn-outline-success">
                    <i class="bi bi-plus-circle me-1"></i> Buat Pesanan Lagi
                </a>
                <button type="button" class="btn btn-outline-secondary"
                        onclick="window.print()">
                    <i class="bi bi-printer me-1"></i> Cetak Struk
                </button>
            </div>
        </section>
    <?php else: ?>
        <section class="card-custom">
            <h4 class="fw-bold mb-1">Formulir Pemesanan</h4>
            <p class="muted mb-4">Pastikan berat pakaian dan paket sudah benar.</p>

            <form method="POST" action="buat-pesanan.php" id="formPesanan" onsubmit="const btn=this.querySelector('button[type=submit]'); if(btn){btn.disabled=true; btn.innerHTML='<span class=\'spinner-border spinner-border-sm me-2\'></span>Memproses Pesanan...';}">
                <input type="hidden" name="csrf_token"
                       value="<?= h($_SESSION['csrf_pesanan']) ?>">

                <div class="mb-3">
                    <label class="form-label">Nama Pelanggan</label>
                    <input type="text" class="form-control"
                           value="<?= h($pelanggan['nama']) ?>" readonly>
                </div>

                <div class="mb-3">
                    <label class="form-label">Nomor HP</label>
                    <input type="text" class="form-control"
                           value="<?= h($pelanggan['no_hp']) ?>" readonly>
                </div>

                <div class="mb-3">
                    <label for="paket" class="form-label">Pilih Paket Laundry</label>
                    <select name="paket" id="paket" class="form-select" required>
                        <option value="Reguler">Reguler — Rp7.000/kg (estimasi 2 hari)</option>
                        <option value="Express">Express — Rp14.000/kg (estimasi 1 hari)</option>
                    </select>
                </div>

                <div class="mb-4">
                    <label for="berat" class="form-label">Berat Pakaian (kg)</label>
                    <input type="number" name="berat" id="berat"
                           class="form-control" min="0.1" max="25"
                           step="0.1" placeholder="Contoh: 3.5 (maks. 25 kg)" required>
                    <small class="text-secondary">Masukkan berat sesuai timbangan (maksimal 25 kg). Berat akhir dapat diverifikasi petugas.</small>
                </div>

                <div class="total-box mb-4">
                    <div class="muted">Perkiraan Total Biaya</div>
                    <div class="total-number" id="totalBiaya">Rp 0</div>
                    <div class="muted" id="keteranganTarif">Tarif Rp7.000/kg</div>
                </div>

                <div class="alert alert-info">
                    Estimasi waktu dihitung sejak pesanan dibuat. Waktu penyelesaian aktual dapat berubah sesuai antrean dan konfirmasi petugas.
                </div>

                <button type="submit" class="btn-primary-custom w-100">
                    <i class="bi bi-send me-1"></i> Buat Pesanan Sekarang
                </button>
            </form>
        </section>
    <?php endif; ?>
</div>

<script>
const paket = document.getElementById('paket');
const berat = document.getElementById('berat');
const totalBiaya = document.getElementById('totalBiaya');
const keteranganTarif = document.getElementById('keteranganTarif');

function hitungTotal() {
    if (!paket || !berat || !totalBiaya) return;

    const tarif = paket.value === 'Express' ? 14000 : 7000;
    const jumlahBerat = Number(berat.value);
    const total = jumlahBerat > 0 ? Math.round(jumlahBerat * tarif) : 0;

    totalBiaya.textContent = 'Rp ' + total.toLocaleString('id-ID');
    keteranganTarif.textContent =
        'Tarif ' + paket.value + ': Rp' + tarif.toLocaleString('id-ID') + '/kg';
}

if (paket && berat) {
    paket.addEventListener('change', hitungTotal);
    berat.addEventListener('input', hitungTotal);
    hitungTotal();
}
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
