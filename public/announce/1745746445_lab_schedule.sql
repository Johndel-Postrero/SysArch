-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Apr 25, 2025 at 01:58 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `sitin`
--

-- --------------------------------------------------------

--
-- Table structure for table `lab_schedule`
--

CREATE TABLE `lab_schedule` (
  `id` int(11) NOT NULL,
  `lab_number` varchar(10) NOT NULL,
  `schedule_type` enum('recurring','specific') NOT NULL,
  `weekday` tinyint(1) DEFAULT NULL,
  `specific_date` date DEFAULT NULL,
  `status` enum('available','unavailable') NOT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `start_time` time DEFAULT NULL,
  `end_time` time DEFAULT NULL,
  `parent_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `lab_schedule`
--

INSERT INTO `lab_schedule` (`id`, `lab_number`, `schedule_type`, `weekday`, `specific_date`, `status`, `reason`, `created_at`, `updated_at`, `start_time`, `end_time`, `parent_id`) VALUES
(126, '526', 'recurring', 2, '2025-04-14', 'unavailable', '', '2025-04-14 05:43:34', '2025-04-14 05:43:34', '07:00:00', '21:00:00', NULL),
(127, '528', 'recurring', 2, '2025-04-14', 'unavailable', '', '2025-04-14 05:43:34', '2025-04-14 05:43:34', '07:00:00', '21:00:00', NULL),
(128, '530', 'recurring', 2, '2025-04-14', 'unavailable', '', '2025-04-14 05:43:34', '2025-04-14 05:43:34', '07:00:00', '21:00:00', NULL),
(129, '542', 'recurring', 2, '2025-04-14', 'unavailable', '', '2025-04-14 05:43:34', '2025-04-14 05:43:34', '07:00:00', '21:00:00', NULL),
(130, '544', 'recurring', 2, '2025-04-14', 'unavailable', '', '2025-04-14 05:43:34', '2025-04-14 05:43:34', '07:00:00', '21:00:00', NULL),
(131, '524', 'recurring', 3, '2025-04-14', 'available', '', '2025-04-14 05:44:09', '2025-04-14 05:44:09', '07:00:00', '21:00:00', NULL),
(134, '526', 'recurring', 6, '2025-04-25', 'unavailable', '', '2025-04-24 23:43:14', '2025-04-24 23:43:14', '07:00:00', '21:00:00', NULL),
(135, '528', 'recurring', 6, '2025-04-25', 'unavailable', '', '2025-04-24 23:43:14', '2025-04-24 23:43:14', '07:00:00', '21:00:00', NULL),
(136, '530', 'recurring', 6, '2025-04-25', 'unavailable', '', '2025-04-24 23:43:14', '2025-04-24 23:43:14', '07:00:00', '21:00:00', NULL),
(137, '542', 'recurring', 6, '2025-04-25', 'unavailable', '', '2025-04-24 23:43:14', '2025-04-24 23:43:14', '07:00:00', '21:00:00', NULL),
(138, '544', 'recurring', 6, '2025-04-25', 'unavailable', '', '2025-04-24 23:43:14', '2025-04-24 23:43:14', '07:00:00', '21:00:00', NULL),
(139, '524', 'recurring', 2, '2025-04-25', 'available', '', '2025-04-24 23:44:08', '2025-04-24 23:44:08', '07:00:00', '21:00:00', NULL),
(140, '524', 'recurring', 6, '2025-04-25', 'available', '', '2025-04-24 23:44:31', '2025-04-24 23:44:31', '07:00:00', '21:00:00', NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `lab_schedule`
--
ALTER TABLE `lab_schedule`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_lab_schedule` (`lab_number`),
  ADD KEY `fk_parent` (`parent_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `lab_schedule`
--
ALTER TABLE `lab_schedule`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=141;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `lab_schedule`
--
ALTER TABLE `lab_schedule`
  ADD CONSTRAINT `fk_parent` FOREIGN KEY (`parent_id`) REFERENCES `lab_schedule` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
