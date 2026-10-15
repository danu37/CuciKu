<?php
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/init.php';

$pageTitle = 'Notifikasi';

function notifE($value)
{
    return e($value);
}

function notifTanggal($tanggal)
{
    return formatTanggal($tanggal);
}

function notifStatus($status)
{
    return statusLabel($status);
}

/* Ambil cucian siap diambil */
$stmt = $pdo->query("
    SELECT id, kode_transaksi, nama_pelanggan, no_wa,
           paket, berat, deadline, status, tanggal_masuk
    FROM transaksi
    WHERE status = 'ready'
    ORDER BY deadline ASC
");
$ready = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* Ambil transaksi yang melewati deadline dan belum selesai */
$stmt = $pdo->query("
    SELECT id, kode_transaksi, nama_pelanggan, no_wa,
           paket, berat, deadline, status, tanggal_masuk
    FROM transaksi
    WHERE deadline < NOW()
      AND status NOT IN ('completed', 'cancelled')
    ORDER BY deadline ASC
");
$overdue = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* Ambil aktivitas dari tabel notifikasi */
$notices = [];

try {
    $stmt = $pdo->query("
        SELECT
            n.id,
            n.transaksi_id,
            n.tipe,
            n.pesan,
            n.dibaca,
            n.created_at,
            t.kode_transaksi,
            t.nama_pelanggan
        FROM notifikasi n
        LEFT JOIN transaksi t ON t.id = n.transaksi_id
        ORDER BY n.created_at DESC, n.id DESC
        LIMIT 50
    ");

    $notices = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log($e->getMessage());
}

/* Hitung jumlah notifikasi */
$attentionCount = count($ready) + count($overdue);

require __DIR__ . '/../includes/header.php';
?>

<style>
.notification-page {
    padding: 24px;
    color: #172033;
}
.notification-heading {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 16px;
    flex-wrap: wrap;
    margin-bottom: 24px;
}
.notification-heading h1 {
    font-size: 28px;
    margin: 0 0 6px;
}
.notification-muted {
    color: #778197;
    font-size: 14px;
}
.notification-summary {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 11px 15px;
    border-radius: 12px;
    background: var(--brand-light, #e7f5f2);
    color: var(--brand, #267b72);
    font-weight: 700;
    font-size: 13px;
}
.notification-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 20px;
}
.notification-card {
    background: #fff;
    border: 1px solid #e9ecf4;
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 5px 18px rgba(28, 38, 70, .04);
}
.notification-card-header {
    padding: 20px;
    border-bottom: 1px solid #edf0f6;
}
.notification-card-header h2 {
    margin: 0;
    font-size: 17px;
}
.notification-card-header p {
    margin: 8px 0 0;
    font-size: 13px;
    color: #778197;
}
.notification-title-row {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}
.notification-count {
    background: #f0f1f7;
    color: #535c70;
    border-radius: 20px;
    padding: 4px 9px;
    font-size: 12px;
    font-weight: 700;
}
.notification-row {
    display: flex;
    align-items: center;
    gap: 13px;
    padding: 16px 20px;
    border-bottom: 1px solid #f0f2f7;
}
.notification-row:last-child {
    border-bottom: 0;
}
.notification-symbol {
    flex: 0 0 42px;
    height: 42px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--brand-light, #e7f5f2);
    color: var(--brand, #267b72);
    font-size: 19px;
}
.notification-symbol.ready {
    background: #dcf8e9;
    color: #15834e;
}
.notification-symbol.overdue {
    background: #ffe7e8;
    color: #c52d3a;
}
.notification-main {
    min-width: 0;
    flex: 1;
}
.notification-main strong {
    display: block;
    font-size: 13px;
    margin-bottom: 5px;
    overflow-wrap: anywhere;
}
.notification-main span {
    display: block;
    color: #778197;
    font-size: 12px;
    line-height: 1.6;
}
.notification-action {
    color: var(--brand, #267b72);
    text-decoration: none;
    border: 1px solid #d5ebe7;
    background: #fff;
    border-radius: 8px;
    padding: 7px 11px;
    font-size: 12px;
    font-weight: 600;
    white-space: nowrap;
    transition: background-color .15s ease, border-color .15s ease;
}
.notification-action:hover {
    background: var(--brand-light, #e7f5f2);
    border-color: var(--brand, #267b72);
    color: var(--brand-dark, #1f665f);
}
.notification-empty {
    padding: 34px 18px;
    text-align: center;
    color: #778197;
}
.notification-empty i {
    display: block;
    font-size: 28px;
    margin-bottom: 10px;
    color: #19a268;
}
.notification-empty strong {
    display: block;
    color: #30394d;
    font-size: 14px;
    margin-bottom: 6px;
}
.notification-empty span {
    font-size: 12px;
}
.notification-activity {
    margin-top: 20px;
}
.notification-unread {
    background: #f4faf9;
}
.notification-type {
    display: inline-block;
    font-size: 10px;
    font-weight: 700;
    text-transform: uppercase;
    border-radius: 5px;
    padding: 3px 6px;
    margin-bottom: 5px;
    background: #edf0f7;
    color: #5d6578;
}
.notification-type.new {
    background: #e3efff;
    color: #245fc1;
}
.notification-type.ready {
    background: #dcf8e9;
    color: #15834e;
}
.notification-note {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    margin-top: 18px;
    padding: 15px;
    background: #f5f6fb;
    color: #687187;
    border-radius: 12px;
    font-size: 13px;
    line-height: 1.6;
}
@media(max-width: 850px) {
    .notification-grid {
        grid-template-columns: 1fr;
    }
    .notification-page {
        padding: 15px;
    }
}
@media(max-width: 520px) {
    .notification-row {
        align-items: flex-start;
        padding: 14px;
        gap: 10px;
    }
    .notification-symbol {
        flex-basis: 36px;
        height: 36px;
    }
    .notification-action {
        padding: 6px 8px;
    }
}
</style>

<div class="notification-page">
    <div class="notification-heading">
        <div>
            <h1>Notifikasi</h1>
            <div class="notification-muted">
                Pantau cucian siap diambil, deadline, dan aktivitas transaksi.
            </div>
        </div>

        <div class="notification-summary">
            <i class="bi bi-bell"></i>
            <?= $attentionCount ?> perlu diperhatikan
        </div>
    </div>

    <div class="notification-grid">
        <!-- Cuci siap diambil -->
        <section class="notification-card">
            <div class="notification-card-header">
                <div class="notification-title-row">
                    <h2><i class="bi bi-bag-check" style="color:#15945b"></i>
                        Cucian siap diambil
                    </h2>
                    <span class="notification-count"><?= count($ready) ?></span>
                </div>
                <p>Transaksi yang sudah siap dan menunggu pelanggan.</p>
            </div>

            <?php if (!$ready): ?>
                <div class="notification-empty">
                    <i class="bi bi-check-circle"></i>
                    <strong>Belum ada cucian siap diambil</strong>
                    <span>Transaksi berstatus siap diambil akan muncul di sini.</span>
                </div>
            <?php else: ?>
                <?php foreach ($ready as $o): ?>
                    <div class="notification-row">
                        <div class="notification-symbol ready">
                            <i class="bi bi-bag-check"></i>
                        </div>

                        <div class="notification-main">
                            <strong>
                                <?= notifE($o['nama_pelanggan']) ?>
                                · <?= notifE($o['kode_transaksi']) ?>
                            </strong>
                            <span>
                                <?= notifE($o['paket']) ?> ·
                                <?= notifE($o['berat']) ?> kg
                            </span>
                            <span>Deadline: <?= notifTanggal($o['deadline']) ?></span>
                        </div>

                        <a class="notification-action"
                           href="antrean.php?modal=edit&amp;code=<?= urlencode($o['kode_transaksi']) ?>">
                            <i class="bi bi-pencil-square me-1"></i> Perbarui
                        </a>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </section>

        <!-- Deadline terlewat -->
        <section class="notification-card">
            <div class="notification-card-header">
                <div class="notification-title-row">
                    <h2><i class="bi bi-clock-history" style="color:#d52c40"></i>
                        Deadline terlewat
                    </h2>
                    <span class="notification-count"><?= count($overdue) ?></span>
                </div>
                <p>Transaksi aktif yang sudah melewati estimasi selesai.</p>
            </div>

            <?php if (!$overdue): ?>
                <div class="notification-empty">
                    <i class="bi bi-calendar-check"></i>
                    <strong>Tidak ada deadline terlewat</strong>
                    <span>Semua transaksi aktif masih sesuai jadwal.</span>
                </div>
            <?php else: ?>
                <?php foreach ($overdue as $o): ?>
                    <div class="notification-row">
                        <div class="notification-symbol overdue">
                            <i class="bi bi-exclamation-triangle"></i>
                        </div>

                        <div class="notification-main">
                            <strong>
                                <?= notifE($o['nama_pelanggan']) ?>
                                · <?= notifE($o['kode_transaksi']) ?>
                            </strong>
                            <span>Status: <?= notifE(notifStatus($o['status'])) ?></span>
                            <span>Deadline: <?= notifTanggal($o['deadline']) ?></span>
                        </div>

                        <a class="notification-action"
                           href="antrean.php?modal=edit&amp;code=<?= urlencode($o['kode_transaksi']) ?>">
                            <i class="bi bi-arrow-right-circle me-1"></i> Periksa
                        </a>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </section>
    </div>

    <!-- Aktivitas terbaru -->
    <section class="notification-card notification-activity">
        <div class="notification-card-header">
            <div class="notification-title-row">
                <h2><i class="bi bi-activity"></i> Aktivitas terbaru</h2>
                <span class="notification-count"><?= count($notices) ?></span>
            </div>
            <p>Riwayat notifikasi yang tersimpan di database.</p>
        </div>

        <?php if (!$notices): ?>
            <div class="notification-empty">
                <i class="bi bi-inbox"></i>
                <strong>Belum ada aktivitas</strong>
                <span>Notifikasi akan muncul setelah aktivitas transaksi tercatat.</span>
            </div>
        <?php else: ?>
            <?php foreach ($notices as $n): ?>
                <div class="notification-row <?= empty($n['dibaca']) ? 'notification-unread' : '' ?>">
                    <div class="notification-symbol">
                        <i class="bi bi-bell"></i>
                    </div>

                    <div class="notification-main">
                        <span class="notification-type <?= notifE($n['tipe'] ?? '') ?>">
                            <?= notifE($n['tipe'] ?? 'aktivitas') ?>
                        </span>

                        <strong><?= notifE($n['pesan'] ?? '') ?></strong>

                        <span>
                            <?= notifE($n['kode_transaksi'] ?? 'Transaksi tidak tersedia') ?>
                            <?php if (!empty($n['nama_pelanggan'])): ?>
                                · <?= notifE($n['nama_pelanggan']) ?>
                            <?php endif; ?>
                        </span>

                        <span><?= notifTanggal($n['created_at'] ?? null) ?></span>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </section>

    <div class="notification-note">
        <i class="bi bi-info-circle-fill"></i>
        <span>
            Notifikasi ini ditampilkan di dalam aplikasi. Pengiriman pesan
            WhatsApp belum diaktifkan dan memerlukan integrasi layanan gateway.
        </span>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>