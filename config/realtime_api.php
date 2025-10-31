<?php
// Suppress all errors and warnings to ensure clean JSON output
error_reporting(0);
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/email_handler.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? '';

/**
 * API untuk Real-time Polling & Email Management
 */

switch ($action) {
    
    /**
     * Get latest changes - untuk polling
     */
    case 'get_latest_changes':
        try {
            $lastCheck = $_GET['last_check'] ?? date('Y-m-d H:i:s', strtotime('-10 seconds'));
            
            // Check if data_changes table exists first
            $tableCheck = $pdo->query("SHOW TABLES LIKE 'data_changes'");
            if (!$tableCheck->fetch()) {
                // Table doesn't exist, return empty response
                echo json_encode([
                    'success' => true,
                    'has_changes' => false,
                    'changes' => [],
                    'current_time' => date('Y-m-d H:i:s'),
                    'info' => 'data_changes table not found - please run migration'
                ]);
                break;
            }
            
            // Query perubahan terbaru
            $stmt = $pdo->prepare("
                SELECT 
                    dc.id,
                    dc.table_name,
                    dc.record_id,
                    dc.change_type,
                    dc.change_timestamp,
                    CASE 
                        WHEN dc.table_name = 'setoran' THEN s.employee_id
                        WHEN dc.table_name = 'cash_flow_management' THEN c.store_id
                    END as identifier
                FROM data_changes dc
                LEFT JOIN setoran s ON dc.table_name = 'setoran' AND dc.record_id = s.id
                LEFT JOIN cash_flow_management c ON dc.table_name = 'cash_flow_management' AND dc.record_id = c.id
                WHERE dc.change_timestamp > ? 
                  AND dc.notified = 0
                ORDER BY dc.change_timestamp DESC
                LIMIT 50
            ");
            
            $stmt->execute([$lastCheck]);
            $changes = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Mark as notified
            if (!empty($changes)) {
                $ids = array_column($changes, 'id');
                $placeholders = implode(',', array_fill(0, count($ids), '?'));
                $stmt = $pdo->prepare("UPDATE data_changes SET notified = 1 WHERE id IN ($placeholders)");
                $stmt->execute($ids);
            }
            
            echo json_encode([
                'success' => true,
                'has_changes' => !empty($changes),
                'changes' => $changes,
                'current_time' => date('Y-m-d H:i:s')
            ]);
            
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false, 
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
        }
        break;
    
    /**
     * Track new data change (dipanggil setelah insert/update/delete)
     */
    case 'track_change':
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            
            $stmt = $pdo->prepare("
                INSERT INTO data_changes (table_name, record_id, change_type)
                VALUES (?, ?, ?)
            ");
            
            $stmt->execute([
                $data['table_name'],
                $data['record_id'],
                $data['change_type']
            ]);
            
            echo json_encode(['success' => true]);
            
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;
    
    /**
     * Get email config
     */
    case 'get_email_config':
        try {
            $emailHandler = new EmailHandler($pdo);
            $config = $emailHandler->getConfig();
            
            // Hide password untuk security
            if ($config && isset($config['smtp_password'])) {
                $config['smtp_password'] = str_repeat('*', strlen($config['smtp_password']));
            }
            
            echo json_encode([
                'success' => true,
                'config' => $config
            ]);
            
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;
    
    /**
     * Update email config
     */
    case 'update_email_config':
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            
            // Jika password adalah masked (****), ambil dari database
            if (isset($data['smtp_password']) && preg_match('/^\*+$/', $data['smtp_password'])) {
                $stmt = $pdo->query("SELECT smtp_password FROM email_config WHERE id = 1");
                $current = $stmt->fetch(PDO::FETCH_ASSOC);
                $data['smtp_password'] = $current['smtp_password'];
            }
            
            $emailHandler = new EmailHandler($pdo);
            $result = $emailHandler->updateConfig($data);
            
            echo json_encode($result);
            
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;
    
    /**
     * Test email connection
     */
    case 'test_email':
        try {
            $emailHandler = new EmailHandler($pdo);
            $result = $emailHandler->testConnection();
            
            echo json_encode($result);
            
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;
    
    /**
     * Send test email
     */
    case 'send_test_email':
        try {
            $emailHandler = new EmailHandler($pdo);
            
            $subject = '🧪 Test Email - POSM Notification';
            $message = '
                <h3>Test Email Berhasil!</h3>
                <p>Ini adalah email test dari sistem Admin Panel POSM.</p>
                <p><strong>Waktu:</strong> ' . date('d/m/Y H:i:s') . '</p>
                <p>Jika Anda menerima email ini, berarti konfigurasi email sudah benar.</p>
            ';
            
            $result = $emailHandler->sendNotification($subject, $message, 'test');
            
            echo json_encode($result);
            
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;
    
    /**
     * Get notification history
     */
    case 'get_notification_history':
        try {
            $limit = $_GET['limit'] ?? 50;
            
            // Check if table exists first
            $tableCheck = $pdo->query("SHOW TABLES LIKE 'email_notifications'");
            if (!$tableCheck->fetch()) {
                // Table doesn't exist, return empty response
                echo json_encode([
                    'success' => true,
                    'history' => [],
                    'info' => 'email_notifications table not found - please run migration'
                ]);
                break;
            }
            
            $stmt = $pdo->prepare("
                SELECT * FROM email_notifications 
                ORDER BY created_at DESC 
                LIMIT ?
            ");
            
            $stmt->execute([$limit]);
            $history = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'history' => $history
            ]);
            
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false, 
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
        }
        break;
    
    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}
?>