-- =============================================
-- INDOXUS COMMUNICATIONS DATABASE SCHEMA
-- Contact Form Submissions Storage
-- =============================================

-- Create database
CREATE DATABASE IF NOT EXISTS indoxus_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE indoxus_db;

-- Contact submissions table
CREATE TABLE IF NOT EXISTS contact_submissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    job_title VARCHAR(255),
    company VARCHAR(255),
    country VARCHAR(100),
    service VARCHAR(255) DEFAULT 'General Inquiry',
    message TEXT NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    status ENUM('new', 'read', 'replied', 'archived') DEFAULT 'new',
    submitted_at DATETIME NOT NULL,
    read_at DATETIME NULL,
    replied_at DATETIME NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_status (status),
    INDEX idx_submitted_at (submitted_at),
    INDEX idx_company (company),
    INDEX idx_country (country)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Service inquiry tracking table
CREATE TABLE IF NOT EXISTS service_inquiries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    submission_id INT,
    service_name VARCHAR(255) NOT NULL,
    inquiry_date DATETIME NOT NULL,
    FOREIGN KEY (submission_id) REFERENCES contact_submissions(id) ON DELETE CASCADE,
    INDEX idx_service_name (service_name),
    INDEX idx_inquiry_date (inquiry_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Email logs table
CREATE TABLE IF NOT EXISTS email_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    submission_id INT,
    email_type ENUM('admin_notification', 'user_confirmation') NOT NULL,
    recipient_email VARCHAR(255) NOT NULL,
    subject VARCHAR(500),
    sent_at DATETIME NOT NULL,
    status ENUM('sent', 'failed') DEFAULT 'sent',
    error_message TEXT,
    FOREIGN KEY (submission_id) REFERENCES contact_submissions(id) ON DELETE CASCADE,
    INDEX idx_email_type (email_type),
    INDEX idx_sent_at (sent_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Admin users table (for future CRM access)
CREATE TABLE IF NOT EXISTS admin_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL UNIQUE,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(255),
    role ENUM('admin', 'manager', 'staff') DEFAULT 'staff',
    is_active BOOLEAN DEFAULT TRUE,
    last_login DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_username (username),
    INDEX idx_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default admin user (password: admin123 - CHANGE THIS!)
INSERT INTO admin_users (username, email, password_hash, full_name, role) VALUES
('admin', 'admin@indoxus.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrator', 'admin');

-- =============================================
-- USEFUL QUERIES
-- =============================================

-- View all submissions (most recent first)
-- SELECT * FROM contact_submissions ORDER BY submitted_at DESC;

-- View unread submissions
-- SELECT * FROM contact_submissions WHERE status = 'new' ORDER BY submitted_at DESC;

-- Count submissions by service
-- SELECT service, COUNT(*) as count FROM contact_submissions GROUP BY service;

-- View submissions from last 7 days
-- SELECT * FROM contact_submissions WHERE submitted_at >= DATE_SUB(NOW(), INTERVAL 7 DAY);

-- Mark submission as read
-- UPDATE contact_submissions SET status = 'read', read_at = NOW() WHERE id = ?;

-- Mark submission as replied
-- UPDATE contact_submissions SET status = 'replied', replied_at = NOW() WHERE id = ?;

-- Get submission statistics
-- SELECT 
--     COUNT(*) as total_submissions,
--     SUM(CASE WHEN status = 'new' THEN 1 ELSE 0 END) as new_submissions,
--     SUM(CASE WHEN status = 'read' THEN 1 ELSE 0 END) as read_submissions,
--     SUM(CASE WHEN status = 'replied' THEN 1 ELSE 0 END) as replied_submissions
-- FROM contact_submissions;

-- =============================================
-- BACKUP COMMAND
-- =============================================
-- mysqldump -u username -p indoxus_db > indoxus_backup_$(date +%Y%m%d).sql

-- =============================================
-- MAINTENANCE
-- =============================================

-- Clean up old archived submissions (older than 1 year)
-- DELETE FROM contact_submissions WHERE status = 'archived' AND submitted_at < DATE_SUB(NOW(), INTERVAL 1 YEAR);

-- Optimize tables
-- OPTIMIZE TABLE contact_submissions, service_inquiries, email_logs, admin_users;
