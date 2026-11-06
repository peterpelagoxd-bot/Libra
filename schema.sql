-- =======================================================
-- LIBRA - Digital Library Book Borrowing System
-- MySQL / MariaDB Database Schema & Seed Data
-- Database Name: libra
-- =======================================================

CREATE DATABASE IF NOT EXISTS `libra` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `libra`;

-- Disable foreign key checks during drop & recreate
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `user_remember_tokens`;
DROP TABLE IF EXISTS `password_resets`;
DROP TABLE IF EXISTS `activity_logs`;
DROP TABLE IF EXISTS `transactions`;
DROP TABLE IF EXISTS `borrowers`;
DROP TABLE IF EXISTS `books`;
DROP TABLE IF EXISTS `categories`;
DROP TABLE IF EXISTS `users`;

SET FOREIGN_KEY_CHECKS = 1;

-- -------------------------------------------------------
-- 1. Users Account Database (D5)
-- -------------------------------------------------------
CREATE TABLE `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(50) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `full_name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(100) NOT NULL UNIQUE,
  `role` ENUM('Administrator', 'Librarian', 'Borrower') NOT NULL,
  `borrower_id` INT DEFAULT NULL,
  `status` ENUM('Active', 'Inactive', 'Locked') NOT NULL DEFAULT 'Active',
  `failed_attempts` INT NOT NULL DEFAULT 0,
  `locked_until` DATETIME DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------
-- 2. Categories Database
-- -------------------------------------------------------
CREATE TABLE `categories` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL UNIQUE,
  `description` TEXT,
  `icon` VARCHAR(50) DEFAULT 'book',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------
-- 3. Books Database (D1)
-- -------------------------------------------------------
CREATE TABLE `books` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(255) NOT NULL,
  `author` VARCHAR(255) NOT NULL,
  `category_id` INT NOT NULL,
  `isbn` VARCHAR(30) NOT NULL UNIQUE,
  `publisher` VARCHAR(150),
  `published_year` INT,
  `shelf_location` VARCHAR(50),
  `total_copies` INT NOT NULL DEFAULT 1,
  `available_copies` INT NOT NULL DEFAULT 1,
  `description` TEXT,
  `cover_color` VARCHAR(50) DEFAULT 'indigo',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------
-- 4. Borrowers Database (D2)
-- -------------------------------------------------------
CREATE TABLE `borrowers` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `borrower_code` VARCHAR(50) NOT NULL UNIQUE,
  `full_name` VARCHAR(100) NOT NULL,
  `type` ENUM('Student', 'Employee') NOT NULL,
  `department` VARCHAR(100) NOT NULL,
  `email` VARCHAR(100) NOT NULL UNIQUE,
  `phone` VARCHAR(30),
  `status` ENUM('Active', 'Suspended') NOT NULL DEFAULT 'Active',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------
-- 5. Borrowing Transactions & Return Records (D3 & D4)
-- -------------------------------------------------------
CREATE TABLE `transactions` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `transaction_code` VARCHAR(50) NOT NULL UNIQUE,
  `borrower_id` INT NOT NULL,
  `book_id` INT NOT NULL,
  `borrow_date` DATE NOT NULL,
  `due_date` DATE NOT NULL,
  `return_date` DATE DEFAULT NULL,
  `status` ENUM('Requested', 'Borrowed', 'Returned', 'Overdue', 'Cancelled') NOT NULL DEFAULT 'Borrowed',
  `return_condition` VARCHAR(100) DEFAULT NULL,
  `fine_amount` DECIMAL(10,2) DEFAULT 0.00,
  `notes` TEXT,
  `processed_by` INT DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`borrower_id`) REFERENCES `borrowers`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`book_id`) REFERENCES `books`(`id`) ON DELETE RESTRICT,
  FOREIGN KEY (`processed_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------
-- 6. System Audit & Activity Logs
-- -------------------------------------------------------
CREATE TABLE `activity_logs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT DEFAULT NULL,
  `action` VARCHAR(100) NOT NULL,
  `details` TEXT NOT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------
-- 7. Password Reset Tokens
-- -------------------------------------------------------
CREATE TABLE `password_resets` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `token` VARCHAR(255) NOT NULL UNIQUE,
  `expires_at` DATETIME NOT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------
-- 8. User Remember Tokens
-- -------------------------------------------------------
CREATE TABLE `user_remember_tokens` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `token_hash` VARCHAR(255) NOT NULL,
  `expires_at` DATETIME NOT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------
-- Helper Stored Function for Cross-Engine Compatibility
-- Enables Julian Day difference: (JULIANDAY(d1) - JULIANDAY(d2))
-- -------------------------------------------------------
DROP FUNCTION IF EXISTS `JULIANDAY`;
DELIMITER //
CREATE FUNCTION `JULIANDAY`(d DATE) RETURNS INT DETERMINISTIC
BEGIN
  RETURN TO_DAYS(d);
END //
DELIMITER ;

-- =======================================================
-- INITIAL SEED DATA
-- =======================================================

-- Categories
INSERT INTO `categories` (`name`, `description`, `icon`) VALUES
('Computer Science & IT', 'Core computer architecture, programming languages, and operating systems', 'cpu'),
('Software Engineering', 'System design, design patterns, testing, agile methodologies and clean code', 'layers'),
('Data Science & AI', 'Machine learning, artificial intelligence, neural networks, and statistics', 'brain'),
('Database Systems', 'Relational models, distributed databases, transaction processing, and query optimization', 'database'),
('Business & Management', 'Corporate strategy, project management, financial modeling, and leadership', 'briefcase'),
('Literature & Arts', 'Classic literature, humanities, philosophy, and creative writing', 'feather');

-- Borrowers (Students & Employees)
INSERT INTO `borrowers` (`borrower_code`, `full_name`, `type`, `department`, `email`, `phone`, `status`) VALUES
('STU-2026-0042', 'Sophia Chen', 'Student', 'Computer Science Dept.', 'sophia.chen@university.edu', '09552348901', 'Active'),
('EMP-2024-0018', 'Prof. Arthur Pendelton', 'Employee', 'Electrical & Computer Engineering', 'a.pendelton@university.edu', '09557891234', 'Active'),
('STU-2026-0105', 'Alex Rivera', 'Student', 'Business Administration', 'alex.rivera@university.edu', '09554567890', 'Active'),
('STU-2026-0211', 'Emily Watson', 'Student', 'Data Science & Analytics', 'emily.watson@university.edu', '09559012345', 'Active'),
('EMP-2023-0007', 'Dr. Linda Rodriguez', 'Employee', 'School of Mathematics', 'linda.rodriguez@university.edu', '09556789012', 'Active');

-- User Accounts with Verified Passwords:
-- admin     -> Libraadmin
-- librarian -> Libralib
-- student   -> Librastud
-- faculty   -> Librafac
-- alex      -> alex123
INSERT INTO `users` (`username`, `password`, `full_name`, `email`, `role`, `borrower_id`, `status`, `failed_attempts`, `locked_until`) VALUES
('admin', '$2y$10$obie2foN87H7m7jzQoORc.TxPuSP11QGflbCNuKA7ghLgZFjcRh4C', 'Dr. Eleanor Vance', 'admin@library.edu', 'Administrator', NULL, 'Active', 0, NULL),
('librarian', '$2y$10$sfpb/Cwk4jVlZDxtP3xwEelG7/UblYqwmw8yKhBCF7CtMWPTSEDou', 'Marcus Sterling', 'librarian@library.edu', 'Librarian', NULL, 'Active', 0, NULL),
('student', '$2y$10$E73jjKfbh06tbZgw6Qoo2Oc2KVrs1/QV3uJgnDpk.EqmeRGNY7Sbu', 'Sophia Chen', 'sophia.chen@university.edu', 'Borrower', 1, 'Active', 0, NULL),
('faculty', '$2y$10$Wschsar2xbTv/L4EBduzJ.xi3Ox8aGIpKhOY8QuDOuFwegLB9owYS', 'Prof. Arthur Pendelton', 'a.pendelton@university.edu', 'Borrower', 2, 'Active', 0, NULL),
('alex', '$2y$10$eE0m94jF34J5Eflm3o2FuuB6dF7b2i5j8Y3t1B2Q0lK1r5e7T9e/2', 'Alex Rivera', 'alex.rivera@university.edu', 'Borrower', 3, 'Active', 0, NULL);

-- Books
INSERT INTO `books` (`title`, `author`, `category_id`, `isbn`, `publisher`, `published_year`, `shelf_location`, `total_copies`, `available_copies`, `description`, `cover_color`) VALUES
('Clean Code: A Handbook of Agile Software Craftsmanship', 'Robert C. Martin', 2, '978-0132350884', 'Prentice Hall', 2008, 'Shelf A1-04', 5, 3, 'Even bad code can function. But if code is not clean, it can bring a development organization to its knees.', 'indigo'),
('Introduction to Algorithms (4th Edition)', 'Thomas H. Cormen, Charles E. Leiserson, Ronald L. Rivest', 1, '978-0262046305', 'MIT Press', 2022, 'Shelf A2-11', 4, 2, 'A comprehensive update of the leading algorithms text, with new chapters on bipartite graphs, online algorithms, and machine learning.', 'indigo'),
('Artificial Intelligence: A Modern Approach (4th Ed.)', 'Stuart Russell and Peter Norvig', 3, '978-0134610993', 'Pearson', 2020, 'Shelf B1-02', 3, 1, 'The long-anticipated revision of this best-selling comprehensive introduction to the theory and practice of artificial intelligence.', 'indigo'),
('Design Patterns: Elements of Reusable Object-Oriented Software', 'Erich Gamma, Richard Helm, Ralph Johnson, John Vlissides', 2, '978-0201633610', 'Addison-Wesley', 1994, 'Shelf A1-09', 4, 4, 'Capturing a wealth of experience about the design of object-oriented software, four top-notch designers present a catalog of simple and succinct solutions.', 'indigo'),
('Database System Concepts (7th Edition)', 'Abraham Silberschatz, Henry F. Korth, S. Sudarshan', 4, '978-0078022159', 'McGraw-Hill', 2019, 'Shelf A3-05', 3, 2, 'Database System Concepts by Silberschatz, Korth and Sudarshan is now in its 7th edition and is one of the cornerstone texts of database education.', 'indigo'),
('Designing Data-Intensive Applications', 'Martin Kleppmann', 2, '978-1449373320', 'O\'Reilly Media', 2017, 'Shelf B2-08', 4, 3, 'Data is at the center of many challenges in system design today. Difficult issues need to be figured out, such as scalability, consistency, reliability, and maintainability.', 'indigo'),
('The Pragmatic Programmer: 20th Anniversary Edition', 'David Thomas, Andrew Hunt', 2, '978-0135957059', 'Addison-Wesley', 2019, 'Shelf A1-02', 5, 5, 'Straight from the programming trenches, The Pragmatic Programmer cuts through the increasing specialization and technicalities of modern software development.', 'indigo'),
('Deep Learning (Adaptive Computation and Machine Learning)', 'Ian Goodfellow, Yoshua Bengio, Aaron Courville', 3, '978-0262035613', 'MIT Press', 2016, 'Shelf B1-07', 2, 0, 'An introduction to a broad range of topics in deep learning, covering mathematical and conceptual background, deep learning techniques used in industry.', 'indigo'),
('Principles of Corporate Finance (13th Edition)', 'Richard A. Brealey, Stewart C. Myers, Franklin Allen', 5, '978-1260013900', 'McGraw-Hill', 2019, 'Shelf C1-03', 3, 3, 'The integrated solutions for Brealey\'s Principles of Corporate Finance have been specifically designed to help improve student performance.', 'indigo'),
('To Kill a Mockingbird (Special Edition)', 'Harper Lee', 6, '978-0061120084', 'Harper Perennial', 2006, 'Shelf D2-14', 4, 4, 'The unforgettable novel of a childhood in a sleepy Southern town and the crisis of conscience that rocked it.', 'indigo');

-- Transactions
INSERT INTO `transactions` (`transaction_code`, `borrower_id`, `book_id`, `borrow_date`, `due_date`, `return_date`, `status`, `return_condition`, `fine_amount`, `notes`, `processed_by`) VALUES
('TXN-2026-0001', 1, 2, '2026-10-03', '2026-10-17', NULL, 'Overdue', NULL, 20.00, 'Overdue reminder notice dispatched.', 2),
('TXN-2026-0002', 2, 1, '2026-10-18', '2026-11-01', NULL, 'Borrowed', NULL, 0.00, 'Standard 14-day faculty loan.', 2),
('TXN-2026-0003', 3, 3, '2026-10-16', '2026-10-23', NULL, 'Borrowed', NULL, 0.00, 'Midterm study research.', 2),
('TXN-2026-0004', 4, 8, '2026-10-18', '2026-11-01', NULL, 'Borrowed', NULL, 0.00, 'Final year thesis research copy 1.', 2),
('TXN-2026-0005', 5, 8, '2026-10-16', '2026-10-23', NULL, 'Borrowed', NULL, 0.00, 'Instructor reserve copy 2.', 2),
('TXN-2026-0006', 1, 6, '2026-09-26', '2026-10-10', '2026-10-09', 'Returned', 'Excellent - No marks or wear', 0.00, 'Returned early in mint condition.', 2),
('TXN-2026-0007', 1, 1, '2026-10-21', '2026-11-04', NULL, 'Requested', NULL, 0.00, 'Borrower submitted online borrow request via student portal.', NULL);

-- Activity Logs
INSERT INTO `activity_logs` (`user_id`, `action`, `details`) VALUES
(1, 'System Initialization', 'System initialized with default catalogs, categories, and test user accounts.'),
(2, 'Book Checkout', 'Processed checkout of Introduction to Algorithms for Sophia Chen (TXN-2026-0001).'),
(2, 'Book Return', 'Processed return of Designing Data-Intensive Applications for Sophia Chen (TXN-2026-0006).'),
(3, 'Borrow Request', 'Sophia Chen submitted online request to borrow Clean Code (TXN-2026-0007).');
