-- Final UCSI Digital Library Database Schema
-- With Cart System, Admin Logging, and Enhanced Features

CREATE DATABASE IF NOT EXISTS ucsi_digital_library CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE ucsi_digital_library;

-- Books table (unchanged - matches BookForm.tsx)
CREATE TABLE books (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(500) NOT NULL,
    author VARCHAR(500) NOT NULL,
    subject VARCHAR(100) NOT NULL,
    description TEXT NOT NULL,
    cover_image VARCHAR(500),
    pdf_url VARCHAR(500),
    edition VARCHAR(100),
    publication_date DATE,
    publisher VARCHAR(500),
    isbn VARCHAR(20),
    book_hash VARCHAR(64),
    source VARCHAR(500),
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_title (title(100)),
    INDEX idx_author (author(100)),
    INDEX idx_subject (subject),
    INDEX idx_status (status),
    FULLTEXT INDEX ft_search (title, author, description)
);

-- Student cart system
CREATE TABLE student_carts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    session_id VARCHAR(128) NOT NULL,              -- Browser session ID
    student_email VARCHAR(255),                     -- Optional - filled when they start download
    book_id INT NOT NULL,
    added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP DEFAULT (CURRENT_TIMESTAMP + INTERVAL 24 HOUR), -- Cart expires in 24 hours

    FOREIGN KEY (book_id) REFERENCES books(id) ON DELETE CASCADE,
    INDEX idx_session_id (session_id),
    INDEX idx_student_email (student_email),
    INDEX idx_expires (expires_at),
    UNIQUE KEY unique_session_book (session_id, book_id)
);

-- Cart download sessions (tracks the download process)
CREATE TABLE cart_download_sessions (
    id VARCHAR(128) PRIMARY KEY,                   -- Unique session ID
    student_email VARCHAR(255) NOT NULL,
    total_books INT NOT NULL,
    books_data JSON,                               -- Store book IDs and titles as JSON
    otp_code VARCHAR(6),
    otp_verified BOOLEAN DEFAULT FALSE,
    download_token VARCHAR(128),
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    downloaded_at TIMESTAMP NULL,

    INDEX idx_student_email (student_email),
    INDEX idx_expires (expires_at)
);

-- Students table (enhanced)
CREATE TABLE students (
    id INT PRIMARY KEY AUTO_INCREMENT,
    student_id VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(255) NOT NULL UNIQUE,
    full_name VARCHAR(255),
    total_downloads INT DEFAULT 0,
    last_download_at TIMESTAMP NULL,
    is_verified BOOLEAN DEFAULT FALSE,
    verification_date TIMESTAMP NULL,
    status ENUM('active', 'suspended', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_student_id (student_id),
    INDEX idx_email (email),
    INDEX idx_status (status)
);

-- OTP verifications (enhanced for both download and admin recovery)
CREATE TABLE otp_verifications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    email VARCHAR(255) NOT NULL,
    otp_code VARCHAR(6) NOT NULL,
    purpose ENUM('cart_download', 'admin_recovery') NOT NULL,
    reference_id VARCHAR(128),                     -- Cart session ID or admin ID
    is_used BOOLEAN DEFAULT FALSE,
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    used_at TIMESTAMP NULL,

    INDEX idx_email (email),
    INDEX idx_otp_code (otp_code),
    INDEX idx_expires (expires_at),
    INDEX idx_purpose (purpose),
    INDEX idx_reference_id (reference_id)
);

-- Downloads tracking (enhanced for cart downloads)
CREATE TABLE downloads (
    id INT PRIMARY KEY AUTO_INCREMENT,
    download_session_id VARCHAR(128),              -- Links to cart_download_sessions
    student_email VARCHAR(255) NOT NULL,
    student_id VARCHAR(50),
    book_id INT,                                   -- Individual book (if single download)
    total_books INT DEFAULT 1,                     -- Number of books downloaded
    download_type ENUM('single', 'cart') DEFAULT 'single',
    user_ip VARCHAR(45),
    user_agent TEXT,
    download_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (book_id) REFERENCES books(id) ON DELETE SET NULL,
    INDEX idx_download_session_id (download_session_id),
    INDEX idx_student_email (student_email),
    INDEX idx_download_date (download_date),
    INDEX idx_download_type (download_type)
);

-- Admin users table (enhanced)
CREATE TABLE admin_users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(255) NOT NULL,
    role ENUM('super_admin', 'admin', 'moderator') DEFAULT 'admin',
    last_login TIMESTAMP NULL,
    last_login_ip VARCHAR(45),
    failed_login_attempts INT DEFAULT 0,
    locked_until TIMESTAMP NULL,
    password_reset_required BOOLEAN DEFAULT FALSE,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_email (email),
    INDEX idx_role (role),
    INDEX idx_active (is_active)
);

-- Admin sessions table (enhanced)
CREATE TABLE admin_sessions (
    id VARCHAR(128) PRIMARY KEY,
    admin_id INT NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (admin_id) REFERENCES admin_users(id) ON DELETE CASCADE,
    INDEX idx_admin_id (admin_id),
    INDEX idx_expires (expires_at),
    INDEX idx_last_activity (last_activity)
);

-- Comprehensive admin activity logging
CREATE TABLE admin_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    admin_id INT,
    admin_email VARCHAR(255) NOT NULL,
    action VARCHAR(100) NOT NULL,                  -- 'login', 'logout', 'create_book', 'update_book', 'delete_book', 'upload_file', etc.
    entity_type VARCHAR(50),                       -- 'book', 'user', 'system', etc.
    entity_id INT,                                 -- ID of the affected entity
    details JSON,                                  -- Additional details as JSON
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (admin_id) REFERENCES admin_users(id) ON DELETE SET NULL,
    INDEX idx_admin_id (admin_id),
    INDEX idx_action (action),
    INDEX idx_entity_type (entity_type),
    INDEX idx_created_at (created_at)
);

-- System settings
CREATE TABLE system_settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    description TEXT,
    updated_by INT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (updated_by) REFERENCES admin_users(id) ON DELETE SET NULL,
    INDEX idx_setting_key (setting_key)
);

-- Insert default admin user (password: admin123)
INSERT INTO admin_users (email, password_hash, full_name, role)
VALUES ('admin@ucsi.edu.my', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'UCSI Library Administrator', 'super_admin')
ON DUPLICATE KEY UPDATE email = email;

-- Insert system settings
INSERT INTO system_settings (setting_key, setting_value, description) VALUES
('site_name', 'UCSI DIGITAL LIBRARY Bangladesh Branch', 'Library site name'),
('admin_email', 'admin@ucsi.edu.my', 'Admin notification email'),
('otp_expiry_minutes', '10', 'OTP expiry time in minutes'),
('cart_expiry_hours', '24', 'Cart expiry time in hours'),
('max_cart_items', '10', 'Maximum books per cart'),
('smtp_host', 'smtp.hostinger.com', 'SMTP server for Hostinger'),
('smtp_port', '587', 'SMTP port'),
('smtp_username', '', 'SMTP username (your Hostinger email)'),
('smtp_password', '', 'SMTP password'),
('email_from_address', 'library@yourdomain.com', 'Email from address'),
('email_from_name', 'UCSI Digital Library', 'Email from name')
ON DUPLICATE KEY UPDATE setting_key = setting_key;

-- Sample books data
INSERT IGNORE INTO books (title, author, subject, description, cover_image, pdf_url, edition, publisher, isbn) VALUES
('Introduction to Computer Science', 'John Smith', 'Computer Science', 'Comprehensive guide to computer science fundamentals covering programming, algorithms, and data structures.', NULL, '/uploads/books/intro-cs.pdf', '11th Edition', 'Pearson Education', '9780134450629'),
('Advanced Business Management', 'Jane Doe', 'Business', 'Essential business management concepts and practices for modern organizations.', NULL, '/uploads/books/business-mgmt.pdf', '2nd Edition', 'McGraw-Hill Education', '9781260080469'),
('Calculus and Analytical Geometry', 'Dr. Robert Wilson', 'Mathematics', 'Advanced mathematical concepts for university students including calculus and geometry.', NULL, '/uploads/books/calculus.pdf', '8th Edition', 'Cengage Learning', '9781337275347'),
('Digital Marketing Strategies', 'Sarah Johnson', 'Business', 'Modern digital marketing approaches and social media strategies for businesses.', NULL, '/uploads/books/digital-marketing.pdf', '1st Edition', 'Wiley', '9781119653035'),
('Engineering Mechanics', 'Prof. Michael Chen', 'Engineering', 'Fundamental principles of engineering mechanics with practical applications.', NULL, '/uploads/books/eng-mechanics.pdf', '14th Edition', 'Pearson', '9780134814971');

-- Sample UCSI student data
INSERT IGNORE INTO students (student_id, email, full_name, is_verified, verification_date, status) VALUES
('12345', '12345@ucsiuniversity.edu.my', 'Ahmad Rahman', TRUE, NOW(), 'active'),
('23456', '23456@ucsiuniversity.edu.my', 'Siti Nurhaliza', TRUE, NOW(), 'active'),
('34567', '34567@ucsiuniversity.edu.my', 'Raj Kumar', TRUE, NOW(), 'active'),
('45678', '45678@ucsiuniversity.edu.my', 'Lim Wei Ming', TRUE, NOW(), 'active'),
('56789', '56789@ucsiuniversity.edu.my', 'Fatimah Abdullah', TRUE, NOW(), 'active');

-- Create views for common queries
CREATE VIEW admin_activity_summary AS
SELECT
    DATE(created_at) as activity_date,
    admin_email,
    action,
    COUNT(*) as action_count
FROM admin_logs
GROUP BY DATE(created_at), admin_email, action
ORDER BY activity_date DESC;

CREATE VIEW download_statistics AS
SELECT
    DATE(download_date) as download_date,
    download_type,
    COUNT(*) as download_count,
    COUNT(DISTINCT student_email) as unique_students,
    SUM(total_books) as total_books_downloaded
FROM downloads
GROUP BY DATE(download_date), download_type
ORDER BY download_date DESC;

-- Cleanup procedures for maintenance
DELIMITER //

CREATE PROCEDURE CleanupExpiredCarts()
BEGIN
    DELETE FROM student_carts WHERE expires_at < NOW();
    DELETE FROM cart_download_sessions WHERE expires_at < NOW();
    DELETE FROM otp_verifications WHERE expires_at < NOW();
END //

CREATE PROCEDURE GetAdminStats(IN admin_id_param INT)
BEGIN
    SELECT
        (SELECT COUNT(*) FROM books WHERE status = 'active') as total_books,
        (SELECT COUNT(*) FROM students WHERE status = 'active') as total_students,
        (SELECT COUNT(*) FROM downloads WHERE download_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)) as downloads_this_week,
        (SELECT COUNT(*) FROM admin_logs WHERE admin_id = admin_id_param AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)) as admin_actions_this_month;
END //

DELIMITER ;

-- Create indexes for performance
CREATE INDEX idx_downloads_recent ON downloads(download_date DESC);
CREATE INDEX idx_admin_logs_recent ON admin_logs(created_at DESC);
CREATE INDEX idx_cart_active ON student_carts(expires_at, session_id);