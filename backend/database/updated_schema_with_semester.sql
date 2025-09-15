-- Updated UCSI Digital Library Schema with Semester Support
-- Add semester field and file upload routing

USE ucsi_digital_library;

-- Add semester column to books table
ALTER TABLE books ADD COLUMN semester VARCHAR(50) AFTER subject;
ALTER TABLE books ADD COLUMN academic_year INT AFTER semester;

-- Update the books table structure
ALTER TABLE books
MODIFY COLUMN pdf_url VARCHAR(500) COMMENT 'Generated path: year/semester/subject/title/',
MODIFY COLUMN cover_image VARCHAR(500) COMMENT 'Generated path: year/semester/subject/title/cover.ext';

-- Add indexes for new fields
ALTER TABLE books ADD INDEX idx_semester (semester);
ALTER TABLE books ADD INDEX idx_academic_year (academic_year);
ALTER TABLE books ADD INDEX idx_year_semester_subject (academic_year, semester, subject);

-- Update system settings for semesters
INSERT INTO system_settings (setting_key, setting_value, description) VALUES
('available_semesters', '["January Semester", "May Semester", "September Semester"]', 'Available semester options'),
('current_academic_year', '2025', 'Current academic year'),
('auto_generate_paths', 'true', 'Automatically generate file paths based on year/semester/subject/title')
ON DUPLICATE KEY UPDATE setting_key = setting_key;

-- Create view for organized book listing
CREATE OR REPLACE VIEW books_organized AS
SELECT
    id,
    title,
    author,
    subject,
    semester,
    academic_year,
    description,
    cover_image,
    pdf_url,
    edition,
    publication_date,
    publisher,
    isbn,
    status,
    created_at,
    updated_at,
    CONCAT(academic_year, '/',
           REPLACE(semester, ' ', '_'), '/',
           REPLACE(subject, ' ', '_'), '/',
           REPLACE(REPLACE(title, ' ', '_'), '/', '_')) as file_path_base
FROM books
WHERE status = 'active'
ORDER BY academic_year DESC, semester, subject, title;

-- Update sample data with semester information
UPDATE books SET
    semester = 'May Semester',
    academic_year = 2025
WHERE semester IS NULL;

-- Sample additional data with semester
INSERT IGNORE INTO books (title, author, subject, semester, academic_year, description, edition, publisher, isbn) VALUES
('Advanced Database Systems', 'Dr. Sarah Johnson', 'Computer Science', 'September Semester', 2025, 'Comprehensive guide to advanced database concepts including NoSQL, distributed systems, and performance optimization.', '4th Edition', 'MIT Press', '9780262033847'),
('Microeconomics Principles', 'Prof. David Chen', 'Economics', 'January Semester', 2025, 'Fundamental principles of microeconomics with real-world applications and case studies.', '12th Edition', 'Pearson', '9780134744476'),
('Organic Chemistry Lab Manual', 'Dr. Maria Rodriguez', 'Chemistry', 'May Semester', 2025, 'Laboratory experiments and procedures for organic chemistry students.', '3rd Edition', 'Wiley', '9781118083383');

-- Create procedure for generating file paths
DELIMITER //

CREATE PROCEDURE GenerateFilePath(
    IN book_title VARCHAR(500),
    IN book_subject VARCHAR(100),
    IN book_semester VARCHAR(50),
    IN book_year INT,
    IN file_type VARCHAR(10), -- 'pdf' or 'cover'
    OUT file_path VARCHAR(500)
)
BEGIN
    DECLARE safe_title VARCHAR(500);
    DECLARE safe_subject VARCHAR(100);
    DECLARE safe_semester VARCHAR(50);
    DECLARE file_extension VARCHAR(10);

    -- Set file extension based on type
    IF file_type = 'pdf' THEN
        SET file_extension = '.pdf';
    ELSE
        SET file_extension = '.jpg'; -- Default for cover, can be changed
    END IF;

    -- Create safe filename components (remove special characters)
    SET safe_title = REPLACE(REPLACE(REPLACE(REPLACE(book_title, ' ', '_'), '/', '_'), '\\', '_'), ':', '_');
    SET safe_subject = REPLACE(REPLACE(book_subject, ' ', '_'), '/', '_');
    SET safe_semester = REPLACE(REPLACE(book_semester, ' ', '_'), '/', '_');

    -- Limit title length to prevent long paths
    IF LENGTH(safe_title) > 50 THEN
        SET safe_title = SUBSTRING(safe_title, 1, 50);
    END IF;

    -- Generate the full path
    SET file_path = CONCAT(
        'uploads/books/',
        book_year, '/',
        safe_semester, '/',
        safe_subject, '/',
        safe_title, '/',
        IF(file_type = 'pdf', 'book', 'cover'),
        file_extension
    );

END //

DELIMITER ;

-- Create function to get semester options
DELIMITER //

CREATE FUNCTION GetSemesterOptions()
RETURNS JSON
READS SQL DATA
DETERMINISTIC
BEGIN
    DECLARE semester_options JSON;

    SELECT setting_value INTO semester_options
    FROM system_settings
    WHERE setting_key = 'available_semesters';

    RETURN semester_options;
END //

DELIMITER ;

-- Update admin logs to track semester information
ALTER TABLE admin_logs
MODIFY COLUMN details JSON COMMENT 'Additional details including semester/year information';

-- View for semester-wise statistics
CREATE OR REPLACE VIEW semester_statistics AS
SELECT
    academic_year,
    semester,
    subject,
    COUNT(*) as book_count,
    COUNT(CASE WHEN pdf_url IS NOT NULL AND pdf_url != '' THEN 1 END) as books_with_pdf,
    COUNT(CASE WHEN cover_image IS NOT NULL AND cover_image != '' THEN 1 END) as books_with_cover
FROM books
WHERE status = 'active'
GROUP BY academic_year, semester, subject
ORDER BY academic_year DESC, semester, subject;