CREATE DATABASE IF NOT EXISTS esmartlib_rhsti CHARACTER SET utf8mb4;
USE esmartlib_rhsti;
CREATE TABLE users(id INT AUTO_INCREMENT PRIMARY KEY, username VARCHAR(50) UNIQUE, password VARCHAR(255), role ENUM('admin','librarian','student') DEFAULT 'librarian', student_id INT NULL);
CREATE TABLE students(id INT AUTO_INCREMENT PRIMARY KEY, student_no VARCHAR(30) UNIQUE NOT NULL, name VARCHAR(120) NOT NULL, gender VARCHAR(10), track VARCHAR(60), strand VARCHAR(60), grade VARCHAR(10), section VARCHAR(30));
CREATE TABLE books(id INT AUTO_INCREMENT PRIMARY KEY, accession_no VARCHAR(30) UNIQUE NOT NULL, title VARCHAR(200) NOT NULL, author VARCHAR(120), publisher VARCHAR(120), category VARCHAR(60), quantity INT DEFAULT 1, available INT DEFAULT 1);
CREATE TABLE borrowings(id INT AUTO_INCREMENT PRIMARY KEY, student_id INT NOT NULL, book_id INT NOT NULL, borrowed_at DATETIME NOT NULL, due_date DATE NOT NULL, returned_at DATETIME NULL, FOREIGN KEY(student_id) REFERENCES students(id), FOREIGN KEY(book_id) REFERENCES books(id));
CREATE TABLE attendance(id INT AUTO_INCREMENT PRIMARY KEY, student_id INT NOT NULL, time_in DATETIME NOT NULL, time_out DATETIME NULL, FOREIGN KEY(student_id) REFERENCES students(id));
