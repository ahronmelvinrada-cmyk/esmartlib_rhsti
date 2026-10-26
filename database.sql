-- eSmartlib RHSTI - database schema
-- I-import sa phpMyAdmin (Import tab) o gamit ang MySQL console.
-- Ligtas i-run ulit: hindi nito buburahin ang kasalukuyang data.

CREATE DATABASE IF NOT EXISTS esmartlib_rhsti
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE esmartlib_rhsti;

SET NAMES utf8mb4;

-- ---------------------------------------------------------------
-- students
-- ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS students (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  student_no VARCHAR(30)  NOT NULL,
  name       VARCHAR(120) NOT NULL,
  gender     VARCHAR(10)  NULL,
  track      VARCHAR(60)  NULL,
  strand     VARCHAR(60)  NULL,
  grade      VARCHAR(10)  NULL,
  section    VARCHAR(30)  NULL,
  UNIQUE KEY uq_students_student_no (student_no),
  KEY idx_students_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------
-- users (admin / librarian / student login)
-- ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  username   VARCHAR(50)  NOT NULL,
  password   VARCHAR(255) NOT NULL,
  role       ENUM('admin','librarian','student') NOT NULL DEFAULT 'librarian',
  student_id INT NULL,
  UNIQUE KEY uq_users_username (username),
  KEY idx_users_student (student_id),
  CONSTRAINT fk_users_student
    FOREIGN KEY (student_id) REFERENCES students(id)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------
-- books
-- ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS books (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  accession_no VARCHAR(30)  NOT NULL,
  title        VARCHAR(200) NOT NULL,
  author       VARCHAR(120) NULL,
  publisher    VARCHAR(120) NULL,
  category     VARCHAR(60)  NULL,
  quantity     INT NOT NULL DEFAULT 1,
  available    INT NOT NULL DEFAULT 1,
  UNIQUE KEY uq_books_accession_no (accession_no),
  KEY idx_books_title (title),
  CONSTRAINT chk_books_quantity  CHECK (quantity >= 0),
  CONSTRAINT chk_books_available CHECK (available >= 0 AND available <= quantity)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------
-- borrowings
-- ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS borrowings (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  student_id  INT      NOT NULL,
  book_id     INT      NOT NULL,
  borrowed_at DATETIME NOT NULL,
  due_date    DATE     NOT NULL,
  returned_at DATETIME NULL,
  KEY idx_borrowings_student_returned (student_id, returned_at),
  KEY idx_borrowings_book_returned (book_id, returned_at),
  KEY idx_borrowings_due (due_date),
  CONSTRAINT fk_borrowings_student
    FOREIGN KEY (student_id) REFERENCES students(id)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_borrowings_book
    FOREIGN KEY (book_id) REFERENCES books(id)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------
-- attendance (Time-In / Time-Out)
-- ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS attendance (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  student_id INT      NOT NULL,
  time_in    DATETIME NOT NULL,
  time_out   DATETIME NULL,
  KEY idx_attendance_student_out (student_id, time_out),
  KEY idx_attendance_time_in (time_in),
  CONSTRAINT fk_attendance_student
    FOREIGN KEY (student_id) REFERENCES students(id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------
-- Default admin account (para makapag-login pagkatapos mag-import)
--   username: admin
--   password: admin123   <-- PALITAN AGAD pagka-login
-- Hindi ito papalitan kung nandiyan na ang "admin".
-- ---------------------------------------------------------------
INSERT INTO users (username, password, role)
VALUES ('admin', '$2y$10$Kp9KXXV0Dr.vwWqO99ejVuMKoek4ZhMM.OG1.f05kV6yuCMw0TYvO', 'admin')
ON DUPLICATE KEY UPDATE username = username;
