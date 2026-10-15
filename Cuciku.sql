-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Waktu pembuatan: 15 Okt 2026 pada 05.44
-- Versi server: 8.0.30
-- Versi PHP: 8.4.10

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Basis data: `laundryku`
--

-- --------------------------------------------------------

--
-- Struktur dari tabel `admins`
--

CREATE TABLE `admins` (
  `id` int UNSIGNED NOT NULL,
  `nama` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `username` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `admins`
--

INSERT INTO `admins` (`id`, `nama`, `username`, `password`, `created_at`, `updated_at`) VALUES
(1, 'Admin Laundry', 'admin', '$2y$10$0JH83axzMOsc81XfILPewe9M8rZlB22xYnFwLEyLwL3aE8QJf2.3K', '2026-10-14 02:12:54', '2026-10-14 06:11:08');

-- --------------------------------------------------------

--
-- Struktur dari tabel `notifikasi`
--

CREATE TABLE `notifikasi` (
  `id` bigint UNSIGNED NOT NULL,
  `transaksi_id` bigint UNSIGNED DEFAULT NULL,
  `tipe` enum('new','ready') COLLATE utf8mb4_unicode_ci NOT NULL,
  `pesan` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `dibaca` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `notifikasi`
--

INSERT INTO `notifikasi` (`id`, `transaksi_id`, `tipe`, `pesan`, `dibaca`, `created_at`) VALUES
(1, NULL, 'new', 'Order baru dibuat untuk E2E Modal Tester', 0, '2026-10-14 12:48:09'),
(2, 7, 'new', 'Order baru dibuat untuk danu', 0, '2026-10-14 13:26:34'),
(4, 9, 'ready', 'Cucian siap diambil: 10. DANU ANGGO AJI PRASETYO', 0, '2026-10-14 21:40:51'),
(6, 7, 'ready', 'Cucian siap diambil: danu', 0, '2026-10-15 00:18:31'),
(7, 13, 'new', 'Order baru dibuat untuk Mas Marcel', 0, '2026-10-15 01:24:49'),
(8, 13, 'ready', 'Cucian siap diambil: Mas Marcel', 0, '2026-10-15 01:26:31');

-- --------------------------------------------------------

--
-- Struktur dari tabel `pelanggan`
--

CREATE TABLE `pelanggan` (
  `id_pelanggan` int UNSIGNED NOT NULL,
  `nama` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `no_hp` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `alamat` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `pelanggan`
--

INSERT INTO `pelanggan` (`id_pelanggan`, `nama`, `no_hp`, `email`, `password`, `alamat`, `created_at`, `updated_at`) VALUES
(1, 'danu', '0882088789991', 'danu@gmail.nu', '$2y$10$v/pHNlIRag9Nu0d0RXton.rHATCzf4qhhjfaLAdQ/YfuT4SJX7x/W', NULL, '2026-10-14 01:54:41', '2026-10-14 01:54:41'),
(3, '10. DANU ANGGO AJI PRASETYO', '08886863707', 'danuanggoajiprasetyo@gmail.com', '$2y$12$XW5YU0qTauyeODgk3MOP3uQsO48wv/B2ap/xfXw.z4QRCK7AAJQ1a', NULL, '2026-10-14 06:31:26', '2026-10-14 06:31:26'),
(4, 'DanuTest', '08999999999', 'danutest@test.com', '$2y$12$pA3kSg/NCfOf7sCloyzaHOBHtZsT.HvOb7rBfpttMBDGlFkqIRNPO', NULL, '2026-10-14 06:42:22', '2026-10-14 06:42:22'),
(5, '10. DANU ANGGO AJI PRASETYO', '085731637000', 'danu@gmail.com', '$2y$12$YQhWZ9plrVwx188DWxAKp.aANh3WoNbebhah5qe/TQKnfsr3FTvK2', NULL, '2026-10-14 20:59:42', '2026-10-14 20:59:42'),
(7, 'Budi', '08887573834', 'budi@gmail.com', '$2y$12$nuuoBtuYjq51B.8Ui25F1.bqjNHSs3uRmqC6kQWncYdXsDdAjkzue', 'Ponorogo Jatim', '2026-10-15 00:48:15', '2026-10-15 00:48:15'),
(9, 'Mas Marcel', '0888888888', 'marcel@gmail.com', '$2y$12$oMIbPl5BM31e3d5ZHSZZl.jbXtOypbJi6wuVCkxaDtvoaHmn11Cim', NULL, '2026-10-15 01:23:45', '2026-10-15 01:23:45');

-- --------------------------------------------------------

--
-- Struktur dari tabel `transaksi`
--

CREATE TABLE `transaksi` (
  `id` bigint UNSIGNED NOT NULL,
  `kode_transaksi` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `pelanggan_id` int UNSIGNED DEFAULT NULL,
  `nama_pelanggan` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `no_wa` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `paket` enum('Reguler','Express') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Reguler',
  `berat` decimal(6,2) NOT NULL,
  `tarif_per_kg` decimal(12,2) NOT NULL,
  `tanggal_masuk` datetime NOT NULL,
  `deadline` datetime NOT NULL,
  `status` enum('waiting','washing','drying','ironing','ready','completed','cancelled') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'waiting',
  `total_biaya` decimal(12,2) NOT NULL,
  `pembayaran` enum('belum_lunas','lunas') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'belum_lunas',
  `admin_id` int UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `transaksi`
--

INSERT INTO `transaksi` (`id`, `kode_transaksi`, `pelanggan_id`, `nama_pelanggan`, `no_wa`, `paket`, `berat`, `tarif_per_kg`, `tanggal_masuk`, `deadline`, `status`, `total_biaya`, `pembayaran`, `admin_id`, `created_at`, `updated_at`) VALUES
(1, 'LD-20261014-A29591', 1, 'danu', '0882088789991', 'Reguler', 1.50, 7000.00, '2026-10-14 09:31:36', '2026-10-16 09:31:36', 'waiting', 10500.00, 'belum_lunas', NULL, '2026-10-14 02:31:36', '2026-10-14 02:31:36'),
(2, 'LD-20261014-D314E4', 1, 'danu', '0882088789991', 'Reguler', 33.00, 7000.00, '2026-10-14 09:40:00', '2026-10-16 09:40:00', 'waiting', 231000.00, 'belum_lunas', NULL, '2026-10-14 02:40:24', '2026-10-14 20:55:05'),
(3, 'LD-20261014-A87CA9', 1, 'danu', '0882088789991', 'Express', 1.20, 14000.00, '2026-10-14 10:46:22', '2026-10-15 10:46:22', 'completed', 16800.00, 'lunas', NULL, '2026-10-14 03:46:22', '2026-10-14 21:38:52'),
(7, 'LD-20261014-E8EC86', 1, 'danu', '0882088789991', 'Reguler', 33.00, 7000.00, '2026-10-14 20:26:00', '2026-10-16 20:26:00', 'completed', 231000.00, 'belum_lunas', 1, '2026-10-14 13:26:34', '2026-10-15 00:18:35'),
(9, 'LD-20261015-E7778F', 5, '10. DANU ANGGO AJI PRASETYO', '085731637000', 'Express', 45.00, 14000.00, '2026-10-15 04:00:00', '2026-10-15 16:00:00', 'ready', 630000.00, 'lunas', NULL, '2026-10-14 21:00:21', '2026-10-14 21:40:51'),
(13, 'LD-20261015-4C16CF', 9, 'Mas Marcel', '0888888888', 'Express', 25.00, 14000.00, '2026-10-15 08:23:00', '2026-10-15 20:23:00', 'ready', 350000.00, 'lunas', 1, '2026-10-15 01:24:49', '2026-10-15 01:26:31');

--
-- Indeks untuk tabel yang dibuang
--

--
-- Indeks untuk tabel `admins`
--
ALTER TABLE `admins`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indeks untuk tabel `notifikasi`
--
ALTER TABLE `notifikasi`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_notifikasi_transaksi` (`transaksi_id`),
  ADD KEY `idx_notifikasi_dibaca` (`dibaca`);

--
-- Indeks untuk tabel `pelanggan`
--
ALTER TABLE `pelanggan`
  ADD PRIMARY KEY (`id_pelanggan`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `no_hp` (`no_hp`);

--
-- Indeks untuk tabel `transaksi`
--
ALTER TABLE `transaksi`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `kode_transaksi` (`kode_transaksi`),
  ADD KEY `fk_transaksi_admin` (`admin_id`),
  ADD KEY `idx_transaksi_status` (`status`),
  ADD KEY `idx_transaksi_deadline` (`deadline`),
  ADD KEY `idx_transaksi_tanggal` (`tanggal_masuk`),
  ADD KEY `fk_transaksi_pelanggan` (`pelanggan_id`);

--
-- AUTO_INCREMENT untuk tabel yang dibuang
--

--
-- AUTO_INCREMENT untuk tabel `admins`
--
ALTER TABLE `admins`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT untuk tabel `notifikasi`
--
ALTER TABLE `notifikasi`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT untuk tabel `pelanggan`
--
ALTER TABLE `pelanggan`
  MODIFY `id_pelanggan` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT untuk tabel `transaksi`
--
ALTER TABLE `transaksi`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- Ketidakleluasaan untuk tabel pelimpahan (Dumped Tables)
--

--
-- Ketidakleluasaan untuk tabel `notifikasi`
--
ALTER TABLE `notifikasi`
  ADD CONSTRAINT `fk_notifikasi_transaksi` FOREIGN KEY (`transaksi_id`) REFERENCES `transaksi` (`id`) ON DELETE SET NULL;

--
-- Ketidakleluasaan untuk tabel `transaksi`
--
ALTER TABLE `transaksi`
  ADD CONSTRAINT `fk_transaksi_admin` FOREIGN KEY (`admin_id`) REFERENCES `admins` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_transaksi_pelanggan` FOREIGN KEY (`pelanggan_id`) REFERENCES `pelanggan` (`id_pelanggan`) ON DELETE SET NULL ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
