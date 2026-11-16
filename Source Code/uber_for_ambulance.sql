-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Sep 03, 2026 at 03:57 AM
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
-- Database: `uber_for_ambulance`
--

-- --------------------------------------------------------

--
-- Table structure for table `accepts`
--

CREATE TABLE `accepts` (
  `ride_id` varchar(10) NOT NULL,
  `driver_id` varchar(10) NOT NULL,
  `reg_no` varchar(15) NOT NULL,
  `admin_cost` int(11) NOT NULL DEFAULT 0,
  `driver_earning` int(11) NOT NULL DEFAULT 0,
  `settlement_status` varchar(20) NOT NULL DEFAULT 'Pending',
  `settled_at` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `accepts`
--

INSERT INTO `accepts` (`ride_id`, `driver_id`, `reg_no`, `admin_cost`, `driver_earning`, `settlement_status`, `settled_at`) VALUES
('RD-1001', 'DR-201', 'AMB-B01', 500, 1000, 'Settled', '2026-08-01'),
('RD-1002', 'DR-202', 'AMB-B02', 500, 1000, 'Settled', '2026-08-03'),
('RD-1003', 'DR-203', 'AMB-I01', 6000, 2000, 'Settled', '2026-08-05'),
('RD-1004', 'DR-201', 'AMB-B01', 500, 1000, 'Settled', '2026-08-08'),
('RD-1005', 'DR-202', 'AMB-B02', 500, 1000, 'Settled', '2026-08-10'),
('RD-1006', 'DR-201', 'AMB-B01', 500, 1000, 'Settled', '2026-08-21'),
('RD-1007', 'DR-201', 'AMB-B01', 500, 1000, 'Settled', '2026-08-21'),
('RD-1414', 'DR-409', 'AMB-B01', 500, 1000, 'Settled', '2026-08-20'),
('RD-5238', 'DR-201', 'AMB-B02', 500, 1000, 'Settled', '2026-09-03'),
('RD-6326', 'DR-201', 'AMB-B02', 500, 1000, 'Settled', '2026-08-20'),
('RD-6401', 'DR-201', 'AMB-B01', 500, 1000, 'Settled', '2026-08-21'),
('RD-7011', 'DR-201', 'AMB-I01', 6000, 2000, 'Settled', '2026-09-03'),
('RD-7851', 'DR-201', 'AMB-I01', 6000, 2000, 'Settled', '2026-08-20'),
('RD-8430', 'DR-201', 'AMB-I01', 6000, 2000, 'Settled', '2026-08-20'),
('RD-9647', 'DR-201', 'AMB-B01', 500, 1000, 'Settled', '2026-08-21');

-- --------------------------------------------------------

--
-- Table structure for table `admin`
--

CREATE TABLE `admin` (
  `user_id` varchar(10) NOT NULL,
  `office_room_no` varchar(10) NOT NULL,
  `admin_level` varchar(30) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admin`
--

INSERT INTO `admin` (`user_id`, `office_room_no`, `admin_level`) VALUES
('AD-363', 'ER-01', 'Fleet Supervisor'),
('AD-886', 'ER-07', 'Operations Manager'),
('AD-901', 'ER-07', 'Fleet Supervisor');

-- --------------------------------------------------------

--
-- Table structure for table `ambulance`
--

CREATE TABLE `ambulance` (
  `reg_no` varchar(15) NOT NULL,
  `hourly_rate` int(11) NOT NULL,
  `admin_cost` int(11) NOT NULL DEFAULT 500,
  `available_status` varchar(20) NOT NULL DEFAULT 'Available',
  `vehicle_type` varchar(30) NOT NULL,
  `admin_id` varchar(10) NOT NULL
) ;

--
-- Dumping data for table `ambulance`
--

INSERT INTO `ambulance` (`reg_no`, `hourly_rate`, `admin_cost`, `available_status`, `vehicle_type`, `admin_id`) VALUES
('AMB-B01', 1500, 500, 'Available', 'Basic', 'AD-901'),
('AMB-B02', 1500, 500, 'Available', 'Basic', 'AD-901'),
('AMB-B325', 1500, 500, 'Available', 'Basic', 'AD-901'),
('AMB-B469', 1500, 500, 'Available', 'Basic', 'AD-901'),
('AMB-B517', 1500, 500, 'Available', 'Basic', 'AD-901'),
('AMB-I01', 8000, 6000, 'Available', 'ICU', 'AD-901'),
('AMB-I182', 8000, 6000, 'Available', 'ICU', 'AD-901'),
('AMB-I254', 8000, 6000, 'Available', 'ICU', 'AD-901'),
('AMB-I289', 8000, 5500, 'Available', 'ICU', 'AD-901'),
('AMB-I871', 8000, 6000, 'Available', 'ICU', 'AD-901');

-- --------------------------------------------------------

--
-- Table structure for table `basic_ambulance`
--

CREATE TABLE `basic_ambulance` (
  `reg_no` varchar(15) NOT NULL,
  `stretcher_type` varchar(30) NOT NULL,
  `kit_class` varchar(5) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `basic_ambulance`
--

INSERT INTO `basic_ambulance` (`reg_no`, `stretcher_type`, `kit_class`) VALUES
('AMB-B01', 'Hydraulic foldable', 'A'),
('AMB-B02', 'Manual scoop', 'B'),
('AMB-B325', 'Hydraulic foldable', 'A'),
('AMB-B469', 'Hydraulic foldable', 'A'),
('AMB-B517', 'Hydraulic foldable', 'A');

-- --------------------------------------------------------

--
-- Table structure for table `driver`
--

CREATE TABLE `driver` (
  `user_id` varchar(10) NOT NULL,
  `license_no` varchar(20) NOT NULL,
  `rating` decimal(3,2) NOT NULL DEFAULT 5.00,
  `admin_id` varchar(10) NOT NULL
) ;

--
-- Dumping data for table `driver`
--

INSERT INTO `driver` (`user_id`, `license_no`, `rating`, `admin_id`) VALUES
('DR-201', 'DHA-AMB-201', 3.22, 'AD-901'),
('DR-202', 'DHA-AMB-202', 4.00, 'AD-901'),
('DR-203', 'DHA-AMB-203', 5.00, 'AD-901'),
('DR-409', '748957398475', 5.00, 'AD-901'),
('DR-517', 'CTG-420-69', 5.00, 'AD-363');

-- --------------------------------------------------------

--
-- Table structure for table `icu_ambulance`
--

CREATE TABLE `icu_ambulance` (
  `reg_no` varchar(15) NOT NULL,
  `ventilator_count` int(11) NOT NULL,
  `has_oxygen` tinyint(1) NOT NULL DEFAULT 1
) ;

--
-- Dumping data for table `icu_ambulance`
--

INSERT INTO `icu_ambulance` (`reg_no`, `ventilator_count`, `has_oxygen`) VALUES
('AMB-I01', 2, 1),
('AMB-I182', 2, 1),
('AMB-I254', 2, 1),
('AMB-I289', 2, 1),
('AMB-I871', 2, 1);

-- --------------------------------------------------------

--
-- Table structure for table `patient`
--

CREATE TABLE `patient` (
  `user_id` varchar(10) NOT NULL,
  `emergency_type` varchar(50) NOT NULL,
  `age` int(11) NOT NULL
) ;

--
-- Dumping data for table `patient`
--

INSERT INTO `patient` (`user_id`, `emergency_type`, `age`) VALUES
('PT-101', 'Cardiac support', 34),
('PT-102', 'Trauma response', 27),
('PT-103', 'Maternity support', 31);

-- --------------------------------------------------------

--
-- Table structure for table `ride_request`
--

CREATE TABLE `ride_request` (
  `ride_id` varchar(10) NOT NULL,
  `pickup_location` varchar(100) NOT NULL,
  `drop_location` varchar(100) NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'Requested',
  `fare` int(11) NOT NULL,
  `vehicle_type` varchar(30) NOT NULL DEFAULT 'Basic',
  `request_date` date NOT NULL,
  `passenger_rating` int(11) DEFAULT NULL,
  `feedback` text DEFAULT NULL,
  `patient_id` varchar(10) NOT NULL,
  `admin_id` varchar(10) NOT NULL
) ;

--
-- Dumping data for table `ride_request`
--

INSERT INTO `ride_request` (`ride_id`, `pickup_location`, `drop_location`, `status`, `fare`, `vehicle_type`, `request_date`, `passenger_rating`, `feedback`, `patient_id`, `admin_id`) VALUES
('RD-1001', 'Dhanmondi 27', 'Square Hospital', 'Completed', 1500, 'Basic', '2026-08-01', 5, 'Fast and calm response.', 'PT-101', 'AD-901'),
('RD-1002', 'Uttara Sector 7', 'Dhaka Medical College', 'Completed', 1500, 'Basic', '2026-08-03', 4, 'Professional driving.', 'PT-102', 'AD-901'),
('RD-1003', 'Banani 11', 'Evercare Hospital', 'Completed', 8000, 'ICU', '2026-08-05', 5, 'Excellent ICU support.', 'PT-101', 'AD-901'),
('RD-1004', 'Mirpur 10', 'Birdem Hospital', 'Completed', 1500, 'Basic', '2026-08-08', 5, 'Very reassuring service.', 'PT-103', 'AD-901'),
('RD-1005', 'Mohakhali DOHS', 'United Hospital', 'Completed', 1500, 'Basic', '2026-08-10', 4, 'Good overall experience.', 'PT-102', 'AD-901'),
('RD-1006', 'Gulshan 2', 'LabAid Hospital', 'Completed', 1500, 'Basic', '2026-08-19', 2, NULL, 'PT-101', 'AD-901'),
('RD-1007', 'Agrabad Access Road', 'Chattogram Medical College', 'Completed', 1500, 'Basic', '2026-08-20', 4, 'very nice', 'PT-103', 'AD-901'),
('RD-1414', 'Dhanmondi', 'Mirpur', 'Completed', 1500, 'Basic', '2026-08-20', 5, 'very nice', 'PT-101', 'AD-901'),
('RD-5238', 'Badda', 'Mohammadpur', 'Completed', 1500, 'Basic', '2026-08-21', 4, 'very nice', 'PT-101', 'AD-901'),
('RD-6326', 'Badda', 'Mirpur', 'Completed', 1500, 'Basic', '2026-08-20', 4, NULL, 'PT-101', 'AD-901'),
('RD-6401', 'Dhanmondi', 'Mirpur', 'Completed', 1500, 'Basic', '2026-08-21', 1, NULL, 'PT-101', 'AD-901'),
('RD-7011', 'Dhanmondi', 'Mohammadpur', 'Completed', 8000, 'ICU', '2026-09-03', NULL, NULL, 'PT-101', 'AD-901'),
('RD-7851', 'Badda', 'Mohammadpur', 'Completed', 8000, 'ICU', '2026-08-20', NULL, NULL, 'PT-102', 'AD-901'),
('RD-8430', 'Dhanmondi', 'Mirpur', 'Completed', 8000, 'ICU', '2026-08-20', 3, NULL, 'PT-101', 'AD-901'),
('RD-9647', 'Badda', 'Mirpur', 'Completed', 1500, 'Basic', '2026-08-21', 1, 'very bad', 'PT-101', 'AD-901');

-- --------------------------------------------------------

--
-- Table structure for table `user`
--

CREATE TABLE `user` (
  `user_id` varchar(10) NOT NULL,
  `username` varchar(30) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `password_hash` varchar(255) NOT NULL
) ;

--
-- Dumping data for table `user`
--

INSERT INTO `user` (`user_id`, `username`, `first_name`, `last_name`, `password_hash`) VALUES
('AD-363', 'hredika_islam', 'Hredika', 'Islam', '$2y$10$J6rAiivS9g1sn6ZeBX9fU.AhnuihasQPtYvT2bDiFFhMS1N6gcboS'),
('AD-886', 'admin423', 'Hasib', 'Rishad', '$2y$10$xa6VDOXnPqXJ7tr8iZWEo.C7uFm1MgUCAbFCXDXhov/mndrtd59Ii'),
('AD-901', 'maya_rahman', 'Maya', 'Rahman', '$2y$10$b3IJQDvm4Y3QOZlEIrJTDutGvzFxioHgSmUiVgo/JhnihUkfQIvnm'),
('DR-201', 'rafi_ahmed', 'Rafi', 'Ahmed', '$2y$10$WWaHuR9PtfS95BbMmxbXjefAxthaodBwkQRxxZtNoEtaZkDTenBoO'),
('DR-202', 'tania_sultana', 'Tania', 'Sultana', '$2y$10$WWaHuR9PtfS95BbMmxbXjefAxthaodBwkQRxxZtNoEtaZkDTenBoO'),
('DR-203', 'imran_hossain', 'Imran', 'Hossain', '$2y$10$WWaHuR9PtfS95BbMmxbXjefAxthaodBwkQRxxZtNoEtaZkDTenBoO'),
('DR-409', 'tony_stark', 'Tony', 'Stark', '$2y$10$WWaHuR9PtfS95BbMmxbXjefAxthaodBwkQRxxZtNoEtaZkDTenBoO'),
('DR-517', 'mihaz_speed_lover', 'Mihaz Abdin', 'Emon', '$2y$10$9Z0BlBmKbT4w3RRi31zZkuVpdeI3/4zjy1eTczWgbA1n6g9nTAsLO'),
('PT-101', 'ariana_chowdhury', 'Ariana', 'Chowdhury', '$2y$10$.ugncFiyFxhk6tkVqhaYBOo.IXvKniBByy6jF8ti7hV6bfARn0rJ.'),
('PT-102', 'nabil_hasan', 'Nabil', 'Hasan', '$2y$10$.ugncFiyFxhk6tkVqhaYBOo.IXvKniBByy6jF8ti7hV6bfARn0rJ.'),
('PT-103', 'sadia_khan', 'Sadia', 'Khan', '$2y$10$.ugncFiyFxhk6tkVqhaYBOo.IXvKniBByy6jF8ti7hV6bfARn0rJ.');

-- --------------------------------------------------------

--
-- Table structure for table `user_phone`
--

CREATE TABLE `user_phone` (
  `user_id` varchar(10) NOT NULL,
  `phone` varchar(20) NOT NULL
) ;

--
-- Dumping data for table `user_phone`
--

INSERT INTO `user_phone` (`user_id`, `phone`) VALUES
('AD-363', '7347698047593'),
('AD-886', '7347698047593'),
('AD-901', '+8801700000901'),
('DR-201', '+8801700000201'),
('DR-202', '+8801700000202'),
('DR-203', '+8801700000203'),
('DR-409', '7347698047593'),
('DR-517', '015673647364'),
('PT-101', '+8801700000101'),
('PT-102', '+8801700000102'),
('PT-103', '+8801700000103');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `accepts`
--
ALTER TABLE `accepts`
  ADD PRIMARY KEY (`ride_id`,`driver_id`,`reg_no`),
  ADD KEY `driver_id` (`driver_id`),
  ADD KEY `reg_no` (`reg_no`);

--
-- Indexes for table `admin`
--
ALTER TABLE `admin`
  ADD PRIMARY KEY (`user_id`);

--
-- Indexes for table `ambulance`
--
ALTER TABLE `ambulance`
  ADD PRIMARY KEY (`reg_no`),
  ADD KEY `fk_ambulance_admin` (`admin_id`);

--
-- Indexes for table `basic_ambulance`
--
ALTER TABLE `basic_ambulance`
  ADD PRIMARY KEY (`reg_no`);

--
-- Indexes for table `driver`
--
ALTER TABLE `driver`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `license_no` (`license_no`),
  ADD KEY `admin_id` (`admin_id`);

--
-- Indexes for table `icu_ambulance`
--
ALTER TABLE `icu_ambulance`
  ADD PRIMARY KEY (`reg_no`);

--
-- Indexes for table `patient`
--
ALTER TABLE `patient`
  ADD PRIMARY KEY (`user_id`);

--
-- Indexes for table `ride_request`
--
ALTER TABLE `ride_request`
  ADD PRIMARY KEY (`ride_id`),
  ADD KEY `patient_id` (`patient_id`),
  ADD KEY `admin_id` (`admin_id`);

--
-- Indexes for table `user`
--
ALTER TABLE `user`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `uq_user_username` (`username`);

--
-- Indexes for table `user_phone`
--
ALTER TABLE `user_phone`
  ADD PRIMARY KEY (`user_id`,`phone`);

--
-- Constraints for dumped tables
--

--
-- Constraints for table `accepts`
--
ALTER TABLE `accepts`
  ADD CONSTRAINT `accepts_ibfk_1` FOREIGN KEY (`ride_id`) REFERENCES `ride_request` (`ride_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `accepts_ibfk_2` FOREIGN KEY (`driver_id`) REFERENCES `driver` (`user_id`),
  ADD CONSTRAINT `accepts_ibfk_3` FOREIGN KEY (`reg_no`) REFERENCES `ambulance` (`reg_no`);

--
-- Constraints for table `admin`
--
ALTER TABLE `admin`
  ADD CONSTRAINT `admin_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `user` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `ambulance`
--
ALTER TABLE `ambulance`
  ADD CONSTRAINT `fk_ambulance_admin` FOREIGN KEY (`admin_id`) REFERENCES `admin` (`user_id`);

--
-- Constraints for table `basic_ambulance`
--
ALTER TABLE `basic_ambulance`
  ADD CONSTRAINT `basic_ambulance_ibfk_1` FOREIGN KEY (`reg_no`) REFERENCES `ambulance` (`reg_no`) ON DELETE CASCADE;

--
-- Constraints for table `driver`
--
ALTER TABLE `driver`
  ADD CONSTRAINT `driver_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `user` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `driver_ibfk_2` FOREIGN KEY (`admin_id`) REFERENCES `admin` (`user_id`);

--
-- Constraints for table `icu_ambulance`
--
ALTER TABLE `icu_ambulance`
  ADD CONSTRAINT `icu_ambulance_ibfk_1` FOREIGN KEY (`reg_no`) REFERENCES `ambulance` (`reg_no`) ON DELETE CASCADE;

--
-- Constraints for table `patient`
--
ALTER TABLE `patient`
  ADD CONSTRAINT `patient_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `user` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `ride_request`
--
ALTER TABLE `ride_request`
  ADD CONSTRAINT `ride_request_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patient` (`user_id`),
  ADD CONSTRAINT `ride_request_ibfk_2` FOREIGN KEY (`admin_id`) REFERENCES `admin` (`user_id`);

--
-- Constraints for table `user_phone`
--
ALTER TABLE `user_phone`
  ADD CONSTRAINT `user_phone_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `user` (`user_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
