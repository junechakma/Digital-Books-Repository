<?php
/**
 * Download Model Class
 * Handles download tracking and statistics
 */

require_once __DIR__ . '/../config/config.php';

class Download {
    private $conn;
    private $table_name = "downloads";

    public function __construct($db) {
        $this->conn = $db;
    }

    /**
     * Record a download
     * @param int $book_id
     * @param string $user_email
     * @param string $user_ip
     * @param string $user_agent
     * @return bool
     */
    public function recordDownload($book_id, $user_email = null, $user_ip = null, $user_agent = null) {
        $query = "INSERT INTO " . $this->table_name . "
                 (book_id, user_email, user_ip, user_agent)
                 VALUES (?, ?, ?, ?)";

        $stmt = $this->conn->prepare($query);
        return $stmt->execute([
            $book_id,
            $user_email,
            $user_ip ?: getClientIP(),
            $user_agent ?: ($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown')
        ]);
    }

    /**
     * Get download count for a book
     * @param int $book_id
     * @return int
     */
    public function getBookDownloadCount($book_id) {
        $query = "SELECT COUNT(*) as count FROM " . $this->table_name . " WHERE book_id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$book_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    }

    /**
     * Get total downloads
     * @return int
     */
    public function getTotalDownloads() {
        $query = "SELECT COUNT(*) as count FROM " . $this->table_name;
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    }

    /**
     * Get downloads in date range
     * @param string $start_date
     * @param string $end_date
     * @return array
     */
    public function getDownloadsByDateRange($start_date, $end_date) {
        $query = "SELECT DATE(download_date) as date, COUNT(*) as count
                 FROM " . $this->table_name . "
                 WHERE download_date BETWEEN ? AND ?
                 GROUP BY DATE(download_date)
                 ORDER BY date DESC";

        $stmt = $this->conn->prepare($query);
        $stmt->execute([$start_date, $end_date]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get most downloaded books
     * @param int $limit
     * @return array
     */
    public function getMostDownloaded($limit = 10) {
        $query = "SELECT b.*, COUNT(d.id) as download_count
                 FROM books b
                 LEFT JOIN " . $this->table_name . " d ON b.id = d.book_id
                 WHERE b.status = 'active'
                 GROUP BY b.id
                 ORDER BY download_count DESC, b.created_at DESC
                 LIMIT ?";

        $stmt = $this->conn->prepare($query);
        $stmt->execute([$limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get recent downloads
     * @param int $limit
     * @return array
     */
    public function getRecentDownloads($limit = 20) {
        $query = "SELECT d.*, b.title, b.author
                 FROM " . $this->table_name . " d
                 JOIN books b ON d.book_id = b.id
                 WHERE b.status = 'active'
                 ORDER BY d.download_date DESC
                 LIMIT ?";

        $stmt = $this->conn->prepare($query);
        $stmt->execute([$limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get download statistics
     * @return array
     */
    public function getStats() {
        $stats = [];

        // Total downloads
        $stats['total_downloads'] = $this->getTotalDownloads();

        // Downloads today
        $query = "SELECT COUNT(*) as count FROM " . $this->table_name . "
                 WHERE DATE(download_date) = CURDATE()";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $stats['downloads_today'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

        // Downloads this week
        $query = "SELECT COUNT(*) as count FROM " . $this->table_name . "
                 WHERE download_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $stats['downloads_this_week'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

        // Downloads this month
        $query = "SELECT COUNT(*) as count FROM " . $this->table_name . "
                 WHERE download_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $stats['downloads_this_month'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

        return $stats;
    }

    /**
     * Check if user downloaded a book recently (to prevent spam)
     * @param int $book_id
     * @param string $user_ip
     * @param int $minutes
     * @return bool
     */
    public function hasRecentDownload($book_id, $user_ip, $minutes = 5) {
        $query = "SELECT COUNT(*) as count FROM " . $this->table_name . "
                 WHERE book_id = ? AND user_ip = ?
                 AND download_date >= DATE_SUB(NOW(), INTERVAL ? MINUTE)";

        $stmt = $this->conn->prepare($query);
        $stmt->execute([$book_id, $user_ip, $minutes]);
        return $stmt->fetch(PDO::FETCH_ASSOC)['count'] > 0;
    }
}
?>