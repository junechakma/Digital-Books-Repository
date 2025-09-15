<?php
/**
 * File Upload Handler Class
 * Handles PDF and image file uploads with security validation
 */

require_once __DIR__ . '/../config/config.php';

class FileUpload {
    private $upload_path;
    private $allowed_types;
    private $max_size;

    public function __construct() {
        $this->upload_path = UPLOAD_PATH;
        $this->allowed_types = ALLOWED_FILE_TYPES;
        $this->max_size = MAX_FILE_SIZE;

        // Ensure upload directory exists
        if (!is_dir($this->upload_path)) {
            mkdir($this->upload_path, 0755, true);
        }
    }

    /**
     * Upload PDF file (legacy method - uses year/month structure)
     * @param array $file $_FILES array element
     * @return array Result with success status and details
     */
    public function uploadPDF($file) {
        try {
            // Validate file
            $validation = $this->validateFile($file, $this->allowed_types);
            if (!$validation['success']) {
                return $validation;
            }

            // Generate secure filename
            $secure_filename = generateSecureFilename($file['name']);

            // Create year/month directory structure
            $year = date('Y');
            $month = date('m');
            $upload_dir = $this->upload_path . $year . '/' . $month . '/';

            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }

            $destination = $upload_dir . $secure_filename;
            $relative_path = 'uploads/books/' . $year . '/' . $month . '/' . $secure_filename;

            // Move uploaded file
            if (move_uploaded_file($file['tmp_name'], $destination)) {
                // Extract PDF metadata
                $metadata = $this->extractPDFMetadata($destination);

                // Generate file hash for integrity checking
                $file_hash = hash_file('sha256', $destination);

                logActivity("PDF uploaded successfully: $secure_filename");

                return [
                    'success' => true,
                    'message' => 'File uploaded successfully',
                    'data' => [
                        'filename' => $secure_filename,
                        'original_name' => $file['name'],
                        'path' => $relative_path,
                        'size' => $file['size'],
                        'hash' => $file_hash,
                        'metadata' => $metadata
                    ]
                ];
            } else {
                throw new Exception('Failed to move uploaded file');
            }

        } catch (Exception $e) {
            logActivity("PDF upload failed: " . $e->getMessage(), 'ERROR');
            return [
                'success' => false,
                'message' => 'Upload failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Upload PDF file with smart routing (semester-based structure)
     * @param array $file $_FILES array element
     * @param array $routing_info Routing information (title, subject, semester, academic_year)
     * @return array Result with success status and details
     */
    public function uploadPDFWithRouting($file, $routing_info = []) {
        try {
            // Validate file
            $validation = $this->validateFile($file, $this->allowed_types);
            if (!$validation['success']) {
                return $validation;
            }

            // Generate path based on routing info or fallback to date-based
            if (!empty($routing_info['title']) && !empty($routing_info['subject']) &&
                !empty($routing_info['semester']) && !empty($routing_info['academic_year'])) {

                $path_info = $this->generateSmartPath($routing_info, 'pdf');
                $upload_dir = $path_info['directory'];
                $filename = 'book.pdf';
                $relative_path = $path_info['relative_path'] . $filename;
            } else {
                // Fallback to legacy year/month structure
                $year = date('Y');
                $month = date('m');
                $upload_dir = $this->upload_path . $year . '/' . $month . '/';
                $filename = generateSecureFilename($file['name']);
                $relative_path = 'uploads/books/' . $year . '/' . $month . '/' . $filename;
            }

            // Create directory if it doesn't exist
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }

            $destination = $upload_dir . $filename;

            // Move uploaded file
            if (move_uploaded_file($file['tmp_name'], $destination)) {
                // Extract PDF metadata
                $metadata = $this->extractPDFMetadata($destination);

                // Generate file hash for integrity checking
                $file_hash = hash_file('sha256', $destination);

                logActivity("PDF uploaded successfully with routing: $filename");

                return [
                    'success' => true,
                    'message' => 'File uploaded successfully',
                    'data' => [
                        'filename' => $filename,
                        'original_name' => $file['name'],
                        'path' => $relative_path,
                        'size' => $file['size'],
                        'hash' => $file_hash,
                        'metadata' => $metadata,
                        'routing_used' => !empty($routing_info)
                    ]
                ];
            } else {
                throw new Exception('Failed to move uploaded file');
            }

        } catch (Exception $e) {
            logActivity("PDF upload failed: " . $e->getMessage(), 'ERROR');
            return [
                'success' => false,
                'message' => 'Upload failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Upload cover image (legacy method)
     * @param array $file $_FILES array element
     * @return array Result with success status and details
     */
    public function uploadCoverImage($file) {
        try {
            // Validate file
            $validation = $this->validateFile($file, ALLOWED_IMAGE_TYPES);
            if (!$validation['success']) {
                return $validation;
            }

            // Generate secure filename
            $secure_filename = generateSecureFilename($file['name']);
            $destination = COVER_PATH . $secure_filename;
            $relative_path = 'uploads/covers/' . $secure_filename;

            // Ensure cover directory exists
            if (!is_dir(COVER_PATH)) {
                mkdir(COVER_PATH, 0755, true);
            }

            // Move uploaded file
            if (move_uploaded_file($file['tmp_name'], $destination)) {
                // Resize image if needed
                $this->resizeImage($destination, 300, 400); // Standard book cover size

                logActivity("Cover image uploaded successfully: $secure_filename");

                return [
                    'success' => true,
                    'message' => 'Cover image uploaded successfully',
                    'data' => [
                        'filename' => $secure_filename,
                        'original_name' => $file['name'],
                        'path' => $relative_path,
                        'size' => filesize($destination)
                    ]
                ];
            } else {
                throw new Exception('Failed to move uploaded image');
            }

        } catch (Exception $e) {
            logActivity("Cover image upload failed: " . $e->getMessage(), 'ERROR');
            return [
                'success' => false,
                'message' => 'Upload failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Upload cover image with smart routing (semester-based structure)
     * @param array $file $_FILES array element
     * @param array $routing_info Routing information (title, subject, semester, academic_year)
     * @return array Result with success status and details
     */
    public function uploadCoverImageWithRouting($file, $routing_info = []) {
        try {
            // Validate file
            $validation = $this->validateFile($file, ALLOWED_IMAGE_TYPES);
            if (!$validation['success']) {
                return $validation;
            }

            // Generate path based on routing info or fallback to legacy covers directory
            if (!empty($routing_info['title']) && !empty($routing_info['subject']) &&
                !empty($routing_info['semester']) && !empty($routing_info['academic_year'])) {

                $path_info = $this->generateSmartPath($routing_info, 'cover');
                $upload_dir = $path_info['directory'];

                // Get file extension
                $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                $filename = 'cover.' . $file_extension;
                $relative_path = $path_info['relative_path'] . $filename;
            } else {
                // Fallback to legacy covers directory
                $upload_dir = COVER_PATH;
                $filename = generateSecureFilename($file['name']);
                $relative_path = 'uploads/covers/' . $filename;
            }

            // Create directory if it doesn't exist
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }

            $destination = $upload_dir . $filename;

            // Move uploaded file
            if (move_uploaded_file($file['tmp_name'], $destination)) {
                // Resize image if needed
                $this->resizeImage($destination, 300, 400); // Standard book cover size

                logActivity("Cover image uploaded successfully with routing: $filename");

                return [
                    'success' => true,
                    'message' => 'Cover image uploaded successfully',
                    'data' => [
                        'filename' => $filename,
                        'original_name' => $file['name'],
                        'path' => $relative_path,
                        'size' => filesize($destination),
                        'routing_used' => !empty($routing_info)
                    ]
                ];
            } else {
                throw new Exception('Failed to move uploaded image');
            }

        } catch (Exception $e) {
            logActivity("Cover image upload failed: " . $e->getMessage(), 'ERROR');
            return [
                'success' => false,
                'message' => 'Upload failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Validate uploaded file
     * @param array $file $_FILES array element
     * @param array $allowed_types Allowed MIME types
     * @return array Validation result
     */
    private function validateFile($file, $allowed_types) {
        // Check if file was uploaded
        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            return ['success' => false, 'message' => 'No file uploaded'];
        }

        // Check for upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return ['success' => false, 'message' => $this->getUploadErrorMessage($file['error'])];
        }

        // Check file size
        if ($file['size'] > $this->max_size) {
            return [
                'success' => false,
                'message' => 'File size exceeds maximum allowed size of ' . ($this->max_size / 1024 / 1024) . 'MB'
            ];
        }

        // Check MIME type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mime_type, $allowed_types)) {
            return [
                'success' => false,
                'message' => 'Invalid file type. Allowed types: ' . implode(', ', $allowed_types)
            ];
        }

        // Additional security checks for PDF
        if ($mime_type === 'application/pdf') {
            if (!$this->validatePDFSecurity($file['tmp_name'])) {
                return ['success' => false, 'message' => 'PDF file failed security validation'];
            }
        }

        return ['success' => true];
    }

    /**
     * Validate PDF file security
     * @param string $file_path Path to PDF file
     * @return bool
     */
    private function validatePDFSecurity($file_path) {
        // Basic PDF header check
        $handle = fopen($file_path, 'rb');
        $header = fread($handle, 8);
        fclose($handle);

        if (strpos($header, '%PDF-') !== 0) {
            return false;
        }

        // Check file size is reasonable
        $size = filesize($file_path);
        if ($size > MAX_FILE_SIZE || $size < 1024) { // Minimum 1KB
            return false;
        }

        return true;
    }

    /**
     * Extract PDF metadata
     * @param string $file_path Path to PDF file
     * @return array Metadata
     */
    private function extractPDFMetadata($file_path) {
        $metadata = [
            'pages' => null,
            'title' => null,
            'author' => null,
            'subject' => null,
            'creator' => null,
            'creation_date' => null
        ];

        // Try to extract basic info using file commands if available
        if (function_exists('shell_exec') && !empty(shell_exec('which pdfinfo'))) {
            $output = shell_exec("pdfinfo " . escapeshellarg($file_path) . " 2>/dev/null");
            if ($output) {
                $lines = explode("\n", $output);
                foreach ($lines as $line) {
                    if (strpos($line, 'Pages:') === 0) {
                        $metadata['pages'] = (int)trim(substr($line, 6));
                    } elseif (strpos($line, 'Title:') === 0) {
                        $metadata['title'] = trim(substr($line, 6));
                    } elseif (strpos($line, 'Author:') === 0) {
                        $metadata['author'] = trim(substr($line, 7));
                    } elseif (strpos($line, 'Subject:') === 0) {
                        $metadata['subject'] = trim(substr($line, 8));
                    } elseif (strpos($line, 'Creator:') === 0) {
                        $metadata['creator'] = trim(substr($line, 8));
                    } elseif (strpos($line, 'CreationDate:') === 0) {
                        $metadata['creation_date'] = trim(substr($line, 13));
                    }
                }
            }
        }

        return $metadata;
    }

    /**
     * Resize image
     * @param string $file_path Path to image file
     * @param int $max_width Maximum width
     * @param int $max_height Maximum height
     */
    private function resizeImage($file_path, $max_width, $max_height) {
        if (!extension_loaded('gd')) {
            return; // GD extension not available
        }

        $image_info = getimagesize($file_path);
        if (!$image_info) {
            return;
        }

        list($width, $height, $type) = $image_info;

        // Check if resize is needed
        if ($width <= $max_width && $height <= $max_height) {
            return;
        }

        // Calculate new dimensions
        $ratio = min($max_width / $width, $max_height / $height);
        $new_width = (int)($width * $ratio);
        $new_height = (int)($height * $ratio);

        // Create new image
        $new_image = imagecreatetruecolor($new_width, $new_height);

        // Load original image
        switch ($type) {
            case IMAGETYPE_JPEG:
                $source = imagecreatefromjpeg($file_path);
                break;
            case IMAGETYPE_PNG:
                $source = imagecreatefrompng($file_path);
                imagealphablending($new_image, false);
                imagesavealpha($new_image, true);
                break;
            case IMAGETYPE_GIF:
                $source = imagecreatefromgif($file_path);
                break;
            default:
                return;
        }

        // Resize
        imagecopyresampled($new_image, $source, 0, 0, 0, 0, $new_width, $new_height, $width, $height);

        // Save resized image
        switch ($type) {
            case IMAGETYPE_JPEG:
                imagejpeg($new_image, $file_path, 85);
                break;
            case IMAGETYPE_PNG:
                imagepng($new_image, $file_path, 8);
                break;
            case IMAGETYPE_GIF:
                imagegif($new_image, $file_path);
                break;
        }

        // Clean up
        imagedestroy($source);
        imagedestroy($new_image);
    }

    /**
     * Get upload error message
     * @param int $error_code PHP upload error code
     * @return string Error message
     */
    private function getUploadErrorMessage($error_code) {
        switch ($error_code) {
            case UPLOAD_ERR_INI_SIZE:
                return 'File size exceeds server limit';
            case UPLOAD_ERR_FORM_SIZE:
                return 'File size exceeds form limit';
            case UPLOAD_ERR_PARTIAL:
                return 'File was only partially uploaded';
            case UPLOAD_ERR_NO_FILE:
                return 'No file was uploaded';
            case UPLOAD_ERR_NO_TMP_DIR:
                return 'Missing temporary folder';
            case UPLOAD_ERR_CANT_WRITE:
                return 'Failed to write file to disk';
            case UPLOAD_ERR_EXTENSION:
                return 'Upload stopped by extension';
            default:
                return 'Unknown upload error';
        }
    }

    /**
     * Generate smart file path based on routing information
     * @param array $routing_info Routing information
     * @param string $file_type Type of file ('pdf' or 'cover')
     * @return array Path information
     */
    private function generateSmartPath($routing_info, $file_type = 'pdf') {
        // Sanitize path components
        $safe_year = preg_replace('/[^0-9]/', '', $routing_info['academic_year']);
        $safe_semester = preg_replace('/[^a-zA-Z0-9]/', '_', $routing_info['semester']);
        $safe_subject = preg_replace('/[^a-zA-Z0-9]/', '_', $routing_info['subject']);
        $safe_title = preg_replace('/[^a-zA-Z0-9\s]/', '', $routing_info['title']);
        $safe_title = preg_replace('/\s+/', '_', trim($safe_title));

        // Limit title length to prevent very long paths
        if (strlen($safe_title) > 50) {
            $safe_title = substr($safe_title, 0, 50);
        }

        // Build directory path: year/semester/subject/title/
        $directory_path = $safe_year . '/' . $safe_semester . '/' . $safe_subject . '/' . $safe_title . '/';
        $full_directory = $this->upload_path . $directory_path;
        $relative_path = 'uploads/books/' . $directory_path;

        return [
            'directory' => $full_directory,
            'relative_path' => $relative_path,
            'components' => [
                'year' => $safe_year,
                'semester' => $safe_semester,
                'subject' => $safe_subject,
                'title' => $safe_title
            ]
        ];
    }

    /**
     * Delete file
     * @param string $file_path Relative path to file
     * @return bool
     */
    public function deleteFile($file_path) {
        $full_path = BASE_PATH . '/' . $file_path;
        if (file_exists($full_path)) {
            return unlink($full_path);
        }
        return true; // File doesn't exist, consider it deleted
    }
}
?>