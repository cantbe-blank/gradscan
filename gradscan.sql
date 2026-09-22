-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Sep 22, 2026 at 01:51 PM
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
-- Database: `gradscan`
--

-- --------------------------------------------------------

--
-- Table structure for table `graduate`
--

CREATE TABLE `graduate` (
  `graduate_id` int(11) NOT NULL,
  `school_id` int(11) NOT NULL,
  `student_id` varchar(20) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `middle_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) NOT NULL,
  `suffix` varchar(10) DEFAULT NULL,
  `course` varchar(100) NOT NULL,
  `major` varchar(100) DEFAULT NULL,
  `address` varchar(255) NOT NULL,
  `honors` enum('none','cum laude','magna cum laude','summa cum laude') NOT NULL DEFAULT 'none',
  `graduation_year` int(11) NOT NULL,
  `photo` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `graduate`
--

INSERT INTO `graduate` (`graduate_id`, `school_id`, `student_id`, `first_name`, `middle_name`, `last_name`, `suffix`, `course`, `major`, `address`, `honors`, `graduation_year`, `photo`, `created_at`, `updated_at`) VALUES
(1, 1, '23-01-2405', 'John Airo', 'Camiguing', 'Castro', '', 'BSIT', 'AP', 'Brgy 02, Casiguran, Aurora', 'cum laude', 2027, 'photos/photo_1.jpg', '2026-09-14 22:36:43', '2026-09-14 22:36:43'),
(2, 1, '23-01-2406', 'Arwin', '', 'Magno', '', 'BSIT', 'DD', 'Brgy. Cozo, Casiguran, Aurora', 'magna cum laude', 2028, 'photos/photo_2.jpg', '2026-09-14 22:45:27', '2026-09-14 22:45:27'),
(4, 1, '23-01-2407', 'John Airo', 'Santolan', 'Castro', '', 'BSIT', 'AP', 'Brgy. dos, Casiguran, Aurora', 'none', 2027, 'photos/photo_4.jpg', '2026-09-17 16:18:53', '2026-09-17 16:18:53'),
(5, 1, '23-01-2408', 'John Hiro', 'Camiguing', 'Castrosss', '', 'BSIT', 'AP', 'asf;lkdsajf;klfjdsf', 'magna cum laude', 2027, 'photos/photo_5.jpg', '2026-09-17 17:14:05', '2026-09-20 18:04:33'),
(6, 1, '23-01-0031', 'Gabriel', 'Baguio', 'Bitong', 'III', 'BSIT', 'AP', 'BRGY. 04', 'none', 2027, 'photos/photo_6.jpg', '2026-09-18 15:54:52', '2026-09-20 18:04:33');

-- --------------------------------------------------------

--
-- Table structure for table `layout`
--

CREATE TABLE `layout` (
  `layout_id` int(11) NOT NULL,
  `school_id` int(11) DEFAULT NULL,
  `template_id` int(11) DEFAULT NULL,
  `layout_name` varchar(100) NOT NULL,
  `layout_config` text NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `layout`
--

INSERT INTO `layout` (`layout_id`, `school_id`, `template_id`, `layout_name`, `layout_config`, `is_active`, `created_at`, `updated_at`) VALUES
(1, NULL, NULL, 'Default Template', '{\"background_color\":\"#1e293b\",\"text_color\":\"#ffffff\",\"accent_color\":\"#3b82f6\",\"header_text\":\"Congratulations Graduates!\",\"show_photo\":true}', 1, '2026-09-14 21:19:06', '2026-09-14 21:19:06'),
(2, 1, 1, 'My Layout', '{\"background_color\":\"#1e293b\",\"text_color\":\"#e46f21\",\"accent_color\":\"#db9824\",\"header_text\":\"Congratulations\",\"show_photo\":true}', 1, '2026-09-14 22:45:56', '2026-09-14 22:46:35');

-- --------------------------------------------------------

--
-- Table structure for table `qr_code`
--

CREATE TABLE `qr_code` (
  `qr_id` int(11) NOT NULL,
  `graduate_id` int(11) NOT NULL,
  `qr_token` varchar(255) NOT NULL,
  `qr_status` enum('active','expired','used','invalidated') NOT NULL DEFAULT 'active',
  `issued_at` datetime DEFAULT current_timestamp(),
  `expires_at` datetime DEFAULT NULL,
  `used_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `qr_code`
--

INSERT INTO `qr_code` (`qr_id`, `graduate_id`, `qr_token`, `qr_status`, `issued_at`, `expires_at`, `used_at`) VALUES
(1, 2, 'cc0c5de86abc927435a53f8082021c05', 'used', '2026-09-14 22:45:27', NULL, '2026-09-21 14:54:27'),
(3, 4, '643d71ae4e77b2bb86b5c3807e8edafc', 'used', '2026-09-17 16:18:53', NULL, '2026-09-21 14:54:48'),
(4, 5, '0625e135f4861f8b0bb5c1b71a80e66d', 'used', '2026-09-17 17:14:05', NULL, '2026-09-21 14:55:01'),
(5, 6, '91bd8aa5d5d2ee717637955745292b69', 'used', '2026-09-18 15:54:53', NULL, '2026-09-21 14:55:09');

-- --------------------------------------------------------

--
-- Table structure for table `scan_log`
--

CREATE TABLE `scan_log` (
  `scan_id` int(11) NOT NULL,
  `qr_id` int(11) DEFAULT NULL,
  `operator_id` int(11) DEFAULT NULL,
  `scan_status` enum('success','used','expired','invalidated','not_found') NOT NULL,
  `scanned_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `scan_log`
--

INSERT INTO `scan_log` (`scan_id`, `qr_id`, `operator_id`, `scan_status`, `scanned_at`) VALUES
(3, 3, 2, 'success', '2026-09-20 16:54:23'),
(4, 3, 2, 'used', '2026-09-20 16:54:36'),
(5, 3, 2, 'used', '2026-09-20 16:54:42'),
(6, 3, 2, 'used', '2026-09-20 16:54:47'),
(7, 3, 2, 'used', '2026-09-20 16:55:10'),
(8, 3, 2, 'success', '2026-09-20 16:57:03'),
(9, 3, 2, 'used', '2026-09-20 16:57:43'),
(10, 4, 2, 'success', '2026-09-20 16:57:48'),
(11, 4, 2, 'used', '2026-09-20 16:57:55'),
(12, 3, 2, 'used', '2026-09-20 16:57:56'),
(13, 3, 2, 'used', '2026-09-20 16:58:17'),
(14, 3, 2, 'success', '2026-09-20 16:58:44'),
(15, 4, 2, 'used', '2026-09-20 16:58:48'),
(16, 3, 2, 'used', '2026-09-20 16:58:51'),
(17, 4, 2, 'used', '2026-09-20 16:58:57'),
(18, 4, 2, 'invalidated', '2026-09-20 17:00:36'),
(19, 3, 2, 'used', '2026-09-20 17:00:45'),
(20, 3, 2, 'success', '2026-09-20 17:00:58'),
(21, 4, 2, 'invalidated', '2026-09-20 17:01:11'),
(22, 3, 2, 'used', '2026-09-20 17:01:17'),
(23, 3, 2, 'used', '2026-09-20 17:01:21'),
(24, 3, 2, 'used', '2026-09-20 17:01:26'),
(25, 4, 2, 'invalidated', '2026-09-20 17:01:32'),
(26, 4, 2, 'expired', '2026-09-20 17:01:57'),
(27, 4, 2, 'success', '2026-09-20 17:24:38'),
(28, 4, 2, 'used', '2026-09-20 17:27:40'),
(29, 4, 2, 'success', '2026-09-20 17:28:26'),
(30, 4, 2, 'success', '2026-09-20 17:29:26'),
(31, 4, 2, 'success', '2026-09-20 17:31:25'),
(32, 4, 2, 'success', '2026-09-20 17:32:52'),
(33, 4, 2, 'used', '2026-09-20 17:35:39'),
(34, 3, 2, 'success', '2026-09-20 17:35:45'),
(35, 3, 2, 'used', '2026-09-20 17:38:31'),
(36, 3, 2, 'used', '2026-09-20 17:38:43'),
(37, 4, 2, 'success', '2026-09-20 17:39:06'),
(38, 4, 2, 'success', '2026-09-20 17:41:22'),
(39, 1, 2, 'success', '2026-09-20 17:43:03'),
(40, 1, 2, 'used', '2026-09-20 17:43:33'),
(41, NULL, 2, 'not_found', '2026-09-20 17:43:35'),
(42, 3, 2, 'success', '2026-09-20 17:43:36'),
(43, 3, 2, 'used', '2026-09-20 17:43:43'),
(44, 3, 2, 'used', '2026-09-20 17:43:46'),
(45, 4, 2, 'used', '2026-09-20 17:43:47'),
(46, 5, 2, 'success', '2026-09-20 17:43:49'),
(47, 5, 2, 'success', '2026-09-20 17:48:24'),
(48, 4, 2, 'success', '2026-09-20 17:50:06'),
(49, 4, 2, 'used', '2026-09-20 17:50:09'),
(50, 4, 2, 'used', '2026-09-20 17:50:21'),
(51, 3, 2, 'success', '2026-09-20 17:50:25'),
(52, 3, 2, 'used', '2026-09-20 17:51:02'),
(53, 3, 2, 'used', '2026-09-20 17:51:05'),
(54, 4, 2, 'success', '2026-09-20 17:51:05'),
(55, 5, 2, 'success', '2026-09-20 17:52:19'),
(56, 5, 2, 'used', '2026-09-20 18:08:04'),
(57, 5, 2, 'success', '2026-09-20 18:08:52'),
(58, 5, 2, 'used', '2026-09-20 18:09:04'),
(59, 4, 2, 'success', '2026-09-20 18:09:17'),
(60, 4, 2, 'used', '2026-09-20 18:09:22'),
(61, 4, 2, 'used', '2026-09-20 18:09:25'),
(62, 4, 2, 'used', '2026-09-20 18:09:29'),
(63, 1, 2, 'success', '2026-09-21 14:51:03'),
(64, 1, 2, 'success', '2026-09-21 14:54:27'),
(65, 1, 2, 'used', '2026-09-21 14:54:34'),
(66, NULL, 2, 'not_found', '2026-09-21 14:54:36'),
(67, NULL, 2, 'not_found', '2026-09-21 14:54:41'),
(68, NULL, 2, 'not_found', '2026-09-21 14:54:44'),
(69, NULL, 2, 'not_found', '2026-09-21 14:54:47'),
(70, 3, 2, 'success', '2026-09-21 14:54:48'),
(71, 3, 2, 'used', '2026-09-21 14:55:00'),
(72, 4, 2, 'success', '2026-09-21 14:55:01'),
(73, 4, 2, 'used', '2026-09-21 14:55:07'),
(74, 5, 2, 'success', '2026-09-21 14:55:09'),
(75, 5, 2, 'used', '2026-09-21 14:55:16');

-- --------------------------------------------------------

--
-- Table structure for table `school`
--

CREATE TABLE `school` (
  `school_id` int(11) NOT NULL,
  `school_name` varchar(100) NOT NULL,
  `school_code` varchar(20) NOT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `school`
--

INSERT INTO `school` (`school_id`, `school_name`, `school_code`, `status`, `created_at`, `updated_at`) VALUES
(1, 'School of Information Technogy', 'SOIT', 'active', '2026-09-14 22:06:57', '2026-09-14 22:06:57'),
(5, 'School of Education', 'BSEE', 'active', '2026-09-22 13:11:15', '2026-09-22 13:11:15'),
(6, 'College of Law', 'COL', 'active', '2026-09-22 14:27:25', '2026-09-22 14:27:25');

-- --------------------------------------------------------

--
-- Table structure for table `user`
--

CREATE TABLE `user` (
  `user_id` int(11) NOT NULL,
  `school_id` int(11) DEFAULT NULL,
  `username` varchar(50) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `role` enum('super_admin','school_admin','operator') NOT NULL DEFAULT 'school_admin',
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user`
--

INSERT INTO `user` (`user_id`, `school_id`, `username`, `password_hash`, `full_name`, `role`, `status`, `created_at`, `updated_at`) VALUES
(1, 1, 'admin', '$2y$10$l9k0rt46bq4H9WGSk1ksu.hcOpUYR6pPNkXAB7DdlSld6UtKhRJvG', 'School Administrator', 'school_admin', 'active', '2026-09-14 22:08:35', '2026-09-14 22:08:35'),
(2, 1, 'operator_bsit', '$2b$12$3tEuI7EGH7lCIXs1pw3iaeq.fDl.ZBJSnF0tFUt99usqtsFsSZGX2', 'Test BSIT Operator', 'operator', 'active', '2026-09-17 17:50:35', '2026-09-22 15:05:15'),
(3, NULL, 'super_admin1', '$2b$12$3tEuI7EGH7lCIXs1pw3iaeq.fDl.ZBJSnF0tFUt99usqtsFsSZGX2', 'Test Super Admin', 'super_admin', 'active', '2026-09-21 15:43:39', '2026-09-21 15:43:39'),
(4, 5, 'admin_bsee', '$2y$10$ciC2dG1dZUEdUS.GmACvBeDm1BnctW20BT6JecqN3ubDmWwcKQ4Yy', 'BSEE Administrator', 'school_admin', 'active', '2026-09-22 13:12:24', '2026-09-22 19:12:14'),
(5, 5, 'operator_bsee', '$2y$10$IEkvpPOTH4SfQGkpoW8Hg.o4PidoTjX8Vj.ELn33K8BaU3AWpWqSG', 'Test BSEE Operator', 'operator', 'active', '2026-09-22 14:41:20', '2026-09-22 14:41:20');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `graduate`
--
ALTER TABLE `graduate`
  ADD PRIMARY KEY (`graduate_id`),
  ADD UNIQUE KEY `student_id` (`student_id`),
  ADD KEY `school_id` (`school_id`);

--
-- Indexes for table `layout`
--
ALTER TABLE `layout`
  ADD PRIMARY KEY (`layout_id`),
  ADD KEY `school_id` (`school_id`),
  ADD KEY `template_id` (`template_id`);

--
-- Indexes for table `qr_code`
--
ALTER TABLE `qr_code`
  ADD PRIMARY KEY (`qr_id`),
  ADD UNIQUE KEY `qr_token` (`qr_token`),
  ADD KEY `graduate_id` (`graduate_id`);

--
-- Indexes for table `scan_log`
--
ALTER TABLE `scan_log`
  ADD PRIMARY KEY (`scan_id`),
  ADD KEY `qr_id` (`qr_id`),
  ADD KEY `operator_id` (`operator_id`);

--
-- Indexes for table `school`
--
ALTER TABLE `school`
  ADD PRIMARY KEY (`school_id`),
  ADD UNIQUE KEY `school_code` (`school_code`);

--
-- Indexes for table `user`
--
ALTER TABLE `user`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD KEY `school_id` (`school_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `graduate`
--
ALTER TABLE `graduate`
  MODIFY `graduate_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `layout`
--
ALTER TABLE `layout`
  MODIFY `layout_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `qr_code`
--
ALTER TABLE `qr_code`
  MODIFY `qr_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `scan_log`
--
ALTER TABLE `scan_log`
  MODIFY `scan_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=76;

--
-- AUTO_INCREMENT for table `school`
--
ALTER TABLE `school`
  MODIFY `school_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `user`
--
ALTER TABLE `user`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `graduate`
--
ALTER TABLE `graduate`
  ADD CONSTRAINT `graduate_ibfk_1` FOREIGN KEY (`school_id`) REFERENCES `school` (`school_id`);

--
-- Constraints for table `layout`
--
ALTER TABLE `layout`
  ADD CONSTRAINT `layout_ibfk_1` FOREIGN KEY (`school_id`) REFERENCES `school` (`school_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `layout_ibfk_2` FOREIGN KEY (`template_id`) REFERENCES `layout` (`layout_id`) ON DELETE SET NULL;

--
-- Constraints for table `qr_code`
--
ALTER TABLE `qr_code`
  ADD CONSTRAINT `qr_code_ibfk_1` FOREIGN KEY (`graduate_id`) REFERENCES `graduate` (`graduate_id`) ON DELETE CASCADE;

--
-- Constraints for table `scan_log`
--
ALTER TABLE `scan_log`
  ADD CONSTRAINT `scan_log_ibfk_1` FOREIGN KEY (`qr_id`) REFERENCES `qr_code` (`qr_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `scan_log_ibfk_2` FOREIGN KEY (`operator_id`) REFERENCES `user` (`user_id`) ON DELETE SET NULL;

--
-- Constraints for table `user`
--
ALTER TABLE `user`
  ADD CONSTRAINT `user_ibfk_1` FOREIGN KEY (`school_id`) REFERENCES `school` (`school_id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
