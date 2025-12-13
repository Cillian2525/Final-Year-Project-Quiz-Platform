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
-- IMPORTANT: The password below is hashed. To generate a new hash for 'admin123', run:
-- php -r "echo password_hash('admin123', PASSWORD_DEFAULT);"
-- Then replace the hash in the INSERT statement below.

INSERT INTO users (username, email, password, role, status)
VALUES ('admin', 'admin@quizsystem.com', '$2y$10$N9qo8uLOickgx2ZMRZoMyeIjZAgcfl7p92ldGxad68LJZdL17lhWy', 'admin', 'active');

-- Email: admin@quizsystem.com
-- Password: admin123 (stored as hash)





