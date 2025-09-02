<?php
// STRICT ERROR HANDLING
error_reporting(0); // Turn off all error reporting
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php_errors.log');

// Set Nigeria timezone
date_default_timezone_set('Africa/Lagos');

// Database configuration
define('DB_HOST', 'sql313.byethost16.com');
define('DB_USER', 'b16_39626919');
define('DB_PASS', 'bankyj123');
define('DB_NAME', 'b16_39626919_cipherchat');

// Encryption
define('ENCRYPTION_KEY', 'your_256bit_key_here');
define('ENCRYPTION_METHOD', 'AES-256-CBC');

// File uploads
define('UPLOAD_DIR', __DIR__ . '/uploads/');
define('MAX_FILE_SIZE', 10 * 1024 * 1024); // 10MB
define('ALLOWED_TYPES', ['image/jpeg', 'image/png', 'audio/mpeg', 'application/pdf']);

// Ensure JSON output
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

// Global error handler
set_exception_handler(function($e) {
    error_log("Uncaught Exception: " . $e->getMessage());
    http_response_code(500);
    die(json_encode([
        'status' => 'error',
        'message' => 'An internal error occurred'
    ]));
});