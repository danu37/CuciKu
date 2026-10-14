-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 14, 2026 at 05:59 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `laundryku`
--

-- --------------------------------------------------------

--
-- Table structure for table `admins`
--

CREATE TABLE `admins` (
  `id` int(10) UNSIGNED NOT NULL,
  `nama` varchar(100) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `admins`
--

INSERT INTO `admins` (`id`, `nama`, `username`, `password`, `created_at`, `updated_at`) VALUES
(1, 'Admin Laundry', 'admin', '$2y$10$U2CxKy6Jn8Nm2cvoHXIuo.Zwuyf/as/pBYeuf4NlI4ka6Cj3dSBAG', '2026-10-14 02:12:54', '2026-10-14 02:12:54');

-- --------------------------------------------------------

--
-- Table structure for table `notifikasi`
--

CREATE TABLE `notifikasi` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `transaksi_id` bigint(20) UNSIGNED DEFAULT NULL,
  `tipe` enum('new','ready') NOT NULL,
  `pesan` varchar(255) NOT NULL,
  `dibaca` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `pelanggan`
--

CREATE TABLE `pelanggan` (
  `id_pelanggan` int(10) UNSIGNED NOT NULL,
  `nama` varchar(100) NOT NULL,
  `no_hp` varchar(20) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `alamat` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `pelanggan`
--

INSERT INTO `pelanggan` (`id_pelanggan`, `nama`, `no_hp`, `email`, `password`, `alamat`, `created_at`, `updated_at`) VALUES
(1, 'danu', '0882088789991', 'danu@gmail.nu', '$2y$10$v/pHNlIRag9Nu0d0RXton.rHATCzf4qhhjfaLAdQ/YfuT4SJX7x/W', NULL, '2026-10-14 01:54:41', '2026-10-14 01:54:41');

-- --------------------------------------------------------

--
-- Table structure for table `transaksi`
--

CREATE TABLE `transaksi` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `kode_transaksi` varchar(30) NOT NULL,
  `pelanggan_id` int(10) UNSIGNED DEFAULT NULL,
  `nama_pelanggan` varchar(100) NOT NULL,
  `no_wa` varchar(20) DEFAULT NULL,
  `paket` enum('Reguler','Express') NOT NULL DEFAULT 'Reguler',
  `berat` decimal(6,2) NOT NULL,
  `tarif_per_kg` decimal(12,2) NOT NULL,
  `tanggal_masuk` datetime NOT NULL,
  `deadline` datetime NOT NULL,
  `status` enum('waiting','washing','drying','ironing','ready','completed','cancelled') NOT NULL DEFAULT 'waiting',
  `total_biaya` decimal(12,2) NOT NULL,
  `pembayaran` enum('belum_lunas','lunas') NOT NULL DEFAULT 'belum_lunas',
  `admin_id` int(10) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `transaksi`
--

INSERT INTO `transaksi` (`id`, `kode_transaksi`, `pelanggan_id`, `nama_pelanggan`, `no_wa`, `paket`, `berat`, `tarif_per_kg`, `tanggal_masuk`, `deadline`, `status`, `total_biaya`, `pembayaran`, `admin_id`, `created_at`, `updated_at`) VALUES
(1, 'LD-20261014-A29591', 1, 'danu', '0882088789991', 'Reguler', 1.50, 7000.00, '2026-10-14 09:31:36', '2026-10-16 09:31:36', 'waiting', 10500.00, 'belum_lunas', NULL, '2026-10-14 02:31:36', '2026-10-14 02:31:36'),
(2, 'LD-20261014-D314E4', 1, 'danu', '0882088789991', 'Reguler', 33.00, 7000.00, '2026-10-14 09:40:00', '2026-10-16 09:40:00', 'waiting', 231000.00, 'lunas', NULL, '2026-10-14 02:40:24', '2026-10-14 03:45:39'),
(3, 'LD-20261014-A87CA9', 1, 'danu', '0882088789991', 'Express', 1.20, 14000.00, '2026-10-14 10:46:22', '2026-10-15 10:46:22', 'waiting', 16800.00, 'belum_lunas', NULL, '2026-10-14 03:46:22', '2026-10-14 03:46:22');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admins`
--
ALTER TABLE `admins`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `notifikasi`
--
ALTER TABLE `notifikasi`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_notifikasi_transaksi` (`transaksi_id`),
  ADD KEY `idx_notifikasi_dibaca` (`dibaca`);

--
-- Indexes for table `pelanggan`
--
ALTER TABLE `pelanggan`
  ADD PRIMARY KEY (`id_pelanggan`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `transaksi`
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
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admins`
--
ALTER TABLE `admins`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `notifikasi`
--
ALTER TABLE `notifikasi`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `pelanggan`
--
ALTER TABLE `pelanggan`
  MODIFY `id_pelanggan` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `transaksi`
--
ALTER TABLE `transaksi`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `notifikasi`
--
ALTER TABLE `notifikasi`
  ADD CONSTRAINT `fk_notifikasi_transaksi` FOREIGN KEY (`transaksi_id`) REFERENCES `transaksi` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `transaksi`
--
ALTER TABLE `transaksi`
  ADD CONSTRAINT `fk_transaksi_admin` FOREIGN KEY (`admin_id`) REFERENCES `admins` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_transaksi_pelanggan` FOREIGN KEY (`pelanggan_id`) REFERENCES `pelanggan` (`id_pelanggan`) ON DELETE SET NULL ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
