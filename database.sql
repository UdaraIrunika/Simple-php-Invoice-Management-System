-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 06, 2025 at 11:45 PM
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
-- Database: `royal_travel_invoices`
--
CREATE DATABASE IF NOT EXISTS `royal_travel_invoices` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `royal_travel_invoices`;

-- --------------------------------------------------------

--
-- Table structure for table `booking`
--

CREATE TABLE `booking` (
  `booking_id` int(11) NOT NULL,
  `user_email` varchar(100) NOT NULL,
  `package_id` int(11) NOT NULL,
  `package_name` varchar(255) NOT NULL,
  `from_date` date NOT NULL,
  `to_date` date NOT NULL,
  `status` enum('pending','confirmed','cancelled') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `booking`
--

INSERT INTO `booking` (`booking_id`, `user_email`, `package_id`, `package_name`, `from_date`, `to_date`, `status`, `created_at`) VALUES
(1, 'customer1@example.com', 1, 'Bali Paradise Tour', '2023-07-15', '2023-07-22', 'confirmed', '2025-11-06 20:29:28'),
(2, 'customer2@example.com', 2, 'European Adventure', '2023-08-10', '2023-08-20', 'confirmed', '2025-11-06 20:29:28'),
(3, 'customer3@example.com', 3, 'Thailand Explorer', '2023-09-05', '2023-09-15', 'pending', '2025-11-06 20:29:28'),
(4, 'test@hgd.com', 2, 'European Adventure', '2025-11-06', '2025-11-13', 'confirmed', '2025-11-06 20:45:01');

-- --------------------------------------------------------

--
-- Table structure for table `invoice`
--

CREATE TABLE `invoice` (
  `invoice_id` int(11) NOT NULL,
  `invoice_number` varchar(50) NOT NULL,
  `booking_id` int(11) DEFAULT NULL,
  `invoice_date` date NOT NULL,
  `customer_name` varchar(255) NOT NULL,
  `customer_email` varchar(255) NOT NULL,
  `package_name` varchar(255) NOT NULL,
  `package_price` decimal(10,2) NOT NULL,
  `tax` decimal(5,2) DEFAULT 0.00,
  `discount` decimal(5,2) DEFAULT 0.00,
  `total_amount` decimal(10,2) NOT NULL,
  `payment_status` enum('pending','paid','overdue') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `invoice`
--

INSERT INTO `invoice` (`invoice_id`, `invoice_number`, `booking_id`, `invoice_date`, `customer_name`, `customer_email`, `package_name`, `package_price`, `tax`, `discount`, `total_amount`, `payment_status`, `created_at`) VALUES
(1, 'RTT-INV-0001', 1, '2025-11-06', 'Customer1', 'customer1@example.com', 'Bali Paradise Tour', 1000.00, 10.00, 50.00, 1050.00, 'pending', '2025-11-06 20:45:53');

-- --------------------------------------------------------

--
-- Table structure for table `invoice_items`
--

CREATE TABLE `invoice_items` (
  `item_id` int(11) NOT NULL,
  `invoice_id` int(11) NOT NULL,
  `description` varchar(255) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `unit_price` decimal(10,2) NOT NULL,
  `subtotal` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `setting_id` int(11) NOT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `settings`
--

INSERT INTO `settings` (`setting_id`, `setting_key`, `setting_value`, `updated_at`) VALUES
(1, 'company_name', 'UI DESIGNERS AND DEVELOPERS', '2025-11-06 22:44:17'),
(2, 'company_address', 'https://github.com/UdaraIrunika/', '2025-11-06 22:41:45'),
(3, 'company_phone', '+94764353012', '2025-11-06 21:04:01'),
(4, 'company_email', 'uiindustryprivetlimited@gmail.com', '2025-11-06 22:41:45'),
(5, 'currency', 'USD', '2025-11-06 20:29:28'),
(6, 'tax_rate', NULL, '2025-11-06 21:04:01'),
(7, 'invoice_prefix', NULL, '2025-11-06 21:04:01'),
(15, 'smtp_host', '', '2025-11-06 21:04:01'),
(16, 'smtp_port', '587', '2025-11-06 21:04:01'),
(17, 'smtp_username', '', '2025-11-06 21:04:01'),
(18, 'smtp_password', '', '2025-11-06 21:04:01'),
(19, 'email_from_name', '', '2025-11-06 21:04:01'),
(20, 'invoice_footer', '', '2025-11-06 21:04:01');

-- --------------------------------------------------------

--
-- Table structure for table `system_log`
--

CREATE TABLE `system_log` (
  `log_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(50) NOT NULL,
  `action_details` text DEFAULT NULL,
  `performed_by` varchar(100) DEFAULT NULL,
  `performed_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `system_log`
--

INSERT INTO `system_log` (`log_id`, `user_id`, `action`, `action_details`, `performed_by`, `performed_at`) VALUES
(1, 1, 'booking_create', 'Booking #4 created for test@hgd.com', 'admin', '2025-11-06 20:45:01'),
(2, 1, 'booking_update', 'Booking #4 updated', 'admin', '2025-11-06 20:45:20'),
(3, 1, 'invoice_create', 'Invoice RTT-INV-0001 created', 'admin', '2025-11-06 20:45:53'),
(4, 1, 'settings_update', 'System settings updated', 'admin', '2025-11-06 21:04:01'),
(5, 1, 'logout', 'User logged out', 'admin', '2025-11-06 22:36:32'),
(6, 1, 'login', 'User logged in successfully', 'admin', '2025-11-06 22:36:36'),
(7, 1, 'logout', 'User logged out', 'admin', '2025-11-06 22:36:40'),
(8, 1, 'login', 'User logged in successfully', 'admin', '2025-11-06 22:37:57'),
(9, 1, 'settings_update', 'System settings updated', 'admin', '2025-11-06 22:41:45'),
(10, 1, 'logout', 'User logged out', 'admin', '2025-11-06 22:43:56'),
(11, 1, 'login', 'User logged in successfully', 'admin', '2025-11-06 22:44:01'),
(12, 1, 'settings_update', 'System settings updated', 'admin', '2025-11-06 22:44:18'),
(13, 1, 'logout', 'User logged out', 'admin', '2025-11-06 22:44:29');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','staff') DEFAULT 'staff',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `username`, `email`, `password`, `role`, `created_at`) VALUES
(1, 'admin', 'admin@royaltravel.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', '2025-11-06 20:29:28');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `booking`
--
ALTER TABLE `booking`
  ADD PRIMARY KEY (`booking_id`);

--
-- Indexes for table `invoice`
--
ALTER TABLE `invoice`
  ADD PRIMARY KEY (`invoice_id`),
  ADD UNIQUE KEY `invoice_number` (`invoice_number`),
  ADD KEY `booking_id` (`booking_id`);

--
-- Indexes for table `invoice_items`
--
ALTER TABLE `invoice_items`
  ADD PRIMARY KEY (`item_id`),
  ADD KEY `invoice_id` (`invoice_id`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`setting_id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`);

--
-- Indexes for table `system_log`
--
ALTER TABLE `system_log`
  ADD PRIMARY KEY (`log_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `booking`
--
ALTER TABLE `booking`
  MODIFY `booking_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `invoice`
--
ALTER TABLE `invoice`
  MODIFY `invoice_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `invoice_items`
--
ALTER TABLE `invoice_items`
  MODIFY `item_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `settings`
--
ALTER TABLE `settings`
  MODIFY `setting_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=47;

--
-- AUTO_INCREMENT for table `system_log`
--
ALTER TABLE `system_log`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `invoice`
--
ALTER TABLE `invoice`
  ADD CONSTRAINT `invoice_ibfk_1` FOREIGN KEY (`booking_id`) REFERENCES `booking` (`booking_id`) ON DELETE SET NULL;

--
-- Constraints for table `invoice_items`
--
ALTER TABLE `invoice_items`
  ADD CONSTRAINT `invoice_items_ibfk_1` FOREIGN KEY (`invoice_id`) REFERENCES `invoice` (`invoice_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
