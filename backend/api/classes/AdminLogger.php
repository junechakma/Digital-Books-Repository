<?php
/**
 * Admin Logger Class
 * Comprehensive logging system for all admin activities
 */

require_once __DIR__ . '/../config/config.php';

class AdminLogger {
    private $conn;
    private $logs_table = "admin_logs";

    public function __construct($db) {
        $this->conn = $db;
    }

    /**
     * Log admin action
     * @param int $admin_id Admin ID (null for anonymous actions)
     * @param string $admin_email Admin email
     * @param string $action Action performed
     * @param string $entity_type Type of entity affected
     * @param int $entity_id ID of affected entity
     * @param array $details Additional details
     * @param string $ip_address IP address
     * @param string $user_agent User agent
     * @return bool Success
     */
    public function logAction($admin_id, $admin_email, $action, $entity_type = null, $entity_id = null, $details = [], $ip_address = null, $user_agent = null) {
        $query = "INSERT INTO {$this->logs_table}
                 (admin_id, admin_email, action, entity_type, entity_id, details, ip_address, user_agent)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $this->conn->prepare($query);

        $success = $stmt->execute([
            $admin_id,
            $admin_email,
            $action,
            $entity_type,
            $entity_id,
            !empty($details) ? json_encode($details) : null,
            $ip_address ?: getClientIP(),
            $user_agent ?: ($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown')
        ]);

        if ($success) {
            // Also log to file for backup
            logActivity("ADMIN_ACTION: $admin_email performed '$action' on $entity_type:$entity_id", 'ADMIN');
        }

        return $success;
    }

    /**
     * Log admin login
     * @param int $admin_id
     * @param string $admin_email
     * @param bool $success Login success/failure
     * @param string $ip_address
     * @param string $user_agent
     * @return bool
     */
    public function logLogin($admin_id, $admin_email, $success, $ip_address = null, $user_agent = null) {
        $action = $success ? 'login_success' : 'login_failed';
        $details = ['login_success' => $success];

        if (!$success) {
            $details['failure_reason'] = 'Invalid credentials';
        }

        return $this->logAction(
            $admin_id,
            $admin_email,
            $action,
            'auth',
            null,
            $details,
            $ip_address,
            $user_agent
        );
    }

    /**
     * Log admin logout
     * @param int $admin_id
     * @param string $admin_email
     * @param string $ip_address
     * @param string $user_agent
     * @return bool
     */
    public function logLogout($admin_id, $admin_email, $ip_address = null, $user_agent = null) {
        return $this->logAction(
            $admin_id,
            $admin_email,
            'logout',
            'auth',
            null,
            [],
            $ip_address,
            $user_agent
        );
    }

    /**
     * Log book creation
     * @param int $admin_id
     * @param string $admin_email
     * @param int $book_id
     * @param array $book_data
     * @return bool
     */
    public function logBookCreated($admin_id, $admin_email, $book_id, $book_data) {
        $details = [
            'title' => $book_data['title'] ?? 'Unknown',
            'author' => $book_data['author'] ?? 'Unknown',
            'subject' => $book_data['subject'] ?? 'Unknown',
            'has_pdf' => !empty($book_data['pdf_url'])
        ];

        return $this->logAction(
            $admin_id,
            $admin_email,
            'create_book',
            'book',
            $book_id,
            $details
        );
    }

    /**
     * Log book update
     * @param int $admin_id
     * @param string $admin_email
     * @param int $book_id
     * @param array $old_data
     * @param array $new_data
     * @return bool
     */
    public function logBookUpdated($admin_id, $admin_email, $book_id, $old_data, $new_data) {
        $changes = [];
        $tracked_fields = ['title', 'author', 'subject', 'description', 'pdf_url', 'cover_image'];

        foreach ($tracked_fields as $field) {
            $old_value = $old_data[$field] ?? null;
            $new_value = $new_data[$field] ?? null;

            if ($old_value !== $new_value) {
                $changes[$field] = [
                    'from' => $old_value,
                    'to' => $new_value
                ];
            }
        }

        $details = [
            'title' => $new_data['title'] ?? $old_data['title'] ?? 'Unknown',
            'changes' => $changes
        ];

        return $this->logAction(
            $admin_id,
            $admin_email,
            'update_book',
            'book',
            $book_id,
            $details
        );
    }

    /**
     * Log book deletion
     * @param int $admin_id
     * @param string $admin_email
     * @param int $book_id
     * @param array $book_data
     * @param bool $hard_delete
     * @return bool
     */
    public function logBookDeleted($admin_id, $admin_email, $book_id, $book_data, $hard_delete = false) {
        $details = [
            'title' => $book_data['title'] ?? 'Unknown',
            'author' => $book_data['author'] ?? 'Unknown',
            'hard_delete' => $hard_delete,
            'had_pdf' => !empty($book_data['pdf_url'])
        ];

        $action = $hard_delete ? 'hard_delete_book' : 'soft_delete_book';

        return $this->logAction(
            $admin_id,
            $admin_email,
            $action,
            'book',
            $book_id,
            $details
        );
    }

    /**
     * Log file upload
     * @param int $admin_id
     * @param string $admin_email
     * @param string $file_type
     * @param string $filename
     * @param int $file_size
     * @param int $book_id
     * @return bool
     */
    public function logFileUpload($admin_id, $admin_email, $file_type, $filename, $file_size, $book_id = null) {
        $details = [
            'file_type' => $file_type,
            'filename' => $filename,
            'file_size' => $file_size,
            'size_mb' => round($file_size / 1024 / 1024, 2)
        ];

        return $this->logAction(
            $admin_id,
            $admin_email,
            'upload_file',
            'file',
            $book_id,
            $details
        );
    }

    /**
     * Log password reset request
     * @param string $admin_email
     * @param bool $success
     * @param string $ip_address
     * @return bool
     */
    public function logPasswordReset($admin_email, $success, $ip_address = null) {
        $action = $success ? 'password_reset_requested' : 'password_reset_failed';
        $details = ['success' => $success];

        return $this->logAction(
            null, // No admin ID for password reset
            $admin_email,
            $action,
            'auth',
            null,
            $details,
            $ip_address
        );
    }

    /**
     * Log OTP verification for admin
     * @param string $admin_email
     * @param bool $success
     * @param string $purpose
     * @param string $ip_address
     * @return bool
     */
    public function logOTPVerification($admin_email, $success, $purpose, $ip_address = null) {
        $action = $success ? 'otp_verified' : 'otp_verification_failed';
        $details = [
            'success' => $success,
            'purpose' => $purpose
        ];

        return $this->logAction(
            null,
            $admin_email,
            $action,
            'auth',
            null,
            $details,
            $ip_address
        );
    }

    /**
     * Get admin activity logs
     * @param int $page
     * @param int $limit
     * @param array $filters
     * @return array
     */
    public function getActivityLogs($page = 1, $limit = 50, $filters = []) {
        $offset = ($page - 1) * $limit;
        $where_conditions = [];
        $params = [];

        // Build where conditions
        if (!empty($filters['admin_email'])) {
            $where_conditions[] = "admin_email = ?";
            $params[] = $filters['admin_email'];
        }

        if (!empty($filters['action'])) {
            $where_conditions[] = "action = ?";
            $params[] = $filters['action'];
        }

        if (!empty($filters['entity_type'])) {
            $where_conditions[] = "entity_type = ?";
            $params[] = $filters['entity_type'];
        }

        if (!empty($filters['date_from'])) {
            $where_conditions[] = "DATE(created_at) >= ?";
            $params[] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $where_conditions[] = "DATE(created_at) <= ?";
            $params[] = $filters['date_to'];
        }

        $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

        // Get logs
        $query = "SELECT * FROM {$this->logs_table} $where_clause
                 ORDER BY created_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;

        $stmt = $this->conn->prepare($query);
        $stmt->execute($params);
        $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Decode JSON details
        foreach ($logs as &$log) {
            if ($log['details']) {
                $log['details'] = json_decode($log['details'], true);
            }
        }

        // Get total count
        $count_query = "SELECT COUNT(*) as total FROM {$this->logs_table} $where_clause";
        $count_params = array_slice($params, 0, -2); // Remove limit and offset
        $count_stmt = $this->conn->prepare($count_query);
        $count_stmt->execute($count_params);
        $total = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];

        return [
            'logs' => $logs,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $limit,
                'total' => $total,
                'total_pages' => ceil($total / $limit)
            ]
        ];
    }

    /**
     * Get admin activity summary
     * @param string $admin_email
     * @param int $days
     * @return array
     */
    public function getAdminActivitySummary($admin_email, $days = 30) {
        $query = "SELECT
                    action,
                    COUNT(*) as count,
                    MAX(created_at) as last_action
                  FROM {$this->logs_table}
                  WHERE admin_email = ? AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                  GROUP BY action
                  ORDER BY count DESC";

        $stmt = $this->conn->prepare($query);
        $stmt->execute([$admin_email, $days]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get system activity statistics
     * @param int $days
     * @return array
     */
    public function getSystemStats($days = 7) {
        $stats = [];

        // Total actions
        $query = "SELECT COUNT(*) as total FROM {$this->logs_table}
                 WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$days]);
        $stats['total_actions'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

        // Actions by type
        $query = "SELECT action, COUNT(*) as count FROM {$this->logs_table}
                 WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                 GROUP BY action ORDER BY count DESC LIMIT 10";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$days]);
        $stats['actions_by_type'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Active admins
        $query = "SELECT COUNT(DISTINCT admin_email) as count FROM {$this->logs_table}
                 WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$days]);
        $stats['active_admins'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

        // Daily activity
        $query = "SELECT DATE(created_at) as date, COUNT(*) as count
                 FROM {$this->logs_table}
                 WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                 GROUP BY DATE(created_at)
                 ORDER BY date DESC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$days]);
        $stats['daily_activity'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $stats;
    }

    /**
     * Clean up old logs (keep only recent ones)
     * @param int $keep_days Number of days to keep
     * @return int Number of deleted logs
     */
    public function cleanupOldLogs($keep_days = 365) {
        $query = "DELETE FROM {$this->logs_table}
                 WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$keep_days]);

        $deleted_count = $stmt->rowCount();
        if ($deleted_count > 0) {
            logActivity("Admin logs cleanup: $deleted_count old logs removed (older than $keep_days days)", 'ADMIN');
        }

        return $deleted_count;
    }

    /**
     * Export logs to CSV
     * @param array $filters
     * @return string CSV content
     */
    public function exportLogsToCSV($filters = []) {
        $logs_data = $this->getActivityLogs(1, 10000, $filters); // Get up to 10k logs
        $logs = $logs_data['logs'];

        $csv_content = "Date,Admin Email,Action,Entity Type,Entity ID,IP Address,Details\n";

        foreach ($logs as $log) {
            $details = $log['details'] ? json_encode($log['details']) : '';
            $csv_content .= sprintf(
                "%s,%s,%s,%s,%s,%s,\"%s\"\n",
                $log['created_at'],
                $log['admin_email'],
                $log['action'],
                $log['entity_type'] ?? '',
                $log['entity_id'] ?? '',
                $log['ip_address'] ?? '',
                str_replace('"', '""', $details) // Escape quotes
            );
        }

        return $csv_content;
    }
}
?>