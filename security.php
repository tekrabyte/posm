<?php
/**
 * Security Functions
 * Handles CSRF protection and session management
 */

// Generate CSRF Token
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Validate CSRF Token
function validateCSRFToken($token) {
    if (!isset($_SESSION['csrf_token'])) {
        return false;
    }
    
    if (!hash_equals($_SESSION['csrf_token'], $token)) {
        return false;
    }
    
    return true;
}

// Get CSRF Token (for use in AJAX)
function getCSRFToken() {
    return $_SESSION['csrf_token'] ?? generateCSRFToken();
}

// Check session timeout (30 minutes)
function checkSessionTimeout() {
    $timeout_duration = 1800; // 30 minutes in seconds
    
    if (isset($_SESSION['LAST_ACTIVITY'])) {
        $elapsed_time = time() - $_SESSION['LAST_ACTIVITY'];
        
        if ($elapsed_time > $timeout_duration) {
            // Session expired
            session_unset();
            session_destroy();
            return false;
        }
    }
    
    // Update last activity time
    $_SESSION['LAST_ACTIVITY'] = time();
    return true;
}

// Sanitize input
function sanitizeInput($data) {
    if (is_array($data)) {
        return array_map('sanitizeInput', $data);
    }
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

// Validate required fields
function validateRequired($fields, $data) {
    $missing = [];
    foreach ($fields as $field) {
        if (!isset($data[$field]) || empty(trim($data[$field]))) {
            $missing[] = $field;
        }
    }
    return $missing;
}

// JSON Response with better error messages
function secureJsonResponse($success, $message, $data = [], $summary = [], $httpCode = 200) {
    http_response_code($httpCode);
    
    $response = [
        'success' => $success,
        'message' => $message
    ];
    
    if (!empty($data)) {
        $response['data'] = $data;
    }
    
    if (!empty($summary)) {
        $response['summary'] = $summary;
    }
    
    // Add CSRF token to response for subsequent requests
    if ($success && isset($_SESSION['csrf_token'])) {
        $response['csrf_token'] = $_SESSION['csrf_token'];
    }
    
    echo json_encode($response);
    exit;
}
?>
