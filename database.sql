-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 09, 2026 at 04:33 AM
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
-- Database: `database`
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
(1, 3, '2026-10-26 13:10:58', '2026-10-26 19:18:45'),
(2, 1, '2026-10-28 13:32:47', '2026-10-28 18:10:36'),
(3, 1, '2026-11-03 17:07:36', NULL),
(4, 1, '2026-11-08 13:02:32', '2026-11-08 13:07:55'),
(5, 1, '2026-11-08 13:08:08', '2026-11-08 13:08:20');

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
(9, 'The Diary of a Young Girl', 'Anne Frank', 'Biography', '3', 'BK-0009', 1, 1),
(31, 'BK-0001', 'Noli Me Tangere', 'Jose Rizal', 'Anvil Publishing', 'Filipino Literature', 5, 5),
(32, 'BK-0002', 'El Filibusterismo', 'Jose Rizal', 'Anvil Publishing', 'Filipino Literature', 5, 5),
(33, 'BK-0003', 'Florante at Laura', 'Francisco Balagtas', 'Vibal Group', 'Filipino Literature', 3, 3),
(34, 'BK-0004', 'Harry Potter and the Sorcerer\'s Stone', 'J.K. Rowling', 'Scholastic', 'Fiction', 4, 4),
(35, 'BK-0005', 'The Hobbit', 'J.R.R. Tolkien', 'HarperCollins', 'Fiction', 3, 3),
(36, 'BK-0006', 'Charlotte\'s Web', 'E.B. White', 'Harper & Brothers', 'Children\'s Books', 2, 2),
(37, 'BK-0007', 'Diary of a Wimpy Kid', 'Jeff Kinney', 'Amulet Books', 'Children\'s Books', 4, 4),
(38, 'BK-0008', 'A Brief History of Time', 'Stephen Hawking', 'Bantam Books', 'Science', 2, 2),
(39, 'BK-0009', 'The Diary of a Young Girl', 'Anne Frank', 'Contact Publishing', 'Biography', 3, 3),
(40, 'BK-0010', 'Rich Dad Poor Dad', 'Robert Kiyosaki', 'Plata Publishing', 'Business', 2, 2),
(41, 'BK-0011', 'Atomic Habits', 'James Clear', 'Avery', 'Self-Help', 3, 3),
(42, 'BK-0012', 'Introduction to Computer Science', 'Various Authors', 'Rex Bookstore', 'Technology', 6, 6),
(43, 'BK-0013', 'Basic Algebra', 'Various Authors', 'Phoenix Publishing House', 'Mathematics', 10, 10),
(44, 'BK-0014', 'Philippine History', 'Various Authors', 'Vibal Group', 'History', 8, 8),
(45, 'BK-0015', 'English Grammar and Composition', 'Various Authors', 'Rex Bookstore', 'Language', 7, 7);

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
(1, 1, 37, '', 'ayaw ko', 'approved', '2026-11-05 19:55:21', '2026-11-05 20:03:14', 1, '2026-11-05 20:03:14', 1);

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
(1, 3, 9, '2026-10-29 14:15:05', '2026-11-05', '2026-10-29 14:17:37');

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
(3, 1, 7, '2026-11-05 20:06:06');

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
(3, 1, 1, 'BDAVDAYASD', '2026-11-05 20:08:42', '2026-11-05 20:09:03');

-- --------------------------------------------------------

--
-- Table structure for table `login_attempts`
--

CREATE TABLE `login_attempts` (
  `id` int(11) NOT NULL,
  `ip` varchar(45) NOT NULL,
  `username` varchar(100) NOT NULL,
  `tried_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
('library_name', 'SCAN GO'),
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
(1, '230630', 'Ahron Melvin Rada', 'Male', 'academic', 'css', '12', 'persistent', 'uploads/students/s1_1792993896.jpg', 1),
(2, '2026-0036', 'AUSTRIA, REX RAIVEN A.', 'Male', 'Academic', '', 'Grade 11', 'Garnet', NULL, 1),
(3, '2026-0215', 'ABANES, TERENCE A.', 'Male', 'Academic', '', 'Grade 11', 'Garnet', NULL, 1),
(4, '2026-0150', 'CABANELA,  ARCEL E.', 'Male', 'Academic', '', 'Grade 11', 'Garnet', NULL, 1),
(5, '2026-0078', 'CASIN, ZAIJAN D.', 'Male', 'Academic', '', 'Grade 11', 'Garnet', NULL, 1),
(6, '2026-0278', 'MAGCAIYO, JED B.', 'Male', 'Academic', '', 'Grade 11', 'Garnet', NULL, 1),
(7, '2026-0034', 'RIVERA, ALVIN JHON R.', 'Male', 'Academic', '', 'Grade 11', 'Garnet', NULL, 1),
(8, '2026-0261', 'SABANAL, MARK DAVID A.', 'Male', 'Academic', '', 'Grade 11', 'Garnet', NULL, 1),
(9, '2026-0035', 'VALENCIA, JOHN MICHAEL S.', 'Male', 'Academic', '', 'Grade 11', 'Garnet', NULL, 1),
(10, '2026-0061', 'ABANTO, MARY ANN L.', 'Female', 'Academic', '', 'Grade 11', 'Garnet', NULL, 1),
(11, '2026-0027', 'ABIADA, KHAYLA S.', 'Female', 'Academic', 'ASSH', 'Grade 11', 'Garnet', NULL, 1),
(12, '2026-0263', 'ABIHAY, JESSA MAE A.', 'Female', 'Academic', '', 'Grade 11', 'Garnet', NULL, 1),
(13, '2026-0104', 'ABOGADO, HASLEN R.', 'Female', 'Academic', '', 'Grade 11', 'Garnet', NULL, 1),
(14, '2026-0049', 'ADLAWAN, LENRAY ANNE L.', 'Female', 'Academic', '', 'Grade 11', 'Garnet', NULL, 1),
(15, '2026-0042', 'AGAO, NORLYN B.', 'Female', 'Academic', '', 'Grade 11', 'Garnet', NULL, 1),
(16, '2026-0041', 'ASIS, RICA MAE D.', 'Female', 'Academic', '', 'Grade 11', 'Garnet', NULL, 1),
(17, '2026-0287', 'AYCARDO, PRINCESS D.', 'Female', 'Academic', '', 'Grade 11', 'Garnet', NULL, 1),
(18, '2026-0046', 'CAJILIG, RYONA LHEN E.', 'Female', 'Academic', '', 'Grade 11', 'Garnet', NULL, 1),
(19, '2026-0040', 'CAÑAS, MARIA EBETH B.', 'Female', 'Academic', '', 'Grade 11', 'Garnet', NULL, 1),
(20, '2026-0102', 'CAÑON, JANEL A.', 'Female', 'Academic', '', 'Grade 11', 'Garnet', NULL, 1),
(21, '2026-0162', 'CARDONA, JAMEL  D.', 'Female', 'Academic', '', 'Grade 11', 'Garnet', NULL, 1),
(22, '2026-0063', 'CHAVEZ, GENICA A.', 'Female', 'Academic', '', 'Grade 11', 'Garnet', NULL, 1),
(23, '2026-', 'CHAY HAN, JARIAH FAYE M.', 'Female', 'Academic', '', 'Grade 11', 'Garnet', NULL, 1),
(24, '2026-0051', 'CLACIO, ANTONETTE M.', 'Female', 'Academic', '', 'Grade 11', 'Garnet', NULL, 1),
(25, '2026-0021', 'GADIL, BABY JANE M.', 'Female', 'Academic', '', 'Grade 11', 'Garnet', NULL, 1),
(26, '2026-0101', 'GALADLAS, RAST G.', 'Female', 'Academic', '', 'Grade 11', 'Garnet', NULL, 1),
(27, '2026-0087', 'IBASCO, JONAHBELLE C.', 'Female', 'Academic', '', 'Grade 11', 'Garnet', NULL, 1),
(28, '2026-0055', 'MABEZA, ALLEN JOY C.', 'Female', 'Academic', '', 'Grade 11', 'Garnet', NULL, 1),
(29, '2026-0308', 'MENESES, FRANCINE M', 'Female', 'Academic', '', 'Grade 11', 'Garnet', NULL, 1),
(30, '2026-0014', 'NAMORO, TRISHA MAE -', 'Female', 'Academic', '', 'Grade 11', 'Garnet', NULL, 1),
(31, '2026-0076', 'NUÑEZ, LIANE ZYRICA -', 'Female', 'Academic', '', 'Grade 11', 'Garnet', NULL, 1),
(32, '2026-0086', 'PERALTA, KATRICE TIFFANY B.', 'Female', 'Academic', '', 'Grade 11', 'Garnet', NULL, 1),
(33, '2026-0088', 'QUIÑONES, ALLYSSA MAE D.', 'Female', 'Academic', '', 'Grade 11', 'Garnet', NULL, 1),
(34, '2026-0058', 'RADA , ERMAM JEAN V.', 'Female', 'Academic', '', 'Grade 11', 'Garnet', NULL, 1),
(35, '2026-0092', 'RAMORES, CATHERINE P.', 'Female', 'Academic', '', 'Grade 11', 'Garnet', NULL, 1),
(36, '2026-0304', 'RAÑOLA, CHASKA MARIE D.', 'Female', 'Academic', '', 'Grade 11', 'Garnet', NULL, 1),
(37, '2026-0093', 'SALCEDO, JESSA MAE C.', 'Female', 'Academic', '', 'Grade 11', 'Garnet', NULL, 1),
(38, '2026-0091', 'TAJA, ANGELICA R.', 'Female', 'Academic', '', 'Grade 11', 'Garnet', NULL, 1),
(39, '2026-0033', 'TOBISI, CLEAH MAE M.', 'Female', 'Academic', '', 'Grade 11', 'Garnet', NULL, 1),
(40, '2026-0077', 'VELASQUEZ, JUSTIN MAE B.', 'Female', 'Academic', '', 'Grade 11', 'Garnet', NULL, 1),
(41, '2026-0280', 'ZAMUDIO, AIRA JADE B.', 'Female', 'Academic', '', 'Grade 11', 'Garnet', NULL, 1),
(42, '2026-0226', 'ABAY, JAMES RUBIEN N.', 'Male', 'TechPro', 'ICT', 'Grade 11', 'Zircon', NULL, 1),
(43, '2026-0292', 'ANGELES, ELLYZER L.', 'Male', 'TechPro', 'ICT', 'Grade 11', 'Zircon', NULL, 1),
(44, '2026-0070', 'ASIS, DAVE C.', 'Male', 'TechPro', 'ICT', 'Grade 11', 'Zircon', NULL, 1),
(45, '2026-0275', 'ASUTILLA, ZHED AARON P.', 'Male', 'TechPro', 'ICT', 'Grade 11', 'Zircon', NULL, 1),
(46, '2026-0291', 'BAÑADERA, JETHRO O.', 'Male', 'TechPro', 'ICT', 'Grade 11', 'Zircon', NULL, 1),
(47, '2026-0183', 'BERJA, LEBRON JAMES D.', 'Male', 'TechPro', 'ICT', 'Grade 11', 'Zircon', NULL, 1),
(48, '2026-0217', 'BOMBITA, KYLA RHYBEN B.', 'Male', 'TechPro', 'ICT', 'Grade 11', 'Zircon', NULL, 1),
(49, '2026-0306', 'BUEFANO, JIMUEL C.', 'Male', 'TechPro', 'ICT', 'Grade 11', 'Zircon', NULL, 1),
(50, '2026-0220', 'CABAGUE, REYNAN C.', 'Male', 'TechPro', 'ICT', 'Grade 11', 'Zircon', NULL, 1),
(51, '2026-0048', 'CACHUELA, KEN JOSEPH R.', 'Male', 'TechPro', 'ICT', 'Grade 11', 'Zircon', NULL, 1),
(52, '2026-0056', 'CASIANO, JOHN JOSEPH R.', 'Male', 'TechPro', 'ICT', 'Grade 11', 'Zircon', NULL, 1),
(53, '2026-0313', 'DACILLO, AVELINO R.', 'Male', 'TechPro', 'ICT', 'Grade 11', 'Zircon', NULL, 1),
(54, '2026-0289', 'DAJERO, REDIN D.', 'Male', 'TechPro', 'ICT', 'Grade 11', 'Zircon', NULL, 1),
(55, '2026-0099', 'EBRON, BRYCE DENIELE T.', 'Male', 'TechPro', 'ICT', 'Grade 11', 'Zircon', NULL, 1),
(56, '2026-0310', 'ESPANOL, CHARLES JOHN D.', 'Male', 'TechPro', 'ICT', 'Grade 11', 'Zircon', NULL, 1),
(57, '2026-0019', 'FERRER, ADRIAN L.', 'Male', 'TechPro', 'ICT', 'Grade 11', 'Zircon', NULL, 1),
(58, '2026-0098', 'IBIAS, EDWIN T.', 'Male', 'TechPro', 'ICT', 'Grade 11', 'Zircon', NULL, 1),
(59, '2026-0252', 'INSUYA, JOHN RUCEL -', 'Male', 'TechPro', 'ICT', 'Grade 11', 'Zircon', NULL, 1),
(60, '2026-0047', 'JARGUE, LEONARD PAUL R.', 'Male', 'TechPro', 'ICT', 'Grade 11', 'Zircon', NULL, 1),
(61, '2026-0296', 'LARIOS, JIAN KIRBY P.', 'Male', 'TechPro', 'ICT', 'Grade 11', 'Zircon', NULL, 1),
(62, '2026-0018', 'LAYA, ROY ANGELO A.', 'Male', 'TechPro', 'ICT', 'Grade 11', 'Zircon', NULL, 1),
(63, '2026-0241', 'LOMBERIO, ERICK L.', 'Male', 'TechPro', 'ICT', 'Grade 11', 'Zircon', NULL, 1),
(64, '2026-0245', 'LOPEZ, MARK ANDRIE N.', 'Male', 'TechPro', 'ICT', 'Grade 11', 'Zircon', NULL, 1),
(65, '2026-0037', 'MALALUAN, JUSTINE WAYNE O.', 'Male', 'TechPro', 'ICT', 'Grade 11', 'Zircon', NULL, 1),
(66, '2026-0038', 'NARIO, MARCO S.', 'Male', 'TechPro', 'ICT', 'Grade 11', 'Zircon', NULL, 1),
(67, '2026-0297', 'PREDICALA, JAMES CLYNE R.', 'Male', 'TechPro', 'ICT', 'Grade 11', 'Zircon', NULL, 1),
(68, '2026-0020', 'RUIZ, DIOMAR JR F.', 'Male', 'TechPro', 'ICT', 'Grade 11', 'Zircon', NULL, 1),
(69, '2026-0274', 'SABACO  JHON HILSEN R.', 'Male', 'TechPro', 'ICT', 'Grade 11', 'Zircon', NULL, 1),
(70, '2026-0302', 'TORREJA, JEROME ASHLEY', 'Male', 'TechPro', 'ICT', 'Grade 11', 'Zircon', NULL, 1),
(71, '2026-0096', 'VALENCIA, JEROME G.', 'Male', 'TechPro', 'ICT', 'Grade 11', 'Zircon', NULL, 1),
(72, '2026-0148', 'ZANTUA, JOHN ELMER H.', 'Male', 'TechPro', 'ICT', 'Grade 11', 'Zircon', NULL, 1),
(73, '2026-0214', 'ALOC, CHRISTINE A.', 'Female', 'TechPro', 'ICT', 'Grade 11', 'Zircon', NULL, 1),
(74, '2026-0144', 'BAROTILLO, LESLY MAE D.', 'Female', 'TechPro', 'ICT', 'Grade 11', 'Zircon', NULL, 1),
(75, '2026-0224', 'CERENO, BEA I.', 'Female', 'TechPro', 'ICT', 'Grade 11', 'Zircon', NULL, 1),
(76, '2026-0167', 'DE LEON, ANNICA MAE B.', 'Female', 'TechPro', 'ICT', 'Grade 11', 'Zircon', NULL, 1),
(77, '2026-0244', 'ESTORNINOS, RIZA MIE D.', 'Female', 'TechPro', 'ICT', 'Grade 11', 'Zircon', NULL, 1),
(78, '2026-0015', 'FRENANDEZ, RIENNE ANDREA A.', 'Female', 'TechPro', 'ICT', 'Grade 11', 'Zircon', NULL, 1),
(79, '2026-0073', 'GUEVARRA, JUDYLYN N.', 'Female', 'TechPro', 'ICT', 'Grade 11', 'Zircon', NULL, 1),
(80, '2026-0097', 'LLORENTE, JENELYN S.', 'Female', 'TechPro', 'ICT', 'Grade 11', 'Zircon', NULL, 1),
(81, '2026-0223', 'LONCERAS, PRINCESS JHARAINE B.', 'Female', 'TechPro', 'ICT', 'Grade 11', 'Zircon', NULL, 1),
(82, '2026-0232', 'MABEZA, DANICA A.', 'Female', 'TechPro', 'ICT', 'Grade 11', 'Zircon', NULL, 1),
(83, '2026-0269', 'MAGANA, TRISHA MAE F.', 'Female', 'TechPro', 'ICT', 'Grade 11', 'Zircon', NULL, 1),
(84, '2026-0259', 'MENESES, CHERRY ROSE P.', 'Female', 'TechPro', 'ICT', 'Grade 11', 'Zircon', NULL, 1),
(85, '2026-0112', 'QUIBRAL, ANLARAINE KATE N.', 'Female', 'TechPro', 'ICT', 'Grade 11', 'Zircon', NULL, 1),
(86, '2026-0074', 'RAMOS, NICA B.', 'Female', 'TechPro', 'ICT', 'Grade 11', 'Zircon', NULL, 1),
(87, '2026-0260', 'SORIA, EMMALYN A.', 'Female', 'TechPro', 'ICT', 'Grade 11', 'Zircon', NULL, 1),
(88, '2026-0201', 'ZANTUA, JHELIAN S.', 'Female', 'TechPro', 'ICT', 'Grade 11', 'Zircon', NULL, 1),
(91, '230634', 'JOAN BERJA', 'Female', 'ACADEMIC', 'CCS', '12', 'POKEMON', NULL, 1),
(92, '2026005', 'ABIADA, KHAYLA S.', 'Female', 'Academic', 'ASSH', '12', 'persistent', NULL, 1),
(94, '230636', 'JOAN ko', 'Female', 'Academic', 'css', '12', 'persistent', NULL, 1);

-- --------------------------------------------------------

--
-- Table structure for table `student_login_locks`
--

CREATE TABLE `student_login_locks` (
  `student_id` int(11) NOT NULL,
  `failed_attempts` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `locked_until` bigint(20) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `student_login_locks`
--

INSERT INTO `student_login_locks` (`student_id`, `failed_attempts`, `locked_until`) VALUES
(1, 0, NULL),
(92, 0, NULL),
(93, 0, NULL);

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
  `email` varchar(254) DEFAULT NULL,
  `email_verified_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `role`, `student_id`, `must_change`, `email`, `email_verified_at`) VALUES
(1, 'ahronmelvinrada', '$2y$12$DY0WRlVZ/oHNtcvDZanmHO3GuvXBfW1Kpo/gVUehabxw1jIGe34y2', 'admin', NULL, 0, NULL, NULL),
(2, 'ivyjanebocoron', '$2y$12$Q8tQ.mAjK8/WtnWJtysYGeMwbQVLs.nOaGlJtmNShfpWmfjjPG1he', 'librarian', NULL, 0, NULL, NULL),
(3, '230630', '$2y$10$bwqMQvg6S5gP2capg0uhG.Hg3.Pyx4tzjR6ITV.QD9z/2iGUItIFm', 'student', 1, 0, NULL, NULL),
(4, '2026-0215', '$2y$10$eEu1aHF0K/4RBlmBrQj4gOTsmsPdKdOBFhKj0HdNe2My5HPKCXfZK', 'student', 3, 0, NULL, NULL),
(7, '230634', '$2y$10$AenNCG9fprcEwdFXb7fvuuiZg6.dVAOTUNV3Xo7mIcXPh7Wzzj3pe', 'student', 91, 0, NULL, NULL),
(8, '2026005', '$2y$10$h31A8rGE4BLeHDKvmN03ouCNG4N3w86iPBUe96JqyN.lIYvhma0Ya', 'student', 92, 1, NULL, NULL),
(10, '230636', '$2y$10$WXOYTJRys5ugdwOe/qA/kOX4pCJgqpOJLl/V5og6ukTb6XVASbP/u', 'student', 94, 0, NULL, NULL);

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
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `users_email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `attendance`
--
ALTER TABLE `attendance`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `books`
--
ALTER TABLE `books`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=46;

--
-- AUTO_INCREMENT for table `book_requests`
--
ALTER TABLE `book_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `borrowings`
--
ALTER TABLE `borrowings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `chat_conversations`
--
ALTER TABLE `chat_conversations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `chat_messages`
--
ALTER TABLE `chat_messages`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `login_attempts`
--
ALTER TABLE `login_attempts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `students`
--
ALTER TABLE `students`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=95;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

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
