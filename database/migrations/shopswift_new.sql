-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Máy chủ: 127.0.0.1
-- Thời gian đã tạo: Th3 18, 2026 lúc 05:44 PM
-- Phiên bản máy phục vụ: 10.4.32-MariaDB
-- Phiên bản PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Cơ sở dữ liệu: `shopswiftv2`
--

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `accounts`
--

CREATE TABLE `accounts` (
  `account_id` int(11) NOT NULL,
  `account_name` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `last_login_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `failed_attempts` int(11) DEFAULT 0,
  `locked_until` datetime DEFAULT NULL,
  `password_changed_at` datetime DEFAULT NULL,
  `last_failed_login_at` datetime DEFAULT NULL,
  `two_fa_enabled` tinyint(1) DEFAULT 0,
  `two_fa_secret` varchar(100) DEFAULT NULL,
  `password_reset_token` varchar(255) DEFAULT NULL,
  `reset_token_expires_at` datetime DEFAULT NULL,
  `account_type` enum('local','google','facebook','sso') DEFAULT 'local',
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Đang đổ dữ liệu cho bảng `accounts`
--

INSERT INTO `accounts` (`account_id`, `account_name`, `password`, `last_login_at`, `created_at`, `failed_attempts`, `locked_until`, `password_changed_at`, `last_failed_login_at`, `two_fa_enabled`, `two_fa_secret`, `password_reset_token`, `reset_token_expires_at`, `account_type`, `is_active`) VALUES
(1, 'admin', '$2y$10$NPA3DJe1AM3bragduTJhrOmg1uglSV34pTAtaBN5CpGj/BpfIrTse', '2026-03-16 17:10:54', '2025-08-10 22:48:46', 0, NULL, NULL, NULL, 0, NULL, NULL, NULL, 'local', 1),
(2, 'customer1', '$2y$10$NPA3DJe1AM3bragduTJhrOmg1uglSV34pTAtaBN5CpGj/BpfIrTse', NULL, '2025-08-10 22:48:46', 0, NULL, NULL, NULL, 0, NULL, NULL, NULL, 'local', 1),
(3, 'customer2', '$2y$10$NPA3DJe1AM3bragduTJhrOmg1uglSV34pTAtaBN5CpGj/BpfIrTse', NULL, '2025-08-10 22:48:46', 0, NULL, NULL, NULL, 0, NULL, NULL, NULL, 'local', 1),
(4, 'shipper1', '$2y$10$kexvWcbcPiRhoCzY2UY1N.Y.iEjyN/jyv9eX7ub8569NbAo/auX2C', '2025-12-30 11:50:15', '2025-08-10 22:48:46', 0, NULL, '2025-11-16 22:02:50', NULL, 0, NULL, NULL, NULL, 'local', 1),
(5, 'shipper2', '$2y$10$mPWKElHmSRabQIbdWsp1Xe8.G5VY1sMBrwVi9pjaA/FQdg6qEx71i', NULL, '2025-08-10 22:48:46', 0, NULL, NULL, NULL, 0, NULL, NULL, NULL, 'local', 1),
(7, 'test_user', '$2y$10$q13vA19F.I9ozO0kl3yO.e/M2zB2ozbiFtzw1oj2PkGBFcXcinSJW', NULL, '2025-08-11 00:17:31', 2, NULL, NULL, '2025-08-11 13:06:06', 0, NULL, NULL, NULL, 'local', 1),
(8, 'thanhle', '$2y$10$u67LK5YXu24QeT.40dWjGueVUr4h006U12qrpQf4iaQLFfRip41rm', NULL, '2025-08-11 17:44:54', 0, NULL, '2025-08-21 17:46:23', NULL, 0, NULL, 'dfd755ab8f1126210e524042cc90b17b867984806e4ee2b58a06944474b77e95', '2025-08-21 17:59:34', 'local', 1),
(9, 'shipper3', '$2y$10$G5cIq1/MAgssfsidQ/KpGenyR9yYa2t4OMm3mec/k8OZwBGLb28KS', NULL, '2025-08-20 13:27:45', 0, NULL, NULL, NULL, 0, NULL, NULL, NULL, 'local', 1),
(10, 'shipper4', '$2y$10$mPWKElHmSRabQIbdWsp1Xe8.G5VY1sMBrwVi9pjaA/FQdg6qEx71i', NULL, '2025-08-20 13:27:45', 0, NULL, NULL, NULL, 0, NULL, NULL, NULL, 'local', 1),
(11, 'aa', '$2y$10$NPY8BMeoMX8drwXr6/eUT.ZVtMKjFMNgCM6wRGEljJlc.vECoLK3G', NULL, '2025-08-19 01:11:01', 0, NULL, NULL, NULL, 0, NULL, NULL, NULL, 'local', 1),
(13, 'shipper_new1', '$2y$10$G5cIq1/MAgssfsidQ/KpGenyR9yYa2t4OMm3mec/k8OZwBGLb28KS', NULL, '2025-08-20 13:33:39', 0, NULL, NULL, NULL, 0, NULL, NULL, NULL, 'local', 1),
(14, 'shipper_new2', '$2y$10$mPWKElHmSRabQIbdWsp1Xe8.G5VY1sMBrwVi9pjaA/FQdg6qEx71i', NULL, '2025-08-20 13:33:39', 0, NULL, NULL, NULL, 0, NULL, NULL, NULL, 'local', 1),
(15, 'shipper_new3', '$2y$10$G5cIq1/MAgssfsidQ/KpGenyR9yYa2t4OMm3mec/k8OZwBGLb28KS', NULL, '2025-08-20 13:33:39', 0, NULL, NULL, NULL, 0, NULL, NULL, NULL, 'local', 1),
(16, 'shipper_new4', '$2y$10$mPWKElHmSRabQIbdWsp1Xe8.G5VY1sMBrwVi9pjaA/FQdg6qEx71i', NULL, '2025-08-20 13:33:39', 0, NULL, NULL, NULL, 0, NULL, NULL, NULL, 'local', 1),
(18, 'test_user_new', '$2y$10$7GudbEoCu1kQCyF44lhRquh6s7ve26S.E7VlBGyw.FAHcPxP45KkC', NULL, '2025-08-24 13:28:02', 0, NULL, NULL, NULL, 0, NULL, NULL, NULL, 'local', 1),
(22, 'thanhle123', '$2y$10$NFagNCf.3yFS8SaPCR.Wu.jhS1ZI6Khe3vCqr8SzUyILOwzyEpDVm', NULL, '2025-08-25 11:55:50', 0, NULL, NULL, NULL, 0, NULL, NULL, NULL, 'local', 1),
(23, 'ledatthanh', '202cb962ac59075b964b07152d234b70', NULL, '2025-11-11 19:44:58', 0, NULL, NULL, NULL, 0, NULL, NULL, NULL, 'local', 1);

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `activity_logs`
--

CREATE TABLE `activity_logs` (
  `log_id` int(11) NOT NULL,
  `entity_type` varchar(50) NOT NULL,
  `entity_id` int(11) NOT NULL,
  `action` enum('create','update','delete','status_change','assign','approve','reject','flag') NOT NULL,
  `changed_by` int(11) NOT NULL,
  `data_before` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`data_before`)),
  `data_after` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`data_after`)),
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Đang đổ dữ liệu cho bảng `activity_logs`
--

INSERT INTO `activity_logs` (`log_id`, `entity_type`, `entity_id`, `action`, `changed_by`, `data_before`, `data_after`, `created_at`) VALUES
(1, 'order', 1, 'create', 1, NULL, '{\"order_id\":1,\"customer_id\":2,\"status\":\"pending\"}', '2025-08-19 23:08:53'),
(2, 'order', 2, 'create', 1, NULL, '{\"order_id\":2,\"customer_id\":3,\"status\":\"pending\"}', '2025-08-19 23:08:53'),
(3, 'order', 2, 'status_change', 1, '{\"status\":\"pending\"}', '{\"status\":\"processing\"}', '2025-08-19 23:08:53'),
(4, 'order', 3, 'create', 1, NULL, '{\"order_id\":3,\"customer_id\":7,\"status\":\"pending\"}', '2025-08-19 23:08:53'),
(5, 'order', 3, 'status_change', 1, '{\"status\":\"pending\"}', '{\"status\":\"processing\"}', '2025-08-19 23:08:53'),
(6, 'order', 3, 'status_change', 1, '{\"status\":\"processing\"}', '{\"status\":\"shipping\"}', '2025-08-19 23:08:53'),
(7, 'order', 3, 'assign', 1, NULL, '{\"shipper_id\":4}', '2025-08-19 23:08:53'),
(8, 'order', 4, 'create', 1, NULL, '{\"order_id\":4,\"customer_id\":8,\"status\":\"pending\"}', '2025-08-19 23:08:53'),
(9, 'order', 4, 'status_change', 1, '{\"status\":\"pending\"}', '{\"status\":\"processing\"}', '2025-08-19 23:08:53'),
(10, 'order', 4, 'status_change', 1, '{\"status\":\"processing\"}', '{\"status\":\"shipping\"}', '2025-08-19 23:08:53'),
(11, 'order', 4, 'status_change', 1, '{\"status\":\"shipping\"}', '{\"status\":\"completed\"}', '2025-08-19 23:08:53'),
(12, 'order', 5, 'create', 1, NULL, '{\"order_id\":5,\"customer_id\":2,\"status\":\"pending\"}', '2025-08-19 23:08:53'),
(13, 'order', 5, 'status_change', 1, '{\"status\":\"pending\"}', '{\"status\":\"cancelled\"}', '2025-08-19 23:08:53'),
(14, 'order', 1, 'assign', 1, NULL, '{\"shipper_id\":15}', '2025-08-20 14:25:14'),
(15, 'order', 2, 'assign', 1, NULL, '{\"shipper_id\":12}', '2025-08-20 14:25:37'),
(16, 'order', 2, 'assign', 1, NULL, '{\"order_id\":\"2\",\"shipper_id\":12}', '2025-08-21 15:44:27'),
(17, 'order', 2, 'assign', 1, NULL, '{\"order_id\":\"2\",\"shipper_id\":14}', '2025-08-21 15:44:58'),
(18, 'order', 2, 'status_change', 1, NULL, '{\"order_id\":\"2\",\"status\":\"processing\",\"reason\":\"Test update\"}', '2025-08-21 16:08:45'),
(19, 'order', 3, 'status_change', 1, NULL, '{\"order_id\":\"3\",\"status\":\"completed\",\"reason\":\"\"}', '2025-08-21 16:09:03'),
(20, 'order', 1, 'assign', 1, NULL, '{\"shipper_id\":12}', '2025-08-22 12:59:29'),
(21, 'order', 2, 'assign', 1, NULL, '{\"shipper_id\":4}', '2025-08-22 21:45:50'),
(22, 'order', 1, 'assign', 1, NULL, '{\"shipper_id\":15}', '2025-08-22 21:54:18'),
(23, 'order', 2, 'assign', 1, NULL, '{\"shipper_id\":4}', '2025-08-23 00:24:34'),
(24, 'order', 4, '', 1, NULL, '{\"email_sent\":true,\"email\":\"thanhle02032003@gmail.com\"}', '2025-08-23 01:41:10'),
(25, 'order', 4, '', 1, NULL, '{\"email_sent\":true,\"email\":\"thanhle02032003@gmail.com\"}', '2025-08-23 01:47:30'),
(26, 'order', 4, '', 1, NULL, '{\"email_sent\":true,\"email\":\"thanhle02032003@gmail.com\"}', '2025-08-23 01:50:17'),
(27, 'order', 4, '', 1, NULL, '{\"email_sent\":true,\"email\":\"thanhle02032003@gmail.com\"}', '2025-08-23 01:50:22'),
(28, 'order', 4, '', 1, NULL, '{\"email_sent\":true,\"email\":\"thanhle02032003@gmail.com\"}', '2025-08-23 01:50:28'),
(29, 'order', 4, '', 1, NULL, '{\"email_sent\":true,\"email\":\"thanhle02032003@gmail.com\"}', '2025-08-23 01:53:50'),
(30, 'order', 4, '', 1, NULL, '{\"email_sent\":true,\"email\":\"thanhle02032003@gmail.com\"}', '2025-08-23 01:54:11'),
(31, 'order', 4, '', 1, NULL, '{\"email_sent\":true,\"email\":\"thanhle02032003@gmail.com\"}', '2025-08-23 02:21:37'),
(32, 'order', 2, 'assign', 1, NULL, '{\"shipper_id\":15}', '2025-08-23 15:40:47'),
(33, 'product_variant', 1, 'update', 1, NULL, '{\"stock_quantity\":15}', '2025-08-23 18:10:51'),
(34, 'product_variant', 1, 'update', 1, NULL, '{\"stock_quantity\":15}', '2025-08-23 18:14:37'),
(35, 'product_variant', 1, 'update', 1, NULL, '{\"stock_quantity\":15}', '2025-08-23 18:16:48'),
(36, 'product_variant', 237, 'create', 1, NULL, '{\"product_id\":56,\"size_id\":2,\"sku\":\"BEIGE-M-5305\",\"stock_quantity\":3,\"status\":\"in_stock\"}', '2025-08-23 20:52:29'),
(37, 'product_variant', 238, 'create', 1, NULL, '{\"product_id\":56,\"size_id\":3,\"sku\":\"BEIGE-L-TEST\",\"stock_quantity\":5,\"status\":\"in_stock\"}', '2025-08-23 22:50:00'),
(38, 'product_variant', 240, 'create', 1, NULL, '{\"product_id\":57,\"size_id\":2,\"sku\":\"1-S-2793\",\"stock_quantity\":3,\"status\":\"in_stock\"}', '2025-08-23 23:53:09'),
(39, 'product_variant', 240, 'delete', 1, NULL, '{\"deactivated\":true}', '2025-08-23 23:54:54'),
(40, 'product_variant', 239, 'delete', 1, NULL, '{\"deactivated\":true}', '2025-08-24 00:08:05'),
(41, 'product_variant', 238, 'delete', 1, NULL, '{\"deactivated\":true}', '2025-08-24 00:13:21'),
(42, 'product_variant', 241, 'create', 1, NULL, '{\"product_id\":57,\"size_id\":3,\"sku\":\"1-L-6436\",\"stock_quantity\":2,\"status\":\"in_stock\"}', '2025-08-24 00:15:29'),
(43, 'product_variant', 241, 'delete', 1, NULL, '{\"deactivated\":true}', '2025-08-24 00:15:34'),
(44, 'product_variant', 99, 'delete', 1, NULL, '{\"deactivated\":true}', '2025-08-24 00:19:10'),
(45, 'product_variant', 245, 'create', 1, NULL, '{\"product_id\":57,\"size_id\":4,\"sku\":\"1-XL-3826\",\"stock_quantity\":3,\"status\":\"in_stock\"}', '2025-08-24 00:23:34'),
(46, 'product_variant', 245, 'delete', 1, NULL, '{\"deactivated\":true}', '2025-08-24 00:24:09'),
(47, 'product_variant', 247, 'update', 1, NULL, '{\"product_id\":58,\"size_id\":2,\"sku\":\"iuoadhiodhj\",\"stock_quantity\":0,\"status\":\"in_stock\"}', '2025-08-24 00:52:38'),
(48, 'product_variant', 247, 'update', 1, NULL, '{\"product_id\":58,\"size_id\":2,\"sku\":\"2-M-5489\",\"stock_quantity\":1,\"status\":\"in_stock\"}', '2025-08-24 00:52:59'),
(49, 'product_variant', 248, 'create', 1, NULL, '{\"product_id\":58,\"size_id\":3,\"sku\":\"2-L-4382\",\"stock_quantity\":3,\"status\":\"in_stock\"}', '2025-08-24 00:53:26'),
(50, 'product_variant', 248, 'delete', 1, NULL, '{\"deactivated\":true}', '2025-08-24 00:53:31'),
(51, 'product_variant', 247, 'delete', 1, NULL, '{\"deleted\":true}', '2025-08-24 01:00:05'),
(52, 'product_variant', 98, 'update', 1, NULL, '{\"product_id\":24,\"size_id\":1,\"sku\":\"BKWSJ-M\",\"stock_quantity\":7,\"status\":\"in_stock\"}', '2025-08-24 01:00:35'),
(53, 'product_variant', 98, 'update', 1, NULL, '{\"product_id\":24,\"size_id\":2,\"sku\":\"BKWSJ-M\",\"stock_quantity\":7,\"status\":\"out_of_stock\"}', '2025-08-24 01:01:02'),
(54, 'product_variant', 98, 'update', 1, NULL, '{\"product_id\":24,\"size_id\":2,\"sku\":\"BKWSJ-M\",\"stock_quantity\":7,\"status\":\"in_stock\"}', '2025-08-24 01:01:09'),
(55, 'product_variant', 98, 'update', 1, NULL, '{\"size_id\":2,\"sku\":\"BKWSJ-M-UPDATED\",\"stock_quantity\":10,\"status\":\"in_stock\"}', '2025-08-24 01:15:11'),
(56, 'product_variant', 249, 'create', 1, NULL, '{\"product_id\":58,\"size_id\":1,\"sku\":\"2-S-0380\",\"stock_quantity\":2,\"status\":\"in_stock\"}', '2025-08-24 01:23:22'),
(57, 'product_variant', 249, 'update', 1, NULL, '{\"size_id\":2,\"sku\":\"2-S-0380\",\"stock_quantity\":2,\"status\":\"in_stock\"}', '2025-08-24 01:23:29'),
(58, 'product_variant', 249, 'update', 1, NULL, '{\"size_id\":1,\"sku\":\"2-S-0380\",\"stock_quantity\":2,\"status\":\"in_stock\"}', '2025-08-24 11:15:14'),
(59, 'order', 2, 'assign', 1, NULL, '{\"shipper_id\":4}', '2025-11-13 10:51:22'),
(60, 'order', 1, 'assign', 1, NULL, '{\"shipper_id\":4}', '2025-11-13 10:52:41'),
(61, 'order', 1, 'assign', 1, NULL, '{\"shipper_id\":4}', '2025-11-13 10:57:12'),
(62, 'order', 1, 'assign', 1, NULL, '{\"shipper_id\":4}', '2025-11-13 10:58:22'),
(63, 'order', 1, 'assign', 1, NULL, '{\"shipper_id\":13}', '2025-11-13 11:02:10'),
(64, 'order', 2, 'assign', 1, NULL, '{\"shipper_id\":12}', '2025-11-13 11:16:04'),
(65, 'order', 2, '', 1, '{\"shipper_id\":4}', '{\"shipper_id\":12}', '2025-11-13 11:16:04'),
(66, 'order', 2, 'assign', 1, NULL, '{\"shipper_id\":4}', '2025-11-13 13:42:10'),
(67, 'order', 2, '', 1, '{\"shipper_id\":12}', '{\"shipper_id\":4}', '2025-11-13 13:42:10'),
(68, 'order', 2, 'assign', 1, NULL, '{\"shipper_id\":15}', '2025-11-13 13:42:40'),
(69, 'order', 2, '', 1, '{\"shipper_id\":4}', '{\"shipper_id\":15}', '2025-11-13 13:42:40'),
(70, 'order', 2, 'assign', 1, NULL, '{\"shipper_id\":4}', '2025-11-13 13:43:04'),
(71, 'order', 2, '', 1, '{\"shipper_id\":15}', '{\"shipper_id\":4}', '2025-11-13 13:43:04'),
(72, 'order', 5, 'assign', 1, NULL, '{\"shipper_id\":4}', '2025-11-13 17:21:20'),
(73, 'order', 5, 'assign', 1, NULL, '{\"shipper_id\":15}', '2025-11-13 17:32:33'),
(74, 'order', 5, '', 1, '{\"shipper_id\":4}', '{\"shipper_id\":15}', '2025-11-13 17:32:33'),
(75, 'order', 5, 'assign', 1, NULL, '{\"shipper_id\":4}', '2025-11-13 17:32:42'),
(76, 'order', 5, '', 1, '{\"shipper_id\":15}', '{\"shipper_id\":4}', '2025-11-13 17:32:42'),
(77, 'order', 5, 'assign', 1, NULL, '{\"shipper_id\":13}', '2025-11-13 19:42:25'),
(78, 'order', 5, '', 1, '{\"shipper_id\":4}', '{\"shipper_id\":13}', '2025-11-13 19:42:25'),
(79, 'order', 5, 'assign', 1, NULL, '{\"shipper_id\":4}', '2025-11-13 19:42:34'),
(80, 'order', 5, '', 1, '{\"shipper_id\":13}', '{\"shipper_id\":4}', '2025-11-13 19:42:34'),
(81, 'order', 5, 'assign', 1, NULL, '{\"shipper_id\":13}', '2025-11-13 21:09:57'),
(82, 'order', 5, '', 1, '{\"shipper_id\":4}', '{\"shipper_id\":13}', '2025-11-13 21:09:57'),
(83, 'order', 5, 'assign', 1, NULL, '{\"shipper_id\":4}', '2025-11-13 21:18:59'),
(84, 'order', 5, '', 1, '{\"shipper_id\":13}', '{\"shipper_id\":4}', '2025-11-13 21:18:59'),
(85, 'order', 5, 'assign', 1, NULL, '{\"shipper_id\":15}', '2025-11-13 21:34:29'),
(86, 'order', 5, '', 1, '{\"shipper_id\":4}', '{\"shipper_id\":15}', '2025-11-13 21:34:29'),
(87, 'order', 5, 'assign', 1, NULL, '{\"shipper_id\":4}', '2025-11-13 21:34:41'),
(88, 'order', 5, '', 1, '{\"shipper_id\":15}', '{\"shipper_id\":4}', '2025-11-13 21:34:41'),
(89, 'order', 5, '', 4, '{\"status\":\"processing\"}', '{\"status\":\"shipping\"}', '2025-11-13 21:43:32'),
(90, 'order', 1, 'assign', 1, NULL, '{\"shipper_id\":4}', '2025-11-16 21:29:24'),
(91, 'order', 1, '', 1, '{\"shipper_id\":13}', '{\"shipper_id\":4}', '2025-11-16 21:29:24'),
(92, 'order', 69, 'assign', 1, NULL, '{\"shipper_id\":4}', '2026-03-13 15:17:43');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `addresses`
--

CREATE TABLE `addresses` (
  `address_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `receiver_name` varchar(100) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `address_line` varchar(255) NOT NULL,
  `ward` varchar(100) NOT NULL,
  `district` varchar(100) NOT NULL,
  `province` varchar(100) NOT NULL,
  `is_default` tinyint(1) DEFAULT 0,
  `lat` decimal(10,6) DEFAULT NULL,
  `lng` decimal(10,6) DEFAULT NULL,
  `default_user_id` int(11) GENERATED ALWAYS AS (case when `is_default` then `user_id` else NULL end) STORED
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Đang đổ dữ liệu cho bảng `addresses`
--

INSERT INTO `addresses` (`address_id`, `user_id`, `receiver_name`, `phone`, `address_line`, `ward`, `district`, `province`, `is_default`, `lat`, `lng`) VALUES
(1, 2, 'Nguyễn Văn Nam', '+84912345678', '123 Đường ABC', 'Phường 1', 'Quận 1', 'TP.HCM', 1, 10.762622, 106.660172),
(3, 7, 'Nguyễn Văn A', '0123456789', '789 Đường DEF', 'Phường 3', 'Quận 5', 'TP.HCM', 1, 10.752622, 106.650172),
(4, 8, 'Lê Đạt Thành', '+84901234567', '321 Đường GHI', 'Phường 4', 'Quận 7', 'TP.HCM', 1, 10.732622, 106.720172),
(6, 2, 'Nguyen Van Nam', '+84912345678', '247/27/7 Hà Huy Giáp', 'Thạnh Lộc', 'Quận 12', 'Hồ Chí Minh', 0, NULL, NULL),
(7, 2, 'Nguyen Van Nam', '+84912345678', '123 Đường ABC', 'Phường 1', 'Quận 1', 'TP.HCM', 0, NULL, NULL),
(8, 2, 'Nguyen Van Nam', '+84912345678', '247/27/7 Hà Huy Giáp', 'Thạnh Lộc', 'Quận 12', 'Hồ Chí Minh', 0, NULL, NULL),
(9, 2, 'Nguyen Van Nam', '+84912345678', '247/27/7 Hà Huy Giáp', 'Thạnh Lộc', 'Quận 12', 'Hồ Chí Minh', 0, NULL, NULL),
(10, 2, 'Nguyen Van Nam', '+84912345678', '247/27/7 Hà Huy Giáp', 'Thạnh Lộc', 'Quận 12', 'Hồ Chí Minh', 0, NULL, NULL),
(11, 2, 'Nguyen Van Nam', '+84912345678', '247/27/7 Hà Huy Giáp', 'Thạnh Lộc', 'Quận 12', 'Hồ Chí Minh', 0, NULL, NULL),
(12, 2, 'Nguyen Van Nam', '+84912345678', '123 Đường ABC', 'Phường 1', 'Quận 1', 'TP.HCM', 0, NULL, NULL),
(13, 2, 'Nguyen Van Nam', '+84912345678', '1313', '131', '131', '131', 0, NULL, NULL),
(14, 2, 'Nguyen Van Nam', '+84912345678', '1313', '131', '131', '131', 0, NULL, NULL),
(15, 2, 'Nguyen Van Nam', '+84912345678', '13', '13', '13', '13', 0, NULL, NULL),
(16, 2, 'Nguyen Van Nam', '+84912345678', '13', '13', '13', '13', 0, NULL, NULL),
(17, 2, 'Nguyen Van Nam', '+84912345678', '247/27/7 Hà Huy Giáp', 'Thạnh Lộc', 'Quận 12', 'Hồ Chí Minh', 0, NULL, NULL),
(18, 2, 'Nguyen Van Nam', '+84912345678', '247/27/7 Hà Huy Giáp', 'Thạnh Lộc', 'Quận 12', 'Hồ Chí Minh', 0, NULL, NULL),
(19, 2, 'Nguyen Van Nam', '+84912345678', '13', '13', '13', '13', 0, NULL, NULL),
(20, 2, 'Nguyen Van Nam', '+84912345678', '13', '13', '13', '13', 0, NULL, NULL),
(21, 2, 'Nguyen Van Nam', '+84912345678', '12', '12', '12', '1', 0, NULL, NULL),
(23, 3, 'Nguyen Van Nam', '+84912345678', '247/27/7 Hà Huy Giáp', 'Thạnh Lộc', 'Quận 12', 'Hồ Chí Minh', 1, NULL, NULL),
(47, 2, 'Nguyen Van Nam', '+84912345678', '123', '123', '123', '123', 0, NULL, NULL),
(49, 2, 'Nguyen Van Nam', '+84912345678', '12', '12', '12', '12', 0, NULL, NULL),
(50, 2, 'Nguyen Van Nam', '+84912345678', '12', '12', '12', '12', 0, NULL, NULL),
(51, 2, 'Nguyen Van Nam', '+84912345678', '12', '12', '12', '12', 0, NULL, NULL),
(52, 2, 'Nguyen Van Nam', '+84912345678', '12', '12', '12', '12', 0, NULL, NULL),
(53, 2, 'Nguyen Van Nam', '+84912345678', '45', '45', '56', '56', 0, NULL, NULL),
(54, 2, 'Nguyen Van Nam', '+84912345678', '12', '12', '12', '12', 0, NULL, NULL),
(55, 2, 'Nguyen Van Nam', '+84912345678', '12', '12', '12', '12', 0, NULL, NULL),
(56, 2, 'Nguyen Van Nam', '+84912345678', '12', '12', '121', '12', 0, NULL, NULL),
(57, 2, 'Nguyen Van Nam', '+84912345678', '12', '12', '12', '12', 0, NULL, NULL),
(58, 2, 'Nguyen Van Nam', '+84912345678', '12', '12', '12', '12', 0, NULL, NULL),
(59, 2, 'Nguyen Van Nam', '+84912345678', '12', '12', '12', '12', 0, NULL, NULL),
(60, 2, 'Nguyen Van Nam', '+84912345678', '12', '12', '12', '12', 0, NULL, NULL),
(61, 2, 'Nguyen Van Nam', '+84912345678', '12', '12', '12', '12', 0, NULL, NULL),
(62, 2, 'Nguyen Van Nam', '+84912345678', '12', '12', '12', '12', 0, NULL, NULL),
(63, 2, 'Nguyen Van Nam', '+84912345678', '12', '12', '12', '12', 0, NULL, NULL),
(64, 2, 'Nguyen Van Nam', '+84912345678', '12', '12', '12', '12', 0, NULL, NULL),
(65, 2, 'Nguyen Van Nam', '+84912345678', '12', '12', '12', '12', 0, NULL, NULL),
(66, 2, 'Nguyen Van Nam', '+84912345678', '13', '123', '12', '123', 0, NULL, NULL),
(70, 2, 'Nguyen Van Nam', '+84912345678', 'rrfgfv', 'dgdggd', 'dgdgdg', 'dggd', 0, NULL, NULL),
(71, 2, 'Nguyen Van Nam', '+84912345678', 'iuwriuo', 'aiofifo', 'ákfjwskf', 'akjkfads', 0, NULL, NULL),
(72, 2, 'Nguyen Van Nam', '+84912345678', 'adad', 'ada', 'adda', 'ada', 0, NULL, NULL),
(73, 2, 'Nguyen Van Nam', '+84912345678', 'adfa', 'ada', 'adda', 'ad', 0, NULL, NULL),
(74, 2, 'Nguyen Van Nam', '+84912345678', 'ad', 'adad', 'adda', 'adda', 0, NULL, NULL),
(75, 2, 'Nguyen Van Nam', '+84912345678', 'ssf', 'sffs', 'sfsf', 'sfs', 0, NULL, NULL);

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `admin_notifications`
--

CREATE TABLE `admin_notifications` (
  `notification_id` int(11) NOT NULL,
  `type` varchar(50) NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `related_order_id` int(11) DEFAULT NULL,
  `related_shipper_id` int(11) DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Đang đổ dữ liệu cho bảng `admin_notifications`
--

INSERT INTO `admin_notifications` (`notification_id`, `type`, `title`, `message`, `related_order_id`, `related_shipper_id`, `is_read`, `created_at`) VALUES
(1, 'order_assigned', 'Đơn hàng mới được gán', 'Đơn hàng #1 đã được gán cho shipper Nguyễn Văn A', 1, 4, 0, '2025-10-28 13:30:00'),
(2, 'order_picked_up', 'Shipper đã lấy hàng', 'Shipper Nguyễn Văn A đã lấy hàng cho đơn #1', 1, 4, 0, '2025-10-28 14:15:00'),
(3, 'shipper_issue', 'Shipper báo sự cố', 'Shipper Trần Thị B báo gặp sự cố với đơn hàng #2', 2, 5, 0, '2025-10-28 14:20:00'),
(4, 'order_delayed', 'Đơn hàng bị delay', 'Đơn hàng #2 dự kiến giao muộn 30 phút', 2, 5, 0, '2025-10-28 14:25:00'),
(5, 'order_delivered', 'Giao hàng thành công', 'Đơn hàng #3 đã được giao thành công', 3, 12, 1, '2025-10-27 12:45:00'),
(6, 'order_cancelled', 'Khách hủy đơn', 'Khách hàng hủy đơn hàng #4', 4, NULL, 1, '2025-10-27 15:30:00'),
(7, 'shipper_offline', 'Shipper offline', 'Shipper Lê Văn C đã offline', NULL, 12, 1, '2025-10-27 18:00:00');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `carts`
--

CREATE TABLE `carts` (
  `cart_id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Đang đổ dữ liệu cho bảng `carts`
--

INSERT INTO `carts` (`cart_id`, `customer_id`, `created_at`, `updated_at`) VALUES
(1, 19, '2025-09-10 17:55:40', '2025-09-10 17:55:40'),
(6, 2, '2025-11-23 15:48:51', '2025-11-23 15:48:51'),
(7, 3, '2025-11-23 23:39:11', '2025-11-23 23:39:11'),
(8, 1, '2026-02-01 20:17:28', '2026-02-01 20:17:28');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `cart_items`
--

CREATE TABLE `cart_items` (
  `item_id` int(11) NOT NULL,
  `cart_id` int(11) NOT NULL,
  `variant_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL CHECK (`quantity` > 0),
  `unit_price_snapshot` decimal(10,2) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Đang đổ dữ liệu cho bảng `cart_items`
--

INSERT INTO `cart_items` (`item_id`, `cart_id`, `variant_id`, `quantity`, `unit_price_snapshot`, `created_at`, `updated_at`) VALUES
(1, 1, 2, 2, 850000.00, '2025-09-10 18:08:09', '2025-09-10 18:08:09'),
(2, 1, 7, 1, 750000.00, '2025-09-10 18:08:09', '2025-09-10 18:08:09'),
(21, 6, 1552, 1, 10000.00, '2026-01-25 18:48:25', '2026-01-25 23:38:01'),
(22, 6, 2, 1, 850000.00, '2026-02-01 20:23:19', '2026-02-01 20:23:19'),
(23, 6, 5, 1, 750000.00, '2026-02-01 20:23:23', '2026-02-01 20:23:23'),
(31, 7, 1552, 1, 10000.00, '2026-03-14 09:26:28', '2026-03-14 09:26:28');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `casso_transactions`
--

CREATE TABLE `casso_transactions` (
  `id` int(11) NOT NULL,
  `transaction_id` varchar(100) NOT NULL,
  `payment_id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `description` text DEFAULT NULL,
  `processed_at` datetime NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Đang đổ dữ liệu cho bảng `casso_transactions`
--

INSERT INTO `casso_transactions` (`id`, `transaction_id`, `payment_id`, `order_id`, `amount`, `description`, `processed_at`, `created_at`) VALUES
(3, 'tx_win_001', 37, 41, 10000.00, 'ORDER_41', '2025-12-06 17:21:26', '2025-12-06 17:21:26'),
(5, 'tx_order43', 39, 43, 10000.00, 'ORDER_43', '2025-12-06 17:46:28', '2025-12-06 17:46:28'),
(41, '13321407', 46, 50, 10000.00, 'PARTNER.DIRECT_DEBITS_VCB.MSE.115826678312.20260125.115826678312-0353126350_ORDER50', '2026-01-25 18:40:53', '2026-01-25 18:40:53'),
(42, '13322063', 47, 51, 10000.00, 'PARTNER.DIRECT_DEBITS_VCB.MSE.115838040301.20260125.115838040301-0353126350_ORDER51', '2026-01-25 18:40:56', '2026-01-25 18:40:56');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `categories`
--

CREATE TABLE `categories` (
  `category_id` int(11) NOT NULL,
  `category_name` varchar(100) NOT NULL,
  `slug` varchar(100) DEFAULT NULL,
  `parent_id` int(11) DEFAULT NULL,
  `position` int(11) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Đang đổ dữ liệu cho bảng `categories`
--

INSERT INTO `categories` (`category_id`, `category_name`, `slug`, `parent_id`, `position`, `is_active`) VALUES
(1, 'ALL', 'all', NULL, 1, 0),
(2, 'TOPS', 'tops', NULL, 2, 0),
(3, 'Áo sơ mi', 'shirts', NULL, 3, 1),
(4, 'Áo khoác & áo khoác', 'jackets-coats', NULL, 4, 1),
(5, 'Váy', 'skirts', NULL, 5, 1),
(6, 'Quần dài', 'pants', NULL, 6, 1),
(7, 'Dự phòng', 'accessories', NULL, 7, 1),
(8, 'adfff12113', 'adfff12113', NULL, 1, 0),
(9, 'àdafsfs', 'dafsfs', 3, 1, 0),
(10, 'adihjaihd', 'adihjaihd', NULL, 1, 0);

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `collections`
--

CREATE TABLE `collections` (
  `collection_id` int(11) NOT NULL,
  `collection_name` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `short_description` text DEFAULT NULL,
  `display_order` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `collections`
--

INSERT INTO `collections` (`collection_id`, `collection_name`, `slug`, `short_description`, `display_order`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'SPRING SUMER 2026', 'xuan-he-2026', 'VIVIENE presents a collection inspired by the master artisans of Chợ Lớn, a place where cultural fusion and the spirit of craftsmanship thrive. This collection honors the artistry of lion head maker Trầm Đức Hưng, calligrapher Kim Hy, and Hí Kịch actor Diệp Gia Bửu, whose dedication and skill have kept traditional crafts alive and flourishing.', 1, 1, '2025-10-26 15:30:22', '2026-02-01 19:18:27');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `collection_images`
--

CREATE TABLE `collection_images` (
  `image_id` int(11) NOT NULL,
  `collection_id` int(11) NOT NULL,
  `image_url` varchar(500) NOT NULL,
  `display_order` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `collection_images`
--

INSERT INTO `collection_images` (`image_id`, `collection_id`, `image_url`, `display_order`, `is_active`, `created_at`) VALUES
(1, 1, 'https://file.hstatic.net/200000911315/file/look_8_125d58f643eb4991a97328f198cef260_2048x2048.jpg', 1, 1, '2026-02-01 18:25:06'),
(2, 1, 'https://file.hstatic.net/200000911315/file/look_18_5383ab4cefce4df99605580da6fcfe59_2048x2048.jpg', 2, 1, '2026-02-01 18:25:06'),
(3, 1, 'https://file.hstatic.net/200000911315/file/look_16_71087d93a90d4a909063d1c1bebfd3da_2048x2048.jpg', 3, 1, '2026-02-01 18:25:06'),
(4, 1, 'https://file.hstatic.net/200000911315/file/look_5_0fd3d682171d47969b5b15eefaf52747_2048x2048.jpg', 4, 1, '2026-02-01 18:25:06'),
(5, 1, 'https://file.hstatic.net/200000911315/file/look_6_c61443ea87664e2c882e5e38b9d2f24c_2048x2048.jpg', 5, 1, '2026-02-01 18:25:06'),
(6, 1, 'https://file.hstatic.net/200000911315/file/look_9_d18409bc7eb342afa533843483d0c558_2048x2048.jpg', 6, 1, '2026-02-01 18:25:06'),
(7, 1, 'https://file.hstatic.net/200000911315/file/look_11_7209fd103d2f45ed8cfd0d8c997bc28b_2048x2048.jpg', 7, 1, '2026-02-01 18:25:06'),
(8, 1, 'https://file.hstatic.net/200000911315/file/look_11_7209fd103d2f45ed8cfd0d8c997bc28b_2048x2048.jpg', 8, 1, '2026-02-01 18:25:06');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `collection_products`
--

CREATE TABLE `collection_products` (
  `id` int(11) NOT NULL,
  `collection_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `display_order` int(11) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `collection_products`
--

INSERT INTO `collection_products` (`id`, `collection_id`, `product_id`, `display_order`, `created_at`) VALUES
(1, 1, 1, 1, '2025-10-26 15:30:22'),
(2, 1, 2, 2, '2025-10-26 15:30:22'),
(3, 1, 8, 3, '2025-10-26 15:30:22'),
(4, 1, 23, 4, '2025-10-26 15:30:22');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `contents`
--

CREATE TABLE `contents` (
  `content_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `content_type` enum('page','blog','faq','policy','editorial') NOT NULL,
  `content` text NOT NULL,
  `excerpt` text DEFAULT NULL,
  `featured_image` varchar(500) DEFAULT NULL,
  `status` enum('draft','published','archived','scheduled') DEFAULT 'draft',
  `publish_at` datetime DEFAULT NULL,
  `display_start` datetime DEFAULT NULL,
  `display_end` datetime DEFAULT NULL,
  `meta_title` varchar(255) DEFAULT NULL,
  `meta_description` text DEFAULT NULL,
  `meta_keywords` varchar(500) DEFAULT NULL,
  `author_id` int(11) DEFAULT NULL,
  `view_count` int(11) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `contents`
--

INSERT INTO `contents` (`content_id`, `title`, `slug`, `content_type`, `content`, `excerpt`, `featured_image`, `status`, `publish_at`, `display_start`, `display_end`, `meta_title`, `meta_description`, `meta_keywords`, `author_id`, `view_count`, `created_at`, `updated_at`) VALUES
(1, 'About Us', 'about-us', 'page', '<h1>About Gian Saigon</h1><p>We are a fashion brand dedicated to bringing you the latest trends...</p>', 'Learn more about our brand and mission', NULL, 'published', '2025-11-01 14:39:25', NULL, NULL, 'About Us - Gian Saigon', 'Learn more about Gian Saigon fashion brand', NULL, 1, 0, '2025-11-01 14:39:25', '2025-11-01 14:39:25'),
(2, 'Spring Summer 2024 Collection', 'spring-summer-2024-collection', 'editorial', '<h1>Spring Summer 2024 Collection</h1><p>Discover our latest collection featuring fresh colors and modern designs...</p>', 'Explore our Spring Summer 2024 fashion collection', NULL, 'published', '2025-11-01 14:39:25', NULL, NULL, 'Spring Summer 2024 Collection - Gian Saigon', 'Shop the latest Spring Summer 2024 fashion collection', NULL, 1, 0, '2025-11-01 14:39:25', '2025-11-01 14:39:25'),
(3, 'Fashion Trends for 2024', 'fashion-trends-2024', 'blog', '<h1>Fashion Trends for 2024</h1><p>Discover the top fashion trends that will dominate this year...</p>', 'Stay ahead with the latest fashion trends for 2024', NULL, 'published', '2025-11-01 14:39:25', NULL, NULL, 'Fashion Trends 2024 - Gian Saigon Blog', 'Explore the top fashion trends for 2024', NULL, 1, 0, '2025-11-01 14:39:25', '2025-11-01 14:39:25'),
(4, 'How to Return Products?', 'how-to-return-products', 'faq', '<h1>How to Return Products?</h1><p>To return a product, please follow these steps: 1. Contact our support team... 2. Pack the item securely... 3. Send it back within 30 days...</p>', 'Learn how to return products with our easy return process', NULL, 'published', '2025-11-01 14:39:25', NULL, NULL, 'How to Return Products - Gian Saigon', 'Step-by-step guide for returning products', NULL, 1, 0, '2025-11-01 14:39:25', '2025-11-01 14:39:25'),
(5, 'Privacy Policy', 'privacy-policy', 'policy', '<h1>Privacy Policy</h1><p>Your privacy is important to us. This policy explains how we collect, use, and protect your personal information...</p>', 'Read our privacy policy to understand how we protect your data', NULL, 'published', '2025-11-01 14:39:25', NULL, NULL, 'Privacy Policy - Gian Saigon', 'Our commitment to protecting your privacy and personal data', NULL, 1, 0, '2025-11-01 14:39:25', '2025-11-01 14:39:25'),
(6, 'Local brand', 'local-brand', 'page', 'Local brand sad<h2></h2>', 'adadda', 'https://www.google.com/imgres?q=%E1%BA%A3nh%20qu%E1%BA%A7n%20%C3%A1o&imgurl=https%3A%2F%2Ffile.hstatic.net%2F200000472237%2Ffile%2Fchup-anh-quan-ao-dep-bang-dien-thoai_ed543464b2c248588df949f9618361f8_grande.jpg&imgrefurl=https%3A%2F%2Fghn.vn%2Fblogs%2Ftip-ban-hang%2Fnhung-cach-chup-anh-quan-ao-dep-bang-dien-thoai-ma-shop-nen-biet&docid=AjyHExWNmfoIWM&tbnid=cftbZDl4Y7pxXM&vet=12ahUKEwiaoY_3xdCQAxV5XGwGHZyLAHsQM3oECBUQAA..i&w=600&h=337&hcb=2&ved=2ahUKEwiaoY_3xdCQAxV5XGwGHZyLAHsQM3oECBUQAA', 'draft', NULL, NULL, NULL, 'Local brand', 'Local brand', 'Local brand', NULL, 0, '2025-11-01 15:39:07', '2025-11-01 15:39:07'),
(7, 'Local brand', 'local-brand-1', 'page', 'Local brand sad<h2></h2>', 'adadda', 'https://www.google.com/imgres?q=%E1%BA%A3nh%20qu%E1%BA%A7n%20%C3%A1o&imgurl=https%3A%2F%2Ffile.hstatic.net%2F200000472237%2Ffile%2Fchup-anh-quan-ao-dep-bang-dien-thoai_ed543464b2c248588df949f9618361f8_grande.jpg&imgrefurl=https%3A%2F%2Fghn.vn%2Fblogs%2Ftip-ban-hang%2Fnhung-cach-chup-anh-quan-ao-dep-bang-dien-thoai-ma-shop-nen-biet&docid=AjyHExWNmfoIWM&tbnid=cftbZDl4Y7pxXM&vet=12ahUKEwiaoY_3xdCQAxV5XGwGHZyLAHsQM3oECBUQAA..i&w=600&h=337&hcb=2&ved=2ahUKEwiaoY_3xdCQAxV5XGwGHZyLAHsQM3oECBUQAA', 'draft', NULL, NULL, NULL, 'Local brand', 'Local brand', 'Local brand', NULL, 0, '2025-11-01 15:39:08', '2025-11-01 15:39:08'),
(8, 'Local brand', 'local-brand-2', 'page', 'Local brand sad<h2></h2>', 'adadda', 'https://www.google.com/imgres?q=%E1%BA%A3nh%20qu%E1%BA%A7n%20%C3%A1o&imgurl=https%3A%2F%2Ffile.hstatic.net%2F200000472237%2Ffile%2Fchup-anh-quan-ao-dep-bang-dien-thoai_ed543464b2c248588df949f9618361f8_grande.jpg&imgrefurl=https%3A%2F%2Fghn.vn%2Fblogs%2Ftip-ban-hang%2Fnhung-cach-chup-anh-quan-ao-dep-bang-dien-thoai-ma-shop-nen-biet&docid=AjyHExWNmfoIWM&tbnid=cftbZDl4Y7pxXM&vet=12ahUKEwiaoY_3xdCQAxV5XGwGHZyLAHsQM3oECBUQAA..i&w=600&h=337&hcb=2&ved=2ahUKEwiaoY_3xdCQAxV5XGwGHZyLAHsQM3oECBUQAA', 'draft', NULL, NULL, NULL, 'Local brand', 'Local brand', 'Local brand', NULL, 0, '2025-11-01 15:39:09', '2025-11-01 15:39:09'),
(9, 'Local brand', 'local-brand-3', 'page', 'Local brand sad<h2></h2>', 'adadda', 'https://www.google.com/imgres?q=%E1%BA%A3nh%20qu%E1%BA%A7n%20%C3%A1o&imgurl=https%3A%2F%2Ffile.hstatic.net%2F200000472237%2Ffile%2Fchup-anh-quan-ao-dep-bang-dien-thoai_ed543464b2c248588df949f9618361f8_grande.jpg&imgrefurl=https%3A%2F%2Fghn.vn%2Fblogs%2Ftip-ban-hang%2Fnhung-cach-chup-anh-quan-ao-dep-bang-dien-thoai-ma-shop-nen-biet&docid=AjyHExWNmfoIWM&tbnid=cftbZDl4Y7pxXM&vet=12ahUKEwiaoY_3xdCQAxV5XGwGHZyLAHsQM3oECBUQAA..i&w=600&h=337&hcb=2&ved=2ahUKEwiaoY_3xdCQAxV5XGwGHZyLAHsQM3oECBUQAA', 'draft', NULL, NULL, NULL, 'Local brand', 'Local brand', 'Local brand', NULL, 0, '2025-11-01 15:39:10', '2025-11-01 15:39:10'),
(10, 'Local brand', 'local-brand-4', 'page', 'Local brand sad<h2></h2>', 'adadda', 'https://www.google.com/imgres?q=%E1%BA%A3nh%20qu%E1%BA%A7n%20%C3%A1o&imgurl=https%3A%2F%2Ffile.hstatic.net%2F200000472237%2Ffile%2Fchup-anh-quan-ao-dep-bang-dien-thoai_ed543464b2c248588df949f9618361f8_grande.jpg&imgrefurl=https%3A%2F%2Fghn.vn%2Fblogs%2Ftip-ban-hang%2Fnhung-cach-chup-anh-quan-ao-dep-bang-dien-thoai-ma-shop-nen-biet&docid=AjyHExWNmfoIWM&tbnid=cftbZDl4Y7pxXM&vet=12ahUKEwiaoY_3xdCQAxV5XGwGHZyLAHsQM3oECBUQAA..i&w=600&h=337&hcb=2&ved=2ahUKEwiaoY_3xdCQAxV5XGwGHZyLAHsQM3oECBUQAA', 'draft', NULL, NULL, NULL, 'Local brand', 'Local brand', 'Local brand', NULL, 0, '2025-11-01 15:39:21', '2025-11-01 15:39:21'),
(11, 'Local brand', 'local-brand-5', 'page', 'Local brand sad<h2></h2>', 'adadda', 'https://www.google.com/imgres?q=%E1%BA%A3nh%20qu%E1%BA%A7n%20%C3%A1o&imgurl=https%3A%2F%2Ffile.hstatic.net%2F200000472237%2Ffile%2Fchup-anh-quan-ao-dep-bang-dien-thoai_ed543464b2c248588df949f9618361f8_grande.jpg&imgrefurl=https%3A%2F%2Fghn.vn%2Fblogs%2Ftip-ban-hang%2Fnhung-cach-chup-anh-quan-ao-dep-bang-dien-thoai-ma-shop-nen-biet&docid=AjyHExWNmfoIWM&tbnid=cftbZDl4Y7pxXM&vet=12ahUKEwiaoY_3xdCQAxV5XGwGHZyLAHsQM3oECBUQAA..i&w=600&h=337&hcb=2&ved=2ahUKEwiaoY_3xdCQAxV5XGwGHZyLAHsQM3oECBUQAA', 'draft', NULL, NULL, NULL, 'Local brand', 'Local brand', 'Local brand', NULL, 0, '2025-11-01 15:39:24', '2025-11-01 15:39:24'),
(12, 'Local brand', 'local-brand-6', 'page', 'Local brand sad<h2></h2>', 'adadda', 'https://www.google.com/imgres?q=%E1%BA%A3nh%20qu%E1%BA%A7n%20%C3%A1o&imgurl=https%3A%2F%2Ffile.hstatic.net%2F200000472237%2Ffile%2Fchup-anh-quan-ao-dep-bang-dien-thoai_ed543464b2c248588df949f9618361f8_grande.jpg&imgrefurl=https%3A%2F%2Fghn.vn%2Fblogs%2Ftip-ban-hang%2Fnhung-cach-chup-anh-quan-ao-dep-bang-dien-thoai-ma-shop-nen-biet&docid=AjyHExWNmfoIWM&tbnid=cftbZDl4Y7pxXM&vet=12ahUKEwiaoY_3xdCQAxV5XGwGHZyLAHsQM3oECBUQAA..i&w=600&h=337&hcb=2&ved=2ahUKEwiaoY_3xdCQAxV5XGwGHZyLAHsQM3oECBUQAA', 'draft', NULL, NULL, NULL, 'Local brand', 'Local brand', 'Local brand', NULL, 0, '2025-11-01 15:39:51', '2025-11-01 15:39:51'),
(13, 'Local brand', 'local-brand-7', 'page', 'Local brand sad<h2></h2>', 'adadda', 'https://www.google.com/imgres?q=%E1%BA%A3nh%20qu%E1%BA%A7n%20%C3%A1o&imgurl=https%3A%2F%2Ffile.hstatic.net%2F200000472237%2Ffile%2Fchup-anh-quan-ao-dep-bang-dien-thoai_ed543464b2c248588df949f9618361f8_grande.jpg&imgrefurl=https%3A%2F%2Fghn.vn%2Fblogs%2Ftip-ban-hang%2Fnhung-cach-chup-anh-quan-ao-dep-bang-dien-thoai-ma-shop-nen-biet&docid=AjyHExWNmfoIWM&tbnid=cftbZDl4Y7pxXM&vet=12ahUKEwiaoY_3xdCQAxV5XGwGHZyLAHsQM3oECBUQAA..i&w=600&h=337&hcb=2&ved=2ahUKEwiaoY_3xdCQAxV5XGwGHZyLAHsQM3oECBUQAA', 'draft', NULL, NULL, NULL, 'Local brand', 'Local brand', 'Local brand', NULL, 0, '2025-11-01 15:39:54', '2025-11-01 15:39:54'),
(14, 'Local brand', 'local-brand-8', 'page', 'Local brand sad<h2></h2>', 'adadda', 'https://www.google.com/imgres?q=%E1%BA%A3nh%20qu%E1%BA%A7n%20%C3%A1o&imgurl=https%3A%2F%2Ffile.hstatic.net%2F200000472237%2Ffile%2Fchup-anh-quan-ao-dep-bang-dien-thoai_ed543464b2c248588df949f9618361f8_grande.jpg&imgrefurl=https%3A%2F%2Fghn.vn%2Fblogs%2Ftip-ban-hang%2Fnhung-cach-chup-anh-quan-ao-dep-bang-dien-thoai-ma-shop-nen-biet&docid=AjyHExWNmfoIWM&tbnid=cftbZDl4Y7pxXM&vet=12ahUKEwiaoY_3xdCQAxV5XGwGHZyLAHsQM3oECBUQAA..i&w=600&h=337&hcb=2&ved=2ahUKEwiaoY_3xdCQAxV5XGwGHZyLAHsQM3oECBUQAA', 'draft', NULL, NULL, NULL, 'Local brand', 'Local brand', 'Local brand', NULL, 0, '2025-11-01 15:39:58', '2025-11-01 15:39:58'),
(17, '1213', '1213', 'blog', '1313', '1331', 'https://www.google.com/imgres?q=%E1%BA%A3nh%20qu%E1%BA%A7n%20%C3%A1o&imgurl=https%3A%2F%2Ffile.hstatic.net%2F200000472237%2Ffile%2Fchup-anh-quan-ao-dep-bang-dien-thoai_ed543464b2c248588df949f9618361f8_grande.jpg&imgrefurl=https%3A%2F%2Fghn.vn%2Fblogs%2Ftip-ban-hang%2Fnhung-cach-chup-anh-quan-ao-dep-bang-dien-thoai-ma-shop-nen-biet&docid=AjyHExWNmfoIWM&tbnid=cftbZDl4Y7pxXM&vet=12ahUKEwiaoY_3xdCQAxV5XGwGHZyLAHsQM3oECBUQAA..i&w=600&h=337&hcb=2&ved=2ahUKEwiaoY_3xdCQAxV5XGwGHZyLAHsQM3oECBUQAA', 'draft', NULL, NULL, NULL, '1', '121', '21', NULL, 1, '2025-11-02 14:34:34', '2025-11-02 14:34:34');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `content_blocks`
--

CREATE TABLE `content_blocks` (
  `content_block_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `content` text DEFAULT NULL,
  `position` enum('top','middle','bottom','about','collection','home') NOT NULL,
  `display_start` datetime DEFAULT NULL,
  `display_end` datetime DEFAULT NULL,
  `status` enum('visible','hidden') DEFAULT 'visible',
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `content_categories`
--

CREATE TABLE `content_categories` (
  `category_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `slug` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `content_categories`
--

INSERT INTO `content_categories` (`category_id`, `name`, `slug`, `description`, `created_at`) VALUES
(1, 'Fashion', 'fashion', 'Fashion trends and style guides', '2025-11-01 14:25:36'),
(2, 'Editorial', 'editorial', 'Editorial content and stories', '2025-11-01 14:25:36'),
(3, 'News', 'news', 'Latest news and updates', '2025-11-01 14:25:36');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `content_category_relations`
--

CREATE TABLE `content_category_relations` (
  `content_id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `content_category_relations`
--

INSERT INTO `content_category_relations` (`content_id`, `category_id`) VALUES
(2, 2),
(3, 1);

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `conversations`
--

CREATE TABLE `conversations` (
  `conversation_id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `label` enum('new','priority','handled') DEFAULT 'new',
  `last_updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `status` enum('open','closed') DEFAULT 'open'
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Đang đổ dữ liệu cho bảng `conversations`
--

INSERT INTO `conversations` (`conversation_id`, `customer_id`, `created_at`, `label`, `last_updated_at`, `status`) VALUES
(1, 2, '2025-08-24 14:52:24', 'new', '2025-11-25 06:01:06', 'open'),
(2, 3, '2025-08-24 15:02:48', 'new', '2025-11-27 17:04:27', 'open'),
(3, 19, '2025-08-27 09:18:15', 'new', '2025-08-27 16:46:27', 'open');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `customers`
--

CREATE TABLE `customers` (
  `user_id` int(11) NOT NULL,
  `loyalty_points` int(11) DEFAULT 0,
  `total_orders` int(11) DEFAULT 0,
  `note` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Đang đổ dữ liệu cho bảng `customers`
--

INSERT INTO `customers` (`user_id`, `loyalty_points`, `total_orders`, `note`, `created_at`, `updated_at`) VALUES
(1, 0, 0, 'Test customer for cart', '2025-09-10 18:08:08', '2025-09-10 18:08:08'),
(2, 0, 2, NULL, '2025-08-10 22:48:46', '2025-08-19 23:08:53'),
(3, 150, 1, NULL, '2025-08-10 22:48:46', '2025-08-19 23:08:53'),
(7, 0, 1, NULL, '2025-08-11 00:17:31', '2025-08-19 23:08:53'),
(8, 0, 1, NULL, '2025-08-11 17:44:54', '2025-08-19 23:08:53'),
(19, 0, 0, NULL, '2025-08-25 11:55:50', '2025-08-25 11:55:50');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `media`
--

CREATE TABLE `media` (
  `media_id` int(11) NOT NULL,
  `block_id` int(11) NOT NULL,
  `url` varchar(500) NOT NULL,
  `type` enum('image','video') NOT NULL,
  `alt_text` varchar(255) DEFAULT NULL,
  `order_index` int(11) DEFAULT 0,
  `uploaded_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `messages`
--

CREATE TABLE `messages` (
  `message_id` int(11) NOT NULL,
  `conversation_id` int(11) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `content` text DEFAULT NULL,
  `metadata` longtext DEFAULT NULL CHECK (json_valid(`metadata`)),
  `sent_at` datetime DEFAULT current_timestamp(),
  `is_read` tinyint(1) DEFAULT 0,
  `edited_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `is_link` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `messages`
--

INSERT INTO `messages` (`message_id`, `conversation_id`, `sender_id`, `content`, `metadata`, `sent_at`, `is_read`, `edited_at`, `deleted_at`, `is_link`) VALUES
(1, 1, 1, 'Xin chào! Tôi cần hỗ trợ về đơn hàng.', NULL, '2025-08-24 15:05:28', 0, NULL, '2025-08-26 21:41:54', 0),
(2, 1, 1, 'Cảm ơn bạn đã liên hệ! Tôi sẽ hỗ trợ bạn ngay.', NULL, '2025-08-24 15:29:30', 0, NULL, '2025-08-26 21:41:58', 0),
(3, 1, 1, 'xin chào', NULL, '2025-08-24 17:01:59', 0, NULL, '2025-08-26 21:42:05', 0),
(4, 1, 1, 'xin chào', NULL, '2025-08-24 17:02:07', 0, NULL, '2025-08-26 21:42:08', 0),
(5, 1, 1, 'xin chào', NULL, '2025-08-24 17:02:19', 0, NULL, '2025-08-26 21:42:11', 0),
(6, 1, 1, 'xin chào ', NULL, '2025-08-24 17:02:25', 0, NULL, '2025-08-26 22:24:39', 0),
(7, 1, 1, '?', NULL, '2025-08-26 10:06:35', 0, NULL, '2025-08-26 22:24:39', 0),
(8, 1, 1, '?', NULL, '2025-08-26 10:16:22', 0, NULL, '2025-08-27 03:03:00', 0),
(9, 1, 1, '?', NULL, '2025-08-26 10:16:31', 0, NULL, '2025-08-27 03:03:00', 0),
(10, 1, 1, 'xin chào', NULL, '2025-08-26 10:26:48', 0, NULL, '2025-08-27 03:03:00', 0),
(11, 1, 1, 'àkflan', NULL, '2025-08-26 10:27:01', 0, NULL, '2025-08-27 03:03:00', 0),
(12, 1, 1, 'adaf', NULL, '2025-08-26 10:28:03', 0, NULL, '2025-08-27 03:03:00', 0),
(13, 1, 1, 'âffaf', NULL, '2025-08-26 10:29:50', 0, NULL, '2025-08-27 03:03:00', 0),
(14, 1, 1, 'xin chào', NULL, '2025-08-26 12:00:35', 0, NULL, '2025-08-27 03:03:01', 0),
(15, 1, 1, 'hello', NULL, '2025-08-26 12:01:51', 0, NULL, '2025-08-27 03:03:01', 0),
(16, 1, 1, 'xin chào', NULL, '2025-08-26 12:05:57', 0, NULL, '2025-08-26 21:41:35', 0),
(17, 1, 1, '', NULL, '2025-08-26 13:15:42', 0, NULL, '2025-08-26 21:46:45', 0),
(18, 1, 1, '', NULL, '2025-08-26 13:17:12', 0, NULL, '2025-08-26 21:47:37', 0),
(19, 1, 1, '[Image]', NULL, '2025-08-26 13:33:17', 0, NULL, '2025-08-27 03:03:03', 0),
(20, 1, 1, '[Image]', NULL, '2025-08-26 13:34:28', 0, NULL, '2025-08-26 21:41:05', 0),
(21, 1, 1, '[Image]', NULL, '2025-08-26 13:39:21', 0, NULL, '2025-08-27 03:03:05', 0),
(22, 1, 1, '[Image]', NULL, '2025-08-26 13:40:07', 0, NULL, '2025-08-27 03:03:06', 0),
(23, 1, 1, '[Video]', NULL, '2025-08-26 13:47:09', 0, NULL, '2025-08-26 21:40:58', 0),
(26, 1, 1, '[Image]', NULL, '2025-08-26 18:23:34', 0, NULL, '2025-08-27 03:03:08', 0),
(27, 1, 1, 'adsad', NULL, '2025-08-26 18:30:27', 0, NULL, '2025-08-27 03:03:08', 0),
(28, 1, 1, '[Image]', NULL, '2025-08-26 18:33:32', 0, NULL, '2025-08-27 03:03:09', 0),
(29, 1, 1, '?', NULL, '2025-08-26 18:45:58', 0, NULL, '2025-08-27 03:03:09', 0),
(30, 1, 1, '[Image]', NULL, '2025-08-26 18:52:34', 0, NULL, '2025-08-27 03:03:09', 0),
(52, 1, 1, '', NULL, '2025-08-26 20:10:04', 0, NULL, '2025-08-27 02:43:10', 0),
(53, 1, 1, '', NULL, '2025-08-26 20:11:29', 0, NULL, '2025-08-27 02:43:05', 0),
(54, 1, 1, '[Video]', NULL, '2025-08-26 21:04:44', 0, NULL, '2025-08-27 02:43:03', 0),
(55, 1, 1, '', NULL, '2025-08-26 21:05:53', 0, NULL, '2025-08-27 02:43:02', 0),
(56, 1, 1, '[Video]', NULL, '2025-08-26 21:32:40', 0, NULL, '2025-08-27 02:41:58', 0),
(57, 1, 1, '[Video]', NULL, '2025-08-26 21:44:38', 0, NULL, '2025-08-27 03:03:12', 0),
(58, 2, 1, '[Image]', NULL, '2025-08-27 05:06:12', 0, NULL, NULL, 0),
(59, 2, 19, 'xin chào', NULL, '2025-08-27 09:01:05', 1, NULL, NULL, 0),
(60, 2, 19, 'a', NULL, '2025-08-27 09:01:48', 1, NULL, NULL, 0),
(61, 2, 19, 'xin chào', NULL, '2025-08-27 09:05:38', 1, NULL, NULL, 0),
(62, 2, 19, '[Image]', NULL, '2025-08-27 09:05:47', 1, NULL, NULL, 0),
(63, 2, 19, 'xin chào', NULL, '2025-08-27 09:10:31', 1, NULL, NULL, 0),
(64, 2, 19, 'hello', NULL, '2025-08-27 09:13:15', 1, NULL, NULL, 0),
(65, 3, 19, 'xin chào', NULL, '2025-08-27 09:18:25', 1, NULL, NULL, 0),
(66, 3, 1, 'chào bạn', NULL, '2025-08-27 09:18:41', 0, NULL, NULL, 0),
(67, 3, 19, 'ADADAAADAD', NULL, '2025-08-27 10:32:00', 1, NULL, NULL, 0),
(68, 3, 19, 'sasassaaas', NULL, '2025-08-27 10:59:11', 1, NULL, NULL, 0),
(69, 3, 1, 'dad', NULL, '2025-08-27 10:59:19', 0, NULL, NULL, 0),
(70, 3, 1, 'sasaas', NULL, '2025-08-27 11:04:13', 0, NULL, NULL, 0),
(71, 3, 19, 'sâs', NULL, '2025-08-27 11:04:20', 1, NULL, NULL, 0),
(72, 3, 19, 'a', NULL, '2025-08-27 11:06:26', 1, NULL, NULL, 0),
(73, 3, 1, 'a', NULL, '2025-08-27 11:06:35', 0, NULL, NULL, 0),
(74, 3, 1, 'âsas', NULL, '2025-08-27 11:25:13', 0, NULL, NULL, 0),
(75, 3, 19, 'âs', NULL, '2025-08-27 11:25:19', 1, NULL, NULL, 0),
(76, 3, 19, 'hello', NULL, '2025-08-27 12:16:58', 1, NULL, NULL, 0),
(77, 3, 19, '1233', NULL, '2025-08-27 12:19:50', 1, NULL, NULL, 0),
(78, 3, 19, '[Image]', NULL, '2025-08-27 12:25:03', 1, NULL, NULL, 0),
(79, 3, 1, 'akakdad', NULL, '2025-08-27 12:33:11', 0, NULL, NULL, 0),
(80, 3, 19, 'nhjlahndjhnaj', NULL, '2025-08-27 13:09:03', 1, NULL, NULL, 0),
(81, 3, 1, '[Image]', NULL, '2025-08-27 13:10:05', 0, NULL, NULL, 0),
(82, 3, 1, 'âs', NULL, '2025-08-27 13:26:31', 0, NULL, NULL, 0),
(83, 3, 1, 'asaas', NULL, '2025-08-27 13:27:26', 0, NULL, NULL, 0),
(84, 3, 1, 'âsas', NULL, '2025-08-27 13:46:47', 0, NULL, NULL, 0),
(85, 3, 1, '121311313', NULL, '2025-08-27 13:46:54', 0, NULL, NULL, 0),
(86, 3, 19, '12', NULL, '2025-08-27 13:46:58', 1, NULL, NULL, 0),
(87, 3, 19, 'sáasas', NULL, '2025-08-27 13:47:20', 1, NULL, NULL, 0),
(88, 3, 19, 'sáas', NULL, '2025-08-27 13:48:33', 1, NULL, NULL, 0),
(89, 3, 19, '1212', NULL, '2025-08-27 13:48:39', 1, NULL, NULL, 0),
(90, 3, 19, 'ashbas', NULL, '2025-08-27 15:27:06', 1, NULL, NULL, 0),
(91, 3, 19, 'ashbas', NULL, '2025-08-27 15:27:14', 1, NULL, NULL, 0),
(92, 3, 1, 'áhaj', NULL, '2025-08-27 15:27:45', 0, NULL, NULL, 0),
(93, 3, 1, 'test message from curl', NULL, '2025-08-27 15:36:51', 0, NULL, NULL, 0),
(94, 3, 1, 'adakdnkad', NULL, '2025-08-27 15:37:53', 0, NULL, NULL, 0),
(95, 3, 1, 'cfcxxc', NULL, '2025-08-27 15:38:48', 0, NULL, NULL, 0),
(96, 3, 1, 'akdjkadj', NULL, '2025-08-27 15:45:17', 0, NULL, NULL, 0),
(97, 3, 1, 'xin chào 123', NULL, '2025-08-27 15:45:34', 0, NULL, NULL, 0),
(98, 3, 1, 'tôi tên là lê đạt thành', NULL, '2025-08-27 15:45:58', 0, NULL, NULL, 0),
(99, 3, 19, 'bạn cần tư vấn gi', NULL, '2025-08-27 15:47:07', 1, NULL, NULL, 0),
(100, 3, 1, 'tôi tên là lê đạt thành', NULL, '2025-08-27 15:54:58', 0, NULL, NULL, 0),
(101, 3, 1, 'ạhdja', NULL, '2025-08-27 15:55:07', 0, NULL, NULL, 0),
(102, 3, 1, 'âsas', NULL, '2025-08-27 15:55:51', 0, NULL, NULL, 0),
(103, 3, 19, 'sâksa', NULL, '2025-08-27 15:56:21', 1, NULL, NULL, 0),
(104, 3, 19, 'sâksa', NULL, '2025-08-27 15:56:28', 1, NULL, NULL, 0),
(105, 3, 1, 'adkkdaad', NULL, '2025-08-27 16:02:59', 0, NULL, NULL, 0),
(106, 3, 1, '[Voice Message]', NULL, '2025-08-27 16:46:27', 0, NULL, NULL, 0),
(107, 1, 2, '[Image]', NULL, '2025-11-25 06:01:06', 1, NULL, NULL, 0),
(108, 2, 3, '?', NULL, '2025-11-26 06:04:22', 1, NULL, NULL, 0),
(109, 2, 3, '?', NULL, '2025-11-26 07:30:49', 1, NULL, NULL, 0),
(110, 2, 3, '?', NULL, '2025-11-26 07:36:33', 1, NULL, NULL, 0),
(111, 2, 3, '🙃', NULL, '2025-11-26 07:48:02', 1, NULL, NULL, 0),
(112, 2, 3, 'xin chào', NULL, '2025-11-27 15:29:56', 1, NULL, NULL, 0),
(113, 2, 3, '[Image]', NULL, '2025-11-27 15:32:58', 1, NULL, NULL, 0),
(114, 2, 3, '[Image]', NULL, '2025-11-27 15:33:39', 1, NULL, NULL, 0),
(115, 2, 3, '[Video]', NULL, '2025-11-27 15:35:49', 1, NULL, NULL, 0),
(116, 2, 3, '[Image]', NULL, '2025-11-27 15:36:42', 1, NULL, NULL, 0),
(117, 2, 3, '', NULL, '2025-11-27 16:28:39', 1, NULL, NULL, 0),
(118, 2, 3, 'qkdnekheoqheqheqhejknabndbakhjgahjdgjqjqejqehjqheqjehad;la;dl', NULL, '2025-11-27 16:31:23', 1, NULL, NULL, 0),
(119, 2, 3, '[Video]', NULL, '2025-11-27 16:33:30', 1, NULL, NULL, 0),
(120, 2, 3, 'adak;đa', NULL, '2025-11-27 16:34:52', 1, NULL, NULL, 0),
(121, 2, 3, 'adaadad', NULL, '2025-11-27 17:04:08', 1, NULL, NULL, 0),
(122, 2, 3, '12313', NULL, '2025-11-27 17:04:27', 1, NULL, NULL, 0);

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `message_media`
--

CREATE TABLE `message_media` (
  `media_id` int(11) NOT NULL,
  `message_id` int(11) NOT NULL,
  `url` varchar(500) NOT NULL,
  `public_id` varchar(255) DEFAULT NULL,
  `type` enum('image','video','file') NOT NULL,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Đang đổ dữ liệu cho bảng `message_media`
--

INSERT INTO `message_media` (`media_id`, `message_id`, `url`, `public_id`, `type`, `metadata`, `created_at`) VALUES
(0, 58, 'https://res.cloudinary.com/doywtb2gt/image/upload/v1756263971/messenger/clo4781_c6lctz.png', 'messenger/clo4781_c6lctz', '', '{\"file_name\":\"Screenshot 2025-01-15 121746.png\",\"file_size\":506461,\"width\":null,\"height\":null,\"format\":null}', '2025-08-27 05:06:12'),
(0, 62, 'https://res.cloudinary.com/doywtb2gt/image/upload/v1756278347/messenger/cloA49D_pbefp2.png', 'messenger/cloA49D_pbefp2', '', '{\"file_name\":\"Screenshot 2025-01-15 224929.png\",\"file_size\":225735,\"width\":null,\"height\":null,\"format\":null}', '2025-08-27 09:05:47'),
(0, 78, 'https://res.cloudinary.com/doywtb2gt/image/upload/v1756290303/messenger/clo1074_qftuis.png', 'messenger/clo1074_qftuis', '', '{\"file_name\":\"Screenshot 2025-01-15 121726.png\",\"file_size\":481601,\"width\":null,\"height\":null,\"format\":null}', '2025-08-27 12:25:03'),
(0, 81, 'https://res.cloudinary.com/doywtb2gt/image/upload/v1756293004/messenger/clo4B5E_qjhceu.png', 'messenger/clo4B5E_qjhceu', '', '{\"file_name\":\"Screenshot 2025-01-15 210342.png\",\"file_size\":283520,\"width\":null,\"height\":null,\"format\":null}', '2025-08-27 13:10:05'),
(0, 106, 'https://res.cloudinary.com/doywtb2gt/video/upload/v1756305987/messenger/voice-message_d6vabq.webm', 'messenger/voice-message_d6vabq', '', '{\"file_name\":\"voice-message.webm\",\"file_size\":77441,\"width\":null,\"height\":null,\"format\":null}', '2025-08-27 16:46:27'),
(0, 107, 'https://res.cloudinary.com/doywtb2gt/image/upload/v1764046865/messenger/cloE708_fda3mw.png', 'messenger/cloE708_fda3mw', '', '{\"file_name\":\"IMG_7600.png\",\"file_size\":2072755,\"width\":null,\"height\":null,\"format\":null}', '2025-11-25 06:01:06'),
(0, 113, 'https://res.cloudinary.com/doywtb2gt/image/upload/v1764253975/messenger/clo2A70_uwepwk.jpg', 'messenger/clo2A70_uwepwk', '', '{\"file_name\":\"z7261139082108_a7462255f8f9554630b0129e5ec211a0.jpg\",\"file_size\":531825,\"width\":null,\"height\":null,\"format\":null}', '2025-11-27 15:32:58'),
(0, 114, 'https://res.cloudinary.com/doywtb2gt/image/upload/v1764254018/messenger/cloC8F4_c8yvm7.jpg', 'messenger/cloC8F4_c8yvm7', '', '{\"file_name\":\"z7261138383177_2b0d36262441e6e3a45744b5c7c1aa0c.jpg\",\"file_size\":497641,\"width\":null,\"height\":null,\"format\":null}', '2025-11-27 15:33:39'),
(0, 115, 'https://res.cloudinary.com/doywtb2gt/video/upload/v1764254146/messenger/clo71C4_xeyjna.mp4', 'messenger/clo71C4_xeyjna', '', '{\"file_name\":\"1.mp4\",\"file_size\":11476505,\"width\":null,\"height\":null,\"format\":null}', '2025-11-27 15:35:49'),
(0, 116, 'https://res.cloudinary.com/doywtb2gt/image/upload/v1764254201/messenger/clo9F1B_awjlsg.jpg', 'messenger/clo9F1B_awjlsg', '', '{\"file_name\":\"z6655518379431_f9f52b3098ef584d988073bfdda5a618.jpg\",\"file_size\":173288,\"width\":null,\"height\":null,\"format\":null}', '2025-11-27 15:36:42'),
(0, 117, 'https://res.cloudinary.com/doywtb2gt/video/upload/v1764257318/messenger/voice-message_jm2ch9.webm', 'messenger/voice-message_jm2ch9', '', '{\"file_name\":\"voice-message.webm\",\"file_size\":52373,\"width\":null,\"height\":null,\"format\":null}', '2025-11-27 16:28:39'),
(0, 119, 'https://res.cloudinary.com/doywtb2gt/video/upload/v1764257608/messenger/clo7F83_pafz5n.mp4', 'messenger/clo7F83_pafz5n', '', '{\"file_name\":\"1.mp4\",\"file_size\":11476505,\"width\":null,\"height\":null,\"format\":null}', '2025-11-27 16:33:30');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `notifications`
--

CREATE TABLE `notifications` (
  `notification_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `type` varchar(50) NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text DEFAULT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`payload`)),
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  `read_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Đang đổ dữ liệu cho bảng `notifications`
--

INSERT INTO `notifications` (`notification_id`, `user_id`, `type`, `title`, `message`, `payload`, `is_read`, `created_at`, `read_at`) VALUES
(1, 4, 'new_order_assigned', 'Đơn hàng mới được gán', 'Bạn có đơn hàng #5 mới cần xử lý', '{\"order_id\":5,\"type\":\"new_order_assigned\"}', 1, '2025-11-13 17:21:25', '2025-11-13 17:21:37'),
(2, 15, 'new_order_assigned', 'Đơn hàng mới được gán', 'Bạn có đơn hàng #5 mới cần xử lý', '{\"order_id\":5,\"type\":\"new_order_assigned\"}', 0, '2025-11-13 17:32:33', NULL),
(3, 4, 'new_order_assigned', 'Đơn hàng mới được gán', 'Bạn có đơn hàng #5 mới cần xử lý', '{\"order_id\":5,\"type\":\"new_order_assigned\"}', 1, '2025-11-13 17:32:42', '2025-11-13 17:33:25'),
(4, 13, 'new_order_assigned', 'Đơn hàng mới được gán', 'Bạn có đơn hàng #5 mới cần xử lý', '{\"order_id\":5,\"type\":\"new_order_assigned\"}', 0, '2025-11-13 19:42:25', NULL),
(5, 4, 'new_order_assigned', 'Đơn hàng mới được gán', 'Bạn có đơn hàng #5 mới cần xử lý', '{\"order_id\":5,\"type\":\"new_order_assigned\"}', 1, '2025-11-13 19:42:34', '2025-11-13 19:42:50'),
(6, 13, 'new_order_assigned', 'Đơn hàng mới được gán', 'Bạn có đơn hàng #5 mới cần xử lý', '{\"order_id\":5,\"type\":\"new_order_assigned\"}', 0, '2025-11-13 21:09:57', NULL),
(7, 4, 'new_order_assigned', 'Đơn hàng mới được gán', 'Bạn có đơn hàng #5 mới cần xử lý', '{\"order_id\":5,\"type\":\"new_order_assigned\"}', 1, '2025-11-13 21:18:59', '2025-11-13 21:44:14'),
(8, 15, 'new_order_assigned', 'Đơn hàng mới được gán', 'Bạn có đơn hàng #5 mới cần xử lý', '{\"order_id\":5,\"type\":\"new_order_assigned\"}', 0, '2025-11-13 21:34:30', NULL),
(9, 4, 'new_order_assigned', 'Đơn hàng mới được gán', 'Bạn có đơn hàng #5 mới cần xử lý', '{\"order_id\":5,\"type\":\"new_order_assigned\"}', 1, '2025-11-13 21:34:41', '2025-11-13 21:34:56'),
(10, 4, 'new_order_assigned', 'Đơn hàng mới được gán', 'Bạn có đơn hàng #1 mới cần xử lý', '{\"order_id\":1,\"type\":\"new_order_assigned\"}', 1, '2025-11-16 21:29:29', '2025-11-16 21:29:50'),
(11, 4, 'new_order_assigned', 'Đơn hàng mới được gán', 'Bạn có đơn hàng #69 mới cần xử lý', '{\"order_id\":69,\"type\":\"new_order_assigned\"}', 0, '2026-03-13 15:17:47', NULL);

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `orders`
--

CREATE TABLE `orders` (
  `order_id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `address_id` int(11) DEFAULT NULL,
  `shipping_method_id` int(11) NOT NULL,
  `voucher_id` int(11) DEFAULT NULL,
  `voucher_code_applied` varchar(50) DEFAULT NULL,
  `discount_amount_applied` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total_amount` decimal(12,2) NOT NULL CHECK (`total_amount` >= 0),
  `shipping_fee` decimal(10,2) NOT NULL CHECK (`shipping_fee` >= 0),
  `cod_amount` decimal(12,2) DEFAULT 0.00,
  `status` enum('pending','processing','shipping','completed','cancelled','returned') DEFAULT 'pending',
  `shipping_status` enum('new_request','accepted','picked_up','delivering','arrived','delivered','completed','rejected') DEFAULT 'new_request',
  `shipping_status_updated_at` datetime DEFAULT current_timestamp(),
  `note` text DEFAULT NULL,
  `internal_note` text DEFAULT NULL,
  `estimated_delivery_at` datetime DEFAULT NULL,
  `invoice_number` varchar(50) DEFAULT NULL,
  `invoice_url` varchar(500) DEFAULT NULL,
  `shipping_address_snapshot` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`shipping_address_snapshot`)),
  `shipping_method_name_snapshot` varchar(100) DEFAULT NULL,
  `voucher_summary_snapshot` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`voucher_summary_snapshot`)),
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Đang đổ dữ liệu cho bảng `orders`
--

INSERT INTO `orders` (`order_id`, `customer_id`, `address_id`, `shipping_method_id`, `voucher_id`, `voucher_code_applied`, `discount_amount_applied`, `total_amount`, `shipping_fee`, `cod_amount`, `status`, `shipping_status`, `shipping_status_updated_at`, `note`, `internal_note`, `estimated_delivery_at`, `invoice_number`, `invoice_url`, `shipping_address_snapshot`, `shipping_method_name_snapshot`, `voucher_summary_snapshot`, `created_at`, `updated_at`) VALUES
(1, 2, 1, 1, 1, 'SALE20', 170000.00, 680000.00, 30000.00, 0.00, 'completed', 'completed', '2025-11-16 21:35:52', 'Giao hàng vào buổi tối', 'Khách yêu cầu giao sau 6h tối', '2025-08-22 23:08:53', 'INV-2025-001', NULL, '{\"receiver_name\":\"Nguyễn Văn Nam\",\"phone\":\"+84912345678\",\"address_line\":\"123 Đường ABC\",\"ward\":\"Phường 1\",\"district\":\"Quận 1\",\"province\":\"TP.HCM\"}', 'Giao hàng tiêu chuẩn', '{\"code\":\"SALE20\",\"discount_amount\":170000,\"discount_type\":\"percent\"}', '2025-08-19 23:08:53', '2025-11-16 21:35:52'),
(2, 3, NULL, 2, 2, 'WELCOME100K', 100000.00, 750000.00, 50000.00, 0.00, 'shipping', 'picked_up', '2025-12-30 11:57:44', 'Giao hàng nhanh', 'Khách VIP - ưu tiên xử lý', '2025-08-20 23:08:53', 'INV-2025-002', NULL, '{\"receiver_name\":\"Trần Thị Lan\",\"phone\":\"+84923456789\",\"address_line\":\"456 Đường XYZ\",\"ward\":\"Phường 2\",\"district\":\"Quận 3\",\"province\":\"TP.HCM\"}', 'Giao hàng nhanh', '{\"code\":\"WELCOME100K\",\"discount_amount\":100000,\"discount_type\":\"amount\"}', '2025-08-19 23:08:53', '2025-12-30 11:57:44'),
(3, 7, 3, 1, 3, 'NEWUSER15', 78000.00, 442000.00, 30000.00, 0.00, 'completed', 'completed', '2025-11-16 21:18:53', 'Giao hàng tiêu chuẩn', 'Đơn hàng test', '2025-08-21 23:08:53', 'INV-2025-003', NULL, '{\"receiver_name\":\"Nguyễn Văn A\",\"phone\":\"0123456789\",\"address_line\":\"789 Đường DEF\",\"ward\":\"Phường 3\",\"district\":\"Quận 5\",\"province\":\"TP.HCM\"}', 'Giao hàng tiêu chuẩn', '{\"code\":\"NEWUSER15\",\"discount_amount\":78000,\"discount_type\":\"percent\"}', '2025-08-19 23:08:53', '2025-11-16 21:18:53'),
(4, 8, 4, 3, NULL, NULL, 0.00, 590000.00, 0.00, 0.00, 'completed', 'new_request', '2025-11-15 16:00:38', 'Giao hàng miễn phí', 'Đơn hàng hoàn thành', '2025-08-18 23:08:53', 'INV-2025-004', NULL, '{\"receiver_name\":\"Lê Đạt Thành\",\"phone\":\"+84901234567\",\"address_line\":\"321 Đường GHI\",\"ward\":\"Phường 4\",\"district\":\"Quận 7\",\"province\":\"TP.HCM\"}', 'Giao hàng miễn phí', NULL, '2025-08-19 23:08:53', '2025-08-19 23:08:53'),
(5, 2, 1, 1, NULL, NULL, 0.00, 850000.00, 30000.00, 0.00, 'shipping', 'new_request', '2025-11-15 16:00:38', 'Đơn hàng bị hủy', 'Khách hủy do thay đổi ý định', '2025-08-22 23:08:53', 'INV-2025-005', NULL, '{\"receiver_name\":\"Nguyễn Văn Nam\",\"phone\":\"+84912345678\",\"address_line\":\"123 Đường ABC\",\"ward\":\"Phường 1\",\"district\":\"Quận 1\",\"province\":\"TP.HCM\"}', 'Giao hàng tiêu chuẩn', NULL, '2025-08-19 23:08:53', '2025-11-13 21:43:32'),
(8, 2, 10, 3, NULL, NULL, 0.00, 3400000.00, 0.00, 0.00, 'pending', 'new_request', '2025-11-23 23:08:18', '', '', NULL, 'INV-2025-006', NULL, NULL, NULL, NULL, '2025-11-23 23:08:18', '2025-11-23 23:08:18'),
(9, 2, 11, 3, NULL, NULL, 0.00, 3400000.00, 0.00, 0.00, 'pending', 'new_request', '2025-11-23 23:14:15', '', '', NULL, 'INV-2025-007', NULL, NULL, NULL, NULL, '2025-11-23 23:14:15', '2025-11-23 23:14:15'),
(10, 2, 12, 3, NULL, NULL, 0.00, 3400000.00, 0.00, 0.00, 'pending', 'new_request', '2025-11-23 23:14:22', '1313', '', NULL, 'INV-2025-008', NULL, NULL, NULL, NULL, '2025-11-23 23:14:22', '2025-11-23 23:14:22'),
(11, 3, NULL, 3, NULL, NULL, 0.00, 750000.00, 0.00, 0.00, 'pending', 'new_request', '2025-11-23 23:39:31', '', '', NULL, 'INV-2025-009', NULL, NULL, NULL, NULL, '2025-11-23 23:39:31', '2025-11-23 23:39:31'),
(12, 3, NULL, 3, NULL, NULL, 0.00, 750000.00, 0.00, 0.00, 'pending', 'new_request', '2025-11-23 23:40:00', '', '', NULL, 'INV-2025-010', NULL, NULL, NULL, NULL, '2025-11-23 23:40:00', '2025-11-23 23:40:00'),
(13, 3, NULL, 3, NULL, NULL, 0.00, 750000.00, 0.00, 0.00, 'pending', 'new_request', '2025-11-23 23:41:46', '', '', NULL, 'INV-2025-011', NULL, NULL, NULL, NULL, '2025-11-23 23:41:46', '2025-11-23 23:41:46'),
(14, 3, NULL, 1, NULL, NULL, 0.00, 780000.00, 30000.00, 0.00, 'pending', 'new_request', '2025-11-23 23:42:22', '', '', NULL, 'INV-2025-012', NULL, NULL, NULL, NULL, '2025-11-23 23:42:22', '2025-11-23 23:42:22'),
(15, 3, NULL, 3, NULL, NULL, 0.00, 750000.00, 0.00, 0.00, 'pending', 'new_request', '2025-11-23 23:45:13', '', '', NULL, 'INV-2025-013', NULL, NULL, NULL, NULL, '2025-11-23 23:45:13', '2025-11-23 23:45:13'),
(16, 3, NULL, 3, NULL, NULL, 0.00, 750000.00, 0.00, 0.00, 'pending', 'new_request', '2025-11-23 23:48:13', '', '', NULL, 'INV-2025-014', NULL, NULL, NULL, NULL, '2025-11-23 23:48:13', '2025-11-23 23:48:13'),
(17, 3, NULL, 3, NULL, NULL, 0.00, 750000.00, 0.00, 0.00, 'pending', 'new_request', '2025-11-23 23:48:44', '', '', NULL, 'INV-2025-015', NULL, NULL, NULL, NULL, '2025-11-23 23:48:44', '2025-11-23 23:48:44'),
(18, 3, NULL, 3, NULL, NULL, 0.00, 750000.00, 0.00, 0.00, 'pending', 'new_request', '2025-11-23 23:49:15', '', '', NULL, 'INV-2025-016', NULL, NULL, NULL, NULL, '2025-11-23 23:49:15', '2025-11-23 23:49:15'),
(19, 3, NULL, 3, NULL, NULL, 0.00, 750000.00, 0.00, 0.00, 'pending', 'new_request', '2025-11-23 23:51:46', '', '', NULL, 'INV-2025-017', NULL, NULL, NULL, NULL, '2025-11-23 23:51:46', '2025-11-23 23:51:46'),
(20, 3, NULL, 3, NULL, NULL, 0.00, 750000.00, 0.00, 0.00, 'pending', 'new_request', '2025-11-23 23:52:11', '', '', NULL, 'INV-2025-018', NULL, NULL, NULL, NULL, '2025-11-23 23:52:11', '2025-11-23 23:52:11'),
(21, 3, NULL, 3, NULL, NULL, 0.00, 750000.00, 0.00, 0.00, 'pending', 'new_request', '2025-11-23 23:58:25', '', '', NULL, 'INV-2025-019', NULL, NULL, NULL, NULL, '2025-11-23 23:58:25', '2025-11-23 23:58:25'),
(22, 3, NULL, 3, NULL, NULL, 0.00, 750000.00, 0.00, 0.00, 'pending', 'new_request', '2025-11-24 00:00:08', '', '', NULL, 'INV-2025-020', NULL, NULL, NULL, NULL, '2025-11-24 00:00:08', '2025-11-24 00:00:08'),
(23, 3, NULL, 3, NULL, NULL, 0.00, 750000.00, 0.00, 0.00, 'pending', 'new_request', '2025-11-24 00:34:08', '', '', NULL, 'INV-2025-021', NULL, NULL, NULL, NULL, '2025-11-24 00:34:08', '2025-11-24 00:34:08'),
(24, 3, NULL, 3, NULL, NULL, 0.00, 750000.00, 0.00, 0.00, 'processing', 'new_request', '2025-11-24 00:41:12', '', '', NULL, 'INV-2025-022', NULL, NULL, NULL, NULL, '2025-11-24 00:41:12', '2025-11-24 00:47:16'),
(25, 3, NULL, 3, NULL, NULL, 0.00, 750000.00, 0.00, 0.00, 'processing', 'new_request', '2025-11-25 11:40:40', '', '', NULL, 'INV-2025-023', NULL, NULL, NULL, NULL, '2025-11-25 11:40:40', '2025-11-25 11:42:46'),
(26, 2, 49, 3, NULL, NULL, 0.00, 2550000.00, 0.00, 0.00, 'processing', 'new_request', '2025-11-25 21:30:57', '', '', NULL, 'INV-2025-024', NULL, NULL, NULL, NULL, '2025-11-25 21:30:57', '2025-11-25 21:31:32'),
(28, 2, 51, 3, NULL, NULL, 0.00, 10000.00, 0.00, 0.00, 'pending', 'new_request', '2025-12-06 12:45:42', '', '', NULL, 'INV-2025-025', NULL, NULL, NULL, NULL, '2025-12-06 12:45:42', '2025-12-06 12:45:42'),
(29, 2, 52, 3, NULL, NULL, 0.00, 10000.00, 0.00, 0.00, 'pending', 'new_request', '2025-12-06 13:02:01', '', '', NULL, 'INV-2025-026', NULL, NULL, NULL, NULL, '2025-12-06 13:02:01', '2025-12-06 13:02:01'),
(30, 2, 53, 3, NULL, NULL, 0.00, 10000.00, 0.00, 0.00, 'pending', 'new_request', '2025-12-06 13:23:27', '', '', NULL, 'INV-2025-027', NULL, NULL, NULL, NULL, '2025-12-06 13:23:27', '2025-12-06 13:23:27'),
(31, 2, 54, 3, NULL, NULL, 0.00, 10000.00, 0.00, 0.00, 'pending', 'new_request', '2025-12-06 14:21:58', '', '', NULL, 'INV-2025-028', NULL, NULL, NULL, NULL, '2025-12-06 14:21:58', '2025-12-06 14:21:58'),
(32, 2, 55, 3, NULL, NULL, 0.00, 10000.00, 0.00, 0.00, 'pending', 'new_request', '2025-12-06 14:32:44', '', '', NULL, 'INV-2025-029', NULL, NULL, NULL, NULL, '2025-12-06 14:32:44', '2025-12-06 14:32:44'),
(33, 2, 56, 3, NULL, NULL, 0.00, 10000.00, 0.00, 0.00, 'pending', 'new_request', '2025-12-06 14:56:18', '', '', NULL, 'INV-2025-030', NULL, NULL, NULL, NULL, '2025-12-06 14:56:18', '2025-12-06 14:56:18'),
(34, 2, 57, 3, NULL, NULL, 0.00, 10000.00, 0.00, 0.00, 'pending', 'new_request', '2025-12-06 14:58:06', '', '', NULL, 'INV-2025-031', NULL, NULL, NULL, NULL, '2025-12-06 14:58:06', '2025-12-06 14:58:06'),
(35, 2, 58, 3, NULL, NULL, 0.00, 10000.00, 0.00, 0.00, 'pending', 'new_request', '2025-12-06 15:18:53', '', '', NULL, 'INV-2025-032', NULL, NULL, NULL, NULL, '2025-12-06 15:18:53', '2025-12-06 15:18:53'),
(36, 2, 59, 3, NULL, NULL, 0.00, 10000.00, 0.00, 0.00, 'pending', 'new_request', '2025-12-06 15:24:46', '', '', NULL, 'INV-2025-033', NULL, NULL, NULL, NULL, '2025-12-06 15:24:46', '2025-12-06 15:24:46'),
(37, 2, 60, 3, NULL, NULL, 0.00, 10000.00, 0.00, 0.00, 'pending', 'new_request', '2025-12-06 15:39:05', '', '', NULL, 'INV-2025-034', NULL, NULL, NULL, NULL, '2025-12-06 15:39:05', '2025-12-06 15:39:05'),
(38, 2, 61, 3, NULL, NULL, 0.00, 10000.00, 0.00, 0.00, 'pending', 'new_request', '2025-12-06 15:43:29', '', '', NULL, 'INV-2025-035', NULL, NULL, NULL, NULL, '2025-12-06 15:43:29', '2025-12-06 15:43:29'),
(39, 2, 62, 3, NULL, NULL, 0.00, 10000.00, 0.00, 0.00, 'pending', 'new_request', '2025-12-06 15:59:25', '', '', NULL, 'INV-2025-036', NULL, NULL, NULL, NULL, '2025-12-06 15:59:25', '2025-12-06 15:59:25'),
(40, 2, 63, 3, NULL, NULL, 0.00, 10000.00, 0.00, 0.00, 'pending', 'new_request', '2025-12-06 16:02:34', '', '', NULL, 'INV-2025-037', NULL, NULL, NULL, NULL, '2025-12-06 16:02:34', '2025-12-06 16:02:34'),
(41, 2, 64, 3, NULL, NULL, 0.00, 10000.00, 0.00, 0.00, 'processing', 'new_request', '2025-12-06 17:04:40', '', '', NULL, 'INV-2025-038', NULL, NULL, NULL, NULL, '2025-12-06 17:04:40', '2025-12-06 17:21:26'),
(42, 2, 65, 3, NULL, NULL, 0.00, 10000.00, 0.00, 0.00, 'pending', 'new_request', '2025-12-06 17:30:23', '', '', NULL, 'INV-2025-039', NULL, NULL, NULL, NULL, '2025-12-06 17:30:23', '2025-12-06 17:30:23'),
(43, 2, 66, 3, NULL, NULL, 0.00, 10000.00, 0.00, 0.00, 'processing', 'new_request', '2025-12-06 17:41:34', '', '', NULL, 'INV-2025-040', NULL, NULL, NULL, NULL, '2025-12-06 17:41:34', '2025-12-06 17:46:28'),
(44, 3, NULL, 3, NULL, NULL, 0.00, 10000.00, 0.00, 0.00, 'pending', 'new_request', '2025-12-06 18:06:32', '', '', NULL, 'INV-2025-041', NULL, NULL, NULL, NULL, '2025-12-06 18:06:32', '2025-12-30 11:53:57'),
(45, 3, NULL, 3, NULL, NULL, 0.00, 20000.00, 0.00, 0.00, 'shipping', 'new_request', '2025-12-30 11:53:04', '', '', NULL, 'INV-2025-042', NULL, NULL, NULL, NULL, '2025-12-30 11:53:04', '2025-12-30 11:54:43'),
(46, 3, NULL, 3, NULL, NULL, 0.00, 20000.00, 0.00, 0.00, 'pending', 'new_request', '2026-01-25 15:56:44', '', '', NULL, 'INV-2026-001', NULL, NULL, NULL, NULL, '2026-01-25 15:56:44', '2026-01-25 15:56:44'),
(47, 2, 70, 3, NULL, NULL, 0.00, 10000.00, 0.00, 0.00, 'pending', 'new_request', '2026-01-25 16:03:46', '', '', NULL, 'INV-2026-002', NULL, NULL, NULL, NULL, '2026-01-25 16:03:46', '2026-01-25 16:03:46'),
(48, 2, 71, 3, NULL, NULL, 0.00, 20000.00, 0.00, 0.00, 'pending', 'new_request', '2026-01-25 16:44:55', '', '', NULL, 'INV-2026-003', NULL, NULL, NULL, NULL, '2026-01-25 16:44:55', '2026-01-25 16:44:55'),
(49, 2, 72, 3, NULL, NULL, 0.00, 10000.00, 0.00, 0.00, 'pending', 'new_request', '2026-01-25 16:53:06', '', '', NULL, 'INV-2026-004', NULL, NULL, NULL, NULL, '2026-01-25 16:53:06', '2026-01-25 16:53:06'),
(50, 2, 73, 3, NULL, NULL, 0.00, 10000.00, 0.00, 0.00, 'processing', 'new_request', '2026-01-25 17:02:38', '', '', NULL, 'INV-2026-005', NULL, NULL, NULL, NULL, '2026-01-25 17:02:38', '2026-01-25 18:40:53'),
(51, 2, 74, 3, NULL, NULL, 0.00, 10000.00, 0.00, 0.00, 'processing', 'new_request', '2026-01-25 18:22:33', '', '', NULL, 'INV-2026-006', NULL, NULL, NULL, NULL, '2026-01-25 18:22:33', '2026-01-25 18:40:56'),
(52, 2, 75, 3, NULL, NULL, 0.00, 10000.00, 0.00, 0.00, 'pending', 'new_request', '2026-01-25 18:45:53', '', '', NULL, 'INV-2026-007', NULL, NULL, NULL, NULL, '2026-01-25 18:45:53', '2026-01-25 18:45:53'),
(53, 3, NULL, 3, NULL, NULL, 0.00, 20000.00, 0.00, 0.00, 'pending', 'new_request', '2026-01-26 22:36:44', '', '', NULL, 'INV-2026-008', NULL, NULL, NULL, NULL, '2026-01-26 22:36:44', '2026-01-26 22:36:44'),
(54, 3, 23, 3, NULL, NULL, 0.00, 10000.00, 0.00, 0.00, 'pending', 'new_request', '2026-03-12 14:17:58', '', '', NULL, 'INV-2026-009', NULL, NULL, NULL, NULL, '2026-03-12 14:17:58', '2026-03-12 14:17:58'),
(55, 3, 23, 3, NULL, NULL, 0.00, 10000.00, 0.00, 0.00, 'pending', 'new_request', '2026-03-12 14:20:34', '', '', NULL, 'INV-2026-010', NULL, NULL, NULL, NULL, '2026-03-12 14:20:34', '2026-03-12 14:20:34'),
(56, 3, 23, 3, NULL, NULL, 0.00, 10000.00, 0.00, 0.00, 'pending', 'new_request', '2026-03-12 14:25:57', '', '', NULL, 'INV-2026-011', NULL, NULL, NULL, NULL, '2026-03-12 14:25:57', '2026-03-12 14:25:57'),
(57, 3, 23, 3, NULL, NULL, 0.00, 10000.00, 0.00, 0.00, 'pending', 'new_request', '2026-03-12 14:29:47', '', '', NULL, 'INV-2026-012', NULL, NULL, NULL, NULL, '2026-03-12 14:29:47', '2026-03-12 14:29:47'),
(58, 3, 23, 3, NULL, NULL, 0.00, 10000.00, 0.00, 0.00, 'pending', 'new_request', '2026-03-12 14:33:27', '', '', NULL, 'INV-2026-013', NULL, NULL, NULL, NULL, '2026-03-12 14:33:27', '2026-03-12 14:33:27'),
(59, 3, 23, 3, NULL, NULL, 0.00, 10000.00, 0.00, 0.00, 'pending', 'new_request', '2026-03-12 14:36:13', '', '', NULL, 'INV-2026-014', NULL, NULL, NULL, NULL, '2026-03-12 14:36:13', '2026-03-12 14:36:13'),
(60, 3, 23, 3, NULL, NULL, 0.00, 10000.00, 0.00, 0.00, 'pending', 'new_request', '2026-03-12 15:02:43', '', '', NULL, 'INV-2026-015', NULL, NULL, NULL, NULL, '2026-03-12 15:02:43', '2026-03-12 15:02:43'),
(61, 3, 23, 3, NULL, NULL, 0.00, 10000.00, 0.00, 0.00, 'pending', 'new_request', '2026-03-12 15:14:43', '', '', NULL, 'INV-2026-016', NULL, NULL, NULL, NULL, '2026-03-12 15:14:43', '2026-03-12 15:14:43'),
(62, 3, 23, 3, NULL, NULL, 0.00, 10000.00, 0.00, 0.00, 'pending', 'new_request', '2026-03-12 15:26:07', '', '', NULL, 'INV-2026-017', NULL, NULL, NULL, NULL, '2026-03-12 15:26:07', '2026-03-12 15:26:07'),
(63, 3, 23, 3, NULL, NULL, 0.00, 10000.00, 0.00, 0.00, 'pending', 'new_request', '2026-03-12 15:38:02', '', '', NULL, 'INV-2026-018', NULL, NULL, NULL, NULL, '2026-03-12 15:38:02', '2026-03-12 15:38:02'),
(64, 3, 23, 3, NULL, NULL, 0.00, 10000.00, 0.00, 0.00, 'pending', 'new_request', '2026-03-12 15:38:56', '', '', NULL, 'INV-2026-019', NULL, NULL, NULL, NULL, '2026-03-12 15:38:56', '2026-03-12 15:38:56'),
(65, 3, 23, 3, NULL, NULL, 0.00, 10000.00, 0.00, 0.00, 'pending', 'new_request', '2026-03-12 16:02:05', '', '', NULL, 'INV-2026-020', NULL, NULL, NULL, NULL, '2026-03-12 16:02:05', '2026-03-12 16:02:05'),
(66, 3, 23, 3, NULL, NULL, 0.00, 10000.00, 0.00, 0.00, 'pending', 'new_request', '2026-03-12 16:10:35', '', '', NULL, 'INV-2026-021', NULL, NULL, NULL, NULL, '2026-03-12 16:10:35', '2026-03-12 16:10:35'),
(67, 3, 23, 3, NULL, NULL, 0.00, 10000.00, 0.00, 0.00, 'pending', 'new_request', '2026-03-12 17:06:09', '', '', NULL, 'INV-2026-022', NULL, NULL, NULL, NULL, '2026-03-12 17:06:09', '2026-03-12 17:06:09'),
(68, 3, 23, 3, NULL, NULL, 0.00, 10000.00, 0.00, 0.00, 'pending', 'new_request', '2026-03-12 17:07:24', '', '', NULL, 'INV-2026-023', NULL, NULL, NULL, NULL, '2026-03-12 17:07:24', '2026-03-12 17:07:24'),
(69, 3, 23, 3, NULL, NULL, 0.00, 10000.00, 0.00, 0.00, 'processing', 'new_request', '2026-03-13 15:17:43', '', '', NULL, 'INV-2026-024', NULL, NULL, NULL, NULL, '2026-03-13 14:30:32', '2026-03-13 15:17:43'),
(70, 3, 23, 3, NULL, NULL, 0.00, 10000.00, 0.00, 0.00, 'pending', 'new_request', '2026-03-13 14:52:52', '', '', NULL, 'INV-2026-025', NULL, NULL, NULL, NULL, '2026-03-13 14:52:52', '2026-03-13 14:52:52'),
(71, 3, 23, 3, NULL, NULL, 0.00, 10000.00, 0.00, 0.00, 'shipping', 'new_request', '2026-03-13 15:04:52', '', '', NULL, 'INV-2026-026', NULL, NULL, NULL, NULL, '2026-03-13 15:04:52', '2026-03-13 15:18:43');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `order_delivery_events`
--

CREATE TABLE `order_delivery_events` (
  `event_id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `shipper_id` int(11) DEFAULT NULL,
  `status_from` enum('new_request','accepted','picked_up','delivering','arrived','delivered','completed','rejected') DEFAULT NULL,
  `status_to` enum('new_request','accepted','picked_up','delivering','arrived','delivered','completed','rejected') NOT NULL,
  `note` text DEFAULT NULL,
  `photo_url` varchar(500) DEFAULT NULL,
  `latitude` decimal(10,7) DEFAULT NULL,
  `longitude` decimal(10,7) DEFAULT NULL,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Đang đổ dữ liệu cho bảng `order_delivery_events`
--

INSERT INTO `order_delivery_events` (`event_id`, `order_id`, `shipper_id`, `status_from`, `status_to`, `note`, `photo_url`, `latitude`, `longitude`, `metadata`, `created_at`) VALUES
(1, 3, 4, 'new_request', 'accepted', NULL, NULL, NULL, NULL, '[]', '2025-11-16 20:39:43'),
(2, 3, 4, 'accepted', 'picked_up', NULL, 'file:///data/user/0/host.exp.exponent/cache/ExperienceData/%2540anonymous%252Fmobile-app-delivery-new-29b78b0f-aa89-4206-be1a-15179c8a4e8c/Camera/0c76ca9a-eae1-4bfb-9b4f-d72a08edb916.jpg', 20.9828606, 105.6960602, '[]', '2025-11-16 21:06:36'),
(3, 3, 4, 'picked_up', 'delivering', NULL, NULL, NULL, NULL, '[]', '2025-11-16 21:10:01'),
(4, 3, 4, 'delivering', 'arrived', NULL, NULL, 20.9828611, 105.6960604, '[]', '2025-11-16 21:10:15'),
(5, 3, 4, 'arrived', 'delivered', NULL, 'file:///data/user/0/host.exp.exponent/cache/ExperienceData/%2540anonymous%252Fmobile-app-delivery-new-29b78b0f-aa89-4206-be1a-15179c8a4e8c/Camera/5eecafd9-89f9-49b3-9ec9-eaf8564aae76.jpg', 20.9828606, 105.6960602, '[]', '2025-11-16 21:10:57'),
(6, 3, 4, 'delivered', 'completed', NULL, NULL, NULL, NULL, '[]', '2025-11-16 21:18:53'),
(7, 1, 4, 'new_request', 'new_request', 'Order assigned to shipper', NULL, NULL, NULL, NULL, '2025-11-16 21:29:24'),
(8, 1, 4, 'new_request', 'accepted', NULL, NULL, NULL, NULL, '[]', '2025-11-16 21:31:21'),
(9, 1, 4, 'accepted', 'picked_up', NULL, 'file:///data/user/0/host.exp.exponent/cache/ExperienceData/%2540anonymous%252Fmobile-app-delivery-new-29b78b0f-aa89-4206-be1a-15179c8a4e8c/Camera/f4653dcc-7e7b-44e2-9360-c694f868009f.jpg', 20.9828615, 105.6960604, '[]', '2025-11-16 21:32:19'),
(10, 1, 4, 'picked_up', 'delivering', NULL, NULL, NULL, NULL, '[]', '2025-11-16 21:34:21'),
(11, 1, 4, 'delivering', 'arrived', NULL, NULL, 20.9828609, 105.6960604, '[]', '2025-11-16 21:34:52'),
(12, 1, 4, 'arrived', 'delivered', NULL, 'file:///data/user/0/host.exp.exponent/cache/ExperienceData/%2540anonymous%252Fmobile-app-delivery-new-29b78b0f-aa89-4206-be1a-15179c8a4e8c/Camera/c31b7702-15c5-4de7-8aaa-bb2021d5c433.jpg', 20.9828612, 105.6960606, '[]', '2025-11-16 21:35:52'),
(13, 1, 4, 'delivered', 'completed', NULL, NULL, NULL, NULL, '[]', '2025-11-16 21:35:52'),
(15, 8, NULL, NULL, 'new_request', 'Shipping workflow initialized', NULL, NULL, NULL, NULL, '2025-11-23 23:08:18'),
(16, 9, NULL, NULL, 'new_request', 'Shipping workflow initialized', NULL, NULL, NULL, NULL, '2025-11-23 23:14:15'),
(17, 10, NULL, NULL, 'new_request', 'Shipping workflow initialized', NULL, NULL, NULL, NULL, '2025-11-23 23:14:22'),
(18, 11, NULL, NULL, 'new_request', 'Shipping workflow initialized', NULL, NULL, NULL, NULL, '2025-11-23 23:39:31'),
(19, 12, NULL, NULL, 'new_request', 'Shipping workflow initialized', NULL, NULL, NULL, NULL, '2025-11-23 23:40:00'),
(20, 13, NULL, NULL, 'new_request', 'Shipping workflow initialized', NULL, NULL, NULL, NULL, '2025-11-23 23:41:46'),
(21, 14, NULL, NULL, 'new_request', 'Shipping workflow initialized', NULL, NULL, NULL, NULL, '2025-11-23 23:42:22'),
(22, 15, NULL, NULL, 'new_request', 'Shipping workflow initialized', NULL, NULL, NULL, NULL, '2025-11-23 23:45:13'),
(23, 16, NULL, NULL, 'new_request', 'Shipping workflow initialized', NULL, NULL, NULL, NULL, '2025-11-23 23:48:13'),
(24, 17, NULL, NULL, 'new_request', 'Shipping workflow initialized', NULL, NULL, NULL, NULL, '2025-11-23 23:48:44'),
(25, 18, NULL, NULL, 'new_request', 'Shipping workflow initialized', NULL, NULL, NULL, NULL, '2025-11-23 23:49:15'),
(26, 19, NULL, NULL, 'new_request', 'Shipping workflow initialized', NULL, NULL, NULL, NULL, '2025-11-23 23:51:46'),
(27, 20, NULL, NULL, 'new_request', 'Shipping workflow initialized', NULL, NULL, NULL, NULL, '2025-11-23 23:52:11'),
(28, 21, NULL, NULL, 'new_request', 'Shipping workflow initialized', NULL, NULL, NULL, NULL, '2025-11-23 23:58:25'),
(29, 22, NULL, NULL, 'new_request', 'Shipping workflow initialized', NULL, NULL, NULL, NULL, '2025-11-24 00:00:08'),
(30, 23, NULL, NULL, 'new_request', 'Shipping workflow initialized', NULL, NULL, NULL, NULL, '2025-11-24 00:34:08'),
(31, 24, NULL, NULL, 'new_request', 'Shipping workflow initialized', NULL, NULL, NULL, NULL, '2025-11-24 00:41:12'),
(32, 25, NULL, NULL, 'new_request', 'Shipping workflow initialized', NULL, NULL, NULL, NULL, '2025-11-25 11:40:40'),
(33, 26, NULL, NULL, 'new_request', 'Shipping workflow initialized', NULL, NULL, NULL, NULL, '2025-11-25 21:30:57'),
(34, 28, NULL, NULL, 'new_request', 'Shipping workflow initialized', NULL, NULL, NULL, NULL, '2025-12-06 12:45:42'),
(35, 29, NULL, NULL, 'new_request', 'Shipping workflow initialized', NULL, NULL, NULL, NULL, '2025-12-06 13:02:01'),
(36, 30, NULL, NULL, 'new_request', 'Shipping workflow initialized', NULL, NULL, NULL, NULL, '2025-12-06 13:23:27'),
(37, 31, NULL, NULL, 'new_request', 'Shipping workflow initialized', NULL, NULL, NULL, NULL, '2025-12-06 14:21:58'),
(38, 32, NULL, NULL, 'new_request', 'Shipping workflow initialized', NULL, NULL, NULL, NULL, '2025-12-06 14:32:44'),
(39, 33, NULL, NULL, 'new_request', 'Shipping workflow initialized', NULL, NULL, NULL, NULL, '2025-12-06 14:56:18'),
(40, 34, NULL, NULL, 'new_request', 'Shipping workflow initialized', NULL, NULL, NULL, NULL, '2025-12-06 14:58:06'),
(41, 35, NULL, NULL, 'new_request', 'Shipping workflow initialized', NULL, NULL, NULL, NULL, '2025-12-06 15:18:53'),
(42, 36, NULL, NULL, 'new_request', 'Shipping workflow initialized', NULL, NULL, NULL, NULL, '2025-12-06 15:24:46'),
(43, 37, NULL, NULL, 'new_request', 'Shipping workflow initialized', NULL, NULL, NULL, NULL, '2025-12-06 15:39:05'),
(44, 38, NULL, NULL, 'new_request', 'Shipping workflow initialized', NULL, NULL, NULL, NULL, '2025-12-06 15:43:29'),
(45, 39, NULL, NULL, 'new_request', 'Shipping workflow initialized', NULL, NULL, NULL, NULL, '2025-12-06 15:59:25'),
(46, 40, NULL, NULL, 'new_request', 'Shipping workflow initialized', NULL, NULL, NULL, NULL, '2025-12-06 16:02:34'),
(47, 41, NULL, NULL, 'new_request', 'Shipping workflow initialized', NULL, NULL, NULL, NULL, '2025-12-06 17:04:40'),
(48, 42, NULL, NULL, 'new_request', 'Shipping workflow initialized', NULL, NULL, NULL, NULL, '2025-12-06 17:30:23'),
(49, 43, NULL, NULL, 'new_request', 'Shipping workflow initialized', NULL, NULL, NULL, NULL, '2025-12-06 17:41:34'),
(50, 44, NULL, NULL, 'new_request', 'Shipping workflow initialized', NULL, NULL, NULL, NULL, '2025-12-06 18:06:32'),
(51, 45, NULL, NULL, 'new_request', 'Shipping workflow initialized', NULL, NULL, NULL, NULL, '2025-12-30 11:53:04'),
(52, 2, 4, 'new_request', 'accepted', NULL, NULL, NULL, NULL, '[]', '2025-12-30 11:57:25'),
(53, 2, 4, 'accepted', 'picked_up', NULL, 'file:///data/user/0/host.exp.exponent/cache/ExperienceData/%2540anonymous%252Fmobile-app-delivery-new-29b78b0f-aa89-4206-be1a-15179c8a4e8c/Camera/aa8f869c-0fdf-46b7-8807-45d8d375ccda.jpg', 20.9828606, 105.6960602, '[]', '2025-12-30 11:57:44'),
(54, 46, NULL, NULL, 'new_request', 'Shipping workflow initialized', NULL, NULL, NULL, NULL, '2026-01-25 15:56:44'),
(55, 47, NULL, NULL, 'new_request', 'Shipping workflow initialized', NULL, NULL, NULL, NULL, '2026-01-25 16:03:46'),
(56, 48, NULL, NULL, 'new_request', 'Shipping workflow initialized', NULL, NULL, NULL, NULL, '2026-01-25 16:44:55'),
(57, 49, NULL, NULL, 'new_request', 'Shipping workflow initialized', NULL, NULL, NULL, NULL, '2026-01-25 16:53:06'),
(58, 50, NULL, NULL, 'new_request', 'Shipping workflow initialized', NULL, NULL, NULL, NULL, '2026-01-25 17:02:38'),
(59, 51, NULL, NULL, 'new_request', 'Shipping workflow initialized', NULL, NULL, NULL, NULL, '2026-01-25 18:22:33'),
(60, 52, NULL, NULL, 'new_request', 'Shipping workflow initialized', NULL, NULL, NULL, NULL, '2026-01-25 18:45:53'),
(61, 53, NULL, NULL, 'new_request', 'Shipping workflow initialized', NULL, NULL, NULL, NULL, '2026-01-26 22:36:44'),
(62, 54, NULL, NULL, 'new_request', 'Shipping workflow initialized', NULL, NULL, NULL, NULL, '2026-03-12 14:17:58'),
(63, 55, NULL, NULL, 'new_request', 'Shipping workflow initialized', NULL, NULL, NULL, NULL, '2026-03-12 14:20:34'),
(64, 56, NULL, NULL, 'new_request', 'Shipping workflow initialized', NULL, NULL, NULL, NULL, '2026-03-12 14:25:57'),
(65, 57, NULL, NULL, 'new_request', 'Shipping workflow initialized', NULL, NULL, NULL, NULL, '2026-03-12 14:29:47'),
(66, 58, NULL, NULL, 'new_request', 'Shipping workflow initialized', NULL, NULL, NULL, NULL, '2026-03-12 14:33:27'),
(67, 59, NULL, NULL, 'new_request', 'Shipping workflow initialized', NULL, NULL, NULL, NULL, '2026-03-12 14:36:13'),
(68, 60, NULL, NULL, 'new_request', 'Shipping workflow initialized', NULL, NULL, NULL, NULL, '2026-03-12 15:02:43'),
(69, 61, NULL, NULL, 'new_request', 'Shipping workflow initialized', NULL, NULL, NULL, NULL, '2026-03-12 15:14:43'),
(70, 62, NULL, NULL, 'new_request', 'Shipping workflow initialized', NULL, NULL, NULL, NULL, '2026-03-12 15:26:07'),
(71, 63, NULL, NULL, 'new_request', 'Shipping workflow initialized', NULL, NULL, NULL, NULL, '2026-03-12 15:38:02'),
(72, 64, NULL, NULL, 'new_request', 'Shipping workflow initialized', NULL, NULL, NULL, NULL, '2026-03-12 15:38:56'),
(73, 65, NULL, NULL, 'new_request', 'Shipping workflow initialized', NULL, NULL, NULL, NULL, '2026-03-12 16:02:05'),
(74, 66, NULL, NULL, 'new_request', 'Shipping workflow initialized', NULL, NULL, NULL, NULL, '2026-03-12 16:10:35'),
(75, 67, NULL, NULL, 'new_request', 'Shipping workflow initialized', NULL, NULL, NULL, NULL, '2026-03-12 17:06:09'),
(76, 68, NULL, NULL, 'new_request', 'Shipping workflow initialized', NULL, NULL, NULL, NULL, '2026-03-12 17:07:24'),
(77, 69, NULL, NULL, 'new_request', 'Shipping workflow initialized', NULL, NULL, NULL, NULL, '2026-03-13 14:30:32'),
(78, 70, NULL, NULL, 'new_request', 'Shipping workflow initialized', NULL, NULL, NULL, NULL, '2026-03-13 14:52:52'),
(79, 71, NULL, NULL, 'new_request', 'Shipping workflow initialized', NULL, NULL, NULL, NULL, '2026-03-13 15:04:52'),
(80, 69, 4, 'new_request', 'new_request', 'Order assigned to shipper', NULL, NULL, NULL, NULL, '2026-03-13 15:17:43');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `order_delivery_proofs`
--

CREATE TABLE `order_delivery_proofs` (
  `proof_id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `shipper_id` int(11) DEFAULT NULL,
  `status` enum('picked_up','delivering','arrived','delivered') NOT NULL,
  `photo_url` varchar(500) NOT NULL,
  `proof_type` enum('pickup_photo','delivery_photo','signature','other') DEFAULT 'delivery_photo',
  `latitude` decimal(10,7) DEFAULT NULL,
  `longitude` decimal(10,7) DEFAULT NULL,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `captured_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Đang đổ dữ liệu cho bảng `order_delivery_proofs`
--

INSERT INTO `order_delivery_proofs` (`proof_id`, `order_id`, `shipper_id`, `status`, `photo_url`, `proof_type`, `latitude`, `longitude`, `metadata`, `captured_at`) VALUES
(1, 3, 4, 'picked_up', 'file:///data/user/0/host.exp.exponent/cache/ExperienceData/%2540anonymous%252Fmobile-app-delivery-new-29b78b0f-aa89-4206-be1a-15179c8a4e8c/Camera/0c76ca9a-eae1-4bfb-9b4f-d72a08edb916.jpg', 'pickup_photo', 20.9828606, 105.6960602, NULL, '2025-11-16 21:06:36'),
(2, 3, 4, 'delivered', 'file:///data/user/0/host.exp.exponent/cache/ExperienceData/%2540anonymous%252Fmobile-app-delivery-new-29b78b0f-aa89-4206-be1a-15179c8a4e8c/Camera/5eecafd9-89f9-49b3-9ec9-eaf8564aae76.jpg', 'delivery_photo', 20.9828606, 105.6960602, NULL, '2025-11-16 21:10:57'),
(3, 1, 4, 'picked_up', 'file:///data/user/0/host.exp.exponent/cache/ExperienceData/%2540anonymous%252Fmobile-app-delivery-new-29b78b0f-aa89-4206-be1a-15179c8a4e8c/Camera/f4653dcc-7e7b-44e2-9360-c694f868009f.jpg', 'pickup_photo', 20.9828615, 105.6960604, NULL, '2025-11-16 21:32:19'),
(4, 1, 4, 'delivered', 'file:///data/user/0/host.exp.exponent/cache/ExperienceData/%2540anonymous%252Fmobile-app-delivery-new-29b78b0f-aa89-4206-be1a-15179c8a4e8c/Camera/c31b7702-15c5-4de7-8aaa-bb2021d5c433.jpg', 'delivery_photo', 20.9828612, 105.6960606, NULL, '2025-11-16 21:35:52'),
(5, 2, 4, 'picked_up', 'file:///data/user/0/host.exp.exponent/cache/ExperienceData/%2540anonymous%252Fmobile-app-delivery-new-29b78b0f-aa89-4206-be1a-15179c8a4e8c/Camera/aa8f869c-0fdf-46b7-8807-45d8d375ccda.jpg', 'pickup_photo', 20.9828606, 105.6960602, NULL, '2025-12-30 11:57:44');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `order_items`
--

CREATE TABLE `order_items` (
  `item_id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `variant_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL CHECK (`quantity` > 0),
  `unit_price` decimal(10,2) NOT NULL CHECK (`unit_price` >= 0),
  `product_name_snapshot` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Đang đổ dữ liệu cho bảng `order_items`
--

INSERT INTO `order_items` (`item_id`, `order_id`, `variant_id`, `quantity`, `unit_price`, `product_name_snapshot`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 2, 425000.00, 'BEIGE WOOL BLEND CARGO PANTS', '2025-08-19 23:08:53', '2025-08-19 23:08:53'),
(2, 1, 17, 1, 590000.00, 'BLACK KHAKI POLOSHIRT', '2025-08-19 23:08:53', '2025-08-19 23:08:53'),
(3, 2, 5, 1, 750000.00, 'BLACK DIAGONAL PLEAT WOOL-VISCOSE TROUSERS', '2025-08-19 23:08:53', '2025-08-19 23:08:53'),
(4, 2, 25, 1, 890000.00, 'BLACK KHAKI WOOL BOMBER JACKET', '2025-08-19 23:08:53', '2025-08-19 23:08:53'),
(5, 3, 8, 1, 650000.00, 'BLACK SIDE PLEAT WIDE LEG JEANS', '2025-08-19 23:08:53', '2025-08-19 23:08:53'),
(6, 4, 33, 1, 590000.00, 'BROWN KHAKI POLOSHIRT', '2025-08-19 23:08:53', '2025-08-19 23:08:53'),
(7, 5, 1, 2, 425000.00, 'BEIGE WOOL BLEND CARGO PANTS', '2025-08-19 23:08:53', '2025-08-19 23:08:53'),
(8, 8, 1, 4, 850000.00, 'BEIGE WOOL BLEND CARGO PANTS', '2025-11-23 23:08:18', '2025-11-23 23:08:18'),
(9, 9, 1, 4, 850000.00, 'BEIGE WOOL BLEND CARGO PANTS', '2025-11-23 23:14:15', '2025-11-23 23:14:15'),
(10, 10, 1, 4, 850000.00, 'BEIGE WOOL BLEND CARGO PANTS', '2025-11-23 23:14:22', '2025-11-23 23:14:22'),
(11, 11, 5, 1, 750000.00, 'BLACK DIAGONAL PLEAT WOOL-VISCOSE TROUSERS', '2025-11-23 23:39:31', '2025-11-23 23:39:31'),
(12, 12, 5, 1, 750000.00, 'BLACK DIAGONAL PLEAT WOOL-VISCOSE TROUSERS', '2025-11-23 23:40:00', '2025-11-23 23:40:00'),
(13, 13, 5, 1, 750000.00, 'BLACK DIAGONAL PLEAT WOOL-VISCOSE TROUSERS', '2025-11-23 23:41:46', '2025-11-23 23:41:46'),
(14, 14, 5, 1, 750000.00, 'BLACK DIAGONAL PLEAT WOOL-VISCOSE TROUSERS', '2025-11-23 23:42:22', '2025-11-23 23:42:22'),
(15, 15, 5, 1, 750000.00, 'BLACK DIAGONAL PLEAT WOOL-VISCOSE TROUSERS', '2025-11-23 23:45:13', '2025-11-23 23:45:13'),
(16, 16, 5, 1, 750000.00, 'BLACK DIAGONAL PLEAT WOOL-VISCOSE TROUSERS', '2025-11-23 23:48:13', '2025-11-23 23:48:13'),
(17, 17, 5, 1, 750000.00, 'BLACK DIAGONAL PLEAT WOOL-VISCOSE TROUSERS', '2025-11-23 23:48:44', '2025-11-23 23:48:44'),
(18, 18, 5, 1, 750000.00, 'BLACK DIAGONAL PLEAT WOOL-VISCOSE TROUSERS', '2025-11-23 23:49:15', '2025-11-23 23:49:15'),
(19, 19, 5, 1, 750000.00, 'BLACK DIAGONAL PLEAT WOOL-VISCOSE TROUSERS', '2025-11-23 23:51:46', '2025-11-23 23:51:46'),
(20, 20, 5, 1, 750000.00, 'BLACK DIAGONAL PLEAT WOOL-VISCOSE TROUSERS', '2025-11-23 23:52:11', '2025-11-23 23:52:11'),
(21, 21, 5, 1, 750000.00, 'BLACK DIAGONAL PLEAT WOOL-VISCOSE TROUSERS', '2025-11-23 23:58:25', '2025-11-23 23:58:25'),
(22, 22, 5, 1, 750000.00, 'BLACK DIAGONAL PLEAT WOOL-VISCOSE TROUSERS', '2025-11-24 00:00:08', '2025-11-24 00:00:08'),
(23, 23, 5, 1, 750000.00, 'BLACK DIAGONAL PLEAT WOOL-VISCOSE TROUSERS', '2025-11-24 00:34:08', '2025-11-24 00:34:08'),
(24, 24, 5, 1, 750000.00, 'BLACK DIAGONAL PLEAT WOOL-VISCOSE TROUSERS', '2025-11-24 00:41:12', '2025-11-24 00:41:12'),
(25, 25, 5, 1, 750000.00, 'BLACK DIAGONAL PLEAT WOOL-VISCOSE TROUSERS', '2025-11-25 11:40:40', '2025-11-25 11:40:40'),
(26, 26, 1, 3, 850000.00, 'BEIGE WOOL BLEND CARGO PANTS', '2025-11-25 21:30:57', '2025-11-25 21:30:57'),
(27, 28, 1552, 1, 10000.00, 'sản phẩm test', '2025-12-06 12:45:42', '2025-12-06 12:45:42'),
(28, 29, 1552, 1, 10000.00, 'sản phẩm test', '2025-12-06 13:02:01', '2025-12-06 13:02:01'),
(29, 30, 1552, 1, 10000.00, 'sản phẩm test', '2025-12-06 13:23:27', '2025-12-06 13:23:27'),
(30, 31, 1552, 1, 10000.00, 'sản phẩm test', '2025-12-06 14:21:58', '2025-12-06 14:21:58'),
(31, 32, 1552, 1, 10000.00, 'sản phẩm test', '2025-12-06 14:32:44', '2025-12-06 14:32:44'),
(32, 33, 1552, 1, 10000.00, 'sản phẩm test', '2025-12-06 14:56:18', '2025-12-06 14:56:18'),
(33, 34, 1552, 1, 10000.00, 'sản phẩm test', '2025-12-06 14:58:06', '2025-12-06 14:58:06'),
(34, 35, 1552, 1, 10000.00, 'sản phẩm test', '2025-12-06 15:18:53', '2025-12-06 15:18:53'),
(35, 36, 1552, 1, 10000.00, 'sản phẩm test', '2025-12-06 15:24:46', '2025-12-06 15:24:46'),
(36, 37, 1552, 1, 10000.00, 'sản phẩm test', '2025-12-06 15:39:05', '2025-12-06 15:39:05'),
(37, 38, 1552, 1, 10000.00, 'sản phẩm test', '2025-12-06 15:43:29', '2025-12-06 15:43:29'),
(38, 39, 1552, 1, 10000.00, 'sản phẩm test', '2025-12-06 15:59:25', '2025-12-06 15:59:25'),
(39, 40, 1552, 1, 10000.00, 'sản phẩm test', '2025-12-06 16:02:34', '2025-12-06 16:02:34'),
(40, 41, 1552, 1, 10000.00, 'sản phẩm test', '2025-12-06 17:04:40', '2025-12-06 17:04:40'),
(41, 42, 1552, 1, 10000.00, 'sản phẩm test', '2025-12-06 17:30:23', '2025-12-06 17:30:23'),
(42, 43, 1552, 1, 10000.00, 'sản phẩm test', '2025-12-06 17:41:34', '2025-12-06 17:41:34'),
(43, 44, 1552, 1, 10000.00, 'sản phẩm test', '2025-12-06 18:06:32', '2025-12-06 18:06:32'),
(44, 45, 1552, 2, 10000.00, 'sản phẩm test', '2025-12-30 11:53:04', '2025-12-30 11:53:04'),
(45, 46, 1552, 2, 10000.00, 'sản phẩm test', '2026-01-25 15:56:44', '2026-01-25 15:56:44'),
(46, 47, 1552, 1, 10000.00, 'sản phẩm test', '2026-01-25 16:03:46', '2026-01-25 16:03:46'),
(47, 48, 1552, 2, 10000.00, 'sản phẩm test', '2026-01-25 16:44:55', '2026-01-25 16:44:55'),
(48, 49, 1552, 1, 10000.00, 'sản phẩm test', '2026-01-25 16:53:06', '2026-01-25 16:53:06'),
(49, 50, 1552, 1, 10000.00, 'sản phẩm test', '2026-01-25 17:02:38', '2026-01-25 17:02:38'),
(50, 51, 1552, 1, 10000.00, 'sản phẩm test', '2026-01-25 18:22:33', '2026-01-25 18:22:33'),
(51, 52, 1552, 1, 10000.00, 'sản phẩm test', '2026-01-25 18:45:53', '2026-01-25 18:45:53'),
(52, 53, 1552, 2, 10000.00, 'sản phẩm test', '2026-01-26 22:36:44', '2026-01-26 22:36:44'),
(53, 54, 1552, 1, 10000.00, 'sản phẩm test', '2026-03-12 14:17:58', '2026-03-12 14:17:58'),
(54, 55, 1552, 1, 10000.00, 'sản phẩm test', '2026-03-12 14:20:34', '2026-03-12 14:20:34'),
(55, 56, 1552, 1, 10000.00, 'sản phẩm test', '2026-03-12 14:25:57', '2026-03-12 14:25:57'),
(56, 57, 1552, 1, 10000.00, 'sản phẩm test', '2026-03-12 14:29:47', '2026-03-12 14:29:47'),
(57, 58, 1552, 1, 10000.00, 'sản phẩm test', '2026-03-12 14:33:27', '2026-03-12 14:33:27'),
(58, 59, 1552, 1, 10000.00, 'sản phẩm test', '2026-03-12 14:36:13', '2026-03-12 14:36:13'),
(59, 60, 1552, 1, 10000.00, 'sản phẩm test', '2026-03-12 15:02:43', '2026-03-12 15:02:43'),
(60, 61, 1552, 1, 10000.00, 'sản phẩm test', '2026-03-12 15:14:43', '2026-03-12 15:14:43'),
(61, 62, 1552, 1, 10000.00, 'sản phẩm test', '2026-03-12 15:26:07', '2026-03-12 15:26:07'),
(62, 63, 1552, 1, 10000.00, 'sản phẩm test', '2026-03-12 15:38:02', '2026-03-12 15:38:02'),
(63, 64, 1552, 1, 10000.00, 'sản phẩm test', '2026-03-12 15:38:56', '2026-03-12 15:38:56'),
(64, 65, 1552, 1, 10000.00, 'sản phẩm test', '2026-03-12 16:02:05', '2026-03-12 16:02:05'),
(65, 66, 1552, 1, 10000.00, 'sản phẩm test', '2026-03-12 16:10:35', '2026-03-12 16:10:35'),
(66, 67, 1552, 1, 10000.00, 'sản phẩm test', '2026-03-12 17:06:09', '2026-03-12 17:06:09'),
(67, 68, 1552, 1, 10000.00, 'sản phẩm test', '2026-03-12 17:07:24', '2026-03-12 17:07:24'),
(68, 69, 1552, 1, 10000.00, 'sản phẩm test', '2026-03-13 14:30:32', '2026-03-13 14:30:32'),
(69, 70, 1552, 1, 10000.00, 'sản phẩm test', '2026-03-13 14:52:52', '2026-03-13 14:52:52'),
(70, 71, 1552, 1, 10000.00, 'sản phẩm test', '2026-03-13 15:04:52', '2026-03-13 15:04:52');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `order_status_logs`
--

CREATE TABLE `order_status_logs` (
  `log_id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `status` enum('pending','processing','shipping','completed','cancelled','returned') NOT NULL,
  `changed_by` int(11) NOT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `changed_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Đang đổ dữ liệu cho bảng `order_status_logs`
--

INSERT INTO `order_status_logs` (`log_id`, `order_id`, `status`, `changed_by`, `reason`, `changed_at`) VALUES
(1, 1, 'pending', 1, 'Đơn hàng được tạo', '2025-08-19 23:08:53'),
(2, 2, 'pending', 1, 'Đơn hàng được tạo', '2025-08-19 23:08:53'),
(3, 2, 'processing', 1, 'Đơn hàng đang được xử lý', '2025-08-19 23:08:53'),
(4, 3, 'pending', 1, 'Đơn hàng được tạo', '2025-08-19 23:08:53'),
(5, 3, 'processing', 1, 'Đơn hàng đang được xử lý', '2025-08-19 23:08:53'),
(6, 3, 'shipping', 1, 'Đơn hàng đang được giao', '2025-08-19 23:08:53'),
(7, 4, 'pending', 1, 'Đơn hàng được tạo', '2025-08-19 23:08:53'),
(8, 4, 'processing', 1, 'Đơn hàng đang được xử lý', '2025-08-19 23:08:53'),
(9, 4, 'shipping', 1, 'Đơn hàng đang được giao', '2025-08-19 23:08:53'),
(10, 4, 'completed', 1, 'Đơn hàng đã được giao thành công', '2025-08-19 23:08:53'),
(11, 5, 'pending', 1, 'Đơn hàng được tạo', '2025-08-19 23:08:53'),
(12, 5, 'cancelled', 1, 'Khách hàng hủy đơn hàng', '2025-08-19 23:08:53'),
(13, 1, 'processing', 1, 'Test', '2025-08-20 10:11:16'),
(14, 1, 'processing', 1, 'Test', '2025-08-20 10:19:01'),
(15, 1, 'shipping', 1, 'Ready to ship', '2025-08-20 10:23:16'),
(16, 2, 'pending', 1, '', '2025-08-20 10:31:14'),
(17, 1, 'pending', 1, '', '2025-08-20 14:27:31'),
(18, 1, 'processing', 1, 'Test update from debug script', '2025-08-21 10:03:37'),
(19, 1, 'processing', 1, 'Test update', '2025-08-21 10:08:55'),
(20, 1, 'processing', 1, 'Test update from direct endpoint', '2025-08-21 10:31:27'),
(21, 2, 'processing', 1, 'Test update', '2025-08-21 16:08:45'),
(22, 3, 'completed', 1, '', '2025-08-21 16:09:03'),
(23, 1, 'completed', 1, 'Test update status', '2025-08-22 12:59:42'),
(24, 2, 'pending', 1, '', '2025-08-22 21:09:08'),
(25, 2, 'processing', 1, '', '2025-08-22 21:17:12'),
(26, 2, 'pending', 1, '', '2025-08-22 21:54:12'),
(27, 2, 'processing', 1, '', '2025-08-23 00:24:27'),
(28, 2, 'pending', 1, '', '2025-08-23 01:53:35'),
(29, 2, 'processing', 1, '', '2025-08-23 02:21:12'),
(30, 2, 'processing', 1, '', '2025-08-23 02:56:14'),
(31, 2, 'shipping', 1, '', '2025-11-13 13:44:22'),
(32, 5, 'processing', 1, '', '2025-11-13 17:20:49'),
(33, 5, 'shipping', 4, 'Shipper picked up the order', '2025-11-13 21:43:32'),
(34, 3, 'completed', 4, 'Synced from shipping workflow', '2025-11-16 21:18:53'),
(35, 1, 'processing', 1, '', '2025-11-16 21:29:01'),
(36, 1, 'completed', 4, 'Synced from shipping workflow', '2025-11-16 21:35:52'),
(38, 8, 'pending', 2, 'Đơn hàng được tạo', '2025-11-23 23:08:18'),
(39, 9, 'pending', 2, 'Đơn hàng được tạo', '2025-11-23 23:14:15'),
(40, 10, 'pending', 2, 'Đơn hàng được tạo', '2025-11-23 23:14:22'),
(41, 11, 'pending', 3, 'Đơn hàng được tạo', '2025-11-23 23:39:31'),
(42, 12, 'pending', 3, 'Đơn hàng được tạo', '2025-11-23 23:40:00'),
(43, 13, 'pending', 3, 'Đơn hàng được tạo', '2025-11-23 23:41:46'),
(44, 14, 'pending', 3, 'Đơn hàng được tạo', '2025-11-23 23:42:22'),
(45, 15, 'pending', 3, 'Đơn hàng được tạo', '2025-11-23 23:45:13'),
(46, 16, 'pending', 3, 'Đơn hàng được tạo', '2025-11-23 23:48:13'),
(47, 17, 'pending', 3, 'Đơn hàng được tạo', '2025-11-23 23:48:44'),
(48, 18, 'pending', 3, 'Đơn hàng được tạo', '2025-11-23 23:49:15'),
(49, 19, 'pending', 3, 'Đơn hàng được tạo', '2025-11-23 23:51:46'),
(50, 20, 'pending', 3, 'Đơn hàng được tạo', '2025-11-23 23:52:11'),
(51, 21, 'pending', 3, 'Đơn hàng được tạo', '2025-11-23 23:58:25'),
(52, 22, 'pending', 3, 'Đơn hàng được tạo', '2025-11-24 00:00:08'),
(53, 23, 'pending', 3, 'Đơn hàng được tạo', '2025-11-24 00:34:08'),
(54, 24, 'pending', 3, 'Đơn hàng được tạo', '2025-11-24 00:41:12'),
(55, 25, 'pending', 3, 'Đơn hàng được tạo', '2025-11-25 11:40:40'),
(56, 26, 'pending', 2, 'Đơn hàng được tạo', '2025-11-25 21:30:57'),
(57, 28, 'pending', 2, 'Đơn hàng được tạo', '2025-12-06 12:45:42'),
(58, 29, 'pending', 2, 'Đơn hàng được tạo', '2025-12-06 13:02:01'),
(59, 30, 'pending', 2, 'Đơn hàng được tạo', '2025-12-06 13:23:27'),
(60, 31, 'pending', 2, 'Đơn hàng được tạo', '2025-12-06 14:21:58'),
(61, 32, 'pending', 2, 'Đơn hàng được tạo', '2025-12-06 14:32:44'),
(62, 33, 'pending', 2, 'Đơn hàng được tạo', '2025-12-06 14:56:18'),
(63, 34, 'pending', 2, 'Đơn hàng được tạo', '2025-12-06 14:58:06'),
(64, 35, 'pending', 2, 'Đơn hàng được tạo', '2025-12-06 15:18:53'),
(65, 36, 'pending', 2, 'Đơn hàng được tạo', '2025-12-06 15:24:46'),
(66, 37, 'pending', 2, 'Đơn hàng được tạo', '2025-12-06 15:39:05'),
(67, 38, 'pending', 2, 'Đơn hàng được tạo', '2025-12-06 15:43:29'),
(68, 39, 'pending', 2, 'Đơn hàng được tạo', '2025-12-06 15:59:25'),
(69, 40, 'pending', 2, 'Đơn hàng được tạo', '2025-12-06 16:02:34'),
(70, 41, 'pending', 2, 'Đơn hàng được tạo', '2025-12-06 17:04:40'),
(71, 42, 'pending', 2, 'Đơn hàng được tạo', '2025-12-06 17:30:23'),
(72, 43, 'pending', 2, 'Đơn hàng được tạo', '2025-12-06 17:41:34'),
(73, 44, 'pending', 3, 'Đơn hàng được tạo', '2025-12-06 18:06:32'),
(74, 45, 'pending', 3, 'Đơn hàng được tạo', '2025-12-30 11:53:04'),
(75, 44, 'pending', 1, '', '2025-12-30 11:53:57'),
(76, 45, 'shipping', 1, '', '2025-12-30 11:54:43'),
(77, 46, 'pending', 3, 'Đơn hàng được tạo', '2026-01-25 15:56:44'),
(78, 47, 'pending', 2, 'Đơn hàng được tạo', '2026-01-25 16:03:46'),
(79, 48, 'pending', 2, 'Đơn hàng được tạo', '2026-01-25 16:44:55'),
(80, 49, 'pending', 2, 'Đơn hàng được tạo', '2026-01-25 16:53:06'),
(81, 50, 'pending', 2, 'Đơn hàng được tạo', '2026-01-25 17:02:38'),
(82, 51, 'pending', 2, 'Đơn hàng được tạo', '2026-01-25 18:22:33'),
(83, 52, 'pending', 2, 'Đơn hàng được tạo', '2026-01-25 18:45:53'),
(84, 53, 'pending', 3, 'Đơn hàng được tạo', '2026-01-26 22:36:44'),
(85, 54, 'pending', 3, 'Đơn hàng được tạo', '2026-03-12 14:17:58'),
(86, 55, 'pending', 3, 'Đơn hàng được tạo', '2026-03-12 14:20:34'),
(87, 56, 'pending', 3, 'Đơn hàng được tạo', '2026-03-12 14:25:57'),
(88, 57, 'pending', 3, 'Đơn hàng được tạo', '2026-03-12 14:29:47'),
(89, 58, 'pending', 3, 'Đơn hàng được tạo', '2026-03-12 14:33:27'),
(90, 59, 'pending', 3, 'Đơn hàng được tạo', '2026-03-12 14:36:13'),
(91, 60, 'pending', 3, 'Đơn hàng được tạo', '2026-03-12 15:02:43'),
(92, 61, 'pending', 3, 'Đơn hàng được tạo', '2026-03-12 15:14:43'),
(93, 62, 'pending', 3, 'Đơn hàng được tạo', '2026-03-12 15:26:07'),
(94, 63, 'pending', 3, 'Đơn hàng được tạo', '2026-03-12 15:38:02'),
(95, 64, 'pending', 3, 'Đơn hàng được tạo', '2026-03-12 15:38:56'),
(96, 65, 'pending', 3, 'Đơn hàng được tạo', '2026-03-12 16:02:05'),
(97, 66, 'pending', 3, 'Đơn hàng được tạo', '2026-03-12 16:10:35'),
(98, 67, 'pending', 3, 'Đơn hàng được tạo', '2026-03-12 17:06:09'),
(99, 68, 'pending', 3, 'Đơn hàng được tạo', '2026-03-12 17:07:24'),
(100, 69, 'pending', 3, 'Đơn hàng được tạo', '2026-03-13 14:30:32'),
(101, 70, 'pending', 3, 'Đơn hàng được tạo', '2026-03-13 14:52:52'),
(102, 71, 'pending', 3, 'Đơn hàng được tạo', '2026-03-13 15:04:52'),
(103, 69, 'processing', 1, 'Order assigned to shipper', '2026-03-13 15:17:43'),
(104, 71, 'shipping', 1, '', '2026-03-13 15:18:43');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `order_tracking_events`
--

CREATE TABLE `order_tracking_events` (
  `event_id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `status` varchar(50) NOT NULL,
  `note` text DEFAULT NULL,
  `lat` decimal(10,6) DEFAULT NULL,
  `lng` decimal(10,6) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Đang đổ dữ liệu cho bảng `order_tracking_events`
--

INSERT INTO `order_tracking_events` (`event_id`, `order_id`, `status`, `note`, `lat`, `lng`, `created_by`, `created_at`) VALUES
(1, 1, 'pending', 'Đơn hàng được tạo', NULL, NULL, 1, '2025-10-28 13:00:00'),
(2, 1, 'confirmed', 'Shop xác nhận đơn hàng', NULL, NULL, 1, '2025-10-28 13:15:00'),
(3, 1, 'assigned', 'Đã gán cho shipper Nguyễn Văn A', NULL, NULL, 1, '2025-10-28 13:30:00'),
(4, 1, 'picking_up', 'Shipper đang đến lấy hàng', 10.760000, 106.650000, 4, '2025-10-28 14:00:00'),
(5, 1, 'picked_up', 'Đã lấy hàng thành công', 10.762622, 106.660172, 4, '2025-10-28 14:15:00'),
(6, 1, 'in_transit', 'Đang giao hàng', 10.765000, 106.665000, 4, '2025-10-28 14:30:00'),
(7, 2, 'pending', 'Đơn hàng được tạo', NULL, NULL, 1, '2025-10-28 12:30:00'),
(8, 2, 'confirmed', 'Shop xác nhận đơn hàng', NULL, NULL, 1, '2025-10-28 12:45:00'),
(9, 2, 'assigned', 'Đã gán cho shipper Trần Thị B', NULL, NULL, 1, '2025-10-28 13:00:00'),
(10, 2, 'picking_up', 'Shipper đang đến lấy hàng', 10.775000, 106.675000, 5, '2025-10-28 13:30:00'),
(11, 2, 'picked_up', 'Đã lấy hàng thành công', 10.780000, 106.680000, 5, '2025-10-28 14:00:00'),
(12, 2, 'in_transit', 'Đang giao hàng', 10.785000, 106.685000, 5, '2025-10-28 14:25:00'),
(13, 3, 'pending', 'Đơn hàng được tạo', NULL, NULL, 1, '2025-10-27 10:00:00'),
(14, 3, 'confirmed', 'Shop xác nhận đơn hàng', NULL, NULL, 1, '2025-10-27 10:15:00'),
(15, 3, 'assigned', 'Đã gán cho shipper Lê Văn C', NULL, NULL, 1, '2025-10-27 10:30:00'),
(16, 3, 'picking_up', 'Shipper đang đến lấy hàng', 10.750000, 106.650000, 12, '2025-10-27 11:00:00'),
(17, 3, 'picked_up', 'Đã lấy hàng thành công', 10.760000, 106.660000, 12, '2025-10-27 11:30:00'),
(18, 3, 'in_transit', 'Đang giao hàng', 10.770000, 106.670000, 12, '2025-10-27 12:00:00'),
(19, 3, 'arriving', 'Sắp đến nơi', 10.775000, 106.675000, 12, '2025-10-27 12:30:00'),
(20, 3, 'delivered', 'Giao hàng thành công', 10.780000, 106.680000, 12, '2025-10-27 12:45:00');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `payments`
--

CREATE TABLE `payments` (
  `payment_id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `method` enum('mock_qr','vietqr','vnpay','bank_transfer','cod','casso','payos') NOT NULL,
  `status` enum('pending','confirmed','failed') NOT NULL DEFAULT 'pending',
  `transaction_id` varchar(100) DEFAULT NULL,
  `bank_code` varchar(20) DEFAULT NULL,
  `paid_amount` decimal(12,2) DEFAULT 0.00,
  `is_suspicious` tinyint(1) DEFAULT 0,
  `vnp_secure_hash` varchar(255) DEFAULT NULL,
  `callback_payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`callback_payload`)),
  `confirmed_at` datetime DEFAULT NULL,
  `failure_reason` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `payment_url` varchar(500) DEFAULT NULL COMMENT 'URL for QR code (ngrok URL)',
  `expires_at` datetime DEFAULT NULL COMMENT 'Payment expiration time'
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Đang đổ dữ liệu cho bảng `payments`
--

INSERT INTO `payments` (`payment_id`, `order_id`, `method`, `status`, `transaction_id`, `bank_code`, `paid_amount`, `is_suspicious`, `vnp_secure_hash`, `callback_payload`, `confirmed_at`, `failure_reason`, `created_at`, `updated_at`, `payment_url`, `expires_at`) VALUES
(1, 1, 'cod', 'pending', NULL, NULL, 0.00, 0, NULL, NULL, NULL, NULL, '2025-08-19 23:08:53', '2025-08-19 23:08:53', NULL, NULL),
(2, 2, 'vnpay', 'confirmed', 'VNPAY_20250114_001', 'VCB', 800000.00, 0, 'abc123', '{\"vnp_Amount\":\"80000000\",\"vnp_BankCode\":\"VCB\"}', '2025-08-19 23:08:53', NULL, '2025-08-19 23:08:53', '2025-08-19 23:08:53', NULL, NULL),
(3, 3, 'cod', 'pending', NULL, NULL, 0.00, 0, NULL, NULL, NULL, NULL, '2025-08-19 23:08:53', '2025-08-19 23:08:53', NULL, NULL),
(4, 4, 'vietqr', 'confirmed', 'VIETQR_20250114_001', 'TCB', 590000.00, 0, 'def456', '{\"amount\":\"590000\",\"bank_code\":\"TCB\"}', '2025-08-19 23:08:53', NULL, '2025-08-19 23:08:53', '2025-08-19 23:08:53', NULL, NULL),
(5, 5, 'cod', 'pending', NULL, NULL, 0.00, 0, NULL, NULL, NULL, NULL, '2025-08-19 23:08:53', '2025-08-19 23:08:53', NULL, NULL),
(6, 9, 'vietqr', 'pending', NULL, NULL, 3400000.00, 0, NULL, NULL, NULL, NULL, '2025-11-23 23:14:15', '2025-11-23 23:14:15', 'http://localhost:3000/checkout/payment/approve/6', '2025-11-24 17:14:15'),
(7, 10, 'vietqr', 'pending', NULL, NULL, 3400000.00, 0, NULL, NULL, NULL, NULL, '2025-11-23 23:14:22', '2025-11-23 23:14:22', 'http://localhost:3000/checkout/payment/approve/7', '2025-11-24 17:14:22'),
(8, 11, 'vietqr', 'pending', NULL, NULL, 750000.00, 0, NULL, NULL, NULL, NULL, '2025-11-23 23:39:31', '2025-11-23 23:39:31', 'http://localhost:3000/checkout/payment/approve/8', '2025-11-24 17:39:31'),
(9, 12, 'vietqr', 'pending', NULL, NULL, 750000.00, 0, NULL, NULL, NULL, NULL, '2025-11-23 23:40:00', '2025-11-23 23:40:00', 'http://localhost:3000/checkout/payment/approve/9', '2025-11-24 17:40:00'),
(10, 13, 'vietqr', 'pending', NULL, NULL, 750000.00, 0, NULL, NULL, NULL, NULL, '2025-11-23 23:41:46', '2025-11-23 23:41:46', 'http://localhost:3000/checkout/payment/approve/10', '2025-11-24 17:41:46'),
(11, 14, 'vietqr', 'pending', NULL, NULL, 780000.00, 0, NULL, NULL, NULL, NULL, '2025-11-23 23:42:22', '2025-11-23 23:42:22', 'http://localhost:3000/checkout/payment/approve/11', '2025-11-24 17:42:22'),
(12, 15, 'vietqr', 'pending', NULL, NULL, 750000.00, 0, NULL, NULL, NULL, NULL, '2025-11-23 23:45:13', '2025-11-23 23:45:13', 'http://localhost:3000/checkout/payment/approve/12', '2025-11-24 17:45:13'),
(13, 16, 'vietqr', 'pending', NULL, NULL, 750000.00, 0, NULL, NULL, NULL, NULL, '2025-11-23 23:48:13', '2025-11-23 23:48:13', 'http://localhost:3000/checkout/payment/approve/13', '2025-11-24 17:48:13'),
(14, 17, 'vietqr', 'pending', NULL, NULL, 750000.00, 0, NULL, NULL, NULL, NULL, '2025-11-23 23:48:44', '2025-11-23 23:48:44', 'http://localhost:3000/checkout/payment/approve/14', '2025-11-24 17:48:44'),
(15, 18, 'vietqr', 'pending', NULL, NULL, 750000.00, 0, NULL, NULL, NULL, NULL, '2025-11-23 23:49:15', '2025-11-23 23:49:15', 'http://localhost:3000/checkout/payment/approve/15', '2025-11-24 17:49:15'),
(16, 19, 'vietqr', 'pending', NULL, NULL, 750000.00, 0, NULL, NULL, NULL, NULL, '2025-11-23 23:51:46', '2025-11-23 23:51:46', 'http://localhost:3000/checkout/payment/approve/16', '2025-11-24 17:51:46'),
(17, 20, 'vietqr', 'pending', NULL, NULL, 750000.00, 0, NULL, NULL, NULL, NULL, '2025-11-23 23:52:11', '2025-11-23 23:52:11', 'http://localhost:3000/checkout/payment/approve/17', '2025-11-24 17:52:11'),
(18, 21, 'vietqr', 'pending', NULL, NULL, 750000.00, 0, NULL, NULL, NULL, NULL, '2025-11-23 23:58:25', '2025-11-23 23:58:25', 'http://localhost:3000/checkout/payment/approve/18', '2025-11-24 17:58:25'),
(19, 22, 'vietqr', 'pending', NULL, NULL, 750000.00, 0, NULL, NULL, NULL, NULL, '2025-11-24 00:00:08', '2025-11-24 00:00:08', 'http://localhost:3000/checkout/payment/approve/19', '2025-11-24 18:00:08'),
(20, 23, 'vietqr', 'pending', NULL, NULL, 750000.00, 0, NULL, NULL, NULL, NULL, '2025-11-24 00:34:08', '2025-11-24 00:34:08', 'https://prechampioned-nonentreatingly-eugenia.ngrok-free.dev/checkout/payment/approve/20', '2025-11-24 18:34:08'),
(21, 24, 'vietqr', 'confirmed', NULL, NULL, 750000.00, 0, NULL, NULL, '2025-11-24 00:47:16', NULL, '2025-11-24 00:41:12', '2025-11-24 00:47:16', 'https://prechampioned-nonentreatingly-eugenia.ngrok-free.dev/checkout/payment/approve/21', '2025-11-24 18:41:12'),
(22, 25, 'vietqr', 'confirmed', NULL, NULL, 750000.00, 0, NULL, NULL, '2025-11-25 11:42:46', NULL, '2025-11-25 11:40:40', '2025-11-25 11:42:46', 'https://prechampioned-nonentreatingly-eugenia.ngrok-free.dev/checkout/payment/approve/22', '2025-11-26 05:40:40'),
(23, 26, 'vietqr', 'confirmed', NULL, NULL, 2550000.00, 0, NULL, NULL, '2025-11-25 21:31:32', NULL, '2025-11-25 21:30:57', '2025-11-25 21:31:32', 'https://prechampioned-nonentreatingly-eugenia.ngrok-free.dev/checkout/payment/approve/23', '2025-11-26 15:30:57'),
(24, 28, '', 'pending', NULL, NULL, 10000.00, 0, NULL, NULL, NULL, NULL, '2025-12-06 12:45:42', '2026-03-12 14:29:40', NULL, '2025-12-07 06:45:42'),
(25, 29, '', 'pending', NULL, NULL, 10000.00, 0, NULL, NULL, NULL, NULL, '2025-12-06 13:02:01', '2026-03-12 14:29:40', NULL, '2025-12-07 07:02:01'),
(26, 30, '', 'pending', NULL, NULL, 10000.00, 0, NULL, NULL, NULL, NULL, '2025-12-06 13:23:27', '2026-03-12 14:29:40', NULL, '2025-12-07 07:23:27'),
(27, 31, '', 'pending', NULL, NULL, 10000.00, 0, NULL, NULL, NULL, NULL, '2025-12-06 14:21:58', '2026-03-12 14:29:40', NULL, '2025-12-07 08:21:58'),
(28, 32, '', 'pending', NULL, NULL, 10000.00, 0, NULL, NULL, NULL, NULL, '2025-12-06 14:32:44', '2026-03-12 14:29:40', NULL, '2025-12-07 08:32:44'),
(29, 33, '', 'pending', NULL, NULL, 10000.00, 0, NULL, NULL, NULL, NULL, '2025-12-06 14:56:18', '2026-03-12 14:29:40', NULL, '2025-12-07 08:56:18'),
(30, 34, '', 'pending', NULL, NULL, 10000.00, 0, NULL, NULL, NULL, NULL, '2025-12-06 14:58:06', '2026-03-12 14:29:40', NULL, '2025-12-07 08:58:06'),
(31, 35, '', 'pending', NULL, NULL, 10000.00, 0, NULL, NULL, NULL, NULL, '2025-12-06 15:18:53', '2026-03-12 14:29:40', NULL, '2025-12-07 09:18:53'),
(32, 36, '', 'pending', NULL, NULL, 10000.00, 0, NULL, NULL, NULL, NULL, '2025-12-06 15:24:46', '2026-03-12 14:29:40', NULL, '2025-12-07 09:24:46'),
(33, 37, '', 'pending', NULL, NULL, 10000.00, 0, NULL, NULL, NULL, NULL, '2025-12-06 15:39:05', '2026-03-12 14:29:40', 'https://img.vietqr.io/image/VCB-9353126350-compact2.png?amount=1000000&addInfo=ORDER_37', '2025-12-07 09:39:05'),
(34, 38, '', 'pending', NULL, NULL, 10000.00, 0, NULL, NULL, NULL, NULL, '2025-12-06 15:43:29', '2026-03-12 14:29:40', 'https://img.vietqr.io/image/VCB-9353126350-compact2.png?amount=10000&addInfo=ORDER_38', '2025-12-07 09:43:29'),
(35, 39, '', 'pending', NULL, NULL, 10000.00, 0, NULL, NULL, NULL, NULL, '2025-12-06 15:59:25', '2026-03-12 14:29:40', 'https://img.vietqr.io/image/VCB-9353126350-compact2.png?amount=10000&addInfo=ORDER_39', '2025-12-07 09:59:25'),
(36, 40, '', 'confirmed', NULL, NULL, 10000.00, 0, NULL, NULL, NULL, NULL, '2025-12-06 16:02:34', '2026-03-12 14:29:40', 'https://img.vietqr.io/image/VCB-9353126350-compact2.png?amount=10000&addInfo=ORDER_40', '2025-12-07 10:02:34'),
(37, 41, '', 'confirmed', 'tx_win_001', NULL, 10000.00, 0, NULL, '{\"id\":\"tx_win_001\",\"tid\":\"tx_win_001\",\"description\":\"ORDER_41\",\"amount\":10000}', '2025-12-06 17:21:26', NULL, '2025-12-06 17:04:40', '2026-03-12 14:29:40', 'https://img.vietqr.io/image/VCB-9353126350-compact2.png?amount=10000&addInfo=ORDER_41', '2025-12-07 11:04:40'),
(38, 42, '', 'pending', NULL, NULL, 10000.00, 0, NULL, NULL, NULL, NULL, '2025-12-06 17:30:23', '2026-03-12 14:29:40', 'https://img.vietqr.io/image/VCB-9353126350-compact2.png?amount=10000&addInfo=ORDER_42', '2025-12-07 11:30:23'),
(39, 43, '', 'confirmed', 'tx_order43', NULL, 10000.00, 0, NULL, '{\"id\":\"tx_order43\",\"tid\":\"tx_order43\",\"description\":\"ORDER_43\",\"amount\":10000}', '2025-12-06 17:46:28', NULL, '2025-12-06 17:41:34', '2026-03-12 14:29:40', 'https://img.vietqr.io/image/VCB-9353126350-compact2.png?amount=10000&addInfo=ORDER_43', '2025-12-07 11:41:34'),
(40, 44, '', 'pending', NULL, NULL, 10000.00, 0, NULL, NULL, NULL, NULL, '2025-12-06 18:06:32', '2026-03-12 14:29:40', 'https://img.vietqr.io/image/VCB-9353126350-compact2.png?amount=10000&addInfo=ORDER_44', '2025-12-07 12:06:32'),
(41, 45, 'vietqr', 'pending', NULL, NULL, 20000.00, 0, NULL, NULL, NULL, NULL, '2025-12-30 11:53:04', '2025-12-30 11:53:04', 'https://prechampioned-nonentreatingly-eugenia.ngrok-free.dev/checkout/payment/approve/41', '2025-12-31 05:53:04'),
(42, 46, 'vietqr', 'pending', NULL, NULL, 20000.00, 0, NULL, NULL, NULL, NULL, '2026-01-25 15:56:44', '2026-01-25 15:56:44', 'https://prechampioned-nonentreatingly-eugenia.ngrok-free.dev/checkout/payment/approve/42', '2026-01-26 09:56:44'),
(43, 47, 'vietqr', 'pending', NULL, NULL, 10000.00, 0, NULL, NULL, NULL, NULL, '2026-01-25 16:03:46', '2026-01-25 16:03:46', 'https://prechampioned-nonentreatingly-eugenia.ngrok-free.dev/checkout/payment/approve/43', '2026-01-26 10:03:46'),
(44, 48, 'vietqr', 'pending', NULL, NULL, 20000.00, 0, NULL, NULL, NULL, NULL, '2026-01-25 16:44:55', '2026-01-25 16:44:55', 'https://prechampioned-nonentreatingly-eugenia.ngrok-free.dev/checkout/payment/approve/44', '2026-01-26 10:44:55'),
(45, 49, 'vietqr', 'pending', NULL, NULL, 10000.00, 0, NULL, NULL, NULL, NULL, '2026-01-25 16:53:06', '2026-01-25 16:53:06', 'https://prechampioned-nonentreatingly-eugenia.ngrok-free.dev/checkout/payment/approve/45', '2026-01-26 10:53:06'),
(46, 50, '', 'confirmed', '13321407', NULL, 10000.00, 0, NULL, '{\"id\":13321407,\"tid\":\"5161 - 85891\",\"description\":\"PARTNER.DIRECT_DEBITS_VCB.MSE.115826678312.20260125.115826678312-0353126350_ORDER50\",\"amount\":10000,\"cusumBalance\":38936,\"when\":\"2026-01-25T17:03:06\",\"bookingDate\":null,\"bankSubAccId\":\"9353126350\",\"paymentChannel\":\"\",\"virtualAccount\":\"\",\"virtualAccountName\":\"\",\"corresponsiveName\":\"\",\"corresponsiveAccount\":\"\",\"corresponsiveBankId\":\"\",\"corresponsiveBankName\":\"\",\"accountId\":14477,\"bankCodeName\":\"vietcombank\"}', '2026-01-25 18:40:53', NULL, '2026-01-25 17:02:38', '2026-03-12 14:29:40', 'https://img.vietqr.io/image/VCB-9353126350-compact2.jpg?amount=10000&addInfo=ORDER_50&accountName=LE+DAT+THANH', '2026-01-26 11:02:38'),
(47, 51, '', 'confirmed', '13322063', 'VCB', 10000.00, 0, NULL, '{\"id\":13322063,\"tid\":\"5161 - 25432\",\"description\":\"PARTNER.DIRECT_DEBITS_VCB.MSE.115838040301.20260125.115838040301-0353126350_ORDER51\",\"amount\":10000,\"cusum_balance\":48936,\"when\":\"2026-01-25 18:35:56\",\"bank_sub_acc_id\":\"9353126350\",\"subAccId\":\"9353126350\",\"bankName\":\"Vietcombank\",\"bankAbbreviation\":\"VCB\",\"virtualAccount\":\"\",\"virtualAccountName\":\"\",\"corresponsiveName\":\"\",\"corresponsiveAccount\":\"\",\"corresponsiveBankId\":\"\",\"corresponsiveBankName\":\"\"}', '2026-01-25 18:40:56', NULL, '2026-01-25 18:22:33', '2026-03-12 14:29:40', 'https://img.vietqr.io/image/VCB-9353126350-compact2.jpg?amount=10000&addInfo=ORDER_51&accountName=LE+DAT+THANH', '2026-01-26 12:22:33'),
(48, 52, 'cod', 'confirmed', NULL, NULL, 10000.00, 0, NULL, NULL, '2026-01-25 18:45:53', NULL, '2026-01-25 18:45:53', '2026-01-25 18:45:53', NULL, NULL),
(49, 53, '', 'pending', NULL, NULL, 20000.00, 0, NULL, NULL, NULL, NULL, '2026-01-26 22:36:44', '2026-03-12 14:29:40', 'https://img.vietqr.io/image/VCB-9353126350-compact2.jpg?amount=20000&addInfo=ORDER_53&accountName=LE+DAT+THANH', '2026-01-27 16:36:44'),
(50, 54, '', 'pending', NULL, NULL, 10000.00, 0, NULL, NULL, NULL, NULL, '2026-03-12 14:17:58', '2026-03-12 14:17:58', NULL, '2026-03-13 08:17:58'),
(51, 55, '', 'pending', NULL, NULL, 10000.00, 0, NULL, NULL, NULL, NULL, '2026-03-12 14:20:34', '2026-03-12 14:20:34', NULL, '2026-03-13 08:20:34'),
(52, 56, '', 'pending', NULL, NULL, 10000.00, 0, NULL, NULL, NULL, NULL, '2026-03-12 14:25:57', '2026-03-12 14:25:57', NULL, '2026-03-13 08:25:57'),
(53, 57, '', 'pending', NULL, NULL, 10000.00, 0, NULL, NULL, NULL, NULL, '2026-03-12 14:29:47', '2026-03-12 14:29:47', NULL, '2026-03-13 08:29:47'),
(54, 58, '', 'pending', NULL, NULL, 10000.00, 0, NULL, NULL, NULL, NULL, '2026-03-12 14:33:27', '2026-03-12 14:33:27', NULL, '2026-03-13 08:33:27'),
(55, 59, 'payos', 'pending', NULL, NULL, 10000.00, 0, NULL, NULL, NULL, NULL, '2026-03-12 14:36:13', '2026-03-12 14:36:13', NULL, '2026-03-13 08:36:13'),
(56, 60, 'payos', 'pending', NULL, NULL, 10000.00, 0, NULL, NULL, NULL, NULL, '2026-03-12 15:02:43', '2026-03-12 15:02:43', NULL, '2026-03-13 09:02:43'),
(57, 61, 'payos', 'pending', NULL, NULL, 10000.00, 0, NULL, NULL, NULL, NULL, '2026-03-12 15:14:43', '2026-03-12 15:14:43', NULL, '2026-03-13 09:14:43'),
(58, 62, 'payos', 'pending', NULL, NULL, 10000.00, 0, NULL, NULL, NULL, NULL, '2026-03-12 15:26:07', '2026-03-12 15:26:07', NULL, '2026-03-13 09:26:07'),
(59, 63, 'payos', 'pending', NULL, NULL, 10000.00, 0, NULL, NULL, NULL, NULL, '2026-03-12 15:38:02', '2026-03-12 15:38:02', NULL, '2026-03-13 09:38:02'),
(60, 64, 'payos', 'pending', NULL, NULL, 10000.00, 0, NULL, NULL, NULL, NULL, '2026-03-12 15:38:56', '2026-03-12 15:38:56', NULL, '2026-03-13 09:38:56'),
(61, 65, 'payos', 'pending', NULL, NULL, 10000.00, 0, NULL, NULL, NULL, NULL, '2026-03-12 16:02:05', '2026-03-12 16:02:05', NULL, '2026-03-13 10:02:05'),
(62, 66, 'payos', 'pending', NULL, NULL, 10000.00, 0, NULL, NULL, NULL, NULL, '2026-03-12 16:10:35', '2026-03-12 16:10:36', 'https://pay.payos.vn/web/122e6c085c8a48bb8a66a8e5d3972b54', '2026-03-13 10:10:35'),
(63, 67, 'payos', 'pending', NULL, NULL, 10000.00, 0, NULL, NULL, NULL, NULL, '2026-03-12 17:06:09', '2026-03-12 17:06:09', NULL, '2026-03-13 11:06:09'),
(64, 68, 'payos', 'pending', NULL, NULL, 10000.00, 0, NULL, NULL, NULL, NULL, '2026-03-12 17:07:24', '2026-03-12 17:07:24', NULL, '2026-03-13 11:07:24'),
(65, 69, 'payos', 'pending', NULL, NULL, 10000.00, 0, NULL, NULL, NULL, NULL, '2026-03-13 14:30:32', '2026-03-13 14:30:33', 'https://pay.payos.vn/web/6287c65f92f046628ffebf5b171de757', '2026-03-14 08:30:32'),
(66, 70, 'payos', 'pending', NULL, NULL, 10000.00, 0, NULL, NULL, NULL, NULL, '2026-03-13 14:52:52', '2026-03-13 14:52:53', 'https://pay.payos.vn/web/c10d1c03a4e14bbdbe76b14657b6c95c', '2026-03-14 08:52:52'),
(67, 71, 'payos', 'confirmed', 'FT26072695187907', '', 10000.00, 0, NULL, '{\"accountNumber\":\"0353126350\",\"amount\":10000,\"description\":\"ORDER71\",\"reference\":\"FT26072695187907\",\"transactionDateTime\":\"2026-03-13 15:05:29\",\"virtualAccountNumber\":\"\",\"counterAccountBankId\":\"970422\",\"counterAccountBankName\":\"\",\"counterAccountName\":null,\"counterAccountNumber\":\"2281072020614\",\"virtualAccountName\":\"\",\"currency\":\"VND\",\"orderCode\":71,\"paymentLinkId\":\"7556539d881943beb72234ff87372a83\",\"code\":\"00\",\"desc\":\"success\"}', '2026-03-13 15:05:30', NULL, '2026-03-13 15:04:52', '2026-03-13 15:05:30', 'https://pay.payos.vn/web/7556539d881943beb72234ff87372a83', '2026-03-14 09:04:52');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `payos_transactions`
--

CREATE TABLE `payos_transactions` (
  `id` int(11) NOT NULL,
  `transaction_id` varchar(191) NOT NULL,
  `payment_id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `processed_at` datetime NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Đang đổ dữ liệu cho bảng `payos_transactions`
--

INSERT INTO `payos_transactions` (`id`, `transaction_id`, `payment_id`, `order_id`, `amount`, `description`, `processed_at`, `created_at`) VALUES
(1, 'FT26072695187907', 67, 71, 10000.00, 'ORDER71', '2026-03-13 15:05:30', '2026-03-13 15:05:30');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `products`
--

CREATE TABLE `products` (
  `product_id` int(11) NOT NULL,
  `product_name` varchar(255) NOT NULL,
  `slug` varchar(255) DEFAULT NULL,
  `category_id` int(11) NOT NULL,
  `short_description` text DEFAULT NULL,
  `description` text DEFAULT NULL,
  `material` varchar(100) DEFAULT NULL,
  `list_price` decimal(10,2) NOT NULL COMMENT 'Giá bán chính',
  `compare_at_price` decimal(10,2) DEFAULT NULL COMMENT 'Giá so sánh (gạch ngang)',
  `cost_price` decimal(10,2) DEFAULT NULL COMMENT 'Giá vốn',
  `stock` int(11) DEFAULT 0 COMMENT 'Tồn kho tổng',
  `status` enum('active','inactive','draft') DEFAULT 'active',
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_featured` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Đang đổ dữ liệu cho bảng `products`
--

INSERT INTO `products` (`product_id`, `product_name`, `slug`, `category_id`, `short_description`, `description`, `material`, `list_price`, `compare_at_price`, `cost_price`, `stock`, `status`, `is_active`, `created_at`, `updated_at`, `is_featured`) VALUES
(1, 'BEIGE WOOL BLEND CARGO PANTS', 'beige-wool-blend-cargo-pants', 6, 'Relaxed-fit cargo pants with elasticated waistband', 'Relaxed-fit cargo pants with an elasticated waistband and two side cargo pockets.', '83% Tencel, 15% Wool, 2% Spandex', 850000.00, 900000.00, 400000.00, 40, 'active', 1, '2025-08-12 13:24:43', '2025-08-14 15:21:28', 0),
(2, 'BLACK DIAGONAL PLEAT WOOL-VISCOSE TROUSERS', 'black-diagonal-pleat-wool-viscose-trousers', 6, 'Diagonal pleat trousers with premium wool-viscose blend', 'Diagonal Pleat Wool Viscose Trousers provides a fresh, refined look with its unique diagonal pleats.', '20% Wool, 30% Viscose, 49% Rayon, 1% Elastane', 750000.00, 850000.00, 350000.00, 48, 'active', 1, '2025-08-12 13:24:43', '2025-10-28 21:28:25', 0),
(3, 'Quần rộng có màu đen-giằng sạch', 'qu-n-r-ng-c-m-u-en-gi-ng-s-ch-1', 6, 'Quần dài với dây thắt lưng tích hợp sạch', 'Quần chân rộng thư giãn với một nếp gấp trước.', '83% Tencel, chỉ 15%, 2% spandex', 720000.00, 800000.00, 320000.00, 40, 'active', 1, '2025-08-12 13:24:43', '2025-08-28 16:06:15', 0),
(4, 'Mặt đen nếp gấp quần jean chân rộng', 'm-t-en-n-p-g-p-qu-n-jean-ch-n-r-ng-1', 6, 'Quần jean chân rộng lấy cảm hứng từ thập niên 90 với nếp gấp bên', 'Phản ánh về thời trang thập niên 90, thiết kế quần jean chân rộng làm nổi bật thắt lưng.', 'Trọng lượng nặng denim ~ 14oz', 650000.00, 750000.00, 300000.00, 40, 'active', 1, '2025-08-12 13:24:43', '2025-08-28 16:06:15', 0),
(5, 'BROWN DIAGONAL PLEAT WOOL-VISCOSE TROUSERS', 'brown-diagonal-pleat-wool-viscose-trousers', 6, 'Brown diagonal pleat trousers with wool-viscose blend', 'Diagonal Pleat Wool Viscose Trousers in brown color.', '20% Wool, 30% Viscose, 49% Rayon, 1% Elastane', 750000.00, 850000.00, 350000.00, 40, 'active', 1, '2025-08-12 13:24:43', '2025-08-14 15:21:28', 0),
(6, 'Quần len viscose xếp nếp màu đỏ tía', 'qu-n-len-viscose-x-p-n-p-m-u-t-a-1', 6, 'Quần xếp nếp màu đỏ tía với hình bóng chân rộng thanh lịch', 'Pleat Wool Viscose quần có màu đỏ tía.', '20% trong số đó, 30% trong số các viscos, 49% tia, 1%.', 780000.00, 880000.00, 370000.00, 40, 'active', 1, '2025-08-12 13:24:43', '2025-08-28 16:06:18', 0),
(7, 'Quần len viscose xếp nếp chéo chéo màu xám', 'qu-n-len-viscose-x-p-n-p-ch-o-ch-o-m-u-x-m-1', 6, 'Quần xếp nếp chéo màu xám với vẻ ngoài tinh tế', 'Quần viscose len chéo chéo có màu xám.', '20% trong số đó, 30% trong số các viscos, 49% tia, 1%.', 750000.00, 850000.00, 350000.00, 40, 'active', 1, '2025-08-12 13:24:43', '2025-08-28 16:06:20', 0),
(8, 'BLACK KHAKI POLOSHIRT', 'black-khaki-poloshirt', 2, 'Relaxed-fit polo shirt with premium fabric blend', 'Relaxed-fit polo shirt with premium fabric blend.', '83% Tencel, 15% Wool, 2% Spandex', 590000.00, 680000.00, 280000.00, 40, 'active', 1, '2025-08-12 13:24:43', '2025-08-14 15:21:28', 0),
(9, 'Đen đan một nửa zip polo', 'en-an-m-t-n-a-zip-polo-1', 2, 'Polo nửa zip thoải mái với cấu trúc đan', 'Thoải mái nửa zip polo với xây dựng đan và phù hợp thoải mái.', '83% Tencel, chỉ 15%, 2% spandex', 620000.00, 720000.00, 290000.00, 40, 'active', 1, '2025-08-12 13:24:43', '2025-08-28 16:06:22', 0),
(10, 'Bể đan đen', 'b-an-en-1', 2, 'Bể đan phù hợp thường xuyên với tỷ lệ cổ điển', 'Một top bể đan phù hợp thường xuyên với tỷ lệ cổ điển.', '70% cotton, chất xơ tổng hợp cao cấp 30%', 520000.00, 600000.00, 240000.00, 40, 'active', 1, '2025-08-12 13:24:43', '2025-08-28 16:06:23', 0),
(11, 'Bộ đồ cotton màu nâu', 'b-cotton-m-u-n-u-1', 2, 'Bộ đồ lót cotton cao cấp với sự phù hợp thoải mái', 'Bộ đồ cotton cao cấp với sự phù hợp thoải mái.', '100% bông', 550000.00, 650000.00, 250000.00, 40, 'active', 1, '2025-08-12 13:24:43', '2025-08-28 16:06:23', 0),
(12, 'Polystyrene kaki nâu', 'polystyrene-kaki-n-u-1', 2, 'Thiết kế polo cổ điển với vật liệu sáng tạo', 'Thiết kế polo cổ điển với vật liệu sáng tạo.', '70% cotton, 30% polyester', 590000.00, 680000.00, 280000.00, 40, 'active', 1, '2025-08-12 13:24:43', '2025-08-28 16:06:24', 0),
(13, 'Cotton Turtleneck Top', 'cotton-turtleneck-top', 2, 'Cotton Terleneck cao cấp với sự phù hợp thoải mái', 'Cotton Terleneck cao cấp với sự phù hợp thoải mái.', '100% bông', 560000.00, 650000.00, 260000.00, 40, 'active', 1, '2025-08-12 13:24:43', '2025-08-28 16:06:26', 0),
(14, 'Kem bông Bodysuit', 'kem-b-ng-bodysuit-1', 2, 'Bộ đồ lót cotton kem thanh lịch', 'Bodysuit cotton Kem thanh lịch với các tùy chọn kiểu dáng đa năng.', '100% bông', 550000.00, 650000.00, 250000.00, 40, 'active', 1, '2025-08-12 13:24:43', '2025-08-28 16:06:26', 0),
(15, 'Maroon khaki poloshirt', 'maroon-khaki-poloshirt', 2, 'Thiết kế polo cổ điển với màu sắc maroon', 'Thiết kế polo cổ điển trong màu maroon.', '70% cotton, 30% polyester', 590000.00, 680000.00, 280000.00, 40, 'active', 1, '2025-08-12 13:24:43', '2025-08-28 16:06:28', 0),
(16, 'Tank top', 'tank-top', 2, 'Bể chứa hai tầng phù hợp thường xuyên với vải cao cấp', 'Một top tank có gân phù hợp thường xuyên với hỗn hợp vải cao cấp.', '70% cotton, chất xơ tổng hợp cao cấp 30%', 510000.00, 590000.00, 230000.00, 40, 'active', 1, '2025-08-12 13:24:43', '2025-08-28 16:06:30', 0),
(17, 'Áo thêu diều', 'o-th-u-di-u-1', 3, 'Áo sơ mi thêu tay với thiết kế diều phức tạp', 'Lấy cảm hứng từ những hình tượng trưng vui tươi và diều tăng vọt trong gió.', '70% cotton, 30% poplin', 680000.00, 780000.00, 320000.00, 40, 'active', 1, '2025-08-12 13:24:43', '2025-08-28 16:06:31', 0),
(18, 'Áo sơ mi dài tay màu đen', 'o-s-mi-d-i-tay-m-u-en-1', 3, 'Áo sơ mi tay dài bằng sợi tre cao cấp', 'Áo tay áo dài tay cao cấp.', 'Hỗn hợp sợi tre', 630000.00, 720000.00, 300000.00, 40, 'active', 1, '2025-08-12 13:24:43', '2025-08-28 16:06:32', 0),
(19, 'Áo tay áo ngắn bằng tre đen', 'o-tay-o-ng-n-b-ng-tre-en-1', 3, 'Áo tay áo ngắn bằng sợi tre cao cấp', 'Áo tay áo ngắn bằng sợi tre cao cấp.', 'Hỗn hợp sợi tre', 580000.00, 670000.00, 270000.00, 40, 'active', 1, '2025-08-12 13:24:43', '2025-08-28 16:06:33', 0),
(20, 'Áo không tay đen', 'o-kh-ng-tay-en-1', 3, 'Áo sơ mi không tay với cổ áo có cấu trúc', 'Một chiếc áo không tay, thoải mái phù hợp với cổ áo có cấu trúc.', '67% cotton, 31% polyester, 2% spandex', 540000.00, 620000.00, 250000.00, 40, 'active', 1, '2025-08-12 13:24:43', '2025-08-28 16:06:35', 0),
(21, 'Áo sơ mi ngắn tay bằng gạch', 'o-s-mi-ng-n-tay-b-ng-g-ch-1', 3, 'Áo sơ mi tre có màu gạch', 'Áo tay tay ngắn bằng tre màu bằng gạch.', 'Hỗn hợp sợi tre', 580000.00, 670000.00, 270000.00, 40, 'active', 1, '2025-08-12 13:24:43', '2025-08-28 16:06:35', 0),
(22, 'Áo sơ mi thắt lưng', 'o-s-mi-th-t-l-ng-1', 3, 'Áo sơ mi thanh lịch với chi tiết thắt lưng cà vạt', 'Áo sơ mi thanh lịch với chi tiết thắt lưng cà vạt.', 'Hỗn hợp bông', 570000.00, 650000.00, 260000.00, 40, 'active', 1, '2025-08-12 13:24:43', '2025-08-28 16:06:37', 0),
(23, 'BLACK KHAKI WOOL BOMBER JACKET', 'black-khaki-wool-bomber-jacket', 4, 'Premium wool bomber with contemporary styling', 'Premium wool bomber jacket with contemporary styling.', 'Wool blend', 890000.00, 990000.00, 420000.00, 40, 'active', 1, '2025-08-12 13:24:43', '2025-08-14 15:21:28', 0),
(24, 'Áo khoác len khaki màu be', 'o-kho-c-len-khaki-m-u-be-1', 4, 'Áo khoác safari với chức năng và phong cách cổ điển', 'Áo khoác safari cho mùa đông mùa đông.', 'Len 20%, 80% Rayon', 850000.00, 950000.00, 400000.00, 40, 'active', 1, '2025-08-12 13:24:43', '2025-08-28 16:06:41', 0),
(25, 'Đen đuôi kaki len trenchcoat', 'en-u-i-kaki-len-trenchcoat-1', 4, 'Áo khoác rãnh được thiết kế bằng len đen', 'Áo khoác rãnh được thiết kế bằng màu đen.', 'Hỗn hợp len', 950000.00, 1050000.00, 450000.00, 40, 'active', 1, '2025-08-12 13:24:43', '2025-08-28 16:06:42', 0),
(26, 'Áo khoác len đuôi nâu', 'o-kho-c-len-u-i-n-u-1', 4, 'Bộ lông màu nâu thanh lịch với phù hợp phù hợp', 'Áo khoác màu nâu thanh lịch.', 'Hỗn hợp len', 950000.00, 1050000.00, 450000.00, 40, 'active', 1, '2025-08-12 13:24:43', '2025-08-28 16:06:42', 0),
(27, 'Cổ điển bằng len kaki trenchcoat', 'c-i-n-b-ng-len-kaki-trenchcoat-1', 4, 'Áo choàng cổ điển với các tính năng truyền thống', 'Bộ lông cổ điển giữ lại tất cả các tính năng truyền thống.', 'Len 30%, 70% bông', 820000.00, 920000.00, 380000.00, 40, 'active', 1, '2025-08-12 13:24:43', '2025-08-28 16:06:43', 0),
(28, 'Kem áo khoác bỏ túi thư giãn', 'kem-o-kho-c-b-t-i-th-gi-n-1', 4, 'Áo khoác bị cắt trong hình bóng thư giãn', 'Một chiếc áo khoác cắt trong hình bóng thư giãn.', '20% trong số đó, 30% trong số các viscos, 49% tia, 1%.', 750000.00, 850000.00, 350000.00, 40, 'active', 1, '2025-08-12 13:24:43', '2025-08-28 16:06:43', 0),
(29, 'Túi lấy da lộn màu đen', 't-i-l-y-da-l-n-m-u-en-1', 7, 'Túi vai có cấu trúc mềm cỡ trung bình', 'Một túi vai có cấu trúc trung bình, có cấu trúc mềm được thiết kế để vội vàng hàng ngày.', 'Khẩu vải pha trộn chống nhăn-380gsm', 690000.00, 780000.00, 320000.00, 40, 'active', 1, '2025-08-12 13:24:43', '2025-08-28 16:06:44', 0),
(30, 'Kem Daily Túi trôi dạt', 'kem-daily-t-i-tr-i-d-t-1', 7, 'Túi tote vai từ da giả chất lượng cao', 'Túi tote vai được chế tạo từ da giả chất lượng cao.', 'Da giả', 720000.00, 820000.00, 340000.00, 40, 'active', 1, '2025-08-12 13:24:43', '2025-08-28 16:06:44', 0),
(31, 'Có túi crossbody', 'c-t-i-crossbody-1', 7, 'Túi crossbody sành điệu để sử dụng hàng ngày', 'Túi Crossbody sành điệu hoàn hảo để sử dụng hàng ngày.', 'Da tổng hợp cao cấp', 550000.00, 650000.00, 250000.00, 40, 'active', 1, '2025-08-12 13:24:43', '2025-08-28 16:06:45', 0),
(60, 'sản phẩm test', 's-n-ph-m-test', 3, 'test', 'test', '83% Tencel, 15% Wool, 2% Spandex', 10000.00, 0.00, 0.00, 999996, 'active', 1, '2025-12-06 12:42:11', '2025-12-06 12:42:11', 0);

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `product_images`
--

CREATE TABLE `product_images` (
  `image_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `variant_id` int(11) DEFAULT NULL,
  `url` varchar(500) NOT NULL,
  `media_public_id` varchar(255) DEFAULT NULL,
  `position` int(11) NOT NULL DEFAULT 0,
  `alt_text` varchar(255) DEFAULT NULL,
  `image_type` enum('thumbnail','gallery','model') DEFAULT 'gallery',
  `is_main` tinyint(1) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `main_key` varchar(64) GENERATED ALWAYS AS (case when `is_main` then concat(coalesce(concat('p:',`product_id`),''),':',coalesce(concat('v:',`variant_id`),'none')) else NULL end) STORED
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Đang đổ dữ liệu cho bảng `product_images`
--

INSERT INTO `product_images` (`image_id`, `product_id`, `variant_id`, `url`, `media_public_id`, `position`, `alt_text`, `image_type`, `is_main`, `created_at`, `updated_at`) VALUES
(1, 1, NULL, 'https://res.cloudinary.com/dwyll0fbp/image/upload/v1754969381/36_6d7b2a0bc09a4bc6a1dfd6efcdd02d64_master_nsbw4b.webp', NULL, 0, NULL, 'thumbnail', 1, '2025-08-12 13:30:39', '2025-08-12 13:30:39'),
(2, 1, NULL, 'https://images.unsplash.com/photo-1617137984095-74e4e5e3613f?w=600', NULL, 3, NULL, '', 0, '2025-08-12 13:30:39', '2025-08-13 16:21:09'),
(3, 1, NULL, 'https://res.cloudinary.com/dwyll0fbp/image/upload/v1754969439/2_umcex7.webp', NULL, 2, NULL, 'gallery', 0, '2025-08-12 13:30:39', '2025-08-12 13:30:39'),
(4, 2, NULL, 'https://res.cloudinary.com/dwyll0fbp/image/upload/v1754969711/1_kpjrjs.webp', NULL, 0, NULL, 'thumbnail', 1, '2025-08-12 13:30:39', '2025-08-12 13:30:39'),
(5, 2, NULL, 'https://res.cloudinary.com/dwyll0fbp/image/upload/v1754969729/2_ioi4h6.webp', NULL, 1, NULL, 'gallery', 0, '2025-08-12 13:30:39', '2025-08-12 13:30:39'),
(6, 2, NULL, 'https://res.cloudinary.com/dwyll0fbp/image/upload/v1754969729/3_fyppnk.webp', NULL, 2, NULL, 'gallery', 0, '2025-08-12 13:30:39', '2025-08-12 13:30:39'),
(7, 2, NULL, 'https://res.cloudinary.com/dwyll0fbp/image/upload/v1754969730/4_gtdkfr.webp', NULL, 3, NULL, 'gallery', 0, '2025-08-12 13:30:39', '2025-08-12 13:30:39'),
(12, 5, NULL, 'https://res.cloudinary.com/dwyll0fbp/image/upload/v1754970095/1_wybgtz.webp', NULL, 0, NULL, 'thumbnail', 1, '2025-08-12 13:30:39', '2025-08-12 13:30:39'),
(13, 5, NULL, 'https://res.cloudinary.com/dwyll0fbp/image/upload/v1754970097/2_pxhusv.webp', NULL, 1, NULL, 'gallery', 0, '2025-08-12 13:30:39', '2025-08-12 13:30:39'),
(14, 5, NULL, 'https://res.cloudinary.com/dwyll0fbp/image/upload/v1754970097/3_jln8vz.webp', NULL, 2, NULL, 'gallery', 0, '2025-08-12 13:30:39', '2025-08-12 13:30:39'),
(15, 5, NULL, 'https://res.cloudinary.com/dwyll0fbp/image/upload/v1754970098/4_p0yght.webp', NULL, 3, NULL, 'gallery', 0, '2025-08-12 13:30:39', '2025-08-12 13:30:39'),
(23, 8, NULL, 'https://res.cloudinary.com/dwyll0fbp/image/upload/v1754970226/1_fbsufs.webp', NULL, 0, NULL, 'thumbnail', 1, '2025-08-12 13:30:39', '2025-08-12 13:30:39'),
(24, 8, NULL, 'https://res.cloudinary.com/dwyll0fbp/image/upload/v1754970227/2_peabv2.webp', NULL, 1, NULL, 'gallery', 0, '2025-08-12 13:30:39', '2025-08-12 13:30:39'),
(57, 23, NULL, 'https://res.cloudinary.com/dwyll0fbp/image/upload/v1754970672/1_bqlxqy.webp', NULL, 0, NULL, 'thumbnail', 1, '2025-08-12 13:30:39', '2025-08-12 13:30:39'),
(58, 23, NULL, 'https://res.cloudinary.com/dwyll0fbp/image/upload/v1754970674/2_ll5l7u.webp', NULL, 1, NULL, 'gallery', 0, '2025-08-12 13:30:39', '2025-08-12 13:30:39'),
(59, 23, NULL, 'https://res.cloudinary.com/dwyll0fbp/image/upload/v1754970676/3_kmwoc9.webp', NULL, 2, NULL, 'gallery', 0, '2025-08-12 13:30:39', '2025-08-12 13:30:39'),
(949, 3, NULL, 'https://res.cloudinary.com/dwyll0fbp/image/upload/v1754970002/38_7057adca26244308b63aeaaf76e5cb9e_master_g2we1t.webp', NULL, 0, NULL, 'thumbnail', 1, '2025-08-28 16:06:15', '2025-08-28 16:06:15'),
(950, 3, NULL, 'https://res.cloudinary.com/dwyll0fbp/image/upload/v1754970004/2_elo8h8.webp', NULL, 1, NULL, 'gallery', 0, '2025-08-28 16:06:15', '2025-08-28 16:06:15'),
(951, 4, NULL, 'https://res.cloudinary.com/dwyll0fbp/image/upload/v1754970029/1_sxqfmm.webp', NULL, 0, NULL, 'thumbnail', 1, '2025-08-28 16:06:15', '2025-08-28 16:06:15'),
(952, 4, NULL, 'https://res.cloudinary.com/dwyll0fbp/image/upload/v1754970029/2_z2nuqi.webp', NULL, 1, NULL, 'gallery', 0, '2025-08-28 16:06:15', '2025-08-28 16:06:15'),
(953, 6, NULL, 'https://res.cloudinary.com/dwyll0fbp/image/upload/v1754970140/1_l2wver.webp', NULL, 0, NULL, 'thumbnail', 1, '2025-08-28 16:06:18', '2025-08-28 16:06:18'),
(954, 6, NULL, 'https://res.cloudinary.com/dwyll0fbp/image/upload/v1754970137/2_jyw3th.webp', NULL, 1, NULL, 'gallery', 0, '2025-08-28 16:06:18', '2025-08-28 16:06:18'),
(955, 6, NULL, 'https://res.cloudinary.com/dwyll0fbp/image/upload/v1754970145/3_hm53mz.webp', NULL, 2, NULL, 'gallery', 0, '2025-08-28 16:06:18', '2025-08-28 16:06:18'),
(956, 6, NULL, 'https://res.cloudinary.com/dwyll0fbp/image/upload/v1754970145/4_d2fvdi.webp', NULL, 3, NULL, 'gallery', 0, '2025-08-28 16:06:18', '2025-08-28 16:06:18'),
(957, 7, NULL, 'https://res.cloudinary.com/dwyll0fbp/image/upload/v1754970176/1_qinfuw.webp', NULL, 0, NULL, 'thumbnail', 1, '2025-08-28 16:06:20', '2025-08-28 16:06:20'),
(958, 7, NULL, 'https://res.cloudinary.com/dwyll0fbp/image/upload/v1754970175/2_wym9ag.webp', NULL, 1, NULL, 'gallery', 0, '2025-08-28 16:06:20', '2025-08-28 16:06:20'),
(959, 7, NULL, 'https://res.cloudinary.com/dwyll0fbp/image/upload/v1754970177/3_twcrta.webp', NULL, 2, NULL, 'gallery', 0, '2025-08-28 16:06:20', '2025-08-28 16:06:20'),
(960, 9, NULL, 'https://res.cloudinary.com/dwyll0fbp/image/upload/v1754970246/1_m6igbr.jpg', NULL, 0, NULL, 'thumbnail', 1, '2025-08-28 16:06:22', '2025-08-28 16:06:22'),
(961, 9, NULL, 'https://res.cloudinary.com/dwyll0fbp/image/upload/v1754970247/2_v5cmn1.webp', NULL, 1, NULL, 'gallery', 0, '2025-08-28 16:06:22', '2025-08-28 16:06:22'),
(962, 9, NULL, 'https://res.cloudinary.com/dwyll0fbp/image/upload/v1754970247/3_hp9i3z.webp', NULL, 2, NULL, 'gallery', 0, '2025-08-28 16:06:22', '2025-08-28 16:06:22'),
(963, 10, NULL, 'https://res.cloudinary.com/dwyll0fbp/image/upload/v1754970274/1_ufbdps.jpg', NULL, 0, NULL, 'thumbnail', 1, '2025-08-28 16:06:23', '2025-08-28 16:06:23'),
(964, 10, NULL, 'https://res.cloudinary.com/dwyll0fbp/image/upload/v1754970275/2_mtoi2q.webp', NULL, 1, NULL, 'gallery', 0, '2025-08-28 16:06:23', '2025-08-28 16:06:23'),
(965, 10, NULL, 'https://res.cloudinary.com/dwyll0fbp/image/upload/v1754970276/3_sbm33w.webp', NULL, 2, NULL, 'gallery', 0, '2025-08-28 16:06:23', '2025-08-28 16:06:23'),
(966, 11, NULL, 'https://res.cloudinary.com/dwyll0fbp/image/upload/v1754970318/1_lbjwa9.webp', NULL, 0, NULL, 'thumbnail', 1, '2025-08-28 16:06:23', '2025-08-28 16:06:23'),
(967, 11, NULL, 'https://res.cloudinary.com/dwyll0fbp/image/upload/v1754970318/2_iatvs2.webp', NULL, 1, NULL, 'gallery', 0, '2025-08-28 16:06:23', '2025-08-28 16:06:23'),
(968, 12, NULL, 'https://res.cloudinary.com/dwyll0fbp/image/upload/v1754970338/1_dkahuo.webp', NULL, 0, NULL, 'thumbnail', 1, '2025-08-28 16:06:24', '2025-08-28 16:06:24'),
(969, 12, NULL, 'https://res.cloudinary.com/dwyll0fbp/image/upload/v1754970340/2_pkqbyr.webp', NULL, 1, NULL, 'gallery', 0, '2025-08-28 16:06:24', '2025-08-28 16:06:24'),
(970, 13, NULL, 'https://res.cloudinary.com/dwyll0fbp/image/upload/v1754970359/1_mof8yh.webp', NULL, 0, NULL, 'thumbnail', 1, '2025-08-28 16:06:26', '2025-08-28 16:06:26'),
(971, 13, NULL, 'https://res.cloudinary.com/dwyll0fbp/image/upload/v1754970360/2_qlaof0.webp', NULL, 1, NULL, 'gallery', 0, '2025-08-28 16:06:26', '2025-08-28 16:06:26'),
(972, 14, NULL, 'https://res.cloudinary.com/dwyll0fbp/image/upload/v1754970376/1_cpjhgr.webp', NULL, 0, NULL, 'thumbnail', 1, '2025-08-28 16:06:26', '2025-08-28 16:06:26'),
(973, 14, NULL, 'https://res.cloudinary.com/dwyll0fbp/image/upload/v1754970377/2_gemsk1.webp', NULL, 1, NULL, 'gallery', 0, '2025-08-28 16:06:26', '2025-08-28 16:06:26'),
(974, 14, NULL, 'https://res.cloudinary.com/dwyll0fbp/image/upload/v1754970379/3_rqtwgv.webp', NULL, 2, NULL, 'gallery', 0, '2025-08-28 16:06:26', '2025-08-28 16:06:26'),
(975, 14, NULL, 'https://res.cloudinary.com/dwyll0fbp/image/upload/v1754970379/4_eq8p0a.webp', NULL, 3, NULL, 'gallery', 0, '2025-08-28 16:06:26', '2025-08-28 16:06:26'),
(976, 15, NULL, 'https://res.cloudinary.com/dwyll0fbp/image/upload/v1754970495/1_bkxyej.webp', NULL, 0, NULL, 'thumbnail', 1, '2025-08-28 16:06:28', '2025-08-28 16:06:28'),
(977, 15, NULL, 'https://res.cloudinary.com/dwyll0fbp/image/upload/v1754970500/2_scvner.jpg', NULL, 1, NULL, 'gallery', 0, '2025-08-28 16:06:28', '2025-08-28 16:06:28'),
(978, 16, NULL, 'https://res.cloudinary.com/dwyll0fbp/image/upload/v1754970517/1_c0xmfz.webp', NULL, 0, NULL, 'thumbnail', 1, '2025-08-28 16:06:30', '2025-08-28 16:06:30'),
(979, 16, NULL, 'https://res.cloudinary.com/dwyll0fbp/image/upload/v1754970518/2_u2sauk.webp', NULL, 1, NULL, 'gallery', 0, '2025-08-28 16:06:30', '2025-08-28 16:06:30'),
(980, 17, NULL, 'https://res.cloudinary.com/dwyll0fbp/image/upload/v1754970541/1_cgtyps.webp', NULL, 0, NULL, 'thumbnail', 1, '2025-08-28 16:06:31', '2025-08-28 16:06:31'),
(981, 17, NULL, 'https://res.cloudinary.com/dwyll0fbp/image/upload/v1754970542/2_y3tpdx.webp', NULL, 1, NULL, 'gallery', 0, '2025-08-28 16:06:31', '2025-08-28 16:06:31'),
(982, 18, NULL, 'https://res.cloudinary.com/dwyll0fbp/image/upload/v1754970565/1_vdhhrn.webp', NULL, 0, NULL, 'thumbnail', 1, '2025-08-28 16:06:32', '2025-08-28 16:06:32'),
(983, 18, NULL, 'https://res.cloudinary.com/dwyll0fbp/image/upload/v1754970567/2_xkhkq2.webp', NULL, 1, NULL, 'gallery', 0, '2025-08-28 16:06:32', '2025-08-28 16:06:32'),
(984, 19, NULL, 'https://res.cloudinary.com/dwyll0fbp/image/upload/v1754970583/1_kiag7i.webp', NULL, 0, NULL, 'thumbnail', 1, '2025-08-28 16:06:33', '2025-08-28 16:06:33'),
(985, 19, NULL, 'https://res.cloudinary.com/dwyll0fbp/image/upload/v1754970584/2_wriaum.webp', NULL, 1, NULL, 'gallery', 0, '2025-08-28 16:06:33', '2025-08-28 16:06:33'),
(986, 20, NULL, 'https://res.cloudinary.com/dwyll0fbp/image/upload/v1754970598/1_eplk9r.webp', NULL, 0, NULL, 'thumbnail', 1, '2025-08-28 16:06:35', '2025-08-28 16:06:35'),
(987, 20, NULL, 'https://res.cloudinary.com/dwyll0fbp/image/upload/v1754970599/2_vdmlqj.webp', NULL, 1, NULL, 'gallery', 0, '2025-08-28 16:06:35', '2025-08-28 16:06:35'),
(988, 21, NULL, 'https://res.cloudinary.com/dwyll0fbp/image/upload/v1754970633/1_jtzmh0.webp', NULL, 0, NULL, 'thumbnail', 1, '2025-08-28 16:06:35', '2025-08-28 16:06:35'),
(989, 21, NULL, 'https://res.cloudinary.com/dwyll0fbp/image/upload/v1754970634/2_kpijpl.webp', NULL, 1, NULL, 'gallery', 0, '2025-08-28 16:06:35', '2025-08-28 16:06:35'),
(990, 22, NULL, 'https://res.cloudinary.com/dwyll0fbp/image/upload/v1754970649/1_uua6av.webp', NULL, 0, NULL, 'thumbnail', 1, '2025-08-28 16:06:37', '2025-08-28 16:06:37'),
(991, 22, NULL, 'https://res.cloudinary.com/dwyll0fbp/image/upload/v1754970650/2_myqjy2.webp', NULL, 1, NULL, 'gallery', 0, '2025-08-28 16:06:37', '2025-08-28 16:06:37'),
(992, 24, NULL, 'https://res.cloudinary.com/dwyll0fbp/image/upload/v1754970700/1_qos7bo.webp', NULL, 0, NULL, 'thumbnail', 1, '2025-08-28 16:06:41', '2025-08-28 16:06:41'),
(993, 24, NULL, 'https://res.cloudinary.com/dwyll0fbp/image/upload/v1754970701/2_fj2r6k.webp', NULL, 1, NULL, 'gallery', 0, '2025-08-28 16:06:41', '2025-08-28 16:06:41'),
(994, 25, NULL, 'https://res.cloudinary.com/dwyll0fbp/image/upload/v1754970719/1_ujmwpo.webp', NULL, 0, NULL, 'thumbnail', 1, '2025-08-28 16:06:42', '2025-08-28 16:06:42'),
(995, 25, NULL, 'https://res.cloudinary.com/dwyll0fbp/image/upload/v1754970721/2_cinifl.webp', NULL, 1, NULL, 'gallery', 0, '2025-08-28 16:06:42', '2025-08-28 16:06:42'),
(996, 26, NULL, 'https://res.cloudinary.com/dwyll0fbp/image/upload/v1754970746/1_njngca.webp', NULL, 0, NULL, 'thumbnail', 1, '2025-08-28 16:06:42', '2025-08-28 16:06:42'),
(997, 26, NULL, 'https://res.cloudinary.com/dwyll0fbp/image/upload/v1754970750/3_rabxsq.webp', NULL, 1, NULL, 'gallery', 0, '2025-08-28 16:06:42', '2025-08-28 16:06:42'),
(998, 26, NULL, 'https://res.cloudinary.com/dwyll0fbp/image/upload/v1754970748/2_waylcm.webp', NULL, 2, NULL, 'gallery', 0, '2025-08-28 16:06:42', '2025-08-28 16:06:42'),
(999, 26, NULL, 'https://res.cloudinary.com/dwyll0fbp/image/upload/v1754970752/4_e07gz6.webp', NULL, 3, NULL, 'gallery', 0, '2025-08-28 16:06:42', '2025-08-28 16:06:42'),
(1000, 26, NULL, 'https://res.cloudinary.com/dwyll0fbp/image/upload/v1754970754/5_nvvxzx.webp', NULL, 4, NULL, 'gallery', 0, '2025-08-28 16:06:42', '2025-08-28 16:06:42'),
(1001, 27, NULL, 'https://res.cloudinary.com/dwyll0fbp/image/upload/v1754970776/1_e0amn9.webp', NULL, 0, NULL, 'thumbnail', 1, '2025-08-28 16:06:43', '2025-08-28 16:06:43'),
(1002, 27, NULL, 'https://res.cloudinary.com/dwyll0fbp/image/upload/v1754970778/2_qfjzpm.webp', NULL, 1, NULL, 'gallery', 0, '2025-08-28 16:06:43', '2025-08-28 16:06:43'),
(1003, 28, NULL, 'https://res.cloudinary.com/dwyll0fbp/image/upload/v1754970793/1_ls80zg.webp', NULL, 0, NULL, 'thumbnail', 1, '2025-08-28 16:06:43', '2025-08-28 16:06:43'),
(1004, 28, NULL, 'https://res.cloudinary.com/dwyll0fbp/image/upload/v1754970795/2_yl56ma.webp', NULL, 1, NULL, 'gallery', 0, '2025-08-28 16:06:43', '2025-08-28 16:06:43'),
(1005, 29, NULL, 'https://res.cloudinary.com/dwyll0fbp/image/upload/v1754970818/1_cgyjzi.webp', NULL, 0, NULL, 'thumbnail', 1, '2025-08-28 16:06:44', '2025-08-28 16:06:44'),
(1006, 29, NULL, 'https://res.cloudinary.com/dwyll0fbp/image/upload/v1754970830/2_ooy5fk.webp', NULL, 1, NULL, 'gallery', 0, '2025-08-28 16:06:44', '2025-08-28 16:06:44'),
(1007, 29, NULL, 'https://res.cloudinary.com/dwyll0fbp/image/upload/v1754970830/3_bb3gk4.webp', NULL, 2, NULL, 'gallery', 0, '2025-08-28 16:06:44', '2025-08-28 16:06:44'),
(1008, 29, NULL, 'https://res.cloudinary.com/dwyll0fbp/image/upload/v1754970834/5_oddqky.webp', NULL, 3, NULL, 'gallery', 0, '2025-08-28 16:06:44', '2025-08-28 16:06:44'),
(1009, 29, NULL, 'https://res.cloudinary.com/dwyll0fbp/image/upload/v1754970831/4_e7av5w.webp', NULL, 4, NULL, 'gallery', 0, '2025-08-28 16:06:44', '2025-08-28 16:06:44'),
(1010, 30, NULL, 'https://res.cloudinary.com/dwyll0fbp/image/upload/v1754970887/1_t61ck9.webp', NULL, 0, NULL, 'thumbnail', 1, '2025-08-28 16:06:44', '2025-08-28 16:06:44'),
(1011, 30, NULL, 'https://res.cloudinary.com/dwyll0fbp/image/upload/v1754970889/2_gx7o36.webp', NULL, 1, NULL, 'gallery', 0, '2025-08-28 16:06:44', '2025-08-28 16:06:44'),
(1012, 30, NULL, 'https://res.cloudinary.com/dwyll0fbp/image/upload/v1754970891/3_ilgq09.webp', NULL, 2, NULL, 'gallery', 0, '2025-08-28 16:06:44', '2025-08-28 16:06:44'),
(1013, 30, NULL, 'https://res.cloudinary.com/dwyll0fbp/image/upload/v1754970893/4_li6xl3.webp', NULL, 3, NULL, 'gallery', 0, '2025-08-28 16:06:44', '2025-08-28 16:06:44'),
(1014, 31, NULL, 'https://res.cloudinary.com/dwyll0fbp/image/upload/v1754970921/1_x96a7z.webp', NULL, 0, NULL, 'thumbnail', 1, '2025-08-28 16:06:45', '2025-08-28 16:06:45'),
(1015, 31, NULL, 'https://res.cloudinary.com/dwyll0fbp/image/upload/v1754970926/2_xhte3a.webp', NULL, 1, NULL, 'gallery', 0, '2025-08-28 16:06:45', '2025-08-28 16:06:45'),
(1016, 31, NULL, 'https://res.cloudinary.com/dwyll0fbp/image/upload/v1754970929/3_cbao8m.webp', NULL, 2, NULL, 'gallery', 0, '2025-08-28 16:06:45', '2025-08-28 16:06:45'),
(1020, 60, NULL, 'https://res.cloudinary.com/dknwpznzc/image/upload/v1764999733/shopswift/products/cloFA04_pif9cs.webp', 'shopswift/products/cloFA04_pif9cs', 0, 'sản phẩm test - Image 1', 'thumbnail', 1, '2025-12-06 12:42:13', '2025-12-06 12:42:13');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `product_variants`
--

CREATE TABLE `product_variants` (
  `variant_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `size_id` int(11) NOT NULL,
  `sku` varchar(50) DEFAULT NULL,
  `stock_quantity` int(11) NOT NULL COMMENT 'Tồn kho cho size này',
  `status` enum('in_stock','out_of_stock') NOT NULL DEFAULT 'in_stock',
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Đang đổ dữ liệu cho bảng `product_variants`
--

INSERT INTO `product_variants` (`variant_id`, `product_id`, `size_id`, `sku`, `stock_quantity`, `status`, `is_active`) VALUES
(1, 1, 1, 'BWBCP-S', 0, 'in_stock', 1),
(2, 1, 2, 'BWBCP-M', 12, 'in_stock', 1),
(3, 1, 3, 'BWBCP-L', 10, 'in_stock', 1),
(4, 1, 4, 'BWBCP-XL', 5, 'in_stock', 1),
(5, 2, 1, 'BDPT-S', 95, 'in_stock', 1),
(6, 2, 2, 'BDPT-M', 15, 'in_stock', 1),
(7, 2, 3, 'BDPT-L', 12, 'in_stock', 1),
(8, 2, 4, 'BDPT-XL', 11, 'in_stock', 1),
(17, 8, 1, 'BKP-S', 15, 'in_stock', 1),
(18, 8, 2, 'BKP-M', 20, 'in_stock', 1),
(19, 8, 3, 'BKP-L', 18, 'in_stock', 1),
(20, 8, 4, 'BKP-XL', 12, 'in_stock', 1),
(25, 23, 1, 'BKWBJ-S', 4, 'in_stock', 1),
(26, 23, 2, 'BKWBJ-M', 6, 'in_stock', 1),
(27, 23, 3, 'BKWBJ-L', 5, 'in_stock', 1),
(28, 23, 4, 'BKWBJ-XL', 3, 'in_stock', 1),
(33, 5, 1, 'BDPWVT-S', 5, 'in_stock', 1),
(34, 5, 2, 'BDPWVT-M', 8, 'in_stock', 1),
(35, 5, 3, 'BDPWVT-L', 6, 'in_stock', 1),
(36, 5, 4, 'BDPWVT-XL', 3, 'in_stock', 1),
(1445, 3, 1, 'BCWT-S', 6, 'in_stock', 1),
(1446, 3, 2, 'BCWT-M', 10, 'in_stock', 1),
(1447, 3, 3, 'BCWT-L', 8, 'in_stock', 1),
(1448, 3, 4, 'BCWT-XL', 4, 'in_stock', 1),
(1449, 4, 1, 'BSPWLJ-S', 12, 'in_stock', 1),
(1450, 4, 2, 'BSPWLJ-M', 18, 'in_stock', 1),
(1451, 4, 3, 'BSPWLJ-L', 15, 'in_stock', 1),
(1452, 4, 4, 'BSPWLJ-XL', 10, 'in_stock', 1),
(1453, 6, 1, 'BPWVT-S', 6, 'in_stock', 1),
(1454, 6, 2, 'BPWVT-M', 10, 'in_stock', 1),
(1455, 6, 3, 'BPWVT-L', 8, 'in_stock', 1),
(1456, 6, 4, 'BPWVT-XL', 4, 'in_stock', 1),
(1457, 7, 1, 'GDPWVT-S', 4, 'in_stock', 1),
(1458, 7, 2, 'GDPWVT-M', 6, 'in_stock', 1),
(1459, 7, 3, 'GDPWVT-L', 5, 'in_stock', 1),
(1460, 7, 4, 'GDPWVT-XL', 2, 'in_stock', 1),
(1461, 9, 1, 'BKHZP-S', 8, 'in_stock', 1),
(1462, 9, 2, 'BKHZP-M', 12, 'in_stock', 1),
(1463, 9, 3, 'BKHZP-L', 10, 'in_stock', 1),
(1464, 9, 4, 'BKHZP-XL', 6, 'in_stock', 1),
(1465, 10, 1, 'BKT-S', 12, 'in_stock', 1),
(1466, 10, 2, 'BKT-M', 18, 'in_stock', 1),
(1467, 10, 3, 'BKT-L', 15, 'in_stock', 1),
(1468, 10, 4, 'BKT-XL', 8, 'in_stock', 1),
(1469, 11, 1, 'BCB-S', 7, 'in_stock', 1),
(1470, 11, 2, 'BCB-M', 10, 'in_stock', 1),
(1471, 11, 3, 'BCB-L', 8, 'in_stock', 1),
(1472, 11, 4, 'BCB-XL', 5, 'in_stock', 1),
(1473, 12, 1, 'BKPS-S', 9, 'in_stock', 1),
(1474, 12, 2, 'BKPS-M', 14, 'in_stock', 1),
(1475, 12, 3, 'BKPS-L', 12, 'in_stock', 1),
(1476, 12, 4, 'BKPS-XL', 7, 'in_stock', 1),
(1477, 13, 1, 'CTT-S', 6, 'in_stock', 1),
(1478, 13, 2, 'CTT-M', 10, 'in_stock', 1),
(1479, 13, 3, 'CTT-L', 8, 'in_stock', 1),
(1480, 13, 4, 'CTT-XL', 4, 'in_stock', 1),
(1481, 14, 1, 'CCB-S', 8, 'in_stock', 1),
(1482, 14, 2, 'CCB-M', 12, 'in_stock', 1),
(1483, 14, 3, 'CCB-L', 10, 'in_stock', 1),
(1484, 14, 4, 'CCB-XL', 6, 'in_stock', 1),
(1485, 15, 1, 'MKPS-S', 8, 'in_stock', 1),
(1486, 15, 2, 'MKPS-M', 13, 'in_stock', 1),
(1487, 15, 3, 'MKPS-L', 11, 'in_stock', 1),
(1488, 15, 4, 'MKPS-XL', 6, 'in_stock', 1),
(1489, 16, 1, 'RTT-S', 11, 'in_stock', 1),
(1490, 16, 2, 'RTT-M', 16, 'in_stock', 1),
(1491, 16, 3, 'RTT-L', 14, 'in_stock', 1),
(1492, 16, 4, 'RTT-XL', 9, 'in_stock', 1),
(1493, 17, 1, 'KES-S', 6, 'in_stock', 1),
(1494, 17, 2, 'KES-M', 10, 'in_stock', 1),
(1495, 17, 3, 'KES-L', 8, 'in_stock', 1),
(1496, 17, 4, 'KES-XL', 4, 'in_stock', 1),
(1497, 18, 1, 'BBLSS-S', 7, 'in_stock', 1),
(1498, 18, 2, 'BBLSS-M', 11, 'in_stock', 1),
(1499, 18, 3, 'BBLSS-L', 9, 'in_stock', 1),
(1500, 18, 4, 'BBLSS-XL', 5, 'in_stock', 1),
(1501, 19, 1, 'BBSSS-S', 9, 'in_stock', 1),
(1502, 19, 2, 'BBSSS-M', 14, 'in_stock', 1),
(1503, 19, 3, 'BBSSS-L', 12, 'in_stock', 1),
(1504, 19, 4, 'BBSSS-XL', 7, 'in_stock', 1),
(1505, 20, 1, 'BSS-S', 8, 'in_stock', 1),
(1506, 20, 2, 'BSS-M', 12, 'in_stock', 1),
(1507, 20, 3, 'BSS-L', 10, 'in_stock', 1),
(1508, 20, 4, 'BSS-XL', 6, 'in_stock', 1),
(1509, 21, 1, 'BRSSS-S', 8, 'in_stock', 1),
(1510, 21, 2, 'BRSSS-M', 13, 'in_stock', 1),
(1511, 21, 3, 'BRSSS-L', 11, 'in_stock', 1),
(1512, 21, 4, 'BRSSS-XL', 6, 'in_stock', 1),
(1513, 22, 1, 'TWS-S', 6, 'in_stock', 1),
(1514, 22, 2, 'TWS-M', 10, 'in_stock', 1),
(1515, 22, 3, 'TWS-L', 8, 'in_stock', 1),
(1516, 22, 4, 'TWS-XL', 4, 'in_stock', 1),
(1517, 24, 1, 'BKWSJ-S', 5, 'in_stock', 1),
(1518, 24, 2, 'BKWSJ-M-UPDATED', 10, 'in_stock', 1),
(1519, 24, 4, 'BKWSJ-XL', 3, 'in_stock', 1),
(1520, 25, 1, 'BTKWT-S', 3, 'in_stock', 1),
(1521, 25, 2, 'BTKWT-M', 5, 'in_stock', 1),
(1522, 25, 3, 'BTKWT-L', 4, 'in_stock', 1),
(1523, 25, 4, 'BTKWT-XL', 2, 'in_stock', 1),
(1524, 26, 1, 'BTWTC-S', 3, 'in_stock', 1),
(1525, 26, 2, 'BTWTC-M', 4, 'in_stock', 1),
(1526, 26, 3, 'BTWTC-L', 3, 'in_stock', 1),
(1527, 26, 4, 'BTWTC-XL', 2, 'in_stock', 1),
(1528, 27, 1, 'CKWT-S', 4, 'in_stock', 1),
(1529, 27, 2, 'CKWT-M', 8, 'in_stock', 1),
(1530, 27, 3, 'CKWT-L', 6, 'in_stock', 1),
(1531, 27, 4, 'CKWT-XL', 2, 'in_stock', 1),
(1532, 28, 1, 'CRWPJ-S', 6, 'in_stock', 1),
(1533, 28, 2, 'CRWPJ-M', 9, 'in_stock', 1),
(1534, 28, 3, 'CRWPJ-L', 7, 'in_stock', 1),
(1535, 28, 4, 'CRWPJ-XL', 4, 'in_stock', 1),
(1536, 29, 1, 'BSGB-S', 6, 'in_stock', 1),
(1537, 29, 2, 'BSGB-M', 10, 'in_stock', 1),
(1538, 29, 3, 'BSGB-L', 8, 'in_stock', 1),
(1539, 29, 4, 'BSGB-XL', 5, 'in_stock', 1),
(1540, 30, 1, 'CDDB-S', 5, 'in_stock', 1),
(1541, 30, 2, 'CDDB-M', 8, 'in_stock', 1),
(1542, 30, 3, 'CDDB-L', 7, 'in_stock', 1),
(1543, 30, 4, 'CDDB-XL', 3, 'in_stock', 1),
(1544, 31, 1, 'HCCB-S', 9, 'in_stock', 1),
(1545, 31, 2, 'HCCB-M', 14, 'in_stock', 1),
(1546, 31, 3, 'HCCB-L', 12, 'in_stock', 1),
(1547, 31, 4, 'HCCB-XL', 7, 'in_stock', 1),
(1552, 60, 1, '1313414', 99952, 'in_stock', 1),
(1553, 60, 3, '1231', 100003, 'in_stock', 1),
(1554, 60, 2, '123', 100000, 'in_stock', 1);

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `purchase_items`
--

CREATE TABLE `purchase_items` (
  `item_id` int(11) NOT NULL,
  `receipt_id` int(11) NOT NULL,
  `variant_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `unit_price` decimal(10,2) NOT NULL,
  `subtotal` decimal(12,2) GENERATED ALWAYS AS (`quantity` * `unit_price`) STORED,
  `note` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Đang đổ dữ liệu cho bảng `purchase_items`
--

INSERT INTO `purchase_items` (`item_id`, `receipt_id`, `variant_id`, `quantity`, `unit_price`, `note`) VALUES
(1, 1, 1, 100, 150000.00, 'Áo thun trắng size M'),
(2, 1, 2, 80, 150000.00, 'Áo thun trắng size L'),
(3, 1, 3, 60, 150000.00, 'Áo thun trắng size XL'),
(4, 1, 4, 50, 200000.00, 'Quần jean xanh size M'),
(5, 1, 5, 40, 200000.00, 'Quần jean xanh size L'),
(10, 1, 1, 100, 150000.00, 'Áo thun trắng size M'),
(11, 1, 2, 80, 150000.00, 'Áo thun trắng size L'),
(12, 1, 3, 60, 150000.00, 'Áo thun trắng size XL'),
(13, 1, 4, 50, 200000.00, 'Quần jean xanh size M'),
(14, 1, 5, 40, 200000.00, 'Quần jean xanh size L'),
(19, 1, 1, 100, 150000.00, 'Áo thun trắng size S'),
(20, 1, 2, 80, 150000.00, 'Áo thun trắng size M'),
(21, 1, 3, 60, 150000.00, 'Áo thun trắng size L'),
(22, 1, 4, 50, 200000.00, 'Áo thun trắng size XL'),
(23, 1, 5, 40, 200000.00, 'Quần jean size S'),
(24, 2, 6, 200, 120000.00, 'Quần jean size M'),
(25, 2, 7, 150, 120000.00, 'Quần jean size L'),
(26, 2, 8, 100, 180000.00, 'Quần jean size XL'),
(27, 2, 17, 80, 250000.00, 'Áo khoác size S'),
(28, 3, 18, 120, 300000.00, 'Áo khoác size M'),
(29, 3, 19, 100, 300000.00, 'Áo khoác size L'),
(30, 3, 20, 80, 300000.00, 'Áo khoác size XL'),
(31, 3, 25, 60, 400000.00, 'Áo vest size S'),
(32, 3, 26, 50, 400000.00, 'Áo vest size M'),
(33, 4, 27, 90, 220000.00, 'Áo vest size L'),
(34, 4, 28, 75, 220000.00, 'Áo vest size XL'),
(35, 4, 33, 60, 220000.00, 'Áo phông size S'),
(36, 4, 34, 45, 280000.00, 'Áo phông size M'),
(37, 4, 35, 40, 280000.00, 'Áo phông size L'),
(38, 5, 36, 300, 50000.00, 'Áo phông size XL'),
(39, 5, 1445, 250, 75000.00, 'Áo thun size S'),
(40, 5, 1446, 200, 100000.00, 'Áo thun size M'),
(41, 5, 1447, 150, 120000.00, 'Áo thun size L'),
(42, 6, 1448, 50, 350000.00, 'Áo thun size XL'),
(43, 6, 1449, 40, 350000.00, 'Quần jean size S'),
(44, 6, 1450, 30, 350000.00, 'Quần jean size M'),
(45, 6, 1451, 25, 450000.00, 'Quần jean size L'),
(46, 6, 1452, 20, 450000.00, 'Quần jean size XL'),
(47, 7, 1453, 80, 180000.00, 'Áo phông size S'),
(48, 7, 1454, 70, 180000.00, 'Áo phông size M'),
(49, 7, 1455, 60, 180000.00, 'Áo phông size L'),
(50, 7, 1456, 50, 220000.00, 'Áo phông size XL'),
(51, 7, 1457, 45, 220000.00, 'Áo khoác size S'),
(52, 8, 1458, 100, 80000.00, 'Áo khoác size M'),
(53, 8, 1459, 90, 80000.00, 'Áo khoác size L'),
(54, 8, 1460, 80, 80000.00, 'Áo khoác size XL'),
(55, 8, 1461, 70, 100000.00, 'Áo thun size S'),
(56, 8, 1462, 60, 100000.00, 'Áo thun size M'),
(57, 9, 1463, 200, 60000.00, 'Áo thun size L - đã hủy'),
(58, 9, 1464, 150, 60000.00, 'Áo thun size XL - đã hủy'),
(59, 10, 1465, 100, 300000.00, 'Quần jean size S - thay đổi thiết kế'),
(60, 10, 1466, 80, 300000.00, 'Quần jean size M - không phù hợp'),
(61, 31, 8, 3, 200000.00, '');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `purchase_receipts`
--

CREATE TABLE `purchase_receipts` (
  `receipt_id` int(11) NOT NULL,
  `supplier_id` int(11) NOT NULL,
  `note` text DEFAULT NULL,
  `status` enum('pending','confirmed','cancelled') DEFAULT 'pending',
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Đang đổ dữ liệu cho bảng `purchase_receipts`
--

INSERT INTO `purchase_receipts` (`receipt_id`, `supplier_id`, `note`, `status`, `created_at`, `updated_at`) VALUES
(1, 1, 'Nhập hàng đợt 1 - Thu đông 2024', 'confirmed', '2024-09-15 14:20:00', '2024-09-16 10:00:00'),
(2, 2, 'Nhập vải cotton xuất khẩu', 'confirmed', '2024-09-20 10:30:00', '2024-09-21 14:30:00'),
(3, 3, 'Hàng mùa đông 2024', 'confirmed', '2024-09-25 16:45:00', '2024-09-26 16:45:00'),
(4, 4, 'Vải jean cao cấp', 'confirmed', '2024-10-01 09:15:00', '2024-10-02 09:15:00'),
(5, 5, 'Phụ kiện thời trang', 'confirmed', '2024-10-05 11:30:00', '2024-10-06 11:30:00'),
(6, 6, 'Đơn hàng đặc biệt - chờ xác nhận', 'pending', '2024-10-10 13:45:00', '2025-10-28 20:46:40'),
(7, 7, 'Hàng mẫu mới', 'pending', '2024-10-15 08:20:00', '2025-10-28 20:46:40'),
(8, 8, 'Đơn hàng khẩn cấp', 'pending', '2024-10-20 15:10:00', '2025-10-28 20:46:40'),
(9, 9, 'Hủy do không đạt chất lượng', 'cancelled', '2024-10-25 12:00:00', '2025-10-28 20:46:40'),
(10, 10, 'Hủy do thay đổi kế hoạch', 'cancelled', '2024-10-28 17:30:00', '2025-10-28 20:46:40'),
(11, 1, 'Nhập hàng đợt 1 - Thu đông 2024', 'confirmed', '2024-10-01 10:00:00', '2025-10-28 20:37:13'),
(12, 2, 'Nhập vải cotton xuất khẩu', 'confirmed', '2024-10-05 14:30:00', '2025-10-28 20:37:13'),
(13, 3, 'Hàng mùa đông 2024', 'confirmed', '2024-10-10 09:15:00', '2025-10-28 20:37:13'),
(14, 4, 'Vải jean cao cấp', 'confirmed', '2024-10-15 16:45:00', '2025-10-28 20:37:13'),
(15, 5, 'Phụ kiện thời trang', 'confirmed', '2024-10-20 11:20:00', '2025-10-28 20:37:13'),
(16, 6, 'Đơn hàng đặc biệt - chờ xác nhận', 'pending', '2024-10-25 13:30:00', '2025-10-28 20:37:13'),
(17, 7, 'Hàng mẫu mới', 'pending', '2024-10-26 08:45:00', '2025-10-28 21:28:49'),
(18, 8, 'Đơn hàng khẩn cấp', 'confirmed', '2024-10-27 15:20:00', '2025-10-28 21:27:20'),
(19, 9, 'Hủy do không đạt chất lượng', 'cancelled', '2024-10-28 10:10:00', '2025-10-28 20:37:13'),
(20, 10, 'Hủy do thay đổi kế hoạch', 'cancelled', '2024-10-29 14:30:00', '2025-10-28 20:37:13'),
(21, 1, 'Nhập hàng đợt 1 - Thu đông 2024', 'confirmed', '2024-10-01 10:00:00', '2025-10-28 20:46:40'),
(22, 2, 'Nhập vải cotton xuất khẩu', 'confirmed', '2024-10-05 14:30:00', '2025-10-28 20:46:40'),
(23, 3, 'Hàng mùa đông 2024', 'confirmed', '2024-10-10 09:15:00', '2025-10-28 20:46:40'),
(24, 4, 'Vải jean cao cấp', 'confirmed', '2024-10-15 16:45:00', '2025-10-28 20:46:40'),
(25, 5, 'Phụ kiện thời trang', 'confirmed', '2024-10-20 11:20:00', '2025-10-28 20:46:40'),
(26, 6, 'Đơn hàng đặc biệt - chờ xác nhận', 'pending', '2024-10-25 13:30:00', '2025-10-28 20:46:40'),
(27, 7, 'Hàng mẫu mới', 'pending', '2024-10-26 08:45:00', '2025-10-28 20:46:40'),
(29, 9, 'Hủy do không đạt chất lượng', 'cancelled', '2024-10-28 10:10:00', '2025-10-28 20:46:40'),
(30, 10, 'Hủy do thay đổi kế hoạch', 'cancelled', '2024-10-29 14:30:00', '2025-10-28 20:46:40'),
(31, 4, '', 'confirmed', '2025-10-28 21:28:06', '2025-10-28 21:28:25');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `refresh_tokens`
--

CREATE TABLE `refresh_tokens` (
  `token_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `refresh_token` varchar(255) NOT NULL,
  `expires_at` datetime NOT NULL,
  `revoked_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Đang đổ dữ liệu cho bảng `refresh_tokens`
--

INSERT INTO `refresh_tokens` (`token_id`, `user_id`, `refresh_token`, `expires_at`, `revoked_at`, `created_at`) VALUES
(1, 1, '6d52ae18059a5aea9ca815a9705e8dcbccbe4ca2594f92d6bb8532e4f0f43d69', '2025-09-15 11:08:53', NULL, '2025-08-16 16:08:53'),
(2, 1, '1f526ac8ecdf854b3ed16117b9ede0c7e6b29a2815fc31873768ad9d07d8ea97', '2025-09-15 11:09:18', '2025-08-16 16:09:27', '2025-08-16 16:09:18'),
(3, 1, '9a1a479b12fe6cfb45b6f8e889a9c20c5f09df32808258ae87247474ee354085', '2025-09-15 11:09:27', NULL, '2025-08-16 16:09:27'),
(4, 1, 'a48c413d87f838263fec4f13924996e1b406a640b3ae961854fc7e2d376922d7', '2025-09-15 11:10:01', '2025-08-16 16:10:01', '2025-08-16 16:10:01'),
(5, 1, 'fe022d8de73df8209c1514542c3c0f4ace75e73f883e1c72916b23ff89c247e6', '2025-09-15 11:10:01', NULL, '2025-08-16 16:10:01'),
(6, 1, 'b492de7ce8b62a69a002767b4846af50536e316aeca20c5430d2e4b8c73befcb', '2025-09-15 11:10:25', '2025-08-16 16:10:25', '2025-08-16 16:10:25'),
(7, 1, '55c357b90b9c5b4574ed4d0f0747d62ef94ac28e711b5039e421de444e323bf7', '2025-09-15 11:10:25', NULL, '2025-08-16 16:10:25'),
(8, 1, 'ea9c69e68afc0eb36d2a977a624653ce22292c234cd2a1621453359d75b77f8a', '2025-09-15 11:12:53', '2025-08-16 16:15:27', '2025-08-16 16:12:53'),
(9, 1, 'e77d731d74bcd1e5603182b007c254294ba20846ea949bd0653e8e8d6bf2bc88', '2025-09-15 11:13:17', NULL, '2025-08-16 16:13:17'),
(10, 1, '957b129f1c5fb580b31163bc068cdce1a0d1aa8c90c9f6e136dae4bf7fe6aa9a', '2025-09-16 10:58:24', NULL, '2025-08-17 15:58:24'),
(11, 1, 'fff319e8f101ff3fa7887607291bd8e7b23a657b31d7b75ec58ed3cebed53227', '2025-09-16 10:59:25', NULL, '2025-08-17 15:59:25'),
(12, 1, '70a663a602c4558706f7ef05057928ee517f9151238d99283b8df635f04e0297', '2025-09-16 10:59:52', '2025-08-17 16:57:37', '2025-08-17 15:59:52'),
(13, 1, '26abc7d8b384f624cc6286334497b59544c495c9a8d1e57e7bc070dd7297c382', '2025-09-16 11:00:38', NULL, '2025-08-17 16:00:38'),
(14, 1, '08e5e8f07777dd33271dcfb11e9f20d1d8d53e6318d74eff195a5b8b6e491d01', '2025-09-16 11:57:37', '2025-08-17 18:19:00', '2025-08-17 16:57:37'),
(15, 1, '105d133428ab8d5069ee0e7deffdbfc6607586025965ebeedc1e7c0ab3582467', '2025-09-16 12:59:45', NULL, '2025-08-17 17:59:45'),
(16, 1, '522fde08a4f66e4482491f98bf80c422191c1c1b26befc0c1cd2cd84404b6ffe', '2025-09-16 13:19:00', NULL, '2025-08-17 18:19:00'),
(17, 1, 'c40b3e44232cd1d7863c06462ecceb87414efc88437a54410a40428bf19f83bd', '2025-09-16 16:05:55', NULL, '2025-08-17 21:05:55'),
(18, 1, '7d2aaca51ebbd7e36329f05197f0667d89ccae2fa8d1205eadf724f3f1294383', '2025-09-16 16:06:16', NULL, '2025-08-17 21:06:16'),
(19, 1, '0a401ff1b6edbd7fbe1c40f0118d4b7a49d5b21b83a9a5ecdc78ec2f919fa117', '2025-09-16 21:05:06', NULL, '2025-08-18 02:05:06'),
(20, 1, 'b683a04da0f256223f531a0c525b08e84a779558249a289d945d290914cee283', '2025-09-16 21:05:37', NULL, '2025-08-18 02:05:37'),
(21, 1, '6c9f2a71452d136b227870da70138e99888a8e4db963c3f6d124c32933cb490b', '2025-09-16 21:47:12', NULL, '2025-08-18 02:47:12'),
(22, 1, 'fa15c4c690a89b6d0a643fac940d3b6596dfd6edf08526e8dc34ada6ed0b78d0', '2025-09-16 21:57:39', NULL, '2025-08-18 02:57:39'),
(23, 1, 'c29232b7011ba6167d0c6dc7af3123e37012d294e745308859e9e81ae61e5d31', '2025-09-16 21:59:18', '2025-08-18 03:03:00', '2025-08-18 02:59:18'),
(24, 2, 'fdbed177b8fc327fd4e68bb4a010000b96fa1209bf015bde074123a2330326a5', '2025-09-16 22:03:54', '2025-08-18 04:01:59', '2025-08-18 03:03:54'),
(25, 2, 'e6fa355d7be6394974217bff47f8831c1c704bb152414b2acde951c80a631b26', '2025-09-16 23:01:59', '2025-08-19 22:21:16', '2025-08-18 04:01:59'),
(26, 1, '88db7fef732722fd70862312e953bc50d940ed032cab8a5323ede6ffbae22352', '2025-09-16 23:13:57', NULL, '2025-08-18 04:13:57'),
(27, 1, '3a1e1bbca115ec2e53ca1a3feff7b8c9256228477a806cc184664cd2c3201129', '2025-09-17 18:12:29', NULL, '2025-08-18 23:12:29'),
(28, 1, '3a82a5c928a1ef7389802e68c5009bd69212b40209ebaedd50618b8e2f50f15c', '2025-09-17 18:13:25', NULL, '2025-08-18 23:13:25'),
(29, 1, '15df62b3ca075948bed798d23501adf251276c375e4ec8205f4d9b4f64c1a7e4', '2025-09-17 18:23:32', NULL, '2025-08-18 23:23:32'),
(30, 1, '361b7afa8476836218d7a52d962e6b2a34bf40932330b3ef08ebe4b94d0d867d', '2025-09-17 18:59:04', NULL, '2025-08-18 23:59:04'),
(31, 1, '1b91ffab2d8d8a9b064cb7aeb56b32ee68eaa0831ab7e3dc821c6dd4f49e8510', '2025-09-17 18:59:08', NULL, '2025-08-18 23:59:08'),
(32, 1, 'efca9582f423c823400d46b96f52195d091ac976f73aa4498c5ef3ada08f6ea5', '2025-09-17 19:01:42', NULL, '2025-08-19 00:01:42'),
(33, 1, '74705e032f37c9cd7f09baf474d9cce879ba067ef62cf4a12e2e743e15e74695', '2025-09-17 19:04:46', NULL, '2025-08-19 00:04:46'),
(34, 1, '78a32908bb873d910aff5ed2a9b406d38b39f878f0efe909f1991af58e096938', '2025-09-17 19:06:52', '2025-08-19 01:07:33', '2025-08-19 00:06:52'),
(35, 1, '1ada3db3c8f4a21be7cef761115a5ab938d9fb96809bf8b50e80edd26c22b397', '2025-09-17 19:10:17', NULL, '2025-08-19 00:10:17'),
(36, 1, '0742545111dd2d11302e00523fcd72d52df0ec20457bc9ce1e2651a499c9666f', '2025-09-17 19:18:27', NULL, '2025-08-19 00:18:27'),
(37, 1, 'c4201f36ad2fc969a0d7df0085e231b28ed70f9ae84e22817148107289570595', '2025-09-17 19:21:02', NULL, '2025-08-19 00:21:02'),
(38, 1, '8c068e24b9ff1bd881d253223377d85d2ef976c48f5de858500785bdb3bf5416', '2025-09-17 19:25:23', NULL, '2025-08-19 00:25:23'),
(39, 1, 'd8e1b13507233cb294dd4bc69e87e1b0aef0023cb516fa28a566c4446556e688', '2025-09-17 19:41:13', NULL, '2025-08-19 00:41:13'),
(40, 1, 'a1fc9ff3de3f26c1893b06689b471387f6b0ded21a3c4b7d39ab332efb878699', '2025-09-17 20:07:33', NULL, '2025-08-19 01:07:33'),
(41, 1, 'a783292e9f6742a9bafc83dc75eefe767e842d3ce3db6fc9ec31a4d50f7e8e06', '2025-09-17 20:10:33', NULL, '2025-08-19 01:10:33'),
(42, 1, '1f5db11cb2a1173534fbd081be313d7d190317846536da2f6a160639f7bf7738', '2025-09-18 08:28:14', NULL, '2025-08-19 13:28:14'),
(43, 1, '86eb51e9e41916eaf5e28c662d2e4924c3e03dac8fce81e0e600fc0932bc2f5f', '2025-09-18 08:30:15', NULL, '2025-08-19 13:30:15'),
(44, 1, '47780011258f6c661d01f10f75cab4f1c7df6669abf190dd1304bd76d1894435', '2025-09-18 09:55:24', NULL, '2025-08-19 14:55:24'),
(45, 1, '059d8ed01015c382b357311def769ff92a8925eac39b159dded638635dcb97ed', '2025-09-18 10:02:38', NULL, '2025-08-19 15:02:38'),
(46, 1, '98e8af480f0f1a9764be2fa00cd890f9d3d58bb16969b0cf165e31531d95942c', '2025-09-18 10:10:33', NULL, '2025-08-19 15:10:33'),
(47, 1, '64e8613dbd53e972122d456e85be736a6acfbe3a72050fecfd8dad15913b9d41', '2025-09-18 10:12:15', NULL, '2025-08-19 15:12:15'),
(48, 1, '7b5b14e1d923028ced08541c5bcc0a1d0ea08b395b4c2c21aa8f3e34923d9843', '2025-09-18 10:12:56', NULL, '2025-08-19 15:12:56'),
(49, 1, '916da8703d659982d67407547cc3aba871d805c3951f11e96047f3851a7cff72', '2025-09-18 10:14:21', NULL, '2025-08-19 15:14:21'),
(50, 1, '7997464197abb7e12da493e490430536aa57877c4e9ec6b303c577d49b81e8a0', '2025-09-18 10:44:54', NULL, '2025-08-19 15:44:54'),
(51, 1, '7d80cc48afb9c929ecf87dafc5529712f55e6d309a1903d051f82fb2d930d693', '2025-09-18 10:46:43', NULL, '2025-08-19 15:46:43'),
(52, 1, '51b3c0593b6fc515da217ad56ae5aa3a2e504ea7da478216e33aff3d29cc62fd', '2025-09-18 10:47:06', NULL, '2025-08-19 15:47:06'),
(53, 1, 'dd18e0b58ee90201732ff5349aae9728e8b0dbd4332214e03a646e1aa342ea76', '2025-09-18 10:48:06', NULL, '2025-08-19 15:48:06'),
(54, 1, '2b8a51647a330c7bab3716c03dd6d5d172f10da147a07bdbb3f3d607d9bf5d5e', '2025-09-18 10:52:02', NULL, '2025-08-19 15:52:02'),
(55, 1, '9856377f5e6abeb5b3ab9087969941f99e900fe8cdc3e5b67bd2323f1c814be4', '2025-09-18 10:53:39', NULL, '2025-08-19 15:53:39'),
(56, 1, '72cd3786d0eeeb2f63b7db9298e7e19370fc1e5032d3befa5aadead15cb2a47f', '2025-09-18 11:00:18', NULL, '2025-08-19 16:00:18'),
(57, 1, '48692bcd29271c4a09e4b17ddd76335c3b695877360e3378ef218147ff5ecd42', '2025-09-18 11:14:52', NULL, '2025-08-19 16:14:52'),
(58, 1, '50c95c6638ccb8a0a884ed93e03da8fe9e3ff59d17c655f1fd1fa0d7f0127263', '2025-09-18 12:05:05', NULL, '2025-08-19 17:05:05'),
(59, 1, '933ec210b4036d4d4bfc75d13aab2f43caf997ac873b0cbdfac3fefbfe8ad39d', '2025-09-18 12:27:51', NULL, '2025-08-19 17:27:51'),
(60, 1, '3d955155fb1af8d185e036d5b9ea510c0fa57209b4273eacb8e1548522dec3c8', '2025-09-18 12:28:51', NULL, '2025-08-19 17:28:51'),
(61, 2, 'e8fa3f701ba56f15642d115cb4122ff6bf57960dc2124ff4346bafdc0fe32687', '2025-09-18 17:21:16', '2025-08-19 23:16:30', '2025-08-19 22:21:16'),
(62, 2, 'd9f6a9b59971467837f89f706d5839cae5940690ee09c2429c2fbd0944884167', '2025-09-18 18:16:30', '2025-08-20 00:16:30', '2025-08-19 23:16:30'),
(63, 1, 'b9be8df0b204e6b4b94cd3a4ca553b0eac9ed9d48a97bf1450049e88ab4efa83', '2025-09-18 18:17:11', NULL, '2025-08-19 23:17:11'),
(64, 2, '3ed71da17c239fee283f2977c69365405580d6144c3df8efa29745b22f2be11b', '2025-09-18 19:16:30', '2025-08-20 01:13:51', '2025-08-20 00:16:30'),
(65, 2, 'd710d2bf94ebf331f299f8ad97b632e5c845b753d1bb31f619deaff4d639c19c', '2025-09-18 20:13:51', '2025-08-20 09:29:52', '2025-08-20 01:13:51'),
(66, 2, 'c0934eac23e126941d4e90019ba48e50d55531827773d68c3d71f863ceb1d55a', '2025-09-19 04:29:52', '2025-08-20 10:27:33', '2025-08-20 09:29:52'),
(67, 1, '48c0148641d324b26035233e9f652935da79b3159482bf446e6765f7f8270a34', '2025-09-19 04:48:40', NULL, '2025-08-20 09:48:40'),
(68, 1, 'debf66428055033d8895933a4fba811e20cc40e4c04476b6f8f4bf450a7e737f', '2025-09-19 05:02:42', NULL, '2025-08-20 10:02:42'),
(69, 1, 'ea8ad9d93b7570ac0c385d125baf803e5d6c72b6ed58dec6c4a284b95edbc452', '2025-09-19 05:22:59', NULL, '2025-08-20 10:22:59'),
(70, 1, '5b80fd7020c287c1ba2b0d1585dbb748c04a854574107d336471d0e9f44df846', '2025-09-19 05:26:04', NULL, '2025-08-20 10:26:04'),
(71, 2, '76f1735d2451e3b76198e9ed4fdf41a86fe96aac01d56e64b7e89a17f32b7ed6', '2025-09-19 05:27:33', '2025-08-20 11:24:10', '2025-08-20 10:27:33'),
(72, 2, 'a1dea16addbb66e34ecd5c7fc9f602d492e19e28658794415b3b3c171f41a862', '2025-09-19 06:24:10', '2025-08-20 13:11:27', '2025-08-20 11:24:10'),
(73, 2, '7cd3c6fd50b37bcc4deab98f105bc25102d136a00076f5717a9286f1333104bf', '2025-09-19 08:11:27', NULL, '2025-08-20 13:11:27'),
(74, 1, '19ad1f87a645602c23b6ca3dc4ff61c2db92e83889f5a3e4f616584c2f3c61b2', '2025-09-19 08:34:30', '2025-08-20 14:29:31', '2025-08-20 13:34:30'),
(75, 1, 'ab2a345ec55dce16716d43dfa835a40fc8d8accaf1573ae7b116eb5aabb44933', '2025-09-19 09:17:25', NULL, '2025-08-20 14:17:25'),
(76, 1, '90dbaebdfeefdb722b71749e1da30a018760979ecdae60a4d7ad331c11f0bb94', '2025-09-19 09:24:58', NULL, '2025-08-20 14:24:58'),
(77, 1, '7746875492271378daede94f6111f9f637070528f29e932f18466ee8d2aef47b', '2025-09-19 09:29:31', '2025-08-20 15:25:29', '2025-08-20 14:29:31'),
(78, 1, '12d42e60bbf7db8b143c347e1a8826d91d73f4e55090ef11118d38c07c4fabcb', '2025-09-19 10:25:29', '2025-08-20 16:25:29', '2025-08-20 15:25:29'),
(79, 1, '56800cfee27ccb49f4097c692ca891520b1571cd66434b07a1e0ca2be2a86341', '2025-09-19 10:53:31', NULL, '2025-08-20 15:53:31'),
(80, 1, 'a7f200c58ced53cce3d9a01a906c4c3f5554b1fae8664454342096249d923797', '2025-09-19 11:25:29', '2025-08-20 17:25:29', '2025-08-20 16:25:29'),
(81, 1, 'aa38da451f71ae16898df87ff2aebc0c6f3a23d47b9eae6d2d4892aa0bbfc0a8', '2025-09-19 11:30:56', NULL, '2025-08-20 16:30:56'),
(82, 1, '9d7fe644147d751cb57c80a9b48afa340abb2ae30bb59a49ec83dfaf63126025', '2025-09-19 12:24:09', NULL, '2025-08-20 17:24:09'),
(83, 1, '129fd5d0f0d8960e9bda5eb92ba1c7f41ae3cac1fd879d5ea57b1fd1f62b3afa', '2025-09-19 12:25:29', NULL, '2025-08-20 17:25:29'),
(84, 1, 'fa278b11454fab28ff230ea832a883bba5f4b807a655b726ca0e2955c6408d99', '2025-09-19 12:33:05', NULL, '2025-08-20 17:33:05'),
(85, 1, 'cb8be7e36af99f3db26d31e737cc78b7f0e5c10eb164b102898a9726ce63c80c', '2025-09-19 12:35:27', NULL, '2025-08-20 17:35:27'),
(86, 1, '44a12302f7eaf814bd8ae43cdfe819f8e69f17cf75bcfe29d2196db3d76ede5b', '2025-09-19 17:00:50', NULL, '2025-08-20 22:00:50'),
(87, 1, 'db77ce5ec04aa036cc7fd7cc33e42c99dd7589422651676c0cd8081969a93c27', '2025-09-19 17:03:25', NULL, '2025-08-20 22:03:25'),
(88, 1, '10cff9e1a80c36d82a5e946457f91c0cbec365b7c55ef124315db1b7ff77cc6e', '2025-09-19 17:03:36', NULL, '2025-08-20 22:03:36'),
(89, 1, '01fc927abbe11eaed6c6a653e4ad55441eb30a0ca91e118a378a4156f336fe2c', '2025-09-19 17:25:10', NULL, '2025-08-20 22:25:10'),
(90, 1, 'd3aea2f3d8c5da6330ebdc11d9e24a42cb09542687599ef365a5df5962044aed', '2025-09-19 17:25:45', NULL, '2025-08-20 22:25:45'),
(91, 1, 'c17ccb90500425d7b903c2d89fc69bddf367df98d7d29b2fdb3e7fe6019dd2d2', '2025-09-19 17:45:18', NULL, '2025-08-20 22:45:18'),
(92, 1, '0975a42e2409c5eecd6cc90f62724df1db19a0d302c855c93e8c3f1bec7b032a', '2025-09-19 18:47:44', NULL, '2025-08-20 23:47:44'),
(93, 1, '1b6567574137d896652ed3a4dae4dc895c5cb0047c884971ccb9ddc3172dadf9', '2025-09-20 04:35:06', NULL, '2025-08-21 09:35:06'),
(94, 1, 'f07e77646c88647b883bdaa02ef0596fce583cf5cf156851fd0a9adfde3a0d5d', '2025-09-20 04:36:52', NULL, '2025-08-21 09:36:52'),
(95, 1, '661099d7e3d8016351e56cc1b98aabc31bf8db19eb78e1364b77fe8abaa93659', '2025-09-20 04:51:42', NULL, '2025-08-21 09:51:42'),
(96, 1, '60b2bb58539718678bd961c479469b05a094989d6413cad3942378089ca058a2', '2025-09-20 05:12:51', NULL, '2025-08-21 10:12:51'),
(97, 1, 'c57e7c536560cfeee3195d090f3e971b5ac28356017c5d028d89d58ac8cba41c', '2025-09-20 05:14:26', NULL, '2025-08-21 10:14:26'),
(98, 1, '31dd92d10f378bbcb643ad59371672bb5f50084ddeff8f919c07c2a8aabdd0a9', '2025-09-20 08:33:02', NULL, '2025-08-21 13:33:02'),
(99, 1, 'baaf882ea7435ee2753f882b3e174e39f68fb856554fe79b1f64e92b38c65868', '2025-09-20 08:39:20', NULL, '2025-08-21 13:39:20'),
(100, 1, 'f9874d4a88ae0755354fcde1fa79e076cc3325becf7a148411309322e53b9d5a', '2025-09-20 08:39:40', NULL, '2025-08-21 13:39:40'),
(101, 1, 'b5c53b738c7c6449429e745a1384fb053087f354b73e0e5c35070f2b0aee93f8', '2025-09-20 08:54:27', NULL, '2025-08-21 13:54:27'),
(102, 1, 'aff7220a869b0ee4b16dd0e6bc9377237ec29a8c6ca0c13a039926a037edbc99', '2025-09-20 11:46:04', NULL, '2025-08-21 16:46:04'),
(103, 1, '36fc4da2fe6e9ea209b03f9ad0ba545f078ad74cb9b9431139584aa513e56129', '2025-09-21 07:28:49', NULL, '2025-08-22 12:28:49'),
(104, 1, '286fa28ced50cc2ef4fa1a199f89ad3bdfb130a2c2beb20dd21873a236f8b5cc', '2025-09-21 07:33:50', NULL, '2025-08-22 12:33:50'),
(105, 1, 'dce27953b13f4e0a39db1b95e1b411c631f4f309f8548c776e508e44e941ca9b', '2025-09-21 07:35:54', NULL, '2025-08-22 12:35:54'),
(106, 1, '0deb99e4a74e71be520377dc7de021f224a0559ce511f7c946901833b4b9c8d5', '2025-09-21 07:39:52', NULL, '2025-08-22 12:39:52'),
(107, 1, '58a2ca284f3c8bfba6802d8c0a2afa00818cc29a06f0296a95ad80fd47376a52', '2025-09-21 08:38:11', NULL, '2025-08-22 13:38:11'),
(108, 1, '639a10711f76e631474a9145053beed50c560d92a4211480f0d8230ffa72aa4f', '2025-09-21 09:13:52', NULL, '2025-08-22 14:13:52'),
(109, 1, '85a85118607a6bb6b2623bc971b3656777d4abb16d70767662ef00643d2f42ff', '2025-09-21 09:16:00', NULL, '2025-08-22 14:16:00'),
(110, 1, '99d667407d7704d4510b51ceb79ea6c2e43783833643a8a1e212dc983bfe7d10', '2025-09-21 09:28:30', NULL, '2025-08-22 14:28:30'),
(111, 1, '445888adba11d285ef6e44672c403a311617675b86e6af0e7447b59a63739395', '2025-09-21 10:02:57', NULL, '2025-08-22 15:02:57'),
(112, 1, 'c5dafba6912262217741a4a5e8edf0f6d57499cb368eb616e4d8658b7225f5f2', '2025-09-21 11:05:22', NULL, '2025-08-22 16:05:22'),
(113, 1, '3bdf85fedfca907bc5437fbb6480f489b0a77b4172bc2636fd49b63916d9bb80', '2025-09-21 11:09:53', NULL, '2025-08-22 16:09:53'),
(114, 1, '06319a3b45ed38bd3752d2b65ec7d7fcf7a49d3cfd18eb8568376a46ffe95e5b', '2025-09-21 11:40:35', NULL, '2025-08-22 16:40:35'),
(115, 1, '693675af9c131d8c52cf532683824b6275731225046d5bbc949d9749c2fbc07d', '2025-09-21 11:53:14', NULL, '2025-08-22 16:53:14'),
(116, 1, '36da4de9b176536eaa07be31535b07752562d23f0c47fd23548187d2c8af64fe', '2025-09-21 12:34:15', NULL, '2025-08-22 17:34:15'),
(117, 1, 'a73f258b14bf63d432df5358f0cfcda7eb2d9cb814fa0e5478a311ec5f288df6', '2025-09-21 12:35:33', NULL, '2025-08-22 17:35:33'),
(118, 1, '724758cb337855159df4fc689bb6d251590f3766f3bd23ab54dcaf074b96b38b', '2025-09-21 12:40:52', NULL, '2025-08-22 17:40:52'),
(119, 1, '9fdf5b2760b04703c4cd085dd2d6f2bb4510f0d7bdbc69442bb3815c09399b25', '2025-09-21 15:25:53', NULL, '2025-08-22 20:25:53'),
(120, 1, '758ce593dbc130401f6377daa47dc0663aa2af04bdc9d297ac32078fedb6827e', '2025-09-21 15:48:06', NULL, '2025-08-22 20:48:06'),
(121, 1, '1ab584487ac2dfa3a50eb07d6f48663571952a9c0807065e6e67e71aba4775b9', '2025-09-21 15:49:32', NULL, '2025-08-22 20:49:32'),
(122, 1, '203f3077419e05896668d4cf1936b671f314ec5828cb77914d6db5f9951c8c8a', '2025-09-21 16:53:52', NULL, '2025-08-22 21:53:52'),
(123, 1, '01a55e0766268ea38cbd8f1d010448e383f99b408fb549b1fd4a73b3fbee3bd4', '2025-09-21 19:21:56', NULL, '2025-08-23 00:21:56'),
(124, 1, 'be2b4ca13bc295371a1e4171f9899113c1c4ad2f4a7a524c8dd4607e3fa4d24d', '2025-09-21 19:22:48', NULL, '2025-08-23 00:22:48'),
(125, 1, 'a26cfb601c09681d13a86efe6c2db05fbef32d65e97a3df9081351e81a4f776b', '2025-09-21 19:24:07', NULL, '2025-08-23 00:24:07'),
(126, 1, 'f45d1a8e3987aeedf2a834d118f633f723c4e60a275d5241adc62c95266ac186', '2025-09-21 19:43:09', NULL, '2025-08-23 00:43:09'),
(127, 1, 'ea98558d893116c420d683425f4e923398338194aae8a3a50a49ecebb8b5240e', '2025-09-21 19:43:13', NULL, '2025-08-23 00:43:13'),
(128, 1, '032930ce28b616b8291674988e5d42ae99bf404a652409711981e7906a7238b8', '2025-09-21 20:01:37', NULL, '2025-08-23 01:01:37'),
(129, 1, '112cd67a56be5626a125d8dd0c78bade84c5abca9ff0199a615ac2a30fba2ef3', '2025-09-21 20:43:06', NULL, '2025-08-23 01:43:06'),
(130, 1, 'b0d063f18239a6529e9e6f9d799c0d31d15c8d1858027f00194ca0563a4fc29a', '2025-09-21 21:05:40', NULL, '2025-08-23 02:05:40'),
(131, 1, 'b385459f1aa0c2db259792173cff3173b96a20e34d9484cd905ff8c0b121569b', '2025-09-21 21:20:58', NULL, '2025-08-23 02:20:58'),
(132, 1, '0628432651e11eab62c1b38959624a743fe438c20eddad61e4dd2a21e017066e', '2025-09-21 21:22:55', NULL, '2025-08-23 02:22:55'),
(133, 1, '572718b685ad7d3bf62b55edf97975982a55c8fde199c07a1910e73eabd8091b', '2025-09-21 21:58:33', NULL, '2025-08-23 02:58:33'),
(134, 1, 'e5c5a961d4da547a314057a963feb706a1f764c706e9834869b148240d78eaff', '2025-09-21 22:00:53', NULL, '2025-08-23 03:00:53'),
(135, 1, '49e0c9ee8938fc8062d0d5ca45c15c57e72118a68ebe63057b1206cc2a2786d8', '2025-09-21 22:58:41', NULL, '2025-08-23 03:58:41'),
(136, 1, 'a2d7607df726b4b6baafab30c7f513f6a0cfb628b3e4a46d3010ecb985b82e98', '2025-09-22 06:00:30', NULL, '2025-08-23 11:00:30'),
(137, 1, 'dabdfe4417b0816589a5fd4203461f78d35df77c2e39d1a6489ac195705d4b9f', '2025-09-22 06:01:29', NULL, '2025-08-23 11:01:29'),
(138, 1, '639d095d745efb7e3ab8bf5113697c8e73902c23ea02ab3c653dbef4e072f7dd', '2025-09-22 09:54:02', NULL, '2025-08-23 14:54:02'),
(139, 1, '861db18946a09c4f97ccacdf81758cb545f1ea341d74adc6bc17508f67e2b799', '2025-09-22 10:34:42', NULL, '2025-08-23 15:34:42'),
(140, 1, '0a4dc7ced906dea4b1cfe82e100ac42e1abc1e346f7b0a85f6f4efaea190617c', '2025-09-22 10:39:45', NULL, '2025-08-23 15:39:45'),
(141, 1, '39d645ae341f6e442fb1e1887b5d0b1b2e3d956c40acd94863758a3aa7a2abfa', '2025-09-22 11:42:18', NULL, '2025-08-23 16:42:18'),
(142, 1, '47282230468c1c872c240a2b8573afe6f8d1d8064e6a74f7120f232604bcbf35', '2025-09-22 12:48:22', NULL, '2025-08-23 17:48:22'),
(143, 1, '85257d3df641d5ba1183b435b3864931e194270fd799e58d6da2446fc334902f', '2025-09-22 12:50:45', NULL, '2025-08-23 17:50:45'),
(144, 1, 'e25c9e38ce23b734d39d54346509a7a4e1deae5a92e03a6692fc6cd5d9d38b8c', '2025-09-22 13:04:57', NULL, '2025-08-23 18:04:57'),
(145, 1, '119137510b6fedf43637129119e5329687f5756ad7ac86cb4360e116df505d59', '2025-09-22 13:28:04', NULL, '2025-08-23 18:28:04'),
(146, 1, '036ca03c38110cd82368495bc8290f64c815a1e8561f4f23ce1da9514a8141d9', '2025-09-22 14:31:39', NULL, '2025-08-23 19:31:39'),
(147, 1, 'f94b6ed0d978ab28726cfa18dc058d672f7ed1c417473e2bbd6759e89e4fd02c', '2025-09-22 15:45:46', NULL, '2025-08-23 20:45:46'),
(148, 1, '57ee0fdbb13c8e857d10a79c9d5f8e4b28f91596e27efd1fcf91f55d332db907', '2025-09-22 15:47:16', NULL, '2025-08-23 20:47:16'),
(149, 1, 'e98cc88ab8fb8b16d50d65ed56e26eafd9cb6b331c6dff8c867f082eecc9cc33', '2025-09-22 15:51:11', NULL, '2025-08-23 20:51:11'),
(150, 1, 'db13ed6f8c8d975f63906c68e6ef967665d5e9f5562c060ad9aab5cddb023562', '2025-09-22 15:52:29', NULL, '2025-08-23 20:52:29'),
(151, 1, 'e7114476f85ae1f3ef6438dd463cb2fef7a8506d58d44ed9b11ca729b69bea91', '2025-09-22 17:11:13', NULL, '2025-08-23 22:11:13'),
(152, 1, '93463019996f61d0e032c98bce3f4cb0f296ecc7bd0b6890b9326b0afe6cdadb', '2025-09-22 17:11:31', NULL, '2025-08-23 22:11:31'),
(153, 1, '43e5648e6ffb307d958dc1c0f0f3cd204e9a02e6382c3a8647197bb0b7510f25', '2025-09-22 17:49:35', NULL, '2025-08-23 22:49:35'),
(154, 1, 'b5bf72c642a15f7f5384b661dd96a63df019c7210796ff62be4e96cc3523a8a8', '2025-09-22 17:53:31', NULL, '2025-08-23 22:53:31'),
(155, 1, '0193c84559184619fc18857d33d824e9ffb490c6dc04fc1ec5647e4f3e6dabf1', '2025-09-22 17:57:29', NULL, '2025-08-23 22:57:29'),
(156, 1, '140c4938a41220df1b849dee30de8598adecf2763bcc4f90abdbd3d08846e051', '2025-09-22 18:00:49', NULL, '2025-08-23 23:00:49'),
(157, 1, 'ea719fd7ff7d0c3eed1bd19a9a2aea6c16e08dcc6249b9c92d98256b69fd1b92', '2025-09-22 18:28:20', NULL, '2025-08-23 23:28:20'),
(158, 1, 'dcd50c01ddfbb239d8bd60ea6f52b17a62c502c8f7143ac81397b21beee000ec', '2025-09-22 18:31:06', NULL, '2025-08-23 23:31:06'),
(159, 1, '313c4f0ea819d8d6e897e5e3ef17f547c8057c511d5bbc23bcbd032e367ec3bc', '2025-09-22 18:31:43', NULL, '2025-08-23 23:31:43'),
(160, 1, 'e66890541814eaf1d6d295d0778373acf2f7f761f2dfe88f02c65744981be606', '2025-09-22 18:34:13', NULL, '2025-08-23 23:34:13'),
(161, 1, '6e08793da46142608f37e94791c1cba7d7a38b982cc68c0f023d4b84fd7b5546', '2025-09-22 18:37:56', NULL, '2025-08-23 23:37:56'),
(162, 1, 'a0610adff4969dcf58c95be3e5be7a8fa3d44a35d21717ce350e4d57207c11fb', '2025-09-22 18:44:15', NULL, '2025-08-23 23:44:15'),
(163, 1, '8b422f78bcc22841abd2f56055951a970e33455858d498d1fbaa166f216753a0', '2025-09-22 18:46:11', NULL, '2025-08-23 23:46:11'),
(164, 1, '4481f55e1ecfef65e422e36c5e0cdde88815ecde180637f717ec3f6f78f8cd7d', '2025-09-22 18:48:38', NULL, '2025-08-23 23:48:38'),
(165, 1, '29e98ae9eb50289e4dc7b7aa3a8152793fa772637e0815818e802d785f411df1', '2025-09-22 18:50:53', NULL, '2025-08-23 23:50:53'),
(166, 1, '90a66c2e662d6a9cecf26f5ae435cec406dffd888809f04e164a24a7752ccf43', '2025-09-22 18:54:45', NULL, '2025-08-23 23:54:45'),
(167, 1, 'bf274352b8fd74d72fbb6b4c12e632a5dda9b7d255068bb64fbf3677dd73bd38', '2025-09-22 19:03:42', NULL, '2025-08-24 00:03:42'),
(168, 1, '02615db71ccb43c3630e88384abbf9202ac7ea736e8480bf42943f58cf83bc1e', '2025-09-22 19:08:05', NULL, '2025-08-24 00:08:05'),
(169, 1, '4f558e75560c9d1a04b09db8dc1f64672a717d4e8ab84c0c755ba6ea5cdd608b', '2025-09-22 19:13:21', NULL, '2025-08-24 00:13:21'),
(170, 1, 'faaa0150e3efcc339689f9529f5c43a583253d516b912bb93c8d84e350da83c8', '2025-09-22 19:15:05', NULL, '2025-08-24 00:15:05'),
(171, 1, '5416e32ea48ce03e96525ae195e23de8109d2161e5b4c75c359feef4f86b109b', '2025-09-22 19:19:02', NULL, '2025-08-24 00:19:02'),
(172, 1, '829be37a550ea3f44635c4d6ffd33531d7eaa2135d094ec7815227b4ee1f9e39', '2025-09-22 19:21:38', NULL, '2025-08-24 00:21:38'),
(173, 1, 'bd9d0a9a3172da1bfc1c0ea0407889d7f9cb441d9f52197adcc982410c85f8a5', '2025-09-22 19:23:19', NULL, '2025-08-24 00:23:19'),
(174, 1, '0383634767a046417f9c0e13dba0113c353d2c8a7023bf1dd260815229e9b2d8', '2025-09-22 20:15:11', NULL, '2025-08-24 01:15:11'),
(175, 1, '5bdb04281255852ce5eec1e3cb5635f5cd9c19d270fb9a0423d9308042e7fc77', '2025-09-22 20:22:01', NULL, '2025-08-24 01:22:01'),
(176, 1, '032dd514241614709e0cb98829b4f9bcca8685dbf110ac17261061a6512d07ce', '2025-09-23 06:14:29', NULL, '2025-08-24 11:14:29'),
(177, 1, 'f2d17b46916076a7e83f2d261f12363c57caa29a0171293da068492bfb76e008', '2025-09-23 06:14:49', NULL, '2025-08-24 11:14:49'),
(178, 1, 'eab5e293b79eea0aada432fbaeeee45f2cb61ea78a2ab221eb96e46d107588b2', '2025-09-23 07:48:34', NULL, '2025-08-24 12:48:34'),
(179, 1, 'dc9d7e3f606938e581d5c74c4821a45793bfb65003f1acf9ffdd38f6a5a32645', '2025-09-23 07:57:19', NULL, '2025-08-24 12:57:19'),
(180, 1, '734422760fe9530bc8d8383ab1d17aff6778bb5b8489c5a8c1d5b4893b4cbbe8', '2025-09-23 08:01:49', NULL, '2025-08-24 13:01:49'),
(181, 1, 'dad019332813d1a65b8499039daddb7efb7dac2c44f3c5298a12b59bb125b76b', '2025-09-23 08:15:18', NULL, '2025-08-24 13:15:18'),
(182, 1, 'e94cd6bc828d560070510fc6de630576882f00687c884cf61844b3bf21e9f663', '2025-09-23 08:19:23', NULL, '2025-08-24 13:19:23'),
(183, 1, '6ff76e5e74b3a9f88fae216ac28ed96127f6a923b884199de1b474e95cef36ce', '2025-09-23 09:19:43', NULL, '2025-08-24 14:19:43'),
(184, 1, 'f29502b51bca49d28c647fc2763c73c094e8ec6bcb4db15a65c69145bdb41bba', '2025-09-23 09:19:54', NULL, '2025-08-24 14:19:54'),
(185, 1, '9e56a1940a7850b7362147546ae3198b4e03d8ce227d56161fae8a08119df511', '2025-09-23 09:23:04', NULL, '2025-08-24 14:23:04'),
(186, 1, 'ed17bd98def2d39d5769d1f9312745545e392960656525c9642b8f5ebd7cfc31', '2025-09-23 09:35:41', NULL, '2025-08-24 14:35:41'),
(187, 1, '9f9b55e15f3b0de357616aad30c80c0225207fbe94b702b2bc2fb8a6532c1b8f', '2025-09-23 09:44:02', NULL, '2025-08-24 14:44:02'),
(188, 1, 'b8c6b825ca1e22340b2325ab70b663d7e342ca03c3769b17beb4dbbca421d02a', '2025-09-23 09:57:42', NULL, '2025-08-24 14:57:42'),
(189, 1, 'ff08f160dda027f9c07e91268af2219f2fabe71a736c3753fa353404bd1d5504', '2025-09-23 09:58:43', NULL, '2025-08-24 14:58:43'),
(190, 1, '6805b3636ec7ad8eb765794801a01c186e0416d7970930cc40eac06510cc77a6', '2025-09-23 09:59:13', NULL, '2025-08-24 14:59:13'),
(191, 1, '3b5d20cd5f8109e6224f6b8fbe58432d0463eb224f2887b0a08bc2b79461c4ee', '2025-09-23 10:10:18', NULL, '2025-08-24 15:10:18'),
(192, 1, 'b4ec26ee52079d25cc2f0129bd504fb0c51af1b3500b0fabca39458ba499ebfc', '2025-09-23 10:47:26', NULL, '2025-08-24 15:47:26'),
(193, 1, '25deb3cc5edd05ecb5e9e02e507200f442e4f19c1fcd510239db33fd4c885b8f', '2025-09-23 12:56:38', NULL, '2025-08-24 17:56:38'),
(194, 1, 'b9721a35a39507312ad90bc516b1a6a07154027f9bb6e61beacd2fc08c565aad', '2025-09-23 13:56:35', NULL, '2025-08-24 18:56:35'),
(195, 1, '17ebd9a443aa1ce902ec566ba5a671fbf58cdca43bc0b5ccf39ba5a2aac6084d', '2025-09-23 14:10:39', NULL, '2025-08-24 19:10:39'),
(196, 1, '2c81d1163209666260be42114f5eb157e834bf0c3ba970cdc4fa21bdf8db47e0', '2025-09-23 14:38:48', NULL, '2025-08-24 19:38:48'),
(197, 1, '1ed9c4d9f137436c4db2ce6fe2f853ab96263a4b49f1f5957cf6558d7c342e3d', '2025-09-23 16:22:37', NULL, '2025-08-24 21:22:37'),
(198, 1, 'd6c15a7dafe560c9571a1650e6c00c704e1708a0c8c08058d26ae86c8304cd8e', '2025-09-23 16:27:39', NULL, '2025-08-24 21:27:39'),
(199, 1, '0b5bffec15cd21dfdcaf8495e9c1d803d4249f5f5345f6de98adefafeb0915ec', '2025-09-23 16:40:13', NULL, '2025-08-24 21:40:13'),
(200, 1, 'f9ac7348a97fc837f008b0fa4a1f3c97a019bfa881ce1549c628990bd3914418', '2025-09-23 16:46:22', NULL, '2025-08-24 21:46:22'),
(201, 1, '96d3dd945282d9f9820fcd0016a0c8c1496cb9abcda1c59496b548a3a3e7ba3b', '2025-09-23 16:47:26', NULL, '2025-08-24 21:47:26'),
(202, 1, 'd86f3ad71e3197f64f27ff9f5b1503c1b787c4434c4d295cff7fac7d35ecc69e', '2025-09-23 16:48:49', NULL, '2025-08-24 21:48:49'),
(203, 1, '717d2cb02b46292736549a62db02ac1d39d1de70ac56fe4e8658df0b50ea7dc6', '2025-09-23 16:51:11', NULL, '2025-08-24 21:51:11'),
(204, 1, '99b5ed7004eee3395c1e2656595e0316c777d18e51f35b38c386b8ef13e484d1', '2025-09-23 16:52:05', NULL, '2025-08-24 21:52:05'),
(205, 1, '167b3572a040dfbe4a016be64c0b3765dbfa4efc26fa695ef1718f3d32df6deb', '2025-09-23 16:55:45', NULL, '2025-08-24 21:55:45'),
(206, 1, '18d6cd43fd1dd0576d4930a7b5cbd6d861d6e758ab380410d4e99e29d4788738', '2025-09-23 17:01:25', NULL, '2025-08-24 22:01:25'),
(207, 1, '0d6946012ac8ca44f6b584220151e0df4fe6bf96aa6a1e58bab3b70d80185d90', '2025-09-23 18:09:07', NULL, '2025-08-24 23:09:07'),
(208, 1, 'f9ea393f76fa884e7115f2ab3fa816147527bda6a3687cdb54eafb8d8ac82674', '2025-09-23 19:32:43', NULL, '2025-08-25 00:32:43'),
(209, 1, '9dae1d473de853f882feb1c445b131e6447e2dc24f45b2bfd799577693bc0c4f', '2025-09-23 20:42:13', NULL, '2025-08-25 01:42:13'),
(210, 1, '84e28b6fd84f2fef11f54f0da46af31fe58e5962dcbfabb6bcad72e2676da485', '2025-09-24 06:51:43', NULL, '2025-08-25 11:51:43'),
(211, 1, '7542e1bd9dbc77a5ac1cef732e3defa3b2a679a57b7822e1a79b91fd15a234e5', '2025-09-24 06:52:17', NULL, '2025-08-25 11:52:17'),
(212, 1, '846da3a82c6fe34a99759f8ddb136ad4cec7fa6494a5175208a5eacdcd12571d', '2025-09-24 06:52:34', NULL, '2025-08-25 11:52:34'),
(213, 2, '5270e5565f2708efc5ff0edb97997ed1c84a30bc49b3cbcb7cca66daba587bf2', '2025-09-24 06:52:38', NULL, '2025-08-25 11:52:38'),
(214, 19, '67836deac4e1760d90611785dcffaacd9c95d91a245d04c2e8278c12f40cbe93', '2025-09-24 06:56:00', NULL, '2025-08-25 11:56:00'),
(215, 19, '916906482b54566379b5814e0a14dfdab50fb2f00368db031ce0d6e3c08ea564', '2025-09-24 08:30:29', NULL, '2025-08-25 13:30:29'),
(216, 19, '0232381144392c71b38fed8b1c0163ea6c3fa2b9678e474d7bedeb88e6c92a78', '2025-09-24 09:33:36', NULL, '2025-08-25 14:33:36'),
(217, 19, '834e8dfa28263bae6b7f4e7df2102690cf5e5ae1c7f47520f86741b0139e1e00', '2025-09-24 09:36:15', NULL, '2025-08-25 14:36:15'),
(218, 19, 'c7f0b50375d902da721f6d587d59048ad057f6017f127a3507a586c55786bb86', '2025-09-24 09:37:07', NULL, '2025-08-25 14:37:07'),
(219, 19, 'ed8079f17053aa3ccfb7052c6f84d93a0725e2888e2f03686566ebe4ad0ee64d', '2025-09-24 09:39:31', NULL, '2025-08-25 14:39:31'),
(220, 1, 'b9c9826a792f8e3d28db7a60a0bebfbb66f7d53596df9c1fa54e029cc0d4db5a', '2025-09-24 10:02:31', NULL, '2025-08-25 15:02:31'),
(221, 19, '86e1a68cbb47f018e81ad141099c967ef2a4ea5802bc51ca5b565b85a2db05cb', '2025-09-25 09:33:50', NULL, '2025-08-26 14:33:50'),
(222, 1, '26ed7de2370326f9b8003ddad8435438a064b30ed1003312981c49c994935dcb', '2025-09-25 09:35:04', NULL, '2025-08-26 14:35:04'),
(223, 1, 'c808f0412054de7765f8dae2a5019f7bdeab3d5afaf22ff5148ceba25529c03a', '2025-09-25 12:00:24', NULL, '2025-08-26 17:00:24'),
(224, 1, '325c31caddfd6aa16751d08ad2e712d71307cce6c01c9e976ff2207e217de19f', '2025-09-25 12:18:27', NULL, '2025-08-26 17:18:27'),
(225, 1, 'dbab4826331b2075837df445769dedf9870a3806ff0f415a7801fcf5ab911608', '2025-09-25 12:55:21', NULL, '2025-08-26 17:55:21'),
(226, 1, 'd12ff828fa672fc15f3cff6a97e295ecb21b5ff74ce19cb3759c256938a34f7b', '2025-09-25 13:05:26', NULL, '2025-08-26 18:05:26'),
(227, 1, '6efdbd5bc531f98509ed48c888acb36319e8523ead0019cf2b69294689d3ff5d', '2025-09-25 13:32:28', NULL, '2025-08-26 18:32:28'),
(228, 1, '209320103ff4ac272fa50ff04752d0a1d5f18982a0d5313de9343bcb6be037f8', '2025-09-25 14:39:31', NULL, '2025-08-26 19:39:31'),
(229, 1, '59ca8a2be486121fc5f0443a0ae0d2cd6bdabefde417045edd429fea73617706', '2025-09-25 16:24:58', NULL, '2025-08-26 21:24:58'),
(230, 1, 'e66892b5a65a2232aaff8af4fdb1a2f8f4a0903b7a75e7c9ed5237c8bb40b737', '2025-09-25 17:26:21', NULL, '2025-08-26 22:26:21'),
(231, 1, 'b4618dc5c263ea822d4165400f4e4214071993de05034c4ffc7b5fd0224e7890', '2025-09-25 17:46:31', NULL, '2025-08-26 22:46:31'),
(232, 1, '9336e0cbacf48cdf6a49cf6574050d5a774a4dc9dea9d43cbbaeedd890e09b87', '2025-09-25 18:20:22', NULL, '2025-08-26 23:20:22'),
(233, 1, 'e67d3af47230182af4001eafd2d7aac84c1dd808fd610a690ced64cb38fff1de', '2025-09-25 18:40:11', NULL, '2025-08-26 23:40:11'),
(234, 1, 'b9b6a15ceb37774324a01c4c288975f3aa201239f7e37f9d102a8abef5a8da1a', '2025-09-25 18:41:14', NULL, '2025-08-26 23:41:14'),
(235, 1, 'ad247c14fe7eb6d54d643e2189b29a67c4319f66814bbc17f4fb2f8a98729c14', '2025-09-25 19:13:18', NULL, '2025-08-27 00:13:18'),
(236, 1, '05f6829ef64e792084202cf02a10313d7fdd48915288b68d3d54c3d9416b4ecb', '2025-09-25 19:42:03', NULL, '2025-08-27 00:42:03'),
(237, 1, 'ccdbb177c3231ef0249aa96c33ead18d9abe03d8693106557247447269a0fc38', '2025-09-25 20:43:08', NULL, '2025-08-27 01:43:08'),
(238, 1, 'c46bfd34136fec8ff6664c4126ba33be7103f4e56b8ccefb068152489cd689df', '2025-09-25 21:42:29', NULL, '2025-08-27 02:42:29'),
(239, 1, 'fbd167ec583b313c52929148e82d114815b0c09fed801b06e28b0e202fee060f', '2025-09-25 22:02:42', NULL, '2025-08-27 03:02:42'),
(240, 1, '8f09cbae0028c161cf87fa3b90d8297e3d4c615bd3e846928be44bfdfd5eabfb', '2025-09-26 04:21:38', NULL, '2025-08-27 09:21:38'),
(241, 19, '6b9967986d5a10563d19c1364fce146e48919776a3dd91a17600374f869894cf', '2025-09-26 07:01:12', NULL, '2025-08-27 12:01:12'),
(242, 1, '64135e1acfa786c51b8d643464e3b241befebb60b107c8c27815af52a0de7dd3', '2025-09-26 08:46:10', NULL, '2025-08-27 13:46:10'),
(243, 19, 'fffced1b802555ac73e7f23a08ea92acfdd93fbbef78c65dac8b9ec118517655', '2025-09-26 08:46:25', NULL, '2025-08-27 13:46:25'),
(244, 19, '03f459110334a3fee2f7d7d0774f8e19d5278833ec2e2899ab54042d3adf562f', '2025-09-26 09:01:40', NULL, '2025-08-27 14:01:40'),
(245, 19, '0572cf98511ae8f3ed19f9267c9296edad1e473173fd7fe02855e7989c79fc2b', '2025-09-26 09:04:14', NULL, '2025-08-27 14:04:14'),
(246, 1, 'ee578179baa4d825eeb9587d38cc4a5e8faa3dde7ef9e202caef2db0c163953d', '2025-09-26 09:06:49', NULL, '2025-08-27 14:06:49'),
(247, 19, 'b418320da931140fb65bb1357b13942f2e0e956702d17c7c4175d9661d7f092b', '2025-09-26 09:10:16', NULL, '2025-08-27 14:10:16'),
(248, 19, '9cb8e317fe47055fb01c314c9a112904caaabd5f7657888245a1cbce7a7578ee', '2025-09-26 09:26:25', NULL, '2025-08-27 14:26:25'),
(249, 19, '692e15bf7180b293c1772537fceb91a36720703eb4c8821d6449f1f7a29e4462', '2025-09-26 09:34:32', NULL, '2025-08-27 14:34:32'),
(250, 1, '7b9f52dfb161ef5eec8b38e952911e6eb19365749ccf67007233d0563d324a2b', '2025-09-26 09:34:44', NULL, '2025-08-27 14:34:44'),
(251, 19, 'c5789d97f809624e8935b7393da0dd8d77657ff47c087bf97648853bb4073215', '2025-09-26 10:15:47', NULL, '2025-08-27 15:15:47'),
(252, 19, '4a432c9b3f83147391ab06334b8f24c93e31158a05b6c2cd820494f9dae8c9a8', '2025-09-26 10:28:12', NULL, '2025-08-27 15:28:12'),
(253, 1, '4b66b8aaea7a70b024ae2653ff3544db91f77b84e1d31f663620393909325a6a', '2025-09-26 10:28:30', NULL, '2025-08-27 15:28:30'),
(254, 19, 'cc38d84d005cb80838a6c74eff26f1cfd8c761cb90de68b4d03ae49197cb6a61', '2025-09-26 10:44:52', NULL, '2025-08-27 15:44:52'),
(255, 19, 'e8bf1caf9737c8dde31adfb50a6826d67d347c82f1780816bb23a378acafcdd1', '2025-09-26 12:03:26', NULL, '2025-08-27 17:03:26'),
(256, 19, 'f1fbb91bf96fcb8cf1b3f58ac6013a970848384190c71468bcddc9c8edd2b075', '2025-09-26 12:16:43', NULL, '2025-08-27 17:16:43'),
(257, 1, '1c1988b3e490d637d6c0ef99458088eb2e9de5db6d51e767244a922a6074e1d4', '2025-09-26 12:24:23', NULL, '2025-08-27 17:24:23'),
(258, 1, 'bd434335860a587e99777b473ab014e65d3f5f0a7f3e663901cf9274044c9900', '2025-09-26 12:40:32', NULL, '2025-08-27 17:40:32'),
(259, 19, '7857bd82500975fc9bfa8368a7dcc589f3304cb410f7e157cd92365d7c351756', '2025-09-26 12:40:57', NULL, '2025-08-27 17:40:57'),
(260, 19, 'da150d6616c9808c3d4a886697f8c97b8c2f7d04f823d9fbb0f6e1a5b6bdf7d9', '2025-09-26 12:45:36', NULL, '2025-08-27 17:45:36'),
(261, 1, '6ab0236936872ba712d15bb5fcd4cb59b9dd76d444b953d620a780a86cf82091', '2025-09-26 12:46:11', NULL, '2025-08-27 17:46:11'),
(262, 19, '150f96eca577d0abb29b84cac616ca9bfea6ea2ec2e40e0036c504e457e08af8', '2025-09-26 12:50:02', NULL, '2025-08-27 17:50:02'),
(263, 1, '5cd19ba4cea7e452b09b00d23aff449b30bbcdff8ef4cbc8480629b0eb6611e4', '2025-09-26 13:08:54', NULL, '2025-08-27 18:08:54'),
(264, 19, '17ee959aff30effc58e31d6557dc3ca266caf72446412f49fc3ec7bd891b2352', '2025-09-26 13:48:00', NULL, '2025-08-27 18:48:00'),
(265, 1, '64549c9e0ad3826e16a1c53b5e2536c4bcdfcfad3dbade8bc41b318b9dfab4ef', '2025-09-26 13:48:06', NULL, '2025-08-27 18:48:06'),
(266, 19, '9ae35624d1e05dcba92505f3279577de17c495272898a37d391d57a429339572', '2025-09-26 15:25:50', NULL, '2025-08-27 20:25:50'),
(267, 1, '9c16c39c38fedfb378e17ab2544c58bfc3f46650f09191422c95494f707148bf', '2025-09-26 15:26:27', NULL, '2025-08-27 20:26:27'),
(268, 19, '18e6034e0fb209f196febc8e376d008a159257de81432ab6c063fc37a59032bd', '2025-09-26 15:33:55', NULL, '2025-08-27 20:33:55'),
(269, 1, 'ec23d14352d01c3abb0a8bb1857ccf2e7978cd378ff07ed903d27862894fceb1', '2025-09-26 15:36:20', NULL, '2025-08-27 20:36:20'),
(270, 1, '45d9846d138ec830c3407fdc140a0edd190ba9423ada445f1f3e8a0eb5b66b8a', '2025-09-26 15:39:34', NULL, '2025-08-27 20:39:34'),
(271, 19, '3b830ee48ee38fc9709720d7613daa575efbf508d80ddb224774ab4a836c4356', '2025-09-26 15:44:36', NULL, '2025-08-27 20:44:36'),
(272, 1, '99c40f30fe75cf0cfe8bf8583fa46f12b590fb33ae28597e75ce2fde9e27e052', '2025-09-26 16:46:01', NULL, '2025-08-27 21:46:01'),
(273, 19, 'e190c32e7a92484ead9f2653d8f65674995afb0848f80a745108a6bc10f09014', '2025-09-26 17:19:59', NULL, '2025-08-27 22:19:59'),
(274, 1, '4c797de15a96be26ef3a94d02e5f6276d29d85fc4a67ffe402192be2c3c96f11', '2025-09-27 05:19:53', NULL, '2025-08-28 10:19:53'),
(275, 1, 'a49a815e1ef8b53300a2ac98936c6dd98ce3b0b49d8d824567f2ff39c23e079a', '2025-09-27 06:47:04', NULL, '2025-08-28 11:47:04'),
(276, 1, '61dbf7269738168a288550b8a7ad7539ab8f653fdd14d708d8189e11d3329480', '2025-09-27 08:34:56', NULL, '2025-08-28 13:34:56'),
(277, 1, '25443b6bb8dba8805fa535d838f21e85a9e6af15e77aa862e3c3c80ae539902f', '2025-09-27 14:21:19', NULL, '2025-08-28 19:21:19'),
(278, 1, 'a41a5914095dd4264c5dd8cebfa879f51a0dc2cee388b7e6eb218cc9d03802e3', '2025-10-03 07:25:19', NULL, '2025-09-03 12:25:19'),
(279, 1, 'b3eb80997debc5b66dd42777b7fa137ec1cd0d3bdeda9b85c05f77646e5ca97c', '2025-10-05 09:16:22', NULL, '2025-09-05 14:16:22'),
(280, 1, '92a9587382439d2ef2ec80167134005c0e7b8acf4ebb1a25a981a6c374b97011', '2025-10-05 11:02:19', NULL, '2025-09-05 16:02:19'),
(281, 1, 'a6c38cfc51fa85ec935cf27b47de4bfe8c5c53660f02528b874a92676cedea16', '2025-10-05 11:31:19', NULL, '2025-09-05 16:31:19'),
(282, 1, '2e7ce32222b737646255d5720a96ae7819b91101e628c88a59a6d28709973fff', '2025-10-06 11:39:31', NULL, '2025-09-06 16:39:31'),
(283, 1, '91383654a29816626587ea5321f1dc8cefcea10e43512db22f51a053c0cf5725', '2025-10-10 09:50:22', NULL, '2025-09-10 14:50:22'),
(284, 1, '06bd6fb27223c5514ced9e42125d651e095c6bd21dd39b550e059dbcd018db5c', '2025-10-10 10:54:49', NULL, '2025-09-10 15:54:49'),
(285, 1, '7bc24408d7c322d54f786614232d01570cd882ce3fc81531291188103911add6', '2025-10-10 12:00:45', NULL, '2025-09-10 17:00:45'),
(286, 1, '69cd02aefa9c0117af0f2b98cf870fe41c3ad73ed75ca17b36a1204cb72e8c1e', '2025-10-10 12:56:42', NULL, '2025-09-10 17:56:42'),
(287, 19, '306b6bf6dab8ae898749c2bbc728d190de0efc3e82a7096ffff1f1c4e302354d', '2025-10-10 15:52:04', NULL, '2025-09-10 20:52:04'),
(288, 1, '59d7807878c7b0de6387f2a0448a8d876cca7fac35ed33ccaa713b58784909d5', '2025-11-25 10:01:43', NULL, '2025-10-26 16:01:43'),
(289, 1, 'a8050a35474294ff938d41d9d2b063a8c824e57a341e1c6d4629ccff6134489e', '2025-11-25 14:49:19', NULL, '2025-10-26 20:49:19'),
(290, 1, '08747c8bb21f34a3646c3878232dfe3d4ac1c700adfc28f7a16420aac0cb43dd', '2025-11-25 16:32:59', NULL, '2025-10-26 22:32:59'),
(291, 1, '1b30d85e71370924cea753a27cd3fce1445584e45ac3cea1ae35a6e15719d640', '2025-11-27 14:06:36', NULL, '2025-10-28 20:06:36'),
(292, 1, '83514b6dd7622cfa284f8d91430e65203a6660b7e2c0c6d838f2743d75a9ad63', '2025-11-27 14:47:05', NULL, '2025-10-28 20:47:05'),
(293, 1, '79f0b6443ef7bdd45e2edd169e25fc71ebced239aa2e21f885c968babab15d73', '2025-11-27 15:11:42', NULL, '2025-10-28 21:11:42'),
(294, 1, 'a2b11db05e5fb4faa8b91b35d0e512dfb14a2bade2fdc2ed467789f8eb151ce5', '2025-11-29 17:04:59', NULL, '2025-10-30 23:04:59'),
(295, 1, 'ae327521b9b2fbb98881c6f3721575179ad0a15f69d51bd0ec3e0f9e86d729c5', '2025-11-29 17:29:23', NULL, '2025-10-30 23:29:23'),
(296, 1, '4e09acbae9b406fa903b39719eeabd0736aa318dc2bac1901ef65eac2c03b73d', '2025-11-29 18:15:51', NULL, '2025-10-31 00:15:51'),
(297, 1, '84d860ffed7bca327f8b0634cc80618a795873a7073b36e2aeff61ea572e4fbd', '2025-12-01 08:40:43', NULL, '2025-11-01 14:40:43'),
(298, 1, '003b54f8c927fd98a32dc2e0bbfbd6e0d9816160e7936cc3999d6188f5fbcc04', '2025-12-01 08:48:51', NULL, '2025-11-01 14:48:51'),
(299, 1, '1e6a7776e82dfbaa06f7034772d47700c88d6b55e103e740f5ab4e9135388145', '2025-12-01 08:53:42', NULL, '2025-11-01 14:53:42'),
(300, 1, 'f34d237eaff4e9118d1f453772717f101016bfc463c530eafd2174ad5b117865', '2025-12-02 08:04:42', NULL, '2025-11-02 14:04:42'),
(301, 1, 'cbb11877d5e3a278d506ed711f19adff32f3a60613f928e4770c795a923c977f', '2025-12-02 08:15:05', NULL, '2025-11-02 14:15:05'),
(302, 1, 'fd05bf1adc2e0ef1364298cf2b3fb65c1a09ed8c08f75278345d8f07e1c05e0c', '2025-12-02 08:23:37', NULL, '2025-11-02 14:23:37'),
(303, 1, 'ca78a26680c36ceb89b0989d9e5c04af3fbfaf42e6d3a810a32cb26a123297ee', '2025-12-02 09:01:54', NULL, '2025-11-02 15:01:54'),
(304, 1, '91a6fdb8176dce57dc2a1b209fd78e410234fcfdd1354af77591f44092a839db', '2025-12-02 09:15:39', NULL, '2025-11-02 15:15:39'),
(305, 1, 'a771cc716c46354f2c7e707b84f9f6eb4e8477753c4565a47489a76d5dba3620', '2025-12-02 09:15:53', NULL, '2025-11-02 15:15:53'),
(306, 1, '43f4efbda6252630fcc6df173360a60c54be728f6afeabe7233140e7b173a8ba', '2025-12-02 10:56:03', NULL, '2025-11-02 16:56:03'),
(307, 1, '38ff5fd82a98cd2e20a6178bd8253d3f7fa3402f52270dd6b0ebcf6871d15c8c', '2025-12-09 15:16:57', NULL, '2025-11-09 21:16:57'),
(308, 4, 'a94a59aac11cb0fcb2d22b017376e20c4fa6a17f58c453d758331f9b55b97784', '2025-12-11 13:57:41', NULL, '2025-11-11 19:57:41'),
(309, 4, 'e688867076d0ebb7bd27ee798c2a7f9df5fbb51a4b3cfaf5227ec5088ce76b59', '2025-12-11 14:32:59', NULL, '2025-11-11 20:32:59'),
(310, 4, '0eed8b81b61fe6817ddb1b6b79115da86eb9048d995b93a2ef1119f24eccee1a', '2025-12-11 14:37:54', NULL, '2025-11-11 20:37:54'),
(311, 4, '1a8a4b2b93a25a01d99c37e1ae5aba747409972bfd22167bafeaefa64a35a197', '2025-12-11 14:59:12', NULL, '2025-11-11 20:59:12'),
(312, 4, '421897679fc761daa1ff019854322c05e331a42af89fe9510ebf9cc8c9a9b2e2', '2025-12-11 15:03:26', NULL, '2025-11-11 21:03:26'),
(313, 4, 'ff402628062e34b1e7e0fbf55b5d1e65213d582327cca6d3da3f1cf345d298e2', '2025-12-11 15:05:14', NULL, '2025-11-11 21:05:14'),
(314, 1, '64c065d74f380cbb0a6808c60db3da8cdfed96d90e225cb32cf05ca8ac5bebc1', '2025-12-12 15:20:49', NULL, '2025-11-12 21:20:49'),
(315, 1, '3c771ad6baa76f7e7e703f8565ded2917cadf6ebb87f097c34c3b9a3ffe3778a', '2025-12-12 15:30:05', NULL, '2025-11-12 21:30:05'),
(316, 4, '24041dbcb599d895676b4f2fd4b61f63230aeee4aee90830c01498634bdd4dfa', '2025-12-12 16:00:24', NULL, '2025-11-12 22:00:24'),
(317, 4, 'cb14a16646eac344f9a20fcfa632263e1b0bbbad8a0276d40871ceb5d557c390', '2025-12-12 16:06:50', NULL, '2025-11-12 22:06:50'),
(318, 4, 'e840a160cd9dc0218f98c911682e085aac343f7d427e1ee21ea649a42939e725', '2025-12-12 16:11:21', NULL, '2025-11-12 22:11:21'),
(319, 4, '2e51d5737649de95b72b5bda066a16df043e87162717b01da2c7ebcc8472bea2', '2025-12-12 16:11:35', NULL, '2025-11-12 22:11:35'),
(320, 4, '7c5eacb7fcbabd4fefb1c8aaaca407828c6a5690c783345254d1dc1032c7157f', '2025-12-12 16:20:40', NULL, '2025-11-12 22:20:40'),
(321, 4, '41886ad2d43e19fc18ed27fcef233e88762773d31ac283be794511ada2c6d288', '2025-12-12 16:21:00', NULL, '2025-11-12 22:21:00'),
(322, 4, '518becd5cbfa3dedd31d7a42f976e7d7ce745758893a3c90d6afdd09784f560e', '2025-12-12 16:22:32', NULL, '2025-11-12 22:22:32'),
(323, 4, '2847639c1f35352b24ff4c600ac18124398c8bfa1e4a1890ecdbd2c3543c2558', '2025-12-12 16:22:43', NULL, '2025-11-12 22:22:43'),
(324, 4, 'ac003744fafb2befafd73a2861a39a768888baf661e566a92f646d025432ea8e', '2025-12-12 16:56:35', NULL, '2025-11-12 22:56:35'),
(325, 4, 'b24325e696f37b6eccefa05701ef2114f48b595e4753cc1410d946253a58d913', '2025-12-12 17:17:12', NULL, '2025-11-12 23:17:12'),
(326, 1, '3a35efe8aa2c2cf61e8cdb92f579723d9204312fbaa5e6a377de0233cf5aee2d', '2025-12-13 04:28:54', NULL, '2025-11-13 10:28:54'),
(327, 4, '97f0cf3c421a97bba8233ee8d402dc88d80dccd31d7c8653a81421c340bc9fd4', '2025-12-13 04:29:42', NULL, '2025-11-13 10:29:42'),
(328, 4, 'cf81b6f57fbc65b632853739aaf98f570fbf0b2e38273b31fad3ea99751c934a', '2025-12-13 04:30:00', NULL, '2025-11-13 10:30:00'),
(329, 4, 'b506cd855ba3fdaf6fc7eb3e1dd6e5a6648e9360ff5fad9df6857240edfaa008', '2025-12-13 04:38:58', NULL, '2025-11-13 10:38:58'),
(330, 4, 'a76e57ca6f67e54b4495dca9c63f72d90e2434ca8a43c50bfa5c58d0e1ac5a73', '2025-12-13 04:44:41', NULL, '2025-11-13 10:44:41'),
(331, 4, 'e02b775ac95e9d3f735f39e3ca891d0545fc2a2262bccf74803502ef0195d97b', '2025-12-13 06:22:15', NULL, '2025-11-13 12:22:15'),
(332, 4, '7843e07754a34dc4a590c1d0c596f14b8fb58498276f6eed23e800499f88bd53', '2025-12-13 06:26:08', NULL, '2025-11-13 12:26:08'),
(333, 4, '21d7d2574537e40145da7ebaeb614554077d335ab1ec41e17722feeffd4fd237', '2025-12-13 06:29:53', NULL, '2025-11-13 12:29:53'),
(334, 4, '9207562ed243e0233b77c73cae9871ba0d6513a5340cc27385f404d7b360feb5', '2025-12-13 06:32:51', NULL, '2025-11-13 12:32:51'),
(335, 4, 'b34fb4d140837e4d6769e0321a1a9d996ad8f201bed1c1a20758dab5c71dcd2b', '2025-12-13 07:34:34', NULL, '2025-11-13 13:34:34'),
(336, 4, 'a543a1338454eb78564cff51656ee124ceacd19e7fd16e248d6d32f90c2b3e6a', '2025-12-13 07:40:27', NULL, '2025-11-13 13:40:27'),
(337, 1, '1dc1ead9218b0811c1971abffcf47aa642d93af7c6d87a3b881d310ab9a538da', '2025-12-13 07:41:58', NULL, '2025-11-13 13:41:58'),
(338, 4, 'a3c23953b1fe6d67aa647dd62bbac771040441cf28285c1c7e5b5a511bef7290', '2025-12-13 09:56:34', NULL, '2025-11-13 15:56:34'),
(339, 4, '21acf970c3ce28a92a05c4164db1d1a735a5412488bab6a6120e7d532a51ab65', '2025-12-13 11:16:52', NULL, '2025-11-13 17:16:52'),
(340, 1, '6e3119482bbdcf20d9790c3d529ae20ef06ba3842b62318bfe99b04c3b1a7964', '2025-12-13 11:20:38', NULL, '2025-11-13 17:20:38'),
(341, 1, '7833205ea41f35accdb56b89b5e3160d8f3bad0ccad13dad65a3686a03366b40', '2025-12-13 13:15:16', NULL, '2025-11-13 19:15:16'),
(342, 4, '3e5493d4453df708d14a7ba97e657db74835ec21ab69021b9c777caa6c3f1fa7', '2025-12-13 13:16:34', NULL, '2025-11-13 19:16:34'),
(343, 4, 'e0f571815e43b0e7b85bfac8b00c77568d32cabf17b75e7af986d8647e0f5369', '2025-12-13 13:16:58', '2025-11-13 21:17:13', '2025-11-13 19:16:58'),
(344, 1, '0dc2748845d47f19f6647458db6b11632f57599128def1b7f644504a64f2a6c9', '2025-12-13 15:09:04', NULL, '2025-11-13 21:09:04'),
(345, 4, 'c03ab2f1e111f8c0d3b93801fdc096eb2aad36beb97d11ac05294c6c42afaa3a', '2025-12-13 15:17:13', NULL, '2025-11-13 21:17:13'),
(346, 1, 'bec8c28ee2e6ecc339d82e726a402af09fcbbcd58ff379010dec30db4f7830cc', '2025-12-15 09:14:35', NULL, '2025-11-15 15:14:35'),
(347, 4, '26b43e877c54eefe2cbd111a999b10891ac2965e116b3c176cdfb6b2d0e5ccaa', '2025-12-15 09:19:25', NULL, '2025-11-15 15:19:25'),
(348, 4, '2a559b1dfb5a548176b71113a9e97ef4ab24a514873ab2a4b06174718ed2a349', '2025-12-15 09:21:54', '2025-11-15 19:51:00', '2025-11-15 15:21:54'),
(349, 4, 'fe3e8d3fd25d65bc5168525457421ec7b692aa9ba48b8daf18136bd62b8c8eba', '2025-12-15 13:51:00', '2025-11-15 19:51:05', '2025-11-15 19:51:00'),
(350, 4, 'ed58803483a72f6f54521d53ff4c63f986cd9c4696c18c0f6100d902c82c11f3', '2025-12-15 13:51:05', '2025-11-15 19:51:15', '2025-11-15 19:51:05'),
(351, 4, '22dc7fc95bfb3c763e60267cd7465e49f7e48b63630f9444a7a5b525fb4df2d7', '2025-12-15 13:51:15', '2025-11-16 00:58:58', '2025-11-15 19:51:15'),
(352, 4, '10f1f1b21ba39c7fd97dd6dee34150fd61254e8d4b8b2c4881a10d2bc89fac44', '2025-12-15 18:58:58', '2025-11-16 00:59:08', '2025-11-16 00:58:58'),
(353, 4, '86a3324f649cdeaf5ea87c1faab2829c080e2d9d11b2178b8267d166ddf5a8b8', '2025-12-15 18:59:08', '2025-11-16 00:59:08', '2025-11-16 00:59:08'),
(354, 4, 'd1a9dc610e343fcc8e728d04aca0fd12f0321ca36f2735a0a0e98c3ac38c7008', '2025-12-15 18:59:08', '2025-11-16 00:59:19', '2025-11-16 00:59:08'),
(355, 4, '152d797d9d3dc811a19726b65ffde91b719a877fa4e76164bb02f92e517163ce', '2025-12-15 18:59:19', '2025-11-16 20:38:24', '2025-11-16 00:59:19'),
(356, 4, '9e8cc5856a204657d4c716ae2cd51db9d9974f719e6cd25b3c700670bd0ff85b', '2025-12-16 14:37:54', NULL, '2025-11-16 20:37:54'),
(357, 4, '4be9d0da1533380f5743db5fa35fbc53099d76f9fdc9b629172bec17fb288d8e', '2025-12-16 14:38:24', NULL, '2025-11-16 20:38:24'),
(358, 1, 'be8ef7cc4061f084fbccd7372ee6590ac6e68453564f08e4536494778d23109c', '2025-12-16 15:21:49', NULL, '2025-11-16 21:21:49'),
(359, 1, '7856f3bf5ab38241e353c778a4aaaa3277a822229db5a1c00eface65d13925ef', '2025-12-16 15:59:00', NULL, '2025-11-16 21:59:00'),
(360, 4, '5f3ecfe2403c9606b8354d8095e8f4e68144bee025f77e41629e466af7f6a6ef', '2025-12-16 16:03:22', NULL, '2025-11-16 22:03:22'),
(361, 4, '0f50ae704f72ea1730ba0687df3ff21b6c8ed0a20a20822bf03726e6e000b1db', '2025-12-16 16:06:37', NULL, '2025-11-16 22:06:37'),
(362, 4, '003197663e52bcb86ebfeb6e62bc58c89f8f49edd2428dd061af278da56e5da6', '2025-12-16 16:07:31', NULL, '2025-11-16 22:07:31'),
(363, 4, '357ef4bcbe78afcd11a49bcf1fab1403505f26044363b7defe529869cf546a79', '2025-12-16 16:15:32', NULL, '2025-11-16 22:15:32'),
(364, 4, 'da517d9b3e186eec12b6546f6cd811c1e84ac8b88fe2d410acdcb979df8b18a1', '2025-12-16 16:17:24', '2025-11-16 22:17:24', '2025-11-16 22:17:24'),
(365, 4, '69c0e15b43501acad807cceb3251946b412946f62eca019d15c56f6a42cca955', '2025-12-16 16:17:24', '2025-11-16 23:17:30', '2025-11-16 22:17:24'),
(366, 4, '0fef79e59e1888f6437e169a9d3d22f79f48b155bc0ee257de625da402e484ee', '2025-12-16 17:17:30', NULL, '2025-11-16 23:17:30'),
(367, 1, 'b5d8d5e6819a5feda78dd03fe9374bbc54b828dd514e0447786d7a5b4bd3b337', '2025-12-16 18:31:44', NULL, '2025-11-17 00:31:44'),
(368, 1, '4d00ffe4835fc8b3895b2d23267051b4bc70dbf645b29fad45eb7851b83d47c3', '2025-12-17 10:53:00', NULL, '2025-11-17 16:53:00'),
(369, 1, '182f1646e578e2f542c76b4dae698f219aafcde156963249857c946af6cdc793', '2025-12-17 11:10:03', NULL, '2025-11-17 17:10:03'),
(370, 4, 'b4b188a82205afffff2af438c3c884c26ade29360fb612db0eb1239bc78052e9', '2025-12-18 05:01:01', '2025-11-18 11:01:04', '2025-11-18 11:01:01'),
(371, 4, '6e457984af8a2e3b967e08f53c5cc6b8ccd09325e615fd143f8a89c473e2b18f', '2025-12-18 05:01:04', NULL, '2025-11-18 11:01:04'),
(372, 1, 'f9e89a67970b5c7b63b38cc6b91286508380c76768ccc0ff0872029b1bcd2e62', '2025-12-18 05:01:08', NULL, '2025-11-18 11:01:08'),
(373, 1, 'e646e6530bd6bedd592269ead85b4de022023076079063e94888fd3e331b4597', '2025-12-18 06:17:43', NULL, '2025-11-18 12:17:43'),
(374, 2, '1ae292751b75cffd7cbbb78faea44f00b05520f8c7664a13eab77a89d9e607c0', '2025-12-23 09:31:15', NULL, '2025-11-23 15:31:15'),
(375, 2, '6af29b83ca8b131764df550db255799bc36ef95e72295c6ee547e60d80b310a6', '2025-12-23 09:40:14', NULL, '2025-11-23 15:40:14'),
(376, 2, '4f3a10d28a98b1cc2f63421e738da8a2579c50cf29c252f3c8d6cbdee5d5d8ac', '2025-12-23 09:48:37', '2025-11-23 16:44:24', '2025-11-23 15:48:37'),
(377, 2, '36e34914bcd6e8eb7a9f543d429c744f86af28f0181b5d5e866d546cc22b8e42', '2025-12-23 10:44:24', '2025-11-23 17:27:34', '2025-11-23 16:44:24'),
(378, 2, 'efbac28d99037d69f30b499fdbed88b5b5ccd85de135accf574da677a27ffa23', '2025-12-23 11:27:34', '2025-11-23 18:23:24', '2025-11-23 17:27:34'),
(379, 2, '5db4f29db68d9a0cf1a4819f92397febf767a26afc8dbbfae1e3a115df222660', '2025-12-23 12:23:24', '2025-11-23 19:18:24', '2025-11-23 18:23:24'),
(380, 2, '557b993e2f47c258e18b4575cee2e440a23baa6978da1ebfa204177c86be7fa3', '2025-12-23 13:18:24', NULL, '2025-11-23 19:18:24'),
(381, 2, '037297f81c6d73cbe9501762bfa9613ae9056d2fcb88c9357bc302c4e70aa760', '2025-12-23 14:07:50', '2025-11-23 21:04:05', '2025-11-23 20:07:50'),
(382, 2, '1783e75e1ed156ae3d7cf1bd1902e694f243391cf1876de7ad657656a9bf9964', '2025-12-23 15:04:05', NULL, '2025-11-23 21:04:05'),
(383, 2, '934472d27fe8d2bd81403d5d3b725994083a87c6a217db5834778c863860bd76', '2025-12-23 15:39:12', NULL, '2025-11-23 21:39:12'),
(384, 2, '6f5af7058d8f6f5047a614e4b7b9470c4e136d7f2fbb506c283ca3ef15dc9086', '2025-12-23 15:40:36', '2025-11-23 22:38:24', '2025-11-23 21:40:36'),
(385, 2, '187f94b11d245594a03575ec693d5485141f1b51a062e605e26c7d1474e4e209', '2025-12-23 16:38:24', '2025-11-23 23:26:16', '2025-11-23 22:38:24');
INSERT INTO `refresh_tokens` (`token_id`, `user_id`, `refresh_token`, `expires_at`, `revoked_at`, `created_at`) VALUES
(386, 2, '67135f4768f7e7968b56693179f31c75d24ad810c53fe35177ce42bed4f680c9', '2025-12-23 16:48:54', NULL, '2025-11-23 22:48:54'),
(387, 2, '39c46dbed6bcbc2c65b8a8058ac546e6a0414d36ee9653bb1a6bbb4cec74d439', '2025-12-23 16:49:14', NULL, '2025-11-23 22:49:14'),
(388, 2, 'f0c64fa6110e93e48f11330ad1efb4133f3f51158d477b083453b6c97130152b', '2025-12-23 17:26:21', '2025-11-23 23:39:00', '2025-11-23 23:26:21'),
(389, 3, 'bbeadb7b9b8b0c67a71419f22b7bca985b53a7eac17b362930cc9a6b72be0740', '2025-12-23 17:39:05', '2025-11-23 23:48:50', '2025-11-23 23:39:05'),
(390, 3, '0415f62bce44fdef526981e09c5318a253237928f0e7a224a78dd27d01878071', '2025-12-23 17:42:48', NULL, '2025-11-23 23:42:48'),
(391, 3, '80f73f1c80be8296d40dcc46103b12dcf5474b10c6bd7478066019fe7a7eecdb', '2025-12-23 17:42:52', NULL, '2025-11-23 23:42:52'),
(392, 3, 'def5046c94575920950e3383dd77499270c2feee4af30a9faca773b3d305e919', '2025-12-23 17:49:00', NULL, '2025-11-23 23:49:00'),
(393, 3, '0aaa1e242a31c7e51a04a552c31d8caed4c51a5d7343c7f76a3b42d5002a8129', '2025-12-23 18:33:42', '2025-11-25 11:40:06', '2025-11-24 00:33:42'),
(394, 3, '04e94e29c01185794bcd2897306f41b1a7ede037c075a507a1948967b0ecb2a4', '2025-12-23 18:33:47', '2025-11-25 11:40:17', '2025-11-24 00:33:47'),
(395, 3, '77f31d811fb8ee98489976da3e10bbc5a97f3a3f9c189dee7677d2a03f08fd78', '2025-12-23 18:38:06', '2025-11-24 00:38:29', '2025-11-24 00:38:06'),
(396, 1, '65f908f1129ab48e2d36f66dff9743401d057e1f38973fed769c8cdd9fe3833b', '2025-12-23 18:42:31', NULL, '2025-11-24 00:42:31'),
(397, 1, 'c92c8300f65f96704782c0059bd0562aebeca93c7f21aa01ddf372246ad6e462', '2025-12-23 18:55:41', NULL, '2025-11-24 00:55:41'),
(398, 1, '4c312739c928bcc58e3774ed3edf30c92e70dee6d8b84d64dc9cf04575fb31dd', '2025-12-23 18:57:03', NULL, '2025-11-24 00:57:03'),
(399, 3, '5b03248917db814da46b4d26350791e5fe33c8de7b5521bbe222caaf3960fa46', '2025-12-25 05:40:06', '2025-11-25 11:43:12', '2025-11-25 11:40:06'),
(400, 3, '5c497d146fc770b67640dc451a9705dc6e832372f9d32a2cba186102753a2c4d', '2025-12-25 05:40:17', '2025-11-25 11:43:27', '2025-11-25 11:40:17'),
(401, 3, 'b0418f29c62ae27e91a3e1a99f1f464fd13168f275d203d7ccccf4ea5e141f19', '2025-12-25 05:43:12', '2025-11-25 13:21:15', '2025-11-25 11:43:12'),
(402, 3, '45eee3b2d71a7d33f28ca24263a7f57a0cbb4f9c7557a268d8f16d20ddd915e9', '2025-12-25 05:43:27', '2025-11-25 13:21:15', '2025-11-25 11:43:27'),
(403, 2, '4c6e2e5839f65a2540f9a70ec75dd2401966fb82c21ed44fe95125639d0dad4b', '2025-12-25 05:47:42', NULL, '2025-11-25 11:47:42'),
(404, 2, 'e7ecea0504e7ae2ea2c8b87fbcd8b096e539fc2dfae3f26c37e30f1696f578e3', '2025-12-25 06:00:40', NULL, '2025-11-25 12:00:40'),
(405, 3, '5b26950969b28a997690c156428fd3b7b51589611cbc2ccf21ff2c6f825b7ff1', '2025-12-25 07:21:15', '2025-11-25 14:20:20', '2025-11-25 13:21:15'),
(406, 3, '12c56ea92d9cb3c0ee20074a884969050ffcf8be5bda4f1e07b89eb07c4c4b82', '2025-12-25 07:21:15', '2025-11-25 14:20:20', '2025-11-25 13:21:15'),
(407, 3, '662a677f17e82d74113edcd2085b3a18f8b78503f207b900072bad5674c6d83d', '2025-12-25 08:20:20', '2025-11-25 15:20:18', '2025-11-25 14:20:20'),
(408, 3, '7bbf8d72ccaaaa0cd9b9bf15956c765779142d4215a9ecc92712ddda68f69380', '2025-12-25 08:20:20', '2025-11-25 15:19:36', '2025-11-25 14:20:20'),
(409, 3, 'b07ab97d607c6f153b7ea9df641c3d2def56aa85d7ce5fdb270ce07578ca4087', '2025-12-25 08:55:12', NULL, '2025-11-25 14:55:12'),
(410, 3, '5b1965f1058f872f2e10d5e39c0923a54eaa07ae876bcb763a0d54ed99ad74d4', '2025-12-25 09:19:36', '2025-11-25 16:17:01', '2025-11-25 15:19:36'),
(411, 3, '200fc82c3bc0dc917992658e5bde158c0cb169023d373ad468bd58a7dfa4e583', '2025-12-25 09:20:18', NULL, '2025-11-25 15:20:18'),
(412, 3, '72759537421db3e963e94cde5310b5cc7bc28585325dc46107ad2bd9e4881850', '2025-12-25 09:41:45', '2025-11-25 16:36:52', '2025-11-25 15:41:45'),
(413, 3, 'f39dfef1e82e269437f50efa02bdacc181c40c4038854617571984248e7f862f', '2025-12-25 09:41:58', NULL, '2025-11-25 15:41:58'),
(414, 3, 'e66eef2bc8250bf92bd8b26132b46e71983c74b3f0338eaee8b9e348d47952e8', '2025-12-25 10:15:58', NULL, '2025-11-25 16:15:58'),
(415, 3, '40e75f1011f5fcecfa053c07dcbe1f32680b6aaef891f211e441d6be8a55ce76', '2025-12-25 10:17:01', '2025-11-26 13:39:20', '2025-11-25 16:17:01'),
(416, 3, 'ecae3e59f8a1e2cdd32873b7c63086e27601a7d50e5370d3a2e4999a2e60e369', '2025-12-25 10:23:02', '2025-11-25 16:42:01', '2025-11-25 16:23:02'),
(417, 3, '2237f66e475e7bd349383aa9ca15e5628a7a6b33a3eb6bacab1c02f1250e768c', '2025-12-25 10:36:52', NULL, '2025-11-25 16:36:52'),
(418, 3, 'dd7667f6b5b78a8d8073b79f84574cf54de2d6b10c22b5e44e1e8ff712b173b9', '2025-12-25 10:42:10', NULL, '2025-11-25 16:42:10'),
(419, 3, '1f6a04c78c7d6227631d4481fe5bfb321b862e59271b2b8937989726db1db48a', '2025-12-25 12:14:58', '2025-11-25 19:15:17', '2025-11-25 18:14:58'),
(420, 3, '45e4473a802bd0f6d1d1a049ebe46f3cc3d0983121b391c0c09202087c935d74', '2025-12-25 12:40:56', NULL, '2025-11-25 18:40:56'),
(421, 3, '57c50756a80887975a624ec4bd063bd499db2c5ccfc49bbb87c498c97469e0b2', '2025-12-25 13:15:17', '2025-11-25 20:12:57', '2025-11-25 19:15:17'),
(422, 3, 'f4cda171326524a7387612d23db0ac94bd3d6753666def66612c8ca4416bb7d0', '2025-12-25 14:12:57', '2025-11-25 21:08:10', '2025-11-25 20:12:57'),
(423, 3, 'a98178646769a7ca0ef73346f54122c02797ee168a7014acbc0c6d639021daf5', '2025-12-25 15:08:10', '2025-11-25 22:06:58', '2025-11-25 21:08:10'),
(424, 2, '7c321943127e7c962709569ed3304cd7301f6789757a5c07b5576f5616f1d0c9', '2025-12-25 15:30:18', NULL, '2025-11-25 21:30:18'),
(425, 3, 'bdf868630e405013c39a34e1212304d01ea81c3b4ac7abab8578cd0e9f786ac7', '2025-12-25 16:06:58', '2025-11-26 11:43:25', '2025-11-25 22:06:58'),
(426, 3, 'fd683eb676b0df1ea656690ad3a4dd789f146a76a6cf592f2aaec615f15cb507', '2025-12-26 05:43:25', '2025-11-26 12:52:58', '2025-11-26 11:43:25'),
(427, 3, 'c543052ca93b2c34aa65d1bdd03c8d45b5b5f75edddd413baceb73b14e720162', '2025-12-26 06:52:58', '2025-11-26 13:52:42', '2025-11-26 12:52:58'),
(428, 3, 'bdf66c2378650ae612d57029adeb6cf4ac742159c3491119f89084b3d0e3a746', '2025-12-26 07:39:20', '2025-11-26 14:36:26', '2025-11-26 13:39:20'),
(429, 3, 'c30ba4958f5e47934e701aaf52f736af4b20febf0eb7ca1490847f68a000ae8d', '2025-12-26 07:40:49', NULL, '2025-11-26 13:40:49'),
(430, 3, '58ce4099526560c79f11fe5952bd3a7404c9d820090674313fc9b9faa178d333', '2025-12-26 07:52:42', '2025-11-26 14:49:27', '2025-11-26 13:52:42'),
(431, 3, '4998c95141a7d69cdb1ae91529d65150878d9ded3915322788fdc12782e5c7a3', '2025-12-26 08:36:26', NULL, '2025-11-26 14:36:26'),
(432, 3, '513e527bf95db4e833d4fc9d300b72ef7bb23dec221c738d6c6dda524ad8cd6b', '2025-12-26 08:49:27', '2025-11-26 15:46:51', '2025-11-26 14:49:27'),
(433, 3, '453ec5de0c3713c914783bbb20012e0fd30f1124ab0a3b2327c245f1829d1c2d', '2025-12-26 09:46:51', '2025-11-26 16:48:55', '2025-11-26 15:46:51'),
(434, 3, '74b5de7d4c3dcfde7cfbcbfc4a5dcaa9f32b11dfb30e05be3a84cdc6e64021f8', '2025-12-26 10:48:55', '2025-11-27 19:51:46', '2025-11-26 16:48:55'),
(435, 3, '597736157fb772ba818def911afe061ba70b4ee607973f7efe8939f296b43319', '2025-12-27 13:51:46', '2025-11-27 20:47:05', '2025-11-27 19:51:46'),
(436, 3, '567f3b8cce7aad0fe9a9a04bf6118021833a473acebf236b7d464f50f2e5a7ed', '2025-12-27 14:47:05', '2025-11-27 21:43:29', '2025-11-27 20:47:05'),
(437, 3, '1cbc7b74a7c1899e217c6fd312221f08d961cd280a7c197ff8b00a04d5572c0d', '2025-12-27 14:58:49', '2025-11-27 20:59:05', '2025-11-27 20:58:49'),
(438, 3, '6377f121a6d071fb7e52ff2ce68bf8ac4bcb141188f2d97d1b36055f3dc3f0f9', '2025-12-27 15:01:40', '2025-11-27 21:07:35', '2025-11-27 21:01:40'),
(439, 2, '335b2f36e0cd0204219d3f7368df18ca50f71869b12bc5164a04993fd2281f1b', '2025-12-27 15:08:34', '2025-11-27 21:21:38', '2025-11-27 21:08:34'),
(440, 3, 'b4ab9fe0ab8f27b48c33f7ffcade3d5fe36c717c1afa044da6dc3560912ceeac', '2025-12-27 15:21:44', '2025-11-27 22:19:37', '2025-11-27 21:21:44'),
(441, 3, '7d96f5aa3284dcf954a346b3af2a83a4943375dd994c58a5543f2195f1856fd5', '2025-12-27 15:43:29', '2025-11-27 22:39:37', '2025-11-27 21:43:29'),
(442, 3, '5495a32656ae46d25f1bc56630a294c9b3824dd84fd5dde0fc0507e9c2da350b', '2025-12-27 16:19:37', NULL, '2025-11-27 22:19:37'),
(443, 3, '2eead520ce7adebe6ed98928bfd647fdc27aa4f4c84267e81bf5648e6cc19407', '2025-12-27 16:39:37', '2025-11-27 22:58:49', '2025-11-27 22:39:37'),
(444, 1, '1507cab4dc4353a61ddfb01cdc30d84f9ebf0bb1edeb4863114d18f348cb1c2c', '2025-12-27 16:59:44', NULL, '2025-11-27 22:59:44'),
(445, 2, '7ca599e7de9167c1ab7af79293381df0c7f88bcd46dfc440bceca1345f8b15c3', '2026-01-05 06:30:34', '2025-12-06 13:25:57', '2025-12-06 12:30:34'),
(446, 1, '58e56e44a5ff1385559acb28a2e0b60536a2ea303fb2fc8c5829fd22a1075bb3', '2026-01-05 06:39:34', NULL, '2025-12-06 12:39:34'),
(447, 2, 'fb8ef3b850b930a176455ca19b592fc9c2ef913acbdf7c44deb63bcc7701251c', '2026-01-05 07:25:57', NULL, '2025-12-06 13:25:57'),
(448, 2, '8a952a28968a8409d71d99b54a2bd322ee61a945a8125a28972e636e334f8f4e', '2026-01-05 07:26:45', '2025-12-06 14:21:47', '2025-12-06 13:26:45'),
(449, 2, 'bded56da85412bfcbfdeededc4a62d1315397464b8ee7952376fdd83b3fea63f', '2026-01-05 08:21:47', NULL, '2025-12-06 14:21:47'),
(450, 2, 'f730ed37a589c6ba82640f5f48afa6cfd03833e853afae7baf3034c07e94ac3c', '2026-01-05 09:16:27', '2025-12-06 16:11:57', '2025-12-06 15:16:27'),
(451, 2, '0e37e7849fd6e6da347ad54e5a8ef0f9eb9b4a1b3f19a97d8291bde3065d7a4f', '2026-01-05 10:11:57', '2025-12-06 17:06:58', '2025-12-06 16:11:57'),
(452, 2, 'ec8ad3940ae2845796f5e6aaf39ddbf962d171850509fbec4ca6b985a7bba536', '2026-01-05 11:06:58', NULL, '2025-12-06 17:06:58'),
(453, 2, '9bcfbd9ccddfe49ca40da8751b3f10e467677d5ce90972bcf66c67712296d6c5', '2026-01-05 11:40:25', NULL, '2025-12-06 17:40:25'),
(454, 3, 'a08c7bbb84c754fb824dbc47c4c482f10d9be9d220681b384f2316ef21f28c06', '2026-01-05 12:05:38', NULL, '2025-12-06 18:05:38'),
(455, 4, '2ce598b2de389b35263cdae6248e671366d30100c74e03a756da8355bd357e70', '2026-01-29 05:50:15', '2025-12-30 11:50:16', '2025-12-30 11:50:15'),
(456, 4, 'b2a88a4dac38819b9a529a11bbfed939d389673d5dc3225a5a19cd5930cda678', '2026-01-29 05:50:16', NULL, '2025-12-30 11:50:16'),
(457, 1, '89d2578c906167f3dda8c14b7622d4c1d6ee8e513772777f6bd8c4f421edd0e0', '2026-01-29 05:51:30', NULL, '2025-12-30 11:51:30'),
(458, 3, '717a701ba0c009fd907dfe0f3edb9248ff62d20e8683a22dccae6e5e68960455', '2026-01-29 05:52:26', '2026-01-25 15:03:24', '2025-12-30 11:52:26'),
(459, 3, '06fff34e08674ab887dafd8e8202da658c882476980ab353b06d5b864430c86f', '2026-02-24 09:03:24', '2026-01-25 15:58:25', '2026-01-25 15:03:24'),
(460, 3, 'ce39bb24c8345896b95e23b0a2a3c7aecf3d5a5d382299e3bfb6a8740ee20833', '2026-02-24 09:58:25', '2026-01-25 16:53:27', '2026-01-25 15:58:25'),
(461, 2, 'b94504587dfb6838879fd08246779774426652717a3741be03792c50d147b66e', '2026-02-24 10:03:04', '2026-01-25 16:58:11', '2026-01-25 16:03:04'),
(462, 1, '248f7869daf8461cd8532fbea49a30b017896b1a3d37cca8e0e757431dc66451', '2026-02-24 10:44:24', NULL, '2026-01-25 16:44:24'),
(463, 3, 'c71f2dad8b3ce88e05088ac03bba408a83025ee5d8fba995184a901036b0cc87', '2026-02-24 10:53:27', '2026-01-26 21:45:40', '2026-01-25 16:53:27'),
(464, 2, '9ec5c05486eb3c06e9a4bd2c12a3c3ae5ca208e81b8663a83b0a1c6ce968848f', '2026-02-24 10:58:11', '2026-01-25 17:54:13', '2026-01-25 16:58:11'),
(465, 2, '5b1c42fc2767a2979be12161907a4b8564d0ce52a48cfe26e104e7d3a5857e73', '2026-02-24 11:54:13', '2026-01-25 18:52:09', '2026-01-25 17:54:13'),
(466, 1, '570cad13413f61e72195a3b2653756318ebb1abdb8c3222db8ccb53423ffae50', '2026-02-24 12:22:10', NULL, '2026-01-25 18:22:10'),
(467, 2, 'de471421e4d2d7f054c1a85e0ff7337b7f73723c68a422d77f2a14df91c99997', '2026-02-24 12:52:09', NULL, '2026-01-25 18:52:09'),
(468, 2, 'f0c2b69bf1649a64bcccdcbe4bad4e3e5bfd589829806d56daf4c7d966ad1dd8', '2026-02-24 15:44:35', '2026-01-25 22:41:17', '2026-01-25 21:44:35'),
(469, 2, 'dd350c1d52fae68d4f82af686c3d46d2458e50b3c5789a4b34394160da69b714', '2026-02-24 16:41:17', '2026-01-25 23:37:14', '2026-01-25 22:41:17'),
(470, 2, '13163bfa9ee16edd26becda8aa3caae47c7a2763239e42e9be512c02b6c739ec', '2026-02-24 17:37:14', '2026-01-26 00:33:18', '2026-01-25 23:37:14'),
(471, 2, '8d1f8df5e89fa3bcf48bf0921741f9b6870ec535e30558d626836232499df351', '2026-02-24 18:33:18', NULL, '2026-01-26 00:33:18'),
(472, 3, '77f524247a589a546e4ece1184afdf9d1d157bd60c06f3795ca60f672b63766d', '2026-02-25 15:45:40', '2026-01-26 22:40:25', '2026-01-26 21:45:40'),
(473, 3, '1965100619482eae1f57efc9169bae7ce7a5fa7070a98bb9734e9cd11d809736', '2026-02-25 16:40:25', '2026-01-26 23:35:42', '2026-01-26 22:40:25'),
(474, 3, '86f348a2006deb3c383dc727c0233a25d21c845437411f22d2f561e07498b226', '2026-02-25 17:35:42', '2026-01-26 23:58:26', '2026-01-26 23:35:42'),
(475, 3, '2f07068fc0cd58c8b32f465732fe2ca881686e6213b0c97f720611844b842f53', '2026-02-25 17:58:26', '2026-01-27 00:54:44', '2026-01-26 23:58:26'),
(476, 3, 'af85eed0838c81b6aa42e37906c1d626db3af5aa94c3c3c12300f7a2723323d8', '2026-02-25 18:54:44', '2026-01-27 01:01:42', '2026-01-27 00:54:44'),
(477, 3, 'c357d8c1f2625d17233f7d02849c6a001e72d4dc5e1c1ee04a993cad6de4f01c', '2026-02-25 19:01:42', '2026-01-31 17:36:02', '2026-01-27 01:01:42'),
(478, 3, '2096287225fa10e8060d8ee705f07b9c1db6fabd6664b4661353c761843c3902', '2026-03-02 11:36:02', NULL, '2026-01-31 17:36:02'),
(479, 1, '01a6415de8a449bc5d61d5ee9f03daf72e5e9d96bedafb8d94dec619ad038c35', '2026-03-02 11:36:05', NULL, '2026-01-31 17:36:05'),
(480, 1, '38c6404267d357ba722dfbe68b7da9e0dfe5c84cd4fc923e5223044ef4ca0d72', '2026-03-02 17:22:28', NULL, '2026-01-31 23:22:28'),
(481, 1, '0707f9054395bed5cf5dd12422f5a1e2e38f05efb8eb9daa3696911ea1fa7e9c', '2026-03-02 18:18:59', NULL, '2026-02-01 00:18:59'),
(482, 1, 'bdde38fd8f96bde8055eba7f8f17dc7eed36783f3ef6ed94775717394fced03d', '2026-03-02 19:47:49', NULL, '2026-02-01 01:47:49'),
(483, 1, '1de553127c8bdaa0fcc052af962b6c54d877ce8dacd3ba5d6bb17d48ce01b202', '2026-03-03 09:25:16', NULL, '2026-02-01 15:25:16'),
(484, 1, '5a83f833f384a07bcae6e9b0d715ff2e2da806f1b856076560d953208908677c', '2026-03-03 10:05:16', NULL, '2026-02-01 16:05:16'),
(485, 1, '88796e0dff7576caf0d339605ce1ed2d0328bb5dca328482bbff87a7016f99ef', '2026-03-03 10:05:29', NULL, '2026-02-01 16:05:29'),
(486, 1, '3298c7f608d266a44d0cccedaefb71a8816eb9491ab74e50d6851e486cd1aad3', '2026-03-03 11:02:46', NULL, '2026-02-01 17:02:46'),
(487, 1, '89c574ac4909262164e599514756ad399fb2adf7efacac932950677eca6b0e02', '2026-03-03 14:04:09', '2026-02-01 20:23:54', '2026-02-01 20:04:09'),
(488, 2, '2098d3d02456ed3bd9c176498b8bcaf5e0f4b2c3e43d3ff26cf1e53f6a41f407', '2026-03-03 14:22:54', '2026-02-01 21:48:44', '2026-02-01 20:22:54'),
(489, 2, '6b5b30c73d40e09fe9934c35b76908cb90c4a03311f9678a9ee1e6bdaeeb7e6d', '2026-03-03 14:24:13', NULL, '2026-02-01 20:24:13'),
(490, 2, '3221da439a8d0203bc09494dcc1eae5313c58a8198b5c2826a4bc51ded140dbf', '2026-03-03 15:48:44', '2026-02-01 22:38:46', '2026-02-01 21:48:44'),
(491, 3, '81976afbcb380b3431f806ddcaadd0e2ef0f7349408a697cf350873832c017e2', '2026-03-03 16:39:19', '2026-02-01 23:36:14', '2026-02-01 22:39:19'),
(492, 1, 'b5214cec139c3cb491231ba4b9ab5f7b061eccefb8a9f8c7a0a6ea3c761409c8', '2026-03-03 16:49:47', NULL, '2026-02-01 22:49:47'),
(493, 3, 'e81572d3993255ae7054756160568598d78552950ebabc11f6271e1be313971d', '2026-03-03 17:36:14', NULL, '2026-02-01 23:36:14'),
(494, 3, '37da714df524f11f312bf295a2bf3a67a9a32292ba711160636a8f3f06f00d65', '2026-03-03 18:15:53', '2026-02-02 01:25:19', '2026-02-02 00:15:53'),
(495, 3, '402b6d0c1cde884c28c0662184dcd43c27deac0e6a56b409979e5d7fe6df1282', '2026-03-03 18:53:46', '2026-02-02 01:52:30', '2026-02-02 00:53:46'),
(496, 3, '10652643f85ec3ffff3b03d09932226b820c9792b7e56f5325f047474b1b3977', '2026-03-03 19:25:19', NULL, '2026-02-02 01:25:19'),
(497, 3, 'df7ed82f01088cc1e28a51ca6f29b2fc8ba991106f6287a51d68217431570cb0', '2026-03-03 19:25:35', '2026-02-02 01:49:01', '2026-02-02 01:25:35'),
(498, 3, '4e314aa2210ed62034a19de9f9d08dc58bdaf64d6f6bc6ffc8eaa49a04e7950b', '2026-03-03 19:49:17', '2026-02-02 01:49:24', '2026-02-02 01:49:17'),
(499, 3, 'f5f6de2c4fdb6f15420668ccff3c45afcb9ded074c6cbf489440d03c1b29d1b3', '2026-03-03 19:49:41', NULL, '2026-02-02 01:49:41'),
(500, 3, '3874de9f4529e4c288dba768feae2e5e039a7902ce325f6c1fb64c94a3422259', '2026-03-03 19:52:30', NULL, '2026-02-02 01:52:30'),
(501, 3, 'd0677c1cdda92dd40bd5eb1a73a8931bc106e69778cf72ca18a177c6ab4061d7', '2026-03-05 08:26:48', '2026-02-03 15:23:26', '2026-02-03 14:26:48'),
(502, 1, '9bfd79c50b5c85ecc8ec3cf8eb3d4a24f2ae6c47232b90113d2419353ae8825b', '2026-03-05 08:28:00', NULL, '2026-02-03 14:28:00'),
(503, 3, 'fb07c5fa0539b1c58aeb7a4603c2b866e7f7e80f34d5e8e5d08024be8724a510', '2026-03-05 09:23:26', '2026-02-03 16:18:26', '2026-02-03 15:23:26'),
(504, 3, '35768feb93d011ae7d9897ec5c07f21c1d87321c21ecb3912b3aef67b484fa44', '2026-03-05 10:18:26', NULL, '2026-02-03 16:18:26'),
(505, 3, 'bd69989252b75d3cb386c5ee4c756b27d35729d57c3166cd2c4b7b9492a2cfd3', '2026-04-11 09:17:17', '2026-03-12 15:12:29', '2026-03-12 14:17:17'),
(506, 3, '3d9e34df18609282561420b4ad2c0cb3c26f2b65b0c48ae7d3f2a9e43c4b1e23', '2026-04-11 10:12:29', '2026-03-12 16:07:34', '2026-03-12 15:12:29'),
(507, 3, 'ea535bcb9ce50f5bc74ba9a3d24f5a5ccc3d5723918c9ed0f0599f6ada941ff4', '2026-04-11 11:07:34', '2026-03-12 16:21:49', '2026-03-12 16:07:34'),
(508, 3, 'cc35cf20844823a5a2667693d77dc16c12cb0e66e971fc5afb15921b8c0fa2d9', '2026-04-11 11:21:57', '2026-03-12 17:04:22', '2026-03-12 16:21:57'),
(509, 3, '9c87cc15f81f6b41d39b7d3ae152d86143857acd725091a3a796278ccebdff0e', '2026-04-11 12:05:26', '2026-03-13 14:29:36', '2026-03-12 17:05:26'),
(510, 3, '9b091d6c6e6f71b5a14f87f843a7fe8af0c1b3c209699e4ea5be65a41373232a', '2026-04-12 09:29:36', '2026-03-13 14:30:10', '2026-03-13 14:29:36'),
(511, 3, 'e08dbfdb6fecfb9fca0d05b7202a14e621a5fff7fb939fe2bebd678a32c7205c', '2026-04-12 09:30:18', '2026-03-13 14:49:34', '2026-03-13 14:30:18'),
(512, 3, '9baf90f657f9fd5187a5d66f02fa6a7a84bc11f84d0f381b7fd982ec5b0c05f9', '2026-04-12 09:49:40', '2026-03-13 15:04:11', '2026-03-13 14:49:40'),
(513, 3, '1cb153596f8fe80f3eb1b4607df4f82b9adbacdba311c6acf835731e658966dd', '2026-04-12 10:04:21', '2026-03-13 16:01:27', '2026-03-13 15:04:21'),
(514, 1, '402b56f735bcec446a1cedd397845df0055522d3c39f195e3c6e7452946c6f77', '2026-04-12 10:17:06', NULL, '2026-03-13 15:17:06'),
(515, 3, '50447f3f2b2aa3f751d66142ce718ac7a05e7990b2fd9b1b258b124702d26ea5', '2026-04-12 11:01:27', '2026-03-13 16:57:26', '2026-03-13 16:01:27'),
(516, 3, '9bf5e290c0800591626959a538e2db9f32f8d8b823eb7cd6ae7fc715bf121f77', '2026-04-12 11:57:26', '2026-03-14 09:08:12', '2026-03-13 16:57:26'),
(517, 3, '6c7d6dfcbe338ce144948dddadf6a3e2268f139f66ddf023ad24a0479a1f6c22', '2026-04-13 04:08:12', NULL, '2026-03-14 09:08:12'),
(518, 3, 'f512ba008d34f06e1e3706013c0976f1138d6ba3552a2b10508dec11c14d2b7a', '2026-04-13 04:08:29', '2026-03-14 10:04:01', '2026-03-14 09:08:29'),
(519, 1, 'c8644f38d43668ca89492850a4632de3e60c0637f3c04cd705aa7aff8772f636', '2026-04-13 04:54:08', NULL, '2026-03-14 09:54:08'),
(520, 3, 'ff01ddda919c66848c0950a869b34497f1e66cf62e2510f4edd5b71ecc851156', '2026-04-13 05:04:01', '2026-03-15 22:52:30', '2026-03-14 10:04:01'),
(521, 3, '23bc5ddf58457509edaea0ec026c5e297c9c6dfccd0bf78f489f28a197346595', '2026-04-14 17:52:30', '2026-03-15 22:52:40', '2026-03-15 22:52:30'),
(522, 3, 'e8fc04f7f6fee183f1fd054aa18e53d79e6549ad8b9943f0a8f1483c1bdc7a0f', '2026-04-14 17:52:40', '2026-03-16 00:26:55', '2026-03-15 22:52:40'),
(523, 1, '3303a228d31dc25a3bfcb43f8364c2d62eb841c21bd10fa5fb0d75de6ee8174b', '2026-04-14 17:53:57', NULL, '2026-03-15 22:53:57'),
(524, 3, '94df111394ca073b49a49012c1ae5494bf203056d699c3357be7f0c5fe8081d1', '2026-04-14 19:26:55', '2026-03-16 00:26:59', '2026-03-16 00:26:55'),
(525, 3, '9cdc9c48fbe6ac8848d09021bf8363b3464371cff6f20d66a1b62d73f169f012', '2026-04-14 19:26:59', '2026-03-16 01:24:32', '2026-03-16 00:26:59'),
(526, 1, '5ba14074c758ddd2fb6f88e01f940c9a9d6f31d5f4c312ca17f150ba242ca490', '2026-04-14 19:27:50', NULL, '2026-03-16 00:27:50'),
(527, 3, 'f1dadcc470c236096295b5a9b8cb068301ae755add2d605d83600dbe13dafd39', '2026-04-14 20:24:32', '2026-03-16 01:27:17', '2026-03-16 01:24:32'),
(528, 3, '9fec6adca08b68cf5628467d8d206bf613e917da41321c0501960d11141214ba', '2026-04-14 20:27:17', '2026-03-16 02:23:23', '2026-03-16 01:27:17'),
(529, 3, '9e1cbd894df40416ec54791aea999e67bec2e77effa9626567246f75f2eeb922', '2026-04-14 21:23:23', '2026-03-16 02:34:27', '2026-03-16 02:23:23'),
(530, 3, '7f55309f1a256d7790a8d6bebb41c7f7c5b3be476434979427c9610467578441', '2026-04-14 21:34:27', '2026-03-16 10:31:48', '2026-03-16 02:34:27'),
(531, 1, 'd71fbfc659256d51d22e3aaf220526fe0723e340cd4af0571e55391d0ce15272', '2026-04-14 22:21:59', NULL, '2026-03-16 03:21:59'),
(532, 3, 'dcc6e902cc4943a1c5d13be13d57b0e3e5e306cd65160afa80bf9a28ccfc0504', '2026-04-15 05:31:48', '2026-03-16 15:47:15', '2026-03-16 10:31:48'),
(533, 1, '184f3572c3c43e1e03101d8324dfce79d19cadcdabbb96f6ca33a68113000f20', '2026-04-15 05:33:21', '2026-03-16 10:58:04', '2026-03-16 10:33:21'),
(534, 1, 'fa78871c401fad079be11ed3068d8b55a7bda19d5d9e74ef4fdc71ccae0af698', '2026-04-15 05:33:59', NULL, '2026-03-16 10:33:59'),
(535, 3, 'e0858e81d45de7a5e81a000840edb6bd96247a4c23038671474e272bd6d70819', '2026-04-15 05:58:27', NULL, '2026-03-16 10:58:27'),
(536, 3, 'b1d7f0defb1eb14bfd008ff9c1720dc3576b5ae1ca8ab23843482dff64931b77', '2026-04-15 10:47:15', NULL, '2026-03-16 15:47:15'),
(537, 1, 'b22d41ed3d1b73e40fd3e19ede3c62e73ae43d27453e2e173176c8901b030759', '2026-04-15 10:47:19', NULL, '2026-03-16 15:47:19'),
(538, 1, '6d81e42c3f157a4ea24a18472e0a3d67eb23efcf18f348901afef4f2492d2a9a', '2026-04-15 11:03:36', NULL, '2026-03-16 16:03:36'),
(539, 1, 'ad3ae752862dd883a838184e5133b44a8138a7576be073dac2a6e1e2c717e021', '2026-04-15 11:23:07', NULL, '2026-03-16 16:23:07'),
(540, 1, 'b3472a15eea68a6508fc989b7ab63f7fdd4a0af17d8f9804065ed74abaa81f7a', '2026-04-15 11:29:04', '2026-03-16 16:36:53', '2026-03-16 16:29:04'),
(541, 1, '0bf41006e7c3285392d31d25fcc8b9ddb949651de58e010fcc5b53baa3a6f43a', '2026-04-15 11:36:53', NULL, '2026-03-16 16:36:53'),
(542, 1, 'ae7a107a167ee0a203a6dafc19c587ad8b685a26e446247f6f3ae48f03687d42', '2026-04-15 12:03:31', NULL, '2026-03-16 17:03:31'),
(543, 1, '258a6e597a301d39d1701a3a5d4bb2cd726c54577e554818072456489e63055c', '2026-04-15 12:10:55', NULL, '2026-03-16 17:10:55');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `roles`
--

CREATE TABLE `roles` (
  `role_id` int(11) NOT NULL,
  `role_name` varchar(50) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Đang đổ dữ liệu cho bảng `roles`
--

INSERT INTO `roles` (`role_id`, `role_name`, `created_at`, `updated_at`) VALUES
(1, 'admin', '2025-08-10 22:48:46', '2025-08-10 22:48:46'),
(2, 'customer', '2025-08-10 22:48:46', '2025-08-10 22:48:46'),
(3, 'shipper', '2025-08-10 22:48:46', '2025-08-10 22:48:46'),
(4, 'staff', '2025-08-10 22:48:46', '2025-08-10 22:48:46');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `shippers`
--

CREATE TABLE `shippers` (
  `user_id` int(11) NOT NULL,
  `vehicle_info` varchar(255) DEFAULT NULL,
  `rating` decimal(3,2) DEFAULT 0.00,
  `on_time_delivery_pct` decimal(5,2) DEFAULT 0.00,
  `last_delivery_at` datetime DEFAULT NULL,
  `total_delivered` int(11) DEFAULT 0,
  `note` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_available` tinyint(1) DEFAULT 1,
  `status` enum('active','suspended','banned') DEFAULT 'active',
  `fcm_token` varchar(500) DEFAULT NULL,
  `fcm_token_updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Đang đổ dữ liệu cho bảng `shippers`
--

INSERT INTO `shippers` (`user_id`, `vehicle_info`, `rating`, `on_time_delivery_pct`, `last_delivery_at`, `total_delivered`, `note`, `created_at`, `updated_at`, `is_available`, `status`, `fcm_token`, `fcm_token_updated_at`) VALUES
(4, 'Honda Wave RSX', 4.80, 95.50, '2025-08-19 23:08:53', 1, NULL, '2025-08-10 22:48:46', '2025-12-30 11:50:17', 1, 'active', 'e6OyS51yQ9e_06rC4eJQcp:APA91bEgM-7e7FDGA_7MOJMNm_-vyJzJ02MfaM0TNNaYD3QnyGaNzuiyUB2ac0W0Q0y6NfPaQVOufI479rZIBvUmu3xpEKfuuIe0p8xvOTlk0Gwcy6aSiYI', '2025-12-30 11:50:17'),
(5, 'Yamaha Sirius', 4.60, 92.30, NULL, 89, NULL, '2025-08-10 22:48:46', '2025-08-10 22:48:46', 1, 'active', NULL, NULL),
(12, 'Honda Vision', 4.75, 94.20, NULL, 120, 'Shipper có kinh nghiệm giao hàng khu vực trung tâm', '2025-08-20 13:33:39', '2025-08-20 13:33:39', 1, 'active', NULL, NULL),
(13, 'Yamaha Exciter', 4.85, 96.80, NULL, 180, 'Shipper nhanh nhẹn, phục vụ tốt', '2025-08-20 13:33:39', '2025-08-20 13:33:39', 1, 'active', NULL, NULL),
(14, 'Honda Air Blade', 4.70, 93.50, NULL, 95, 'Shipper mới, đang tích lũy kinh nghiệm', '2025-08-20 13:33:39', '2025-08-20 13:33:39', 1, 'active', NULL, NULL),
(15, 'Yamaha Grande', 4.90, 97.10, NULL, 210, 'Shipper xuất sắc, được khách hàng đánh giá cao', '2025-08-20 13:33:39', '2025-08-20 13:33:39', 1, 'active', NULL, NULL);

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `shipper_locations`
--

CREATE TABLE `shipper_locations` (
  `location_id` int(11) NOT NULL,
  `shipper_id` int(11) NOT NULL,
  `order_id` int(11) DEFAULT NULL,
  `lat` decimal(10,6) NOT NULL,
  `lng` decimal(10,6) NOT NULL,
  `speed` decimal(5,2) DEFAULT 0.00,
  `heading` int(11) DEFAULT 0,
  `accuracy` decimal(5,2) DEFAULT 0.00,
  `battery_level` int(11) DEFAULT 100,
  `captured_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Đang đổ dữ liệu cho bảng `shipper_locations`
--

INSERT INTO `shipper_locations` (`location_id`, `shipper_id`, `order_id`, `lat`, `lng`, `speed`, `heading`, `accuracy`, `battery_level`, `captured_at`) VALUES
(1, 4, 1, 10.762622, 106.660172, 25.50, 45, 5.20, 85, '2025-10-28 14:30:00'),
(2, 4, 1, 10.765000, 106.665000, 30.00, 60, 4.80, 82, '2025-10-28 14:35:00'),
(3, 4, 1, 10.770000, 106.670000, 20.00, 90, 6.10, 78, '2025-10-28 14:40:00'),
(4, 5, 2, 10.780000, 106.680000, 35.00, 120, 3.50, 90, '2025-10-28 14:25:00'),
(5, 5, 2, 10.785000, 106.685000, 28.00, 150, 4.20, 87, '2025-10-28 14:30:00'),
(6, 15, NULL, 10.750000, 106.650000, 0.00, 0, 2.10, 95, '2025-10-28 14:45:00');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `shipper_performance`
--

CREATE TABLE `shipper_performance` (
  `shipper_id` int(11) NOT NULL,
  `period_start_date` date NOT NULL,
  `period_end_date` date NOT NULL,
  `total_delivered_count` int(11) NOT NULL DEFAULT 0,
  `on_time_deliveries_count` int(11) NOT NULL DEFAULT 0,
  `total_cod_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `avg_rating` decimal(3,2) DEFAULT 0.00 CHECK (`avg_rating` between 0 and 5)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `shipping_logs`
--

CREATE TABLE `shipping_logs` (
  `log_id` int(11) NOT NULL,
  `tracking_id` int(11) NOT NULL,
  `lat` decimal(10,6) NOT NULL,
  `lng` decimal(10,6) NOT NULL,
  `captured_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `shipping_methods`
--

CREATE TABLE `shipping_methods` (
  `shipping_method_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `fee` decimal(10,2) NOT NULL DEFAULT 0.00,
  `estimated_days` int(11) NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Đang đổ dữ liệu cho bảng `shipping_methods`
--

INSERT INTO `shipping_methods` (`shipping_method_id`, `name`, `fee`, `estimated_days`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'Giao hàng tiêu chuẩn', 30000.00, 3, 1, '2025-08-19 23:08:53', '2025-08-19 23:08:53'),
(2, 'Giao hàng nhanh', 50000.00, 1, 1, '2025-08-19 23:08:53', '2025-08-19 23:08:53'),
(3, 'Giao hàng miễn phí (Đơn hàng > 1M)', 0.00, 7, 1, '2025-08-19 23:08:53', '2025-09-10 16:29:58'),
(4, 'Giao hàng trong ngày', 100000.00, 1, 1, '2025-09-10 16:29:58', '2025-09-10 16:29:58');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `shipping_tracking`
--

CREATE TABLE `shipping_tracking` (
  `tracking_id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `shipper_id` int(11) NOT NULL,
  `current_lat` decimal(10,6) DEFAULT NULL,
  `current_lng` decimal(10,6) DEFAULT NULL,
  `confirmed_delivery_at` datetime DEFAULT NULL,
  `photo_proof_url` varchar(500) DEFAULT NULL,
  `signature_url` varchar(500) DEFAULT NULL,
  `route_polyline` text DEFAULT NULL,
  `last_updated` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Đang đổ dữ liệu cho bảng `shipping_tracking`
--

INSERT INTO `shipping_tracking` (`tracking_id`, `order_id`, `shipper_id`, `current_lat`, `current_lng`, `confirmed_delivery_at`, `photo_proof_url`, `signature_url`, `route_polyline`, `last_updated`) VALUES
(1, 3, 4, 10.752622, 106.650172, NULL, NULL, NULL, NULL, '2025-08-19 23:08:53'),
(2, 1, 4, NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-16 21:29:24'),
(3, 2, 4, NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-13 13:43:04'),
(4, 5, 4, NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-13 21:34:41'),
(5, 69, 4, NULL, NULL, NULL, NULL, NULL, NULL, '2026-03-13 15:17:43');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `sizes`
--

CREATE TABLE `sizes` (
  `size_id` int(11) NOT NULL,
  `size_name` varchar(10) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Đang đổ dữ liệu cho bảng `sizes`
--

INSERT INTO `sizes` (`size_id`, `size_name`) VALUES
(3, 'L'),
(2, 'M'),
(1, 'S'),
(4, 'XL');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `suppliers`
--

CREATE TABLE `suppliers` (
  `supplier_id` int(11) NOT NULL,
  `supplier_name` varchar(255) NOT NULL,
  `contact_name` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `status` enum('active','inactive','suspended') DEFAULT 'active',
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Đang đổ dữ liệu cho bảng `suppliers`
--

INSERT INTO `suppliers` (`supplier_id`, `supplier_name`, `contact_name`, `phone`, `email`, `address`, `status`, `created_at`, `updated_at`) VALUES
(1, 'Công ty TNHH Vải Việt', 'Nguyễn Văn A', '0901234567', 'contact@vaiviet.com', '123 Đường ABC, Phường 1, Quận 1, TP.HCM', 'active', '2025-10-26 22:30:54', '2025-10-26 22:30:54'),
(2, 'Nhà máy Dệt Nam Định', 'Trần Thị B', '0912345678', 'info@detnamdinh.vn', '456 Đường XYZ, Nam Định', 'active', '2025-10-26 22:30:54', '2025-10-28 20:28:29'),
(3, 'Công ty CP Dệt May Hà Nội', 'Lê Văn C', '0923456789', 'sales@detmayhanoi.com', '789 Đường DEF, Hà Nội', 'active', '2025-10-26 22:30:54', '2025-10-26 22:30:54'),
(4, 'Xưởng May Quần Áo Đà Nẵng', 'Phạm Thị D', '0934567890', 'info@maydadang.com', '321 Đường GHI, Đà Nẵng', 'active', '2025-10-26 22:30:54', '2025-10-26 22:30:54'),
(5, 'Công ty TNHH Ngoại Thương Bình Dương', 'Hoàng Văn E', '0945678901', 'contact@ngoaihuongbd.com', '654 Đường JKL, Bình Dương', 'active', '2025-10-26 22:30:54', '2025-10-26 22:30:54'),
(6, 'Công ty Dệt Kim Hoàng Gia', 'Võ Thị F', '0956789012', 'info@detkimhoanggia.com', '987 Đường MNO, TP.HCM', 'inactive', '2025-10-26 22:30:54', '2025-10-26 22:30:54'),
(7, 'Xưởng Sản Xuất Vải Tổng Hợp', 'Đặng Văn G', '0967890123', 'sales@vaitonghop.com', '147 Đường PQR, Long An', 'active', '2025-10-26 22:30:54', '2025-10-26 22:30:54'),
(8, 'Công ty CP Dệt May Miền Nam', 'Bùi Thị H', '0978901234', 'contact@detmaymiennam.com', '258 Đường STU, TP.HCM', 'suspended', '2025-10-26 22:30:54', '2025-10-26 22:30:54'),
(9, 'Nhà Cung Cấp Phụ Liệu Thời Trang', 'Ngô Văn I', '0989012345', 'info@phulieuthoitrang.com', '369 Đường VWX, TP.HCM', 'active', '2025-10-26 22:30:54', '2025-10-26 22:30:54'),
(10, 'Công ty TNHH Xuất Nhập Khẩu Dệt May', 'Dương Thị K', '0990123456', 'sales@xuatnhapkhau.com', '741 Đường YZA, TP.HCM', 'active', '2025-10-26 22:30:54', '2025-10-26 22:30:54');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `account_id` int(11) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `gender` enum('male','female','other') DEFAULT 'other',
  `birthdate` date DEFAULT NULL,
  `avatar_url` varchar(500) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Đang đổ dữ liệu cho bảng `users`
--

INSERT INTO `users` (`user_id`, `account_id`, `first_name`, `last_name`, `email`, `phone`, `gender`, `birthdate`, `avatar_url`) VALUES
(1, 1, 'Admin', 'User', 'admin@shopswift.com', '+84987654321', 'other', NULL, NULL),
(2, 2, 'Nguyen Van', 'Nam', 'nam.nguyen@gmail.com', '+84912345678', 'male', '1990-05-15', NULL),
(3, 3, 'Tran Thi', 'Lan Anh', 'lananh.tran@gmail.com', '0353126350', NULL, NULL, NULL),
(4, 4, 'Le Van', 'Shipper', 'thanhle02032003@gmail.com', '+84934567890', 'male', '1988-12-10', NULL),
(5, 5, 'Pham Thi', 'Delivery', 'shipper2@shopswift.com', '+84945678901', 'female', '1991-03-25', NULL),
(7, 7, 'Nguyễn Văn', 'A', 'test@example.com', '0123456789', 'other', NULL, NULL),
(8, 8, 'Lê Đạt', 'Thành', 'thanhle02032003@gmail.com12', '+84901234567', 'male', '1990-01-01', NULL),
(11, 11, 'aa', '12', 'aaaa@gmail.com', '0253126350', 'other', NULL, NULL),
(12, 13, 'Nguyễn Văn', 'Tài', 'shipper_new1@shopswift.com', '+84956789012', 'male', NULL, NULL),
(13, 14, 'Trần Thị', 'Mai', 'shipper_new2@shopswift.com', '+84967890123', 'male', NULL, NULL),
(14, 15, 'Lê Văn', 'Hùng', 'shipper_new3@shopswift.com', '+84978901234', 'male', NULL, NULL),
(15, 16, 'Phạm Thị', 'Hoa', 'shipper_new4@shopswift.com', '+84989012345', 'male', NULL, NULL),
(16, 18, 'Test', 'User', 'testuser@example.com', '+84987654321', 'other', NULL, NULL),
(19, 22, 'Lê Đạt', 'Thành', 'thanhle11120320003@gmail.com', '+84901123567', 'male', '1990-01-01', NULL),
(21, 22, 'le dat', 'thanh', 'thanhle02032003@gmail.com12345', '0353126350', 'male', NULL, NULL);

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `user_roles`
--

CREATE TABLE `user_roles` (
  `user_id` int(11) NOT NULL,
  `role_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Đang đổ dữ liệu cho bảng `user_roles`
--

INSERT INTO `user_roles` (`user_id`, `role_id`) VALUES
(1, 1),
(2, 2),
(3, 2),
(4, 3),
(5, 3),
(7, 2),
(8, 2),
(11, 2),
(12, 3),
(13, 3),
(14, 3),
(15, 3),
(16, 2),
(19, 2);

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `vouchers`
--

CREATE TABLE `vouchers` (
  `voucher_id` int(11) NOT NULL,
  `code` varchar(50) NOT NULL,
  `discount_amount` decimal(10,2) NOT NULL,
  `discount_type` enum('percent','amount') NOT NULL,
  `max_usage` int(11) DEFAULT NULL,
  `version` int(11) DEFAULT 0,
  `usage_per_user` int(11) DEFAULT NULL,
  `min_order_total` decimal(10,2) DEFAULT 0.00,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Đang đổ dữ liệu cho bảng `vouchers`
--

INSERT INTO `vouchers` (`voucher_id`, `code`, `discount_amount`, `discount_type`, `max_usage`, `version`, `usage_per_user`, `min_order_total`, `start_date`, `end_date`, `status`, `created_at`, `updated_at`) VALUES
(1, 'SALE20', 25.00, 'percent', 100, 0, 1, 600000.00, '2025-08-01', '2025-12-31', 'active', '2025-08-14 12:55:26', '2025-08-24 14:59:14'),
(2, 'WELCOME100K', 100000.00, 'amount', 50, 0, 1, 800000.00, '2025-08-01', '2025-11-30', 'active', '2025-08-14 12:55:26', '2025-08-14 12:55:26'),
(3, 'NEWUSER15', 15.00, 'percent', 200, 0, 1, 300000.00, '2025-08-01', '2025-10-31', 'active', '2025-08-14 12:55:26', '2025-08-14 12:55:26'),
(4, 'FLASH50K', 50000.00, 'amount', 20, 0, 1, 600000.00, '2025-08-14', '2025-08-31', 'active', '2025-08-14 12:55:26', '2025-08-14 12:55:26'),
(5, 'AUTUMN25', 25.00, 'percent', 75, 0, 1, 1000000.00, '2025-09-01', '2025-11-30', 'active', '2025-08-14 12:55:26', '2025-08-14 12:55:26'),
(6, 'TEST20', 20.00, 'percent', 100, 0, 1, 500000.00, '2025-01-01', '2025-12-31', 'active', '2025-08-24 14:23:04', '2025-08-24 14:23:04'),
(7, 'TEST30', 30.00, 'percent', 100, 0, 1, 500000.00, '2025-01-01', '2025-12-31', 'active', '2025-08-24 14:44:02', '2025-08-24 14:44:02');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `voucher_usages`
--

CREATE TABLE `voucher_usages` (
  `usage_id` int(11) NOT NULL,
  `voucher_id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `used_at` datetime DEFAULT current_timestamp(),
  `order_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Chỉ mục cho các bảng đã đổ
--

--
-- Chỉ mục cho bảng `accounts`
--
ALTER TABLE `accounts`
  ADD PRIMARY KEY (`account_id`),
  ADD UNIQUE KEY `account_name` (`account_name`);

--
-- Chỉ mục cho bảng `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `idx_activity_entity` (`entity_type`,`entity_id`,`created_at`),
  ADD KEY `idx_activity_by` (`changed_by`,`created_at`);

--
-- Chỉ mục cho bảng `addresses`
--
ALTER TABLE `addresses`
  ADD PRIMARY KEY (`address_id`),
  ADD UNIQUE KEY `default_user_id` (`default_user_id`),
  ADD KEY `idx_addresses_user` (`user_id`),
  ADD KEY `idx_addresses_user_default` (`user_id`,`is_default`);

--
-- Chỉ mục cho bảng `admin_notifications`
--
ALTER TABLE `admin_notifications`
  ADD PRIMARY KEY (`notification_id`),
  ADD KEY `fk_admin_notif_order` (`related_order_id`),
  ADD KEY `fk_admin_notif_shipper` (`related_shipper_id`),
  ADD KEY `idx_read_time` (`is_read`,`created_at`),
  ADD KEY `idx_type` (`type`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Chỉ mục cho bảng `carts`
--
ALTER TABLE `carts`
  ADD PRIMARY KEY (`cart_id`),
  ADD UNIQUE KEY `uq_cart_customer` (`customer_id`);

--
-- Chỉ mục cho bảng `cart_items`
--
ALTER TABLE `cart_items`
  ADD PRIMARY KEY (`item_id`),
  ADD UNIQUE KEY `cart_id` (`cart_id`,`variant_id`),
  ADD KEY `idx_cart_items_cart` (`cart_id`),
  ADD KEY `idx_cart_items_variant` (`variant_id`);

--
-- Chỉ mục cho bảng `casso_transactions`
--
ALTER TABLE `casso_transactions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `transaction_id` (`transaction_id`),
  ADD KEY `idx_transaction_id` (`transaction_id`),
  ADD KEY `idx_payment_id` (`payment_id`),
  ADD KEY `idx_order_id` (`order_id`);

--
-- Chỉ mục cho bảng `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`category_id`),
  ADD UNIQUE KEY `slug` (`slug`),
  ADD KEY `parent_id` (`parent_id`);

--
-- Chỉ mục cho bảng `collections`
--
ALTER TABLE `collections`
  ADD PRIMARY KEY (`collection_id`),
  ADD UNIQUE KEY `slug` (`slug`),
  ADD KEY `idx_collections_active` (`is_active`,`display_order`);

--
-- Chỉ mục cho bảng `collection_images`
--
ALTER TABLE `collection_images`
  ADD PRIMARY KEY (`image_id`),
  ADD KEY `fk_collection_images_collection` (`collection_id`);

--
-- Chỉ mục cho bảng `collection_products`
--
ALTER TABLE `collection_products`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_collection_product` (`collection_id`,`product_id`),
  ADD KEY `idx_cp_collection` (`collection_id`),
  ADD KEY `idx_cp_product` (`product_id`);

--
-- Chỉ mục cho bảng `contents`
--
ALTER TABLE `contents`
  ADD PRIMARY KEY (`content_id`),
  ADD UNIQUE KEY `idx_slug` (`slug`),
  ADD KEY `idx_content_type` (`content_type`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_publish_at` (`publish_at`),
  ADD KEY `idx_author_id` (`author_id`);

--
-- Chỉ mục cho bảng `content_blocks`
--
ALTER TABLE `content_blocks`
  ADD PRIMARY KEY (`content_block_id`);

--
-- Chỉ mục cho bảng `content_categories`
--
ALTER TABLE `content_categories`
  ADD PRIMARY KEY (`category_id`),
  ADD UNIQUE KEY `idx_category_slug` (`slug`);

--
-- Chỉ mục cho bảng `content_category_relations`
--
ALTER TABLE `content_category_relations`
  ADD PRIMARY KEY (`content_id`,`category_id`),
  ADD KEY `idx_content_id` (`content_id`),
  ADD KEY `idx_category_id` (`category_id`);

--
-- Chỉ mục cho bảng `conversations`
--
ALTER TABLE `conversations`
  ADD PRIMARY KEY (`conversation_id`),
  ADD KEY `idx_conversations_customer` (`customer_id`,`status`,`last_updated_at`);

--
-- Chỉ mục cho bảng `customers`
--
ALTER TABLE `customers`
  ADD PRIMARY KEY (`user_id`);

--
-- Chỉ mục cho bảng `media`
--
ALTER TABLE `media`
  ADD PRIMARY KEY (`media_id`),
  ADD KEY `block_id` (`block_id`);

--
-- Chỉ mục cho bảng `messages`
--
ALTER TABLE `messages`
  ADD PRIMARY KEY (`message_id`),
  ADD KEY `idx_messages_conv` (`conversation_id`,`sent_at`),
  ADD KEY `idx_messages_sender` (`sender_id`,`sent_at`);

--
-- Chỉ mục cho bảng `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`notification_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Chỉ mục cho bảng `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`order_id`),
  ADD UNIQUE KEY `invoice_number` (`invoice_number`),
  ADD KEY `address_id` (`address_id`),
  ADD KEY `shipping_method_id` (`shipping_method_id`),
  ADD KEY `voucher_id` (`voucher_id`),
  ADD KEY `idx_orders_created` (`created_at`),
  ADD KEY `idx_orders_status_created` (`status`,`created_at`),
  ADD KEY `idx_orders_customer_status` (`customer_id`,`status`),
  ADD KEY `idx_orders_customer_created` (`customer_id`,`created_at`),
  ADD KEY `idx_orders_invoice_url` (`invoice_url`);

--
-- Chỉ mục cho bảng `order_delivery_events`
--
ALTER TABLE `order_delivery_events`
  ADD PRIMARY KEY (`event_id`),
  ADD KEY `idx_order_delivery_events_order` (`order_id`),
  ADD KEY `idx_order_delivery_events_shipper` (`shipper_id`);

--
-- Chỉ mục cho bảng `order_delivery_proofs`
--
ALTER TABLE `order_delivery_proofs`
  ADD PRIMARY KEY (`proof_id`),
  ADD KEY `idx_order_delivery_proofs_order` (`order_id`),
  ADD KEY `idx_order_delivery_proofs_shipper` (`shipper_id`);

--
-- Chỉ mục cho bảng `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`item_id`),
  ADD KEY `variant_id` (`variant_id`),
  ADD KEY `idx_order_items_order` (`order_id`);

--
-- Chỉ mục cho bảng `order_status_logs`
--
ALTER TABLE `order_status_logs`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `changed_by` (`changed_by`);

--
-- Chỉ mục cho bảng `order_tracking_events`
--
ALTER TABLE `order_tracking_events`
  ADD PRIMARY KEY (`event_id`),
  ADD KEY `fk_order_events_user` (`created_by`),
  ADD KEY `idx_order_time` (`order_id`,`created_at`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Chỉ mục cho bảng `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`payment_id`),
  ADD UNIQUE KEY `transaction_id` (`transaction_id`),
  ADD KEY `idx_payments_order` (`order_id`,`status`),
  ADD KEY `idx_payments_txid` (`transaction_id`),
  ADD KEY `idx_payments_method_status_time` (`method`,`status`,`created_at`),
  ADD KEY `idx_payments_created` (`created_at`);

--
-- Chỉ mục cho bảng `payos_transactions`
--
ALTER TABLE `payos_transactions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `transaction_id` (`transaction_id`),
  ADD KEY `idx_order_id` (`order_id`),
  ADD KEY `idx_payment_id` (`payment_id`);

--
-- Chỉ mục cho bảng `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`product_id`),
  ADD UNIQUE KEY `slug` (`slug`),
  ADD KEY `idx_products_category` (`category_id`,`status`,`is_featured`);

--
-- Chỉ mục cho bảng `product_images`
--
ALTER TABLE `product_images`
  ADD PRIMARY KEY (`image_id`),
  ADD UNIQUE KEY `main_key` (`main_key`),
  ADD KEY `idx_images_product` (`product_id`,`position`),
  ADD KEY `idx_images_variant` (`variant_id`);

--
-- Chỉ mục cho bảng `product_variants`
--
ALTER TABLE `product_variants`
  ADD PRIMARY KEY (`variant_id`),
  ADD UNIQUE KEY `product_size` (`product_id`,`size_id`),
  ADD UNIQUE KEY `sku` (`sku`),
  ADD KEY `size_id` (`size_id`),
  ADD KEY `idx_variants_product_status` (`product_id`,`status`),
  ADD KEY `idx_variants_product_stock` (`product_id`,`stock_quantity`);

--
-- Chỉ mục cho bảng `purchase_items`
--
ALTER TABLE `purchase_items`
  ADD PRIMARY KEY (`item_id`),
  ADD KEY `variant_id` (`variant_id`),
  ADD KEY `idx_pr_items_receipt` (`receipt_id`);

--
-- Chỉ mục cho bảng `purchase_receipts`
--
ALTER TABLE `purchase_receipts`
  ADD PRIMARY KEY (`receipt_id`),
  ADD KEY `idx_pr_receipts_supplier` (`supplier_id`,`created_at`),
  ADD KEY `idx_purchase_receipts_supplier` (`supplier_id`),
  ADD KEY `idx_purchase_receipts_status` (`status`);

--
-- Chỉ mục cho bảng `refresh_tokens`
--
ALTER TABLE `refresh_tokens`
  ADD PRIMARY KEY (`token_id`),
  ADD UNIQUE KEY `refresh_token` (`refresh_token`),
  ADD KEY `user_id` (`user_id`);

--
-- Chỉ mục cho bảng `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`role_id`),
  ADD UNIQUE KEY `role_name` (`role_name`);

--
-- Chỉ mục cho bảng `shippers`
--
ALTER TABLE `shippers`
  ADD PRIMARY KEY (`user_id`);

--
-- Chỉ mục cho bảng `shipper_locations`
--
ALTER TABLE `shipper_locations`
  ADD PRIMARY KEY (`location_id`),
  ADD KEY `idx_shipper_time` (`shipper_id`,`captured_at`),
  ADD KEY `idx_order_time` (`order_id`,`captured_at`),
  ADD KEY `idx_captured_at` (`captured_at`);

--
-- Chỉ mục cho bảng `shipper_performance`
--
ALTER TABLE `shipper_performance`
  ADD PRIMARY KEY (`shipper_id`,`period_start_date`,`period_end_date`);

--
-- Chỉ mục cho bảng `shipping_logs`
--
ALTER TABLE `shipping_logs`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `idx_shipping_logs_tracking` (`tracking_id`,`captured_at`);

--
-- Chỉ mục cho bảng `shipping_methods`
--
ALTER TABLE `shipping_methods`
  ADD PRIMARY KEY (`shipping_method_id`);

--
-- Chỉ mục cho bảng `shipping_tracking`
--
ALTER TABLE `shipping_tracking`
  ADD PRIMARY KEY (`tracking_id`),
  ADD UNIQUE KEY `uq_tracking_order` (`order_id`),
  ADD KEY `idx_tracking_shipper` (`shipper_id`,`last_updated`);

--
-- Chỉ mục cho bảng `sizes`
--
ALTER TABLE `sizes`
  ADD PRIMARY KEY (`size_id`),
  ADD UNIQUE KEY `uq_sizes_name` (`size_name`);

--
-- Chỉ mục cho bảng `suppliers`
--
ALTER TABLE `suppliers`
  ADD PRIMARY KEY (`supplier_id`),
  ADD KEY `idx_suppliers_status` (`status`);

--
-- Chỉ mục cho bảng `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_users_email` (`email`),
  ADD KEY `idx_users_account` (`account_id`);

--
-- Chỉ mục cho bảng `user_roles`
--
ALTER TABLE `user_roles`
  ADD PRIMARY KEY (`user_id`,`role_id`),
  ADD KEY `role_id` (`role_id`);

--
-- Chỉ mục cho bảng `vouchers`
--
ALTER TABLE `vouchers`
  ADD PRIMARY KEY (`voucher_id`),
  ADD UNIQUE KEY `code` (`code`);

--
-- Chỉ mục cho bảng `voucher_usages`
--
ALTER TABLE `voucher_usages`
  ADD PRIMARY KEY (`usage_id`),
  ADD UNIQUE KEY `uq_voucher_usage_order` (`order_id`),
  ADD KEY `voucher_id` (`voucher_id`),
  ADD KEY `idx_vu_customer_voucher` (`customer_id`,`voucher_id`);

--
-- AUTO_INCREMENT cho các bảng đã đổ
--

--
-- AUTO_INCREMENT cho bảng `accounts`
--
ALTER TABLE `accounts`
  MODIFY `account_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT cho bảng `activity_logs`
--
ALTER TABLE `activity_logs`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=93;

--
-- AUTO_INCREMENT cho bảng `addresses`
--
ALTER TABLE `addresses`
  MODIFY `address_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=77;

--
-- AUTO_INCREMENT cho bảng `admin_notifications`
--
ALTER TABLE `admin_notifications`
  MODIFY `notification_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT cho bảng `carts`
--
ALTER TABLE `carts`
  MODIFY `cart_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT cho bảng `cart_items`
--
ALTER TABLE `cart_items`
  MODIFY `item_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=32;

--
-- AUTO_INCREMENT cho bảng `casso_transactions`
--
ALTER TABLE `casso_transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=43;

--
-- AUTO_INCREMENT cho bảng `categories`
--
ALTER TABLE `categories`
  MODIFY `category_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT cho bảng `collections`
--
ALTER TABLE `collections`
  MODIFY `collection_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT cho bảng `collection_images`
--
ALTER TABLE `collection_images`
  MODIFY `image_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT cho bảng `collection_products`
--
ALTER TABLE `collection_products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT cho bảng `contents`
--
ALTER TABLE `contents`
  MODIFY `content_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT cho bảng `content_blocks`
--
ALTER TABLE `content_blocks`
  MODIFY `content_block_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT cho bảng `content_categories`
--
ALTER TABLE `content_categories`
  MODIFY `category_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT cho bảng `conversations`
--
ALTER TABLE `conversations`
  MODIFY `conversation_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT cho bảng `media`
--
ALTER TABLE `media`
  MODIFY `media_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT cho bảng `messages`
--
ALTER TABLE `messages`
  MODIFY `message_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=123;

--
-- AUTO_INCREMENT cho bảng `notifications`
--
ALTER TABLE `notifications`
  MODIFY `notification_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT cho bảng `orders`
--
ALTER TABLE `orders`
  MODIFY `order_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=72;

--
-- AUTO_INCREMENT cho bảng `order_delivery_events`
--
ALTER TABLE `order_delivery_events`
  MODIFY `event_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=81;

--
-- AUTO_INCREMENT cho bảng `order_delivery_proofs`
--
ALTER TABLE `order_delivery_proofs`
  MODIFY `proof_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT cho bảng `order_items`
--
ALTER TABLE `order_items`
  MODIFY `item_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=71;

--
-- AUTO_INCREMENT cho bảng `order_status_logs`
--
ALTER TABLE `order_status_logs`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=105;

--
-- AUTO_INCREMENT cho bảng `order_tracking_events`
--
ALTER TABLE `order_tracking_events`
  MODIFY `event_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT cho bảng `payments`
--
ALTER TABLE `payments`
  MODIFY `payment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=68;

--
-- AUTO_INCREMENT cho bảng `payos_transactions`
--
ALTER TABLE `payos_transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT cho bảng `products`
--
ALTER TABLE `products`
  MODIFY `product_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=61;

--
-- AUTO_INCREMENT cho bảng `product_images`
--
ALTER TABLE `product_images`
  MODIFY `image_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1021;

--
-- AUTO_INCREMENT cho bảng `product_variants`
--
ALTER TABLE `product_variants`
  MODIFY `variant_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1555;

--
-- AUTO_INCREMENT cho bảng `purchase_items`
--
ALTER TABLE `purchase_items`
  MODIFY `item_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=62;

--
-- AUTO_INCREMENT cho bảng `purchase_receipts`
--
ALTER TABLE `purchase_receipts`
  MODIFY `receipt_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=32;

--
-- AUTO_INCREMENT cho bảng `refresh_tokens`
--
ALTER TABLE `refresh_tokens`
  MODIFY `token_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=544;

--
-- AUTO_INCREMENT cho bảng `roles`
--
ALTER TABLE `roles`
  MODIFY `role_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT cho bảng `shipper_locations`
--
ALTER TABLE `shipper_locations`
  MODIFY `location_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT cho bảng `shipping_logs`
--
ALTER TABLE `shipping_logs`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT cho bảng `shipping_methods`
--
ALTER TABLE `shipping_methods`
  MODIFY `shipping_method_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT cho bảng `shipping_tracking`
--
ALTER TABLE `shipping_tracking`
  MODIFY `tracking_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT cho bảng `sizes`
--
ALTER TABLE `sizes`
  MODIFY `size_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT cho bảng `suppliers`
--
ALTER TABLE `suppliers`
  MODIFY `supplier_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT cho bảng `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT cho bảng `vouchers`
--
ALTER TABLE `vouchers`
  MODIFY `voucher_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT cho bảng `voucher_usages`
--
ALTER TABLE `voucher_usages`
  MODIFY `usage_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Các ràng buộc cho các bảng đã đổ
--

--
-- Các ràng buộc cho bảng `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD CONSTRAINT `activity_logs_ibfk_1` FOREIGN KEY (`changed_by`) REFERENCES `users` (`user_id`);

--
-- Các ràng buộc cho bảng `addresses`
--
ALTER TABLE `addresses`
  ADD CONSTRAINT `addresses_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Các ràng buộc cho bảng `admin_notifications`
--
ALTER TABLE `admin_notifications`
  ADD CONSTRAINT `fk_admin_notif_order` FOREIGN KEY (`related_order_id`) REFERENCES `orders` (`order_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_admin_notif_shipper` FOREIGN KEY (`related_shipper_id`) REFERENCES `shippers` (`user_id`) ON DELETE CASCADE;

--
-- Các ràng buộc cho bảng `carts`
--
ALTER TABLE `carts`
  ADD CONSTRAINT `carts_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`user_id`);

--
-- Các ràng buộc cho bảng `cart_items`
--
ALTER TABLE `cart_items`
  ADD CONSTRAINT `cart_items_ibfk_1` FOREIGN KEY (`cart_id`) REFERENCES `carts` (`cart_id`),
  ADD CONSTRAINT `cart_items_ibfk_2` FOREIGN KEY (`variant_id`) REFERENCES `product_variants` (`variant_id`);

--
-- Các ràng buộc cho bảng `categories`
--
ALTER TABLE `categories`
  ADD CONSTRAINT `categories_ibfk_1` FOREIGN KEY (`parent_id`) REFERENCES `categories` (`category_id`) ON DELETE SET NULL;

--
-- Các ràng buộc cho bảng `collection_images`
--
ALTER TABLE `collection_images`
  ADD CONSTRAINT `fk_collection_images_collection` FOREIGN KEY (`collection_id`) REFERENCES `collections` (`collection_id`) ON DELETE CASCADE;

--
-- Các ràng buộc cho bảng `collection_products`
--
ALTER TABLE `collection_products`
  ADD CONSTRAINT `collection_products_ibfk_1` FOREIGN KEY (`collection_id`) REFERENCES `collections` (`collection_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `collection_products_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE CASCADE;

--
-- Các ràng buộc cho bảng `contents`
--
ALTER TABLE `contents`
  ADD CONSTRAINT `fk_contents_author` FOREIGN KEY (`author_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

--
-- Các ràng buộc cho bảng `content_category_relations`
--
ALTER TABLE `content_category_relations`
  ADD CONSTRAINT `fk_content_relations_category` FOREIGN KEY (`category_id`) REFERENCES `content_categories` (`category_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_content_relations_content` FOREIGN KEY (`content_id`) REFERENCES `contents` (`content_id`) ON DELETE CASCADE;

--
-- Các ràng buộc cho bảng `conversations`
--
ALTER TABLE `conversations`
  ADD CONSTRAINT `conversations_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`user_id`);

--
-- Các ràng buộc cho bảng `customers`
--
ALTER TABLE `customers`
  ADD CONSTRAINT `customers_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Các ràng buộc cho bảng `media`
--
ALTER TABLE `media`
  ADD CONSTRAINT `media_ibfk_1` FOREIGN KEY (`block_id`) REFERENCES `content_blocks` (`content_block_id`);

--
-- Các ràng buộc cho bảng `messages`
--
ALTER TABLE `messages`
  ADD CONSTRAINT `messages_ibfk_1` FOREIGN KEY (`conversation_id`) REFERENCES `conversations` (`conversation_id`),
  ADD CONSTRAINT `messages_ibfk_2` FOREIGN KEY (`sender_id`) REFERENCES `users` (`user_id`);

--
-- Các ràng buộc cho bảng `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`);

--
-- Các ràng buộc cho bảng `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`user_id`),
  ADD CONSTRAINT `orders_ibfk_2` FOREIGN KEY (`address_id`) REFERENCES `addresses` (`address_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `orders_ibfk_3` FOREIGN KEY (`shipping_method_id`) REFERENCES `shipping_methods` (`shipping_method_id`),
  ADD CONSTRAINT `orders_ibfk_4` FOREIGN KEY (`voucher_id`) REFERENCES `vouchers` (`voucher_id`);

--
-- Các ràng buộc cho bảng `order_delivery_events`
--
ALTER TABLE `order_delivery_events`
  ADD CONSTRAINT `fk_delivery_events_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_delivery_events_shipper` FOREIGN KEY (`shipper_id`) REFERENCES `shippers` (`user_id`) ON DELETE SET NULL;

--
-- Các ràng buộc cho bảng `order_delivery_proofs`
--
ALTER TABLE `order_delivery_proofs`
  ADD CONSTRAINT `fk_delivery_proofs_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_delivery_proofs_shipper` FOREIGN KEY (`shipper_id`) REFERENCES `shippers` (`user_id`) ON DELETE SET NULL;

--
-- Các ràng buộc cho bảng `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `order_items_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`),
  ADD CONSTRAINT `order_items_ibfk_2` FOREIGN KEY (`variant_id`) REFERENCES `product_variants` (`variant_id`);

--
-- Các ràng buộc cho bảng `order_status_logs`
--
ALTER TABLE `order_status_logs`
  ADD CONSTRAINT `order_status_logs_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`),
  ADD CONSTRAINT `order_status_logs_ibfk_2` FOREIGN KEY (`changed_by`) REFERENCES `users` (`user_id`);

--
-- Các ràng buộc cho bảng `order_tracking_events`
--
ALTER TABLE `order_tracking_events`
  ADD CONSTRAINT `fk_order_events_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_order_events_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

--
-- Các ràng buộc cho bảng `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`);

--
-- Các ràng buộc cho bảng `payos_transactions`
--
ALTER TABLE `payos_transactions`
  ADD CONSTRAINT `fk_payos_payment` FOREIGN KEY (`payment_id`) REFERENCES `payments` (`payment_id`) ON DELETE CASCADE;

--
-- Các ràng buộc cho bảng `products`
--
ALTER TABLE `products`
  ADD CONSTRAINT `products_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`category_id`);

--
-- Các ràng buộc cho bảng `product_images`
--
ALTER TABLE `product_images`
  ADD CONSTRAINT `product_images_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`),
  ADD CONSTRAINT `product_images_ibfk_2` FOREIGN KEY (`variant_id`) REFERENCES `product_variants` (`variant_id`) ON DELETE SET NULL;

--
-- Các ràng buộc cho bảng `product_variants`
--
ALTER TABLE `product_variants`
  ADD CONSTRAINT `product_variants_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`),
  ADD CONSTRAINT `product_variants_ibfk_2` FOREIGN KEY (`size_id`) REFERENCES `sizes` (`size_id`);

--
-- Các ràng buộc cho bảng `purchase_items`
--
ALTER TABLE `purchase_items`
  ADD CONSTRAINT `purchase_items_ibfk_1` FOREIGN KEY (`receipt_id`) REFERENCES `purchase_receipts` (`receipt_id`),
  ADD CONSTRAINT `purchase_items_ibfk_2` FOREIGN KEY (`variant_id`) REFERENCES `product_variants` (`variant_id`);

--
-- Các ràng buộc cho bảng `purchase_receipts`
--
ALTER TABLE `purchase_receipts`
  ADD CONSTRAINT `purchase_receipts_ibfk_1` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`supplier_id`);

--
-- Các ràng buộc cho bảng `refresh_tokens`
--
ALTER TABLE `refresh_tokens`
  ADD CONSTRAINT `refresh_tokens_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Các ràng buộc cho bảng `shippers`
--
ALTER TABLE `shippers`
  ADD CONSTRAINT `shippers_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Các ràng buộc cho bảng `shipper_locations`
--
ALTER TABLE `shipper_locations`
  ADD CONSTRAINT `fk_shipper_locations_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_shipper_locations_shipper` FOREIGN KEY (`shipper_id`) REFERENCES `shippers` (`user_id`) ON DELETE CASCADE;

--
-- Các ràng buộc cho bảng `shipper_performance`
--
ALTER TABLE `shipper_performance`
  ADD CONSTRAINT `shipper_performance_ibfk_1` FOREIGN KEY (`shipper_id`) REFERENCES `shippers` (`user_id`);

--
-- Các ràng buộc cho bảng `shipping_logs`
--
ALTER TABLE `shipping_logs`
  ADD CONSTRAINT `shipping_logs_ibfk_1` FOREIGN KEY (`tracking_id`) REFERENCES `shipping_tracking` (`tracking_id`);

--
-- Các ràng buộc cho bảng `shipping_tracking`
--
ALTER TABLE `shipping_tracking`
  ADD CONSTRAINT `shipping_tracking_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`),
  ADD CONSTRAINT `shipping_tracking_ibfk_2` FOREIGN KEY (`shipper_id`) REFERENCES `shippers` (`user_id`);

--
-- Các ràng buộc cho bảng `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`account_id`) ON DELETE CASCADE;

--
-- Các ràng buộc cho bảng `user_roles`
--
ALTER TABLE `user_roles`
  ADD CONSTRAINT `user_roles_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `user_roles_ibfk_2` FOREIGN KEY (`role_id`) REFERENCES `roles` (`role_id`);

--
-- Các ràng buộc cho bảng `voucher_usages`
--
ALTER TABLE `voucher_usages`
  ADD CONSTRAINT `voucher_usages_ibfk_1` FOREIGN KEY (`voucher_id`) REFERENCES `vouchers` (`voucher_id`),
  ADD CONSTRAINT `voucher_usages_ibfk_2` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`user_id`),
  ADD CONSTRAINT `voucher_usages_ibfk_3` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
