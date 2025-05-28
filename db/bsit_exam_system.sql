-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 28, 2025 at 09:42 PM
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
-- Database: `bsit_exam_system`
--

-- --------------------------------------------------------

--
-- Table structure for table `admins`
--

CREATE TABLE `admins` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin1','admin2') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admins`
--

INSERT INTO `admins` (`id`, `username`, `password`, `role`) VALUES
(1, 'admin@gmail.com', '$2y$10$Q8NrK.rL5/CAD488YC25xudFIFYSA6bYNyQkGf9r97xLj9hkVO9ha', 'admin1'),
(3, 'admin2@gmail.com', '$2y$10$JrWLt10/sTWMIRZfKP4FKeX7hZIRduqDKPV9eTlKnuYttLSiIyJqO', 'admin2');

-- --------------------------------------------------------

--
-- Table structure for table `exams`
--

CREATE TABLE `exams` (
  `id` int(11) NOT NULL,
  `title` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `duration_minutes` int(11) NOT NULL,
  `due_date` date DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `exams`
--

INSERT INTO `exams` (`id`, `title`, `description`, `duration_minutes`, `due_date`, `created_by`, `created_at`) VALUES
(10, 'HAHA', 'wew', 1, '2025-05-27', 1, '2025-05-27 08:07:39'),
(11, 'haha', 'wew', 5, '2025-05-27', 1, '2025-05-27 08:25:40'),
(12, 'Basta Exam', '', 15, '2025-05-28', 1, '2025-05-28 18:50:03'),
(13, 'basta exam nasad', 'Hello', 15, '2025-05-28', 1, '2025-05-28 19:32:53');

-- --------------------------------------------------------

--
-- Table structure for table `exam_assignments`
--

CREATE TABLE `exam_assignments` (
  `id` int(11) NOT NULL,
  `exam_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `assigned_at` datetime DEFAULT current_timestamp(),
  `status` varchar(20) DEFAULT 'assigned',
  `seen` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `exam_assignments`
--

INSERT INTO `exam_assignments` (`id`, `exam_id`, `student_id`, `assigned_at`, `status`, `seen`) VALUES
(1, 1, 1, '2025-05-26 13:04:13', 'assigned', 0),
(2, 2, 2, '2025-05-26 14:00:49', 'assigned', 1),
(5, 5, 2, '2025-05-26 15:58:48', 'assigned', 1),
(11, 10, 2, '2025-05-27 00:07:51', 'completed', 0),
(12, 11, 2, '2025-05-27 00:25:50', 'completed', 0),
(13, 12, 4, '2025-05-28 10:50:15', 'assigned', 0),
(18, 12, 2, '2025-05-28 10:58:04', 'completed', 0),
(29, 12, 5, '2025-05-28 11:31:50', 'assigned', 0),
(30, 13, 4, '2025-05-28 11:33:21', 'assigned', 0),
(31, 13, 5, '2025-05-28 11:33:21', 'assigned', 0),
(32, 13, 6, '2025-05-28 11:41:12', 'assigned', 0);

-- --------------------------------------------------------

--
-- Table structure for table `exam_attempts`
--

CREATE TABLE `exam_attempts` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `exam_id` int(11) NOT NULL,
  `score` int(11) NOT NULL,
  `started_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `exam_attempts`
--

INSERT INTO `exam_attempts` (`id`, `student_id`, `exam_id`, `score`, `started_at`, `completed_at`) VALUES
(4, 2, 10, 1, '2025-05-27 10:10:17', '2025-05-27 10:10:19'),
(5, 2, 11, 1, '2025-05-27 10:26:44', '2025-05-27 10:26:52'),
(6, 2, 12, 0, '2025-05-28 21:31:19', '2025-05-28 21:31:21');

-- --------------------------------------------------------

--
-- Stand-in structure for view `exam_rankings`
-- (See below for the actual view)
--
CREATE TABLE `exam_rankings` (
`exam_id` int(11)
,`student_id` int(11)
,`score` int(11)
,`rank` bigint(21)
);

-- --------------------------------------------------------

--
-- Table structure for table `exam_sends`
--

CREATE TABLE `exam_sends` (
  `id` int(11) NOT NULL,
  `exam_id` int(11) NOT NULL,
  `year` varchar(50) NOT NULL,
  `section` varchar(50) NOT NULL,
  `department` varchar(100) NOT NULL,
  `sent_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `exam_sends`
--

INSERT INTO `exam_sends` (`id`, `exam_id`, `year`, `section`, `department`, `sent_at`) VALUES
(8, 10, '2nd Year', 'A', 'College of Engineering', '2025-05-27 08:07:51'),
(9, 11, '2nd Year', 'A', 'College of Engineering', '2025-05-27 08:25:50'),
(10, 12, '3rd Year', 'D', 'Bachelor of Science in Information Technology', '2025-05-28 18:50:15'),
(11, 12, '3rd Year', 'D', 'Bachelor of Science in Information Technology', '2025-05-28 18:56:56'),
(12, 12, '3rd Year', 'D', 'Bachelor of Science in Information Technology', '2025-05-28 18:57:09'),
(13, 12, '3rd Year', 'D', 'Bachelor of Science in Information Technology', '2025-05-28 18:57:22'),
(14, 12, '3rd Year', 'D', 'Bachelor of Science in Information Technology', '2025-05-28 18:57:42'),
(15, 12, '2nd Year', 'A', 'College of Engineering', '2025-05-28 18:58:04'),
(16, 12, '3rd Year', 'D', 'Bachelor of Science in Information Technology', '2025-05-28 19:00:41'),
(17, 12, '3rd Year', 'D', 'Bachelor of Science in Information Technology', '2025-05-28 19:04:27'),
(18, 12, '3rd Year', 'D', 'Bachelor of Science in Information Technology', '2025-05-28 19:10:54'),
(19, 12, '3rd Year', 'D', 'Bachelor of Science in Information Technology', '2025-05-28 19:11:22'),
(20, 12, '3rd Year', 'D', 'Bachelor of Science in Information Technology', '2025-05-28 19:11:34'),
(21, 12, '3rd Year', 'D', 'Bachelor of Science in Information Technology', '2025-05-28 19:24:53'),
(22, 12, '3rd Year', 'D', 'Bachelor of Science in Information Technology', '2025-05-28 19:25:19'),
(23, 12, '3rd Year', 'D', 'Bachelor of Science in Information Technology', '2025-05-28 19:29:04'),
(24, 12, '3rd Year', 'D', 'Bachelor of Science in Information Technology', '2025-05-28 19:29:38'),
(25, 12, '3rd Year', 'D', 'Bachelor of Science in Information Technology', '2025-05-28 19:31:50'),
(26, 13, '3rd Year', 'D', 'Bachelor of Science in Information Technology', '2025-05-28 19:33:21'),
(27, 13, '2nd Year', 'D', 'Bachelor of Science in Information Technology', '2025-05-28 19:41:12');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `exam_id` int(11) NOT NULL,
  `message` varchar(255) NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `student_id`, `exam_id`, `message`, `is_read`, `created_at`) VALUES
(3, 2, 10, 'New exam assigned: HAHA', 1, '2025-05-27 08:07:51'),
(4, 2, 11, 'New exam assigned: haha', 1, '2025-05-27 08:25:50'),
(5, 4, 12, 'New exam assigned: Basta Exam', 0, '2025-05-28 18:50:15'),
(6, 2, 12, 'New exam assigned: Basta Exam', 1, '2025-05-28 18:58:04'),
(7, 5, 12, 'New exam assigned: Basta Exam', 0, '2025-05-28 19:31:50'),
(8, 4, 13, 'New exam assigned: basta exam nasad', 0, '2025-05-28 19:33:21'),
(9, 5, 13, 'New exam assigned: basta exam nasad', 0, '2025-05-28 19:33:21'),
(10, 6, 13, 'New exam assigned: basta exam nasad', 0, '2025-05-28 19:41:12');

-- --------------------------------------------------------

--
-- Table structure for table `questions`
--

CREATE TABLE `questions` (
  `id` int(11) NOT NULL,
  `exam_id` int(11) NOT NULL,
  `question_text` text NOT NULL,
  `option_a` text NOT NULL,
  `option_b` text NOT NULL,
  `option_c` text NOT NULL,
  `option_d` text NOT NULL,
  `correct_option` enum('A','B','C','D') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `questions`
--

INSERT INTO `questions` (`id`, `exam_id`, `question_text`, `option_a`, `option_b`, `option_c`, `option_d`, `correct_option`) VALUES
(6, 10, 'wewew', 'a', 'b', 'c', 'd', 'B'),
(7, 11, 'wewew', 'a', 'b', 'c', 'd', 'B'),
(8, 12, 'wew', 'w', 'd', 'd', 's', 'C'),
(9, 13, 'we', 'a', 'w', 'e', 's', 'B');

-- --------------------------------------------------------

--
-- Table structure for table `students`
--

CREATE TABLE `students` (
  `id` int(11) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `year_level` enum('1st Year','2nd Year','3rd Year','4th Year') NOT NULL,
  `section` enum('A','B','C','D','E') NOT NULL,
  `registration_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `department` varchar(255) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `students`
--

INSERT INTO `students` (`id`, `full_name`, `email`, `password`, `year_level`, `section`, `registration_date`, `department`) VALUES
(2, 'Sample Student', 'student@gmail.com', '$2y$10$tfW0MD4OLlppmf2EuBCoU.ctCcWAVNnDLLG1iYrBy4ZHxAtGrkn8O', '2nd Year', 'A', '2025-05-26 21:59:46', 'College of Engineering'),
(4, 'Yhansey', 'yhansey@yahoo.com', '$2y$10$59V8nRRxdjlVbewAdjkAaeRC.FRUlnHDPikNugqpcqN/OD1rM1ZyC', '3rd Year', 'D', '2025-05-28 18:48:31', 'Bachelor of Science in Information Technology'),
(5, 'Anya', 'anya@gmail.com', '$2y$10$zh2.EqEqm4isB4R3O3tjrOmYegrqNvCqWBlD8IcSrmFUd/L4yJ36.', '3rd Year', 'D', '2025-05-28 19:30:51', 'Bachelor of Science in Information Technology'),
(6, 'Anya', 'Anya123@gmail.com', '$2y$10$SwBePkuz7iocpPrw46VTLePMyFNOPYCAfl597UpXf9Mw5/vX/mZPm', '2nd Year', 'D', '2025-05-28 19:40:17', 'Bachelor of Science in Information Technology');

-- --------------------------------------------------------

--
-- Table structure for table `student_scores`
--

CREATE TABLE `student_scores` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `exam_id` int(11) NOT NULL,
  `score` float NOT NULL,
  `date_taken` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `student_scores`
--

INSERT INTO `student_scores` (`id`, `student_id`, `exam_id`, `score`, `date_taken`) VALUES
(4, 2, 10, 100, '2025-05-27 08:10:19'),
(5, 2, 11, 100, '2025-05-27 08:26:52'),
(6, 2, 12, 0, '2025-05-28 19:31:21');

-- --------------------------------------------------------

--
-- Structure for view `exam_rankings`
--
DROP TABLE IF EXISTS `exam_rankings`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `exam_rankings`  AS SELECT `exam_attempts`.`exam_id` AS `exam_id`, `exam_attempts`.`student_id` AS `student_id`, `exam_attempts`.`score` AS `score`, rank() over ( partition by `exam_attempts`.`exam_id` order by `exam_attempts`.`score` desc) AS `rank` FROM `exam_attempts` ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admins`
--
ALTER TABLE `admins`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `exams`
--
ALTER TABLE `exams`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `exam_assignments`
--
ALTER TABLE `exam_assignments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_exam_student` (`exam_id`,`student_id`);

--
-- Indexes for table `exam_attempts`
--
ALTER TABLE `exam_attempts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `exam_id` (`exam_id`);

--
-- Indexes for table `exam_sends`
--
ALTER TABLE `exam_sends`
  ADD PRIMARY KEY (`id`),
  ADD KEY `exam_id` (`exam_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `exam_id` (`exam_id`);

--
-- Indexes for table `questions`
--
ALTER TABLE `questions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `exam_id` (`exam_id`);

--
-- Indexes for table `students`
--
ALTER TABLE `students`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `student_scores`
--
ALTER TABLE `student_scores`
  ADD PRIMARY KEY (`id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `exam_id` (`exam_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admins`
--
ALTER TABLE `admins`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `exams`
--
ALTER TABLE `exams`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `exam_assignments`
--
ALTER TABLE `exam_assignments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=33;

--
-- AUTO_INCREMENT for table `exam_attempts`
--
ALTER TABLE `exam_attempts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `exam_sends`
--
ALTER TABLE `exam_sends`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `questions`
--
ALTER TABLE `questions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `students`
--
ALTER TABLE `students`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `student_scores`
--
ALTER TABLE `student_scores`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `exams`
--
ALTER TABLE `exams`
  ADD CONSTRAINT `exams_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `admins` (`id`);

--
-- Constraints for table `exam_attempts`
--
ALTER TABLE `exam_attempts`
  ADD CONSTRAINT `exam_attempts_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`),
  ADD CONSTRAINT `exam_attempts_ibfk_2` FOREIGN KEY (`exam_id`) REFERENCES `exams` (`id`);

--
-- Constraints for table `exam_sends`
--
ALTER TABLE `exam_sends`
  ADD CONSTRAINT `exam_sends_ibfk_1` FOREIGN KEY (`exam_id`) REFERENCES `exams` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`),
  ADD CONSTRAINT `notifications_ibfk_2` FOREIGN KEY (`exam_id`) REFERENCES `exams` (`id`);

--
-- Constraints for table `questions`
--
ALTER TABLE `questions`
  ADD CONSTRAINT `questions_ibfk_1` FOREIGN KEY (`exam_id`) REFERENCES `exams` (`id`);

--
-- Constraints for table `student_scores`
--
ALTER TABLE `student_scores`
  ADD CONSTRAINT `student_scores_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `student_scores_ibfk_2` FOREIGN KEY (`exam_id`) REFERENCES `exams` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
