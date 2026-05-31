-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Dec 04, 2025 at 04:27 AM
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
-- Database: `trs`
--

-- --------------------------------------------------------

--
-- Table structure for table `admin`
--

CREATE TABLE `admin` (
  `admin_id` int(255) NOT NULL,
  `username` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `fullname` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admin`
--

INSERT INTO `admin` (`admin_id`, `username`, `password`, `fullname`) VALUES
(1, 'trsadmin@smccnasipit.edu.ph', 'trsadmin', 'Admin');

-- --------------------------------------------------------

--
-- Table structure for table `adviser`
--

CREATE TABLE `adviser` (
  `adviser_id` int(11) NOT NULL,
  `school_id` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `fullname` varchar(255) NOT NULL,
  `department` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `departmentcourse`
--

CREATE TABLE `departmentcourse` (
  `id` int(11) NOT NULL,
  `department` varchar(255) NOT NULL,
  `course` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `departmentcourse`
--

INSERT INTO `departmentcourse` (`id`, `department`, `course`) VALUES
(1, 'College of Computing and Information Science', 'Bachelor of Science in Information Technology'),
(2, 'College of Computing and Information Science', 'Bachelor of Science in Computer Science'),
(3, 'College of Tourism and Hospitality Management', 'Bachelor of Science in Tourism Management'),
(4, 'College of Tourism and Hospitality Management', 'Bachelor of Science in Hospitality Management'),
(5, 'College of Business and Management', 'Bachelor of Science in Business Administration major in Financial Management'),
(6, 'College of Business and Management', 'Bachelor of Science in Business Administration major in Human Resource Management'),
(7, 'College of Business and Management', 'Bachelor of Science in Business Administration major in Marketing Management'),
(8, 'College of Teacher Education', 'Bachelor of Elementary Education'),
(9, 'College of Arts and Sciences', 'Bachelor of Arts major in English Language'),
(10, 'College of Computing and Information Science', 'Bachelor of Science in Information System'),
(11, 'College of Criminal Justice Education', 'Bachelor of Science in Criminology'),
(12, 'College of Teacher Education', 'Bachelor of Secondary Education major in English'),
(13, 'College of Teacher Education', 'Bachelor of Secondary Education major in Science'),
(14, 'College of Teacher Education', 'Bachelor of Secondary Education major in Social Studies');

-- --------------------------------------------------------

--
-- Table structure for table `finaldocufinal_files`
--

CREATE TABLE `finaldocufinal_files` (
  `finaldocu_id` int(11) NOT NULL,
  `student_id` varchar(50) DEFAULT NULL,
  `finaldocu` varchar(255) DEFAULT NULL,
  `adviser_id` varchar(255) DEFAULT NULL,
  `panel1_id` varchar(50) DEFAULT NULL,
  `panel2_id` varchar(50) DEFAULT NULL,
  `panel3_id` varchar(50) DEFAULT NULL,
  `panel4_id` varchar(50) DEFAULT NULL,
  `panel5_id` varchar(255) NOT NULL,
  `department` varchar(100) DEFAULT NULL,
  `date_submitted` datetime DEFAULT NULL,
  `controlNo` varchar(255) NOT NULL,
  `fullname` varchar(255) NOT NULL,
  `group_number` int(255) NOT NULL,
  `title` varchar(255) NOT NULL,
  `school_year` varchar(255) NOT NULL,
  `routeNumber` varchar(254) NOT NULL,
  `panel6_id` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `finaldocuproposal_files`
--

CREATE TABLE `finaldocuproposal_files` (
  `finaldocu_id` int(11) NOT NULL,
  `student_id` varchar(50) DEFAULT NULL,
  `finaldocu` varchar(255) DEFAULT NULL,
  `panel1_id` varchar(50) DEFAULT NULL,
  `panel2_id` varchar(50) DEFAULT NULL,
  `panel3_id` varchar(50) DEFAULT NULL,
  `panel4_id` varchar(50) DEFAULT NULL,
  `panel5_id` varchar(255) NOT NULL,
  `department` varchar(100) DEFAULT NULL,
  `date_submitted` datetime DEFAULT NULL,
  `adviser_id` int(255) NOT NULL,
  `controlNo` varchar(245) NOT NULL,
  `fullname` varchar(255) NOT NULL,
  `group_number` int(255) NOT NULL,
  `title` varchar(255) NOT NULL,
  `school_year` varchar(255) NOT NULL,
  `minutes` varchar(255) NOT NULL,
  `routeNumber` varchar(255) NOT NULL,
  `panel6_id` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `final_monitoring_form`
--

CREATE TABLE `final_monitoring_form` (
  `id` int(11) NOT NULL,
  `panel_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `panel_name` varchar(255) NOT NULL,
  `date_submitted` date NOT NULL,
  `chapter` varchar(255) DEFAULT NULL,
  `feedback` text DEFAULT NULL,
  `paragraph_number` int(11) DEFAULT NULL,
  `page_number` int(11) DEFAULT NULL,
  `date_released` date DEFAULT NULL,
  `docuRoute1` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `adviser_id` int(11) NOT NULL,
  `adviser_name` varchar(255) NOT NULL,
  `route1_id` int(11) DEFAULT NULL,
  `route2_id` int(11) DEFAULT NULL,
  `docuRoute2` varchar(255) DEFAULT NULL,
  `status` varchar(50) DEFAULT NULL,
  `route3_id` int(255) NOT NULL,
  `docuRoute3` varchar(255) NOT NULL,
  `finaldocu_id` int(255) NOT NULL,
  `finaldocu` varchar(255) NOT NULL,
  `panel5_id` varchar(255) NOT NULL,
  `routeNumber` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `panel`
--

CREATE TABLE `panel` (
  `panel_id` int(11) NOT NULL,
  `school_id` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `fullname` varchar(255) NOT NULL,
  `department` varchar(255) NOT NULL,
  `position` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `proposal_monitoring_form`
--

CREATE TABLE `proposal_monitoring_form` (
  `id` int(11) NOT NULL,
  `panel_id` varchar(50) DEFAULT NULL,
  `student_id` int(255) NOT NULL,
  `panel_name` varchar(100) DEFAULT NULL,
  `date_submitted` date DEFAULT NULL,
  `chapter` varchar(255) DEFAULT NULL,
  `feedback` text DEFAULT NULL,
  `paragraph_number` int(11) DEFAULT NULL,
  `page_number` int(11) DEFAULT NULL,
  `date_released` date DEFAULT NULL,
  `docuRoute1` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `adviser_id` int(255) NOT NULL,
  `adviser_name` varchar(255) NOT NULL,
  `route1_id` int(255) NOT NULL,
  `route2_id` int(255) NOT NULL,
  `docuRoute2` varchar(255) NOT NULL,
  `status` varchar(255) NOT NULL,
  `route3_id` int(255) NOT NULL,
  `docuRoute3` varchar(255) NOT NULL,
  `finaldocu_id` int(255) NOT NULL,
  `finaldocu` varchar(255) NOT NULL,
  `panel5_id` varchar(255) NOT NULL,
  `routeNumber` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `route1final_files`
--

CREATE TABLE `route1final_files` (
  `route1_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `docuRoute1` varchar(255) NOT NULL,
  `panel1_id` int(11) NOT NULL,
  `panel2_id` int(11) NOT NULL,
  `panel3_id` int(11) NOT NULL,
  `panel4_id` int(11) NOT NULL,
  `adviser_id` int(11) NOT NULL,
  `department` varchar(255) NOT NULL,
  `date_submitted` date NOT NULL,
  `controlNo` varchar(244) NOT NULL,
  `fullname` varchar(255) NOT NULL,
  `group_number` int(255) NOT NULL,
  `title` varchar(255) NOT NULL,
  `school_year` varchar(255) NOT NULL,
  `panel5_id` varchar(255) NOT NULL,
  `routeNumber` varchar(255) NOT NULL,
  `panel6_id` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `route1proposal_files`
--

CREATE TABLE `route1proposal_files` (
  `route1_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `docuRoute1` varchar(255) NOT NULL,
  `panel1_id` int(255) NOT NULL,
  `panel2_id` int(255) NOT NULL,
  `panel3_id` int(255) NOT NULL,
  `panel4_id` int(255) NOT NULL,
  `adviser_id` int(255) NOT NULL,
  `department` varchar(255) NOT NULL,
  `date_submitted` datetime DEFAULT NULL,
  `controlNo` varchar(255) NOT NULL,
  `fullname` varchar(255) NOT NULL,
  `group_number` int(255) NOT NULL,
  `title` varchar(255) NOT NULL,
  `school_year` varchar(255) NOT NULL,
  `panel5_id` varchar(255) NOT NULL,
  `minutes` varchar(255) NOT NULL,
  `routeNumber` varchar(255) NOT NULL,
  `panel6_id` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `route2final_files`
--

CREATE TABLE `route2final_files` (
  `route2_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `docuRoute2` varchar(255) NOT NULL,
  `panel1_id` int(11) NOT NULL,
  `panel2_id` int(11) NOT NULL,
  `panel3_id` int(11) NOT NULL,
  `panel4_id` int(11) NOT NULL,
  `adviser_id` int(11) NOT NULL,
  `department` varchar(255) NOT NULL,
  `date_submitted` date NOT NULL,
  `controlNo` varchar(255) NOT NULL,
  `fullname` varchar(255) NOT NULL,
  `group_number` int(255) NOT NULL,
  `title` varchar(255) NOT NULL,
  `school_year` varchar(255) NOT NULL,
  `panel5_id` varchar(255) NOT NULL,
  `routeNumber` varchar(255) NOT NULL,
  `panel6_id` varchar(266) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `route2proposal_files`
--

CREATE TABLE `route2proposal_files` (
  `route2_id` int(11) NOT NULL,
  `student_id` varchar(50) DEFAULT NULL,
  `docuRoute2` varchar(255) DEFAULT NULL,
  `panel1_id` varchar(50) DEFAULT NULL,
  `panel2_id` varchar(50) DEFAULT NULL,
  `panel3_id` varchar(50) DEFAULT NULL,
  `panel4_id` varchar(50) DEFAULT NULL,
  `department` varchar(100) DEFAULT NULL,
  `date_submitted` datetime DEFAULT NULL,
  `adviser_id` int(255) NOT NULL,
  `controlNo` varchar(222) NOT NULL,
  `fullname` varchar(244) NOT NULL,
  `group_number` int(244) NOT NULL,
  `title` varchar(255) NOT NULL,
  `school_year` varchar(255) NOT NULL,
  `panel5_id` varchar(255) NOT NULL,
  `minutes` varchar(255) NOT NULL,
  `routeNumber` varchar(255) NOT NULL,
  `panel6_id` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `route3final_files`
--

CREATE TABLE `route3final_files` (
  `route3_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `docuRoute3` varchar(255) NOT NULL,
  `panel1_id` int(11) NOT NULL,
  `panel2_id` int(11) NOT NULL,
  `panel3_id` int(11) NOT NULL,
  `panel4_id` int(11) NOT NULL,
  `adviser_id` int(11) NOT NULL,
  `department` varchar(255) NOT NULL,
  `date_submitted` date NOT NULL,
  `controlNo` varchar(255) NOT NULL,
  `fullname` varchar(255) NOT NULL,
  `group_number` int(255) NOT NULL,
  `title` varchar(255) NOT NULL,
  `school_year` varchar(255) NOT NULL,
  `panel5_id` varchar(255) NOT NULL,
  `routeNumber` varchar(255) NOT NULL,
  `panel6_id` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `route3proposal_files`
--

CREATE TABLE `route3proposal_files` (
  `route3_id` int(11) NOT NULL,
  `student_id` varchar(50) DEFAULT NULL,
  `docuRoute3` varchar(255) DEFAULT NULL,
  `panel1_id` varchar(50) DEFAULT NULL,
  `panel2_id` varchar(50) DEFAULT NULL,
  `panel3_id` varchar(50) DEFAULT NULL,
  `panel4_id` varchar(50) DEFAULT NULL,
  `department` varchar(100) DEFAULT NULL,
  `date_submitted` datetime DEFAULT NULL,
  `adviser_id` int(255) NOT NULL,
  `controlNo` varchar(255) NOT NULL,
  `fullname` varchar(255) NOT NULL,
  `group_number` int(255) NOT NULL,
  `title` varchar(255) NOT NULL,
  `school_year` varchar(255) NOT NULL,
  `panel5_id` varchar(255) NOT NULL,
  `minutes` varchar(255) NOT NULL,
  `routeNumber` varchar(255) NOT NULL,
  `panel6_id` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `student`
--

CREATE TABLE `student` (
  `student_id` int(11) NOT NULL,
  `school_id` int(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `confirm_password` varchar(255) NOT NULL,
  `fullname` varchar(255) NOT NULL,
  `school_year` varchar(255) NOT NULL,
  `department` varchar(255) NOT NULL,
  `course` varchar(255) NOT NULL,
  `adviser` varchar(255) NOT NULL,
  `group_number` int(255) NOT NULL,
  `group_members` text DEFAULT NULL,
  `controlNo` varchar(100) DEFAULT NULL,
  `title` varchar(215) NOT NULL,
  `semester` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `adviser_email` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admin`
--
ALTER TABLE `admin`
  ADD PRIMARY KEY (`admin_id`);

--
-- Indexes for table `adviser`
--
ALTER TABLE `adviser`
  ADD PRIMARY KEY (`adviser_id`);

--
-- Indexes for table `departmentcourse`
--
ALTER TABLE `departmentcourse`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `finaldocufinal_files`
--
ALTER TABLE `finaldocufinal_files`
  ADD PRIMARY KEY (`finaldocu_id`);

--
-- Indexes for table `finaldocuproposal_files`
--
ALTER TABLE `finaldocuproposal_files`
  ADD PRIMARY KEY (`finaldocu_id`);

--
-- Indexes for table `final_monitoring_form`
--
ALTER TABLE `final_monitoring_form`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `panel`
--
ALTER TABLE `panel`
  ADD PRIMARY KEY (`panel_id`);

--
-- Indexes for table `proposal_monitoring_form`
--
ALTER TABLE `proposal_monitoring_form`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `route1final_files`
--
ALTER TABLE `route1final_files`
  ADD PRIMARY KEY (`route1_id`);

--
-- Indexes for table `route1proposal_files`
--
ALTER TABLE `route1proposal_files`
  ADD PRIMARY KEY (`route1_id`);

--
-- Indexes for table `route2final_files`
--
ALTER TABLE `route2final_files`
  ADD PRIMARY KEY (`route2_id`);

--
-- Indexes for table `route2proposal_files`
--
ALTER TABLE `route2proposal_files`
  ADD PRIMARY KEY (`route2_id`);

--
-- Indexes for table `route3final_files`
--
ALTER TABLE `route3final_files`
  ADD PRIMARY KEY (`route3_id`);

--
-- Indexes for table `route3proposal_files`
--
ALTER TABLE `route3proposal_files`
  ADD PRIMARY KEY (`route3_id`);

--
-- Indexes for table `student`
--
ALTER TABLE `student`
  ADD PRIMARY KEY (`student_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admin`
--
ALTER TABLE `admin`
  MODIFY `admin_id` int(255) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `adviser`
--
ALTER TABLE `adviser`
  MODIFY `adviser_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=34;

--
-- AUTO_INCREMENT for table `departmentcourse`
--
ALTER TABLE `departmentcourse`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `finaldocufinal_files`
--
ALTER TABLE `finaldocufinal_files`
  MODIFY `finaldocu_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT for table `finaldocuproposal_files`
--
ALTER TABLE `finaldocuproposal_files`
  MODIFY `finaldocu_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=51;

--
-- AUTO_INCREMENT for table `final_monitoring_form`
--
ALTER TABLE `final_monitoring_form`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=99;

--
-- AUTO_INCREMENT for table `panel`
--
ALTER TABLE `panel`
  MODIFY `panel_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=36;

--
-- AUTO_INCREMENT for table `proposal_monitoring_form`
--
ALTER TABLE `proposal_monitoring_form`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=239;

--
-- AUTO_INCREMENT for table `route1final_files`
--
ALTER TABLE `route1final_files`
  MODIFY `route1_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT for table `route1proposal_files`
--
ALTER TABLE `route1proposal_files`
  MODIFY `route1_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=101;

--
-- AUTO_INCREMENT for table `route2final_files`
--
ALTER TABLE `route2final_files`
  MODIFY `route2_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `route2proposal_files`
--
ALTER TABLE `route2proposal_files`
  MODIFY `route2_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=43;

--
-- AUTO_INCREMENT for table `route3final_files`
--
ALTER TABLE `route3final_files`
  MODIFY `route3_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT for table `route3proposal_files`
--
ALTER TABLE `route3proposal_files`
  MODIFY `route3_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=40;

--
-- AUTO_INCREMENT for table `student`
--
ALTER TABLE `student`
  MODIFY `student_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=42;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
