-- Run this ONLY if you already imported the old database.sql
USE esmartlib_rhsti;
ALTER TABLE users MODIFY role ENUM('admin','librarian','student') DEFAULT 'librarian';
ALTER TABLE users ADD COLUMN student_id INT NULL;
