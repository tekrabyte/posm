-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: Oct 30, 2025 at 02:26 PM
-- Server version: 11.8.3-MariaDB-log
-- PHP Version: 7.2.34

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `u215947863_pom`
--

-- --------------------------------------------------------

--
-- Table structure for table `activity_log`
--

CREATE TABLE `activity_log` (
  `id` int(11) NOT NULL,
  `activity` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `bbm_distribution`
--

CREATE TABLE `bbm_distribution` (
  `id` int(11) NOT NULL,
  `bbm_group_id` int(11) DEFAULT NULL,
  `store_id` int(11) DEFAULT NULL,
  `jumlah_drigen` decimal(10,2) DEFAULT NULL,
  `pajak` decimal(12,2) DEFAULT NULL,
  `beban` decimal(15,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `bbm_distribution_details`
--

CREATE TABLE `bbm_distribution_details` (
  `id` int(11) NOT NULL,
  `bbm_transaction_id` int(11) DEFAULT NULL,
  `store_id` int(11) DEFAULT NULL,
  `jumlah_drigen` int(11) DEFAULT NULL,
  `subtotal` decimal(15,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `bbm_transactions`
--

CREATE TABLE `bbm_transactions` (
  `id` int(11) NOT NULL,
  `tanggal` date NOT NULL,
  `total_amount` decimal(15,2) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cash_flow_management`
--

CREATE TABLE `cash_flow_management` (
  `id` int(11) NOT NULL,
  `bbm_group_id` int(11) DEFAULT NULL,
  `tanggal` date NOT NULL,
  `store_id` int(11) NOT NULL,
  `description` varchar(255) NOT NULL,
  `amount` int(11) NOT NULL,
  `type` enum('Pemasukan','Pengeluaran') NOT NULL,
  `category` varchar(50) DEFAULT 'lainnya',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `cash_flow_management`
--

INSERT INTO `cash_flow_management` (`id`, `bbm_group_id`, `tanggal`, `store_id`, `description`, `amount`, `type`, `category`, `notes`, `created_at`, `updated_at`) VALUES
(32, NULL, '2025-10-29', 5, 'Pembelian BBM 8 Drigen - Tiban Hills', 2818462, 'Pengeluaran', 'bbm', NULL, '2025-10-29 10:17:06', '2025-10-29 10:17:06'),
(33, NULL, '2025-10-29', 6, 'Pembelian BBM 5 Drigen - Patam Lestari', 1761538, 'Pengeluaran', 'bbm', NULL, '2025-10-29 10:17:06', '2025-10-29 10:17:06');

-- --------------------------------------------------------

--
-- Table structure for table `employees`
--

CREATE TABLE `employees` (
  `id` int(11) NOT NULL,
  `employee_name` varchar(100) NOT NULL,
  `employee_code` varchar(50) DEFAULT NULL,
  `store_id` int(11) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `employees`
--

INSERT INTO `employees` (`id`, `employee_name`, `employee_code`, `store_id`, `is_active`, `created_at`, `updated_at`) VALUES
(6, 'Putri', 'PTM001', 6, 1, '2025-10-27 20:57:31', '2025-10-27 20:57:46'),
(7, 'Zulaika', 'PTM002', 6, 1, '2025-10-27 20:59:05', '2025-10-27 20:59:05'),
(8, 'Ayu', 'TBNH001', 5, 1, '2025-10-27 20:59:05', '2025-10-27 20:59:05'),
(9, 'Yosef', 'TBNH002', 5, 1, '2025-10-27 20:59:05', '2025-10-27 20:59:05');

-- --------------------------------------------------------

--
-- Table structure for table `pemasukan`
--

CREATE TABLE `pemasukan` (
  `id` int(11) NOT NULL,
  `setoran_id` int(11) NOT NULL,
  `description` varchar(255) NOT NULL,
  `amount` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `pengeluaran`
--

CREATE TABLE `pengeluaran` (
  `id` int(11) NOT NULL,
  `setoran_id` int(11) NOT NULL,
  `description` varchar(255) NOT NULL,
  `amount` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `pengeluaran`
--

INSERT INTO `pengeluaran` (`id`, `setoran_id`, `description`, `amount`, `created_at`) VALUES
(8, 16, 'Bg saka', 100000, '2025-10-29 16:51:59');

-- --------------------------------------------------------

--
-- Table structure for table `setoran`
--

CREATE TABLE `setoran` (
  `id` int(11) NOT NULL,
  `tanggal` date NOT NULL,
  `employee_id` int(11) DEFAULT NULL,
  `employee_name` varchar(100) DEFAULT NULL,
  `store_id` int(11) DEFAULT NULL,
  `store_name` varchar(100) DEFAULT NULL,
  `jam_masuk` time NOT NULL,
  `jam_keluar` time NOT NULL,
  `nomor_awal` decimal(10,2) NOT NULL,
  `nomor_akhir` decimal(10,2) NOT NULL,
  `total_liter` decimal(10,2) NOT NULL,
  `qris` int(11) NOT NULL DEFAULT 0,
  `cash` int(11) NOT NULL,
  `total_setoran` int(11) NOT NULL,
  `total_pengeluaran` int(11) NOT NULL DEFAULT 0,
  `total_pemasukan` int(11) NOT NULL DEFAULT 0,
  `total_keseluruhan` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `setoran`
--

INSERT INTO `setoran` (`id`, `tanggal`, `employee_id`, `employee_name`, `store_id`, `store_name`, `jam_masuk`, `jam_keluar`, `nomor_awal`, `nomor_akhir`, `total_liter`, `qris`, `cash`, `total_setoran`, `total_pengeluaran`, `total_pemasukan`, `total_keseluruhan`, `created_at`, `updated_at`) VALUES
(7, '2025-10-28', 8, 'Ayu', 5, 'Tiban Hills', '06:30:00', '14:30:00', 1740.31, 1907.80, 167.49, 116000, 1810135, 1926135, 0, 0, 1810135, '2025-10-27 21:00:15', '2025-10-28 07:31:51'),
(9, '2025-10-28', 6, 'Putri', 6, 'Patam Lestari', '05:57:00', '14:00:00', 6669.41, 6749.32, 79.91, 95000, 823965, 918965, 0, 0, 823965, '2025-10-28 07:00:59', '2025-10-28 07:06:07'),
(10, '2025-10-28', 7, 'Zulaika', 6, 'Patam Lestari', '14:00:00', '22:26:00', 6749.32, 6864.69, 115.37, 105000, 1221755, 1326755, 0, 0, 1221755, '2025-10-28 15:30:07', '2025-10-28 16:23:24'),
(11, '2025-10-28', 9, 'Yosef', 5, 'Tiban Hills', '14:30:00', '23:09:00', 2019.87, 2143.02, 123.15, 95000, 1321225, 1416225, 0, 0, 1321225, '2025-10-28 17:32:12', '2025-10-28 19:45:11'),
(14, '2025-10-29', 7, 'Zulaika', 6, 'Patam Lestari', '05:57:00', '14:00:00', 6864.69, 6946.89, 82.20, 61000, 884300, 945300, 0, 0, 884300, '2025-10-29 06:58:07', '2025-10-29 06:58:07'),
(15, '2025-10-29', 8, 'Ayu', 5, 'Tiban Hills', '06:30:00', '14:32:00', 2143.02, 2315.97, 172.95, 199000, 1789925, 1988925, 0, 0, 1789925, '2025-10-29 07:31:13', '2025-10-29 07:33:05'),
(16, '2025-10-29', 6, 'Putri', 6, 'Patam Lestari', '13:55:00', '22:26:00', 6946.89, 7087.78, 140.89, 35000, 1585235, 1620235, 100000, 0, 1485235, '2025-10-29 15:16:58', '2025-10-29 16:51:59'),
(17, '2025-10-30', 7, 'Zulaika', 6, 'Patam Lestari', '05:56:00', '14:00:00', 7087.78, 7179.94, 92.16, 68000, 991840, 1059840, 0, 0, 991840, '2025-10-30 07:00:04', '2025-10-30 07:00:04'),
(18, '2025-10-30', 8, 'Ayu', 5, 'Tiban Hills', '06:29:00', '14:30:00', 2444.74, 2588.51, 143.77, 104000, 1549355, 1653355, 0, 0, 1549355, '2025-10-30 07:31:01', '2025-10-30 07:31:01');

-- --------------------------------------------------------

--
-- Table structure for table `stores`
--

CREATE TABLE `stores` (
  `id` int(11) NOT NULL,
  `store_name` varchar(100) NOT NULL,
  `address` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `stores`
--

INSERT INTO `stores` (`id`, `store_name`, `address`, `created_at`, `updated_at`) VALUES
(5, 'Tiban Hills', 'Tiban Hills', '2025-10-27 20:57:12', '2025-10-27 20:57:12'),
(6, 'Patam Lestari', 'Patam Lestari', '2025-10-27 20:57:12', '2025-10-27 20:57:12');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','user') NOT NULL DEFAULT 'user',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `role`, `created_at`) VALUES
(1, 'super', 'super', 'admin', '2025-10-21 18:12:30');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activity_log`
--
ALTER TABLE `activity_log`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `bbm_distribution`
--
ALTER TABLE `bbm_distribution`
  ADD PRIMARY KEY (`id`),
  ADD KEY `store_id` (`store_id`),
  ADD KEY `bbm_group_id` (`bbm_group_id`);

--
-- Indexes for table `bbm_distribution_details`
--
ALTER TABLE `bbm_distribution_details`
  ADD PRIMARY KEY (`id`),
  ADD KEY `bbm_transaction_id` (`bbm_transaction_id`),
  ADD KEY `store_id` (`store_id`);

--
-- Indexes for table `bbm_transactions`
--
ALTER TABLE `bbm_transactions`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `cash_flow_management`
--
ALTER TABLE `cash_flow_management`
  ADD PRIMARY KEY (`id`),
  ADD KEY `store_id` (`store_id`),
  ADD KEY `tanggal` (`tanggal`);

--
-- Indexes for table `employees`
--
ALTER TABLE `employees`
  ADD PRIMARY KEY (`id`),
  ADD KEY `store_id` (`store_id`);

--
-- Indexes for table `pemasukan`
--
ALTER TABLE `pemasukan`
  ADD PRIMARY KEY (`id`),
  ADD KEY `setoran_id` (`setoran_id`);

--
-- Indexes for table `pengeluaran`
--
ALTER TABLE `pengeluaran`
  ADD PRIMARY KEY (`id`),
  ADD KEY `setoran_id` (`setoran_id`);

--
-- Indexes for table `setoran`
--
ALTER TABLE `setoran`
  ADD PRIMARY KEY (`id`),
  ADD KEY `employee_id` (`employee_id`),
  ADD KEY `store_id` (`store_id`);

--
-- Indexes for table `stores`
--
ALTER TABLE `stores`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activity_log`
--
ALTER TABLE `activity_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `bbm_distribution`
--
ALTER TABLE `bbm_distribution`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `bbm_distribution_details`
--
ALTER TABLE `bbm_distribution_details`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `bbm_transactions`
--
ALTER TABLE `bbm_transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `cash_flow_management`
--
ALTER TABLE `cash_flow_management`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=34;

--
-- AUTO_INCREMENT for table `employees`
--
ALTER TABLE `employees`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `pemasukan`
--
ALTER TABLE `pemasukan`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `pengeluaran`
--
ALTER TABLE `pengeluaran`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `setoran`
--
ALTER TABLE `setoran`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `stores`
--
ALTER TABLE `stores`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `bbm_distribution`
--
ALTER TABLE `bbm_distribution`
  ADD CONSTRAINT `bbm_distribution_ibfk_1` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`),
  ADD CONSTRAINT `bbm_distribution_ibfk_2` FOREIGN KEY (`bbm_group_id`) REFERENCES `cash_flow_management` (`id`);

--
-- Constraints for table `bbm_distribution_details`
--
ALTER TABLE `bbm_distribution_details`
  ADD CONSTRAINT `bbm_distribution_details_ibfk_1` FOREIGN KEY (`bbm_transaction_id`) REFERENCES `bbm_transactions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `bbm_distribution_details_ibfk_2` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `cash_flow_management`
--
ALTER TABLE `cash_flow_management`
  ADD CONSTRAINT `cash_flow_management_ibfk_1` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `employees`
--
ALTER TABLE `employees`
  ADD CONSTRAINT `employees_ibfk_1` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `pemasukan`
--
ALTER TABLE `pemasukan`
  ADD CONSTRAINT `pemasukan_ibfk_1` FOREIGN KEY (`setoran_id`) REFERENCES `setoran` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `pengeluaran`
--
ALTER TABLE `pengeluaran`
  ADD CONSTRAINT `pengeluaran_ibfk_1` FOREIGN KEY (`setoran_id`) REFERENCES `setoran` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `setoran`
--
ALTER TABLE `setoran`
  ADD CONSTRAINT `setoran_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `setoran_ibfk_2` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
