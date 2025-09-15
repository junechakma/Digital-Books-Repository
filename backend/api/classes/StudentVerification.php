<?php
/**
 * Student Verification Class
 * Handles UCSI student email verification and OTP system
 */

require_once __DIR__ . '/../config/config.php';

class StudentVerification {
    private $conn;
    private $students_table = "students";
    private $otp_table = "otp_verifications";

    public function __construct($db) {
        $this->conn = $db;
    }

    /**
     * Validate UCSI student email format
     * Must be in format: studentid@ucsiuniversity.edu.my
     * @param string $email
     * @return array Validation result
     */
    public function validateUCSIEmail($email) {
        $email = strtolower(trim($email));

        // Check email format
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return [
                'valid' => false,
                'message' => 'Invalid email format'
            ];
        }

        // Check if it's UCSI university email
        if (!preg_match('/^([a-zA-Z0-9]+)@ucsiuniversity\.edu\.my$/', $email, $matches)) {
            return [
                'valid' => false,
                'message' => 'Only UCSI University students can download books. Please use your student email (studentid@ucsiuniversity.edu.my)'
            ];
        }

        $student_id = $matches[1];

        // Validate student ID format (should be numeric or alphanumeric)
        if (!preg_match('/^[a-zA-Z0-9]{3,20}$/', $student_id)) {
            return [
                'valid' => false,
                'message' => 'Invalid student ID format in email'
            ];
        }

        return [
            'valid' => true,
            'email' => $email,
            'student_id' => $student_id,
            'message' => 'Valid UCSI student email'
        ];
    }

    /**
     * Generate and send OTP for book download
     * @param string $email UCSI student email
     * @param int $book_id Book ID to download
     * @return array Result
     */
    public function generateDownloadOTP($email, $book_id) {
        // Validate email first
        $validation = $this->validateUCSIEmail($email);
        if (!$validation['valid']) {
            return $validation;
        }

        $email = $validation['email'];
        $student_id = $validation['student_id'];

        // Check if book exists
        $book_query = "SELECT id, title FROM books WHERE id = ? AND status = 'active'";
        $book_stmt = $this->conn->prepare($book_query);
        $book_stmt->execute([$book_id]);
        $book = $book_stmt->fetch(PDO::FETCH_ASSOC);

        if (!$book) {
            return [
                'success' => false,
                'message' => 'Book not found or not available'
            ];
        }

        // Check for recent OTP requests (prevent spam)
        $recent_check = "SELECT COUNT(*) as count FROM {$this->otp_table}
                        WHERE email = ? AND book_id = ?
                        AND created_at > DATE_SUB(NOW(), INTERVAL 2 MINUTE)";
        $recent_stmt = $this->conn->prepare($recent_check);
        $recent_stmt->execute([$email, $book_id]);
        $recent_count = $recent_stmt->fetch(PDO::FETCH_ASSOC)['count'];

        if ($recent_count > 0) {
            return [
                'success' => false,
                'message' => 'Please wait 2 minutes before requesting another OTP'
            ];
        }

        // Generate 6-digit OTP
        $otp_code = sprintf('%06d', mt_rand(100000, 999999));

        // Set expiry time (10 minutes from now)
        $expires_at = date('Y-m-d H:i:s', strtotime('+10 minutes'));

        // Clean up old OTPs for this email/book combination
        $cleanup_query = "DELETE FROM {$this->otp_table}
                         WHERE email = ? AND book_id = ? AND expires_at < NOW()";
        $cleanup_stmt = $this->conn->prepare($cleanup_query);
        $cleanup_stmt->execute([$email, $book_id]);

        // Insert new OTP
        $insert_query = "INSERT INTO {$this->otp_table}
                        (email, otp_code, book_id, purpose, expires_at)
                        VALUES (?, ?, ?, 'download', ?)";
        $insert_stmt = $this->conn->prepare($insert_query);
        $success = $insert_stmt->execute([$email, $otp_code, $book_id, $expires_at]);

        if (!$success) {
            return [
                'success' => false,
                'message' => 'Failed to generate OTP. Please try again.'
            ];
        }

        // Register student if not exists
        $this->registerStudentIfNotExists($student_id, $email);

        // Send OTP email
        $email_result = $this->sendOTPEmail($email, $otp_code, $book['title'], $student_id);

        if ($email_result['success']) {
            logActivity("OTP generated for student $email, book ID: $book_id");
            return [
                'success' => true,
                'message' => "OTP sent to $email. The code will expire in 10 minutes.",
                'data' => [
                    'email' => $email,
                    'book_id' => $book_id,
                    'book_title' => $book['title'],
                    'expires_in' => 600 // 10 minutes in seconds
                ]
            ];
        } else {
            // If email sending fails, remove the OTP
            $delete_query = "DELETE FROM {$this->otp_table} WHERE email = ? AND otp_code = ?";
            $delete_stmt = $this->conn->prepare($delete_query);
            $delete_stmt->execute([$email, $otp_code]);

            return [
                'success' => false,
                'message' => 'Failed to send OTP email: ' . $email_result['message']
            ];
        }
    }

    /**
     * Verify OTP for book download
     * @param string $email
     * @param string $otp_code
     * @param int $book_id
     * @return array Result
     */
    public function verifyDownloadOTP($email, $otp_code, $book_id) {
        // Validate email
        $validation = $this->validateUCSIEmail($email);
        if (!$validation['valid']) {
            return $validation;
        }

        $email = $validation['email'];

        // Check OTP
        $query = "SELECT * FROM {$this->otp_table}
                 WHERE email = ? AND otp_code = ? AND book_id = ?
                 AND purpose = 'download' AND is_used = FALSE
                 AND expires_at > NOW()";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$email, $otp_code, $book_id]);
        $otp_record = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$otp_record) {
            return [
                'success' => false,
                'message' => 'Invalid or expired OTP. Please request a new one.'
            ];
        }

        // Mark OTP as used
        $update_query = "UPDATE {$this->otp_table} SET is_used = TRUE WHERE id = ?";
        $update_stmt = $this->conn->prepare($update_query);
        $update_stmt->execute([$otp_record['id']]);

        logActivity("OTP verified successfully for student $email, book ID: $book_id");

        return [
            'success' => true,
            'message' => 'OTP verified successfully. You can now download the book.',
            'data' => [
                'email' => $email,
                'student_id' => $validation['student_id'],
                'book_id' => $book_id,
                'verified_at' => date('Y-m-d H:i:s')
            ]
        ];
    }

    /**
     * Register student if not exists
     * @param string $student_id
     * @param string $email
     */
    private function registerStudentIfNotExists($student_id, $email) {
        $check_query = "SELECT id FROM {$this->students_table} WHERE email = ?";
        $check_stmt = $this->conn->prepare($check_query);
        $check_stmt->execute([$email]);

        if (!$check_stmt->fetch()) {
            $insert_query = "INSERT INTO {$this->students_table}
                           (student_id, email, is_verified, status)
                           VALUES (?, ?, TRUE, 'active')";
            $insert_stmt = $this->conn->prepare($insert_query);
            $insert_stmt->execute([$student_id, $email]);

            logActivity("New student registered: $email (ID: $student_id)");
        }
    }

    /**
     * Send OTP email to student
     * @param string $email
     * @param string $otp_code
     * @param string $book_title
     * @param string $student_id
     * @return array Result
     */
    private function sendOTPEmail($email, $otp_code, $book_title, $student_id) {
        $subject = "UCSI Digital Library - Download Verification Code";

        $message = "
<!DOCTYPE html>
<html>
<head>
    <meta charset='UTF-8'>
    <title>UCSI Digital Library - Download Verification</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background-color: #ca1d26; color: white; padding: 20px; text-align: center; }
        .content { padding: 20px; background-color: #f9f9f9; }
        .otp-code {
            font-size: 24px;
            font-weight: bold;
            color: #ca1d26;
            text-align: center;
            padding: 15px;
            background-color: #fff;
            border: 2px dashed #ca1d26;
            margin: 20px 0;
        }
        .footer { text-align: center; padding: 20px; font-size: 12px; color: #666; }
    </style>
</head>
<body>
    <div class='container'>
        <div class='header'>
            <h1>UCSI DIGITAL LIBRARY</h1>
            <p>Bangladesh Branch</p>
        </div>
        <div class='content'>
            <h2>Download Verification Code</h2>
            <p>Dear Student (ID: {$student_id}),</p>
            <p>You have requested to download the following book:</p>
            <p><strong>\"{$book_title}\"</strong></p>

            <p>Your verification code is:</p>
            <div class='otp-code'>{$otp_code}</div>

            <p><strong>Important:</strong></p>
            <ul>
                <li>This code is valid for 10 minutes only</li>
                <li>Do not share this code with anyone</li>
                <li>Use this code to complete your book download</li>
            </ul>

            <p>If you did not request this download, please ignore this email.</p>
        </div>
        <div class='footer'>
            <p>UCSI Digital Library - Bangladesh Branch<br>
            This is an automated message, please do not reply.</p>
        </div>
    </div>
</body>
</html>";

        $headers = [
            'MIME-Version: 1.0',
            'Content-type: text/html; charset=UTF-8',
            'From: UCSI Digital Library <noreply@ucsi.edu.my>',
            'Reply-To: library@ucsi.edu.my',
            'X-Mailer: PHP/' . phpversion()
        ];

        // For development/testing, you can use mail() function
        // In production, use a proper email service like PHPMailer with SMTP
        if (function_exists('mail')) {
            $success = mail($email, $subject, $message, implode("\r\n", $headers));
            if ($success) {
                return ['success' => true, 'message' => 'OTP email sent successfully'];
            } else {
                return ['success' => false, 'message' => 'Failed to send email via mail() function'];
            }
        } else {
            // For development - log the OTP instead of sending email
            logActivity("OTP for $email: $otp_code (Book: $book_title)", 'INFO');
            return [
                'success' => true,
                'message' => 'OTP generated successfully (check server logs for development)',
                'dev_otp' => $otp_code // Only for development
            ];
        }
    }

    /**
     * Get student statistics
     * @return array
     */
    public function getStudentStats() {
        $stats = [];

        // Total registered students
        $query = "SELECT COUNT(*) as total FROM {$this->students_table} WHERE status = 'active'";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $stats['total_students'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

        // Recent OTP requests (last 24 hours)
        $query = "SELECT COUNT(*) as count FROM {$this->otp_table}
                 WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $stats['otp_requests_today'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

        // Successful verifications (last 24 hours)
        $query = "SELECT COUNT(*) as count FROM {$this->otp_table}
                 WHERE is_used = TRUE AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $stats['successful_verifications_today'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

        return $stats;
    }

    /**
     * Clean up expired OTPs (maintenance function)
     */
    public function cleanupExpiredOTPs() {
        $query = "DELETE FROM {$this->otp_table} WHERE expires_at < NOW()";
        $stmt = $this->conn->prepare($query);
        $deleted = $stmt->execute();

        if ($deleted) {
            $count = $stmt->rowCount();
            logActivity("Cleaned up $count expired OTPs");
            return $count;
        }

        return 0;
    }
}
?>