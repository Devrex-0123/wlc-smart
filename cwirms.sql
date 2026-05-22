-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Apr 27, 2026 at 08:00 PM
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
-- Database: `cwirms`
--

-- --------------------------------------------------------

--
-- Table structure for table `canvasser_action_history`
--

CREATE TABLE `canvasser_action_history` (
  `id` int(11) NOT NULL,
  `request_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `action` enum('accept','reject','pending') NOT NULL,
  `acted_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `canvasser_action_history`
--

INSERT INTO `canvasser_action_history` (`id`, `request_id`, `user_id`, `action`, `acted_at`) VALUES
(1, 4, 26, 'accept', '2026-04-17 12:36:54'),
(2, 4, 26, 'pending', '2026-04-17 12:38:41'),
(3, 4, 26, 'accept', '2026-04-17 12:45:04'),
(4, 4, 26, 'pending', '2026-04-17 12:45:05'),
(5, 4, 26, 'accept', '2026-04-20 20:29:39'),
(6, 9, 27, 'accept', '2026-04-21 13:35:16'),
(7, 9, 27, 'pending', '2026-04-21 13:35:21'),
(8, 11, 27, 'accept', '2026-04-21 15:43:50'),
(9, 11, 27, 'pending', '2026-04-21 15:43:53'),
(10, 11, 27, 'accept', '2026-04-21 15:43:54'),
(11, 12, 26, 'accept', '2026-04-27 00:57:53'),
(12, 11, 26, 'accept', '2026-04-27 01:13:25'),
(13, 11, 26, 'pending', '2026-04-27 01:13:38'),
(14, 11, 26, 'accept', '2026-04-27 01:13:51'),
(15, 11, 26, 'pending', '2026-04-27 01:14:09'),
(16, 11, 26, 'accept', '2026-04-27 01:14:38'),
(17, 11, 26, 'pending', '2026-04-27 01:14:46'),
(18, 11, 26, 'accept', '2026-04-27 01:17:10'),
(19, 13, 26, 'accept', '2026-04-27 22:47:54'),
(20, 12, 26, 'accept', '2026-04-28 00:17:38'),
(21, 12, 26, 'accept', '2026-04-28 00:30:57');

-- --------------------------------------------------------

--
-- Table structure for table `comptroller_action_history`
--

CREATE TABLE `comptroller_action_history` (
  `id` int(11) NOT NULL,
  `request_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `action` enum('accept','reject','pending') NOT NULL,
  `acted_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `comptroller_action_history`
--

INSERT INTO `comptroller_action_history` (`id`, `request_id`, `user_id`, `action`, `acted_at`) VALUES
(1, 1, 21, 'accept', '2026-04-10 21:28:40'),
(2, 4, 21, 'reject', '2026-04-10 23:04:54'),
(5, 4, 21, 'reject', '2026-04-10 23:11:41'),
(6, 1, 21, 'reject', '2026-04-10 23:12:46'),
(7, 4, 21, 'accept', '2026-04-10 23:13:09'),
(8, 1, 21, 'accept', '2026-04-10 23:13:20'),
(9, 4, 21, 'reject', '2026-04-10 23:14:57'),
(10, 4, 21, 'accept', '2026-04-10 23:15:00'),
(11, 7, 21, 'accept', '2026-04-18 20:27:33'),
(12, 7, 21, 'pending', '2026-04-18 20:27:45'),
(13, 7, 21, 'accept', '2026-04-20 20:34:43'),
(14, 7, 21, 'pending', '2026-04-20 20:34:44'),
(15, 7, 21, 'reject', '2026-04-20 20:34:47'),
(16, 7, 21, 'pending', '2026-04-20 20:34:48'),
(17, 9, 21, 'accept', '2026-04-21 10:34:39'),
(18, 9, 21, 'pending', '2026-04-21 10:34:40'),
(19, 9, 21, 'reject', '2026-04-21 10:34:46'),
(20, 9, 21, 'pending', '2026-04-21 10:34:48'),
(21, 11, 21, 'accept', '2026-04-21 15:47:26'),
(22, 12, 21, 'accept', '2026-04-27 01:38:08'),
(23, 13, 21, 'accept', '2026-04-27 23:26:36'),
(24, 13, 21, 'pending', '2026-04-27 23:26:38'),
(25, 13, 21, 'accept', '2026-04-27 23:26:40'),
(26, 12, 21, 'accept', '2026-04-28 00:35:49');

-- --------------------------------------------------------

--
-- Table structure for table `department`
--

CREATE TABLE `department` (
  `department_id` int(11) NOT NULL,
  `department name` varchar(100) NOT NULL,
  `photo_url` varchar(255) DEFAULT NULL,
  `default_lab_manager_user_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `department`
--

INSERT INTO `department` (`department_id`, `department name`, `photo_url`) VALUES
(1, 'CICTE', 'app/api/public/uploads/departments/dept_20260422_182022_05cf33b4.png'),
(2, 'CONAHS', 'app/api/public/uploads/departments/dept_20260422_182135_6d8aa301.jpg'),
(3, 'Inventory Office', 'app/api/public/uploads/departments/dept_20260422_183121_4484b964.png'),
(5, 'COAB', 'app/api/public/uploads/departments/dept_20260422_182343_e69195d1.jpg'),
(6, 'GSD OFFICE', 'app/api/public/uploads/departments/dept_20260422_183150_2d36d99b.png'),
(7, 'COED', 'app/api/public/uploads/departments/dept_20260422_182354_2aa9d9cd.png'),
(8, 'STAHM', 'app/api/public/uploads/departments/dept_20260422_182801_d0349d46.png'),
(9, 'POLSCIE', 'app/api/public/uploads/departments/dept_20260422_182820_b9fc155a.jpg');

-- --------------------------------------------------------

--
-- Table structure for table `facilities`
--

CREATE TABLE `facilities` (
  `facility_id` int(11) NOT NULL,
  `building` varchar(100) NOT NULL,
  `laboratory` varchar(100) DEFAULT NULL,
  `room` varchar(100) NOT NULL,
  `type` text NOT NULL,
  `floor` varchar(100) DEFAULT NULL,
  `department_id` int(11) NOT NULL,
  `code` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `facilities`
--

INSERT INTO `facilities` (`facility_id`, `building`, `laboratory`, `room`, `type`, `floor`, `department_id`, `code`) VALUES
(1, 'WLC Main Buiding', 'lab 1', '', 'Computer Lab', '1st floor', 1, 'WLCU01'),
(2, 'WLC Main Buiding', 'Lab 2', '', 'Computer Lab', '1st floor', 1, 'WLCU02'),
(3, 'Nursing Buiding', 'lab 1', '', 'Laboratories', '1st floor', 2, ''),
(4, 'Nursing Buiding', '', 'Room 1', 'Classroom', '1st floor', 2, ''),
(5, 'Nursing Buiding', 'lab 3', '', 'Laboratories', '1st floor', 2, ''),
(6, 'WLC Main Buiding', 'lab 3', '', 'Computer Lab', '1st floor', 1, 'WLCU03'),
(7, 'COAB Buildings', '', 'Room 1', 'Classroom', '1st floor', 7, 'WLC-COED-01');

-- --------------------------------------------------------

--
-- Table structure for table `gsd_action_history`
--

CREATE TABLE `gsd_action_history` (
  `id` int(11) NOT NULL,
  `request_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `action` enum('accept','reject','pending') NOT NULL,
  `acted_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `gsd_action_history`
--

INSERT INTO `gsd_action_history` (`id`, `request_id`, `user_id`, `action`, `acted_at`) VALUES
(1, 4, 22, 'reject', '2026-04-12 18:03:57'),
(2, 4, 22, 'accept', '2026-04-12 18:04:13'),
(3, 4, 22, 'reject', '2026-04-17 12:14:39'),
(4, 4, 22, 'pending', '2026-04-17 12:14:42'),
(5, 4, 22, 'accept', '2026-04-17 12:19:41'),
(6, 4, 22, 'pending', '2026-04-17 12:19:44'),
(7, 7, 22, 'accept', '2026-04-20 20:26:39'),
(8, 7, 22, 'pending', '2026-04-20 20:26:41'),
(9, 4, 22, 'accept', '2026-04-20 20:30:29'),
(10, 11, 22, 'accept', '2026-04-21 15:44:34'),
(11, 12, 22, 'accept', '2026-04-27 00:11:57'),
(12, 12, 22, 'pending', '2026-04-27 00:12:01'),
(13, 11, 22, 'accept', '2026-04-27 01:10:33'),
(14, 11, 22, 'pending', '2026-04-27 01:10:35'),
(15, 12, 22, 'accept', '2026-04-27 01:18:33'),
(16, 12, 22, 'accept', '2026-04-27 01:41:20'),
(17, 13, 22, 'accept', '2026-04-27 23:10:54'),
(18, 13, 22, 'pending', '2026-04-27 23:11:01'),
(19, 13, 22, 'accept', '2026-04-27 23:14:34'),
(20, 13, 22, 'pending', '2026-04-27 23:14:42'),
(21, 13, 22, 'accept', '2026-04-27 23:14:52'),
(22, 13, 22, 'pending', '2026-04-28 00:31:58'),
(23, 13, 22, 'accept', '2026-04-28 00:32:07'),
(24, 12, 22, 'accept', '2026-04-28 00:35:17');

-- --------------------------------------------------------

--
-- Table structure for table `inventory`
--

CREATE TABLE `inventory` (
  `inventory_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `item_code` varchar(100) NOT NULL,
  `facility_id` int(11) NOT NULL,
  `acquisition_date` date DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `user_id` int(11) NOT NULL,
  `request_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `inventory`
--

INSERT INTO `inventory` (`inventory_id`, `name`, `item_code`, `facility_id`, `acquisition_date`, `remarks`, `created_at`, `user_id`, `request_id`) VALUES
(49, 'Computer Set 1', 'WLCU01000001', 1, '2026-04-20', 'good condition', '2026-04-20 12:19:56', 19, 0),
(50, 'Computer set 1', 'WLCU02000001', 2, '2026-04-21', '', '2026-04-21 07:23:41', 19, 0);

-- --------------------------------------------------------

--
-- Table structure for table `items`
--

CREATE TABLE `items` (
  `item_id` int(11) NOT NULL,
  `item_name` varchar(150) NOT NULL,
  `brand` varchar(100) DEFAULT NULL,
  `model` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `category` varchar(100) DEFAULT NULL,
  `unit` varchar(50) DEFAULT NULL,
  `status` varchar(50) DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `supplier_id` int(11) DEFAULT NULL,
  `photo_url` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `items`
--

INSERT INTO `items` (`item_id`, `item_name`, `brand`, `model`, `description`, `category`, `unit`, `status`, `created_at`, `supplier_id`, `photo_url`) VALUES
(10, 'Motherboard', 'Lenovo', 'thinkpad x31', 'asasdasd', 'Electronics', 'Piece', 'Active', '2026-04-01 02:04:07', 1, 'uploads/inventory/item_1776735552_0f385a9a.webp'),
(11, 'RAM', 'Atech', 'asas', '', 'Electronics', 'Set', 'Active', '2026-04-01 02:06:13', 3, 'uploads/inventory/item_1776735561_359d66e6.jpg');

-- --------------------------------------------------------

--
-- Table structure for table `item_components`
--

CREATE TABLE `item_components` (
  `component_id` int(11) NOT NULL,
  `parent_item_id` int(11) NOT NULL,
  `component_item_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `condition_status` varchar(50) DEFAULT NULL,
  `status` varchar(50) DEFAULT 'Available',
  `code` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `photo_url` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Catalog parts: qty, condition, status per line. Unique catalog item per parent (app-enforced).';

--
-- Dumping data for table `item_components`
--

INSERT INTO `item_components` (`component_id`, `parent_item_id`, `component_item_id`, `quantity`, `condition_status`, `status`, `code`, `created_at`, `photo_url`) VALUES
(57, 49, 10, 1, 'Good', 'Available', 'WLCU01000001', '2026-04-20 12:19:56', 'uploads/inventory/1776735753_69e6d609e64ef_images1.jpg'),
(55, 49, 11, 1, 'Good', 'Available', 'WLCU01000001', '2026-04-21 01:42:32', 'uploads/inventory/1776735752_69e6d60826de9_images.jpg'),
(58, 50, 10, 1, 'Good', 'Available', 'WLCU02000001', '2026-04-21 07:23:41', 'uploads/inventory/1776756221_69e725fd53840_motherboard-for-computer.webp'),
(56, 50, 11, 1, 'Good', 'Available', 'WLCU02000001', '2026-04-21 07:23:41', 'uploads/inventory/1776756221_69e725fd54a7d_images.jpg');

-- --------------------------------------------------------

--
-- Table structure for table `item_supplier`
--

CREATE TABLE `item_supplier` (
  `item_supplier_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `supplier_id` int(11) NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `item_supplier`
--

INSERT INTO `item_supplier` (`item_supplier_id`, `item_id`, `supplier_id`, `sort_order`) VALUES
(1, 10, 1, 0),
(2, 11, 3, 0);

-- --------------------------------------------------------

--
-- Table structure for table `log_history`
--

CREATE TABLE `log_history` (
  `log_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `time_in` datetime NOT NULL DEFAULT current_timestamp(),
  `time_out` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `log_history`
--

INSERT INTO `log_history` (`log_id`, `user_id`, `time_in`, `time_out`) VALUES
(102, 16, '2026-01-27 08:47:42', '2026-02-06 11:10:48'),
(103, 16, '2026-02-06 11:11:39', '2026-02-26 19:01:41'),
(104, 16, '2026-02-26 19:02:25', '2026-03-10 12:49:36'),
(105, 16, '2026-03-10 21:58:09', '2026-03-12 13:02:32'),
(106, 16, '2026-03-21 22:52:03', '2026-03-21 22:52:36'),
(107, 16, '2026-03-22 21:40:40', '2026-03-22 21:47:21'),
(108, 16, '2026-03-22 21:57:33', '2026-03-22 21:57:58'),
(109, 18, '2026-03-22 22:00:02', '2026-03-31 09:42:24'),
(110, 16, '2026-03-22 22:27:34', '2026-03-23 23:01:14'),
(111, 16, '2026-03-29 20:08:27', '2026-03-31 09:40:12'),
(112, 16, '2026-03-31 09:55:25', '2026-04-01 08:52:12'),
(113, 18, '2026-04-01 08:16:59', '2026-04-01 08:50:42'),
(114, 18, '2026-04-01 08:51:41', '2026-04-01 08:51:49'),
(115, 16, '2026-04-01 08:52:23', '2026-04-01 08:52:37'),
(116, 18, '2026-04-01 08:52:55', '2026-04-01 08:53:03'),
(117, 18, '2026-04-01 09:01:28', '2026-04-01 09:01:35'),
(118, 18, '2026-04-01 09:05:59', '2026-04-01 09:06:28'),
(119, 16, '2026-04-01 09:06:43', '2026-04-01 10:07:57'),
(120, 18, '2026-04-01 10:08:15', '2026-04-09 22:50:42'),
(121, 16, '2026-04-01 20:14:11', '2026-04-07 10:41:54'),
(122, 16, '2026-04-09 22:50:56', '2026-04-09 22:51:25'),
(123, 18, '2026-04-09 22:51:41', '2026-04-10 12:25:51'),
(124, 18, '2026-04-10 12:26:06', '2026-04-10 12:26:12'),
(125, 18, '2026-04-10 12:26:22', '2026-04-10 12:26:26'),
(126, 18, '2026-04-10 12:40:18', '2026-04-10 12:42:08'),
(127, 18, '2026-04-10 12:42:21', '2026-04-10 12:42:29'),
(128, 16, '2026-04-10 12:42:44', '2026-04-10 12:55:14'),
(129, 18, '2026-04-10 12:55:32', '2026-04-10 18:48:41'),
(130, 16, '2026-04-10 12:59:06', '2026-04-10 18:55:05'),
(131, 21, '2026-04-10 18:55:25', '2026-04-10 18:56:49'),
(132, 21, '2026-04-10 18:57:14', '2026-04-10 19:00:03'),
(133, 21, '2026-04-10 19:00:19', '2026-04-10 19:00:25'),
(134, 21, '2026-04-10 19:03:42', '2026-04-10 22:02:27'),
(135, 18, '2026-04-10 22:02:48', '2026-04-10 22:36:34'),
(136, 16, '2026-04-10 22:36:48', '2026-04-10 22:37:13'),
(137, 21, '2026-04-10 22:37:33', '2026-04-10 22:38:01'),
(138, 16, '2026-04-10 22:42:59', '2026-04-10 22:43:38'),
(139, 16, '2026-04-10 22:46:11', '2026-04-10 22:46:16'),
(140, 16, '2026-04-10 22:46:30', '2026-04-10 22:46:38'),
(141, 18, '2026-04-10 22:46:54', '2026-04-10 22:47:07'),
(142, 16, '2026-04-10 22:47:50', '2026-04-10 22:47:55'),
(143, 21, '2026-04-10 22:48:10', '2026-04-10 22:48:15'),
(144, 16, '2026-04-10 22:53:45', '2026-04-10 22:55:03'),
(145, 18, '2026-04-10 22:55:15', '2026-04-10 22:56:03'),
(146, 21, '2026-04-10 22:56:19', '2026-04-10 23:21:22'),
(147, 16, '2026-04-10 23:22:16', '2026-04-11 19:15:32'),
(148, 22, '2026-04-11 19:15:48', '2026-04-11 19:16:07'),
(149, 16, '2026-04-11 19:16:20', '2026-04-11 19:17:24'),
(150, 22, '2026-04-11 19:17:39', '2026-04-11 19:18:44'),
(151, 16, '2026-04-11 19:19:03', '2026-04-12 17:41:42'),
(152, 22, '2026-04-11 19:20:20', '2026-04-12 18:15:41'),
(153, 21, '2026-04-12 17:42:21', '2026-04-12 18:51:57'),
(154, 16, '2026-04-12 18:15:49', '2026-04-12 18:34:12'),
(155, 16, '2026-04-12 18:34:49', '2026-04-12 18:47:16'),
(156, 18, '2026-04-12 18:47:31', '2026-04-12 18:51:13'),
(157, 22, '2026-04-12 18:52:17', '2026-04-12 18:52:29'),
(158, 18, '2026-04-12 18:52:42', '2026-04-12 19:15:00'),
(159, 16, '2026-04-12 18:56:11', '2026-04-12 19:26:49'),
(160, 22, '2026-04-12 19:15:17', '2026-04-12 19:19:45'),
(161, 24, '2026-04-12 19:27:04', '2026-04-12 19:27:19'),
(162, 24, '2026-04-12 19:27:36', '2026-04-12 19:27:56'),
(163, 24, '2026-04-12 19:28:33', '2026-04-12 19:32:37'),
(164, 24, '2026-04-12 19:33:00', '2026-04-12 19:36:57'),
(165, 16, '2026-04-12 19:37:10', '2026-04-12 19:37:26'),
(166, 24, '2026-04-12 20:04:10', '2026-04-12 20:04:41'),
(167, 22, '2026-04-12 20:05:00', '2026-04-12 20:05:41'),
(168, 21, '2026-04-12 20:05:55', '2026-04-12 20:09:23'),
(169, 22, '2026-04-12 20:10:00', '2026-04-12 20:10:13'),
(170, 24, '2026-04-12 20:10:27', '2026-04-12 20:22:27'),
(171, 22, '2026-04-12 20:22:46', '2026-04-12 20:23:37'),
(172, 24, '2026-04-12 20:23:55', '2026-04-12 20:29:04'),
(173, 22, '2026-04-12 20:29:26', '2026-04-12 20:36:26'),
(174, 24, '2026-04-12 20:36:54', '2026-04-12 20:37:33'),
(175, 22, '2026-04-12 20:37:45', '2026-04-12 20:54:38'),
(176, 22, '2026-04-12 20:55:16', '2026-04-12 20:55:42'),
(177, 24, '2026-04-12 20:55:57', '2026-04-12 21:11:25'),
(178, 22, '2026-04-12 21:12:05', '2026-04-12 21:12:14'),
(179, 21, '2026-04-12 21:12:39', '2026-04-12 21:12:50'),
(180, 16, '2026-04-13 19:59:06', '2026-04-13 20:09:45'),
(185, 16, '2026-04-13 20:15:30', '2026-04-13 20:16:04'),
(186, 18, '2026-04-13 20:16:23', '2026-04-13 20:17:09'),
(187, 21, '2026-04-13 20:17:24', '2026-04-13 20:17:43'),
(188, 22, '2026-04-13 20:18:00', '2026-04-13 20:18:43'),
(189, 24, '2026-04-13 20:19:07', '2026-04-13 20:19:38'),
(190, 16, '2026-04-13 20:21:25', '2026-04-13 20:21:48'),
(191, 18, '2026-04-13 20:22:16', '2026-04-13 20:22:50'),
(192, 21, '2026-04-13 20:23:05', '2026-04-13 20:23:27'),
(193, 22, '2026-04-13 20:23:40', '2026-04-13 20:24:03'),
(194, 24, '2026-04-13 20:24:16', '2026-04-13 20:24:50'),
(195, 18, '2026-04-13 20:25:08', '2026-04-13 20:25:27'),
(196, 21, '2026-04-13 20:25:45', '2026-04-13 20:26:00'),
(199, 22, '2026-04-14 20:27:40', '2026-04-14 20:52:06'),
(201, 16, '2026-04-17 11:55:24', '2026-04-17 11:56:21'),
(202, 22, '2026-04-17 11:56:34', '2026-04-17 12:01:50'),
(203, 18, '2026-04-17 12:02:05', '2026-04-17 12:02:23'),
(204, 22, '2026-04-17 12:02:48', '2026-04-17 12:25:04'),
(205, 26, '2026-04-17 12:25:17', '2026-04-17 12:42:50'),
(206, 22, '2026-04-17 12:43:04', '2026-04-17 12:43:19'),
(207, 26, '2026-04-17 12:43:37', '2026-04-17 12:54:45'),
(208, 18, '2026-04-18 20:11:11', '2026-04-18 20:26:26'),
(209, 16, '2026-04-18 20:26:39', '2026-04-18 20:27:03'),
(210, 21, '2026-04-18 20:27:22', '2026-04-18 20:27:48'),
(211, 24, '2026-04-18 20:28:24', '2026-04-18 20:31:19'),
(212, 22, '2026-04-18 20:31:31', '2026-04-18 20:31:50'),
(213, 26, '2026-04-18 20:32:02', '2026-04-20 20:30:03'),
(214, 16, '2026-04-20 20:11:07', '2026-04-20 20:25:51'),
(215, 22, '2026-04-20 20:26:06', '2026-04-20 20:27:41'),
(216, 22, '2026-04-20 20:30:16', '2026-04-20 20:30:46'),
(217, 26, '2026-04-20 20:31:08', '2026-04-20 20:31:17'),
(218, 18, '2026-04-20 20:31:30', '2026-04-20 20:34:15'),
(219, 21, '2026-04-20 20:34:33', '2026-04-20 20:35:19'),
(220, 24, '2026-04-20 20:35:44', '2026-04-20 20:36:29'),
(221, 16, '2026-04-20 20:47:10', '2026-04-20 22:45:23'),
(222, 18, '2026-04-20 22:45:35', '2026-04-20 22:53:11'),
(223, 22, '2026-04-20 22:53:25', '2026-04-20 23:18:19'),
(224, 27, '2026-04-20 23:18:36', '2026-04-20 23:18:43'),
(225, 22, '2026-04-20 23:18:58', '2026-04-20 23:19:21'),
(226, 27, '2026-04-20 23:19:47', '2026-04-20 23:29:32'),
(227, 16, '2026-04-21 09:25:00', '2026-04-21 09:53:19'),
(228, 18, '2026-04-21 09:53:37', '2026-04-21 10:21:05'),
(229, 22, '2026-04-21 10:21:19', '2026-04-21 10:31:01'),
(230, 27, '2026-04-21 10:31:12', '2026-04-21 10:34:09'),
(231, 21, '2026-04-21 10:34:22', '2026-04-21 10:34:54'),
(232, 24, '2026-04-21 10:35:07', '2026-04-21 10:35:47'),
(233, 16, '2026-04-21 13:23:14', '2026-04-21 13:23:54'),
(234, 18, '2026-04-21 13:24:07', '2026-04-21 13:26:45'),
(235, 22, '2026-04-21 13:27:13', '2026-04-21 13:28:39'),
(236, 27, '2026-04-21 13:28:53', '2026-04-21 13:37:20'),
(237, 16, '2026-04-21 13:30:02', '2026-04-21 13:30:13'),
(238, 22, '2026-04-21 13:30:25', '2026-04-21 13:30:39'),
(239, 16, '2026-04-21 13:37:37', '2026-04-21 13:40:22'),
(240, 16, '2026-04-21 13:52:01', '2026-04-21 13:54:57'),
(241, 18, '2026-04-21 13:55:24', '2026-04-21 13:58:08'),
(242, 18, '2026-04-21 13:58:23', '2026-04-21 13:59:29'),
(243, 19, '2026-04-21 14:02:14', '2026-04-21 14:02:38'),
(244, 16, '2026-04-21 14:55:46', '2026-04-21 14:56:58'),
(245, 16, '2026-04-21 15:09:04', '2026-04-21 15:09:44'),
(246, 18, '2026-04-21 15:09:59', '2026-04-21 15:11:41'),
(247, 16, '2026-04-21 15:19:00', '2026-04-21 15:25:34'),
(248, 18, '2026-04-21 15:25:49', '2026-04-21 15:34:25'),
(249, 22, '2026-04-21 15:34:44', '2026-04-21 15:35:17'),
(250, 27, '2026-04-21 15:35:34', '2026-04-21 15:44:03'),
(251, 22, '2026-04-21 15:44:18', '2026-04-21 15:44:38'),
(252, 21, '2026-04-21 15:46:42', '2026-04-27 01:38:13'),
(253, 26, '2026-04-22 12:34:31', '2026-04-22 12:34:42'),
(254, 18, '2026-04-22 22:32:52', '2026-04-22 23:14:13'),
(255, 16, '2026-04-22 23:14:26', '2026-04-22 23:15:05'),
(256, 18, '2026-04-22 23:15:30', '2026-04-22 23:44:02'),
(257, 16, '2026-04-22 23:44:15', '2026-04-22 23:49:46'),
(258, 18, '2026-04-22 23:50:06', '2026-04-22 23:50:23'),
(259, 16, '2026-04-22 23:50:38', '2026-04-22 23:54:18'),
(260, 16, '2026-04-22 23:54:31', '2026-04-22 23:58:23'),
(261, 16, '2026-04-22 23:58:43', '2026-04-23 00:02:39'),
(262, 16, '2026-04-23 00:04:22', '2026-04-26 20:25:40'),
(263, 18, '2026-04-26 20:25:57', '2026-04-26 22:27:36'),
(264, 16, '2026-04-26 22:27:48', '2026-04-26 22:32:17'),
(265, 18, '2026-04-26 22:32:27', '2026-04-26 23:09:46'),
(266, 16, '2026-04-26 23:09:57', '2026-04-26 23:18:17'),
(267, 18, '2026-04-26 23:18:42', '2026-04-26 23:21:23'),
(268, 16, '2026-04-26 23:21:33', '2026-04-26 23:28:08'),
(269, 16, '2026-04-26 23:28:26', '2026-04-26 23:28:33'),
(270, 18, '2026-04-26 23:28:49', '2026-04-26 23:55:41'),
(271, 22, '2026-04-26 23:55:59', '2026-04-27 00:14:49'),
(272, 18, '2026-04-27 00:15:10', '2026-04-27 00:16:17'),
(273, 22, '2026-04-27 00:16:40', '2026-04-27 00:17:01'),
(274, 18, '2026-04-27 00:17:22', '2026-04-27 00:30:44'),
(275, 22, '2026-04-27 00:31:04', '2026-04-27 00:31:24'),
(276, 26, '2026-04-27 00:31:41', '2026-04-27 01:04:21'),
(277, 18, '2026-04-27 01:04:35', '2026-04-27 01:05:15'),
(278, 22, '2026-04-27 01:05:28', '2026-04-27 01:06:48'),
(279, 18, '2026-04-27 01:08:14', '2026-04-27 01:08:21'),
(280, 16, '2026-04-27 01:08:31', '2026-04-27 01:09:02'),
(281, 18, '2026-04-27 01:09:20', '2026-04-27 01:09:57'),
(282, 22, '2026-04-27 01:10:14', '2026-04-27 01:12:22'),
(283, 26, '2026-04-27 01:12:39', '2026-04-27 01:17:18'),
(284, 18, '2026-04-27 01:17:32', '2026-04-27 01:18:10'),
(285, 22, '2026-04-27 01:18:24', '2026-04-27 01:18:43'),
(286, 24, '2026-04-27 01:38:30', '2026-04-27 01:39:29'),
(287, 16, '2026-04-27 01:39:47', '2026-04-27 01:40:11'),
(288, 18, '2026-04-27 01:40:31', '2026-04-27 01:40:58'),
(289, 22, '2026-04-27 01:41:10', '2026-04-27 01:41:51'),
(290, 18, '2026-04-27 01:42:02', '2026-04-27 02:11:38'),
(291, 16, '2026-04-27 02:11:51', '2026-04-27 02:24:52'),
(292, 18, '2026-04-27 02:25:08', '2026-04-27 02:25:35'),
(293, 16, '2026-04-27 02:25:50', '2026-04-27 02:33:34'),
(294, 18, '2026-04-27 02:33:48', '2026-04-27 02:35:37'),
(295, 16, '2026-04-27 02:35:47', '2026-04-27 22:29:44'),
(296, 18, '2026-04-27 21:38:49', '2026-04-27 21:41:19'),
(297, 22, '2026-04-27 21:41:35', '2026-04-27 22:21:47'),
(298, 21, '2026-04-27 22:22:00', '2026-04-27 22:24:47'),
(299, 22, '2026-04-27 22:25:01', '2026-04-27 22:25:15'),
(300, 26, '2026-04-27 22:25:26', '2026-04-27 22:26:21'),
(301, 18, '2026-04-27 22:28:13', '2026-04-27 22:29:05'),
(302, 18, '2026-04-27 22:30:10', '2026-04-27 22:36:38'),
(303, 22, '2026-04-27 22:36:53', '2026-04-27 22:46:14'),
(304, 27, '2026-04-27 22:46:29', '2026-04-27 22:46:45'),
(305, 26, '2026-04-27 22:47:00', '2026-04-27 22:49:41'),
(306, 22, '2026-04-27 22:49:54', '2026-04-27 23:15:17'),
(307, 21, '2026-04-27 23:15:27', '2026-04-27 23:26:44'),
(308, 24, '2026-04-27 23:27:06', '2026-04-27 23:32:03'),
(309, 18, '2026-04-27 23:37:08', '2026-04-28 00:07:17'),
(310, 16, '2026-04-28 00:07:38', '2026-04-28 00:10:35'),
(311, 22, '2026-04-28 00:11:31', '2026-04-28 00:12:06'),
(312, 26, '2026-04-28 00:12:21', '2026-04-28 00:31:07'),
(313, 22, '2026-04-28 00:31:23', '2026-04-28 00:35:23'),
(314, 21, '2026-04-28 00:35:35', '2026-04-28 00:35:52'),
(315, 24, '2026-04-28 00:36:07', '2026-04-28 01:55:00'),
(316, 16, '2026-04-28 01:55:11', '2026-04-28 01:55:11');

-- --------------------------------------------------------

--
-- Table structure for table `president_action_history`
--

CREATE TABLE `president_action_history` (
  `id` int(11) NOT NULL,
  `request_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `action` enum('accept','reject','pending') NOT NULL,
  `acted_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `president_action_history`
--

INSERT INTO `president_action_history` (`id`, `request_id`, `user_id`, `action`, `acted_at`) VALUES
(1, 4, 24, 'accept', '2026-04-12 20:22:08'),
(2, 1, 24, 'accept', '2026-04-13 20:24:44'),
(3, 7, 24, 'accept', '2026-04-20 20:36:19'),
(4, 7, 24, 'pending', '2026-04-20 20:36:20'),
(5, 12, 24, 'accept', '2026-04-27 01:39:20'),
(6, 13, 24, 'accept', '2026-04-27 23:29:40'),
(7, 13, 24, 'pending', '2026-04-27 23:29:42'),
(8, 13, 24, 'accept', '2026-04-27 23:29:44'),
(9, 12, 24, 'accept', '2026-04-28 00:36:19');

-- --------------------------------------------------------

--
-- Table structure for table `purchase_requisition_audit`
--

CREATE TABLE `purchase_requisition_audit` (
  `purchase_audit_id` int(11) NOT NULL,
  `request_id` int(11) NOT NULL,
  `generated_by_user_id` int(11) NOT NULL,
  `generated_at` datetime NOT NULL DEFAULT current_timestamp(),
  `requester_name` varchar(120) DEFAULT NULL,
  `purpose` varchar(255) DEFAULT NULL,
  `grand_total` decimal(14,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `purchase_requisition_audit`
--

INSERT INTO `purchase_requisition_audit` (`purchase_audit_id`, `request_id`, `generated_by_user_id`, `generated_at`, `requester_name`, `purpose`, `grand_total`) VALUES
(1, 12, 16, '2026-04-28 01:59:15', 'Dean', 'For New The Laboratory', 145000.00),
(2, 13, 16, '2026-04-28 01:55:18', 'Dean', 'for the new laboratory', 2000.00);

-- --------------------------------------------------------

--
-- Table structure for table `purchase_requisition_audit_item`
--

CREATE TABLE `purchase_requisition_audit_item` (
  `purchase_audit_item_id` int(11) NOT NULL,
  `purchase_audit_id` int(11) NOT NULL,
  `line_no` int(11) NOT NULL,
  `description_name` varchar(180) NOT NULL,
  `description_brand` varchar(120) DEFAULT NULL,
  `description_model` varchar(120) DEFAULT NULL,
  `description_specification` varchar(500) DEFAULT NULL,
  `qty` int(11) NOT NULL DEFAULT 1,
  `supplier_name` varchar(180) NOT NULL,
  `unit_price` decimal(14,2) NOT NULL DEFAULT 0.00,
  `amount` decimal(14,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `purchase_requisition_audit_item`
--

INSERT INTO `purchase_requisition_audit_item` (`purchase_audit_item_id`, `purchase_audit_id`, `line_no`, `description_name`, `description_brand`, `description_model`, `description_specification`, `qty`, `supplier_name`, `unit_price`, `amount`) VALUES
(1, 1, 1, 'CPU', 'AMD', 'Ryzen 5 5600G', '6-core, 12-thread desktop processor with 3.9GHz base clock and up to 4.4GHz boost. Includes integrated Radeon Graphics, suitable for office work, school use, and light multimedia tasks.', 29, 'RS SHOP', 5000.00, 145000.00),
(2, 2, 1, 'Desktop table', 'Uratex', 'DT-120 120x60cm', 'Wooden office desk, laminated finish, metal legs, brown color, suitable for office use', 1, 'KimWatches Shop', 2000.00, 2000.00);

-- --------------------------------------------------------

--
-- Table structure for table `requisition_form_approval`
-- (Inventory manager review on the requisition form — first gate)
--

CREATE TABLE `requisition_form_approval` (
  `request_id` int(11) NOT NULL,
  `requisition_status` enum('accept','reject','pending','') DEFAULT 'pending',
  `requisition_note` varchar(255) DEFAULT NULL,
  `requisition_reviewed_by` varchar(100) DEFAULT NULL,
  `requisition_reviewed_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `requisition_form_approval`
--

INSERT INTO `requisition_form_approval` (`request_id`, `requisition_status`, `requisition_note`, `requisition_reviewed_by`, `requisition_reviewed_at`) VALUES
(4, 'pending', NULL, NULL, NULL),
(1, 'pending', NULL, NULL, NULL),
(7, 'pending', NULL, NULL, NULL),
(9, 'pending', NULL, NULL, NULL),
(11, 'accept', NULL, 'jhaye.rebojo', '2026-04-26 19:08:47'),
(12, 'accept', NULL, 'jhaye.rebojo', '2026-04-26 14:19:51'),
(13, 'accept', NULL, 'jhaye.rebojo', '2026-04-27 16:29:32');

-- --------------------------------------------------------

--
-- Table structure for table `canvass_verification_approval`
-- (Canvasser, G.S.D., comptroller, president — shared canvass / verification chain)
--

CREATE TABLE `canvass_verification_approval` (
  `request_id` int(11) NOT NULL,
  `canvas_status` enum('accept','reject','pending','') DEFAULT 'pending',
  `canvassed_by` varchar(100) DEFAULT NULL,
  `canvassed_at` datetime DEFAULT NULL,
  `canvas_assignee_user_id` int(11) DEFAULT NULL,
  `suggested_supplier_id` int(11) DEFAULT NULL,
  `suggested_supplier_name` varchar(120) DEFAULT NULL,
  `checked_by` varchar(100) DEFAULT NULL,
  `checked_at` datetime DEFAULT NULL,
  `comp_status` enum('accept','reject','pending','') DEFAULT 'pending',
  `verified_by` varchar(100) DEFAULT NULL,
  `verified_at` datetime DEFAULT NULL,
  `gsd_status` enum('accept','reject','pending','') DEFAULT 'pending',
  `approved_by` varchar(100) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `pres_status` enum('accept','reject','pending','') DEFAULT 'pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `canvass_verification_approval`
--

INSERT INTO `canvass_verification_approval` (`request_id`, `canvas_status`, `canvassed_by`, `canvassed_at`, `canvas_assignee_user_id`, `suggested_supplier_id`, `suggested_supplier_name`, `checked_by`, `checked_at`, `comp_status`, `verified_by`, `verified_at`, `gsd_status`, `approved_by`, `approved_at`, `pres_status`) VALUES
(4, 'accept', 'canvasser', '2026-04-20 20:29:39', 26, NULL, NULL, NULL, NULL, NULL, 'gsd', '2026-04-20 20:30:29', 'accept', NULL, NULL, 'pending'),
(1, 'pending', NULL, NULL, NULL, NULL, NULL, 'comptroller', '2026-04-10 23:13:20', 'accept', NULL, NULL, NULL, 'president', '2026-04-13 20:24:44', 'accept'),
(7, 'pending', 'rex', '2026-04-20 23:19:14', 27, NULL, NULL, NULL, NULL, 'pending', NULL, NULL, NULL, NULL, NULL, 'pending'),
(9, 'pending', 'rex', NULL, 27, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(11, 'accept', 'canvasser', '2026-04-27 01:17:10', 26, 3, 'SonJingWoo Shop', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'pending'),
(12, 'accept', 'canvasser', '2026-04-28 00:30:57', 26, NULL, NULL, 'comptroller', '2026-04-28 00:35:49', 'accept', 'gsd', '2026-04-28 00:35:17', 'accept', 'president', '2026-04-28 00:36:19', 'accept'),
(13, 'accept', 'canvasser', '2026-04-27 00:57:53', NULL, NULL, NULL, 'comptroller', '2026-04-27 23:26:40', 'accept', 'gsd', '2026-04-28 00:32:07', 'accept', 'president', '2026-04-27 23:29:44', 'accept');

-- --------------------------------------------------------

--
-- Table structure for table `purchase_requisition_approval`
-- (Purchase requisition form — inventory + president verifiers only)
--

CREATE TABLE `purchase_requisition_approval` (
  `request_id` int(11) NOT NULL,
  `pr_inv_status` enum('accept','reject','pending') NOT NULL DEFAULT 'pending',
  `pr_inv_note` varchar(500) DEFAULT NULL,
  `pr_inv_at` datetime DEFAULT NULL,
  `pr_pres_status` enum('accept','reject','pending') NOT NULL DEFAULT 'pending',
  `pr_pres_note` varchar(500) DEFAULT NULL,
  `pr_pres_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `purchase_requisition_approval`
--

INSERT INTO `purchase_requisition_approval` (`request_id`, `pr_inv_status`, `pr_inv_note`, `pr_inv_at`, `pr_pres_status`, `pr_pres_note`, `pr_pres_at`) VALUES
(4, 'pending', NULL, NULL, 'pending', NULL, NULL),
(1, 'pending', NULL, NULL, 'pending', NULL, NULL),
(7, 'pending', NULL, NULL, 'pending', NULL, NULL),
(9, 'pending', NULL, NULL, 'pending', NULL, NULL),
(11, 'pending', NULL, NULL, 'pending', NULL, NULL),
(12, 'accept', NULL, '2026-04-28 01:59:15', 'pending', NULL, NULL),
(13, 'pending', NULL, NULL, 'pending', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `request_approval_suggested_supplier_item`
--

CREATE TABLE `request_approval_suggested_supplier_item` (
  `suggested_item_id` int(11) NOT NULL,
  `request_id` int(11) NOT NULL,
  `canvass_detail_id` int(11) NOT NULL,
  `supplier_id` int(11) NOT NULL,
  `selected_by_user_id` int(11) NOT NULL,
  `selected_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `request_approval_suggested_supplier_item`
--

INSERT INTO `request_approval_suggested_supplier_item` (`suggested_item_id`, `request_id`, `canvass_detail_id`, `supplier_id`, `selected_by_user_id`, `selected_at`) VALUES
(20, 13, 17, 1, 22, '2026-04-27 23:14:48'),
(23, 12, 20, 4, 22, '2026-04-28 00:35:14');

-- --------------------------------------------------------

--
-- Table structure for table `requisition_canvass_detail`
--

CREATE TABLE `requisition_canvass_detail` (
  `canvass_detail_id` int(11) NOT NULL,
  `request_id` int(11) NOT NULL,
  `requisition_line_id` int(11) DEFAULT NULL,
  `user_id` int(11) NOT NULL,
  `component_label` varchar(150) NOT NULL,
  `brand` varchar(100) DEFAULT NULL,
  `model` varchar(100) DEFAULT NULL,
  `specification` varchar(500) DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `requisition_canvass_detail`
--

INSERT INTO `requisition_canvass_detail` (`canvass_detail_id`, `request_id`, `requisition_line_id`, `user_id`, `component_label`, `brand`, `model`, `specification`, `sort_order`, `created_at`, `updated_at`) VALUES
(14, 11, NULL, 18, 'RAM', 'Atech', 'asas', NULL, 0, '2026-04-27 01:17:10', '2026-04-27 01:17:10'),
(17, 13, NULL, 18, 'Desktop table', 'Uratex', 'DT-120 120x60cm', 'Wooden office desk, laminated finish, metal legs, brown color, suitable for office use', 0, '2026-04-27 22:47:54', '2026-04-27 22:47:54'),
(20, 12, NULL, 18, 'CPU', 'AMD', 'Ryzen 5 5600G', '6-core, 12-thread desktop processor with 3.9GHz base clock and up to 4.4GHz boost. Includes integrated Radeon Graphics, suitable for office work, school use, and light multimedia tasks.', 0, '2026-04-28 00:30:57', '2026-04-28 00:30:57');

-- --------------------------------------------------------

--
-- Table structure for table `requisition_canvass_detail_supplier`
--

CREATE TABLE `requisition_canvass_detail_supplier` (
  `canvass_detail_supplier_id` int(11) NOT NULL,
  `canvass_detail_id` int(11) NOT NULL,
  `supplier_id` int(11) NOT NULL,
  `price` decimal(12,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `requisition_canvass_detail_supplier`
--

INSERT INTO `requisition_canvass_detail_supplier` (`canvass_detail_supplier_id`, `canvass_detail_id`, `supplier_id`, `price`) VALUES
(11, 14, 3, 123154.00),
(15, 17, 1, 2000.00),
(16, 17, 4, 2100.00),
(17, 17, 3, 2300.00),
(22, 20, 1, 132123123.00),
(23, 20, 4, 5000.00);

-- --------------------------------------------------------

--
-- Table structure for table `requisition_item`
--

CREATE TABLE `requisition_item` (
  `request_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `department_id` int(11) NOT NULL,
  `facility_id` int(11) NOT NULL,
  `status` enum('Pending','Ongoing','Completed','') NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `message` varchar(100) DEFAULT NULL,
  `purpose` varchar(255) DEFAULT NULL,
  `urgent_note` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `requisition_item`
--

INSERT INTO `requisition_item` (`request_id`, `user_id`, `department_id`, `facility_id`, `status`, `created_at`, `message`, `purpose`, `urgent_note`) VALUES
(1, 18, 1, 2, 'Ongoing', '2026-04-09 08:17:00', NULL, NULL, NULL),
(4, 18, 1, 2, 'Ongoing', '2026-04-09 09:08:00', 'asdasd', NULL, NULL),
(7, 18, 1, 2, 'Pending', '2026-04-18 09:59:00', NULL, NULL, NULL),
(9, 18, 1, 1, 'Pending', '2026-04-21 10:33:00', 'para ni sa bago nga laboratory ma\'am', NULL, NULL),
(11, 18, 1, 1, 'Ongoing', '2026-04-21 11:07:00', 'para sa bago na setup', NULL, NULL),
(12, 18, 1, 1, 'Ongoing', '2026-04-22 11:24:00', 'URGENT REQUEST', 'For New The Laboratory', NULL),
(13, 18, 1, 2, 'Ongoing', '2026-04-27 16:28:59', 'urgent', 'for the new laboratory', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `requisition_line`
--

CREATE TABLE `requisition_line` (
  `requisition_line_id` int(11) NOT NULL,
  `request_id` int(11) NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `item_id` int(11) DEFAULT NULL,
  `item_name` varchar(100) NOT NULL,
  `item_brand` varchar(100) DEFAULT NULL,
  `item_category` varchar(100) DEFAULT NULL,
  `photo_url` varchar(100) NOT NULL DEFAULT '',
  `quantity` int(11) NOT NULL DEFAULT 1,
  `unit_type` enum('set','unit','piece') NOT NULL DEFAULT 'unit'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `requisition_line`
--

INSERT INTO `requisition_line` (`requisition_line_id`, `request_id`, `sort_order`, `item_id`, `item_name`, `item_brand`, `item_category`, `photo_url`, `quantity`, `unit_type`) VALUES
(1, 1, 0, 10, 'Laptop', 'Lenovo', 'Electronics', '', 1, 'unit'),
(2, 4, 0, NULL, 'Keybourd', 'Lenovo', 'Electronics', '', 1, 'unit'),
(9, 7, 0, NULL, 'Desktop Table', 'Appliances', 'Furniture', '', 1, 'unit'),
(10, 7, 1, 11, 'RAM', 'Atech', 'Electronics', '', 1, 'unit'),
(13, 9, 0, 10, 'Motherboard', 'Lenovo', 'Electronics', '', 1, 'unit'),
(14, 9, 1, 11, 'RAM', 'Atech', 'Electronics', '', 1, 'unit'),
(16, 11, 0, NULL, 'CPU', 'LEenovo', 'Electronics', '', 1, 'unit'),
(17, 12, 0, NULL, 'Computer Set', NULL, NULL, '', 29, 'set'),
(18, 13, 0, NULL, 'Table', NULL, NULL, '', 1, 'unit');

-- --------------------------------------------------------

--
-- Table structure for table `suppliers`
--

CREATE TABLE `suppliers` (
  `supplier_id` int(11) NOT NULL,
  `supplier_name` varchar(100) NOT NULL,
  `contact_person` varchar(100) DEFAULT NULL,
  `phone_number` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `city` varchar(50) DEFAULT NULL,
  `country` varchar(50) DEFAULT NULL,
  `postal_code` varchar(20) DEFAULT NULL,
  `date_added` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` varchar(20) DEFAULT 'Active',
  `supplier_image` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `suppliers`
--

INSERT INTO `suppliers` (`supplier_id`, `supplier_name`, `contact_person`, `phone_number`, `email`, `address`, `city`, `country`, `postal_code`, `date_added`, `status`, `supplier_image`) VALUES
(1, 'KimWatches Shop', 'John Doe', '09994895132', 'john.doe@gmail.com', 'St.Avenue, Ormoc City', 'Ormoc City', 'Philippines', '6541', '2026-03-10 03:15:39', 'Active', 'uploads/suppliers/1776736140_157688af99_finished-red-gear-computers-outline1.jpg'),
(3, 'SonJingWoo Shop', 'Elisa Doe', '0912346589', 'sample@gmail.com', 'asdasd', 'Ormoc City', 'Philippines', '6541', '2026-04-01 02:06:45', 'Active', 'uploads/suppliers/1776736107_6e3aff5ee6_images.png'),
(4, 'RS SHOP', 'John Wick', '0923 231 3213', 'john.wick@gmail.com', 'A. real St.', 'Ormoc City', 'Philippines', '6541', '2026-04-20 15:27:38', 'Active', 'uploads/suppliers/1776736175_9ce44a6330_images (1).png');

-- --------------------------------------------------------

--
-- Table structure for table `user`
--

CREATE TABLE `user` (
  `user_id` int(11) NOT NULL,
  `Email` varchar(100) NOT NULL,
  `password` varchar(100) NOT NULL,
  `role` enum('Inventory Manager','Dean','Laboratory Manager','Comptroller','President','Employee','User','GSD officer') NOT NULL DEFAULT 'User',
  `failed_attempts` int(11) NOT NULL DEFAULT 0,
  `lock_time` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `department_id` int(11) DEFAULT NULL,
  `photo_url` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user`
--

INSERT INTO `user` (`user_id`, `Email`, `password`, `role`, `failed_attempts`, `lock_time`, `created_at`, `department_id`, `photo_url`) VALUES
(16, 'jhaye.rebojo@gmail.com', 'Super@123', 'Inventory Manager', 0, 0, '2026-01-27 08:23:28', 3, 'uploads/users/user_1769646426_69ff5826.jpg'),
(18, 'Dean@gmail.com', '77b1b4814d9375bfa6a5a6c9c030dd6b3d8d0186d22b915b55234053e2af1c7c', 'Dean', 0, 0, '2026-03-12 13:00:28', 1, 'uploads/users/user_1773291737_2041b63c.webp'),
(19, 'LabManager@gmail.com', '6c78191194ca82f588085c6e734d206cf0fa501d88a2d9fb13097766cd6aff8a', 'Employee', 0, 0, '2026-04-01 08:25:53', 1, 'uploads/users/user_1775003153_ed2ae0c8.jpg'),
(21, 'comptroller@gmail.com', '6c78191194ca82f588085c6e734d206cf0fa501d88a2d9fb13097766cd6aff8a', 'Comptroller', 0, 0, '2026-04-10 18:49:51', 3, 'uploads/users/user_1775905526_09b52d68.webp'),
(22, 'gsd@gmail.com', '6c78191194ca82f588085c6e734d206cf0fa501d88a2d9fb13097766cd6aff8a', 'GSD officer', 0, 0, '2026-04-11 19:02:37', 6, 'uploads/users/user_1775905479_73855861.jpg'),
(24, 'president@gmail.com', 'e5151ec16459678d36dd7f349a42530469ed80dc318a6fc3cbd428285dae37fb', 'President', 0, 0, '2026-04-12 19:20:52', 0, 'uploads/users/user_1775992852_6dc238e8.jpg'),
(26, 'canvasser@gmail.com', '6c78191194ca82f588085c6e734d206cf0fa501d88a2d9fb13097766cd6aff8a', 'Employee', 0, 0, '2026-04-17 12:01:37', 6, NULL),
(27, 'rex@gmail.com', '6c78191194ca82f588085c6e734d206cf0fa501d88a2d9fb13097766cd6aff8a', 'Employee', 0, 0, '2026-04-20 23:18:17', 6, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `user_activity`
--

CREATE TABLE `user_activity` (
  `activity_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `activity_type` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_activity`
--

INSERT INTO `user_activity` (`activity_id`, `user_id`, `activity_type`, `description`, `created_at`) VALUES
(183, 16, 'Add Department', 'Added department: CONAHS', '2026-01-29 08:09:38'),
(184, 16, 'Add Facility', 'Added facility in dept 1: Nursing Buiding / ', '2026-01-29 08:18:56'),
(185, 16, 'Edit User', 'Updated user (jhaye.rebojo@gmail.com) → Email/Role/Department/Photo changed.', '2026-01-29 08:24:52'),
(186, 16, 'Edit User', 'Updated user (jhaye.rebojo@gmail.com) → Email/Role/Department/Photo changed.', '2026-01-29 08:27:06'),
(187, 16, 'Add Department', 'Added department: Inventory Office', '2026-01-29 08:27:40'),
(188, 16, 'Edit User', 'Updated user (jhaye.rebojo@gmail.com) → Email/Role/Department/Photo changed.', '2026-01-29 08:27:48'),
(189, 16, 'Add Department', 'Added department: CICTE', '2026-01-29 08:27:58'),
(190, 16, 'Delete Department', 'Deleted department: CICTE', '2026-01-29 08:28:07'),
(191, 16, 'Edit Facility', 'Updated facility: Nursing Buiding ', '2026-01-29 08:36:17'),
(192, 16, 'Add Facility', 'Added facility in dept 1: WLC Main Buiding / ', '2026-01-29 08:36:44'),
(193, 16, 'Edit Facility', 'Updated facility: WLC Main Buiding ', '2026-01-29 08:36:49'),
(194, 16, 'Add Facility', 'Added facility in dept 2: Nursing Buiding / ', '2026-01-29 08:37:02'),
(195, 16, 'Add Facility', 'Added facility in dept 2: Nursing Buiding / Room 1', '2026-01-29 08:37:58'),
(196, 16, 'Add Item', 'Added item: table', '2026-02-03 08:07:53'),
(197, 16, 'Add Facility', 'Added facility in dept 2: Nursing Buiding / ', '2026-02-03 08:09:03'),
(198, 16, 'Add Facility', 'Added facility in dept 1: WLC Main Buiding / ', '2026-02-03 08:21:27'),
(199, 16, 'Add User', 'Created new user: rex11840@gmail.com', '2026-02-03 08:24:19'),
(200, 16, 'Edit User', 'Updated user (rex11840@gmail.com) → Email/Password/Role/Department/Photo changed.', '2026-02-03 08:24:28'),
(201, 16, 'Edit User', 'Updated user (rex11840@gmail.com) → Email/Password/Role/Department/Photo changed.', '2026-02-03 08:24:32'),
(202, 16, 'Add Inventory', 'Added inventory: Table with quantity: 1', '2026-02-03 08:39:03'),
(203, 16, 'Add Component', 'Added component to inventory: 30', '2026-02-03 08:39:32'),
(204, 16, 'Edit Inventory', 'Updated inventory: Table', '2026-02-03 08:39:34'),
(205, 16, 'Add Inventory', 'Added inventory: Table with quantity: 1', '2026-02-03 08:44:15'),
(206, 16, 'Edit Inventory', 'Updated inventory: Table', '2026-02-05 15:12:31'),
(207, 16, 'Delete Component', 'Deleted component: 50', '2026-02-05 15:12:31'),
(208, 16, 'Add Item', 'Added item: RAM', '2026-02-05 15:33:31'),
(209, 16, 'Edit Item', 'Updated item: RAM → RAM', '2026-02-05 15:33:40'),
(210, 16, 'Add Inventory', 'Added inventory: System Unit with quantity: 1', '2026-02-05 15:44:00'),
(211, 16, 'Add Department', 'Added department: COAB', '2026-02-06 10:03:52'),
(212, 16, 'Edit Facility', 'Updated facility: WLC Main Buiding ', '2026-02-12 09:54:31'),
(213, 16, 'Edit Facility', 'Updated facility: WLC Main Buiding ', '2026-02-12 09:54:48'),
(214, 16, 'Edit Facility', 'Updated facility: WLC Main Buiding ', '2026-02-12 09:54:56'),
(215, 16, 'Delete Inventory', 'Deleted inventory item', '2026-02-12 10:13:46'),
(216, 16, 'Add Inventory', 'Added inventory: Table with quantity: 1', '2026-02-12 10:19:28'),
(217, 16, 'Delete Inventory', 'Deleted inventory item', '2026-02-12 10:21:42'),
(218, 16, 'Add Inventory', 'Added inventory: Table with quantity: 1', '2026-02-12 10:22:02'),
(219, 16, 'Delete Inventory', 'Deleted inventory item', '2026-02-12 10:24:51'),
(220, 16, 'Add Inventory', 'Added inventory: Table with quantity: 1', '2026-02-12 10:25:11'),
(221, 16, 'Add Inventory', 'Added inventory: System Unit with quantity: 1', '2026-02-12 10:36:00'),
(222, 16, 'Add Item', 'Added item: SSD', '2026-02-12 10:36:18'),
(223, 16, 'Add Component', 'Added component to inventory: 36', '2026-02-12 10:36:33'),
(224, 16, 'Edit Inventory', 'Updated inventory: System Unit', '2026-02-26 19:12:03'),
(225, 16, 'Delete Component', 'Deleted component: 51', '2026-02-26 19:12:03'),
(226, 16, 'Add Inventory', 'Added inventory: Computer Set 1 with quantity: 1', '2026-02-26 19:12:41'),
(227, 16, 'Add Supplier', 'Added supplier: KimWatches Shop', '2026-03-10 11:15:39'),
(228, 16, 'Add Supplier', 'Added supplier: SonJingWoo Shop', '2026-03-10 11:45:30'),
(229, 16, 'Edit Supplier', 'Updated supplier: SonJingWoo Shop → SonJingWoo Shop', '2026-03-10 11:51:45'),
(230, 16, 'Edit Supplier', 'Updated supplier: KimWatches Shop → KimWatches Shop', '2026-03-10 11:51:59'),
(231, 16, 'Delete Supplier', 'Deleted supplier: SonJingWoo Shop', '2026-03-10 11:57:34'),
(232, 16, 'Edit Supplier', 'Updated supplier: KimWatches Shop → KimWatches Shop', '2026-03-10 12:34:05'),
(233, 16, 'Edit Supplier', 'Updated supplier: KimWatches Shop → KimWatches Shop', '2026-03-10 12:34:09'),
(234, 16, 'Edit User', 'Updated user (rex11840@gmail.com) → Email/Password/Role/Department/Photo changed.', '2026-03-12 12:58:47'),
(235, 16, 'Edit Inventory', 'Updated inventory: Computer Set 1', '2026-03-12 12:59:36'),
(236, 16, 'Add User', 'Created new user: sample1@gmail.com', '2026-03-12 13:00:28'),
(237, 16, 'Edit Inventory', 'Updated inventory: Computer Set 1', '2026-03-12 13:00:40'),
(238, 16, 'Edit Inventory', 'Updated inventory: System Unit', '2026-03-12 13:01:08'),
(239, 16, 'Edit Inventory', 'Updated inventory: Table', '2026-03-12 13:01:12'),
(240, 16, 'Edit Inventory', 'Updated inventory: System Unit', '2026-03-12 13:01:19'),
(241, 16, 'Edit Inventory', 'Updated inventory: Table', '2026-03-12 13:01:27'),
(242, 16, 'Delete User', 'Deleted user: rex11840@gmail.com', '2026-03-12 13:01:31'),
(243, 16, 'Edit User', 'Updated user (sample1@gmail.com) → Email/Role/Department/Photo changed.', '2026-03-12 13:02:07'),
(244, 16, 'Edit User', 'Updated user (sample1@gmail.com) → Email/Role/Department/Photo changed.', '2026-03-12 13:02:17'),
(245, 16, 'Edit User', 'Updated user (rex11840@gmail.com) → Email/Password/Role/Department/Photo changed.', '2026-03-22 21:57:56'),
(246, 16, 'Edit Inventory', 'Updated inventory: System Unit', '2026-03-29 20:18:40'),
(247, 18, 'Add Department User', 'Created new user: asdasd@gmail.com in their department', '2026-04-01 08:25:53'),
(248, 18, 'Edit Department User', 'Updated user (asdasd@gmail.com) → Email/Role/Photo changed.', '2026-04-01 08:30:09'),
(249, 18, 'Add Department User', 'Created new user: martin.martinez@gmail.com in their department', '2026-04-01 08:48:33'),
(250, 18, 'Delete Department User', 'Deleted user: martin.martinez@gmail.com from Account Management - CICTE', '2026-04-01 08:48:48'),
(251, 16, 'Delete Inventory', 'Deleted inventory item', '2026-04-01 09:53:05'),
(252, 16, 'Delete Inventory', 'Deleted inventory item', '2026-04-01 09:53:07'),
(253, 16, 'Delete Inventory', 'Deleted inventory item', '2026-04-01 09:53:09'),
(254, 16, 'Delete Inventory', 'Deleted inventory item', '2026-04-01 09:53:16'),
(255, 16, 'Delete Inventory', 'Deleted inventory item', '2026-04-01 09:53:22'),
(256, 16, 'Delete Item', 'Deleted item: table', '2026-04-01 09:53:46'),
(257, 16, 'Delete Item', 'Deleted item: RAM', '2026-04-01 09:53:48'),
(258, 16, 'Delete Item', 'Deleted item: SSD', '2026-04-01 09:53:49'),
(259, 16, 'Add Item', 'Added item: Laptop', '2026-04-01 10:04:07'),
(260, 16, 'Add Inventory', 'Added inventory: Laptop with quantity: 2', '2026-04-01 10:04:40'),
(261, 16, 'Add Component', 'Added component to inventory: 38', '2026-04-01 10:04:51'),
(262, 16, 'Edit Inventory', 'Updated inventory: Laptop', '2026-04-01 10:04:53'),
(263, 16, 'Add Item', 'Added item: RAM', '2026-04-01 10:06:13'),
(264, 16, 'Add Supplier', 'Added supplier: SonJingWoo Shop', '2026-04-01 10:06:45'),
(265, 16, 'Edit Item', 'Updated item: RAM → RAM', '2026-04-01 10:06:54'),
(266, 16, 'Add Component', 'Added component to inventory: 38', '2026-04-01 10:07:14'),
(267, 16, 'Edit Inventory', 'Updated inventory: Laptop', '2026-04-01 10:07:16'),
(268, 16, 'Delete Component', 'Deleted component: 53', '2026-04-01 10:07:16'),
(269, 16, 'Delete Component', 'Deleted component: 53', '2026-04-01 10:07:16'),
(270, 16, 'Edit Inventory', 'Updated inventory: Laptop', '2026-04-01 10:07:28'),
(271, 16, 'Edit Item', 'Updated item: RAM → RAM', '2026-04-01 20:14:34'),
(272, 16, 'Edit Item', 'Updated item: RAM → RAM', '2026-04-01 20:24:02'),
(273, 16, 'Edit Item', 'Updated item: RAM → RAM', '2026-04-01 20:24:15'),
(274, 16, 'Add User', 'Created new user: comptroller@gmail.com', '2026-04-10 18:49:51'),
(275, 16, 'Add User', 'Created new user: gsd@gmail.com', '2026-04-11 19:02:37'),
(276, 16, 'Edit User', 'Updated user (gsd@gmail.com) → Email/Password/Role/Department/Photo changed.', '2026-04-11 19:04:39'),
(277, 16, 'Edit User', 'Updated user (comptroller@gmail.com) → Email/Password/Role/Department/Photo changed.', '2026-04-11 19:04:51'),
(278, 16, 'Edit User', 'Updated user (comptroller@gmail.com) → Email/Role/Department/Photo changed.', '2026-04-11 19:05:26'),
(279, 16, 'Edit User', 'Updated user (gsd@gmail.com) → Email/Role/Department/Photo changed.', '2026-04-11 19:08:10'),
(280, 16, 'Edit User', 'Updated user (gsd@gmail.com) → Email/Role/Department/Photo changed.', '2026-04-11 19:08:13'),
(281, 16, 'Edit User', 'Updated user (comptroller@gmail.com) → Email/Role/Department/Photo changed.', '2026-04-11 19:08:19'),
(282, 16, 'Add User', 'Created new user: sample123@gmail.com', '2026-04-11 19:08:49'),
(283, 16, 'Delete User', 'Deleted user: sample123@gmail.com', '2026-04-11 19:09:19'),
(284, 16, 'Add User', 'Created new user: president@gmail.com', '2026-04-12 19:20:52'),
(285, 16, 'Add User', 'Created new user: canvasser@gmail.com', '2026-04-13 20:04:56'),
(286, 16, 'Add Department', 'Added department: GSD OFFICE', '2026-04-17 11:55:55'),
(287, 16, 'Delete User', 'Deleted user: canvasser@gmail.com', '2026-04-17 11:56:06'),
(288, 16, 'Edit User', 'Updated user (gsd@gmail.com) → Email/Role/Department/Photo changed.', '2026-04-17 11:56:15'),
(289, 22, 'Add Department User', 'Created new user: canvasser@gmail.com in their department', '2026-04-17 12:01:38'),
(290, 16, 'Edit User', 'Updated user (rex11840@gmail.com) → Email/Role/Department/Photo changed.', '2026-04-20 20:11:54'),
(291, 16, 'Add Department', 'Added department: COED', '2026-04-20 20:12:15'),
(292, 16, 'Add Facility', 'Added facility in dept 7: COAB Buildings / Room 1 (WLC-COED-01)', '2026-04-20 20:13:25'),
(293, 16, 'Edit User', 'Updated user (Sample@gmail.com) → Email/Role/Department/Photo changed.', '2026-04-20 20:16:15'),
(294, 16, 'Add Inventory', 'Added inventory: System Unit with quantity: 20', '2026-04-20 20:19:56'),
(295, 16, 'Edit Inventory', 'Updated inventory: System Unit', '2026-04-20 20:20:19'),
(296, 22, 'Add Department User', 'Created new user: rex@gmail.com in their department', '2026-04-20 23:18:17'),
(297, 16, 'Edit Item', 'Updated item: Laptop → Motherboard', '2026-04-21 09:37:35'),
(298, 16, 'Edit Item', 'Updated item: Motherboard → Motherboard', '2026-04-21 09:39:12'),
(299, 16, 'Edit Item', 'Updated item: RAM → RAM', '2026-04-21 09:39:21'),
(300, 16, 'Add Component', 'Added component to inventory: 49', '2026-04-21 09:42:32'),
(301, 16, 'Edit Inventory', 'Updated inventory: System Unit', '2026-04-21 09:42:33'),
(302, 16, 'Edit Inventory', 'Updated inventory: System Unit', '2026-04-21 09:43:41'),
(303, 16, 'Edit Inventory', 'Updated inventory: comp', '2026-04-21 09:43:56'),
(304, 16, 'Edit Inventory', 'Updated inventory: Computer Set 1', '2026-04-21 09:44:07'),
(305, 16, 'Edit Supplier', 'Updated supplier: SonJingWoo Shop → SonJingWoo Shop', '2026-04-21 09:48:27'),
(306, 16, 'Edit Supplier', 'Updated supplier: KimWatches Shop → KimWatches Shop', '2026-04-21 09:49:00'),
(307, 16, 'Edit Supplier', 'Updated supplier: RS SHOP → RS SHOP', '2026-04-21 09:49:35'),
(308, 16, 'Add Inventory', 'Added inventory: Computer set 1 with quantity: 1', '2026-04-21 15:23:41'),
(309, 16, 'Edit Department', 'Updated department: CICTE → CICTE', '2026-04-23 00:20:22'),
(310, 16, 'Edit Department', 'Updated department: CONAHS → CONAHS', '2026-04-23 00:21:35'),
(311, 16, 'Edit Department', 'Updated department: COAB → COAB', '2026-04-23 00:23:43'),
(312, 16, 'Edit Department', 'Updated department: COED → COED', '2026-04-23 00:23:54'),
(313, 16, 'Add Department', 'Added department: STAHM', '2026-04-23 00:28:01'),
(314, 16, 'Add Department', 'Added department: POLSCIE', '2026-04-23 00:28:20'),
(315, 16, 'Edit Department', 'Updated department: Inventory Office → Inventory Office', '2026-04-23 00:31:21'),
(316, 16, 'Edit Department', 'Updated department: GSD OFFICE → GSD OFFICE', '2026-04-23 00:31:50');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `canvasser_action_history`
--
ALTER TABLE `canvasser_action_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_cavh_request` (`request_id`),
  ADD KEY `idx_cavh_user` (`user_id`);

--
-- Indexes for table `comptroller_action_history`
--
ALTER TABLE `comptroller_action_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_cah_request` (`request_id`),
  ADD KEY `idx_cah_user` (`user_id`);

--
-- Indexes for table `department`
--
ALTER TABLE `department`
  ADD PRIMARY KEY (`department_id`),
  ADD KEY `idx_dept_default_lab_manager` (`default_lab_manager_user_id`);

--
-- Indexes for table `facilities`
--
ALTER TABLE `facilities`
  ADD PRIMARY KEY (`facility_id`),
  ADD KEY `department_id` (`department_id`);

--
-- Indexes for table `gsd_action_history`
--
ALTER TABLE `gsd_action_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_gah_request` (`request_id`),
  ADD KEY `idx_gah_user` (`user_id`);

--
-- Indexes for table `inventory`
--
ALTER TABLE `inventory`
  ADD PRIMARY KEY (`inventory_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `request_id` (`request_id`),
  ADD KEY `facility_id` (`facility_id`);

--
-- Indexes for table `items`
--
ALTER TABLE `items`
  ADD PRIMARY KEY (`item_id`),
  ADD KEY `supplier_id` (`supplier_id`);

--
-- Indexes for table `item_components`
--
ALTER TABLE `item_components`
  ADD PRIMARY KEY (`component_id`),
  ADD UNIQUE KEY `parent_item_id` (`parent_item_id`,`component_item_id`),
  ADD KEY `component_item_id` (`component_item_id`);

--
-- Indexes for table `item_supplier`
--
ALTER TABLE `item_supplier`
  ADD PRIMARY KEY (`item_supplier_id`),
  ADD UNIQUE KEY `uq_item_supplier` (`item_id`,`supplier_id`),
  ADD KEY `idx_item_supplier_item` (`item_id`),
  ADD KEY `idx_item_supplier_supplier` (`supplier_id`);

--
-- Indexes for table `log_history`
--
ALTER TABLE `log_history`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `president_action_history`
--
ALTER TABLE `president_action_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_pah_request` (`request_id`),
  ADD KEY `idx_pah_user` (`user_id`);

--
-- Indexes for table `purchase_requisition_audit`
--
ALTER TABLE `purchase_requisition_audit`
  ADD PRIMARY KEY (`purchase_audit_id`),
  ADD KEY `idx_pra_request` (`request_id`),
  ADD KEY `idx_pra_generated_by` (`generated_by_user_id`);

--
-- Indexes for table `purchase_requisition_audit_item`
--
ALTER TABLE `purchase_requisition_audit_item`
  ADD PRIMARY KEY (`purchase_audit_item_id`),
  ADD KEY `idx_prai_audit` (`purchase_audit_id`);

--
-- Indexes for table `requisition_form_approval`
--
ALTER TABLE `requisition_form_approval`
  ADD PRIMARY KEY (`request_id`);

--
-- Indexes for table `canvass_verification_approval`
--
ALTER TABLE `canvass_verification_approval`
  ADD PRIMARY KEY (`request_id`),
  ADD KEY `idx_cva_canvas_assignee` (`canvas_assignee_user_id`),
  ADD KEY `idx_cva_suggested_supplier` (`suggested_supplier_id`);

--
-- Indexes for table `purchase_requisition_approval`
--
ALTER TABLE `purchase_requisition_approval`
  ADD PRIMARY KEY (`request_id`);

--
-- Indexes for table `request_approval_suggested_supplier_item`
--
ALTER TABLE `request_approval_suggested_supplier_item`
  ADD PRIMARY KEY (`suggested_item_id`),
  ADD UNIQUE KEY `uq_req_canvass_detail` (`request_id`,`canvass_detail_id`),
  ADD KEY `idx_rassi_request` (`request_id`),
  ADD KEY `idx_rassi_supplier` (`supplier_id`),
  ADD KEY `fk_rassi_canvass_detail` (`canvass_detail_id`),
  ADD KEY `fk_rassi_user` (`selected_by_user_id`);

--
-- Indexes for table `requisition_canvass_detail`
--
ALTER TABLE `requisition_canvass_detail`
  ADD PRIMARY KEY (`canvass_detail_id`),
  ADD KEY `idx_rcd_request` (`request_id`),
  ADD KEY `idx_rcd_line` (`requisition_line_id`),
  ADD KEY `idx_rcd_user` (`user_id`);

--
-- Table structure for table `notification_views`
--
CREATE TABLE IF NOT EXISTS `notification_views` (
  `notification_view_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `request_id` int(11) NOT NULL,
  `notification_key` varchar(64) NOT NULL,
  `viewed_at` datetime NOT NULL,
  PRIMARY KEY (`notification_view_id`),
  UNIQUE KEY `idx_user_request_key` (`user_id`,`request_id`,`notification_key`),
  KEY `idx_user_key` (`user_id`,`notification_key`),
  KEY `idx_request` (`request_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Indexes for table `requisition_canvass_detail_supplier`
--
ALTER TABLE `requisition_canvass_detail_supplier`
  ADD PRIMARY KEY (`canvass_detail_supplier_id`),
  ADD UNIQUE KEY `uq_canvass_detail_supplier` (`canvass_detail_id`,`supplier_id`),
  ADD KEY `idx_cds_supplier` (`supplier_id`);

--
-- Indexes for table `requisition_item`
--
ALTER TABLE `requisition_item`
  ADD PRIMARY KEY (`request_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `department_id` (`department_id`),
  ADD KEY `facility_id` (`facility_id`);

--
-- Indexes for table `requisition_line`
--
ALTER TABLE `requisition_line`
  ADD PRIMARY KEY (`requisition_line_id`),
  ADD KEY `idx_rl_request` (`request_id`),
  ADD KEY `idx_rl_item` (`item_id`);

--
-- Indexes for table `suppliers`
--
ALTER TABLE `suppliers`
  ADD PRIMARY KEY (`supplier_id`);

--
-- Indexes for table `user`
--
ALTER TABLE `user`
  ADD PRIMARY KEY (`user_id`),
  ADD KEY `department_id` (`department_id`);

--
-- Indexes for table `user_activity`
--
ALTER TABLE `user_activity`
  ADD PRIMARY KEY (`activity_id`),
  ADD KEY `user_id` (`user_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `canvasser_action_history`
--
ALTER TABLE `canvasser_action_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `comptroller_action_history`
--
ALTER TABLE `comptroller_action_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- AUTO_INCREMENT for table `department`
--
ALTER TABLE `department`
  MODIFY `department_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `facilities`
--
ALTER TABLE `facilities`
  MODIFY `facility_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `gsd_action_history`
--
ALTER TABLE `gsd_action_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT for table `inventory`
--
ALTER TABLE `inventory`
  MODIFY `inventory_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=51;

--
-- AUTO_INCREMENT for table `items`
--
ALTER TABLE `items`
  MODIFY `item_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `item_components`
--
ALTER TABLE `item_components`
  MODIFY `component_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=59;

--
-- AUTO_INCREMENT for table `item_supplier`
--
ALTER TABLE `item_supplier`
  MODIFY `item_supplier_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `log_history`
--
ALTER TABLE `log_history`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=317;

--
-- AUTO_INCREMENT for table `president_action_history`
--
ALTER TABLE `president_action_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `purchase_requisition_audit`
--
ALTER TABLE `purchase_requisition_audit`
  MODIFY `purchase_audit_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `purchase_requisition_audit_item`
--
ALTER TABLE `purchase_requisition_audit_item`
  MODIFY `purchase_audit_item_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `request_approval_suggested_supplier_item`
--
ALTER TABLE `request_approval_suggested_supplier_item`
  MODIFY `suggested_item_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT for table `requisition_canvass_detail`
--
ALTER TABLE `requisition_canvass_detail`
  MODIFY `canvass_detail_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `requisition_canvass_detail_supplier`
--
ALTER TABLE `requisition_canvass_detail_supplier`
  MODIFY `canvass_detail_supplier_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT for table `requisition_item`
--
ALTER TABLE `requisition_item`
  MODIFY `request_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `requisition_line`
--
ALTER TABLE `requisition_line`
  MODIFY `requisition_line_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `suppliers`
--
ALTER TABLE `suppliers`
  MODIFY `supplier_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `user`
--
ALTER TABLE `user`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- AUTO_INCREMENT for table `user_activity`
--
ALTER TABLE `user_activity`
  MODIFY `activity_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=317;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `canvasser_action_history`
--
ALTER TABLE `canvasser_action_history`
  ADD CONSTRAINT `canvasser_action_history_ibfk_1` FOREIGN KEY (`request_id`) REFERENCES `requisition_item` (`request_id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `canvasser_action_history_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `user` (`user_id`) ON UPDATE CASCADE;

--
-- Constraints for table `comptroller_action_history`
--
ALTER TABLE `comptroller_action_history`
  ADD CONSTRAINT `comptroller_action_history_ibfk_1` FOREIGN KEY (`request_id`) REFERENCES `requisition_item` (`request_id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `comptroller_action_history_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `user` (`user_id`) ON UPDATE CASCADE;

--
-- Constraints for table `department`
--
ALTER TABLE `department`
  ADD CONSTRAINT `department_ibfk_default_lab_manager` FOREIGN KEY (`default_lab_manager_user_id`) REFERENCES `user` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `facilities`
--
ALTER TABLE `facilities`
  ADD CONSTRAINT `facilities_ibfk_1` FOREIGN KEY (`department_id`) REFERENCES `department` (`department_id`);

--
-- Constraints for table `gsd_action_history`
--
ALTER TABLE `gsd_action_history`
  ADD CONSTRAINT `gsd_action_history_ibfk_1` FOREIGN KEY (`request_id`) REFERENCES `requisition_item` (`request_id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `gsd_action_history_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `user` (`user_id`) ON UPDATE CASCADE;

--
-- Constraints for table `inventory`
--
ALTER TABLE `inventory`
  ADD CONSTRAINT `inventory_ibfk_1` FOREIGN KEY (`facility_id`) REFERENCES `facilities` (`facility_id`),
  ADD CONSTRAINT `inventory_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `user` (`user_id`);

--
-- Constraints for table `items`
--
ALTER TABLE `items`
  ADD CONSTRAINT `items_ibfk_1` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`supplier_id`);

--
-- Constraints for table `item_components`
--
ALTER TABLE `item_components`
  ADD CONSTRAINT `item_components_ibfk_1` FOREIGN KEY (`parent_item_id`) REFERENCES `inventory` (`inventory_id`),
  ADD CONSTRAINT `item_components_ibfk_2` FOREIGN KEY (`component_item_id`) REFERENCES `items` (`item_id`);

--
-- Constraints for table `item_supplier`
--
ALTER TABLE `item_supplier`
  ADD CONSTRAINT `item_supplier_ibfk_item` FOREIGN KEY (`item_id`) REFERENCES `items` (`item_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `item_supplier_ibfk_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`supplier_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `log_history`
--
ALTER TABLE `log_history`
  ADD CONSTRAINT `log_history_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `user` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `president_action_history`
--
ALTER TABLE `president_action_history`
  ADD CONSTRAINT `president_action_history_ibfk_1` FOREIGN KEY (`request_id`) REFERENCES `requisition_item` (`request_id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `president_action_history_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `user` (`user_id`) ON UPDATE CASCADE;

--
-- Constraints for table `purchase_requisition_audit`
--
ALTER TABLE `purchase_requisition_audit`
  ADD CONSTRAINT `fk_pra_generated_by` FOREIGN KEY (`generated_by_user_id`) REFERENCES `user` (`user_id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_pra_request` FOREIGN KEY (`request_id`) REFERENCES `requisition_item` (`request_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `purchase_requisition_audit_item`
--
ALTER TABLE `purchase_requisition_audit_item`
  ADD CONSTRAINT `fk_prai_audit` FOREIGN KEY (`purchase_audit_id`) REFERENCES `purchase_requisition_audit` (`purchase_audit_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `requisition_form_approval`
--
ALTER TABLE `requisition_form_approval`
  ADD CONSTRAINT `fk_rfa_request` FOREIGN KEY (`request_id`) REFERENCES `requisition_item` (`request_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `canvass_verification_approval`
--
ALTER TABLE `canvass_verification_approval`
  ADD CONSTRAINT `fk_cva_request` FOREIGN KEY (`request_id`) REFERENCES `requisition_item` (`request_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_cva_suggested_supplier` FOREIGN KEY (`suggested_supplier_id`) REFERENCES `suppliers` (`supplier_id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `purchase_requisition_approval`
--
ALTER TABLE `purchase_requisition_approval`
  ADD CONSTRAINT `fk_pra_pr_request` FOREIGN KEY (`request_id`) REFERENCES `requisition_item` (`request_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `request_approval_suggested_supplier_item`
--
ALTER TABLE `request_approval_suggested_supplier_item`
  ADD CONSTRAINT `fk_rassi_canvass_detail` FOREIGN KEY (`canvass_detail_id`) REFERENCES `requisition_canvass_detail` (`canvass_detail_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_rassi_request` FOREIGN KEY (`request_id`) REFERENCES `requisition_item` (`request_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_rassi_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`supplier_id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_rassi_user` FOREIGN KEY (`selected_by_user_id`) REFERENCES `user` (`user_id`) ON UPDATE CASCADE;

--
-- Constraints for table `requisition_canvass_detail`
--
ALTER TABLE `requisition_canvass_detail`
  ADD CONSTRAINT `rcd_fk_line` FOREIGN KEY (`requisition_line_id`) REFERENCES `requisition_line` (`requisition_line_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `rcd_fk_request` FOREIGN KEY (`request_id`) REFERENCES `requisition_item` (`request_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `rcd_fk_user` FOREIGN KEY (`user_id`) REFERENCES `user` (`user_id`) ON UPDATE CASCADE;

--
-- Constraints for table `requisition_canvass_detail_supplier`
--
ALTER TABLE `requisition_canvass_detail_supplier`
  ADD CONSTRAINT `cds_fk_detail` FOREIGN KEY (`canvass_detail_id`) REFERENCES `requisition_canvass_detail` (`canvass_detail_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `cds_fk_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`supplier_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `requisition_item`
--
ALTER TABLE `requisition_item`
  ADD CONSTRAINT `requisition_item_ibfk_1` FOREIGN KEY (`department_id`) REFERENCES `department` (`department_id`),
  ADD CONSTRAINT `requisition_item_ibfk_2` FOREIGN KEY (`facility_id`) REFERENCES `facilities` (`facility_id`),
  ADD CONSTRAINT `requisition_item_ibfk_3` FOREIGN KEY (`user_id`) REFERENCES `user` (`user_id`);

--
-- Constraints for table `requisition_line`
--
ALTER TABLE `requisition_line`
  ADD CONSTRAINT `requisition_line_ibfk_item` FOREIGN KEY (`item_id`) REFERENCES `items` (`item_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `requisition_line_ibfk_request` FOREIGN KEY (`request_id`) REFERENCES `requisition_item` (`request_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `user_activity`
--
ALTER TABLE `user_activity`
  ADD CONSTRAINT `user_activity_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `user` (`user_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
