-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Sep 18, 2026 at 12:28 PM
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
(5, 1, '23-01-2408', 'John Hiro', 'Camiguing', 'Castrosss', '', 'BSIT', 'AP', 'asf;lkdsajf;klfjdsf', 'magna cum laude', 2027, '../photos/photo_5.png', '2026-09-17 17:14:05', '2026-09-17 17:14:05'),
(6, 1, '23-01-0031', 'Gabriel', 'Baguio', 'Bitong', 'III', 'BSIT', 'AP', 'BRGY. 04', 'none', 2027, '../photos/photo_6.jpg', '2026-09-18 15:54:52', '2026-09-18 15:55:19');

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
(1, 2, 'cc0c5de86abc927435a53f8082021c05', 'active', '2026-09-14 22:45:27', NULL, NULL),
(3, 4, '643d71ae4e77b2bb86b5c3807e8edafc', 'active', '2026-09-17 16:18:53', NULL, NULL),
(4, 5, '0625e135f4861f8b0bb5c1b71a80e66d', 'active', '2026-09-17 17:14:05', NULL, NULL),
(5, 6, '91bd8aa5d5d2ee717637955745292b69', 'active', '2026-09-18 15:54:53', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `scan_log`
--

CREATE TABLE `scan_log` (
  `scan_id` int(11) NOT NULL,
  `qr_id` int(11) DEFAULT NULL,
  `operator_id` int(11) DEFAULT NULL,
  `scan_status` enum('success','used','expired','invalidated', 'not_found') NOT NULL,
  `scanned_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `scan_log`
--

INSERT INTO `scan_log` (`scan_id`, `qr_id`, `operator_id`, `scan_status`, `scanned_at`) VALUES
(1, 1, NULL, 'success', '2026-09-17 16:25:14'),
(2, 1, NULL, 'used', '2026-09-17 16:26:04');

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
(1, 'School of Information Technogy', 'SOIT', 'active', '2026-09-14 22:06:57', '2026-09-14 22:06:57');

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
(2, 1, 'operator_bsit', '$2b$12$3tEuI7EGH7lCIXs1pw3iaeq.fDl.ZBJSnF0tFUt99usqtsFsSZGX2', 'Test BSIT Operator', 'operator', 'active', '2026-09-17 17:50:35', '2026-09-17 17:50:35');

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
  MODIFY `scan_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `school`
--
ALTER TABLE `school`
  MODIFY `school_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `user`
--
ALTER TABLE `user`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

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
