-- phpMyAdmin SQL Dump
-- version 4.9.0.1
-- https://www.phpmyadmin.net/
--
-- Host: sql210.infinityfree.com
-- Generation Time: Nov 11, 2026 at 05:09 AM
-- Server version: 11.4.13-MariaDB
-- PHP Version: 7.2.22

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `if0_43274999_database`
--

-- --------------------------------------------------------

--
-- Table structure for table `account_email_verifications`
--

CREATE TABLE `account_email_verifications` (
  `user_id` int(11) NOT NULL,
  `email` varchar(254) NOT NULL,
  `code_hash` varchar(255) NOT NULL,
  `created_at` datetime NOT NULL,
  `expires_at` datetime NOT NULL,
  `attempts` tinyint(3) UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `attendance`
--

CREATE TABLE `attendance` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `time_in` datetime NOT NULL,
  `time_out` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `attendance`
--

INSERT INTO `attendance` (`id`, `student_id`, `time_in`, `time_out`) VALUES
(2, 1, '2026-10-28 13:32:47', '2026-10-28 18:10:36'),
(11, 1, '2026-11-03 13:57:11', NULL),
(12, 1, '2026-11-03 13:57:22', '2026-11-03 17:28:33'),
(13, 96, '2026-11-09 18:21:21', '2026-11-09 18:21:55'),
(14, 96, '2026-11-09 18:25:08', '2026-11-09 18:25:35'),
(15, 96, '2026-11-09 18:26:50', '2026-11-09 18:27:04'),
(16, 116, '2026-11-10 09:12:38', NULL),
(17, 116, '2026-11-10 09:13:16', NULL),
(18, 116, '2026-11-10 09:14:33', NULL),
(19, 116, '2026-11-10 09:14:54', NULL),
(20, 96, '2026-11-10 09:26:12', NULL),
(21, 96, '2026-11-10 09:26:33', NULL),
(22, 96, '2026-11-10 09:27:50', NULL),
(23, 1, '2026-11-10 09:36:33', '2026-11-10 09:36:46'),
(24, 99, '2026-11-10 09:38:51', NULL),
(25, 93, '2026-11-10 09:39:06', '2026-11-10 09:39:34'),
(26, 108, '2026-11-10 09:39:46', '2026-11-10 09:39:55');

-- --------------------------------------------------------

--
-- Table structure for table `books`
--

CREATE TABLE `books` (
  `id` int(11) NOT NULL,
  `accession_no` varchar(30) NOT NULL,
  `title` varchar(200) NOT NULL,
  `author` varchar(120) DEFAULT NULL,
  `publisher` varchar(120) DEFAULT NULL,
  `category` varchar(60) DEFAULT NULL,
  `quantity` int(11) DEFAULT 1,
  `available` int(11) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `books`
--

INSERT INTO `books` (`id`, `accession_no`, `title`, `author`, `publisher`, `category`, `quantity`, `available`) VALUES
(1, 'BK-0001', 'Noli Me Tangere', 'Jose Rizal', 'Anvil Publishing', 'Filipino Literature', 5, 5),
(2, 'BK-0002', 'El Filibusterismo', 'Jose Rizal', 'Anvil Publishing', 'Filipino Literature', 5, 5),
(3, 'BK-0003', 'Florante at Laura', 'Francisco Balagtas', 'Vibal Group', 'Filipino Literature', 3, 3),
(4, 'BK-0004', 'Harry Potter and the Sorcerer\'s Stone', 'J.K. Rowling', 'Scholastic', 'Fiction', 4, 4),
(5, 'BK-0005', 'The Hobbit', 'J.R.R. Tolkien', 'HarperCollins', 'Fiction', 3, 3),
(6, 'BK-0006', 'Charlotte\'s Web', 'E.B. White', 'Harper & Brothers', 'Children\'s Books', 2, 2),
(7, 'BK-0007', 'Diary of a Wimpy Kid', 'Jeff Kinney', 'Amulet Books', 'Children\'s Books', 4, 4),
(8, 'BK-0008', 'A Brief History of Time', 'Stephen Hawking', 'Bantam Books', 'Science', 2, 2),
(9, 'BK-0009', 'The Diary of a Young Girl', 'Anne Frank', 'Contact Publishing', 'Biography', 3, 3),
(10, 'BK-0010', 'Rich Dad Poor Dad', 'Robert Kiyosaki', 'Plata Publishing', 'Business', 2, 2),
(11, 'BK-0011', 'Atomic Habits', 'James Clear', 'Avery', 'Self-Help', 3, 3),
(12, 'BK-0012', 'Introduction to Computer Science', 'Various Authors', 'Rex Bookstore', 'Technology', 6, 6),
(13, 'BK-0013', 'Basic Algebra', 'Various Authors', 'Phoenix Publishing House', 'Mathematics', 10, 10),
(14, 'BK-0014', 'Philippine History', 'Various Authors', 'Vibal Group', 'History', 8, 8),
(15, 'BK-0015', 'English Grammar and Composition', 'Various Authors', 'Rex Bookstore', 'Language', 7, 7);

-- --------------------------------------------------------

--
-- Table structure for table `book_requests`
--

CREATE TABLE `book_requests` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `book_id` int(11) NOT NULL,
  `message` varchar(500) NOT NULL DEFAULT '',
  `admin_reply` varchar(500) DEFAULT NULL,
  `status` enum('pending','approved','declined','cancelled','fulfilled') NOT NULL DEFAULT 'pending',
  `requested_at` datetime NOT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `reviewed_by` int(11) DEFAULT NULL,
  `replied_at` datetime DEFAULT NULL,
  `replied_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `book_requests`
--

INSERT INTO `book_requests` (`id`, `student_id`, `book_id`, `message`, `admin_reply`, `status`, `requested_at`, `reviewed_at`, `reviewed_by`, `replied_at`, `replied_by`) VALUES
(1, 1, 37, '', 'ayaw ko', 'approved', '2026-11-05 19:55:21', '2026-11-05 20:03:14', 1, '2026-11-05 20:03:14', 1),
(2, 93, 8, 'Pahiram daw', 'Yuko nga', 'declined', '2026-11-05 07:56:47', '2026-11-06 02:54:50', 1, '2026-11-06 02:54:58', 1);

-- --------------------------------------------------------

--
-- Table structure for table `borrowings`
--

CREATE TABLE `borrowings` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `book_id` int(11) NOT NULL,
  `borrowed_at` datetime NOT NULL,
  `due_date` date NOT NULL,
  `returned_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `borrowings`
--

INSERT INTO `borrowings` (`id`, `student_id`, `book_id`, `borrowed_at`, `due_date`, `returned_at`) VALUES
(1, 3, 8, '2026-11-03 13:39:17', '2026-11-10', '2026-11-05 21:36:38'),
(2, 1, 8, '2026-11-05 21:38:44', '2026-11-12', '2026-11-05 21:44:19'),
(3, 96, 7, '2026-11-09 18:37:10', '2026-11-16', '2026-11-09 18:40:31');

-- --------------------------------------------------------

--
-- Table structure for table `chat_conversations`
--

CREATE TABLE `chat_conversations` (
  `id` int(11) NOT NULL,
  `user_one` int(11) NOT NULL,
  `user_two` int(11) NOT NULL,
  `created_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `chat_conversations`
--

INSERT INTO `chat_conversations` (`id`, `user_one`, `user_two`, `created_at`) VALUES
(1, 1, 3, '2026-11-05 20:05:06'),
(2, 1, 2, '2026-11-05 20:05:57'),
(3, 1, 7, '2026-11-05 20:06:06'),
(5, 1, 9, '2026-11-05 05:30:27'),
(6, 1, 18, '2026-11-07 20:48:22'),
(7, 10, 24, '2026-11-08 15:37:43'),
(8, 18, 21, '2026-11-08 20:48:53');

-- --------------------------------------------------------

--
-- Table structure for table `chat_messages`
--

CREATE TABLE `chat_messages` (
  `id` bigint(20) NOT NULL,
  `conversation_id` int(11) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `created_at` datetime NOT NULL,
  `read_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `chat_messages`
--

INSERT INTO `chat_messages` (`id`, `conversation_id`, `sender_id`, `message`, `created_at`, `read_at`) VALUES
(1, 1, 1, 'JCBSVJHVSAC', '2026-11-05 20:08:39', '2026-11-05 20:09:03'),
(2, 1, 1, 'VDHASAHYVCSAC', '2026-11-05 20:08:41', '2026-11-05 20:09:03'),
(3, 1, 1, 'BDAVDAYASD', '2026-11-05 20:08:42', '2026-11-05 20:09:03'),
(4, 1, 1, 'vadddaad', '2026-11-05 05:30:56', '2026-11-05 05:31:36'),
(5, 1, 1, 'vsfgsgd', '2026-11-05 05:30:59', '2026-11-05 05:31:36'),
(6, 6, 18, 'Sup', '2026-11-07 20:48:25', '2026-11-07 20:54:31'),
(7, 6, 18, 'Lupet may chat', '2026-11-07 20:48:37', '2026-11-07 20:54:31'),
(8, 6, 18, 'Hahahahaha', '2026-11-07 20:48:42', '2026-11-07 20:54:31'),
(9, 7, 10, 'Toyy musta', '2026-11-08 15:37:53', NULL),
(10, 8, 21, 'BOBO KA', '2026-11-08 20:48:56', '2026-11-08 20:49:06'),
(11, 6, 1, 'hahaha lupit noh', '2026-11-09 16:10:38', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `login_attempts`
--

CREATE TABLE `login_attempts` (
  `id` int(11) NOT NULL,
  `ip` varchar(45) NOT NULL,
  `username` varchar(100) NOT NULL,
  `tried_at` datetime NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `login_attempts`
--

INSERT INTO `login_attempts` (`id`, `ip`, `username`, `tried_at`) VALUES
(37, '136.158.100.0', '23063', '2026-11-10 21:37:17'),
(38, '136.158.100.0', '22', '2026-11-11 10:34:15'),
(39, '136.158.176.50', '2309262', '2026-11-11 12:06:57'),
(40, '136.158.176.50', '2309262', '2026-11-11 12:07:09');

-- --------------------------------------------------------

--
-- Table structure for table `offline_sync_operations`
--

CREATE TABLE `offline_sync_operations` (
  `op_id` char(36) NOT NULL,
  `actor_user_id` int(11) NOT NULL,
  `operation_type` varchar(24) NOT NULL,
  `status` varchar(16) NOT NULL,
  `result_json` mediumtext DEFAULT NULL,
  `created_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `password_reset_codes`
--

CREATE TABLE `password_reset_codes` (
  `user_id` int(11) NOT NULL,
  `code_hash` varchar(255) DEFAULT NULL,
  `requested_at` datetime NOT NULL,
  `expires_at` datetime DEFAULT NULL,
  `attempts` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `password_reset_codes`
--

INSERT INTO `password_reset_codes` (`user_id`, `code_hash`, `requested_at`, `expires_at`, `attempts`, `approved_by`, `approved_at`) VALUES
(3, '$2y$12$GpapRt.if8eWnmog6NIuluy9xEeVA9iVYtFbzcz7wXEkeQfhVwedC', '2026-11-04 21:51:39', '2026-11-04 22:03:10', 0, 1, '2026-11-04 05:53:11'),
(10, '$2y$12$T5di5M/qSz1VsAsJQku3v.d17tEDH3jFtS3u.B9/JsRXnEsubm2Xi', '2026-11-08 12:37:00', '2026-11-08 20:12:39', 0, 1, '2026-11-08 04:02:40');

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `k` varchar(64) NOT NULL,
  `v` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `settings`
--

INSERT INTO `settings` (`k`, `v`) VALUES
('library_name', 'Scan Go'),
('loan_days', '3'),
('max_loans', '3'),
('school_name', 'RHSTI Library');

-- --------------------------------------------------------

--
-- Table structure for table `students`
--

CREATE TABLE `students` (
  `id` int(11) NOT NULL,
  `student_no` varchar(30) NOT NULL,
  `name` varchar(120) NOT NULL,
  `gender` varchar(10) DEFAULT NULL,
  `track` varchar(60) DEFAULT NULL,
  `strand` varchar(60) DEFAULT NULL,
  `grade` varchar(10) DEFAULT NULL,
  `section` varchar(30) DEFAULT NULL,
  `photo` varchar(255) DEFAULT NULL,
  `account_active` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `students`
--

INSERT INTO `students` (`id`, `student_no`, `name`, `gender`, `track`, `strand`, `grade`, `section`, `photo`, `account_active`) VALUES
(1, '230630', 'Ahron Melvin Rada', 'Male', 'computer science', 'css', '11', 'block A', 'uploads/students/s1_1792993896.jpg', 1),
(3, '2026-0215', 'ABANES, TERENCE A.', 'Male', 'Academic', '', 'Grade 11', 'Garnet', NULL, 0),
(93, '230924', 'John Vincent Jalimao', 'Male', 'ICT', 'ccs', '11', 'Block A', 'uploads/students/s93_1793894158.jpg', 1),
(96, '230666', 'Karen claire Asis', 'Female', 'Computer Science', 'Css', '11', 'Block A', 'uploads/students/s96_1793924594.jpg', 1),
(97, '230705', 'John lloyd Sulit', 'Male', 'ICT', 'ccs', '11', 'Block A', 'uploads/students/s97_1793961446.jpg', 1),
(99, '230662', 'Jake Laguador', 'Male', 'ICT', 'ccs', '11', 'Block A', 'uploads/students/s99_1793962962.jpg', 1),
(102, '211376', 'Jhoncarlo', 'Male', 'ICT', 'CCS', '11', 'Block A', NULL, 1),
(107, '233577', 'Francis Bacos', 'Male', 'ICT', 'ccs', '11', 'Block A', 'uploads/students/s107_1794112546.jpg', 1),
(108, '230926', 'Ken James Era', 'Male', 'ICT', 'ccs', '11', 'Block A', 'uploads/students/s108_1794113254.jpg', 1),
(112, '230917', 'King Jasper Lozano', 'Male', 'ICT', 'ccs', '11', 'Block A', 'uploads/students/s112_1794370745.jpg', 1),
(113, '230811', 'TIM JAZTIN SARTE', 'Male', 'ICT', 'ccs', '11', 'Block A', 'uploads/students/s113_1794120098.jpg', 1),
(114, '230346', 'Alexander', 'Male', 'Tvl', 'ccs', '11', 'Block A', NULL, 1),
(116, '230818', 'Eduard john N.Entong', 'Male', 'ICT', 'ccs', '11', 'Block A', 'uploads/students/s116_1794272843.jpg', 1);

-- --------------------------------------------------------

--
-- Table structure for table `student_login_locks`
--

CREATE TABLE `student_login_locks` (
  `student_id` int(11) NOT NULL,
  `failed_attempts` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `locked_until` bigint(20) UNSIGNED DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `student_login_locks`
--

INSERT INTO `student_login_locks` (`student_id`, `failed_attempts`, `locked_until`) VALUES
(1, 0, NULL),
(91, 0, NULL),
(3, 0, NULL),
(92, 0, NULL),
(93, 0, NULL),
(94, 3, NULL),
(96, 0, NULL),
(97, 0, NULL),
(99, 0, NULL),
(100, 1, NULL),
(102, 2, NULL),
(103, 0, NULL),
(104, 0, NULL),
(107, 0, NULL),
(108, 0, NULL),
(111, 0, NULL),
(113, 0, NULL),
(112, 0, NULL),
(114, 0, NULL),
(116, 0, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `role` enum('admin','librarian','student') DEFAULT 'librarian',
  `student_id` int(11) DEFAULT NULL,
  `must_change` tinyint(1) NOT NULL DEFAULT 0,
  `email` varchar(254) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `role`, `student_id`, `must_change`, `email`) VALUES
(1, 'ahronmelvinrada', '$2y$12$DY0WRlVZ/oHNtcvDZanmHO3GuvXBfW1Kpo/gVUehabxw1jIGe34y2', 'admin', NULL, 0, NULL),
(2, 'ivyjanebocoron', '$2y$12$Q8tQ.mAjK8/WtnWJtysYGeMwbQVLs.nOaGlJtmNShfpWmfjjPG1he', 'librarian', NULL, 0, NULL),
(3, '230630', '$2y$10$bwqMQvg6S5gP2capg0uhG.Hg3.Pyx4tzjR6ITV.QD9z/2iGUItIFm', 'student', 1, 0, 'ahronrada737@gmail.com'),
(10, '230924', '$2y$12$Cw9cVZskoBZtr04OeMD4PO9bamc7UKUEqoVfK3tCBLv4RCaHdzYwm', 'student', 93, 0, NULL),
(12, '230666', '$2y$12$Z2YgyscRH17TeYqklBpF.OExvyhhfLG.T6GA9hk5QSsjqm0zEjca6', 'student', 96, 0, 'karenclaireasis6@gmail.com'),
(13, '230705', '$2y$12$bTEytiJuadlfrTY4jttNUeOTfaQV8siUkAwBxXb9mctqsVOwhKy7m', 'student', 97, 0, NULL),
(14, '230662', '$2y$12$CgjMFCJaNMf3NmE/jcFWP.Q3q/IaVtZVeWcg/Gkf.u77cPyZxUglu', 'student', 99, 0, NULL),
(17, '233577', '$2y$12$vTY4KwhHf5kVpI128sr56O5IJ7zTUxEE6XmaLajmrZ/60lHmnxyt2', 'student', 107, 0, NULL),
(18, '230926', '$2y$12$JuxKsWJQnPIPNETZnZx4ruma68e.kHTaT9pFSf9fGlhMLoTUWYG9K', 'student', 108, 0, 'kenjamesera@mabinicolleges.edu.ph'),
(20, '230917', '$2y$12$e9Spm5KYlEa9/gpuyYUSL.cwz7UoePKBB87nVCav0jzLg8EM1uQja', 'student', 112, 0, NULL),
(21, '230811', '$2y$12$eAQWmFJCyRdRJ8L0NcK4Vu.ZIccOmVw7No0jDQLcxjPi.YCRXtxMG', 'student', 113, 0, NULL),
(22, '230346', '$2y$12$8iudWypdd1o3CdILlc3duOJmQZ7c.y.6KOFP5JhJEAUAIOAE/wpLS', 'student', 114, 0, NULL),
(24, 'johnlloydsulit', '$2y$12$IgHUnnKA4mTU8KLFs6aFneJJHcnDjHRJVGjaxCbD4NFgRlHYbGOXG', 'librarian', NULL, 0, NULL),
(25, '230818', '$2y$12$8pflb9Zhug8ByJHgC6htCekcfdvYkZSBCYTcEAMdzhEkZcOhq/B3e', 'student', 116, 0, NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `account_email_verifications`
--
ALTER TABLE `account_email_verifications`
  ADD PRIMARY KEY (`user_id`);

--
-- Indexes for table `attendance`
--
ALTER TABLE `attendance`
  ADD PRIMARY KEY (`id`),
  ADD KEY `student_id` (`student_id`);

--
-- Indexes for table `books`
--
ALTER TABLE `books`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `accession_no` (`accession_no`);

--
-- Indexes for table `book_requests`
--
ALTER TABLE `book_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `k_student_status` (`student_id`,`status`),
  ADD KEY `k_book_status` (`book_id`,`status`),
  ADD KEY `k_requested_at` (`requested_at`);

--
-- Indexes for table `borrowings`
--
ALTER TABLE `borrowings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `book_id` (`book_id`);

--
-- Indexes for table `chat_conversations`
--
ALTER TABLE `chat_conversations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_chat_pair` (`user_one`,`user_two`);

--
-- Indexes for table `chat_messages`
--
ALTER TABLE `chat_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `k_chat_messages` (`conversation_id`,`id`),
  ADD KEY `k_chat_unread` (`conversation_id`,`sender_id`,`read_at`);

--
-- Indexes for table `login_attempts`
--
ALTER TABLE `login_attempts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `k_ip_user` (`ip`,`username`,`tried_at`);

--
-- Indexes for table `offline_sync_operations`
--
ALTER TABLE `offline_sync_operations`
  ADD PRIMARY KEY (`op_id`),
  ADD KEY `k_actor_created` (`actor_user_id`,`created_at`);

--
-- Indexes for table `password_reset_codes`
--
ALTER TABLE `password_reset_codes`
  ADD PRIMARY KEY (`user_id`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`k`);

--
-- Indexes for table `students`
--
ALTER TABLE `students`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `student_no` (`student_no`);

--
-- Indexes for table `student_login_locks`
--
ALTER TABLE `student_login_locks`
  ADD PRIMARY KEY (`student_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `attendance`
--
ALTER TABLE `attendance`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- AUTO_INCREMENT for table `books`
--
ALTER TABLE `books`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=46;

--
-- AUTO_INCREMENT for table `book_requests`
--
ALTER TABLE `book_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `borrowings`
--
ALTER TABLE `borrowings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `chat_conversations`
--
ALTER TABLE `chat_conversations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `chat_messages`
--
ALTER TABLE `chat_messages`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `login_attempts`
--
ALTER TABLE `login_attempts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=41;

--
-- AUTO_INCREMENT for table `students`
--
ALTER TABLE `students`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=117;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `attendance`
--
ALTER TABLE `attendance`
  ADD CONSTRAINT `attendance_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`);

--
-- Constraints for table `borrowings`
--
ALTER TABLE `borrowings`
  ADD CONSTRAINT `borrowings_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`),
  ADD CONSTRAINT `borrowings_ibfk_2` FOREIGN KEY (`book_id`) REFERENCES `books` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
