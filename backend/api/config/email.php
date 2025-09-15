<?php
/**
 * Email Configuration for Hostinger
 * Handles email sending with SMTP support
 */

require_once __DIR__ . '/config.php';

class EmailService {
    private $conn;
    private $smtp_settings;

    public function __construct($db) {
        $this->conn = $db;
        $this->loadSMTPSettings();
    }

    /**
     * Load SMTP settings from database
     */
    private function loadSMTPSettings() {
        $query = "SELECT setting_key, setting_value FROM system_settings
                 WHERE setting_key IN ('smtp_host', 'smtp_port', 'smtp_username', 'smtp_password',
                                       'email_from_address', 'email_from_name')";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

        $this->smtp_settings = [
            'host' => $settings['smtp_host'] ?? 'smtp.hostinger.com',
            'port' => (int)($settings['smtp_port'] ?? 587),
            'username' => $settings['smtp_username'] ?? '',
            'password' => $settings['smtp_password'] ?? '',
            'from_address' => $settings['email_from_address'] ?? 'library@yourdomain.com',
            'from_name' => $settings['email_from_name'] ?? 'UCSI Digital Library'
        ];
    }

    /**
     * Send email using SMTP (for production) or mail() (for development)
     * @param string $to Recipient email
     * @param string $subject Email subject
     * @param string $html_body HTML email body
     * @param array $options Additional options
     * @return array Result
     */
    public function sendEmail($to, $subject, $html_body, $options = []) {
        // Validate email
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return [
                'success' => false,
                'message' => 'Invalid recipient email address'
            ];
        }

        // Check if SMTP is configured
        if ($this->isSMTPConfigured()) {
            return $this->sendWithSMTP($to, $subject, $html_body, $options);
        } else {
            // Fallback to PHP mail() function
            return $this->sendWithPHPMail($to, $subject, $html_body, $options);
        }
    }

    /**
     * Send email using SMTP (Hostinger)
     * @param string $to
     * @param string $subject
     * @param string $html_body
     * @param array $options
     * @return array
     */
    private function sendWithSMTP($to, $subject, $html_body, $options = []) {
        // Check if PHPMailer is available (you might need to install it)
        if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
            return $this->sendWithPHPMailer($to, $subject, $html_body, $options);
        }

        // Try using SMTP with sockets (basic implementation)
        return $this->sendWithSocketSMTP($to, $subject, $html_body, $options);
    }

    /**
     * Send email using PHPMailer (recommended for production)
     * Note: You need to install PHPMailer via Composer
     */
    private function sendWithPHPMailer($to, $subject, $html_body, $options = []) {
        try {
            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);

            // Server settings
            $mail->isSMTP();
            $mail->Host = $this->smtp_settings['host'];
            $mail->SMTPAuth = true;
            $mail->Username = $this->smtp_settings['username'];
            $mail->Password = $this->smtp_settings['password'];
            $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = $this->smtp_settings['port'];

            // Recipients
            $mail->setFrom($this->smtp_settings['from_address'], $this->smtp_settings['from_name']);
            $mail->addAddress($to);
            $mail->addReplyTo($this->smtp_settings['from_address'], $this->smtp_settings['from_name']);

            // Content
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $html_body;

            // Priority if specified
            if (isset($options['priority']) && $options['priority'] === 'high') {
                $mail->Priority = 1;
                $mail->addCustomHeader('X-MSMail-Priority', 'High');
                $mail->addCustomHeader('Importance', 'High');
            }

            $mail->send();

            logActivity("Email sent successfully via PHPMailer to: $to");
            return [
                'success' => true,
                'message' => 'Email sent successfully via SMTP'
            ];

        } catch (\PHPMailer\PHPMailer\Exception $e) {
            logActivity("PHPMailer error: " . $e->getMessage(), 'ERROR');
            return [
                'success' => false,
                'message' => 'SMTP error: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Basic SMTP implementation using sockets (fallback)
     * @param string $to
     * @param string $subject
     * @param string $html_body
     * @param array $options
     * @return array
     */
    private function sendWithSocketSMTP($to, $subject, $html_body, $options = []) {
        $smtp = $this->smtp_settings;

        // Create socket connection
        $socket = fsockopen($smtp['host'], $smtp['port'], $errno, $errstr, 10);
        if (!$socket) {
            logActivity("SMTP socket connection failed: $errstr ($errno)", 'ERROR');
            return [
                'success' => false,
                'message' => "SMTP connection failed: $errstr"
            ];
        }

        // Read server greeting
        $response = fgets($socket, 512);
        if (substr($response, 0, 3) !== '220') {
            fclose($socket);
            return [
                'success' => false,
                'message' => 'SMTP server not ready'
            ];
        }

        // SMTP conversation
        $commands = [
            "EHLO " . $smtp['host'],
            "STARTTLS",
            "AUTH LOGIN",
            base64_encode($smtp['username']),
            base64_encode($smtp['password']),
            "MAIL FROM: <{$smtp['from_address']}>",
            "RCPT TO: <$to>",
            "DATA"
        ];

        foreach ($commands as $command) {
            fputs($socket, $command . "\r\n");
            $response = fgets($socket, 512);

            // Basic response checking
            $response_code = substr($response, 0, 3);
            if ($command === "STARTTLS" && $response_code === '220') {
                stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
                continue;
            }
            if (!in_array($response_code, ['220', '221', '235', '250', '334', '354'])) {
                fclose($socket);
                return [
                    'success' => false,
                    'message' => 'SMTP command failed: ' . trim($response)
                ];
            }
        }

        // Send email headers and body
        $headers = $this->buildEmailHeaders($to, $subject, $options);
        fputs($socket, $headers . "\r\n" . $html_body . "\r\n.\r\n");
        $response = fgets($socket, 512);

        // QUIT
        fputs($socket, "QUIT\r\n");
        fclose($socket);

        if (substr($response, 0, 3) === '250') {
            logActivity("Email sent successfully via socket SMTP to: $to");
            return [
                'success' => true,
                'message' => 'Email sent successfully via SMTP'
            ];
        } else {
            return [
                'success' => false,
                'message' => 'Failed to send email: ' . trim($response)
            ];
        }
    }

    /**
     * Send email using PHP mail() function (development/fallback)
     * @param string $to
     * @param string $subject
     * @param string $html_body
     * @param array $options
     * @return array
     */
    private function sendWithPHPMail($to, $subject, $html_body, $options = []) {
        $headers = $this->buildEmailHeaders($to, $subject, $options);

        // Use mail() function
        $success = mail($to, $subject, $html_body, $headers);

        if ($success) {
            logActivity("Email sent successfully via mail() to: $to");
            return [
                'success' => true,
                'message' => 'Email sent successfully via PHP mail()'
            ];
        } else {
            logActivity("Failed to send email via mail() to: $to", 'ERROR');
            return [
                'success' => false,
                'message' => 'Failed to send email via PHP mail() function'
            ];
        }
    }

    /**
     * Build email headers
     * @param string $to
     * @param string $subject
     * @param array $options
     * @return string
     */
    private function buildEmailHeaders($to, $subject, $options = []) {
        $headers = [];
        $headers[] = 'MIME-Version: 1.0';
        $headers[] = 'Content-type: text/html; charset=UTF-8';
        $headers[] = 'From: ' . $this->smtp_settings['from_name'] . ' <' . $this->smtp_settings['from_address'] . '>';
        $headers[] = 'Reply-To: ' . $this->smtp_settings['from_address'];
        $headers[] = 'X-Mailer: UCSI Digital Library PHP/' . phpversion();

        // Priority headers
        if (isset($options['priority']) && $options['priority'] === 'high') {
            $headers[] = 'X-Priority: 1 (Highest)';
            $headers[] = 'X-MSMail-Priority: High';
            $headers[] = 'Importance: High';
        }

        // Additional headers
        if (isset($options['headers']) && is_array($options['headers'])) {
            $headers = array_merge($headers, $options['headers']);
        }

        return implode("\r\n", $headers);
    }

    /**
     * Check if SMTP is properly configured
     * @return bool
     */
    private function isSMTPConfigured() {
        return !empty($this->smtp_settings['host']) &&
               !empty($this->smtp_settings['username']) &&
               !empty($this->smtp_settings['password']) &&
               !empty($this->smtp_settings['from_address']);
    }

    /**
     * Update SMTP settings in database
     * @param array $settings
     * @return bool
     */
    public function updateSMTPSettings($settings) {
        $allowed_keys = ['smtp_host', 'smtp_port', 'smtp_username', 'smtp_password',
                        'email_from_address', 'email_from_name'];

        foreach ($settings as $key => $value) {
            if (in_array($key, $allowed_keys)) {
                $query = "UPDATE system_settings SET setting_value = ? WHERE setting_key = ?";
                $stmt = $this->conn->prepare($query);
                $stmt->execute([$value, $key]);
            }
        }

        // Reload settings
        $this->loadSMTPSettings();

        logActivity("SMTP settings updated");
        return true;
    }

    /**
     * Test email configuration
     * @param string $test_email
     * @return array
     */
    public function testEmailConfiguration($test_email) {
        $subject = "UCSI Digital Library - Email Configuration Test";
        $body = "
        <h2>Email Configuration Test</h2>
        <p>If you receive this email, your SMTP configuration is working correctly.</p>
        <p>Test sent at: " . date('Y-m-d H:i:s') . "</p>
        <p>SMTP Host: " . $this->smtp_settings['host'] . "</p>
        <p>SMTP Port: " . $this->smtp_settings['port'] . "</p>
        <hr>
        <p><small>UCSI Digital Library - Bangladesh Branch</small></p>
        ";

        return $this->sendEmail($test_email, $subject, $body);
    }

    /**
     * Get current email settings (without sensitive data)
     * @return array
     */
    public function getEmailSettings() {
        return [
            'smtp_host' => $this->smtp_settings['host'],
            'smtp_port' => $this->smtp_settings['port'],
            'smtp_username' => $this->smtp_settings['username'],
            'smtp_configured' => $this->isSMTPConfigured(),
            'from_address' => $this->smtp_settings['from_address'],
            'from_name' => $this->smtp_settings['from_name']
        ];
    }
}

/**
 * Global email sending function
 * @param string $to
 * @param string $subject
 * @param string $html_body
 * @param array $options
 * @return array
 */
function sendSystemEmail($to, $subject, $html_body, $options = []) {
    try {
        $database = getDatabase();
        $db = $database->getConnection();

        if (!$db) {
            return [
                'success' => false,
                'message' => 'Database connection failed'
            ];
        }

        $emailService = new EmailService($db);
        return $emailService->sendEmail($to, $subject, $html_body, $options);

    } catch (Exception $e) {
        logActivity("Email sending error: " . $e->getMessage(), 'ERROR');
        return [
            'success' => false,
            'message' => 'Email service error: ' . $e->getMessage()
        ];
    }
}
?>