<?php

namespace Config;

class File_Security
{
    // File type constants
    const ALLOWED_IMAGE_TYPES = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    const ALLOWED_DOCUMENT_TYPES = ['pdf', 'doc', 'docx'];
    const ALLOWED_ALL_TYPES = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf', 'doc', 'docx'];

    // Size limits (in bytes)
    const MAX_IMAGE_SIZE = 5 * 1024 * 1024; // 5MB for images
    const MAX_DOCUMENT_SIZE = 10 * 1024 * 1024; // 10MB for documents
    const MAX_TOTAL_UPLOAD_SIZE = 50 * 1024 * 1024; // 50MB total per request

    private $rateLimiter;

    public function __construct()
    {
        $this->rateLimiter = new Rate_Limiting();
    }

    /**
     * Validate uploaded file security
     * @param array $file $_FILES array element
     * @param string $allowedTypes Category: 'images', 'documents', 'all'
     * @param string $uploadIdentifier User/IP identifier for rate limiting
     * @return array [valid: bool, errors: array]
     */
    public function validateFile(array $file, string $allowedTypes = 'images', string $uploadIdentifier = ''): array
    {
        $errors = [];

        // Check if file was uploaded successfully
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = $this->getUploadErrorMessage($file['error']);
            return ['valid' => false, 'errors' => $errors];
        }

        // Check rate limiting if identifier provided
        if (!empty($uploadIdentifier)) {
            if (!$this->rateLimiter->checkUploadRateLimit($uploadIdentifier)) {
                $errors[] = 'Upload rate limit exceeded. Please try again later.';
                return ['valid' => false, 'errors' => $errors];
            }
        }

        // Validate file size
        $maxSize = $this->getMaxSizeForType($allowedTypes);
        if ($file['size'] > $maxSize) {
            $errors[] = "File size exceeds maximum allowed size of " . $this->formatBytes($maxSize);
        }

        // Validate file type by extension
        $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowedExtensions = $this->getAllowedExtensions($allowedTypes);

        if (!in_array($fileExtension, $allowedExtensions)) {
            $errors[] = "File type '{$fileExtension}' is not allowed. Allowed types: " . implode(', ', $allowedExtensions);
        }

        // Validate MIME type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!$this->isAllowedMimeType($mimeType, $allowedTypes)) {
            $errors[] = "File MIME type '{$mimeType}' is not allowed";
        }

        // Check for malicious content in filename
        if ($this->hasMaliciousFilename($file['name'])) {
            $errors[] = "Filename contains potentially malicious characters";
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * Validate multiple files in a single request
     * @param array $files Array of $_FILES elements
     * @param string $allowedTypes
     * @param string $uploadIdentifier
     * @return array [valid: bool, errors: array, valid_files: array]
     */
    public function validateMultipleFiles(array $files, string $allowedTypes = 'images', string $uploadIdentifier = ''): array
    {
        $allErrors = [];
        $validFiles = [];

        // Check total upload size
        $totalSize = array_sum(array_column($files, 'size'));
        if ($totalSize > self::MAX_TOTAL_UPLOAD_SIZE) {
            return [
                'valid' => false,
                'errors' => ["Total upload size exceeds maximum of " . $this->formatBytes(self::MAX_TOTAL_UPLOAD_SIZE)],
                'valid_files' => []
            ];
        }

        foreach ($files as $index => $file) {
            $validation = $this->validateFile($file, $allowedTypes, $uploadIdentifier);

            if ($validation['valid']) {
                $validFiles[] = $file;
            } else {
                $allErrors = array_merge($allErrors, array_map(function($error) use ($index, $file) {
                    return "File '{$file['name']}': {$error}";
                }, $validation['errors']));
            }
        }

        return [
            'valid' => empty($allErrors),
            'errors' => $allErrors,
            'valid_files' => $validFiles
        ];
    }

    /**
     * Generate secure filename
     * @param string $originalFilename
     * @param string $prefix Optional prefix
     * @return string Secure filename
     */
    public function generateSecureFilename(string $originalFilename, string $prefix = ''): string
    {
        $extension = strtolower(pathinfo($originalFilename, PATHINFO_EXTENSION));

        // Generate random filename
        $randomString = bin2hex(random_bytes(16));
        $timestamp = time();

        $filename = $prefix . $timestamp . '_' . $randomString;
        if (!empty($extension)) {
            $filename .= '.' . $extension;
        }

        return $filename;
    }

    /**
     * Move uploaded file to secure location with validation
     * @param array $file
     * @param string $destinationPath
     * @param string $filename
     * @return bool Success
     */
    public function moveUploadedFileSecurely(array $file, string $destinationPath, string $filename): bool
    {
        // Ensure destination directory exists and is writable
        if (!is_dir($destinationPath)) {
            mkdir($destinationPath, 0755, true);
        }

        if (!is_writable($destinationPath)) {
            return false;
        }

        $fullPath = $destinationPath . DIRECTORY_SEPARATOR . $filename;

        // Move the file
        if (move_uploaded_file($file['tmp_name'], $fullPath)) {
            // Set proper permissions (readable by owner and group, not world-writable)
            chmod($fullPath, 0644);
            return true;
        }

        return false;
    }

    /**
     * Scan file for basic malware patterns (basic check)
     * @param string $filePath
     * @return bool True if suspicious content found
     */
    public function basicMalwareScan(string $filePath): bool
    {
        // This is a very basic check - in production, use proper antivirus software
        $content = file_get_contents($filePath);

        // Check for common malicious patterns
        $maliciousPatterns = [
            '<?php', 'eval(', 'base64_decode(', 'system(', 'exec(',
            '<script', 'javascript:', 'vbscript:', 'onload=', 'onerror='
        ];

        foreach ($maliciousPatterns as $pattern) {
            if (stripos($content, $pattern) !== false) {
                return true; // Suspicious content found
            }
        }

        return false;
    }

    /**
     * Get upload directory for different file types
     * @param string $type 'property_images', 'kyc_documents', etc.
     * @return string Directory path
     */
    public function getUploadDirectory(string $type): string
    {
        $baseDir = __DIR__ . '/../public/assets/uploads/';

        $directories = [
            'property_images' => $baseDir . 'properties/',
            'kyc_documents' => $baseDir . 'kyc/',
            'user_avatars' => $baseDir . 'avatars/',
            'general' => $baseDir . 'general/'
        ];

        return $directories[$type] ?? $directories['general'];
    }

    /**
     * Clean up old temporary files
     * @param string $directory
     * @param int $maxAgeHours
     */
    public function cleanupTempFiles(string $directory, int $maxAgeHours = 24): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $files = glob($directory . '*');
        $now = time();
        $maxAge = $maxAgeHours * 3600;

        foreach ($files as $file) {
            if (is_file($file) && ($now - filemtime($file)) > $maxAge) {
                unlink($file);
            }
        }
    }

    // Private helper methods

    private function getMaxSizeForType(string $type): int
    {
        switch ($type) {
            case 'images':
                return self::MAX_IMAGE_SIZE;
            case 'documents':
                return self::MAX_DOCUMENT_SIZE;
            case 'all':
            default:
                return max(self::MAX_IMAGE_SIZE, self::MAX_DOCUMENT_SIZE);
        }
    }

    private function getAllowedExtensions(string $type): array
    {
        switch ($type) {
            case 'images':
                return self::ALLOWED_IMAGE_TYPES;
            case 'documents':
                return self::ALLOWED_DOCUMENT_TYPES;
            case 'all':
            default:
                return self::ALLOWED_ALL_TYPES;
        }
    }

    private function isAllowedMimeType(string $mimeType, string $type): bool
    {
        $allowedMimeTypes = [
            'images' => ['image/jpeg', 'image/png', 'image/gif', 'image/webp'],
            'documents' => ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
            'all' => ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document']
        ];

        return in_array($mimeType, $allowedMimeTypes[$type] ?? $allowedMimeTypes['all']);
    }

    private function hasMaliciousFilename(string $filename): bool
    {
        // Check for directory traversal attempts
        if (strpos($filename, '..') !== false || strpos($filename, '/') !== false || strpos($filename, '\\') !== false) {
            return true;
        }

        // Check for null bytes
        if (strpos($filename, "\0") !== false) {
            return true;
        }

        // Check for suspicious extensions
        $suspiciousPatterns = ['.php', '.exe', '.bat', '.cmd', '.scr', '.pif'];
        foreach ($suspiciousPatterns as $pattern) {
            if (stripos($filename, $pattern) !== false) {
                return true;
            }
        }

        return false;
    }

    private function getUploadErrorMessage(int $errorCode): string
    {
        switch ($errorCode) {
            case UPLOAD_ERR_INI_SIZE:
                return 'File exceeds server upload size limit';
            case UPLOAD_ERR_FORM_SIZE:
                return 'File exceeds form upload size limit';
            case UPLOAD_ERR_PARTIAL:
                return 'File was only partially uploaded';
            case UPLOAD_ERR_NO_FILE:
                return 'No file was uploaded';
            case UPLOAD_ERR_NO_TMP_DIR:
                return 'Missing temporary folder';
            case UPLOAD_ERR_CANT_WRITE:
                return 'Failed to write file to disk';
            case UPLOAD_ERR_EXTENSION:
                return 'File upload stopped by extension';
            default:
                return 'Unknown upload error';
        }
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        $bytes /= pow(1024, $pow);

        return round($bytes, 2) . ' ' . $units[$pow];
    }
}
