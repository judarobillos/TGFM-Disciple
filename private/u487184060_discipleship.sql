-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: Aug 27, 2026 at 05:20 AM
-- Server version: 11.8.8-MariaDB-log
-- PHP Version: 7.2.34

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `u487184060_discipleship`
--

-- --------------------------------------------------------

--
-- Table structure for table `content_plans`
--

CREATE TABLE `content_plans` (
  `id` varchar(32) NOT NULL,
  `name` varchar(120) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `blurb` text DEFAULT NULL,
  `billing` enum('once','week','month','year') NOT NULL DEFAULT 'month',
  `scope` enum('all','topic') NOT NULL DEFAULT 'all',
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `position` int(11) NOT NULL DEFAULT 0,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `content_plans`
--

INSERT INTO `content_plans` (`id`, `name`, `price`, `blurb`, `billing`, `scope`, `active`, `position`, `updated_at`) VALUES
('month', 'Monthly Pass', 299.00, 'Every training, series and topic, open for a month.', 'month', 'all', 1, 2, '2026-08-26 04:14:16'),
('single', 'Individual Teaching', 49.00, 'One teaching, yours to keep. Choose the topic at checkout and it stays open in your account.', 'once', 'topic', 1, 0, '2026-08-26 04:14:16'),
('week', 'Weekly Pass', 99.00, 'Everything TGFM teaches, open for seven days.', 'week', 'all', 1, 1, '2026-08-26 04:14:16'),
('year', 'Annual Pass', 2990.00, 'A year of everything, at the lowest monthly rate TGFM offers.', 'year', 'all', 1, 3, '2026-08-26 04:14:16');

-- --------------------------------------------------------

--
-- Table structure for table `content_series`
--

CREATE TABLE `content_series` (
  `training_id` varchar(32) NOT NULL,
  `id` varchar(32) NOT NULL,
  `title` varchar(160) NOT NULL,
  `blurb` text DEFAULT NULL,
  `teacher` varchar(120) NOT NULL DEFAULT '',
  `position` int(11) NOT NULL DEFAULT 0,
  `published` tinyint(1) NOT NULL DEFAULT 0,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `image` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `content_series`
--

INSERT INTO `content_series` (`training_id`, `id`, `title`, `blurb`, `teacher`, `position`, `published`, `updated_at`, `image`) VALUES
('t1', 'se_7zwdmd', 'Holy Ghost Project', 'PLACEHOLDER — replace with TGFM own description.', 'Pastor Roy Oliveros', 2, 1, '2026-08-27 03:41:48', NULL),
('t1', 'se1', 'Question & Answer With Pastor Roy & Rochel Oliveros', 'PLACEHOLDER — replace with TGFM own description.', 'Pastor Roy Oliveros', 0, 1, '2026-08-27 04:27:43', 'uploads/23a12a6327750c3c.jpg'),
('t1', 'se2', 'Discipleship Series', 'PLACEHOLDER — replace with TGFM own description.', 'Rochel Oliveros', 1, 1, '2026-08-27 04:27:59', NULL),
('t2', 'se1', 'Ambassadors Briefing Series 1', 'PLACEHOLDER — replace with TGFM own description.', 'Pastor Roy Oliveros', 0, 1, '2026-08-26 05:27:43', 'uploads/50cd6d5bdf1686c0.jpg'),
('t2', 'se2', 'Ambassadors Briefing Series 2', 'PLACEHOLDER — rename this in the admin.', 'Rochel Oliveros', 1, 1, '2026-08-27 04:11:50', 'uploads/e54ae2a876d41c77.jpg'),
('t3', 'se1', 'The Seven Churches Series', 'PLACEHOLDER — replace with TGFM own description.', 'Pastor Roy Oliveros', 0, 1, '2026-08-27 04:28:29', 'uploads/b10d6cd8ed0c4942.jpg'),
('t3', 'se2', 'Life Issues', 'PLACEHOLDER — replace with TGFM own description.', 'Rochel Oliveros', 1, 1, '2026-08-27 04:28:39', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `content_topics`
--

CREATE TABLE `content_topics` (
  `training_id` varchar(32) NOT NULL,
  `series_id` varchar(32) NOT NULL,
  `id` varchar(32) NOT NULL,
  `title` varchar(200) NOT NULL,
  `yt_id` varchar(20) NOT NULL,
  `duration` varchar(12) NOT NULL DEFAULT '00:00',
  `notes` text DEFAULT NULL,
  `position` int(11) NOT NULL DEFAULT 0,
  `published` tinyint(1) NOT NULL DEFAULT 0,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `image` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `content_topics`
--

INSERT INTO `content_topics` (`training_id`, `series_id`, `id`, `title`, `yt_id`, `duration`, `notes`, `position`, `published`, `updated_at`, `image`) VALUES
('t1', 'se_7zwdmd', 'v_rgwo81', 'Holy Ghost Project #ReadyForHarvest - July 15, 2026', 'PSQ1kc3Xamw', '00:00', '', 0, 1, '2026-08-27 03:36:29', NULL),
('t1', 'se_7zwdmd', 'v_w9yc33', 'Holy Ghost Project #FourMonths #LiftUpYourEyes - July 8, 2026', 'PSQ1kc3Xamw', '00:00', '', 1, 1, '2026-08-27 03:36:57', NULL),
('t1', 'se1', 'v_8wj6gm', 'Question & Answer With Pastor Roy Oliveros - August 19, 2026', 'PSQ1kc3Xamw', '00:00', '', 0, 1, '2026-08-26 15:08:10', 'uploads/2e593dbb1fcd24c6.jpg'),
('t1', 'se1', 'v_nvrtvv', 'Video 3', 'f9cts8ED9Kw', '00:00', '', 2, 1, '2026-08-26 05:27:00', 'uploads/8ea9c96a616c1a05.jpg'),
('t1', 'se1', 'v_vza66q', 'Question & Answer With Pastor Roy Oliveros - August 12, 2026', 'PSQ1kc3Xamw', '00:00', '', 1, 1, '2026-08-26 05:26:41', 'uploads/e38049b932ec5986.jpg');

-- --------------------------------------------------------

--
-- Table structure for table `content_trainings`
--

CREATE TABLE `content_trainings` (
  `id` varchar(32) NOT NULL,
  `title` varchar(160) NOT NULL,
  `blurb` text DEFAULT NULL,
  `hue` smallint(6) NOT NULL DEFAULT 220,
  `position` int(11) NOT NULL DEFAULT 0,
  `published` tinyint(1) NOT NULL DEFAULT 0,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `image` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `content_trainings`
--

INSERT INTO `content_trainings` (`id`, `title`, `blurb`, `hue`, `position`, `published`, `updated_at`, `image`) VALUES
('t1', 'Kingdom Life Training', 'PLACEHOLDER — replace with TGFM own description.', 206, 0, 1, '2026-08-27 04:27:36', 'uploads/eb85646998f4ece1.jpg'),
('t2', 'Ambassadors Briefing', 'PLACEHOLDER — replace with TGFM own description.', 232, 1, 1, '2026-08-27 04:28:11', 'uploads/2da8fdfdc8c17502.jpg'),
('t3', 'Simbalive', 'PLACEHOLDER — replace with TGFM own description.', 205, 2, 1, '2026-08-27 04:28:23', 'uploads/29971e867c7a6def.jpg');

-- --------------------------------------------------------

--
-- Table structure for table `disciples`
--

CREATE TABLE `disciples` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(120) NOT NULL,
  `email` varchar(190) NOT NULL,
  `phone` varchar(40) NOT NULL DEFAULT '',
  `gender` varchar(20) NOT NULL DEFAULT '',
  `location` varchar(160) NOT NULL DEFAULT '',
  `pastor` varchar(120) NOT NULL DEFAULT '',
  `de_year` varchar(10) NOT NULL DEFAULT '',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `disciples`
--

INSERT INTO `disciples` (`id`, `name`, `email`, `phone`, `gender`, `location`, `pastor`, `de_year`, `created_at`, `updated_at`) VALUES
(1, 'Juda Robillos', 'robillosjuda+test6@gmail.com', '+639461432307', 'Female', 'Davao', 'Ps Roy and Rochel', '2017', '2026-08-27 03:45:22', '2026-08-27 03:45:22'),
(2, 'Juda Robillos', 'robillosjuda+test7@gmail.com', '+639461432307', 'Female', 'Davao', 'Ps Roy and Rochel', '2017', '2026-08-27 04:14:13', '2026-08-27 04:14:13'),
(3, 'Juda Robillos', 'robillosjuda+test8@gmail.com', '+639461432307', 'Female', 'Davao', 'Ps Roy and Rochel', '2017', '2026-08-27 04:41:10', '2026-08-27 04:41:10');

-- --------------------------------------------------------

--
-- Table structure for table `disciple_pastors`
--

CREATE TABLE `disciple_pastors` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(120) NOT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `position` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `disciple_pastors`
--

INSERT INTO `disciple_pastors` (`id`, `name`, `active`, `position`) VALUES
(1, 'Ps Roy and Rochel', 1, 0),
(2, 'Ps Dan Ramsis and RubyJane', 1, 1),
(3, 'Ps Joki and Marlen', 1, 2),
(4, 'Ps Josh and Nove Nerez', 1, 3),
(5, 'Ps Jun and Irish Quino', 1, 4),
(6, 'Ps Kris and Jen Alicante', 1, 5),
(7, 'Ps Robel and Mau Bello', 1, 6),
(8, 'Ps Bebith Baste', 1, 7),
(9, 'Ps Daisery Hangad', 1, 8),
(10, 'Ps Ella Suan', 1, 9),
(11, 'Ps Flong Bernales', 1, 10),
(12, 'Ps Grace Migriño', 1, 11),
(13, 'Ps Aaron Lorilla', 1, 12),
(14, 'Ps Don Frias', 1, 13);

-- --------------------------------------------------------

--
-- Table structure for table `entitlements`
--

CREATE TABLE `entitlements` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED DEFAULT NULL,
  `email` varchar(190) NOT NULL,
  `training_id` varchar(32) NOT NULL,
  `series_id` varchar(32) NOT NULL,
  `topic_id` varchar(32) NOT NULL,
  `reference` varchar(36) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `entitlements`
--

INSERT INTO `entitlements` (`id`, `user_id`, `email`, `training_id`, `series_id`, `topic_id`, `reference`, `created_at`) VALUES
(1, 9, 'robillosjuda+test4@gmail.com', 't1', 'se1', 'v_8wj6gm', 'TGFM-2608-5835EDBA', '2026-08-26 04:50:08'),
(2, NULL, 'robillosjuda+test6@gmail.com', 't1', 'se_7zwdmd', 'v_rgwo81', 'TGFM-2608-24E2F47B', '2026-08-27 03:59:32'),
(3, NULL, 'robillosjuda+test7@gmail.com', 't1', 'se1', 'v_vza66q', 'TGFM-2608-D12E8F47', '2026-08-27 04:15:04');

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `id` int(10) UNSIGNED NOT NULL,
  `reference` varchar(36) NOT NULL,
  `user_id` int(10) UNSIGNED DEFAULT NULL,
  `email` varchar(190) NOT NULL,
  `name` varchar(120) NOT NULL,
  `plan` varchar(20) NOT NULL,
  `period` varchar(10) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `currency` char(3) NOT NULL DEFAULT 'PHP',
  `method` enum('maya','paypal') NOT NULL,
  `status` enum('pending','paid','failed','cancelled','refunded') NOT NULL DEFAULT 'pending',
  `gateway_id` varchar(190) DEFAULT NULL,
  `gateway_state` varchar(60) DEFAULT NULL,
  `claim_token` char(32) DEFAULT NULL,
  `claimed_at` datetime DEFAULT NULL,
  `access_until` date DEFAULT NULL,
  `raw` mediumtext DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `paid_at` datetime DEFAULT NULL,
  `topic_ref` varchar(104) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `payments`
--

INSERT INTO `payments` (`id`, `reference`, `user_id`, `email`, `name`, `plan`, `period`, `amount`, `currency`, `method`, `status`, `gateway_id`, `gateway_state`, `claim_token`, `claimed_at`, `access_until`, `raw`, `created_at`, `paid_at`, `topic_ref`) VALUES
(1, 'TGFM-2608-B8F8438D', NULL, 'robillosjuda@gmail.com', 'Juda Robillos', 'week', 'week', 140.00, 'PHP', 'maya', 'cancelled', '997210f7-cb0e-476a-9bf4-66d221bc6256', 'BUYER_CANCELLED', NULL, NULL, '2026-09-01', '', '2026-08-25 17:44:03', NULL, NULL),
(2, 'TGFM-2608-BC56AC60', NULL, 'judarobillosdev@gmail.com', 'Juda Robillos', 'month', 'month', 499.00, 'PHP', 'maya', 'pending', 'c004b992-7397-489a-8fe1-da54e4c5e0a7', NULL, NULL, NULL, '2026-09-25', NULL, '2026-08-25 17:44:55', NULL, NULL),
(3, 'TGFM-2608-2927115D', NULL, 'judarobillosdev@gmail.com', 'Juda Robillos', 'month', 'month', 499.00, 'PHP', 'maya', 'pending', '78419357-4c37-47fd-8085-f170b80d2485', NULL, NULL, NULL, '2026-09-25', NULL, '2026-08-25 17:54:31', NULL, NULL),
(4, 'TGFM-2608-FF78E3BD', NULL, 'judarobillosdev@gmail.com', 'Juda Robillos', 'week', 'week', 140.00, 'PHP', 'maya', 'paid', 'a64f5fb1-bbbd-4d40-87b0-f3fbfc1bc7c7', 'PAYMENT_SUCCESS', 'd8f0ec1480726b80b4d24903d1225548', NULL, '2026-09-01', '{\"id\":\"a64f5fb1-bbbd-4d40-87b0-f3fbfc1bc7c7\",\"isPaid\":true,\"status\":\"PAYMENT_SUCCESS\",\"amount\":\"140\",\"currency\":\"PHP\",\"canVoid\":true,\"canRefund\":false,\"canCapture\":false,\"createdAt\":\"2026-08-25T18:21:26.169Z\",\"updatedAt\":\"2026-08-25T18:22:10.601Z\",\"description\":\"Charge for judarobillosdev@gmail.com\",\"paymentTokenId\":\"Ck7alk049WrnN6kEZOacUGNR8b5TpG43oZDIWHOx9PDNqFKbecwwfh7Ssyf6SN8diEQ48xKcJwL20MLszof4Ae9Nv07csV4zKUQYIZD13T3GRqi9bZAAMPBVkbvpm9Sf7PlgJ4Tj3rbd8wgnav9Sf69muklspyZni7Y\",\"fundSource\":{\"type\":\"card\",\"id\":\"Ck7alk049WrnN6kEZOacUGNR8b5TpG43oZDIWHOx9PDNqFKbecwwfh7Ssyf6SN8diEQ48xKcJwL20MLszof4Ae9Nv07csV4zKUQYIZD13T3GRqi9bZAAMPBVkbvpm9Sf7PlgJ4Tj3rbd8wgnav9Sf69muklspyZni7Y\",\"description\":\"**** **** **** 2346\",\"details\":{\"scheme\":\"master-card\",\"last4\":\"2346\",\"first6\":\"512345\",\"masked\":\"512345******2346\",\"issuer\":\"Others\"}},\"receipt\":{\"transactionId\":\"43a1bc25-f53b-485f-bb7a-885cce159a60\",\"approvalCode\":\"00001234\",\"receiptNo\":\"79a29cb2b52f\",\"approval_code\":\"00001234\"},\"approvalCode\":\"00001234\",\"receiptNumber\":\"79a29cb2b52f\",\"requestReferenceNumber\":\"TGFM-2608-FF78E3BD\"}', '2026-08-25 18:21:26', '2026-08-25 18:37:31', NULL),
(5, 'TGFM-2608-641CD4B2', NULL, 'judarobillosdev@gmail.com', 'Juda Robillos', 'week', 'week', 140.00, 'PHP', 'maya', 'pending', '62561e32-d37c-419f-9d5f-940b9f0ffc62', NULL, NULL, NULL, '2026-09-01', NULL, '2026-08-25 18:24:35', NULL, NULL),
(6, 'TGFM-2608-904895A5', 4, 'judarobillosdev+test@gmail.com', 'Juda Robillos', 'month', 'month', 499.00, 'PHP', 'maya', 'paid', 'c0b91b72-9ba9-4180-9225-8eb2b956a853', 'PAYMENT_SUCCESS', NULL, '2026-08-25 18:39:34', '2026-09-25', '{\"id\":\"c0b91b72-9ba9-4180-9225-8eb2b956a853\",\"isPaid\":true,\"status\":\"PAYMENT_SUCCESS\",\"amount\":\"499\",\"currency\":\"PHP\",\"canVoid\":true,\"canRefund\":false,\"canCapture\":false,\"createdAt\":\"2026-08-25T18:38:11.835Z\",\"updatedAt\":\"2026-08-25T18:39:09.968Z\",\"description\":\"Charge for judarobillosdev+test@gmail.com\",\"paymentTokenId\":\"GSERUuYjnpZRK9H0eHyWF26rKH3pZkPcucXXAuGbiNey40v33FA3beb7VRzPIiLqSWogX13T4zaqQivJtXHxIniADuMokrg6SzXWzA7b82IGxSzfWBtN28Zo3vGjc68YKEBvWtZ79JvWpvWCBcEFIn3BVGkXxMYsn6rGqo\",\"fundSource\":{\"type\":\"card\",\"id\":\"GSERUuYjnpZRK9H0eHyWF26rKH3pZkPcucXXAuGbiNey40v33FA3beb7VRzPIiLqSWogX13T4zaqQivJtXHxIniADuMokrg6SzXWzA7b82IGxSzfWBtN28Zo3vGjc68YKEBvWtZ79JvWpvWCBcEFIn3BVGkXxMYsn6rGqo\",\"description\":\"**** **** **** 4154\",\"details\":{\"scheme\":\"master-card\",\"last4\":\"4154\",\"first6\":\"545301\",\"masked\":\"545301******4154\",\"issuer\":\"Others\"}},\"receipt\":{\"transactionId\":\"3011d70e-f7f6-4996-bce2-3972a4a0dc68\",\"approvalCode\":\"00001234\",\"receiptNo\":\"242323e08747\",\"approval_code\":\"00001234\"},\"approvalCode\":\"00001234\",\"receiptNumber\":\"242323e08747\",\"requestReferenceNumber\":\"TGFM-2608-904895A5\"}', '2026-08-25 18:38:11', '2026-08-25 18:39:15', NULL),
(7, 'TGFM-2608-A0DD6AF6', 5, 'judarobillosdev@gmail.com', 'Juda Robillos', 'week', 'week', 140.00, 'PHP', 'maya', 'paid', '254d82f8-1069-486f-b39b-04ae1c124efa', 'PAYMENT_SUCCESS', NULL, '2026-08-25 22:50:51', '2026-09-01', '{\"id\":\"254d82f8-1069-486f-b39b-04ae1c124efa\",\"isPaid\":true,\"status\":\"PAYMENT_SUCCESS\",\"amount\":\"140\",\"currency\":\"PHP\",\"canVoid\":true,\"canRefund\":false,\"canCapture\":false,\"createdAt\":\"2026-08-25T22:49:36.160Z\",\"updatedAt\":\"2026-08-25T22:50:24.435Z\",\"description\":\"Charge for judarobillosdev@gmail.com\",\"paymentTokenId\":\"zf3eJWWtGFEhFGiNs7KPwnZJoLURrpPYb0riOQRIQ8axHlc4iygqgENY7MdscQrkmvKPLYk2VsPkygFzHVfJzCdVqq9MDDV4jeoeRHO5T54YqaM56hf5gS2rir1RwSmod3FXkBBKiby0kZHjC4KG1tgQcthbzyhsTKm5LSxos\",\"fundSource\":{\"type\":\"card\",\"id\":\"zf3eJWWtGFEhFGiNs7KPwnZJoLURrpPYb0riOQRIQ8axHlc4iygqgENY7MdscQrkmvKPLYk2VsPkygFzHVfJzCdVqq9MDDV4jeoeRHO5T54YqaM56hf5gS2rir1RwSmod3FXkBBKiby0kZHjC4KG1tgQcthbzyhsTKm5LSxos\",\"description\":\"**** **** **** 2346\",\"details\":{\"scheme\":\"master-card\",\"last4\":\"2346\",\"first6\":\"512345\",\"masked\":\"512345******2346\",\"issuer\":\"Others\"}},\"receipt\":{\"transactionId\":\"6a0558ac-cce1-4e32-ab3b-5935ac829524\",\"approvalCode\":\"00001234\",\"receiptNo\":\"7caac195a667\",\"approval_code\":\"00001234\"},\"approvalCode\":\"00001234\",\"receiptNumber\":\"7caac195a667\",\"requestReferenceNumber\":\"TGFM-2608-A0DD6AF6\"}', '2026-08-25 22:49:36', '2026-08-25 22:50:33', NULL),
(8, 'TGFM-2608-B326A8DE', NULL, 'robillosjuda+test@gmail.com', 'Juda Robillos', 'week', 'week', 140.00, 'PHP', 'maya', 'pending', '85507b67-80cf-497a-a564-61cc54dbfaa2', NULL, NULL, NULL, '2026-09-01', NULL, '2026-08-25 23:19:03', NULL, NULL),
(9, 'TGFM-2608-5ABC66EB', NULL, 'robillosjuda+test@gmail.com', 'Juda Robillos', 'week', 'week', 140.00, 'PHP', 'maya', 'pending', '7551f4c3-05bb-4ed6-b017-66346fe03adc', NULL, NULL, NULL, '2026-09-01', NULL, '2026-08-25 23:19:03', NULL, NULL),
(10, 'TGFM-2608-0516D474', 6, 'robillosjuda+test@gmail.com', 'Juda Robillos', 'week', 'week', 140.00, 'PHP', 'maya', 'paid', 'c1cd7777-bc93-4b5c-ba8b-469b18c055f9', 'PAYMENT_SUCCESS', NULL, '2026-08-25 23:20:29', '2026-09-01', '{\"id\":\"c1cd7777-bc93-4b5c-ba8b-469b18c055f9\",\"isPaid\":true,\"status\":\"PAYMENT_SUCCESS\",\"amount\":\"140\",\"currency\":\"PHP\",\"canVoid\":true,\"canRefund\":false,\"canCapture\":false,\"createdAt\":\"2026-08-25T23:19:03.912Z\",\"updatedAt\":\"2026-08-25T23:20:01.457Z\",\"description\":\"Charge for robillosjuda+test@gmail.com\",\"paymentTokenId\":\"N7mAwK9zo9e0KG7pL6Yh3QOQABOpJVp4Tta8KKgrBC5HVUJbXNO4H564nlJGaoGxqaA9lfhqWMOjLl9U5pHfEPx9eGAPqYjcS5IyTVwcYS2898atNn8IAbjHNNXkc3btdtqlylyRmMCK97Aepwg4yPEKDp2ghgr6icgT79U\",\"fundSource\":{\"type\":\"card\",\"id\":\"N7mAwK9zo9e0KG7pL6Yh3QOQABOpJVp4Tta8KKgrBC5HVUJbXNO4H564nlJGaoGxqaA9lfhqWMOjLl9U5pHfEPx9eGAPqYjcS5IyTVwcYS2898atNn8IAbjHNNXkc3btdtqlylyRmMCK97Aepwg4yPEKDp2ghgr6icgT79U\",\"description\":\"**** **** **** 2346\",\"details\":{\"scheme\":\"master-card\",\"last4\":\"2346\",\"first6\":\"512345\",\"masked\":\"512345******2346\",\"issuer\":\"Others\"}},\"receipt\":{\"transactionId\":\"54d2de7f-0637-43a7-8c9c-3919ef6d7b7e\",\"approvalCode\":\"00001234\",\"receiptNo\":\"b6a79e93618a\",\"approval_code\":\"00001234\"},\"approvalCode\":\"00001234\",\"receiptNumber\":\"b6a79e93618a\",\"requestReferenceNumber\":\"TGFM-2608-0516D474\"}', '2026-08-25 23:19:03', '2026-08-25 23:20:10', NULL),
(11, 'TGFM-2608-6BA9DAF0', 7, 'robillosjuda+test2@gmail.com', 'Juda Robillos', 'week', 'week', 140.00, 'PHP', 'maya', 'paid', 'e61340a6-3587-4694-af52-d25b86f213b5', 'PAYMENT_SUCCESS', NULL, '2026-08-25 23:26:58', '2026-09-01', '{\"id\":\"e61340a6-3587-4694-af52-d25b86f213b5\",\"isPaid\":true,\"status\":\"PAYMENT_SUCCESS\",\"amount\":\"140\",\"currency\":\"PHP\",\"canVoid\":true,\"canRefund\":false,\"canCapture\":false,\"createdAt\":\"2026-08-25T23:25:35.289Z\",\"updatedAt\":\"2026-08-25T23:26:10.199Z\",\"description\":\"Charge for robillosjuda+test2@gmail.com\",\"paymentTokenId\":\"SGqQ3oNp7Bb1vpqxSIZzUxtJYTsN2NtyTpIXeQp6SWG76f13Lwj8gKNU1KCvRwiV9DDfVSi09lEObC0AMLYztWGS5Qz1VnYmLwhsxsQfdWdZ4FpX72boLieAhaOLYau4fmPKCZq4s6X7KDJBpZLuMLi6OpSqkC3Drebg\",\"fundSource\":{\"type\":\"card\",\"id\":\"SGqQ3oNp7Bb1vpqxSIZzUxtJYTsN2NtyTpIXeQp6SWG76f13Lwj8gKNU1KCvRwiV9DDfVSi09lEObC0AMLYztWGS5Qz1VnYmLwhsxsQfdWdZ4FpX72boLieAhaOLYau4fmPKCZq4s6X7KDJBpZLuMLi6OpSqkC3Drebg\",\"description\":\"**** **** **** 2346\",\"details\":{\"scheme\":\"master-card\",\"last4\":\"2346\",\"first6\":\"512345\",\"masked\":\"512345******2346\",\"issuer\":\"Others\"}},\"receipt\":{\"transactionId\":\"59201ee1-60df-4932-8c62-a9acb933718a\",\"approvalCode\":\"00001234\",\"receiptNo\":\"48163fbf7570\",\"approval_code\":\"00001234\"},\"approvalCode\":\"00001234\",\"receiptNumber\":\"48163fbf7570\",\"requestReferenceNumber\":\"TGFM-2608-6BA9DAF0\"}', '2026-08-25 23:25:35', '2026-08-25 23:26:13', NULL),
(12, 'TGFM-2608-5835EDBA', 9, 'robillosjuda+test4@gmail.com', 'Juda Robillos', 'single', 'once', 49.00, 'PHP', 'maya', 'paid', 'e520591f-14c0-49db-9672-3e9638b274f3', 'PAYMENT_SUCCESS', NULL, '2026-08-26 04:50:39', NULL, '{\"id\":\"e520591f-14c0-49db-9672-3e9638b274f3\",\"isPaid\":true,\"status\":\"PAYMENT_SUCCESS\",\"amount\":\"49\",\"currency\":\"PHP\",\"canVoid\":true,\"canRefund\":false,\"canCapture\":false,\"createdAt\":\"2026-08-26T04:49:31.270Z\",\"updatedAt\":\"2026-08-26T04:50:05.225Z\",\"description\":\"Charge for robillosjuda+test4@gmail.com\",\"paymentTokenId\":\"jyrn9Liqh6JRUUaDXGOmrwwGxmdJ8lWfi5lozInBGKn2GDf5M7x4bEWH8TntV1BLCC9p1J3Wanwu2LRMC5TaUKiwFGmFKjZCEO4ceARvOMcZZHjEqwUFjHOIVMVDoptNXHMLYWJYk76uVvjF3eC30SkLDsyZez7V9QBrI\",\"fundSource\":{\"type\":\"card\",\"id\":\"jyrn9Liqh6JRUUaDXGOmrwwGxmdJ8lWfi5lozInBGKn2GDf5M7x4bEWH8TntV1BLCC9p1J3Wanwu2LRMC5TaUKiwFGmFKjZCEO4ceARvOMcZZHjEqwUFjHOIVMVDoptNXHMLYWJYk76uVvjF3eC30SkLDsyZez7V9QBrI\",\"description\":\"**** **** **** 2346\",\"details\":{\"scheme\":\"master-card\",\"last4\":\"2346\",\"first6\":\"512345\",\"masked\":\"512345******2346\",\"issuer\":\"Others\"}},\"receipt\":{\"transactionId\":\"788abe43-7530-4bc0-932e-93913cef43a1\",\"approvalCode\":\"00001234\",\"receiptNo\":\"9e45e2ffdc01\",\"approval_code\":\"00001234\"},\"approvalCode\":\"00001234\",\"receiptNumber\":\"9e45e2ffdc01\",\"requestReferenceNumber\":\"TGFM-2608-5835EDBA\"}', '2026-08-26 04:49:31', '2026-08-26 04:50:08', 't1/se1/v_8wj6gm'),
(13, 'TGFM-2608-B7DE1322', 10, 'robillosjuda+test5@gmail.com', 'Juda Robillos', 'week', 'week', 99.00, 'PHP', 'maya', 'paid', '076e9710-b98f-4773-91ec-b44a2e9733f5', 'PAYMENT_SUCCESS', NULL, '2026-08-26 04:58:29', '2026-09-02', '{\"id\":\"076e9710-b98f-4773-91ec-b44a2e9733f5\",\"isPaid\":true,\"status\":\"PAYMENT_SUCCESS\",\"amount\":\"99\",\"currency\":\"PHP\",\"canVoid\":true,\"canRefund\":false,\"canCapture\":false,\"createdAt\":\"2026-08-26T04:57:26.276Z\",\"updatedAt\":\"2026-08-26T04:58:03.584Z\",\"description\":\"Charge for robillosjuda+test5@gmail.com\",\"paymentTokenId\":\"1NCKWkfRZG7iQqQesXA1pyUqesm63p1qpyrSCUEv8bniTGIoqk28M5N6Q1CXAYtrX5Lw6lUSBmz44hGhC4I0lKDROUDgOYDeN0tqh0kMq7rdM5hBh0jl95YnDFzlr7ZF3FsheZt6JnkUtyFNJ5YMJKbTX0Xn5ZbnrytI\",\"fundSource\":{\"type\":\"card\",\"id\":\"1NCKWkfRZG7iQqQesXA1pyUqesm63p1qpyrSCUEv8bniTGIoqk28M5N6Q1CXAYtrX5Lw6lUSBmz44hGhC4I0lKDROUDgOYDeN0tqh0kMq7rdM5hBh0jl95YnDFzlr7ZF3FsheZt6JnkUtyFNJ5YMJKbTX0Xn5ZbnrytI\",\"description\":\"**** **** **** 2346\",\"details\":{\"scheme\":\"master-card\",\"last4\":\"2346\",\"first6\":\"512345\",\"masked\":\"512345******2346\",\"issuer\":\"Others\"}},\"receipt\":{\"transactionId\":\"ad15a456-91aa-471b-b0cc-530bc6289ba6\",\"approvalCode\":\"00001234\",\"receiptNo\":\"2c36d57f3efb\",\"approval_code\":\"00001234\"},\"approvalCode\":\"00001234\",\"receiptNumber\":\"2c36d57f3efb\",\"requestReferenceNumber\":\"TGFM-2608-B7DE1322\"}', '2026-08-26 04:57:26', '2026-08-26 04:58:07', NULL),
(14, 'TGFM-2608-53F77984', 11, 'transformglobalfm@gmail.com', 'Transform Global Faith Ministries', 'month', 'month', 299.00, 'PHP', 'maya', 'paid', '0b736639-71e2-47ff-955d-f966383c0a0a', 'PAYMENT_SUCCESS', NULL, '2026-08-26 05:06:00', '2026-09-26', '{\"id\":\"0b736639-71e2-47ff-955d-f966383c0a0a\",\"isPaid\":true,\"status\":\"PAYMENT_SUCCESS\",\"amount\":\"299\",\"currency\":\"PHP\",\"canVoid\":true,\"canRefund\":false,\"canCapture\":false,\"createdAt\":\"2026-08-26T05:05:07.956Z\",\"updatedAt\":\"2026-08-26T05:05:36.753Z\",\"description\":\"Charge for transformglobalfm@gmail.com\",\"paymentTokenId\":\"ci92aNegJ6apv7YsAPs4X0Fl6hYjHiSUEde0OKUVJ8OxEaQHbUnR97WqosinCA7Km9shl5mj2nk1hUoyiLIM4MUcGRa44NwSfxr3OjjmgKNp23LOJyRMrwh2mSu0xD3wpKIZuLrDqdqvPmpL2XTQ6rvmhkqzto8ML2EE\",\"fundSource\":{\"type\":\"card\",\"id\":\"ci92aNegJ6apv7YsAPs4X0Fl6hYjHiSUEde0OKUVJ8OxEaQHbUnR97WqosinCA7Km9shl5mj2nk1hUoyiLIM4MUcGRa44NwSfxr3OjjmgKNp23LOJyRMrwh2mSu0xD3wpKIZuLrDqdqvPmpL2XTQ6rvmhkqzto8ML2EE\",\"description\":\"**** **** **** 2346\",\"details\":{\"scheme\":\"master-card\",\"last4\":\"2346\",\"first6\":\"512345\",\"masked\":\"512345******2346\",\"issuer\":\"Others\"}},\"receipt\":{\"transactionId\":\"9fc07ed9-4096-4c93-98cd-2487ef22ccb1\",\"approvalCode\":\"00001234\",\"receiptNo\":\"c11f6ceb67af\",\"approval_code\":\"00001234\"},\"approvalCode\":\"00001234\",\"receiptNumber\":\"c11f6ceb67af\",\"requestReferenceNumber\":\"TGFM-2608-53F77984\"}', '2026-08-26 05:05:07', '2026-08-26 05:05:41', NULL),
(15, 'TGFM-2608-24E2F47B', NULL, 'robillosjuda+test6@gmail.com', 'Juda Robillos', 'single', 'once', 49.00, 'PHP', 'maya', 'paid', '5f0b4f27-b6b1-4c2e-a024-6e0135180a83', 'PAYMENT_SUCCESS', 'a5e3b96ce7c504be798e1ee81c43c9ad', NULL, NULL, '{\n  \"id\": \"5f0b4f27-b6b1-4c2e-a024-6e0135180a83\",\n  \"isPaid\": true,\n  \"status\": \"PAYMENT_SUCCESS\",\n  \"amount\": \"49\",\n  \"currency\": \"PHP\",\n  \"canVoid\": true,\n  \"canRefund\": false,\n  \"canCapture\": false,\n  \"createdAt\": \"2026-08-27T03:59:06.162Z\",\n  \"updatedAt\": \"2026-08-27T03:59:32.093Z\",\n  \"description\": \"Charge for robillosjuda+test6@gmail.com\",\n  \"paymentTokenId\": \"b1HRSNg0csiWxC1lzDvyPTQlKBjEyQE57c55drfhxeFGHlAOxa5vxtbjUimFzcsNRXeZhSX2XkofNzcF3FudTcLXXQoEaGmwUPch7Qw0Sq4YJ8zXflJZcVEvy1WZxKkWDfns4KBVlVp4cLAgOqaUtqISiYQHqkwmTOooTfo\",\n  \"fundSource\": {\n    \"type\": \"card\",\n    \"id\": \"b1HRSNg0csiWxC1lzDvyPTQlKBjEyQE57c55drfhxeFGHlAOxa5vxtbjUimFzcsNRXeZhSX2XkofNzcF3FudTcLXXQoEaGmwUPch7Qw0Sq4YJ8zXflJZcVEvy1WZxKkWDfns4KBVlVp4cLAgOqaUtqISiYQHqkwmTOooTfo\",\n    \"description\": \"**** **** **** 2346\",\n    \"details\": {\n      \"scheme\": \"master-card\",\n      \"last4\": \"2346\",\n      \"first6\": \"512345\",\n      \"masked\": \"512345******2346\",\n      \"issuer\": \"Others\"\n    }\n  },\n  \"receipt\": {\n    \"transactionId\": \"c66e6bb3-1c7a-4800-9cba-bf145330f8a1\",\n    \"approvalCode\": \"00001234\",\n    \"receiptNo\": \"7b724d6e177d\",\n    \"approval_code\": \"00001234\"\n  },\n  \"approvalCode\": \"00001234\",\n  \"receiptNumber\": \"7b724d6e177d\",\n  \"requestReferenceNumber\": \"TGFM-2608-24E2F47B\"\n}', '2026-08-27 03:59:06', '2026-08-27 03:59:32', 't1/se_7zwdmd/v_rgwo81'),
(16, 'TGFM-2608-D12E8F47', NULL, 'robillosjuda+test7@gmail.com', 'Juda Robillos', 'single', 'once', 49.00, 'PHP', 'maya', 'paid', '219b4138-c757-45ff-9d55-9109612971f0', 'PAYMENT_SUCCESS', '039cd7d63049be40d4a1f6e35169d9ba', NULL, NULL, '{\n  \"id\": \"219b4138-c757-45ff-9d55-9109612971f0\",\n  \"isPaid\": true,\n  \"status\": \"PAYMENT_SUCCESS\",\n  \"amount\": \"49\",\n  \"currency\": \"PHP\",\n  \"canVoid\": true,\n  \"canRefund\": false,\n  \"canCapture\": false,\n  \"createdAt\": \"2026-08-27T04:14:35.407Z\",\n  \"updatedAt\": \"2026-08-27T04:15:04.322Z\",\n  \"description\": \"Charge for robillosjuda+test7@gmail.com\",\n  \"paymentTokenId\": \"8tMlRPgGkOBCTuBUQ9gyfdR3UubU6JUiE9gXhtZPVSHJsYNZuu0xZ2j6oXuhwLdnLT0k1ecLhTFifDplVZeGp6KfNsKkvHfGbXhEzg7tTlZTRy893qnkuM15Z38XxM8moGo9BY3l29y0Af5uuiEvLwMpA3fdtDQ2LE\",\n  \"fundSource\": {\n    \"type\": \"card\",\n    \"id\": \"8tMlRPgGkOBCTuBUQ9gyfdR3UubU6JUiE9gXhtZPVSHJsYNZuu0xZ2j6oXuhwLdnLT0k1ecLhTFifDplVZeGp6KfNsKkvHfGbXhEzg7tTlZTRy893qnkuM15Z38XxM8moGo9BY3l29y0Af5uuiEvLwMpA3fdtDQ2LE\",\n    \"description\": \"**** **** **** 2346\",\n    \"details\": {\n      \"scheme\": \"master-card\",\n      \"last4\": \"2346\",\n      \"first6\": \"512345\",\n      \"masked\": \"512345******2346\",\n      \"issuer\": \"Others\"\n    }\n  },\n  \"receipt\": {\n    \"transactionId\": \"a80060ce-47d2-4b2b-8c5d-e488385e16f7\",\n    \"approvalCode\": \"00001234\",\n    \"receiptNo\": \"c4aed060201b\",\n    \"approval_code\": \"00001234\"\n  },\n  \"approvalCode\": \"00001234\",\n  \"receiptNumber\": \"c4aed060201b\",\n  \"requestReferenceNumber\": \"TGFM-2608-D12E8F47\"\n}', '2026-08-27 04:14:35', '2026-08-27 04:15:04', 't1/se1/v_vza66q'),
(17, 'TGFM-2608-086988A0', 13, 'robillosjuda+test8@gmail.com', 'Juda Robillos', 'week', 'week', 99.00, 'PHP', 'maya', 'paid', '70d94952-5273-460c-916d-03ba7da82330', 'PAYMENT_SUCCESS', NULL, '2026-08-27 04:42:21', '2026-09-03', '{\"id\":\"70d94952-5273-460c-916d-03ba7da82330\",\"isPaid\":true,\"status\":\"PAYMENT_SUCCESS\",\"amount\":\"99\",\"currency\":\"PHP\",\"canVoid\":true,\"canRefund\":false,\"canCapture\":false,\"createdAt\":\"2026-08-27T04:41:25.210Z\",\"updatedAt\":\"2026-08-27T04:41:57.255Z\",\"description\":\"Charge for robillosjuda+test8@gmail.com\",\"paymentTokenId\":\"VtE55x04fTHg8x05lyW1DML72xVGazRqW6V0GVTVGSyZhenkLv1qIPCw2ErmGZfomUImHmS9TVnlCyqgSxe85jugjLmxcAWToH1cHXAJaaLUVOE5bUUs13DIa6fNz2x14R3GF6azP5ylKukWEQQc6fbxojbLzDILLOks\",\"fundSource\":{\"type\":\"card\",\"id\":\"VtE55x04fTHg8x05lyW1DML72xVGazRqW6V0GVTVGSyZhenkLv1qIPCw2ErmGZfomUImHmS9TVnlCyqgSxe85jugjLmxcAWToH1cHXAJaaLUVOE5bUUs13DIa6fNz2x14R3GF6azP5ylKukWEQQc6fbxojbLzDILLOks\",\"description\":\"**** **** **** 2346\",\"details\":{\"scheme\":\"master-card\",\"last4\":\"2346\",\"first6\":\"512345\",\"masked\":\"512345******2346\",\"issuer\":\"Others\"}},\"receipt\":{\"transactionId\":\"ec08401b-90af-4e4d-9b7d-cc2acc8f7e71\",\"approvalCode\":\"00001234\",\"receiptNo\":\"c52653f626f9\",\"approval_code\":\"00001234\"},\"approvalCode\":\"00001234\",\"receiptNumber\":\"c52653f626f9\",\"requestReferenceNumber\":\"TGFM-2608-086988A0\"}', '2026-08-27 04:41:25', '2026-08-27 04:42:00', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(120) NOT NULL,
  `email` varchar(190) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('disciple','admin') NOT NULL DEFAULT 'disciple',
  `plan` varchar(20) NOT NULL,
  `access_until` date DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `password_hash`, `role`, `plan`, `access_until`, `created_at`) VALUES
(1, 'TGFM Admin', 'admin@tgfm.org', '$2y$10$xXi8gM0dpTTBCg6y9HhQXOWy94rDVDkAHgGI/rllxXQUaj/KxeKUy', 'admin', 'year', '2099-12-31', '2026-08-25 14:02:53'),
(4, 'Juda Robillos', 'judarobillosdev+test@gmail.com', '$2y$10$6LA/KZN.i6F7ocWW4KILP.hCs39W966hZwe/CHlfjCG9PI6aKMqJ2', 'disciple', 'month', '2026-09-25', '2026-08-25 18:39:34'),
(5, 'Juda Robillos', 'judarobillosdev@gmail.com', '$2y$10$Z9q5Q1oOYvgc2PVVO8tEv.V6JCN0B5hUdHN3OBWMMayCrotJA/iAy', 'disciple', 'week', '2026-09-01', '2026-08-25 22:50:51'),
(6, 'Juda Robillos', 'robillosjuda+test@gmail.com', '$2y$10$FUssjOm9fNhdiVZ.DdPoGuNlob1W8pNEeuVfbaxA0CkKLJF8rPkQi', 'disciple', 'week', '2026-09-01', '2026-08-25 23:20:29'),
(7, 'Juda Robillos', 'robillosjuda+test2@gmail.com', '$2y$10$5eAeRKMg3y5.iVQlLtgsBuNE7CIayCJbSFb3hyZaEO5lp0IT8bpU2', 'disciple', 'week', '2026-09-01', '2026-08-25 23:26:58'),
(9, 'Juda Robillos', 'robillosjuda+test4@gmail.com', '$2y$10$IiseU2QEwTlVQ3Q6XZXD0.ab4xgxTHu6Ax6R4/1Lw/Un0b3vRAVXC', 'disciple', 'single', NULL, '2026-08-26 04:50:39'),
(10, 'Juda Robillos', 'robillosjuda+test5@gmail.com', '$2y$10$Vs69Uy73cgFCHD63B4p1YeHz2PHE4SVcmcj6twqJyz9EN6PGIlFkm', 'disciple', 'week', '2026-09-02', '2026-08-26 04:58:29'),
(11, 'Transform Global Faith Ministries', 'transformglobalfm@gmail.com', '$2y$10$FwRf1FtqaEz47sCbfzAm9ei5W30GS0OxJgV1MkQOnHFE.G4fdCNCC', 'disciple', 'month', '2026-09-26', '2026-08-26 05:06:00'),
(13, 'Juda Robillos', 'robillosjuda+test8@gmail.com', '$2y$10$dVKTE4gQhJpHb0bjA70Y1utX3MwJjBZFnhhS9m9tZOtI8tG2Y2x7e', 'disciple', 'week', '2026-09-03', '2026-08-27 04:42:21');

-- --------------------------------------------------------

--
-- Table structure for table `webhook_log`
--

CREATE TABLE `webhook_log` (
  `id` int(10) UNSIGNED NOT NULL,
  `source` enum('maya','paypal') NOT NULL,
  `event` varchar(80) DEFAULT NULL,
  `reference` varchar(36) DEFAULT NULL,
  `verified` tinyint(1) NOT NULL DEFAULT 0,
  `body` mediumtext DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `webhook_log`
--

INSERT INTO `webhook_log` (`id`, `source`, `event`, `reference`, `verified`, `body`, `created_at`) VALUES
(1, 'maya', 'PAYMENT_SUCCESS', 'OF260000087227', 0, '{\"id\":\"7ab74cfc-62a3-42ce-afff-bb04b1e114cb\",\"isPaid\":true,\"status\":\"PAYMENT_SUCCESS\",\"amount\":\"2702\",\"currency\":\"PHP\",\"canVoid\":true,\"canRefund\":true,\"canCapture\":false,\"createdAt\":\"2026-08-26T15:01:03.848Z\",\"updatedAt\":\"2026-08-26T15:02:44.822Z\",\"description\":\"Charge for maya.developers@maya.ph\",\"paymentTokenId\":\"hMaNwojHWz5b28lpB8css1OHfua2FFGecnDOa8KFWglNYdHRKSQJakmkBGgcbytnJyvUEOyalbnQBIE3QUGcyXyV41S4FIRk47eCsN5q5JH1Enb6ksdIwopPbuDOKNNs1m4Oo26PMmIazT239QctGMYhNW4HAwzrDy0\",\"fundSource\":{\"type\":\"paymaya\",\"id\":\"+6399*****900\",\"description\":\"***** ***0900\",\"details\":{\"firstName\":\"Maya\",\"middleName\":\"Dummy\",\"lastName\":\"Devel*****\",\"msisdn\":\"+6399*****900\",\"email\":\"m************rs@maya.ph\",\"masked\":\"********0900\"}},\"receipt\":{\"transactionId\":\"793413b1-4139-4e51-b73c-329e14988c83\",\"approvalCode\":\"00001234\",\"receiptNo\":\"4ac48ecbde16\",\"approval_code\":\"00001234\"},\"metadata\":{\"source\":\"WES4\",\"payType\":\"PR\",\"payChanCode\":\"MO\",\"mainSPCode\":\"PMI\",\"prnnum\":\"OF260000087227\",\"sssNumber\":\"\",\"fullname\":\"MARY ROSE CORPUZ PASAG \",\"transNo\":\"PMM2301OF260000087227082626XID\",\"applicablePeriod\":\"012026 - 012026\",\"flexiFundAmount\":0,\"ecAmount\":0,\"totalMonthCost\":2686,\"monthlyAmount\":2686,\"totalCost\":2702,\"convenienceFee\":10,\"serviceFee\":6},\"approvalCode\":\"00001234\",\"receiptNumber\":\"4ac48ecbde16\",\"requestReferenceNumber\":\"OF260000087227\"}', '2026-08-26 15:02:45'),
(2, 'maya', 'PAYMENT_EXPIRED', 'SP-260826-157175C3-449-MTA6EGIR', 0, '{\"id\":\"1869ddbf-24d9-4c38-87ab-69abe6014b4e\",\"isPaid\":false,\"status\":\"PAYMENT_EXPIRED\",\"amount\":\"96.67\",\"currency\":\"PHP\",\"canVoid\":false,\"canRefund\":false,\"canCapture\":false,\"createdAt\":\"2026-08-26T14:15:11.840Z\",\"updatedAt\":\"2026-08-26T15:15:14.861Z\",\"description\":\"Charge for marisol.santiago@xdnainteractive.com\",\"paymentTokenId\":\"JLQaWEwle3PkEASmbbCFg8HtUtU1IfgPuQfjrZwGJ9DmyBMKuTNHCRk8W5C81mcKZFtpYHGtlgQJEqr3jObF6DN5yuYOA2tyZ3ebaJg8UbKCEHlXGoteXT4s0fGiBMIbWSE3GJTXg1aWPYkeyau3dryCUdIPTeOY1xyBU0d8\",\"fundSource\":{\"type\":\"card\",\"id\":\"JLQaWEwle3PkEASmbbCFg8HtUtU1IfgPuQfjrZwGJ9DmyBMKuTNHCRk8W5C81mcKZFtpYHGtlgQJEqr3jObF6DN5yuYOA2tyZ3ebaJg8UbKCEHlXGoteXT4s0fGiBMIbWSE3GJTXg1aWPYkeyau3dryCUdIPTeOY1xyBU0d8\",\"description\":\"**** **** **** 1112\",\"source\":\"googlepay\",\"details\":{\"scheme\":null,\"last4\":\"1112\",\"first6\":\"401200\",\"masked\":\"401200******1112\",\"issuer\":\"Others\"}},\"metadata\":{\"bookingId\":349,\"paymentId\":449,\"source\":\"SurePark\",\"bookingReferenceCode\":\"SP-260826-157175C3\"},\"requestReferenceNumber\":\"SP-260826-157175C3-449-MTA6EGIR\"}', '2026-08-26 15:15:15'),
(3, 'maya', 'PAYMENT_EXPIRED', 'SP-260826-7F2231ED-450-MTA6FJGV', 0, '{\"id\":\"7500cfe2-2371-4533-a180-ead4e44d4cf2\",\"isPaid\":false,\"status\":\"PAYMENT_EXPIRED\",\"amount\":\"93.33\",\"currency\":\"PHP\",\"canVoid\":false,\"canRefund\":false,\"canCapture\":false,\"createdAt\":\"2026-08-26T14:16:02.351Z\",\"updatedAt\":\"2026-08-26T15:16:14.854Z\",\"description\":\"Charge for marisol.santiago@xdnainteractive.com\",\"metadata\":{\"bookingId\":356,\"paymentId\":450,\"source\":\"SurePark\",\"bookingReferenceCode\":\"SP-260826-7F2231ED\"},\"requestReferenceNumber\":\"SP-260826-7F2231ED-450-MTA6FJGV\"}', '2026-08-26 15:16:15'),
(4, 'maya', 'PAYMENT_EXPIRED', 'probe-17491', 0, '{\"id\":\"f29b43a5-aca3-4788-955c-cfaaed8738f6\",\"isPaid\":false,\"status\":\"PAYMENT_EXPIRED\",\"amount\":\"100\",\"currency\":\"PHP\",\"canVoid\":false,\"canRefund\":false,\"canCapture\":false,\"createdAt\":\"2026-08-26T14:23:05.912Z\",\"updatedAt\":\"2026-08-26T15:23:15.017Z\",\"requestReferenceNumber\":\"probe-17491\"}', '2026-08-26 15:23:15'),
(5, 'maya', 'PAYMENT_EXPIRED', 'QA26-2YBZZ3-MTA6P173', 0, '{\"id\":\"12d79599-a52a-44cc-a2e8-2c125045a934\",\"isPaid\":false,\"status\":\"PAYMENT_EXPIRED\",\"amount\":\"1300\",\"currency\":\"PHP\",\"canVoid\":false,\"canRefund\":false,\"canCapture\":false,\"createdAt\":\"2026-08-26T14:23:28.818Z\",\"updatedAt\":\"2026-08-26T15:23:29.848Z\",\"description\":\"Charge for smoke-mta6ozcf@example.invalid\",\"metadata\":{\"reference\":\"QA26-2YBZZ3\",\"level\":\"D\"},\"requestReferenceNumber\":\"QA26-2YBZZ3-MTA6P173\"}', '2026-08-26 15:23:30'),
(6, 'maya', 'PAYMENT_EXPIRED', 'QA26-FPNAHX-MTA6T647', 0, '{\"id\":\"51c9bbad-8594-4121-961b-0bd9d8559973\",\"isPaid\":false,\"status\":\"PAYMENT_EXPIRED\",\"amount\":\"1300\",\"currency\":\"PHP\",\"canVoid\":false,\"canRefund\":false,\"canCapture\":false,\"createdAt\":\"2026-08-26T14:26:41.863Z\",\"updatedAt\":\"2026-08-26T15:26:45.024Z\",\"description\":\"Charge for smoke-mta6t3t8@example.invalid\",\"metadata\":{\"reference\":\"QA26-FPNAHX\",\"level\":\"D\"},\"requestReferenceNumber\":\"QA26-FPNAHX-MTA6T647\"}', '2026-08-26 15:26:45'),
(7, 'maya', 'PAYMENT_EXPIRED', 'QA26-32GT2F-MTA7NU43', 0, '{\"id\":\"39b13f1b-9a27-45e7-ba63-35f614d2d7e4\",\"isPaid\":false,\"status\":\"PAYMENT_EXPIRED\",\"amount\":\"1300\",\"currency\":\"PHP\",\"canVoid\":false,\"canRefund\":false,\"canCapture\":false,\"createdAt\":\"2026-08-26T14:50:32.545Z\",\"updatedAt\":\"2026-08-26T15:50:44.877Z\",\"description\":\"Charge for smoke-mta7nr4r@example.invalid\",\"metadata\":{\"reference\":\"QA26-32GT2F\",\"level\":\"D\"},\"requestReferenceNumber\":\"QA26-32GT2F-MTA7NU43\"}', '2026-08-26 15:50:45'),
(8, 'maya', 'PAYMENT_SUCCESS', 'ORD-IBETSHAC', 0, '{\"id\":\"96d8ede8-80db-463e-88d3-0377f9c0d425\",\"isPaid\":true,\"status\":\"PAYMENT_SUCCESS\",\"amount\":\"615\",\"currency\":\"PHP\",\"canVoid\":true,\"canRefund\":true,\"canCapture\":false,\"createdAt\":\"2026-08-26T22:20:53.688Z\",\"updatedAt\":\"2026-08-26T22:21:11.771Z\",\"description\":\"Charge for maya.developers@maya.ph\",\"paymentTokenId\":\"9gPjFzjUnGB2uyP9Q4Xkqa0zYBWXWopwDmJgzX3sGNly4M993OT9Uuy347fsA7QUTP1LlrnfD29ctoh1Ghm4CZlXFMDeN4zCbQLoWsufr1fKB1wUhVUo8eQkVMc9zHQ9m5QDLxP3CicLYBTl3BNP7AxxKMj01lCeVLfOKc3WQ\",\"fundSource\":{\"type\":\"paymaya\",\"id\":\"+6399*****900\",\"description\":\"***** ***0900\",\"details\":{\"firstName\":\"Maya\",\"middleName\":\"Dummy\",\"lastName\":\"Devel*****\",\"msisdn\":\"+6399*****900\",\"email\":\"m************rs@maya.ph\",\"masked\":\"********0900\"}},\"receipt\":{\"transactionId\":\"1df80f2b-5751-4e3f-bdef-ef3be1fad4ec\",\"approvalCode\":\"00001234\",\"receiptNo\":\"209a217e4908\",\"approval_code\":\"00001234\"},\"metadata\":{\"tenant_id\":\"3\",\"online_order_id\":\"9\",\"order_number\":\"ORD-IBETSHAC\"},\"approvalCode\":\"00001234\",\"receiptNumber\":\"209a217e4908\",\"requestReferenceNumber\":\"ORD-IBETSHAC\"}', '2026-08-26 22:21:12'),
(9, 'maya', 'PAYMENT_SUCCESS', 'ORD-9XGCPXAJ', 0, '{\"id\":\"3cedc25f-2675-4028-af10-a76575372b79\",\"isPaid\":true,\"status\":\"PAYMENT_SUCCESS\",\"amount\":\"509.99\",\"currency\":\"PHP\",\"canVoid\":true,\"canRefund\":true,\"canCapture\":false,\"createdAt\":\"2026-08-26T22:25:49.892Z\",\"updatedAt\":\"2026-08-26T22:26:02.931Z\",\"description\":\"Charge for maya.developers@maya.ph\",\"paymentTokenId\":\"8bqr4rKcCZLsy3ZTTNrGz6ORdxoA3HHlKErUt1QkVm5NdaqWHjEobBDBhy7txXZpf06TGvDeX8yktNK2FC4AvtVSsXBLmXaDJfe7pkejeBNfBGij3lWo36cNHGTYdkJpGvZqxWZkhnsHbbHBHhuGe5KYfSJ6aKhuI2rc\",\"fundSource\":{\"type\":\"paymaya\",\"id\":\"+6399*****900\",\"description\":\"***** ***0900\",\"details\":{\"firstName\":\"Maya\",\"middleName\":\"Dummy\",\"lastName\":\"Devel*****\",\"msisdn\":\"+6399*****900\",\"email\":\"m************rs@maya.ph\",\"masked\":\"********0900\"}},\"receipt\":{\"transactionId\":\"7d419546-42e9-4e37-bef0-422e1a4a91ad\",\"approvalCode\":\"00001234\",\"receiptNo\":\"4dbc67fc5330\",\"approval_code\":\"00001234\"},\"metadata\":{\"tenant_id\":\"3\",\"online_order_id\":\"10\",\"order_number\":\"ORD-9XGCPXAJ\"},\"approvalCode\":\"00001234\",\"receiptNumber\":\"4dbc67fc5330\",\"requestReferenceNumber\":\"ORD-9XGCPXAJ\"}', '2026-08-26 22:26:03'),
(10, 'maya', 'PAYMENT_SUCCESS', 'test123', 0, '{\"id\":\"e127a8ef-668d-4c11-a991-daf1aff2aff2\",\"isPaid\":true,\"status\":\"PAYMENT_SUCCESS\",\"amount\":\"3\",\"currency\":\"PHP\",\"canVoid\":true,\"canRefund\":false,\"canCapture\":false,\"createdAt\":\"2026-08-27T01:10:15.996Z\",\"updatedAt\":\"2026-08-27T01:10:43.945Z\",\"description\":\"Charge for jason jarabejo\",\"paymentTokenId\":\"LwVwyQ7jfAOB9NJ4iC22Vi3uCyiWSwMCsxCm9M9y67QNvT4qjkWV4jMTc3a6uCJm3TfRJ4WDeTGalOVHMMKQKbOlfLcuHZA0tfl4f9KumjwSWroLTJ7QxzALWz9BbvqHU5FHtB3691xAt4GBqd4ZDiLetAap74sYnyPz6sc\",\"fundSource\":{\"type\":\"card\",\"id\":\"LwVwyQ7jfAOB9NJ4iC22Vi3uCyiWSwMCsxCm9M9y67QNvT4qjkWV4jMTc3a6uCJm3TfRJ4WDeTGalOVHMMKQKbOlfLcuHZA0tfl4f9KumjwSWroLTJ7QxzALWz9BbvqHU5FHtB3691xAt4GBqd4ZDiLetAap74sYnyPz6sc\",\"description\":\"**** **** **** 2346\",\"details\":{\"scheme\":\"master-card\",\"last4\":\"2346\",\"first6\":\"512345\",\"masked\":\"512345******2346\",\"issuer\":\"Others\"}},\"receipt\":{\"transactionId\":\"ad938160-3ce7-4c26-9d9b-9ca86caf0863\",\"approvalCode\":\"00001234\",\"receiptNo\":\"17a0b12767a1\",\"approval_code\":\"00001234\"},\"approvalCode\":\"00001234\",\"receiptNumber\":\"17a0b12767a1\",\"requestReferenceNumber\":\"test123\"}', '2026-08-27 01:10:44'),
(11, 'maya', 'PAYMENT_CANCELLED', 'sxw6xky8upxjvzf3a3ku', 0, '{\"id\":\"1bd3990d-c9e1-45fe-84ae-c419cb7e55b7\",\"isPaid\":false,\"status\":\"PAYMENT_CANCELLED\",\"amount\":\"4770\",\"currency\":\"PHP\",\"canVoid\":false,\"canRefund\":false,\"canCapture\":false,\"createdAt\":\"2026-08-27T01:11:41.922Z\",\"updatedAt\":\"2026-08-27T01:12:37.964Z\",\"requestReferenceNumber\":\"sxw6xky8upxjvzf3a3ku\"}', '2026-08-27 01:12:38'),
(12, 'maya', 'PAYMENT_EXPIRED', '0gjqzfli9ues8w3ko3dp', 0, '{\"id\":\"962e5e24-a286-4d4a-ba43-b0ca8ec2b53a\",\"isPaid\":false,\"status\":\"PAYMENT_EXPIRED\",\"amount\":\"10.6\",\"currency\":\"PHP\",\"canVoid\":false,\"canRefund\":false,\"canCapture\":false,\"createdAt\":\"2026-08-27T01:18:38.963Z\",\"updatedAt\":\"2026-08-27T02:18:44.859Z\",\"requestReferenceNumber\":\"0gjqzfli9ues8w3ko3dp\"}', '2026-08-27 02:18:45'),
(13, 'maya', 'PAYMENT_CANCELLED', 'FX2608270243051163992c', 0, '{\"id\":\"ad4c0f72-b412-40b8-a85b-1e1a085b05f5\",\"isPaid\":false,\"status\":\"PAYMENT_CANCELLED\",\"amount\":\"492\",\"currency\":\"PHP\",\"canVoid\":false,\"canRefund\":false,\"canCapture\":false,\"createdAt\":\"2026-08-27T02:43:53.749Z\",\"updatedAt\":\"2026-08-27T02:43:57.862Z\",\"requestReferenceNumber\":\"FX2608270243051163992c\"}', '2026-08-27 02:43:58'),
(14, 'maya', 'PAYMENT_CANCELLED', 'FX26082702525953c91c39', 0, '{\"id\":\"9040b452-6193-44be-aa5e-80e3b770cf5b\",\"isPaid\":false,\"status\":\"PAYMENT_CANCELLED\",\"amount\":\"492\",\"currency\":\"PHP\",\"canVoid\":false,\"canRefund\":false,\"canCapture\":false,\"createdAt\":\"2026-08-27T02:53:47.033Z\",\"updatedAt\":\"2026-08-27T03:07:51.654Z\",\"requestReferenceNumber\":\"FX26082702525953c91c39\"}', '2026-08-27 03:07:51'),
(15, 'maya', 'PAYMENT_CANCELLED', 'FX26082703095799250969', 0, '{\"id\":\"316dc0e8-5e70-4c98-9adf-96eba213692f\",\"isPaid\":false,\"status\":\"PAYMENT_CANCELLED\",\"amount\":\"492\",\"currency\":\"PHP\",\"canVoid\":false,\"canRefund\":false,\"canCapture\":false,\"createdAt\":\"2026-08-27T03:10:45.140Z\",\"updatedAt\":\"2026-08-27T03:10:48.310Z\",\"requestReferenceNumber\":\"FX26082703095799250969\"}', '2026-08-27 03:10:48'),
(16, 'maya', 'PAYMENT_SUCCESS', '2da60872-b778-46f0-bfbe-a09917cebb55', 0, '{\"id\":\"d8eff87f-429d-4390-9988-cfbfbdc5865d\",\"isPaid\":true,\"status\":\"PAYMENT_SUCCESS\",\"amount\":\"20\",\"currency\":\"PHP\",\"canVoid\":true,\"canRefund\":false,\"canCapture\":false,\"createdAt\":\"2026-08-27T03:12:18.985Z\",\"updatedAt\":\"2026-08-27T03:12:33.774Z\",\"description\":\"Charge for f f\",\"paymentTokenId\":\"LD65unvs4XXEjBIXXWsIIlPWjazw2rlJL3CkhSfurn6tW7HnI98Mpe3RaA1RXbVVd9DRphZbkLsaEG3vo5gII5xSNLbg16rkgPTPpKTnnxr2aU1fOKLeFcusGSw7wALNJ48vx1f2K90gwRXYR4rVDxmTvnTxxqQfqDQ\",\"fundSource\":{\"type\":\"card\",\"id\":\"LD65unvs4XXEjBIXXWsIIlPWjazw2rlJL3CkhSfurn6tW7HnI98Mpe3RaA1RXbVVd9DRphZbkLsaEG3vo5gII5xSNLbg16rkgPTPpKTnnxr2aU1fOKLeFcusGSw7wALNJ48vx1f2K90gwRXYR4rVDxmTvnTxxqQfqDQ\",\"description\":\"**** **** **** 2346\",\"details\":{\"scheme\":\"master-card\",\"last4\":\"2346\",\"first6\":\"512345\",\"masked\":\"512345******2346\",\"issuer\":\"Others\"}},\"receipt\":{\"transactionId\":\"dc195b57-1342-45f7-b4a9-14dd109c386d\",\"approvalCode\":\"00001234\",\"receiptNo\":\"dffa1ace1b23\",\"approval_code\":\"00001234\"},\"approvalCode\":\"00001234\",\"receiptNumber\":\"dffa1ace1b23\",\"requestReferenceNumber\":\"2da60872-b778-46f0-bfbe-a09917cebb55\"}', '2026-08-27 03:12:34'),
(17, 'maya', 'PAYMENT_CANCELLED', 'FX260827031143254386d9', 0, '{\"id\":\"fe4ac3b0-1597-481e-a4ce-f2263b37a1ed\",\"isPaid\":false,\"status\":\"PAYMENT_CANCELLED\",\"amount\":\"492\",\"currency\":\"PHP\",\"canVoid\":false,\"canRefund\":false,\"canCapture\":false,\"createdAt\":\"2026-08-27T03:12:31.000Z\",\"updatedAt\":\"2026-08-27T03:12:34.459Z\",\"requestReferenceNumber\":\"FX260827031143254386d9\"}', '2026-08-27 03:12:34'),
(18, 'maya', 'PAYMENT_SUCCESS', 'TGFM-2608-24E2F47B', 1, '{\"id\":\"5f0b4f27-b6b1-4c2e-a024-6e0135180a83\",\"isPaid\":true,\"status\":\"PAYMENT_SUCCESS\",\"amount\":\"49\",\"currency\":\"PHP\",\"canVoid\":true,\"canRefund\":false,\"canCapture\":false,\"createdAt\":\"2026-08-27T03:59:06.162Z\",\"updatedAt\":\"2026-08-27T03:59:32.093Z\",\"description\":\"Charge for robillosjuda+test6@gmail.com\",\"paymentTokenId\":\"b1HRSNg0csiWxC1lzDvyPTQlKBjEyQE57c55drfhxeFGHlAOxa5vxtbjUimFzcsNRXeZhSX2XkofNzcF3FudTcLXXQoEaGmwUPch7Qw0Sq4YJ8zXflJZcVEvy1WZxKkWDfns4KBVlVp4cLAgOqaUtqISiYQHqkwmTOooTfo\",\"fundSource\":{\"type\":\"card\",\"id\":\"b1HRSNg0csiWxC1lzDvyPTQlKBjEyQE57c55drfhxeFGHlAOxa5vxtbjUimFzcsNRXeZhSX2XkofNzcF3FudTcLXXQoEaGmwUPch7Qw0Sq4YJ8zXflJZcVEvy1WZxKkWDfns4KBVlVp4cLAgOqaUtqISiYQHqkwmTOooTfo\",\"description\":\"**** **** **** 2346\",\"details\":{\"scheme\":\"master-card\",\"last4\":\"2346\",\"first6\":\"512345\",\"masked\":\"512345******2346\",\"issuer\":\"Others\"}},\"receipt\":{\"transactionId\":\"c66e6bb3-1c7a-4800-9cba-bf145330f8a1\",\"approvalCode\":\"00001234\",\"receiptNo\":\"7b724d6e177d\",\"approval_code\":\"00001234\"},\"approvalCode\":\"00001234\",\"receiptNumber\":\"7b724d6e177d\",\"requestReferenceNumber\":\"TGFM-2608-24E2F47B\"}', '2026-08-27 03:59:32'),
(19, 'maya', 'PAYMENT_EXPIRED', 'FX2608270307074d89da2f', 0, '{\"id\":\"0028f792-b609-4834-842e-1a70cf558e96\",\"isPaid\":false,\"status\":\"PAYMENT_EXPIRED\",\"amount\":\"492\",\"currency\":\"PHP\",\"canVoid\":false,\"canRefund\":false,\"canCapture\":false,\"createdAt\":\"2026-08-27T03:07:55.277Z\",\"updatedAt\":\"2026-08-27T04:07:59.860Z\",\"requestReferenceNumber\":\"FX2608270307074d89da2f\"}', '2026-08-27 04:08:00'),
(20, 'maya', 'PAYMENT_EXPIRED', 'FX260827031142f650b69a', 0, '{\"id\":\"f1dcf325-cc01-4329-9d33-e55f7955da6e\",\"isPaid\":false,\"status\":\"PAYMENT_EXPIRED\",\"amount\":\"492\",\"currency\":\"PHP\",\"canVoid\":false,\"canRefund\":false,\"canCapture\":false,\"createdAt\":\"2026-08-27T03:12:30.556Z\",\"updatedAt\":\"2026-08-27T04:12:44.855Z\",\"requestReferenceNumber\":\"FX260827031142f650b69a\"}', '2026-08-27 04:12:45'),
(21, 'maya', 'PAYMENT_SUCCESS', 'TGFM-2608-D12E8F47', 1, '{\"id\":\"219b4138-c757-45ff-9d55-9109612971f0\",\"isPaid\":true,\"status\":\"PAYMENT_SUCCESS\",\"amount\":\"49\",\"currency\":\"PHP\",\"canVoid\":true,\"canRefund\":false,\"canCapture\":false,\"createdAt\":\"2026-08-27T04:14:35.407Z\",\"updatedAt\":\"2026-08-27T04:15:04.322Z\",\"description\":\"Charge for robillosjuda+test7@gmail.com\",\"paymentTokenId\":\"8tMlRPgGkOBCTuBUQ9gyfdR3UubU6JUiE9gXhtZPVSHJsYNZuu0xZ2j6oXuhwLdnLT0k1ecLhTFifDplVZeGp6KfNsKkvHfGbXhEzg7tTlZTRy893qnkuM15Z38XxM8moGo9BY3l29y0Af5uuiEvLwMpA3fdtDQ2LE\",\"fundSource\":{\"type\":\"card\",\"id\":\"8tMlRPgGkOBCTuBUQ9gyfdR3UubU6JUiE9gXhtZPVSHJsYNZuu0xZ2j6oXuhwLdnLT0k1ecLhTFifDplVZeGp6KfNsKkvHfGbXhEzg7tTlZTRy893qnkuM15Z38XxM8moGo9BY3l29y0Af5uuiEvLwMpA3fdtDQ2LE\",\"description\":\"**** **** **** 2346\",\"details\":{\"scheme\":\"master-card\",\"last4\":\"2346\",\"first6\":\"512345\",\"masked\":\"512345******2346\",\"issuer\":\"Others\"}},\"receipt\":{\"transactionId\":\"a80060ce-47d2-4b2b-8c5d-e488385e16f7\",\"approvalCode\":\"00001234\",\"receiptNo\":\"c4aed060201b\",\"approval_code\":\"00001234\"},\"approvalCode\":\"00001234\",\"receiptNumber\":\"c4aed060201b\",\"requestReferenceNumber\":\"TGFM-2608-D12E8F47\"}', '2026-08-27 04:15:04'),
(22, 'maya', 'PAYMENT_SUCCESS', 'ORD-F8BVODUN', 0, '{\"id\":\"a4fc86e3-433f-4ece-9567-7678a75e4002\",\"isPaid\":true,\"status\":\"PAYMENT_SUCCESS\",\"amount\":\"409.98\",\"currency\":\"PHP\",\"canVoid\":true,\"canRefund\":true,\"canCapture\":false,\"createdAt\":\"2026-08-27T04:23:23.194Z\",\"updatedAt\":\"2026-08-27T04:23:47.875Z\",\"description\":\"Charge for maya.developers@maya.ph\",\"paymentTokenId\":\"iwieI0uCkLhDOoHxI4usVTm6TLKOiTNf1CxFCfiE1Qv4DwRB5hdnQSSHHmWlfZ3fJnWZiZ0PGmWZOIFBm2Gzg7KIHL6e91nGEtaQX2lNWSrCTtpopc604GYSRxrCCAALrgCgpwoR5VGLeezMi5rDeYOyLUEHfNaVGz97l5E\",\"fundSource\":{\"type\":\"paymaya\",\"id\":\"+6399*****900\",\"description\":\"***** ***0900\",\"details\":{\"firstName\":\"Maya\",\"middleName\":\"Dummy\",\"lastName\":\"Devel*****\",\"msisdn\":\"+6399*****900\",\"email\":\"m************rs@maya.ph\",\"masked\":\"********0900\"}},\"receipt\":{\"transactionId\":\"4b5ab084-83f8-47e6-b684-adae209c3f86\",\"approvalCode\":\"00001234\",\"receiptNo\":\"cf6e4f1ea9d3\",\"approval_code\":\"00001234\"},\"metadata\":{\"tenant_id\":\"3\",\"online_order_id\":\"11\",\"order_number\":\"ORD-F8BVODUN\"},\"approvalCode\":\"00001234\",\"receiptNumber\":\"cf6e4f1ea9d3\",\"requestReferenceNumber\":\"ORD-F8BVODUN\"}', '2026-08-27 04:23:48'),
(23, 'maya', '', '', 0, '', '2026-08-27 04:40:45'),
(24, 'maya', '', '', 0, '', '2026-08-27 04:41:57'),
(25, 'maya', 'PAYMENT_EXPIRED', 'PAY_20260827114836_JOLTYHABDDOIDEKL', 0, '{\"id\":\"097331d5-fd89-41ce-97db-96e833256567\",\"isPaid\":false,\"status\":\"PAYMENT_EXPIRED\",\"amount\":\"2400\",\"currency\":\"PHP\",\"canVoid\":false,\"canRefund\":false,\"canCapture\":false,\"createdAt\":\"2026-08-27T03:48:49.930Z\",\"updatedAt\":\"2026-08-27T04:48:59.863Z\",\"description\":\"Charge for gkuyamark@gmail.com\",\"requestReferenceNumber\":\"PAY_20260827114836_JOLTYHABDDOIDEKL\"}', '2026-08-27 04:49:00'),
(26, 'maya', 'PAYMENT_EXPIRED', 'QA26-K3T664-MTAZXGJ4', 0, '{\"id\":\"f0cecd6e-9f4d-4288-a7cb-eaac155c87da\",\"isPaid\":false,\"status\":\"PAYMENT_EXPIRED\",\"amount\":\"1300\",\"currency\":\"PHP\",\"canVoid\":false,\"canRefund\":false,\"canCapture\":false,\"createdAt\":\"2026-08-27T04:01:47.266Z\",\"updatedAt\":\"2026-08-27T05:01:59.864Z\",\"description\":\"Charge for smoke-mtazxa6d@example.invalid\",\"metadata\":{\"reference\":\"QA26-K3T664\",\"level\":\"D\"},\"requestReferenceNumber\":\"QA26-K3T664-MTAZXGJ4\"}', '2026-08-27 05:02:00'),
(27, 'maya', 'PAYMENT_EXPIRED', 'QA26-NWGDRP-MTAZY0XD', 0, '{\"id\":\"f2ee8bd7-00a0-43c7-a33e-aa1760d10a58\",\"isPaid\":false,\"status\":\"PAYMENT_EXPIRED\",\"amount\":\"1300\",\"currency\":\"PHP\",\"canVoid\":false,\"canRefund\":false,\"canCapture\":false,\"createdAt\":\"2026-08-27T04:02:13.785Z\",\"updatedAt\":\"2026-08-27T05:02:15.027Z\",\"description\":\"Charge for smoke-mtazxybe@example.invalid\",\"metadata\":{\"reference\":\"QA26-NWGDRP\",\"level\":\"D\"},\"requestReferenceNumber\":\"QA26-NWGDRP-MTAZY0XD\"}', '2026-08-27 05:02:15'),
(28, 'maya', 'PAYMENT_EXPIRED', 'QA26-RZE2Y5-MTAZY86Z', 0, '{\"id\":\"0f60a868-5718-4b94-b6fb-43a857b891a4\",\"isPaid\":false,\"status\":\"PAYMENT_EXPIRED\",\"amount\":\"1300\",\"currency\":\"PHP\",\"canVoid\":false,\"canRefund\":false,\"canCapture\":false,\"createdAt\":\"2026-08-27T04:02:23.127Z\",\"updatedAt\":\"2026-08-27T05:02:29.848Z\",\"description\":\"Charge for smoke-mtazy5vv@example.invalid\",\"metadata\":{\"reference\":\"QA26-RZE2Y5\",\"level\":\"D\"},\"requestReferenceNumber\":\"QA26-RZE2Y5-MTAZY86Z\"}', '2026-08-27 05:02:30');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `content_plans`
--
ALTER TABLE `content_plans`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `content_series`
--
ALTER TABLE `content_series`
  ADD PRIMARY KEY (`training_id`,`id`);

--
-- Indexes for table `content_topics`
--
ALTER TABLE `content_topics`
  ADD PRIMARY KEY (`training_id`,`series_id`,`id`);

--
-- Indexes for table `content_trainings`
--
ALTER TABLE `content_trainings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `disciples`
--
ALTER TABLE `disciples`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_disciple_email` (`email`),
  ADD KEY `ix_disciple_pastor` (`pastor`);

--
-- Indexes for table `disciple_pastors`
--
ALTER TABLE `disciple_pastors`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_pastor_name` (`name`);

--
-- Indexes for table `entitlements`
--
ALTER TABLE `entitlements`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_ent` (`email`,`training_id`,`series_id`,`topic_id`),
  ADD KEY `ix_ent_user` (`user_id`),
  ADD KEY `ix_ent_ref` (`reference`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_payments_reference` (`reference`),
  ADD KEY `ix_payments_user` (`user_id`),
  ADD KEY `ix_payments_gateway` (`gateway_id`),
  ADD KEY `ix_payments_status` (`status`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_users_email` (`email`);

--
-- Indexes for table `webhook_log`
--
ALTER TABLE `webhook_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `ix_webhook_reference` (`reference`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `disciples`
--
ALTER TABLE `disciples`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `disciple_pastors`
--
ALTER TABLE `disciple_pastors`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `entitlements`
--
ALTER TABLE `entitlements`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `webhook_log`
--
ALTER TABLE `webhook_log`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `content_series`
--
ALTER TABLE `content_series`
  ADD CONSTRAINT `fk_series_training` FOREIGN KEY (`training_id`) REFERENCES `content_trainings` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `content_topics`
--
ALTER TABLE `content_topics`
  ADD CONSTRAINT `fk_topic_series` FOREIGN KEY (`training_id`,`series_id`) REFERENCES `content_series` (`training_id`, `id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
