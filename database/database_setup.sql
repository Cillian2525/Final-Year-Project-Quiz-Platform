-- Quiz System Database Setup

CREATE DATABASE IF NOT EXISTS quiz_system CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE quiz_system;

-- Users table
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'teacher', 'student') NOT NULL DEFAULT 'student',
    status ENUM('active', 'locked') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- default admin user

INSERT INTO users (username, email, password, role, status)
VALUES ('admin', 'admin@quizsystem.com', 'admin123', 'admin', 'active');

-- Email: admin@quizsystem.com
-- Password: admin123





