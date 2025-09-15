<?php
/**
 * Admin Authentication Class with OTP Password Recovery
 * Enhanced security for admin panel
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/AdminLogger.php';

class AdminAuth {
    private $conn;
    private $logger;
    private $users_table = "admin_users";
    private $sessions_table = "admin_sessions";
    private $otp_table = "otp_verifications";

    public function __construct($db) {
        $this->conn = $db;
        $this->logger = new AdminLogger($db);
    }

    /**
     * Admin login with enhanced security
     * @param string $email
     * @param string $password
     * @param string $ip_address
     * @param string $user_agent
     * @return array Login result
     */
    public function login($email, $password, $ip_address = null, $user_agent = null) {
        $email = strtolower(trim($email));
        $ip_address = $ip_address ?: getClientIP();
        $user_agent = $user_agent ?: ($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown');

        // Get admin user
        $query = "SELECT * FROM {$this->users_table} WHERE email = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$email]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$admin) {
            $this->logger->logLogin(null, $email, false, $ip_address, $user_agent);
            return [
                'success' => false,
                'message' => 'Invalid email or password'
            ];
        }

        // Check if account is locked
        if ($admin['locked_until'] && strtotime($admin['locked_until']) > time()) {
            $this->logger->logLogin($admin['id'], $email, false, $ip_address, $user_agent);
            return [
                'success' => false,
                'message' => 'Account is temporarily locked. Please try again later or use password recovery.'
            ];
        }

        // Check if account is active
        if (!$admin['is_active']) {
            $this->logger->logLogin($admin['id'], $email, false, $ip_address, $user_agent);
            return [
                'success' => false,
                'message' => 'Account is deactivated. Please contact administrator.'
            ];
        }

        // Verify password
        if (!password_verify($password, $admin['password_hash'])) {
            // Increment failed attempts
            $this->incrementFailedAttempts($admin['id']);
            $this->logger->logLogin($admin['id'], $email, false, $ip_address, $user_agent);
            return [
                'success' => false,
                'message' => 'Invalid email or password'
            ];
        }

        // Reset failed attempts on successful login
        $this->resetFailedAttempts($admin['id']);

        // Create session
        $session_id = $this->createSession($admin['id'], $ip_address, $user_agent);

        // Update last login
        $this->updateLastLogin($admin['id'], $ip_address);

        // Log successful login
        $this->logger->logLogin($admin['id'], $email, true, $ip_address, $user_agent);

        return [
            'success' => true,
            'message' => 'Login successful',
            'data' => [
                'session_id' => $session_id,
                'admin' => [
                    'id' => $admin['id'],
                    'email' => $admin['email'],
                    'full_name' => $admin['full_name'],
                    'role' => $admin['role']
                ],
                'password_reset_required' => (bool)$admin['password_reset_required']
            ]
        ];
    }

    /**
     * Request password reset with OTP
     * @param string $email
     * @param string $ip_address
     * @return array Result
     */
    public function requestPasswordReset($email, $ip_address = null) {
        $email = strtolower(trim($email));
        $ip_address = $ip_address ?: getClientIP();

        // Check if admin exists
        $query = "SELECT id, email, full_name FROM {$this->users_table} WHERE email = ? AND is_active = TRUE";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$email]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$admin) {
            $this->logger->logPasswordReset($email, false, $ip_address);
            return [
                'success' => false,
                'message' => 'If this email exists in our system, you will receive a reset code.'
            ];
        }

        // Check for recent OTP requests (prevent spam)
        $recent_check = "SELECT COUNT(*) as count FROM {$this->otp_table}
                        WHERE email = ? AND purpose = 'admin_recovery'
                        AND created_at > DATE_SUB(NOW(), INTERVAL 5 MINUTE)";
        $recent_stmt = $this->conn->prepare($recent_check);
        $recent_stmt->execute([$email]);
        $recent_count = $recent_stmt->fetch(PDO::FETCH_ASSOC)['count'];

        if ($recent_count > 0) {
            return [
                'success' => false,
                'message' => 'Please wait 5 minutes before requesting another reset code.'
            ];
        }

        // Generate OTP
        $otp_code = sprintf('%06d', mt_rand(100000, 999999));
        $expires_at = date('Y-m-d H:i:s', strtotime('+15 minutes')); // 15 minutes for admin recovery

        // Clean up old OTPs
        $cleanup_query = "DELETE FROM {$this->otp_table}
                         WHERE email = ? AND purpose = 'admin_recovery'";
        $cleanup_stmt = $this->conn->prepare($cleanup_query);
        $cleanup_stmt->execute([$email]);

        // Insert new OTP
        $insert_query = "INSERT INTO {$this->otp_table}
                        (email, otp_code, purpose, reference_id, expires_at)
                        VALUES (?, ?, 'admin_recovery', ?, ?)";
        $insert_stmt = $this->conn->prepare($insert_query);
        $success = $insert_stmt->execute([$email, $otp_code, $admin['id'], $expires_at]);

        if (!$success) {
            return [
                'success' => false,
                'message' => 'Failed to generate reset code. Please try again.'
            ];
        }

        // Send OTP email
        $email_result = $this->sendPasswordResetOTP($email, $otp_code, $admin['full_name']);

        if ($email_result['success']) {
            $this->logger->logPasswordReset($email, true, $ip_address);
            return [
                'success' => true,
                'message' => 'Password reset code sent to your email. The code will expire in 15 minutes.',
                'data' => [
                    'email' => $email,
                    'expires_in' => 900 // 15 minutes
                ]
            ];
        } else {
            // Remove OTP if email sending failed
            $delete_query = "DELETE FROM {$this->otp_table} WHERE email = ? AND otp_code = ?";
            $delete_stmt = $this->conn->prepare($delete_query);
            $delete_stmt->execute([$email, $otp_code]);

            return [
                'success' => false,
                'message' => 'Failed to send reset email: ' . $email_result['message']
            ];
        }
    }

    /**
     * Verify OTP and reset password
     * @param string $email
     * @param string $otp_code
     * @param string $new_password
     * @param string $ip_address
     * @return array Result
     */
    public function resetPasswordWithOTP($email, $otp_code, $new_password, $ip_address = null) {
        $email = strtolower(trim($email));
        $ip_address = $ip_address ?: getClientIP();

        // Verify OTP
        $query = "SELECT * FROM {$this->otp_table}
                 WHERE email = ? AND otp_code = ? AND purpose = 'admin_recovery'
                 AND is_used = FALSE AND expires_at > NOW()";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$email, $otp_code]);
        $otp_record = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$otp_record) {
            $this->logger->logOTPVerification($email, false, 'admin_recovery', $ip_address);
            return [
                'success' => false,
                'message' => 'Invalid or expired reset code. Please request a new one.'
            ];
        }

        // Validate new password
        $password_validation = $this->validatePassword($new_password);
        if (!$password_validation['valid']) {
            return [
                'success' => false,
                'message' => $password_validation['message']
            ];
        }

        // Update password
        $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
        $update_query = "UPDATE {$this->users_table}
                        SET password_hash = ?, password_reset_required = FALSE,
                            failed_login_attempts = 0, locked_until = NULL,
                            updated_at = CURRENT_TIMESTAMP
                        WHERE email = ?";
        $update_stmt = $this->conn->prepare($update_query);
        $password_updated = $update_stmt->execute([$password_hash, $email]);

        if (!$password_updated) {
            return [
                'success' => false,
                'message' => 'Failed to update password. Please try again.'
            ];
        }

        // Mark OTP as used
        $mark_used_query = "UPDATE {$this->otp_table} SET is_used = TRUE, used_at = NOW() WHERE id = ?";
        $mark_used_stmt = $this->conn->prepare($mark_used_query);
        $mark_used_stmt->execute([$otp_record['id']]);

        // Invalidate all existing sessions for this admin
        $this->invalidateAllSessions($otp_record['reference_id']);

        // Log success
        $this->logger->logOTPVerification($email, true, 'admin_recovery', $ip_address);
        $this->logger->logAction(
            $otp_record['reference_id'],
            $email,
            'password_reset_completed',
            'auth',
            null,
            ['reset_via' => 'otp'],
            $ip_address
        );

        return [
            'success' => true,
            'message' => 'Password reset successfully. Please login with your new password.'
        ];
    }

    /**
     * Validate session
     * @param string $session_id
     * @return array|false Session data or false
     */
    public function validateSession($session_id) {
        $query = "SELECT s.*, a.email, a.full_name, a.role, a.is_active
                 FROM {$this->sessions_table} s
                 JOIN {$this->users_table} a ON s.admin_id = a.id
                 WHERE s.id = ? AND s.expires_at > NOW() AND a.is_active = TRUE";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$session_id]);
        $session = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($session) {
            // Update last activity
            $update_query = "UPDATE {$this->sessions_table} SET last_activity = NOW() WHERE id = ?";
            $update_stmt = $this->conn->prepare($update_query);
            $update_stmt->execute([$session_id]);
        }

        return $session;
    }

    /**
     * Logout admin
     * @param string $session_id
     * @return bool Success
     */
    public function logout($session_id) {
        // Get session info for logging
        $session = $this->validateSession($session_id);

        // Delete session
        $query = "DELETE FROM {$this->sessions_table} WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        $success = $stmt->execute([$session_id]);

        if ($success && $session) {
            $this->logger->logLogout($session['admin_id'], $session['email']);
        }

        return $success;
    }

    /**
     * Create session
     * @param int $admin_id
     * @param string $ip_address
     * @param string $user_agent
     * @return string Session ID
     */
    private function createSession($admin_id, $ip_address, $user_agent) {
        $session_id = bin2hex(random_bytes(32));
        $expires_at = date('Y-m-d H:i:s', strtotime('+' . SESSION_LIFETIME . ' seconds'));

        $query = "INSERT INTO {$this->sessions_table}
                 (id, admin_id, ip_address, user_agent, expires_at)
                 VALUES (?, ?, ?, ?, ?)";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$session_id, $admin_id, $ip_address, $user_agent, $expires_at]);

        return $session_id;
    }

    /**
     * Increment failed login attempts
     * @param int $admin_id
     */
    private function incrementFailedAttempts($admin_id) {
        $query = "UPDATE {$this->users_table}
                 SET failed_login_attempts = failed_login_attempts + 1,
                     locked_until = CASE
                         WHEN failed_login_attempts + 1 >= ? THEN DATE_ADD(NOW(), INTERVAL ? SECOND)
                         ELSE locked_until
                     END
                 WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([MAX_LOGIN_ATTEMPTS, LOGIN_LOCKOUT_TIME, $admin_id]);
    }

    /**
     * Reset failed login attempts
     * @param int $admin_id
     */
    private function resetFailedAttempts($admin_id) {
        $query = "UPDATE {$this->users_table}
                 SET failed_login_attempts = 0, locked_until = NULL
                 WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$admin_id]);
    }

    /**
     * Update last login information
     * @param int $admin_id
     * @param string $ip_address
     */
    private function updateLastLogin($admin_id, $ip_address) {
        $query = "UPDATE {$this->users_table}
                 SET last_login = NOW(), last_login_ip = ?
                 WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$ip_address, $admin_id]);
    }

    /**
     * Invalidate all sessions for an admin
     * @param int $admin_id
     */
    private function invalidateAllSessions($admin_id) {
        $query = "DELETE FROM {$this->sessions_table} WHERE admin_id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$admin_id]);
    }

    /**
     * Validate password strength
     * @param string $password
     * @return array Validation result
     */
    private function validatePassword($password) {
        if (strlen($password) < 8) {
            return [
                'valid' => false,
                'message' => 'Password must be at least 8 characters long.'
            ];
        }

        if (!preg_match('/[A-Z]/', $password)) {
            return [
                'valid' => false,
                'message' => 'Password must contain at least one uppercase letter.'
            ];
        }

        if (!preg_match('/[a-z]/', $password)) {
            return [
                'valid' => false,
                'message' => 'Password must contain at least one lowercase letter.'
            ];
        }

        if (!preg_match('/[0-9]/', $password)) {
            return [
                'valid' => false,
                'message' => 'Password must contain at least one number.'
            ];
        }

        return ['valid' => true];
    }

    /**
     * Send password reset OTP email
     * @param string $email
     * @param string $otp_code
     * @param string $full_name
     * @return array Result
     */
    private function sendPasswordResetOTP($email, $otp_code, $full_name) {
        $subject = "UCSI Digital Library - Admin Password Reset Code";

        $message = "
<!DOCTYPE html>
<html>
<head>
    <meta charset='UTF-8'>
    <title>Admin Password Reset</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background-color: #ca1d26; color: white; padding: 20px; text-align: center; }
        .content { padding: 20px; background-color: #f9f9f9; }
        .otp-code {
            font-size: 28px;
            font-weight: bold;
            color: #ca1d26;
            text-align: center;
            padding: 20px;
            background-color: #fff;
            border: 3px solid #ca1d26;
            margin: 20px 0;
            letter-spacing: 3px;
        }
        .warning {
            background-color: #fff3cd;
            border: 1px solid #ffeaa7;
            padding: 15px;
            margin: 15px 0;
            border-radius: 5px;
        }
        .footer { text-align: center; padding: 20px; font-size: 12px; color: #666; }
    </style>
</head>
<body>
    <div class='container'>
        <div class='header'>
            <h1>🔐 ADMIN PASSWORD RESET</h1>
            <p>UCSI Digital Library - Bangladesh Branch</p>
        </div>
        <div class='content'>
            <h2>Password Reset Request</h2>
            <p>Dear $full_name,</p>
            <p>You have requested to reset your admin password. Use the verification code below:</p>

            <div class='otp-code'>$otp_code</div>

            <div class='warning'>
                <strong>⚠️ SECURITY NOTICE:</strong>
                <ul>
                    <li>This code is valid for 15 minutes only</li>
                    <li>Never share this code with anyone</li>
                    <li>If you didn't request this reset, contact IT immediately</li>
                    <li>This is for admin access - treat with extreme caution</li>
                </ul>
            </div>

            <p>After verification, you'll be able to set a new secure password.</p>

            <p><strong>Best practices for your new password:</strong></p>
            <ul>
                <li>At least 8 characters long</li>
                <li>Include uppercase and lowercase letters</li>
                <li>Include numbers and special characters</li>
                <li>Don't reuse old passwords</li>
            </ul>
        </div>
        <div class='footer'>
            <p>UCSI Digital Library - Admin Panel<br>
            This is an automated security message. Do not reply to this email.<br>
            If you have concerns, contact: admin@ucsi.edu.my</p>
        </div>
    </div>
</body>
</html>";

        $headers = [
            'MIME-Version: 1.0',
            'Content-type: text/html; charset=UTF-8',
            'From: UCSI Digital Library Admin <noreply@ucsi.edu.my>',
            'Reply-To: admin@ucsi.edu.my',
            'X-Priority: 1 (Highest)',
            'X-MSMail-Priority: High',
            'Importance: High',
            'X-Mailer: PHP/' . phpversion()
        ];

        // For development/testing
        if (function_exists('mail')) {
            $success = mail($email, $subject, $message, implode("\r\n", $headers));
            if ($success) {
                return ['success' => true, 'message' => 'Password reset email sent successfully'];
            } else {
                return ['success' => false, 'message' => 'Failed to send email'];
            }
        } else {
            // For development - log the OTP
            logActivity("ADMIN PASSWORD RESET OTP for $email: $otp_code", 'ADMIN');
            return [
                'success' => true,
                'message' => 'OTP generated successfully (check server logs for development)',
                'dev_otp' => $otp_code
            ];
        }
    }

    /**
     * Get admin session statistics
     * @return array Statistics
     */
    public function getSessionStats() {
        $stats = [];

        // Active sessions
        $query = "SELECT COUNT(*) as count FROM {$this->sessions_table} WHERE expires_at > NOW()";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $stats['active_sessions'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

        // Logins today
        $query = "SELECT COUNT(*) as count FROM admin_logs
                 WHERE action = 'login_success' AND DATE(created_at) = CURDATE()";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $stats['logins_today'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

        // Failed logins today
        $query = "SELECT COUNT(*) as count FROM admin_logs
                 WHERE action = 'login_failed' AND DATE(created_at) = CURDATE()";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $stats['failed_logins_today'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

        // Locked accounts
        $query = "SELECT COUNT(*) as count FROM {$this->users_table}
                 WHERE locked_until > NOW()";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $stats['locked_accounts'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

        return $stats;
    }
}
?>