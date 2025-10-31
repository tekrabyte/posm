<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/config.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/**
 * Email Handler untuk notifikasi real-time
 * Menggunakan PHPMailer dengan Gmail SMTP
 */
class EmailHandler {
    private $pdo;
    private $config;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->loadConfig();
    }
    
    /**
     * Load email configuration dari database
     */
    private function loadConfig() {
        try {
            $stmt = $this->pdo->query("SELECT * FROM email_config WHERE id = 1 LIMIT 1");
            $this->config = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$this->config) {
                // Default config jika belum ada
                $this->config = [
                    'smtp_host' => 'smtp.gmail.com',
                    'smtp_port' => 587,
                    'smtp_username' => '',
                    'smtp_password' => '',
                    'recipient_email' => '',
                    'is_enabled' => 0
                ];
            }
        } catch (Exception $e) {
            $this->config = null;
        }
    }
    
    /**
     * Kirim email notification
     */
    public function sendNotification($subject, $message, $notificationType = 'mixed', $relatedId = null) {
        // Cek apakah email enabled
        if (!$this->config || !$this->config['is_enabled']) {
            return ['success' => false, 'message' => 'Email notification disabled'];
        }
        
        // Validasi config
        if (empty($this->config['smtp_username']) || empty($this->config['smtp_password']) || empty($this->config['recipient_email'])) {
            return ['success' => false, 'message' => 'Email configuration incomplete'];
        }
        
        try {
            $mail = new PHPMailer(true);
            
            // Server settings
            $mail->isSMTP();
            $mail->Host = $this->config['smtp_host'];
            $mail->SMTPAuth = true;
            $mail->Username = $this->config['smtp_username'];
            $mail->Password = $this->config['smtp_password'];
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = $this->config['smtp_port'];
            $mail->CharSet = 'UTF-8';
            
            // Recipients
            $mail->setFrom($this->config['smtp_username'], 'Admin POSM Notification');
            $mail->addAddress($this->config['recipient_email']);
            
            // Content
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $this->formatEmailBody($message);
            $mail->AltBody = strip_tags($message);
            
            // Send
            $mail->send();
            
            // Log success
            $this->logNotification($notificationType, $relatedId, $subject, $message, 'sent');
            
            // Update last_sent
            $this->updateLastSent();
            
            return ['success' => true, 'message' => 'Email sent successfully'];
            
        } catch (Exception $e) {
            // Log error
            $this->logNotification($notificationType, $relatedId, $subject, $message, 'failed', $e->getMessage());
            
            return ['success' => false, 'message' => 'Email failed: ' . $e->getMessage()];
        }
    }
    
    /**
     * Format email body dengan HTML
     */
    private function formatEmailBody($message) {
        $html = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; border-radius: 8px 8px 0 0; }
                .content { background: #f9fafb; padding: 20px; border: 1px solid #e5e7eb; }
                .footer { background: #374151; color: white; padding: 15px; text-align: center; border-radius: 0 0 8px 8px; font-size: 12px; }
                .badge { display: inline-block; padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: bold; }
                .badge-success { background: #10b981; color: white; }
                .badge-danger { background: #ef4444; color: white; }
                .badge-info { background: #3b82f6; color: white; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h2 style="margin: 0;">🔔 POSM Notification</h2>
                    <p style="margin: 5px 0 0 0; opacity: 0.9;">Data Baru Terdeteksi</p>
                </div>
                <div class="content">
                    ' . nl2br($message) . '
                </div>
                <div class="footer">
                    <p style="margin: 0;">Admin Panel POSM © 2025</p>
                    <p style="margin: 5px 0 0 0; opacity: 0.7;">Notifikasi otomatis - Jangan balas email ini</p>
                </div>
            </div>
        </body>
        </html>
        ';
        
        return $html;
    }
    
    /**
     * Log notification ke database
     */
    private function logNotification($type, $relatedId, $subject, $message, $status, $error = null) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO email_notifications 
                (notification_type, related_id, subject, message, recipient_email, status, sent_at, error_message)
                VALUES (?, ?, ?, ?, ?, ?, NOW(), ?)
            ");
            
            $stmt->execute([
                $type,
                $relatedId,
                $subject,
                $message,
                $this->config['recipient_email'],
                $status,
                $error
            ]);
        } catch (Exception $e) {
            // Silent fail - jangan break process
        }
    }
    
    /**
     * Update last_sent timestamp
     */
    private function updateLastSent() {
        try {
            $stmt = $this->pdo->prepare("UPDATE email_config SET last_sent = NOW() WHERE id = 1");
            $stmt->execute();
        } catch (Exception $e) {
            // Silent fail
        }
    }
    
    /**
     * Update email config
     */
    public function updateConfig($data) {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE email_config 
                SET smtp_host = ?, smtp_port = ?, smtp_username = ?, smtp_password = ?, 
                    recipient_email = ?, is_enabled = ?
                WHERE id = 1
            ");
            
            $stmt->execute([
                $data['smtp_host'],
                $data['smtp_port'],
                $data['smtp_username'],
                $data['smtp_password'],
                $data['recipient_email'],
                $data['is_enabled']
            ]);
            
            // Reload config
            $this->loadConfig();
            
            return ['success' => true, 'message' => 'Email config updated'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Failed to update: ' . $e->getMessage()];
        }
    }
    
    /**
     * Get current config
     */
    public function getConfig() {
        return $this->config;
    }
    
    /**
     * Test email connection
     */
    public function testConnection() {
        try {
            $mail = new PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = $this->config['smtp_host'];
            $mail->SMTPAuth = true;
            $mail->Username = $this->config['smtp_username'];
            $mail->Password = $this->config['smtp_password'];
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = $this->config['smtp_port'];
            $mail->Timeout = 10;
            
            // Test connect
            $mail->smtpConnect();
            
            return ['success' => true, 'message' => 'Connection successful'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Connection failed: ' . $e->getMessage()];
        }
    }
}
?>