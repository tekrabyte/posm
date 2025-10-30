<?php
/**
 * Security Functions - Enhanced Edition
 * Handles CSRF protection, session management, RBAC, and rate limiting
 */

// =========================================================
// CSRF PROTECTION
// =========================================================

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

// =========================================================
// SESSION MANAGEMENT
// =========================================================

// Check session timeout (30 minutes)
function checkSessionTimeout() {
    $timeout_duration = 1800; // 30 minutes in seconds
    
    if (isset($_SESSION['LAST_ACTIVITY'])) {
        $elapsed_time = time() - $_SESSION['LAST_ACTIVITY'];
        
        if ($elapsed_time > $timeout_duration) {
            // Log session timeout
            if (isset($_SESSION['user_id'])) {
                logActivity($_SESSION['user_id'], 'session_timeout', 'Session expired due to inactivity');
            }
            
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

// =========================================================
// INPUT VALIDATION & SANITIZATION
// =========================================================

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

// =========================================================
// ROLE-BASED ACCESS CONTROL (RBAC)
// =========================================================

// Check if user has specific permission
function hasPermission($permission_name) {
    global $pdo;
    
    if (!isset($_SESSION['user_id'])) {
        return false;
    }
    
    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count
            FROM users u
            JOIN roles r ON u.role_id = r.id
            JOIN role_permissions rp ON r.id = rp.role_id
            JOIN permissions p ON rp.permission_id = p.id
            WHERE u.id = ? AND p.permission_name = ? AND u.is_active = 1
        ");
        $stmt->execute([$_SESSION['user_id'], $permission_name]);
        $result = $stmt->fetch();
        
        return ($result['count'] > 0);
    } catch (Exception $e) {
        error_log("Permission check error: " . $e->getMessage());
        return false;
    }
}

// Get user's role name
function getUserRole() {
    global $pdo;
    
    if (!isset($_SESSION['user_id'])) {
        return null;
    }
    
    try {
        $stmt = $pdo->prepare("
            SELECT r.role_name, r.role_display_name
            FROM users u
            JOIN roles r ON u.role_id = r.id
            WHERE u.id = ?
        ");
        $stmt->execute([$_SESSION['user_id']]);
        return $stmt->fetch();
    } catch (Exception $e) {
        error_log("Get role error: " . $e->getMessage());
        return null;
    }
}

// Get all permissions for current user
function getUserPermissions() {
    global $pdo;
    
    if (!isset($_SESSION['user_id'])) {
        return [];
    }
    
    try {
        $stmt = $pdo->prepare("
            SELECT p.permission_name, p.permission_display_name
            FROM users u
            JOIN roles r ON u.role_id = r.id
            JOIN role_permissions rp ON r.id = rp.role_id
            JOIN permissions p ON rp.permission_id = p.id
            WHERE u.id = ? AND u.is_active = 1
        ");
        $stmt->execute([$_SESSION['user_id']]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Get permissions error: " . $e->getMessage());
        return [];
    }
}

// Require permission (blocks execution if not authorized)
function requirePermission($permission_name) {
    if (!hasPermission($permission_name)) {
        logActivity($_SESSION['user_id'] ?? null, 'access_denied', "Attempted to access: $permission_name");
        
        header('Content-Type: application/json');
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'Akses ditolak. Anda tidak memiliki izin untuk melakukan operasi ini.',
            'error_code' => 'PERMISSION_DENIED'
        ]);
        exit;
    }
}

// =========================================================
// ACTIVITY LOGGING
// =========================================================

// Log user activity
function logActivity($user_id, $action, $description = '') {
    global $pdo;
    
    try {
        $username = $_SESSION['username'] ?? 'Unknown';
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
        
        $stmt = $pdo->prepare("
            INSERT INTO activity_log (user_id, username, action, description, ip_address, user_agent)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$user_id, $username, $action, $description, $ip_address, $user_agent]);
    } catch (Exception $e) {
        error_log("Activity log error: " . $e->getMessage());
    }
}

// Get recent activities
function getRecentActivities($limit = 50) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM activity_log
            ORDER BY created_at DESC
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Get activities error: " . $e->getMessage());
        return [];
    }
}

// =========================================================
// RATE LIMITING
// =========================================================

// Check rate limit
function checkRateLimit($endpoint, $max_attempts = 60, $time_window = 60) {
    global $pdo;
    
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    
    try {
        // Clean up old records
        $pdo->exec("DELETE FROM rate_limit_log WHERE blocked_until < NOW() - INTERVAL 1 HOUR");
        
        // Check if IP is currently blocked
        $stmt = $pdo->prepare("
            SELECT blocked_until FROM rate_limit_log
            WHERE ip_address = ? AND endpoint = ? AND blocked_until > NOW()
        ");
        $stmt->execute([$ip_address, $endpoint]);
        $blocked = $stmt->fetch();
        
        if ($blocked) {
            $remaining = strtotime($blocked['blocked_until']) - time();
            http_response_code(429);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => "Terlalu banyak percobaan. Coba lagi dalam " . ceil($remaining / 60) . " menit.",
                'error_code' => 'RATE_LIMIT_EXCEEDED',
                'retry_after' => $remaining
            ]);
            exit;
        }
        
        // Check attempts in time window
        $stmt = $pdo->prepare("
            SELECT attempt_count, last_attempt 
            FROM rate_limit_log
            WHERE ip_address = ? AND endpoint = ?
        ");
        $stmt->execute([$ip_address, $endpoint]);
        $record = $stmt->fetch();
        
        if ($record) {
            $time_since_last = time() - strtotime($record['last_attempt']);
            
            if ($time_since_last < $time_window) {
                $new_count = $record['attempt_count'] + 1;
                
                // Block if exceeded
                if ($new_count > $max_attempts) {
                    $block_duration = 15 * 60; // 15 minutes
                    $blocked_until = date('Y-m-d H:i:s', time() + $block_duration);
                    
                    $stmt = $pdo->prepare("
                        UPDATE rate_limit_log
                        SET attempt_count = ?, blocked_until = ?
                        WHERE ip_address = ? AND endpoint = ?
                    ");
                    $stmt->execute([$new_count, $blocked_until, $ip_address, $endpoint]);
                    
                    logActivity(null, 'rate_limit_blocked', "IP $ip_address blocked for endpoint $endpoint");
                    
                    http_response_code(429);
                    header('Content-Type: application/json');
                    echo json_encode([
                        'success' => false,
                        'message' => 'Terlalu banyak percobaan. Akun Anda diblokir sementara selama 15 menit.',
                        'error_code' => 'RATE_LIMIT_EXCEEDED',
                        'retry_after' => $block_duration
                    ]);
                    exit;
                }
                
                // Update count
                $stmt = $pdo->prepare("
                    UPDATE rate_limit_log
                    SET attempt_count = ?, last_attempt = NOW()
                    WHERE ip_address = ? AND endpoint = ?
                ");
                $stmt->execute([$new_count, $ip_address, $endpoint]);
            } else {
                // Reset count if time window passed
                $stmt = $pdo->prepare("
                    UPDATE rate_limit_log
                    SET attempt_count = 1, last_attempt = NOW()
                    WHERE ip_address = ? AND endpoint = ?
                ");
                $stmt->execute([$ip_address, $endpoint]);
            }
        } else {
            // Create new record
            $stmt = $pdo->prepare("
                INSERT INTO rate_limit_log (ip_address, endpoint, attempt_count)
                VALUES (?, ?, 1)
            ");
            $stmt->execute([$ip_address, $endpoint]);
        }
        
        return true;
    } catch (Exception $e) {
        error_log("Rate limit error: " . $e->getMessage());
        return true; // Allow on error
    }
}

// =========================================================
// RESPONSE HELPERS
// =========================================================

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
    
    // Add user permissions if logged in
    if ($success && isset($_SESSION['user_id'])) {
        $response['user_role'] = getUserRole();
    }
    
    echo json_encode($response);
    exit;
}

// =========================================================
// UTILITY FUNCTIONS
// =========================================================

// Get client IP address (works behind proxies)
function getClientIP() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        return $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        return $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
        return $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    }
}

// Validate email
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

// Validate phone number (Indonesian format)
function validatePhone($phone) {
    $phone = preg_replace('/[^0-9]/', '', $phone);
    return preg_match('/^(08|628)[0-9]{8,11}$/', $phone);
}

?>
