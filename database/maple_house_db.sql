-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: Sep 20, 2025 at 08:22 PM
-- Server version: 10.4.28-MariaDB
-- PHP Version: 8.2.4

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `maple_house_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `announcements`
--

CREATE TABLE `announcements` (
  `id` int(11) NOT NULL,
  `title` varchar(100) NOT NULL,
  `content` text DEFAULT NULL,
  `target_audience` enum('All','Residents','Staff','Doctors') DEFAULT 'All',
  `priority` enum('Low','Medium','High') DEFAULT 'Medium',
  `is_active` tinyint(1) DEFAULT 1,
  `valid_from` date DEFAULT NULL,
  `valid_to` date DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `announcements`
--

INSERT INTO `announcements` (`id`, `title`, `content`, `target_audience`, `priority`, `is_active`, `valid_from`, `valid_to`, `created_by`, `created_at`) VALUES
(1, 'New Year Celebration', 'Join us for New Year celebration on January 15th at 6:00 PM in the main hall. Special dinner and entertainment programs arranged.', 'All', 'High', 1, '2024-01-10', '2024-01-16', 1, '2025-08-11 08:59:04'),
(2, 'Health Camp', 'Free health screening camp will be organized on January 25th. All residents are encouraged to participate.', 'Residents', 'Medium', 1, '2024-01-15', '2024-01-26', 1, '2025-08-11 08:59:04'),
(3, 'Kitchen Maintenance', 'Kitchen will be under maintenance on January 30th. Alternative arrangements will be made for meals.', 'All', 'High', 1, '2024-01-28', '2024-01-31', 1, '2025-08-11 08:59:04'),
(4, 'New Doctor Joining', 'Dr. Rahman will be joining our medical team from February 1st. He specializes in cardiology.', 'All', 'Medium', 1, '2024-01-20', '2024-02-05', 1, '2025-08-11 08:59:04');

-- --------------------------------------------------------

--
-- Table structure for table `chefs`
--

CREATE TABLE `chefs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `employee_id` varchar(20) DEFAULT NULL,
  `specialization` varchar(100) DEFAULT 'General Cooking',
  `experience_years` int(11) DEFAULT 0,
  `cuisine_expertise` text DEFAULT NULL,
  `shift_hours` varchar(50) DEFAULT NULL,
  `hire_date` date DEFAULT NULL,
  `is_available` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `monthly_salary` decimal(10,2) DEFAULT 35000.00 COMMENT 'Monthly salary amount'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `chefs`
--

INSERT INTO `chefs` (`id`, `user_id`, `employee_id`, `specialization`, `experience_years`, `cuisine_expertise`, `shift_hours`, `hire_date`, `is_available`, `created_at`, `monthly_salary`) VALUES
(1, 15, 'CHEF015', 'Head Chef', 5, 'Bengali, Continental, Indian', '6:00 AM - 2:00 PM, 4:00 PM - 8:00 PM', '2025-09-17', 1, '2025-09-17 16:09:27', 35000.00),
(2, 21, 'CHEF002', 'Breakfast Chef', 4, 'Bengali, Continental, Indian', '6:00 AM - 2:00 PM, 4:00 PM - 8:00 PM', '2025-09-19', 1, '2025-09-19 05:54:21', 35000.00),
(3, 22, 'CHEF004', 'Breakfast Chef', 20, 'Bengali, Continental, Indian', '6:00 AM - 2:00 PM, 4:00 PM - 8:00 PM', '2025-09-10', 1, '2025-09-19 16:54:26', 35000.00);

-- --------------------------------------------------------

--
-- Table structure for table `chef_cooking_sessions`
--

CREATE TABLE `chef_cooking_sessions` (
  `id` int(11) NOT NULL,
  `chef_id` int(11) NOT NULL,
  `meal_date` date NOT NULL,
  `meal_type` enum('Breakfast','Lunch','Dinner') NOT NULL,
  `cooking_status` enum('Not Started','In Progress','Completed') DEFAULT 'Not Started',
  `started_at` timestamp NULL DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `total_residents` int(11) DEFAULT 0,
  `spicy_count` int(11) DEFAULT 0,
  `non_spicy_count` int(11) DEFAULT 0,
  `less_oily_count` int(11) DEFAULT 0,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Stand-in structure for view `chef_daily_assignments`
-- (See below for the actual view)
--
CREATE TABLE `chef_daily_assignments` (
`daily_meal_id` int(11)
,`meal_date` date
,`meal_type` enum('Breakfast','Lunch','Dinner','Snack')
,`assigned_chef_id` int(11)
,`chef_name` varchar(101)
,`chef_specialization` varchar(100)
,`total_residents` bigint(21)
,`spicy_count` bigint(21)
,`non_spicy_count` bigint(21)
,`medium_spicy_count` bigint(21)
,`less_oily_count` bigint(21)
,`normal_oil_count` bigint(21)
,`extra_oily_count` bigint(21)
);

-- --------------------------------------------------------

--
-- Table structure for table `chef_inventory_usage`
--

CREATE TABLE `chef_inventory_usage` (
  `id` int(11) NOT NULL,
  `cooking_session_id` int(11) NOT NULL,
  `inventory_item_id` int(11) NOT NULL,
  `quantity_used` decimal(10,2) NOT NULL,
  `unit_cost` decimal(10,2) NOT NULL,
  `total_cost` decimal(10,2) NOT NULL,
  `usage_type` enum('Spicy','Non Spicy','Less oily','General') DEFAULT 'General',
  `notes` text DEFAULT NULL,
  `used_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `chef_usage_details`
--

CREATE TABLE `chef_usage_details` (
  `id` int(11) NOT NULL,
  `session_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `quantity_used` decimal(10,2) NOT NULL,
  `unit_cost` decimal(10,2) NOT NULL,
  `total_cost` decimal(10,2) NOT NULL,
  `notes` text DEFAULT NULL,
  `used_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `chef_usage_sessions`
--

CREATE TABLE `chef_usage_sessions` (
  `id` int(11) NOT NULL,
  `chef_id` int(11) NOT NULL,
  `meal_type` enum('BREAKFAST','LUNCH','DINNER','SNACK') NOT NULL,
  `meal_date` date NOT NULL,
  `description` text DEFAULT NULL,
  `total_cost` decimal(10,2) DEFAULT 0.00,
  `status` enum('IN_PROGRESS','COMPLETED','CANCELLED') DEFAULT 'IN_PROGRESS',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `completed_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `daily_meals`
--

CREATE TABLE `daily_meals` (
  `id` int(11) NOT NULL,
  `meal_date` date NOT NULL,
  `meal_type` enum('Breakfast','Lunch','Dinner','Snack') NOT NULL,
  `prepared_by` int(11) DEFAULT NULL,
  `breakfast_chef_id` int(11) DEFAULT NULL,
  `lunch_chef_id` int(11) DEFAULT NULL,
  `dinner_chef_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `total_cost` decimal(8,2) DEFAULT 0.00,
  `breakfast_veg_item_1` int(11) DEFAULT NULL,
  `breakfast_veg_item_2` int(11) DEFAULT NULL,
  `breakfast_veg_item_3` int(11) DEFAULT NULL,
  `breakfast_nonveg_item_1` int(11) DEFAULT NULL,
  `breakfast_nonveg_item_2` int(11) DEFAULT NULL,
  `breakfast_nonveg_item_3` int(11) DEFAULT NULL,
  `breakfast_drinks_item_1` int(11) DEFAULT NULL,
  `breakfast_drinks_item_2` int(11) DEFAULT NULL,
  `lunch_veg_item_1` int(11) DEFAULT NULL,
  `lunch_veg_item_2` int(11) DEFAULT NULL,
  `lunch_veg_item_3` int(11) DEFAULT NULL,
  `lunch_nonveg_item_1` int(11) DEFAULT NULL,
  `lunch_nonveg_item_2` int(11) DEFAULT NULL,
  `lunch_nonveg_item_3` int(11) DEFAULT NULL,
  `lunch_drinks_item_1` int(11) DEFAULT NULL,
  `lunch_drinks_item_2` int(11) DEFAULT NULL,
  `dinner_veg_item_1` int(11) DEFAULT NULL,
  `dinner_veg_item_2` int(11) DEFAULT NULL,
  `dinner_veg_item_3` int(11) DEFAULT NULL,
  `dinner_nonveg_item_1` int(11) DEFAULT NULL,
  `dinner_nonveg_item_2` int(11) DEFAULT NULL,
  `dinner_nonveg_item_3` int(11) DEFAULT NULL,
  `dinner_drinks_item_1` int(11) DEFAULT NULL,
  `dinner_drinks_item_2` int(11) DEFAULT NULL,
  `breakfast_cooking_status` enum('not_started','in_progress','completed') DEFAULT 'not_started',
  `lunch_cooking_status` enum('not_started','in_progress','completed') DEFAULT 'not_started',
  `dinner_cooking_status` enum('not_started','in_progress','completed') DEFAULT 'not_started',
  `breakfast_started_at` timestamp NULL DEFAULT NULL,
  `lunch_started_at` timestamp NULL DEFAULT NULL,
  `dinner_started_at` timestamp NULL DEFAULT NULL,
  `breakfast_completed_at` timestamp NULL DEFAULT NULL,
  `lunch_completed_at` timestamp NULL DEFAULT NULL,
  `dinner_completed_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `daily_meals`
--

INSERT INTO `daily_meals` (`id`, `meal_date`, `meal_type`, `prepared_by`, `breakfast_chef_id`, `lunch_chef_id`, `dinner_chef_id`, `created_at`, `updated_at`, `total_cost`, `breakfast_veg_item_1`, `breakfast_veg_item_2`, `breakfast_veg_item_3`, `breakfast_nonveg_item_1`, `breakfast_nonveg_item_2`, `breakfast_nonveg_item_3`, `breakfast_drinks_item_1`, `breakfast_drinks_item_2`, `lunch_veg_item_1`, `lunch_veg_item_2`, `lunch_veg_item_3`, `lunch_nonveg_item_1`, `lunch_nonveg_item_2`, `lunch_nonveg_item_3`, `lunch_drinks_item_1`, `lunch_drinks_item_2`, `dinner_veg_item_1`, `dinner_veg_item_2`, `dinner_veg_item_3`, `dinner_nonveg_item_1`, `dinner_nonveg_item_2`, `dinner_nonveg_item_3`, `dinner_drinks_item_1`, `dinner_drinks_item_2`, `breakfast_cooking_status`, `lunch_cooking_status`, `dinner_cooking_status`, `breakfast_started_at`, `lunch_started_at`, `dinner_started_at`, `breakfast_completed_at`, `lunch_completed_at`, `dinner_completed_at`) VALUES
(5, '2025-09-14', 'Breakfast', 2, 1, NULL, NULL, '2025-09-19 11:15:41', '2025-09-19 12:41:15', 0.00, 19, 25, NULL, 12, NULL, NULL, 51, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'not_started', 'not_started', 'not_started', NULL, NULL, NULL, NULL, NULL, NULL),
(6, '2025-09-14', 'Lunch', 2, NULL, 2, NULL, '2025-09-19 11:38:18', '2025-09-19 12:39:57', 0.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 20, 22, NULL, 5, 58, 17, 51, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'not_started', 'not_started', 'not_started', NULL, NULL, NULL, NULL, NULL, NULL),
(7, '2025-09-14', 'Dinner', NULL, NULL, NULL, 1, '2025-09-19 11:51:22', '2025-09-19 12:41:47', 0.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 58, 19, 59, 10, 60, 14, 55, NULL, 'not_started', 'not_started', 'not_started', NULL, NULL, NULL, NULL, NULL, NULL),
(8, '2025-09-15', 'Breakfast', NULL, 1, NULL, NULL, '2025-09-19 12:53:01', '2025-09-19 12:53:01', 0.00, 20, NULL, NULL, NULL, 9, NULL, 53, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'not_started', 'not_started', 'not_started', NULL, NULL, NULL, NULL, NULL, NULL),
(9, '2025-09-13', 'Lunch', NULL, NULL, 1, NULL, '2025-09-19 12:53:10', '2025-09-19 12:53:10', 0.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 21, NULL, NULL, 4, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'not_started', 'not_started', 'not_started', NULL, NULL, NULL, NULL, NULL, NULL),
(10, '2025-09-16', 'Breakfast', NULL, 2, NULL, NULL, '2025-09-19 13:26:35', '2025-09-19 13:26:35', 0.00, 30, 26, NULL, 13, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'not_started', 'not_started', 'not_started', NULL, NULL, NULL, NULL, NULL, NULL),
(11, '2025-09-13', 'Breakfast', NULL, 2, NULL, NULL, '2025-09-19 13:27:12', '2025-09-19 13:27:12', 0.00, 20, NULL, NULL, 10, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'not_started', 'not_started', 'not_started', NULL, NULL, NULL, NULL, NULL, NULL),
(12, '2025-09-16', 'Lunch', NULL, NULL, 1, NULL, '2025-09-19 14:52:23', '2025-09-19 14:52:23', 0.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 17, NULL, NULL, 5, NULL, NULL, 53, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'not_started', 'not_started', 'not_started', NULL, NULL, NULL, NULL, NULL, NULL),
(13, '2025-09-17', 'Breakfast', NULL, 2, NULL, NULL, '2025-09-19 14:52:38', '2025-09-19 14:52:38', 0.00, 24, NULL, NULL, NULL, 22, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'not_started', 'not_started', 'not_started', NULL, NULL, NULL, NULL, NULL, NULL),
(14, '2025-09-18', 'Breakfast', NULL, 1, NULL, NULL, '2025-09-19 14:53:01', '2025-09-19 14:56:29', 0.00, 32, 22, NULL, 31, 42, NULL, 52, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'not_started', 'not_started', 'not_started', NULL, NULL, NULL, NULL, NULL, NULL),
(17, '2025-09-18', 'Lunch', NULL, NULL, 2, NULL, '2025-09-19 14:54:29', '2025-09-19 14:56:21', 0.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 26, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'not_started', 'not_started', 'not_started', NULL, NULL, NULL, NULL, NULL, NULL),
(18, '2025-09-18', 'Dinner', NULL, NULL, NULL, 1, '2025-09-19 14:55:26', '2025-09-19 14:55:26', 0.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 20, NULL, NULL, 2, NULL, NULL, 16, NULL, 'not_started', 'not_started', 'not_started', NULL, NULL, NULL, NULL, NULL, NULL),
(19, '2025-09-17', 'Lunch', NULL, NULL, 2, NULL, '2025-09-19 14:55:37', '2025-09-19 14:55:37', 0.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 20, NULL, NULL, 56, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'not_started', 'not_started', 'not_started', NULL, NULL, NULL, NULL, NULL, NULL),
(20, '2025-09-15', 'Dinner', NULL, NULL, NULL, 2, '2025-09-19 14:57:03', '2025-09-19 14:57:03', 0.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 20, NULL, NULL, 6, NULL, NULL, NULL, NULL, 'not_started', 'not_started', 'not_started', NULL, NULL, NULL, NULL, NULL, NULL),
(21, '2025-09-15', 'Lunch', NULL, NULL, 2, NULL, '2025-09-19 14:57:23', '2025-09-19 14:57:29', 0.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 20, 28, NULL, 6, 59, NULL, 43, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'not_started', 'not_started', 'not_started', NULL, NULL, NULL, NULL, NULL, NULL),
(22, '2025-09-13', 'Dinner', NULL, NULL, NULL, 1, '2025-09-19 14:57:44', '2025-09-19 14:57:44', 0.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 26, 27, NULL, 6, 25, NULL, NULL, NULL, 'not_started', 'not_started', 'not_started', NULL, NULL, NULL, NULL, NULL, NULL),
(23, '2025-09-16', 'Dinner', NULL, NULL, NULL, 2, '2025-09-19 14:58:04', '2025-09-19 14:58:04', 0.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 20, 28, NULL, 2, 1, 17, 53, NULL, 'not_started', 'not_started', 'not_started', NULL, NULL, NULL, NULL, NULL, NULL),
(24, '2025-09-17', 'Dinner', NULL, NULL, NULL, NULL, '2025-09-19 14:58:30', '2025-09-19 14:58:30', 0.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 20, 28, 22, 7, 12, 20, 55, NULL, 'not_started', 'not_started', 'not_started', NULL, NULL, NULL, NULL, NULL, NULL),
(25, '2025-09-20', 'Breakfast', NULL, 2, NULL, NULL, '2025-09-19 15:01:40', '2025-09-19 15:01:40', 0.00, 19, NULL, NULL, 21, NULL, NULL, 16, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'not_started', 'not_started', 'not_started', NULL, NULL, NULL, NULL, NULL, NULL),
(27, '2025-09-26', 'Lunch', NULL, NULL, NULL, NULL, '2025-09-19 15:01:53', '2025-09-19 15:01:53', 0.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 20, NULL, NULL, 2, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'not_started', 'not_started', 'not_started', NULL, NULL, NULL, NULL, NULL, NULL),
(28, '2025-09-19', 'Breakfast', NULL, 2, NULL, NULL, '2025-09-19 15:11:49', '2025-09-19 15:11:49', 0.00, 19, NULL, NULL, 21, NULL, NULL, 55, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'not_started', 'not_started', 'not_started', NULL, NULL, NULL, NULL, NULL, NULL),
(29, '2025-09-19', 'Lunch', NULL, NULL, 2, NULL, '2025-09-19 15:12:02', '2025-09-19 15:12:25', 0.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 59, 17, NULL, 56, NULL, NULL, 55, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'not_started', 'not_started', 'not_started', NULL, NULL, NULL, NULL, NULL, NULL),
(30, '2025-09-26', 'Breakfast', NULL, 1, NULL, NULL, '2025-09-19 15:12:43', '2025-09-19 15:12:43', 0.00, 19, NULL, NULL, 19, NULL, NULL, 53, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'not_started', 'not_started', 'not_started', NULL, NULL, NULL, NULL, NULL, NULL),
(31, '2025-09-26', 'Dinner', NULL, NULL, NULL, 1, '2025-09-19 15:12:51', '2025-09-19 15:13:05', 0.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 26, NULL, NULL, 6, NULL, NULL, 53, NULL, 'not_started', 'not_started', 'not_started', NULL, NULL, NULL, NULL, NULL, NULL),
(32, '2025-09-19', 'Dinner', NULL, NULL, NULL, 2, '2025-09-19 15:30:04', '2025-09-19 15:40:40', 0.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 20, NULL, NULL, 5, NULL, NULL, 16, NULL, 'not_started', 'not_started', 'not_started', NULL, NULL, NULL, NULL, NULL, NULL),
(33, '2025-09-22', 'Breakfast', NULL, 2, NULL, NULL, '2025-09-19 15:42:16', '2025-09-19 15:42:16', 0.00, 19, NULL, NULL, 24, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'not_started', 'not_started', 'not_started', NULL, NULL, NULL, NULL, NULL, NULL),
(34, '2025-09-20', 'Lunch', NULL, NULL, 2, NULL, '2025-09-20 15:57:03', '2025-09-20 17:19:27', 0.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 20, 24, NULL, 6, 1, NULL, 16, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'not_started', 'not_started', 'not_started', NULL, NULL, NULL, NULL, NULL, NULL),
(35, '2025-09-20', 'Dinner', NULL, NULL, NULL, 1, '2025-09-20 15:57:16', '2025-09-20 15:57:34', 0.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 24, 23, NULL, 6, 59, NULL, 55, NULL, 'not_started', 'not_started', 'not_started', NULL, NULL, NULL, NULL, NULL, NULL),
(36, '2025-09-21', 'Breakfast', NULL, 2, NULL, NULL, '2025-09-20 15:57:27', '2025-09-20 15:57:27', 0.00, 24, NULL, NULL, 28, NULL, NULL, 55, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'not_started', 'not_started', 'not_started', NULL, NULL, NULL, NULL, NULL, NULL),
(37, '2025-09-21', 'Lunch', NULL, NULL, 2, NULL, '2025-09-20 15:57:53', '2025-09-20 15:57:53', 0.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 20, 24, NULL, 6, 20, NULL, 53, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'not_started', 'not_started', 'not_started', NULL, NULL, NULL, NULL, NULL, NULL),
(38, '2025-09-21', 'Dinner', NULL, NULL, NULL, 1, '2025-09-20 15:58:15', '2025-09-20 15:58:15', 0.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 20, 27, 29, 7, 27, 17, 55, 54, 'not_started', 'not_started', 'not_started', NULL, NULL, NULL, NULL, NULL, NULL),
(39, '2025-09-22', 'Lunch', NULL, NULL, 2, NULL, '2025-09-20 15:58:35', '2025-09-20 15:58:35', 0.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 20, 28, NULL, 7, 59, 20, 53, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'not_started', 'not_started', 'not_started', NULL, NULL, NULL, NULL, NULL, NULL),
(40, '2025-09-22', 'Dinner', NULL, NULL, NULL, 1, '2025-09-20 15:58:56', '2025-09-20 15:58:56', 0.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 20, 23, 29, 15, 24, 18, 53, NULL, 'not_started', 'not_started', 'not_started', NULL, NULL, NULL, NULL, NULL, NULL),
(41, '2025-09-23', 'Breakfast', NULL, 2, NULL, NULL, '2025-09-20 15:59:12', '2025-09-20 15:59:12', 0.00, 19, 22, NULL, 24, 25, 41, 53, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'not_started', 'not_started', 'not_started', NULL, NULL, NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `doctors`
--

CREATE TABLE `doctors` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `license_number` varchar(50) DEFAULT NULL,
  `specialization` varchar(100) DEFAULT NULL,
  `qualification` text DEFAULT NULL,
  `consultation_fee` decimal(10,2) DEFAULT NULL,
  `available_hours` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `monthly_salary` decimal(10,2) DEFAULT 45000.00 COMMENT 'Monthly salary amount'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `doctors`
--

INSERT INTO `doctors` (`id`, `user_id`, `license_number`, `specialization`, `qualification`, `consultation_fee`, `available_hours`, `created_at`, `monthly_salary`) VALUES
(1, 2, 'MED12345', 'Geriatric Medicine', 'MBBS, MD (Geriatrics)', 1000.00, 'Mon-Fri 9:00 AM - 5:00 PM', '2025-08-11 08:59:04', 45000.00);

-- --------------------------------------------------------

--
-- Table structure for table `donations`
--

CREATE TABLE `donations` (
  `id` int(11) NOT NULL,
  `donor_name` varchar(100) DEFAULT NULL,
  `donor_email` varchar(100) DEFAULT NULL,
  `donor_phone` varchar(20) DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `purpose` varchar(100) DEFAULT NULL,
  `payment_method` varchar(50) DEFAULT NULL,
  `transaction_id` varchar(100) DEFAULT NULL,
  `donation_date` date DEFAULT NULL,
  `is_anonymous` tinyint(1) DEFAULT 0,
  `is_verified` tinyint(1) DEFAULT 0,
  `verified_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `donations`
--

INSERT INTO `donations` (`id`, `donor_name`, `donor_email`, `donor_phone`, `amount`, `purpose`, `payment_method`, `transaction_id`, `donation_date`, `is_anonymous`, `is_verified`, `verified_by`, `created_at`) VALUES
(1, 'Ahmed Hassan', 'ahmed@email.com', '+880 7777 888999', 10000.00, 'General Support', 'Bank Transfer', 'TXN001234', '2024-01-05', 0, 1, 1, '2025-08-11 08:59:04'),
(2, 'Anonymous Donor', NULL, NULL, 5000.00, 'Medical Equipment', 'Online Payment', 'TXN001235', '2024-01-08', 1, 1, 1, '2025-08-11 08:59:04'),
(3, 'Fatima Rahman', 'fatima@email.com', '+880 8888 999000', 15000.00, 'Food & Nutrition', 'Cash', 'CASH001', '2024-01-10', 0, 1, 1, '2025-08-11 08:59:04'),
(4, 'Corporate Sponsor', 'sponsor@company.com', '+880 9999 000111', 50000.00, 'Infrastructure Development', 'Cheque', 'CHQ001', '2024-01-12', 0, 1, 1, '2025-08-11 08:59:04'),
(5, 'Fixed Test User', 'fixed@example.com', '01234567890', 2500.00, 'Medical Equipment', 'Online Payment', 'TXN1758113012588', '2025-09-17', 0, 1, 12, '2025-09-17 12:43:32'),
(6, NULL, NULL, NULL, 5000.00, 'Emergency Fund', 'Cash', 'TXN1758113027604', '2025-09-17', 1, 1, 12, '2025-09-17 12:43:47'),
(7, NULL, NULL, NULL, 2500.00, 'General Support', 'Bank Transfer', 'TXN1758113095906', '2025-09-17', 1, 1, 12, '2025-09-17 12:44:55'),
(8, NULL, NULL, NULL, 10000.00, 'General Support', 'Bank Transfer', 'TXN1758211293653', '2025-09-18', 1, 1, 12, '2025-09-18 16:01:33'),
(9, 'Habib', 'habib@gmail.com', '', 7750.00, 'General Support', 'Bank Transfer', 'TXN1758211546894', '2025-09-18', 0, 1, 12, '2025-09-18 16:05:46');

-- --------------------------------------------------------

--
-- Table structure for table `expenses`
--

CREATE TABLE `expenses` (
  `id` int(11) NOT NULL,
  `expense_type` enum('inventory','salary','utility','infrastructure','maintenance','other') NOT NULL,
  `category` varchar(100) NOT NULL,
  `description` text NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `expense_date` date NOT NULL,
  `payment_method` enum('cash','bank_transfer','cheque','card','online') DEFAULT 'cash',
  `vendor_name` varchar(255) DEFAULT NULL,
  `vendor_contact` varchar(20) DEFAULT NULL,
  `reference_id` int(11) DEFAULT NULL,
  `reference_type` varchar(50) DEFAULT NULL,
  `receipt_number` varchar(100) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `is_recurring` tinyint(1) DEFAULT 0,
  `recurring_frequency` enum('monthly','quarterly','yearly') DEFAULT NULL,
  `next_due_date` date DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `status` enum('pending','approved','paid','rejected') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `expenses`
--

INSERT INTO `expenses` (`id`, `expense_type`, `category`, `description`, `amount`, `expense_date`, `payment_method`, `vendor_name`, `vendor_contact`, `reference_id`, `reference_type`, `receipt_number`, `notes`, `is_recurring`, `recurring_frequency`, `next_due_date`, `created_by`, `approved_by`, `status`, `created_at`, `updated_at`) VALUES
(1, 'utility', 'Electricity', 'Monthly electricity bill', 8500.00, '2024-02-01', 'bank_transfer', 'DESCO', NULL, NULL, 'utility_bill', NULL, NULL, 0, NULL, NULL, 1, NULL, 'paid', '2025-09-19 16:00:19', '2025-09-19 16:00:19'),
(2, 'utility', 'Water', 'Monthly water bill', 3200.00, '2024-02-01', 'bank_transfer', 'WASA', NULL, NULL, 'utility_bill', NULL, NULL, 0, NULL, NULL, 1, NULL, 'paid', '2025-09-19 16:00:19', '2025-09-19 16:00:19'),
(4, 'maintenance', 'Cleaning', 'Monthly cleaning supplies', 4500.00, '2024-02-05', 'cash', 'Local Supplier', NULL, NULL, 'maintenance', NULL, NULL, 0, NULL, NULL, 1, NULL, 'paid', '2025-09-19 16:00:19', '2025-09-19 16:00:19'),
(5, 'infrastructure', 'Maintenance', 'Room 205 AC repair', 3500.00, '2024-02-10', 'cash', 'Cool Air Services', NULL, NULL, 'infrastructure', NULL, NULL, 0, NULL, NULL, 1, NULL, 'paid', '2025-09-19 16:00:19', '2025-09-19 16:00:19'),
(6, 'other', 'Office Supplies', 'Stationery and office materials', 2800.00, '2024-02-12', 'card', 'Office Mart', NULL, NULL, 'office', NULL, NULL, 0, NULL, NULL, 1, NULL, 'paid', '2025-09-19 16:00:19', '2025-09-19 16:00:19'),
(7, 'salary', 'Staff Salary', 'Monthly salary: Dr. Sarah Rahman (September 2025)', 45000.00, '2025-09-19', 'cash', NULL, NULL, 2, 'staff_salary', NULL, NULL, 0, NULL, NULL, 12, NULL, 'paid', '2025-09-19 16:20:52', '2025-09-19 17:24:50'),
(8, 'salary', 'Staff Salary', 'Monthly salary: Fatima Begum (September 2025)', 25000.00, '2025-09-19', 'cash', NULL, NULL, 4, 'staff_salary', NULL, NULL, 0, NULL, NULL, 12, NULL, 'paid', '2025-09-19 16:20:52', '2025-09-19 17:24:52'),
(9, 'salary', 'Staff Salary', 'Monthly salary: Rabiul Hasan (September 2025)', 25000.00, '2025-09-19', 'cash', NULL, NULL, 14, 'staff_salary', NULL, NULL, 0, NULL, NULL, 12, NULL, 'paid', '2025-09-19 16:20:52', '2025-09-19 17:24:53'),
(10, 'salary', 'Staff Salary', 'Monthly salary: Jobbar Ahmed (September 2025)', 35000.00, '2025-09-19', 'cash', NULL, NULL, 15, 'staff_salary', NULL, NULL, 0, NULL, NULL, 12, NULL, 'paid', '2025-09-19 16:20:52', '2025-09-19 17:24:55'),
(11, 'salary', 'Staff Salary', 'Monthly salary: Robin Rauf (September 2025)', 35000.00, '2025-09-19', 'cash', NULL, NULL, 21, 'staff_salary', NULL, NULL, 0, NULL, NULL, 12, NULL, 'paid', '2025-09-19 16:20:52', '2025-09-19 17:24:57'),
(12, 'salary', 'Staff Salary', 'Monthly salary: Hashem Rabbi (September 2025)', 35000.00, '2025-09-19', 'cash', NULL, NULL, 22, 'staff_salary', NULL, NULL, 0, NULL, NULL, 12, NULL, 'paid', '2025-09-19 16:57:30', '2025-09-19 17:24:44'),
(13, 'inventory', 'Inventory Purchase', 'Inventory purchase: Flour (200 units)', 10000.00, '2025-09-19', 'cash', 'Unknown Supplier', '', 4, 'inventory_item', NULL, NULL, 0, NULL, NULL, 12, NULL, 'paid', '2025-09-19 16:58:53', '2025-09-19 17:24:42'),
(14, 'inventory', 'Inventory Purchase', 'Inventory purchase: Rice (150 units)', 15000.00, '2025-09-20', 'cash', 'Unknown Supplier', '', 5, 'inventory_item', NULL, NULL, 0, NULL, NULL, 12, NULL, 'approved', '2025-09-20 17:53:15', '2025-09-20 17:53:15'),
(15, 'inventory', 'Inventory Purchase', 'Inventory purchase: Chicken Meat (50 units)', 7500.00, '2025-09-20', 'cash', 'Unknown Supplier', '', 6, 'inventory_item', NULL, NULL, 0, NULL, NULL, 12, NULL, 'approved', '2025-09-20 17:54:37', '2025-09-20 17:54:37'),
(16, 'inventory', 'Inventory Purchase', 'Inventory purchase: Beef (50 units)', 35000.00, '2025-09-20', 'cash', 'Unknown Supplier', '', 7, 'inventory_item', NULL, NULL, 0, NULL, NULL, 12, NULL, 'approved', '2025-09-20 17:56:15', '2025-09-20 17:56:15'),
(17, 'inventory', 'Inventory Purchase', 'Inventory purchase: Chili Powder (100 units)', 20000.00, '2025-09-20', 'cash', 'Unknown Supplier', '', 8, 'inventory_item', NULL, NULL, 0, NULL, NULL, 12, NULL, 'approved', '2025-09-20 17:57:47', '2025-09-20 17:57:47');

-- --------------------------------------------------------

--
-- Table structure for table `financial_transactions`
--

CREATE TABLE `financial_transactions` (
  `id` int(11) NOT NULL,
  `transaction_type` enum('Income','Expense') NOT NULL,
  `category` varchar(50) DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `description` text DEFAULT NULL,
  `reference_id` int(11) DEFAULT NULL,
  `reference_type` varchar(50) DEFAULT NULL,
  `transaction_date` date DEFAULT NULL,
  `processed_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `financial_transactions`
--

INSERT INTO `financial_transactions` (`id`, `transaction_type`, `category`, `amount`, `description`, `reference_id`, `reference_type`, `transaction_date`, `processed_by`, `created_at`) VALUES
(1, 'Income', 'Resident Fees', 65000.00, 'Monthly fees from residents', NULL, 'monthly_fees', '2024-01-01', 1, '2025-08-11 08:59:04'),
(2, 'Income', 'Donations', 80000.00, 'Total donations received', NULL, 'donations', '2024-01-10', 1, '2025-08-11 08:59:04'),
(3, 'Expense', 'Salaries', 45000.00, 'Staff salaries for January', NULL, 'salaries', '2024-01-01', 1, '2025-08-11 08:59:04'),
(4, 'Expense', 'Food', 25000.00, 'Food and nutrition expenses', NULL, 'food', '2024-01-05', 1, '2025-08-11 08:59:04'),
(5, 'Expense', 'Medical', 15000.00, 'Medical supplies and medicines', NULL, 'medical', '2024-01-08', 1, '2025-08-11 08:59:04'),
(6, 'Expense', 'Maintenance', 8000.00, 'Building maintenance and repairs', NULL, 'maintenance', '2024-01-12', 1, '2025-08-11 08:59:04');

-- --------------------------------------------------------

--
-- Table structure for table `health_records`
--

CREATE TABLE `health_records` (
  `id` int(11) NOT NULL,
  `resident_id` int(11) DEFAULT NULL,
  `doctor_id` int(11) DEFAULT NULL,
  `checkup_date` date DEFAULT NULL,
  `blood_pressure` varchar(20) DEFAULT NULL,
  `blood_sugar` decimal(5,2) DEFAULT NULL,
  `weight` decimal(5,2) DEFAULT NULL,
  `heart_rate` int(11) DEFAULT NULL,
  `temperature` decimal(4,2) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `prescribed_medicines` text DEFAULT NULL,
  `next_checkup_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `health_records`
--

INSERT INTO `health_records` (`id`, `resident_id`, `doctor_id`, `checkup_date`, `blood_pressure`, `blood_sugar`, `weight`, `heart_rate`, `temperature`, `notes`, `prescribed_medicines`, `next_checkup_date`, `created_at`) VALUES
(1, 1, 1, '2024-01-05', '140/90', 7.50, 68.50, 75, 98.60, 'Blood pressure slightly elevated. Blood sugar under control.', 'Metformin 500mg twice daily, Lisinopril 10mg once daily', '2024-02-05', '2025-08-11 08:59:04'),
(2, 2, 1, '2024-01-08', '130/85', NULL, 72.00, 80, 98.40, 'Arthritis pain manageable. Overall good condition.', 'Ibuprofen 400mg as needed for pain', '2024-02-08', '2025-08-11 08:59:04'),
(3, 3, 1, '2024-01-10', '150/95', NULL, 75.20, 85, 99.00, 'Heart condition stable. Needs regular monitoring.', 'Atenolol 50mg daily, Aspirin 75mg daily', '2024-02-10', '2025-08-11 08:59:04');

-- --------------------------------------------------------

--
-- Table structure for table `infrastructure`
--

CREATE TABLE `infrastructure` (
  `id` int(11) NOT NULL,
  `type` enum('room','floor','facility') NOT NULL,
  `name` varchar(100) NOT NULL,
  `floor_number` int(11) DEFAULT NULL,
  `room_number` varchar(20) DEFAULT NULL,
  `capacity` int(11) DEFAULT 1,
  `room_type` enum('single','double','triple','suite','common') DEFAULT 'single',
  `amenities` text DEFAULT NULL,
  `monthly_rent` decimal(10,2) DEFAULT 0.00,
  `maintenance_cost` decimal(10,2) DEFAULT 0.00,
  `is_occupied` tinyint(1) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `construction_date` date DEFAULT NULL,
  `last_maintenance` date DEFAULT NULL,
  `next_maintenance` date DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `infrastructure`
--

INSERT INTO `infrastructure` (`id`, `type`, `name`, `floor_number`, `room_number`, `capacity`, `room_type`, `amenities`, `monthly_rent`, `maintenance_cost`, `is_occupied`, `is_active`, `construction_date`, `last_maintenance`, `next_maintenance`, `notes`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'floor', 'Ground Floor', 1, NULL, 20, NULL, NULL, 0.00, 2000.00, 0, 1, NULL, NULL, NULL, NULL, 1, '2025-09-19 16:00:19', '2025-09-19 16:00:19'),
(2, 'room', 'Room 101', 1, '101', 1, 'single', NULL, 15000.00, 500.00, 0, 1, NULL, NULL, NULL, NULL, 1, '2025-09-19 16:00:19', '2025-09-19 16:00:19'),
(3, 'room', 'Room 102', 1, '102', 1, 'single', NULL, 15000.00, 500.00, 0, 1, NULL, NULL, NULL, NULL, 1, '2025-09-19 16:00:19', '2025-09-19 16:00:19'),
(4, 'room', 'Room 103', 1, '103', 1, 'single', NULL, 15000.00, 500.00, 0, 1, NULL, NULL, NULL, NULL, 1, '2025-09-19 16:00:19', '2025-09-19 16:00:19'),
(5, 'room', 'Room 104', 1, '104', 1, 'single', NULL, 15000.00, 500.00, 0, 1, NULL, NULL, NULL, NULL, 1, '2025-09-19 16:00:19', '2025-09-19 16:00:19'),
(6, 'room', 'Room 105', 1, '105', 1, 'single', NULL, 15000.00, 500.00, 0, 1, NULL, NULL, NULL, NULL, 1, '2025-09-19 16:00:19', '2025-09-19 16:00:19'),
(7, 'room', 'Room 106', 1, '106', 1, 'single', NULL, 15000.00, 500.00, 0, 1, NULL, NULL, NULL, NULL, 1, '2025-09-19 16:00:19', '2025-09-19 16:00:19'),
(8, 'room', 'Room 107', 1, '107', 1, 'single', NULL, 15000.00, 500.00, 0, 1, NULL, NULL, NULL, NULL, 1, '2025-09-19 16:00:19', '2025-09-19 16:00:19'),
(9, 'room', 'Room 108', 1, '108', 1, 'single', NULL, 15000.00, 500.00, 0, 1, NULL, NULL, NULL, NULL, 1, '2025-09-19 16:00:19', '2025-09-19 16:00:19'),
(10, 'room', 'Room 109', 1, '109', 1, 'single', NULL, 15000.00, 500.00, 0, 1, NULL, NULL, NULL, NULL, 1, '2025-09-19 16:00:19', '2025-09-19 16:00:19'),
(11, 'room', 'Room 110', 1, '110', 1, 'single', NULL, 15000.00, 500.00, 0, 1, NULL, NULL, NULL, NULL, 1, '2025-09-19 16:00:19', '2025-09-19 16:00:19'),
(12, 'room', 'Room 111', 1, '111', 2, 'double', NULL, 25000.00, 700.00, 0, 1, NULL, NULL, NULL, NULL, 1, '2025-09-19 16:00:19', '2025-09-19 16:00:19'),
(13, 'room', 'Room 112', 1, '112', 2, 'double', NULL, 25000.00, 700.00, 0, 1, NULL, NULL, NULL, NULL, 1, '2025-09-19 16:00:19', '2025-09-19 16:00:19'),
(14, 'room', 'Room 113', 1, '113', 2, 'double', NULL, 25000.00, 700.00, 0, 1, NULL, NULL, NULL, NULL, 1, '2025-09-19 16:00:19', '2025-09-19 16:00:19'),
(15, 'room', 'Room 114', 1, '114', 2, 'double', NULL, 25000.00, 700.00, 0, 1, NULL, NULL, NULL, NULL, 1, '2025-09-19 16:00:19', '2025-09-19 16:00:19'),
(16, 'room', 'Room 115', 1, '115', 2, 'double', NULL, 25000.00, 700.00, 0, 1, NULL, NULL, NULL, NULL, 1, '2025-09-19 16:00:19', '2025-09-19 16:00:19'),
(17, 'room', 'Room 116', 1, '116', 2, 'double', NULL, 25000.00, 700.00, 0, 1, NULL, NULL, NULL, NULL, 1, '2025-09-19 16:00:19', '2025-09-19 16:00:19'),
(18, 'room', 'Room 117', 1, '117', 2, 'double', NULL, 25000.00, 700.00, 0, 1, NULL, NULL, NULL, NULL, 1, '2025-09-19 16:00:19', '2025-09-19 16:00:19'),
(19, 'room', 'Room 118', 1, '118', 2, 'double', NULL, 25000.00, 700.00, 0, 1, NULL, NULL, NULL, NULL, 1, '2025-09-19 16:00:19', '2025-09-19 16:00:19'),
(20, 'room', 'Room 119', 1, '119', 3, 'suite', NULL, 35000.00, 1000.00, 0, 1, NULL, NULL, NULL, NULL, 1, '2025-09-19 16:00:19', '2025-09-19 16:00:19'),
(21, 'room', 'Room 120', 1, '120', 3, 'suite', NULL, 35000.00, 1000.00, 0, 1, NULL, NULL, NULL, NULL, 1, '2025-09-19 16:00:19', '2025-09-19 16:00:19'),
(22, 'floor', 'First Floor', 2, NULL, 20, NULL, NULL, 0.00, 2000.00, 0, 1, NULL, NULL, NULL, NULL, 1, '2025-09-19 16:00:19', '2025-09-19 16:00:19'),
(23, 'room', 'Room 201', 2, '201', 1, 'single', NULL, 15000.00, 500.00, 0, 1, NULL, NULL, NULL, NULL, 1, '2025-09-19 16:00:19', '2025-09-19 16:00:19'),
(24, 'room', 'Room 202', 2, '202', 1, 'single', NULL, 15000.00, 500.00, 0, 1, NULL, NULL, NULL, NULL, 1, '2025-09-19 16:00:19', '2025-09-19 16:00:19'),
(25, 'room', 'Room 203', 2, '203', 1, 'single', NULL, 15000.00, 500.00, 0, 1, NULL, NULL, NULL, NULL, 1, '2025-09-19 16:00:19', '2025-09-19 16:00:19'),
(26, 'room', 'Room 204', 2, '204', 1, 'single', NULL, 15000.00, 500.00, 0, 1, NULL, NULL, NULL, NULL, 1, '2025-09-19 16:00:19', '2025-09-19 16:00:19'),
(27, 'room', 'Room 205', 2, '205', 1, 'single', NULL, 15000.00, 500.00, 0, 1, NULL, NULL, NULL, NULL, 1, '2025-09-19 16:00:19', '2025-09-19 16:00:19'),
(28, 'room', 'Room 206', 2, '206', 1, 'single', NULL, 15000.00, 500.00, 0, 1, NULL, NULL, NULL, NULL, 1, '2025-09-19 16:00:19', '2025-09-19 16:00:19'),
(29, 'room', 'Room 207', 2, '207', 1, 'single', NULL, 15000.00, 500.00, 0, 1, NULL, NULL, NULL, NULL, 1, '2025-09-19 16:00:19', '2025-09-19 16:00:19'),
(30, 'room', 'Room 208', 2, '208', 1, 'single', NULL, 15000.00, 500.00, 0, 1, NULL, NULL, NULL, NULL, 1, '2025-09-19 16:00:19', '2025-09-19 16:00:19'),
(31, 'room', 'Room 209', 2, '209', 1, 'single', NULL, 15000.00, 500.00, 0, 1, NULL, NULL, NULL, NULL, 1, '2025-09-19 16:00:19', '2025-09-19 16:00:19'),
(32, 'room', 'Room 210', 2, '210', 1, 'single', NULL, 15000.00, 500.00, 0, 1, NULL, NULL, NULL, NULL, 1, '2025-09-19 16:00:19', '2025-09-19 16:00:19'),
(33, 'room', 'Room 211', 2, '211', 2, 'double', NULL, 25000.00, 700.00, 0, 1, NULL, NULL, NULL, NULL, 1, '2025-09-19 16:00:19', '2025-09-19 16:00:19'),
(34, 'room', 'Room 212', 2, '212', 2, 'double', NULL, 25000.00, 700.00, 0, 1, NULL, NULL, NULL, NULL, 1, '2025-09-19 16:00:19', '2025-09-19 16:00:19'),
(35, 'room', 'Room 213', 2, '213', 2, 'double', NULL, 25000.00, 700.00, 0, 1, NULL, NULL, NULL, NULL, 1, '2025-09-19 16:00:19', '2025-09-19 16:00:19'),
(36, 'room', 'Room 214', 2, '214', 2, 'double', NULL, 25000.00, 700.00, 0, 1, NULL, NULL, NULL, NULL, 1, '2025-09-19 16:00:19', '2025-09-19 16:00:19'),
(37, 'room', 'Room 215', 2, '215', 2, 'double', NULL, 25000.00, 700.00, 0, 1, NULL, NULL, NULL, NULL, 1, '2025-09-19 16:00:19', '2025-09-19 16:00:19'),
(38, 'room', 'Room 216', 2, '216', 2, 'double', NULL, 25000.00, 700.00, 0, 1, NULL, NULL, NULL, NULL, 1, '2025-09-19 16:00:19', '2025-09-19 16:00:19'),
(39, 'room', 'Room 217', 2, '217', 2, 'double', NULL, 25000.00, 700.00, 0, 1, NULL, NULL, NULL, NULL, 1, '2025-09-19 16:00:19', '2025-09-19 16:00:19'),
(40, 'room', 'Room 218', 2, '218', 2, 'double', NULL, 25000.00, 700.00, 0, 1, NULL, NULL, NULL, NULL, 1, '2025-09-19 16:00:19', '2025-09-19 16:00:19'),
(41, 'room', 'Room 219', 2, '219', 3, 'suite', NULL, 35000.00, 1000.00, 0, 1, NULL, NULL, NULL, NULL, 1, '2025-09-19 16:00:19', '2025-09-19 16:00:19'),
(42, 'room', 'Room 220', 2, '220', 3, 'suite', NULL, 35000.00, 1000.00, 0, 1, NULL, NULL, NULL, NULL, 1, '2025-09-19 16:00:19', '2025-09-19 16:00:19'),
(43, 'floor', 'Second Floor', 3, NULL, 20, NULL, NULL, 0.00, 2000.00, 0, 1, NULL, NULL, NULL, NULL, 1, '2025-09-19 16:00:19', '2025-09-19 16:00:19'),
(44, 'room', 'Room 301', 3, '301', 1, 'single', NULL, 15000.00, 500.00, 0, 1, NULL, NULL, NULL, NULL, 1, '2025-09-19 16:00:19', '2025-09-19 16:00:19'),
(45, 'room', 'Room 302', 3, '302', 1, 'single', NULL, 15000.00, 500.00, 0, 1, NULL, NULL, NULL, NULL, 1, '2025-09-19 16:00:19', '2025-09-19 16:00:19'),
(46, 'room', 'Room 303', 3, '303', 1, 'single', NULL, 15000.00, 500.00, 0, 1, NULL, NULL, NULL, NULL, 1, '2025-09-19 16:00:19', '2025-09-19 16:00:19'),
(47, 'room', 'Room 304', 3, '304', 1, 'single', NULL, 15000.00, 500.00, 0, 1, NULL, NULL, NULL, NULL, 1, '2025-09-19 16:00:19', '2025-09-19 16:00:19'),
(48, 'room', 'Room 305', 3, '305', 1, 'single', NULL, 15000.00, 500.00, 0, 1, NULL, NULL, NULL, NULL, 1, '2025-09-19 16:00:19', '2025-09-19 16:00:19'),
(49, 'room', 'Room 306', 3, '306', 1, 'single', NULL, 15000.00, 500.00, 0, 1, NULL, NULL, NULL, NULL, 1, '2025-09-19 16:00:19', '2025-09-19 16:00:19'),
(50, 'room', 'Room 307', 3, '307', 1, 'single', NULL, 15000.00, 500.00, 0, 1, NULL, NULL, NULL, NULL, 1, '2025-09-19 16:00:19', '2025-09-19 16:00:19'),
(51, 'room', 'Room 308', 3, '308', 1, 'single', NULL, 15000.00, 500.00, 0, 1, NULL, NULL, NULL, NULL, 1, '2025-09-19 16:00:19', '2025-09-19 16:00:19'),
(52, 'room', 'Room 309', 3, '309', 1, 'single', NULL, 15000.00, 500.00, 0, 1, NULL, NULL, NULL, NULL, 1, '2025-09-19 16:00:19', '2025-09-19 16:00:19'),
(53, 'room', 'Room 310', 3, '310', 1, 'single', NULL, 15000.00, 500.00, 0, 1, NULL, NULL, NULL, NULL, 1, '2025-09-19 16:00:19', '2025-09-19 16:00:19'),
(54, 'room', 'Room 311', 3, '311', 2, 'double', NULL, 25000.00, 700.00, 0, 1, NULL, NULL, NULL, NULL, 1, '2025-09-19 16:00:19', '2025-09-19 16:00:19'),
(55, 'room', 'Room 312', 3, '312', 2, 'double', NULL, 25000.00, 700.00, 0, 1, NULL, NULL, NULL, NULL, 1, '2025-09-19 16:00:19', '2025-09-19 16:00:19'),
(56, 'room', 'Room 313', 3, '313', 2, 'double', NULL, 25000.00, 700.00, 0, 1, NULL, NULL, NULL, NULL, 1, '2025-09-19 16:00:19', '2025-09-19 16:00:19'),
(57, 'room', 'Room 314', 3, '314', 2, 'double', NULL, 25000.00, 700.00, 0, 1, NULL, NULL, NULL, NULL, 1, '2025-09-19 16:00:19', '2025-09-19 16:00:19'),
(58, 'room', 'Room 315', 3, '315', 2, 'double', NULL, 25000.00, 700.00, 0, 1, NULL, NULL, NULL, NULL, 1, '2025-09-19 16:00:19', '2025-09-19 16:00:19'),
(59, 'room', 'Room 316', 3, '316', 2, 'double', NULL, 25000.00, 700.00, 0, 1, NULL, NULL, NULL, NULL, 1, '2025-09-19 16:00:19', '2025-09-19 16:00:19'),
(60, 'room', 'Room 317', 3, '317', 2, 'double', NULL, 25000.00, 700.00, 0, 1, NULL, NULL, NULL, NULL, 1, '2025-09-19 16:00:19', '2025-09-19 16:00:19'),
(61, 'room', 'Room 318', 3, '318', 2, 'double', NULL, 25000.00, 700.00, 0, 1, NULL, NULL, NULL, NULL, 1, '2025-09-19 16:00:19', '2025-09-19 16:00:19'),
(62, 'room', 'Room 319', 3, '319', 3, 'suite', NULL, 35000.00, 1000.00, 0, 1, NULL, NULL, NULL, NULL, 1, '2025-09-19 16:00:19', '2025-09-19 16:00:19'),
(63, 'room', 'Room 320', 3, '320', 3, 'suite', NULL, 35000.00, 1000.00, 0, 1, NULL, NULL, NULL, NULL, 1, '2025-09-19 16:00:19', '2025-09-19 16:00:19');

-- --------------------------------------------------------

--
-- Table structure for table `inventory_categories`
--

CREATE TABLE `inventory_categories` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `inventory_categories`
--

INSERT INTO `inventory_categories` (`id`, `name`, `description`, `created_at`, `updated_at`) VALUES
(1, 'Vegetables', 'Fresh and frozen vegetables', '2025-09-17 12:32:23', '2025-09-17 12:32:23'),
(2, 'Fruits', 'Fresh and dried fruits', '2025-09-17 12:32:23', '2025-09-17 12:32:23'),
(3, 'Meat & Poultry', 'Fresh and frozen meat products', '2025-09-17 12:32:23', '2025-09-17 12:32:23'),
(4, 'Dairy', 'Milk, cheese, yogurt and dairy products', '2025-09-17 12:32:23', '2025-09-17 12:32:23'),
(5, 'Grains & Cereals', 'Rice, wheat, oats and grain products', '2025-09-17 12:32:23', '2025-09-17 12:32:23'),
(6, 'Spices & Condiments', 'Spices, herbs and cooking condiments', '2025-09-17 12:32:23', '2025-09-17 12:32:23'),
(7, 'Beverages', 'Juices, tea, coffee and other drinks', '2025-09-17 12:32:23', '2025-09-17 12:32:23'),
(8, 'Cleaning Supplies', 'Kitchen and general cleaning materials', '2025-09-17 12:32:23', '2025-09-17 12:32:23'),
(9, 'Medical Supplies', 'Basic medical and health supplies', '2025-09-17 12:32:23', '2025-09-17 12:32:23'),
(10, 'Other Supplies', 'General supplies and miscellaneous items', '2025-09-17 12:32:23', '2025-09-17 12:32:23');

-- --------------------------------------------------------

--
-- Table structure for table `inventory_items`
--

CREATE TABLE `inventory_items` (
  `id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `category_id` int(11) DEFAULT NULL,
  `unit_id` int(11) DEFAULT NULL,
  `current_stock` decimal(10,2) DEFAULT 0.00,
  `after_usage_current_stock` decimal(10,2) DEFAULT NULL COMMENT 'Stock remaining after chef usage',
  `minimum_stock` decimal(10,2) DEFAULT 0.00,
  `maximum_stock` decimal(10,2) DEFAULT 0.00,
  `unit_cost` decimal(10,2) DEFAULT 0.00,
  `supplier_name` varchar(150) DEFAULT NULL,
  `supplier_contact` varchar(100) DEFAULT NULL,
  `location` varchar(100) DEFAULT NULL,
  `expiry_date` date DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `inventory_items`
--

INSERT INTO `inventory_items` (`id`, `name`, `description`, `category_id`, `unit_id`, `current_stock`, `after_usage_current_stock`, `minimum_stock`, `maximum_stock`, `unit_cost`, `supplier_name`, `supplier_contact`, `location`, `expiry_date`, `is_active`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'Meat', '', 7, 10, 333.00, NULL, 1.00, 2.00, 200.00, '', '', '', NULL, 0, 12, '2025-09-17 12:58:13', '2025-09-19 17:14:53'),
(2, 'Rice', '', 10, 1, 500.00, NULL, 20.00, 30.00, 90.00, 'Hazi Karim', '01711223345', 'Dhaka', '2025-11-30', 0, 12, '2025-09-17 16:59:51', '2025-09-19 17:14:57'),
(3, 'Flour', '', 10, 1, 20.00, NULL, 20.00, 30.00, 55.00, 'Hazi Karim', '01711223345', 'Dhaka', '2025-09-30', 0, 12, '2025-09-17 17:01:22', '2025-09-19 17:14:47'),
(4, 'Flour', '', 10, 1, 200.00, NULL, 20.00, 40.00, 50.00, '', '', '', '2025-10-23', 1, 12, '2025-09-19 16:58:53', '2025-09-19 16:58:53'),
(5, 'Rice', '', 5, 1, 150.00, NULL, 10.00, 30.00, 100.00, '', '', '', '2026-02-23', 1, 12, '2025-09-20 17:53:15', '2025-09-20 17:53:15'),
(6, 'Chicken Meat', '', 3, 1, 50.00, NULL, 5.00, 10.00, 150.00, '', '', '', '2025-10-20', 1, 12, '2025-09-20 17:54:37', '2025-09-20 17:54:37'),
(7, 'Beef', '', 3, 1, 50.00, NULL, 5.00, 20.00, 700.00, '', '', '', '2025-09-26', 1, 12, '2025-09-20 17:56:15', '2025-09-20 17:56:15'),
(8, 'Chili Powder', '', 6, 1, 100.00, NULL, 10.00, 20.00, 200.00, '', '', '', '2026-06-20', 1, 12, '2025-09-20 17:57:47', '2025-09-20 17:57:47');

-- --------------------------------------------------------

--
-- Table structure for table `inventory_transactions`
--

CREATE TABLE `inventory_transactions` (
  `id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `transaction_type` enum('IN','OUT','ADJUSTMENT') NOT NULL,
  `quantity` decimal(10,2) NOT NULL,
  `previous_stock` decimal(10,2) NOT NULL,
  `new_stock` decimal(10,2) NOT NULL,
  `reference_type` enum('PURCHASE','USAGE','WASTE','ADJUSTMENT','CHEF_USAGE') NOT NULL,
  `reference_id` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `performed_by` int(11) NOT NULL,
  `transaction_date` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `inventory_transactions`
--

INSERT INTO `inventory_transactions` (`id`, `item_id`, `transaction_type`, `quantity`, `previous_stock`, `new_stock`, `reference_type`, `reference_id`, `notes`, `performed_by`, `transaction_date`) VALUES
(1, 1, 'IN', 333.00, 0.00, 333.00, 'ADJUSTMENT', NULL, 'Initial stock', 12, '2025-09-17 12:58:13'),
(2, 2, 'IN', 500.00, 0.00, 500.00, 'ADJUSTMENT', NULL, 'Initial stock', 12, '2025-09-17 16:59:52'),
(3, 3, 'IN', 20.00, 0.00, 20.00, 'ADJUSTMENT', NULL, 'Initial stock', 12, '2025-09-17 17:01:22'),
(4, 4, 'IN', 200.00, 0.00, 200.00, 'ADJUSTMENT', NULL, 'Initial stock', 12, '2025-09-19 16:58:53'),
(5, 5, 'IN', 150.00, 0.00, 150.00, 'ADJUSTMENT', NULL, 'Initial stock', 12, '2025-09-20 17:53:15'),
(6, 6, 'IN', 50.00, 0.00, 50.00, 'ADJUSTMENT', NULL, 'Initial stock', 12, '2025-09-20 17:54:37'),
(7, 7, 'IN', 50.00, 0.00, 50.00, 'ADJUSTMENT', NULL, 'Initial stock', 12, '2025-09-20 17:56:15'),
(8, 8, 'IN', 100.00, 0.00, 100.00, 'ADJUSTMENT', NULL, 'Initial stock', 12, '2025-09-20 17:57:47');

-- --------------------------------------------------------

--
-- Table structure for table `inventory_units`
--

CREATE TABLE `inventory_units` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `abbreviation` varchar(10) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `inventory_units`
--

INSERT INTO `inventory_units` (`id`, `name`, `abbreviation`, `created_at`) VALUES
(1, 'Kilogram', 'kg', '2025-09-17 12:32:23'),
(2, 'Gram', 'g', '2025-09-17 12:32:23'),
(3, 'Liter', 'L', '2025-09-17 12:32:23'),
(4, 'Milliliter', 'ml', '2025-09-17 12:32:23'),
(5, 'Pieces', 'pcs', '2025-09-17 12:32:23'),
(6, 'Packets', 'pkt', '2025-09-17 12:32:23'),
(7, 'Bottles', 'btl', '2025-09-17 12:32:23'),
(8, 'Cans', 'can', '2025-09-17 12:32:23'),
(9, 'Boxes', 'box', '2025-09-17 12:32:23'),
(10, 'Bags', 'bag', '2025-09-17 12:32:23');

-- --------------------------------------------------------

--
-- Table structure for table `meal_items`
--

CREATE TABLE `meal_items` (
  `id` int(11) NOT NULL,
  `item_name` varchar(255) NOT NULL,
  `ingredients` text DEFAULT NULL,
  `category` enum('protein','fish','rice','vegetable','breakfast_veg','breakfast_nonveg','breakfast_item','drinks') NOT NULL,
  `meal_section` enum('breakfast','lunch','dinner','lunch_dinner','drinks','all') DEFAULT 'all',
  `item_type` enum('veg','non_veg') NOT NULL,
  `price` decimal(8,2) NOT NULL DEFAULT 0.00,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `meal_items`
--

INSERT INTO `meal_items` (`id`, `item_name`, `ingredients`, `category`, `meal_section`, `item_type`, `price`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'Chicken Curry', 'Chicken, Onion, Garlic, Ginger, Tomato, Spices, Oil', 'protein', 'lunch_dinner', 'non_veg', 120.00, 1, '2025-09-19 05:10:45', '2025-09-19 10:18:23'),
(2, 'Beef Curry', 'Beef, Onion, Garlic, Ginger, Potato, Spices, Oil', 'protein', 'lunch_dinner', 'non_veg', 150.00, 1, '2025-09-19 05:10:45', '2025-09-19 10:20:52'),
(3, 'Lamb Curry', NULL, 'protein', 'lunch', 'non_veg', 180.00, 1, '2025-09-19 05:10:45', '2025-09-19 10:12:22'),
(4, 'Mutton Curry', NULL, 'protein', 'lunch', 'non_veg', 170.00, 1, '2025-09-19 05:10:45', '2025-09-19 10:12:28'),
(5, 'Chicken Roast', '', 'protein', 'lunch_dinner', 'non_veg', 140.00, 1, '2025-09-19 05:10:45', '2025-09-19 10:21:12'),
(6, 'Beef Bhuna', '', 'protein', 'lunch_dinner', 'non_veg', 160.00, 1, '2025-09-19 05:10:45', '2025-09-19 10:18:48'),
(7, 'Chicken Korma', '', 'protein', 'lunch_dinner', 'non_veg', 130.00, 1, '2025-09-19 05:10:45', '2025-09-19 10:21:04'),
(9, 'Rui Fish Curry', '', 'fish', 'lunch_dinner', 'non_veg', 100.00, 1, '2025-09-19 05:10:45', '2025-09-19 13:39:34'),
(10, 'Boal Fish Fry', '', 'fish', 'dinner', 'non_veg', 120.00, 1, '2025-09-19 05:10:45', '2025-09-19 13:42:21'),
(11, 'Shing Fish Curry', '', 'fish', 'lunch_dinner', 'non_veg', 150.00, 1, '2025-09-19 05:10:45', '2025-09-19 13:39:40'),
(12, 'Katla Fish Curry', '', 'fish', 'lunch_dinner', 'non_veg', 90.00, 1, '2025-09-19 05:10:45', '2025-09-19 13:39:17'),
(13, 'Pabda Fish Curry', '', 'fish', 'lunch_dinner', 'non_veg', 130.00, 1, '2025-09-19 05:10:45', '2025-09-19 13:39:23'),
(14, 'Hilsa Fish', '', 'fish', 'lunch_dinner', 'non_veg', 220.00, 1, '2025-09-19 05:10:45', '2025-09-19 13:38:59'),
(15, 'Fish Fry Mixed', '', 'fish', 'lunch_dinner', 'non_veg', 110.00, 1, '2025-09-19 05:10:45', '2025-09-19 13:38:34'),
(16, 'Steamed Rice', NULL, 'rice', 'drinks', 'veg', 30.00, 1, '2025-09-19 05:10:45', '2025-09-19 09:36:28'),
(17, 'Polao', 'Basmati Rice, Water, Salt, Oil', 'rice', 'lunch_dinner', 'veg', 50.00, 1, '2025-09-19 05:10:45', '2025-09-19 13:40:30'),
(18, 'Biryani', '', 'rice', 'lunch_dinner', 'non_veg', 80.00, 1, '2025-09-19 05:10:45', '2025-09-19 13:39:56'),
(19, 'Khichuri', NULL, 'rice', 'breakfast', 'veg', 40.00, 1, '2025-09-19 05:10:45', '2025-09-19 13:40:48'),
(20, 'Fried Rice', NULL, 'rice', 'lunch_dinner', 'veg', 60.00, 1, '2025-09-19 05:10:45', '2025-09-19 13:40:53'),
(21, 'Vegetable Rice', NULL, 'rice', 'breakfast', 'veg', 45.00, 1, '2025-09-19 05:10:45', '2025-09-19 13:40:59'),
(22, 'Mixed Vegetable Curry', 'Mixed Vegetables, Onion, Garlic, Spices, Oil', 'vegetable', 'all', 'veg', 40.00, 1, '2025-09-19 05:10:45', '2025-09-19 06:27:40'),
(23, 'Potato Curry', NULL, 'vegetable', 'all', 'veg', 30.00, 1, '2025-09-19 05:10:45', '2025-09-19 05:10:45'),
(24, 'Cabbage Curry', NULL, 'vegetable', 'all', 'veg', 35.00, 1, '2025-09-19 05:10:45', '2025-09-19 05:10:45'),
(25, 'Spinach (Shak)', NULL, 'vegetable', 'all', 'veg', 25.00, 1, '2025-09-19 05:10:45', '2025-09-19 05:10:45'),
(26, 'Brinjal Curry', NULL, 'vegetable', 'all', 'veg', 35.00, 1, '2025-09-19 05:10:45', '2025-09-19 05:10:45'),
(27, 'Okra (Bhendi)', NULL, 'vegetable', 'all', 'veg', 40.00, 1, '2025-09-19 05:10:45', '2025-09-19 13:41:22'),
(28, 'Cauliflower Curry', NULL, 'vegetable', 'all', 'veg', 35.00, 1, '2025-09-19 05:10:45', '2025-09-19 05:10:45'),
(29, 'Pumpkin Curry', NULL, 'vegetable', 'all', 'veg', 30.00, 1, '2025-09-19 05:10:45', '2025-09-19 05:10:45'),
(30, 'Bean Curry', NULL, 'vegetable', 'all', 'veg', 40.00, 1, '2025-09-19 05:10:45', '2025-09-19 05:10:45'),
(31, 'Porota', '', 'breakfast_veg', 'breakfast', 'veg', 50.00, 1, '2025-09-19 05:10:58', '2025-09-19 13:30:50'),
(32, 'Puri', '', 'breakfast_veg', 'breakfast', 'veg', 45.00, 1, '2025-09-19 05:10:58', '2025-09-19 13:31:19'),
(33, 'Bread', '', 'breakfast_veg', 'breakfast', 'veg', 40.00, 1, '2025-09-19 05:10:58', '2025-09-19 10:23:00'),
(35, 'Roti', '', 'breakfast_veg', 'breakfast', 'veg', 30.00, 1, '2025-09-19 05:10:58', '2025-09-19 13:31:50'),
(36, 'Vegetable Sandwich', NULL, 'breakfast_veg', 'breakfast', 'veg', 35.00, 1, '2025-09-19 05:10:58', '2025-09-19 09:36:28'),
(37, 'Chicken Curry', '', 'breakfast_nonveg', 'breakfast', 'non_veg', 70.00, 1, '2025-09-19 05:10:58', '2025-09-19 13:33:34'),
(38, 'Egg Curry', '', 'breakfast_nonveg', 'breakfast', 'non_veg', 55.00, 1, '2025-09-19 05:10:58', '2025-09-19 13:33:18'),
(39, 'Chicken Omelet', '', 'breakfast_nonveg', 'breakfast', 'non_veg', 50.00, 1, '2025-09-19 05:10:58', '2025-09-19 13:32:12'),
(40, 'Dal', '', 'breakfast_nonveg', 'all', 'veg', 60.00, 1, '2025-09-19 05:10:58', '2025-09-19 13:35:27'),
(41, 'Meat Curry', '', 'breakfast_nonveg', 'breakfast', 'non_veg', 65.00, 1, '2025-09-19 05:10:58', '2025-09-19 13:34:12'),
(42, 'Egg Sandwich', NULL, 'breakfast_nonveg', 'breakfast', 'non_veg', 40.00, 1, '2025-09-19 05:10:58', '2025-09-19 09:36:28'),
(43, 'Tea/Coffee', NULL, 'breakfast_item', 'drinks', 'veg', 10.00, 1, '2025-09-19 05:10:58', '2025-09-19 09:36:28'),
(44, 'Milk', '', 'drinks', 'breakfast', 'veg', 15.00, 1, '2025-09-19 05:10:58', '2025-09-19 13:34:36'),
(45, 'Banana', NULL, 'breakfast_item', 'breakfast', 'veg', 8.00, 1, '2025-09-19 05:10:58', '2025-09-19 09:36:28'),
(46, 'Biscuits', NULL, 'breakfast_item', 'breakfast', 'veg', 12.00, 1, '2025-09-19 05:10:58', '2025-09-19 09:36:28'),
(47, 'Toast', NULL, 'breakfast_item', 'breakfast', 'veg', 15.00, 1, '2025-09-19 05:10:58', '2025-09-19 09:36:28'),
(48, 'Butter', NULL, 'breakfast_item', 'breakfast', 'veg', 5.00, 1, '2025-09-19 05:10:58', '2025-09-19 09:36:28'),
(49, 'Jam', NULL, 'breakfast_item', 'breakfast', 'veg', 8.00, 1, '2025-09-19 05:10:58', '2025-09-19 09:36:28'),
(50, 'Honey', NULL, 'breakfast_item', 'breakfast', 'veg', 12.00, 1, '2025-09-19 05:10:58', '2025-09-19 09:36:28'),
(51, 'Tea', 'Tea leaves, Sugar, Milk', 'drinks', 'drinks', 'veg', 10.00, 1, '2025-09-19 09:39:23', '2025-09-19 09:39:23'),
(52, 'Coffee', 'Coffee beans, Sugar, Milk', 'drinks', 'drinks', 'veg', 15.00, 1, '2025-09-19 09:39:23', '2025-09-19 09:39:23'),
(53, 'Orange Juice', 'Fresh Orange', 'drinks', 'drinks', 'veg', 25.00, 1, '2025-09-19 09:39:23', '2025-09-19 09:39:23'),
(54, 'Lemon Water', 'Lemon, Water, Sugar', 'drinks', 'drinks', 'veg', 8.00, 1, '2025-09-19 09:39:23', '2025-09-19 09:39:23'),
(55, 'Milk', 'Fresh Milk', 'drinks', 'drinks', 'veg', 12.00, 1, '2025-09-19 09:39:23', '2025-09-19 09:39:23'),
(56, 'Rice', '', 'protein', 'lunch_dinner', 'non_veg', 200.00, 1, '2025-09-19 10:09:17', '2025-09-19 10:21:45'),
(57, 'Chicken Curry', 'Chicken, Onion, Garlic, Ginger, Spices', 'protein', 'lunch_dinner', 'non_veg', 0.00, 1, '2025-09-19 10:16:29', '2025-09-19 10:16:29'),
(59, 'Mixed Vegetable Curry', 'Mixed Vegetables, Spices', 'vegetable', 'lunch_dinner', 'veg', 0.00, 1, '2025-09-19 10:16:29', '2025-09-19 10:16:29'),
(60, 'lish Fish Curry', 'Fish, Onion, Tomato, Spices', 'fish', 'lunch_dinner', 'non_veg', 0.00, 1, '2025-09-19 10:16:29', '2025-09-19 13:38:11'),
(61, 'Aloo Curry', 'Potato, Cauliflower, Spices', 'vegetable', 'lunch_dinner', 'veg', 0.00, 1, '2025-09-19 10:16:29', '2025-09-19 13:35:53'),
(62, 'Rui Fish Curry', '', 'fish', 'lunch_dinner', 'non_veg', 0.00, 1, '2025-09-19 13:37:24', '2025-09-19 13:37:24');

-- --------------------------------------------------------

--
-- Table structure for table `meal_plans`
--

CREATE TABLE `meal_plans` (
  `id` int(11) NOT NULL,
  `resident_id` int(11) DEFAULT NULL,
  `doctor_id` int(11) DEFAULT NULL,
  `meal_type` enum('Breakfast','Lunch','Dinner','Snack') DEFAULT NULL,
  `recommended_items` text DEFAULT NULL,
  `dietary_restrictions` text DEFAULT NULL,
  `calories_target` int(11) DEFAULT NULL,
  `valid_from` date DEFAULT NULL,
  `valid_to` date DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `meal_plans`
--

INSERT INTO `meal_plans` (`id`, `resident_id`, `doctor_id`, `meal_type`, `recommended_items`, `dietary_restrictions`, `calories_target`, `valid_from`, `valid_to`, `is_active`, `created_at`) VALUES
(1, 1, 1, 'Breakfast', 'Oatmeal, fruits, low-fat milk, whole grain toast', 'Low sodium, diabetic-friendly', 400, '2024-01-01', '2024-03-31', 1, '2025-08-11 08:59:04'),
(2, 1, 1, 'Lunch', 'Grilled fish, steamed vegetables, brown rice, salad', 'Low sodium, diabetic-friendly', 600, '2024-01-01', '2024-03-31', 1, '2025-08-11 08:59:04'),
(3, 1, 1, 'Dinner', 'Lean meat, vegetables, whole grain bread', 'Low sodium, diabetic-friendly', 500, '2024-01-01', '2024-03-31', 1, '2025-08-11 08:59:04'),
(4, 2, 1, 'Breakfast', 'Eggs, whole grain toast, fruits, yogurt', 'Regular diet', 450, '2024-01-01', '2024-03-31', 1, '2025-08-11 08:59:04'),
(5, 2, 1, 'Lunch', 'Chicken curry, rice, vegetables, lentils', 'Regular diet', 650, '2024-01-01', '2024-03-31', 1, '2025-08-11 08:59:04'),
(6, 2, 1, 'Dinner', 'Fish, vegetables, rice, fruit', 'Regular diet', 550, '2024-01-01', '2024-03-31', 1, '2025-08-11 08:59:04');

-- --------------------------------------------------------

--
-- Table structure for table `meal_preferences`
--

CREATE TABLE `meal_preferences` (
  `id` int(11) NOT NULL,
  `resident_id` int(11) NOT NULL,
  `meal_date` date NOT NULL,
  `meal_type` enum('Breakfast','Lunch','Dinner') NOT NULL,
  `dietary_preference` enum('Vegetarian','Non-Vegetarian') DEFAULT 'Vegetarian',
  `spice_level` enum('Spicy','Non Spicy','Medium') DEFAULT 'Medium',
  `oil_preference` enum('Less oily','Normal','Extra oily') DEFAULT 'Normal',
  `special_notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `meal_preferences`
--

INSERT INTO `meal_preferences` (`id`, `resident_id`, `meal_date`, `meal_type`, `dietary_preference`, `spice_level`, `oil_preference`, `special_notes`, `created_at`, `updated_at`) VALUES
(1, 1, '2025-09-20', 'Breakfast', 'Vegetarian', 'Non Spicy', 'Less oily', 'Prefer light breakfast', '2025-09-20 15:44:36', '2025-09-20 15:44:36'),
(2, 1, '2025-09-20', 'Lunch', 'Vegetarian', 'Spicy', 'Normal', 'Love spicy food', '2025-09-20 15:44:36', '2025-09-20 15:44:36'),
(3, 1, '2025-09-20', 'Dinner', 'Vegetarian', 'Medium', 'Less oily', 'Light dinner preferred', '2025-09-20 15:44:36', '2025-09-20 15:44:36'),
(4, 2, '2025-09-20', 'Breakfast', 'Vegetarian', 'Spicy', 'Normal', NULL, '2025-09-20 15:44:36', '2025-09-20 15:44:36'),
(5, 2, '2025-09-20', 'Lunch', 'Vegetarian', 'Spicy', 'Extra oily', 'Extra spicy please', '2025-09-20 15:44:36', '2025-09-20 15:44:36'),
(6, 2, '2025-09-20', 'Dinner', 'Vegetarian', 'Non Spicy', 'Less oily', 'No spice in dinner', '2025-09-20 15:44:36', '2025-09-20 15:44:36'),
(7, 7, '2025-09-20', 'Breakfast', 'Non-Vegetarian', 'Spicy', 'Extra oily', NULL, '2025-09-20 16:10:01', '2025-09-20 17:09:29'),
(8, 7, '2025-09-20', 'Lunch', NULL, NULL, 'Normal', NULL, '2025-09-20 16:10:01', '2025-09-20 17:09:29'),
(9, 7, '2025-09-20', 'Dinner', NULL, NULL, 'Normal', NULL, '2025-09-20 16:10:01', '2025-09-20 17:09:29'),
(10, 7, '2025-09-21', 'Breakfast', NULL, NULL, 'Normal', NULL, '2025-09-20 16:10:01', '2025-09-20 17:09:29'),
(11, 7, '2025-09-21', 'Lunch', NULL, NULL, 'Normal', NULL, '2025-09-20 16:10:01', '2025-09-20 17:09:29'),
(12, 7, '2025-09-21', 'Dinner', NULL, NULL, 'Normal', NULL, '2025-09-20 16:10:01', '2025-09-20 17:09:29');

-- --------------------------------------------------------

--
-- Table structure for table `mental_health_reports`
--

CREATE TABLE `mental_health_reports` (
  `id` int(11) NOT NULL,
  `resident_id` int(11) DEFAULT NULL,
  `doctor_id` int(11) DEFAULT NULL,
  `report_date` date DEFAULT NULL,
  `mental_score` int(11) DEFAULT NULL,
  `mood_assessment` text DEFAULT NULL,
  `behavioral_notes` text DEFAULT NULL,
  `recommendations` text DEFAULT NULL,
  `next_session_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `mental_health_reports`
--

INSERT INTO `mental_health_reports` (`id`, `resident_id`, `doctor_id`, `report_date`, `mental_score`, `mood_assessment`, `behavioral_notes`, `recommendations`, `next_session_date`, `created_at`) VALUES
(1, 1, 1, '2024-01-07', 80, 'Generally positive mood, occasional anxiety about family', 'Engages well in activities, prefers reading', 'Continue social activities, consider family video calls', '2024-02-07', '2025-08-11 08:59:04'),
(2, 2, 1, '2024-01-09', 85, 'Cheerful and sociable, enjoys group activities', 'Very active in community events, helps other residents', 'Maintain current activity level', '2024-02-09', '2025-08-11 08:59:04'),
(3, 3, 1, '2024-01-12', 75, 'Mild depression, worries about health condition', 'Withdrawn at times, needs encouragement', 'Increase social interaction, consider counseling', '2024-02-12', '2025-08-11 08:59:04');

-- --------------------------------------------------------

--
-- Table structure for table `messages`
--

CREATE TABLE `messages` (
  `id` int(11) NOT NULL,
  `sender_id` int(11) DEFAULT NULL,
  `receiver_id` int(11) DEFAULT NULL,
  `subject` varchar(100) DEFAULT NULL,
  `message_body` text DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `parent_message_id` int(11) DEFAULT NULL,
  `sent_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `messages`
--

INSERT INTO `messages` (`id`, `sender_id`, `receiver_id`, `subject`, `message_body`, `is_read`, `parent_message_id`, `sent_at`) VALUES
(1, 5, 2, 'Medication Query', 'Doctor, I have been experiencing some side effects from the new medication. Could we discuss this?', 0, NULL, '2024-01-14 04:30:00'),
(2, 2, 5, 'Re: Medication Query', 'Please schedule an appointment through the system. We will review your medication and make necessary adjustments.', 1, NULL, '2024-01-14 08:15:00'),
(3, 6, 1, 'Room Maintenance Request', 'There is a minor issue with the bathroom faucet in my room. Could someone look into it?', 0, NULL, '2024-01-13 10:45:00');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `title` varchar(100) DEFAULT NULL,
  `message` text DEFAULT NULL,
  `type` enum('Info','Warning','Error','Success') DEFAULT 'Info',
  `is_read` tinyint(1) DEFAULT 0,
  `action_url` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `user_id`, `title`, `message`, `type`, `is_read`, `action_url`, `created_at`) VALUES
(1, 5, 'Health Checkup Reminder', 'Your monthly health checkup is scheduled for tomorrow at 10:00 AM', 'Info', 0, 'health.php', '2025-08-11 08:59:04'),
(2, 5, 'Medicine Reminder', 'Time to take your evening medication', 'Warning', 0, NULL, '2025-08-11 08:59:04'),
(3, 6, 'Payment Due', 'Your monthly payment is due in 5 days', 'Warning', 0, 'billing.php', '2025-08-11 08:59:04'),
(4, 7, 'Family Call Scheduled', 'Your family video call is scheduled for Jan 20, 3:00 PM', 'Success', 0, 'communication.php', '2025-08-11 08:59:04'),
(5, 5, 'Meal Preference', 'Please select your meal preferences for next week', 'Info', 0, 'meals.php', '2025-08-11 08:59:04'),
(6, 14, 'New Service Request', 'New Laundry request from Room 102', 'Info', 0, 'tasks.php', '2025-09-18 18:01:58'),
(7, 17, 'Service Request Submitted', 'Your Laundry request has been submitted and assigned to staff.', 'Success', 0, 'services.php', '2025-09-18 18:01:58'),
(8, 14, 'New Service Request', 'New Room Cleaning request from Room 102', 'Info', 0, 'tasks.php', '2025-09-18 18:02:25'),
(9, 17, 'Service Request Submitted', 'Your Room Cleaning request has been submitted and assigned to staff.', 'Success', 0, 'services.php', '2025-09-18 18:02:25'),
(10, 15, 'New Service Request', 'New Room Cleaning request from Room 102', 'Info', 0, 'tasks.php', '2025-09-19 03:50:56'),
(11, 17, 'Service Request Submitted', 'Your Room Cleaning request has been submitted and assigned to staff.', 'Success', 0, 'services.php', '2025-09-19 03:50:56');

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `id` int(11) NOT NULL,
  `resident_id` int(11) DEFAULT NULL,
  `plan_id` int(11) DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_date` date NOT NULL,
  `payment_method` varchar(50) DEFAULT NULL,
  `transaction_id` varchar(100) DEFAULT NULL,
  `payment_type` enum('admission','monthly','renewal') DEFAULT 'monthly',
  `status` enum('paid','pending','overdue') DEFAULT 'paid',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payment_plans`
--

CREATE TABLE `payment_plans` (
  `id` int(11) NOT NULL,
  `plan_name` varchar(50) NOT NULL,
  `monthly_fee` decimal(10,2) DEFAULT 0.00,
  `yearly_fee` decimal(10,2) DEFAULT 0.00,
  `laundry_limit` int(11) DEFAULT 0,
  `cleaning_limit` int(11) DEFAULT 0,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payment_plans`
--

INSERT INTO `payment_plans` (`id`, `plan_name`, `monthly_fee`, `yearly_fee`, `laundry_limit`, `cleaning_limit`, `description`, `created_at`) VALUES
(1, 'Basic Plan', 0.00, 0.00, 0, 10, 'Free basic accommodation with limited services', '2025-08-11 08:52:50'),
(2, 'Plan 1', 15000.00, 170000.00, 50, 20, 'Enhanced room with moderate service limits', '2025-08-11 08:52:50'),
(3, 'Plan 2', 25000.00, 280000.00, 100, 25, 'Premium room with good service limits', '2025-08-11 08:52:50'),
(4, 'Plan 3', 35000.00, 390000.00, 150, 30, 'Deluxe room with extended services', '2025-08-11 08:52:50'),
(5, 'Plan 4', 50000.00, 550000.00, -1, -1, 'Suite accommodation with unlimited services', '2025-08-11 08:52:50');

-- --------------------------------------------------------

--
-- Table structure for table `plans`
--

CREATE TABLE `plans` (
  `id` int(11) NOT NULL,
  `plan_name` varchar(255) NOT NULL,
  `monthly_fee` decimal(10,2) NOT NULL,
  `description` text DEFAULT NULL,
  `features` text DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `residents`
--

CREATE TABLE `residents` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `room_number` varchar(10) DEFAULT NULL,
  `plan_id` int(11) DEFAULT NULL,
  `admission_date` date DEFAULT NULL,
  `medical_conditions` text DEFAULT NULL,
  `allergies` text DEFAULT NULL,
  `family_contact_info` text DEFAULT NULL,
  `payment_status` enum('Paid','Pending','Overdue') DEFAULT 'Pending',
  `last_payment_date` date DEFAULT NULL,
  `next_payment_due` date DEFAULT NULL,
  `health_score` int(11) DEFAULT 70,
  `mental_health_score` int(11) DEFAULT 70,
  `laundry_usage_current_month` int(11) DEFAULT 0,
  `cleaning_usage_current_month` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `residents`
--

INSERT INTO `residents` (`id`, `user_id`, `room_number`, `plan_id`, `admission_date`, `medical_conditions`, `allergies`, `family_contact_info`, `payment_status`, `last_payment_date`, `next_payment_due`, `health_score`, `mental_health_score`, `laundry_usage_current_month`, `cleaning_usage_current_month`, `created_at`, `updated_at`) VALUES
(1, 5, '101', 3, '2023-06-01', 'Diabetes, Hypertension', 'Peanuts', 'Son: Ahmed Karim, Phone: +880 4444 666777', 'Paid', '2024-01-01', '2025-10-18', 75, 80, 45, 12, '2025-08-11 08:59:04', '2025-09-18 12:46:28'),
(2, 6, '104', 2, '2023-08-15', 'Arthritis', 'None known', 'Daughter: Nasreen, Phone: +880 5555 777888', 'Paid', '2024-01-01', '2025-10-18', 70, 85, 85, 20, '2025-08-11 08:59:04', '2025-09-18 12:26:17'),
(3, 7, '201', 1, '2023-10-10', 'Heart condition', 'Shellfish', 'Son: Rafiq Ahmed, Phone: +880 6666 888999', 'Paid', NULL, '2024-01-15', 65, 75, 35, 15, '2025-08-11 08:59:04', '2025-09-18 03:16:21'),
(5, 13, '302', 2, '2025-09-17', 'Good', 'None', 'Tahmid - 01899342167', 'Paid', NULL, '2025-10-18', 70, 70, 0, 0, '2025-09-17 15:02:56', '2025-09-18 03:43:30'),
(6, 16, '206', 5, '2025-09-18', 'Good', 'None', '', 'Paid', NULL, '2025-10-18', 70, 70, 0, 0, '2025-09-18 03:18:35', '2025-09-18 12:37:51'),
(7, 17, '102', 1, '2025-09-18', '', '', '', 'Paid', NULL, '2025-10-18', 70, 70, 0, 0, '2025-09-18 12:23:23', '2025-09-18 12:26:12');

-- --------------------------------------------------------

--
-- Table structure for table `resident_revenue_history`
--

CREATE TABLE `resident_revenue_history` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `resident_id` int(11) NOT NULL,
  `plan_id` int(11) NOT NULL,
  `transaction_id` varchar(50) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_type` enum('initial','renewal','upgrade','downgrade') DEFAULT 'initial',
  `payment_status` enum('paid','pending','overdue','cancelled') DEFAULT 'paid',
  `payment_date` date NOT NULL,
  `subscription_start_date` date NOT NULL,
  `subscription_end_date` date NOT NULL,
  `renewal_due_date` date NOT NULL,
  `grace_period_end` date NOT NULL,
  `payment_method` varchar(50) DEFAULT 'cash',
  `processed_by` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `resident_revenue_history`
--

INSERT INTO `resident_revenue_history` (`id`, `user_id`, `resident_id`, `plan_id`, `transaction_id`, `amount`, `payment_type`, `payment_status`, `payment_date`, `subscription_start_date`, `subscription_end_date`, `renewal_due_date`, `grace_period_end`, `payment_method`, `processed_by`, `notes`, `is_active`, `created_at`, `updated_at`) VALUES
(5, 16, 6, 5, 'TXN_2025_39597', 50000.00, 'initial', 'paid', '2025-08-16', '2025-08-16', '2025-09-16', '2025-09-18', '2025-09-20', 'cash', 12, 'Initial payment on admission', 0, '2025-09-18 03:18:35', '2025-09-18 03:43:11'),
(10, 16, 6, 5, 'TXN_2025_93408', 50000.00, 'upgrade', 'paid', '2025-09-18', '2025-09-18', '2025-10-18', '2025-10-18', '2025-10-20', 'cash', 12, 'Plan upgraded and renewed', 1, '2025-09-18 03:43:11', '2025-09-18 03:43:11'),
(11, 13, 5, 2, 'TXN_2025_39373', 15000.00, 'renewal', 'paid', '2025-09-18', '2025-09-18', '2025-10-18', '2025-10-18', '2025-10-20', 'cash', 12, 'Monthly subscription renewed', 1, '2025-09-18 03:43:30', '2025-09-18 03:43:30'),
(12, 6, 2, 2, 'TXN_2025_50412', 15000.00, 'renewal', 'paid', '2025-09-18', '2025-09-18', '2025-10-18', '2025-10-18', '2025-10-20', 'cash', 12, 'Monthly subscription renewed', 1, '2025-09-18 03:43:40', '2025-09-18 03:43:40'),
(13, 5, 1, 3, 'TXN_2025_69049', 25000.00, 'renewal', 'paid', '2025-09-18', '2025-09-18', '2025-10-18', '2025-10-18', '2025-10-20', 'cash', 12, 'Monthly subscription renewed', 1, '2025-09-18 12:46:28', '2025-09-18 12:46:28');

-- --------------------------------------------------------

--
-- Table structure for table `services`
--

CREATE TABLE `services` (
  `id` int(11) NOT NULL,
  `service_name` varchar(50) NOT NULL,
  `base_cost` decimal(8,2) DEFAULT 0.00,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `services`
--

INSERT INTO `services` (`id`, `service_name`, `base_cost`, `description`, `is_active`, `created_at`) VALUES
(1, 'Laundry', 5.00, 'Laundry service per item', 1, '2025-08-11 08:52:50'),
(2, 'Room Cleaning', 100.00, 'Room cleaning service', 1, '2025-08-11 08:52:50'),
(3, 'Transportation', 200.00, 'Transportation service', 1, '2025-08-11 08:52:50'),
(4, 'Medical Consultation', 500.00, 'Doctor consultation', 1, '2025-08-11 08:52:50'),
(5, 'Physiotherapy', 300.00, 'Physiotherapy session', 1, '2025-08-11 08:52:50'),
(6, 'Emergency Care', 1000.00, 'Emergency medical care', 1, '2025-08-11 08:52:50');

-- --------------------------------------------------------

--
-- Table structure for table `service_requests`
--

CREATE TABLE `service_requests` (
  `id` int(11) NOT NULL,
  `resident_id` int(11) DEFAULT NULL,
  `service_id` int(11) DEFAULT NULL,
  `request_date` date DEFAULT NULL,
  `scheduled_date` date DEFAULT NULL,
  `assigned_staff_id` int(11) DEFAULT NULL,
  `status` enum('Requested','Scheduled','In Progress','Completed','Cancelled') DEFAULT 'Requested',
  `cost` decimal(8,2) DEFAULT 0.00,
  `notes` text DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `service_requests`
--

INSERT INTO `service_requests` (`id`, `resident_id`, `service_id`, `request_date`, `scheduled_date`, `assigned_staff_id`, `status`, `cost`, `notes`, `completed_at`, `created_at`) VALUES
(1, 1, 1, '2024-01-10', '2024-01-11', 1, 'Completed', 0.00, 'Regular laundry service - within plan limit', NULL, '2025-08-11 08:59:04'),
(2, 1, 2, '2024-01-12', '2024-01-13', 1, 'Completed', 0.00, 'Room cleaning - within plan limit', NULL, '2025-08-11 08:59:04'),
(3, 2, 1, '2024-01-14', '2024-01-15', 1, 'Completed', 5.00, 'Extra laundry service - exceeds plan limit', NULL, '2025-08-11 08:59:04'),
(4, 3, 3, '2024-01-13', '2024-01-16', 1, 'Completed', 200.00, 'Transportation to hospital for checkup', NULL, '2025-08-11 08:59:04'),
(5, 7, 1, '2025-09-19', '2025-09-20', 2, 'Completed', 5.00, '', NULL, '2025-09-18 18:01:58'),
(6, 7, 2, '2025-09-19', '2025-09-21', 2, 'Completed', 0.00, '', NULL, '2025-09-18 18:02:25'),
(7, 7, 2, '2025-09-19', '2025-09-20', 3, 'Completed', 0.00, '', NULL, '2025-09-19 03:50:56');

-- --------------------------------------------------------

--
-- Table structure for table `staff`
--

CREATE TABLE `staff` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `employee_id` varchar(20) DEFAULT NULL,
  `department` varchar(50) DEFAULT NULL,
  `position` varchar(50) DEFAULT NULL,
  `salary` decimal(10,2) DEFAULT NULL,
  `hire_date` date DEFAULT NULL,
  `shift_hours` varchar(50) DEFAULT NULL,
  `is_available` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `staff`
--

INSERT INTO `staff` (`id`, `user_id`, `employee_id`, `department`, `position`, `salary`, `hire_date`, `shift_hours`, `is_available`, `created_at`) VALUES
(1, 4, 'EMP001', 'Housekeeping', 'Care Assistant', 25000.00, '2023-01-15', '8:00 AM - 4:00 PM', 1, '2025-08-11 08:59:04'),
(2, 14, 'EMP002', 'Housekeeping', 'Chef Assist', 30000.00, '2025-09-16', '9 - 5', 1, '2025-09-17 15:15:17'),
(3, 15, 'CHEF015', 'Kitchen', 'Chef - Head Chef', 30000.00, '2025-09-17', '6 am to 4 pm', 1, '2025-09-17 15:31:43');

-- --------------------------------------------------------

--
-- Table structure for table `staff_salaries`
--

CREATE TABLE `staff_salaries` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `salary_month` date NOT NULL,
  `base_salary` decimal(10,2) NOT NULL,
  `overtime_hours` decimal(5,2) DEFAULT 0.00,
  `overtime_rate` decimal(8,2) DEFAULT 0.00,
  `bonus` decimal(10,2) DEFAULT 0.00,
  `deductions` decimal(10,2) DEFAULT 0.00,
  `total_salary` decimal(10,2) NOT NULL,
  `payment_status` enum('pending','paid','partial') DEFAULT 'pending',
  `payment_date` date DEFAULT NULL,
  `payment_method` enum('cash','bank_transfer','cheque') DEFAULT 'bank_transfer',
  `notes` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `staff_salaries`
--

INSERT INTO `staff_salaries` (`id`, `user_id`, `salary_month`, `base_salary`, `overtime_hours`, `overtime_rate`, `bonus`, `deductions`, `total_salary`, `payment_status`, `payment_date`, `payment_method`, `notes`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 2, '2025-09-01', 45000.00, 0.00, 0.00, 0.00, 0.00, 45000.00, 'pending', NULL, 'bank_transfer', 'Auto-renewal on 10th', 12, '2025-09-19 16:20:52', '2025-09-19 16:20:52'),
(2, 4, '2025-09-01', 25000.00, 0.00, 0.00, 0.00, 0.00, 25000.00, 'pending', NULL, 'bank_transfer', 'Auto-renewal on 10th', 12, '2025-09-19 16:20:52', '2025-09-19 16:20:52'),
(3, 14, '2025-09-01', 25000.00, 0.00, 0.00, 0.00, 0.00, 25000.00, 'pending', NULL, 'bank_transfer', 'Auto-renewal on 10th', 12, '2025-09-19 16:20:52', '2025-09-19 16:20:52'),
(4, 15, '2025-09-01', 35000.00, 0.00, 0.00, 0.00, 0.00, 35000.00, 'pending', NULL, 'bank_transfer', 'Auto-renewal on 10th', 12, '2025-09-19 16:20:52', '2025-09-19 16:20:52'),
(5, 21, '2025-09-01', 35000.00, 0.00, 0.00, 0.00, 0.00, 35000.00, 'pending', NULL, 'bank_transfer', 'Auto-renewal on 10th', 12, '2025-09-19 16:20:52', '2025-09-19 16:20:52'),
(6, 22, '2025-09-01', 35000.00, 0.00, 0.00, 0.00, 0.00, 35000.00, 'pending', NULL, 'bank_transfer', 'Auto-renewal on 10th', 12, '2025-09-19 16:57:30', '2025-09-19 16:57:30');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role_id` int(11) DEFAULT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `gender` enum('Male','Female','Other') DEFAULT NULL,
  `emergency_contact_name` varchar(100) DEFAULT NULL,
  `emergency_contact_phone` varchar(20) DEFAULT NULL,
  `family_contact_info` text DEFAULT NULL,
  `profile_image` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `password`, `role_id`, `first_name`, `last_name`, `phone`, `address`, `date_of_birth`, `gender`, `emergency_contact_name`, `emergency_contact_phone`, `family_contact_info`, `profile_image`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'admin', 'admin@maplehouse.com', '$2a$12$ihiurfSC2JXVoqpQWH1i5eUtW7.y3UuEl9.R1lNiGknHQtO0rXUnu', 1, 'System', 'Administrator', '+880 1234 567890', '123 Admin Street, Dhaka', '1980-01-01', 'Male', 'Emergency Admin', '+880 9876 543210', NULL, NULL, 1, '2025-08-11 08:59:04', '2025-08-11 08:59:04'),
(2, 'doctorsarah', 'doctor@maplehouse.com', '$2y$10$BfDWzPbnO3AwNnxEZdHt9.3UgkLEvHeaj5bQpWJlhZK8FOjGUvmH6', 3, 'Sarah', 'Rahman', '+880 1111 222333', '456 Medical Lane, Dhaka', '1975-05-15', 'Female', 'Dr Emergency', '+880 1111 333444', NULL, NULL, 1, '2025-08-11 08:59:04', '2025-09-20 13:32:00'),
(4, 'staff1', 'staff@maplehouse.com', '$2a$12$QyErxF8.h.2/YlJj2oaJEOniCCJ8P.PPmlFoh/parC/USBAl/DEay', 5, 'Fatima', 'Begum', '+880 3333 444555', '321 Staff Road, Dhaka', '1990-07-10', 'Female', 'Staff Emergency', '+880 3333 555666', NULL, NULL, 1, '2025-08-11 08:59:04', '2025-08-11 08:59:04'),
(5, 'resident1', 'resident1@email.com', '$2a$12$vVN6.FZfw6dJ.jG3fOL..OnXQPc7HmJLKn9EVf3gfTEzp3Z7Nyo1e', 6, 'Abdul', 'Karim', '+880 4444 555666', '654 Old Street, Dhaka', '1945-12-05', 'Male', 'Son Ahmed Karim', '+880 4444 666777', NULL, NULL, 1, '2025-08-11 08:59:04', '2025-09-17 14:34:18'),
(6, 'resident2', 'resident2@email.com', '$2a$12$vVN6.FZfw6dJ.jG3fOL..OnXQPc7HmJLKn9EVf3gfTEzp3Z7Nyo1e', 6, 'Rashida', 'Khatun', '+880 5555 666777', '987 Senior Ave, Dhaka', '1950-08-22', 'Female', 'Daughter Nasreen', '+880 5555 777888', NULL, NULL, 1, '2025-08-11 08:59:04', '2025-08-11 08:59:04'),
(7, 'resident3', 'resident3@email.com', '$2a$12$vVN6.FZfw6dJ.jG3fOL..OnXQPc7HmJLKn9EVf3gfTEzp3Z7Nyo1e', 6, 'Nur', 'Ahmed', '+880 6666 777888', '147 Care Lane, Dhaka', '1948-04-18', 'Male', 'Son Rafiq Ahmed', '+880 6666 888999', NULL, NULL, 1, '2025-08-11 08:59:04', '2025-08-11 08:59:04'),
(12, 'admin1212', 'wts5nf1gpm@wyoxafp1.com', '$2y$10$hzC20thO4tkujbujlFTe/OQwIFIcI4OHaraKmhii.59Zc0TLJwk2a', 1, 'Alam', 'Hasan', '01726373733', 'dhaka', '1950-06-06', 'Male', 'Son', '01721111111', NULL, NULL, 1, '2025-08-11 09:15:00', '2025-08-11 09:23:09'),
(13, 'Hasan', 'hasanreza@gmail.com', '$2y$10$bES9/jmNMeA3q/jw5uyjeuc77IzY0geV5y0EL/RC9Pd4L9CYNg1DW', 6, 'Hasan', 'Reza', '01812429422', 'Rangpur', '1954-02-03', 'Male', 'Abdul', '01723446476', NULL, NULL, 1, '2025-09-17 15:02:56', '2025-09-17 16:56:49'),
(14, 'rabiulhasan', 'rabiulhasan2@gmail.com', '$2y$10$fhQQ4PrQse8bh.6CxEernO97WVmhaU5GdJwwSxGmY6LP.9.5UqC2i', 5, 'Rabiul', 'Hasan', '01726373732', 'Dhaka', '1975-02-03', 'Male', '', '', NULL, NULL, 1, '2025-09-17 15:15:17', '2025-09-17 15:15:17'),
(15, 'chefjobbar', 'chefjobbar@gmail.com', '$2y$10$8mxcHdRGeqQd4j1.QmMtSeHa8.Com/9MRbnl8e08gu9Yn61/XKM.C', 4, 'Jobbar', 'Ahmed', '01726373883', 'Dhaka', '1979-07-12', 'Male', '', '', NULL, NULL, 1, '2025-09-17 15:31:43', '2025-09-19 05:57:08'),
(16, 'abidsayed', 'abidsayed@gmail.com', '$2y$10$/1n/hmv7c96f7Mr0ccFql.KsrlihBGNiGIZyEOEoJmUNITHQ07IVC', 6, 'Syed', 'Abid', '01726373899', 'Dhaka', '1961-07-06', 'Male', 'Raju', '01723446445', NULL, NULL, 1, '2025-09-18 03:18:35', '2025-09-18 03:18:35'),
(17, 'jubair', 'jub@gmail.com', '$2y$10$5NSv9jDL0TNykj0bsJaZj.wFcT5DbuCv0vmDFbYiSQOaLY4xu9v7e', 6, 'Syed', 'Jubair', '01812429422', '', '1981-02-17', 'Male', '', '', NULL, NULL, 1, '2025-09-18 12:23:23', '2025-09-18 12:23:23'),
(21, 'chefrobin', 'chefrobin@gmail.com', '$2y$10$WG3Jd00CmJMWPm3ERwjwHu76eP6Vw0wPrFZ0KSUJYb4fuSpkECcTq', 4, 'Robin', 'Rauf', '01798912677', '', '1987-07-19', 'Male', '', '', NULL, NULL, 1, '2025-09-19 05:54:21', '2025-09-19 05:54:21'),
(22, 'chefhashem', 'chefhashem@gmail.com', '$2y$10$Y4LMVy5OHZILnNkfbikFlOwsi0AYLzzrI2dVIfce1wq8gzWY2lK6a', 4, 'Hashem', 'Rabbi', '01361278233', '', '1966-07-20', 'Male', '', '', NULL, NULL, 1, '2025-09-19 16:54:26', '2025-09-19 16:54:26');

-- --------------------------------------------------------

--
-- Table structure for table `user_roles`
--

CREATE TABLE `user_roles` (
  `id` int(11) NOT NULL,
  `role_name` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_roles`
--

INSERT INTO `user_roles` (`id`, `role_name`, `description`, `created_at`) VALUES
(1, 'Admin', 'Full system access and management', '2025-08-11 08:52:50'),
(2, 'Manager', 'Administrative access with some restrictions', '2025-08-11 08:52:50'),
(3, 'Doctor', 'Medical staff with health record access', '2025-08-11 08:52:50'),
(4, 'Chef', 'Kitchen staff with meal planning access', '2025-08-11 08:52:50'),
(5, 'Staff', 'General staff with assigned task access', '2025-08-11 08:52:50'),
(6, 'Resident', 'Resident with personal dashboard access', '2025-08-11 08:52:50');

-- --------------------------------------------------------

--
-- Table structure for table `utility_bills`
--

CREATE TABLE `utility_bills` (
  `id` int(11) NOT NULL,
  `utility_type` enum('electricity','water','gas','internet','phone','cable','waste','security','other') NOT NULL,
  `provider_name` varchar(255) NOT NULL,
  `account_number` varchar(100) DEFAULT NULL,
  `bill_month` date NOT NULL,
  `previous_reading` decimal(10,2) DEFAULT 0.00,
  `current_reading` decimal(10,2) DEFAULT 0.00,
  `units_consumed` decimal(10,2) DEFAULT 0.00,
  `rate_per_unit` decimal(8,4) DEFAULT 0.0000,
  `fixed_charges` decimal(10,2) DEFAULT 0.00,
  `total_amount` decimal(10,2) NOT NULL,
  `due_date` date NOT NULL,
  `payment_status` enum('pending','paid','overdue') DEFAULT 'pending',
  `payment_date` date DEFAULT NULL,
  `late_fee` decimal(10,2) DEFAULT 0.00,
  `bill_document` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `utility_bills`
--

INSERT INTO `utility_bills` (`id`, `utility_type`, `provider_name`, `account_number`, `bill_month`, `previous_reading`, `current_reading`, `units_consumed`, `rate_per_unit`, `fixed_charges`, `total_amount`, `due_date`, `payment_status`, `payment_date`, `late_fee`, `bill_document`, `notes`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'electricity', 'DESCO', 'ELC001234', '2024-02-01', 2320.00, 2450.50, 130.50, 8.5000, 500.00, 1609.25, '2024-02-25', 'paid', NULL, 0.00, NULL, NULL, 1, '2025-09-19 16:00:19', '2025-09-19 16:00:19'),
(2, 'water', 'WASA', 'WTR005678', '2024-02-01', 1780.00, 1850.00, 70.00, 12.0000, 200.00, 1040.00, '2024-02-28', 'paid', NULL, 0.00, NULL, NULL, 1, '2025-09-19 16:00:19', '2025-09-19 16:00:19'),
(3, 'gas', 'Titas Gas', 'GAS009876', '2024-02-01', 850.00, 890.50, 40.50, 18.5000, 150.00, 899.25, '2024-02-20', 'paid', NULL, 0.00, NULL, NULL, 1, '2025-09-19 16:00:19', '2025-09-19 16:00:19'),
(4, 'internet', 'Grameenphone', 'INT123456', '2024-02-01', 0.00, 0.00, 0.00, 0.0000, 2500.00, 2500.00, '2024-02-15', 'paid', NULL, 0.00, NULL, NULL, 1, '2025-09-19 16:00:19', '2025-09-19 16:00:19');

-- --------------------------------------------------------

--
-- Table structure for table `video_calls`
--

CREATE TABLE `video_calls` (
  `id` int(11) NOT NULL,
  `resident_id` int(11) DEFAULT NULL,
  `family_contact_name` varchar(100) DEFAULT NULL,
  `family_contact_email` varchar(100) DEFAULT NULL,
  `scheduled_datetime` datetime DEFAULT NULL,
  `duration_minutes` int(11) DEFAULT 30,
  `call_link` varchar(255) DEFAULT NULL,
  `status` enum('Scheduled','Completed','Cancelled','No Show') DEFAULT 'Scheduled',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `video_calls`
--

INSERT INTO `video_calls` (`id`, `resident_id`, `family_contact_name`, `family_contact_email`, `scheduled_datetime`, `duration_minutes`, `call_link`, `status`, `notes`, `created_at`) VALUES
(1, 1, 'Ahmed Karim', 'ahmed.son@email.com', '2024-01-20 15:00:00', 30, 'https://meet.example.com/room123', 'Scheduled', 'Weekly family call', '2025-08-11 08:59:04'),
(2, 2, 'Nasreen Rahman', 'nasreen@email.com', '2024-01-22 14:00:00', 45, 'https://meet.example.com/room124', 'Scheduled', 'Monthly family meeting', '2025-08-11 08:59:04'),
(3, 3, 'Rafiq Ahmed', 'rafiq@email.com', '2024-01-18 16:00:00', 30, 'https://meet.example.com/room125', 'Completed', 'Health update discussion', '2025-08-11 08:59:04');

-- --------------------------------------------------------

--
-- Structure for view `chef_daily_assignments`
--
DROP TABLE IF EXISTS `chef_daily_assignments`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `chef_daily_assignments`  AS SELECT `dm`.`id` AS `daily_meal_id`, `dm`.`meal_date` AS `meal_date`, `dm`.`meal_type` AS `meal_type`, CASE WHEN `dm`.`meal_type` = 'Breakfast' THEN `dm`.`breakfast_chef_id` WHEN `dm`.`meal_type` = 'Lunch' THEN `dm`.`lunch_chef_id` WHEN `dm`.`meal_type` = 'Dinner' THEN `dm`.`dinner_chef_id` END AS `assigned_chef_id`, CASE WHEN `dm`.`meal_type` = 'Breakfast' THEN concat(`bu`.`first_name`,' ',`bu`.`last_name`) WHEN `dm`.`meal_type` = 'Lunch' THEN concat(`lu`.`first_name`,' ',`lu`.`last_name`) WHEN `dm`.`meal_type` = 'Dinner' THEN concat(`du`.`first_name`,' ',`du`.`last_name`) END AS `chef_name`, CASE WHEN `dm`.`meal_type` = 'Breakfast' THEN `bc`.`specialization` WHEN `dm`.`meal_type` = 'Lunch' THEN `lc`.`specialization` WHEN `dm`.`meal_type` = 'Dinner' THEN `dc`.`specialization` END AS `chef_specialization`, count(`r`.`id`) AS `total_residents`, count(case when `mp`.`spice_level` = 'Spicy' then 1 end) AS `spicy_count`, count(case when `mp`.`spice_level` = 'Non Spicy' then 1 end) AS `non_spicy_count`, count(case when `mp`.`spice_level` = 'Medium' then 1 end) AS `medium_spicy_count`, count(case when `mp`.`oil_preference` = 'Less oily' then 1 end) AS `less_oily_count`, count(case when `mp`.`oil_preference` = 'Normal' then 1 end) AS `normal_oil_count`, count(case when `mp`.`oil_preference` = 'Extra oily' then 1 end) AS `extra_oily_count` FROM ((((((((`daily_meals` `dm` left join `chefs` `bc` on(`dm`.`breakfast_chef_id` = `bc`.`id`)) left join `users` `bu` on(`bc`.`user_id` = `bu`.`id`)) left join `chefs` `lc` on(`dm`.`lunch_chef_id` = `lc`.`id`)) left join `users` `lu` on(`lc`.`user_id` = `lu`.`id`)) left join `chefs` `dc` on(`dm`.`dinner_chef_id` = `dc`.`id`)) left join `users` `du` on(`dc`.`user_id` = `du`.`id`)) left join `residents` `r` on(1 = 1)) left join `meal_preferences` `mp` on(`r`.`id` = `mp`.`resident_id` and `mp`.`meal_date` = `dm`.`meal_date` and `mp`.`meal_type` = `dm`.`meal_type`)) WHERE `dm`.`meal_type` = 'Breakfast' AND `dm`.`breakfast_chef_id` is not null OR `dm`.`meal_type` = 'Lunch' AND `dm`.`lunch_chef_id` is not null OR `dm`.`meal_type` = 'Dinner' AND `dm`.`dinner_chef_id` is not null GROUP BY `dm`.`id`, `dm`.`meal_date`, `dm`.`meal_type`, CASE WHEN `dm`.`meal_type` = 'Breakfast' THEN `dm`.`breakfast_chef_id` WHEN `dm`.`meal_type` = 'Lunch' THEN `dm`.`lunch_chef_id` WHEN `dm`.`meal_type` = 'Dinner' THEN `dm`.`dinner_chef_id` END, CASE WHEN `dm`.`meal_type` = 'Breakfast' THEN concat(`bu`.`first_name`,' ',`bu`.`last_name`) WHEN `dm`.`meal_type` = 'Lunch' THEN concat(`lu`.`first_name`,' ',`lu`.`last_name`) WHEN `dm`.`meal_type` = 'Dinner' THEN concat(`du`.`first_name`,' ',`du`.`last_name`) END, CASE WHEN `dm`.`meal_type` = 'Breakfast' THEN `bc`.`specialization` WHEN `dm`.`meal_type` = 'Lunch' THEN `lc`.`specialization` WHEN `dm`.`meal_type` = 'Dinner' THEN `dc`.`specialization` END ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `announcements`
--
ALTER TABLE `announcements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `chefs`
--
ALTER TABLE `chefs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `chef_cooking_sessions`
--
ALTER TABLE `chef_cooking_sessions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_chef_meal` (`chef_id`,`meal_date`,`meal_type`),
  ADD KEY `idx_cooking_status` (`cooking_status`),
  ADD KEY `idx_chef_cooking_sessions_date_chef` (`meal_date`,`chef_id`);

--
-- Indexes for table `chef_inventory_usage`
--
ALTER TABLE `chef_inventory_usage`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_cooking_session` (`cooking_session_id`),
  ADD KEY `idx_inventory_item` (`inventory_item_id`),
  ADD KEY `idx_chef_inventory_usage_session` (`cooking_session_id`);

--
-- Indexes for table `chef_usage_details`
--
ALTER TABLE `chef_usage_details`
  ADD PRIMARY KEY (`id`),
  ADD KEY `session_id` (`session_id`),
  ADD KEY `item_id` (`item_id`);

--
-- Indexes for table `chef_usage_sessions`
--
ALTER TABLE `chef_usage_sessions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_chef_usage_sessions_chef` (`chef_id`),
  ADD KEY `idx_chef_usage_sessions_date` (`meal_date`);

--
-- Indexes for table `daily_meals`
--
ALTER TABLE `daily_meals`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_meal` (`meal_date`,`meal_type`),
  ADD KEY `prepared_by` (`prepared_by`),
  ADD KEY `idx_breakfast_chef` (`breakfast_chef_id`),
  ADD KEY `idx_lunch_chef` (`lunch_chef_id`),
  ADD KEY `idx_dinner_chef` (`dinner_chef_id`);

--
-- Indexes for table `doctors`
--
ALTER TABLE `doctors`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`),
  ADD UNIQUE KEY `license_number` (`license_number`);

--
-- Indexes for table `donations`
--
ALTER TABLE `donations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `verified_by` (`verified_by`),
  ADD KEY `idx_donations_date` (`donation_date`);

--
-- Indexes for table `expenses`
--
ALTER TABLE `expenses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `approved_by` (`approved_by`),
  ADD KEY `idx_expense_type` (`expense_type`),
  ADD KEY `idx_expense_date` (`expense_date`),
  ADD KEY `idx_reference` (`reference_type`,`reference_id`);

--
-- Indexes for table `financial_transactions`
--
ALTER TABLE `financial_transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `processed_by` (`processed_by`),
  ADD KEY `idx_financial_transactions_date` (`transaction_date`);

--
-- Indexes for table `health_records`
--
ALTER TABLE `health_records`
  ADD PRIMARY KEY (`id`),
  ADD KEY `doctor_id` (`doctor_id`),
  ADD KEY `idx_health_records_resident` (`resident_id`);

--
-- Indexes for table `infrastructure`
--
ALTER TABLE `infrastructure`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_room` (`floor_number`,`room_number`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_type` (`type`),
  ADD KEY `idx_floor` (`floor_number`),
  ADD KEY `idx_occupancy` (`is_occupied`,`is_active`);

--
-- Indexes for table `inventory_categories`
--
ALTER TABLE `inventory_categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `inventory_items`
--
ALTER TABLE `inventory_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `unit_id` (`unit_id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_inventory_items_category` (`category_id`),
  ADD KEY `idx_inventory_items_active` (`is_active`);

--
-- Indexes for table `inventory_transactions`
--
ALTER TABLE `inventory_transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `performed_by` (`performed_by`),
  ADD KEY `idx_inventory_transactions_item` (`item_id`),
  ADD KEY `idx_inventory_transactions_date` (`transaction_date`);

--
-- Indexes for table `inventory_units`
--
ALTER TABLE `inventory_units`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`),
  ADD UNIQUE KEY `abbreviation` (`abbreviation`);

--
-- Indexes for table `meal_items`
--
ALTER TABLE `meal_items`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `meal_plans`
--
ALTER TABLE `meal_plans`
  ADD PRIMARY KEY (`id`),
  ADD KEY `resident_id` (`resident_id`),
  ADD KEY `doctor_id` (`doctor_id`);

--
-- Indexes for table `meal_preferences`
--
ALTER TABLE `meal_preferences`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_resident_meal` (`resident_id`,`meal_date`,`meal_type`),
  ADD KEY `idx_meal_date` (`meal_date`),
  ADD KEY `idx_meal_type` (`meal_type`),
  ADD KEY `idx_meal_preferences_date_type` (`meal_date`,`meal_type`);

--
-- Indexes for table `mental_health_reports`
--
ALTER TABLE `mental_health_reports`
  ADD PRIMARY KEY (`id`),
  ADD KEY `resident_id` (`resident_id`),
  ADD KEY `doctor_id` (`doctor_id`);

--
-- Indexes for table `messages`
--
ALTER TABLE `messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sender_id` (`sender_id`),
  ADD KEY `receiver_id` (`receiver_id`),
  ADD KEY `parent_message_id` (`parent_message_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_notifications_user` (`user_id`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `resident_id` (`resident_id`),
  ADD KEY `plan_id` (`plan_id`);

--
-- Indexes for table `payment_plans`
--
ALTER TABLE `payment_plans`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `plans`
--
ALTER TABLE `plans`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `residents`
--
ALTER TABLE `residents`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`),
  ADD KEY `idx_residents_plan` (`plan_id`);

--
-- Indexes for table `resident_revenue_history`
--
ALTER TABLE `resident_revenue_history`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `transaction_id` (`transaction_id`),
  ADD KEY `processed_by` (`processed_by`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_resident_id` (`resident_id`),
  ADD KEY `idx_plan_id` (`plan_id`),
  ADD KEY `idx_transaction_id` (`transaction_id`),
  ADD KEY `idx_payment_date` (`payment_date`),
  ADD KEY `idx_renewal_due_date` (`renewal_due_date`),
  ADD KEY `idx_grace_period_end` (`grace_period_end`),
  ADD KEY `idx_payment_status` (`payment_status`),
  ADD KEY `idx_is_active` (`is_active`);

--
-- Indexes for table `services`
--
ALTER TABLE `services`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `service_requests`
--
ALTER TABLE `service_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `service_id` (`service_id`),
  ADD KEY `assigned_staff_id` (`assigned_staff_id`),
  ADD KEY `idx_service_requests_resident` (`resident_id`);

--
-- Indexes for table `staff`
--
ALTER TABLE `staff`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`),
  ADD UNIQUE KEY `employee_id` (`employee_id`);

--
-- Indexes for table `staff_salaries`
--
ALTER TABLE `staff_salaries`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_salary_month` (`user_id`,`salary_month`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_salary_month` (`salary_month`),
  ADD KEY `idx_payment_status` (`payment_status`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_users_role` (`role_id`);

--
-- Indexes for table `user_roles`
--
ALTER TABLE `user_roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `role_name` (`role_name`);

--
-- Indexes for table `utility_bills`
--
ALTER TABLE `utility_bills`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_utility_type` (`utility_type`),
  ADD KEY `idx_bill_month` (`bill_month`),
  ADD KEY `idx_due_date` (`due_date`),
  ADD KEY `idx_payment_status` (`payment_status`);

--
-- Indexes for table `video_calls`
--
ALTER TABLE `video_calls`
  ADD PRIMARY KEY (`id`),
  ADD KEY `resident_id` (`resident_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `announcements`
--
ALTER TABLE `announcements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `chefs`
--
ALTER TABLE `chefs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `chef_cooking_sessions`
--
ALTER TABLE `chef_cooking_sessions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `chef_inventory_usage`
--
ALTER TABLE `chef_inventory_usage`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `chef_usage_details`
--
ALTER TABLE `chef_usage_details`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `chef_usage_sessions`
--
ALTER TABLE `chef_usage_sessions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `daily_meals`
--
ALTER TABLE `daily_meals`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=42;

--
-- AUTO_INCREMENT for table `doctors`
--
ALTER TABLE `doctors`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `donations`
--
ALTER TABLE `donations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `expenses`
--
ALTER TABLE `expenses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `financial_transactions`
--
ALTER TABLE `financial_transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `health_records`
--
ALTER TABLE `health_records`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `infrastructure`
--
ALTER TABLE `infrastructure`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=64;

--
-- AUTO_INCREMENT for table `inventory_categories`
--
ALTER TABLE `inventory_categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `inventory_items`
--
ALTER TABLE `inventory_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `inventory_transactions`
--
ALTER TABLE `inventory_transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `inventory_units`
--
ALTER TABLE `inventory_units`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `meal_items`
--
ALTER TABLE `meal_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=63;

--
-- AUTO_INCREMENT for table `meal_plans`
--
ALTER TABLE `meal_plans`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `meal_preferences`
--
ALTER TABLE `meal_preferences`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `mental_health_reports`
--
ALTER TABLE `mental_health_reports`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `messages`
--
ALTER TABLE `messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `payment_plans`
--
ALTER TABLE `payment_plans`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `plans`
--
ALTER TABLE `plans`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `residents`
--
ALTER TABLE `residents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `resident_revenue_history`
--
ALTER TABLE `resident_revenue_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `services`
--
ALTER TABLE `services`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `service_requests`
--
ALTER TABLE `service_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `staff`
--
ALTER TABLE `staff`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `staff_salaries`
--
ALTER TABLE `staff_salaries`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT for table `user_roles`
--
ALTER TABLE `user_roles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `utility_bills`
--
ALTER TABLE `utility_bills`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `video_calls`
--
ALTER TABLE `video_calls`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `announcements`
--
ALTER TABLE `announcements`
  ADD CONSTRAINT `announcements_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `chefs`
--
ALTER TABLE `chefs`
  ADD CONSTRAINT `chefs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `chef_cooking_sessions`
--
ALTER TABLE `chef_cooking_sessions`
  ADD CONSTRAINT `fk_chef_cooking_sessions_chef` FOREIGN KEY (`chef_id`) REFERENCES `chefs` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `chef_inventory_usage`
--
ALTER TABLE `chef_inventory_usage`
  ADD CONSTRAINT `fk_chef_inventory_usage_item` FOREIGN KEY (`inventory_item_id`) REFERENCES `inventory_items` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_chef_inventory_usage_session` FOREIGN KEY (`cooking_session_id`) REFERENCES `chef_cooking_sessions` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `chef_usage_details`
--
ALTER TABLE `chef_usage_details`
  ADD CONSTRAINT `chef_usage_details_ibfk_1` FOREIGN KEY (`session_id`) REFERENCES `chef_usage_sessions` (`id`),
  ADD CONSTRAINT `chef_usage_details_ibfk_2` FOREIGN KEY (`item_id`) REFERENCES `inventory_items` (`id`);

--
-- Constraints for table `chef_usage_sessions`
--
ALTER TABLE `chef_usage_sessions`
  ADD CONSTRAINT `chef_usage_sessions_ibfk_1` FOREIGN KEY (`chef_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `daily_meals`
--
ALTER TABLE `daily_meals`
  ADD CONSTRAINT `daily_meals_breakfast_chef_fk` FOREIGN KEY (`breakfast_chef_id`) REFERENCES `chefs` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `daily_meals_dinner_chef_fk` FOREIGN KEY (`dinner_chef_id`) REFERENCES `chefs` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `daily_meals_ibfk_1` FOREIGN KEY (`prepared_by`) REFERENCES `chefs` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `daily_meals_lunch_chef_fk` FOREIGN KEY (`lunch_chef_id`) REFERENCES `chefs` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `doctors`
--
ALTER TABLE `doctors`
  ADD CONSTRAINT `doctors_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `donations`
--
ALTER TABLE `donations`
  ADD CONSTRAINT `donations_ibfk_1` FOREIGN KEY (`verified_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `expenses`
--
ALTER TABLE `expenses`
  ADD CONSTRAINT `expenses_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `expenses_ibfk_2` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `financial_transactions`
--
ALTER TABLE `financial_transactions`
  ADD CONSTRAINT `financial_transactions_ibfk_1` FOREIGN KEY (`processed_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `health_records`
--
ALTER TABLE `health_records`
  ADD CONSTRAINT `health_records_ibfk_1` FOREIGN KEY (`resident_id`) REFERENCES `residents` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `health_records_ibfk_2` FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`id`);

--
-- Constraints for table `infrastructure`
--
ALTER TABLE `infrastructure`
  ADD CONSTRAINT `infrastructure_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `inventory_items`
--
ALTER TABLE `inventory_items`
  ADD CONSTRAINT `inventory_items_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `inventory_categories` (`id`),
  ADD CONSTRAINT `inventory_items_ibfk_2` FOREIGN KEY (`unit_id`) REFERENCES `inventory_units` (`id`),
  ADD CONSTRAINT `inventory_items_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `inventory_transactions`
--
ALTER TABLE `inventory_transactions`
  ADD CONSTRAINT `inventory_transactions_ibfk_1` FOREIGN KEY (`item_id`) REFERENCES `inventory_items` (`id`),
  ADD CONSTRAINT `inventory_transactions_ibfk_2` FOREIGN KEY (`performed_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `meal_plans`
--
ALTER TABLE `meal_plans`
  ADD CONSTRAINT `meal_plans_ibfk_1` FOREIGN KEY (`resident_id`) REFERENCES `residents` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `meal_plans_ibfk_2` FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`id`);

--
-- Constraints for table `meal_preferences`
--
ALTER TABLE `meal_preferences`
  ADD CONSTRAINT `meal_preferences_ibfk_1` FOREIGN KEY (`resident_id`) REFERENCES `residents` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `mental_health_reports`
--
ALTER TABLE `mental_health_reports`
  ADD CONSTRAINT `mental_health_reports_ibfk_1` FOREIGN KEY (`resident_id`) REFERENCES `residents` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `mental_health_reports_ibfk_2` FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`id`);

--
-- Constraints for table `messages`
--
ALTER TABLE `messages`
  ADD CONSTRAINT `messages_ibfk_1` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `messages_ibfk_2` FOREIGN KEY (`receiver_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `messages_ibfk_3` FOREIGN KEY (`parent_message_id`) REFERENCES `messages` (`id`);

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`resident_id`) REFERENCES `residents` (`id`),
  ADD CONSTRAINT `payments_ibfk_2` FOREIGN KEY (`plan_id`) REFERENCES `plans` (`id`);

--
-- Constraints for table `residents`
--
ALTER TABLE `residents`
  ADD CONSTRAINT `residents_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `residents_ibfk_2` FOREIGN KEY (`plan_id`) REFERENCES `payment_plans` (`id`);

--
-- Constraints for table `resident_revenue_history`
--
ALTER TABLE `resident_revenue_history`
  ADD CONSTRAINT `resident_revenue_history_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `resident_revenue_history_ibfk_2` FOREIGN KEY (`resident_id`) REFERENCES `residents` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `resident_revenue_history_ibfk_3` FOREIGN KEY (`plan_id`) REFERENCES `payment_plans` (`id`),
  ADD CONSTRAINT `resident_revenue_history_ibfk_4` FOREIGN KEY (`processed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `service_requests`
--
ALTER TABLE `service_requests`
  ADD CONSTRAINT `service_requests_ibfk_1` FOREIGN KEY (`resident_id`) REFERENCES `residents` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `service_requests_ibfk_2` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`),
  ADD CONSTRAINT `service_requests_ibfk_3` FOREIGN KEY (`assigned_staff_id`) REFERENCES `staff` (`id`);

--
-- Constraints for table `staff`
--
ALTER TABLE `staff`
  ADD CONSTRAINT `staff_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `staff_salaries`
--
ALTER TABLE `staff_salaries`
  ADD CONSTRAINT `staff_salaries_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `staff_salaries_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `user_roles` (`id`);

--
-- Constraints for table `utility_bills`
--
ALTER TABLE `utility_bills`
  ADD CONSTRAINT `utility_bills_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `video_calls`
--
ALTER TABLE `video_calls`
  ADD CONSTRAINT `video_calls_ibfk_1` FOREIGN KEY (`resident_id`) REFERENCES `residents` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
