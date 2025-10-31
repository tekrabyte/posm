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
            $mail->setFrom($this->config['smtp_username'], 'Pemberitahuan POM MINI');
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
            $errorMsg = $e->getMessage();
            
            // Provide helpful error messages for common Gmail issues
            if (strpos($errorMsg, 'Username and Password not accepted') !== false || 
                strpos($errorMsg, 'Invalid credentials') !== false) {
                $errorMsg = 'Gmail credentials ditolak. Pastikan Anda menggunakan "App Password" dari Google, bukan password Gmail biasa. Cara membuat App Password: https://myaccount.google.com/apppasswords';
            } elseif (strpos($errorMsg, 'Could not connect to SMTP host') !== false) {
                $errorMsg = 'Tidak dapat terhubung ke server Gmail. Periksa koneksi internet atau pastikan port 587 tidak diblokir firewall.';
            } elseif (strpos($errorMsg, 'SMTP connect() failed') !== false) {
                $errorMsg = 'Gagal koneksi SMTP. Pastikan SMTP Host (smtp.gmail.com) dan Port (587) sudah benar.';
            }
            
            // Log error
            $this->logNotification($notificationType, $relatedId, $subject, $message, 'failed', $errorMsg);
            
            return ['success' => false, 'message' => 'Email failed: ' . $errorMsg];
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
                    <h2 style="margin: 0;">🔔 Pemberitahuan POM MINI </h2>
                    <p style="margin: 5px 0 0 0; opacity: 0.9;">Data Baru Terdeteksi</p>
                </div>
                <div class="content">
                    ' . nl2br($message) . '
                </div>
                <div class="footer">
                    <p style="margin: 0;">POM MINI © 2025</p>
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
            $errorMsg = $e->getMessage();
            
            // Provide helpful error messages for common Gmail issues
            if (strpos($errorMsg, 'Username and Password not accepted') !== false || 
                strpos($errorMsg, 'Invalid credentials') !== false) {
                $errorMsg = 'Gmail credentials ditolak. Pastikan Anda menggunakan "App Password" dari Google, bukan password Gmail biasa. Cara membuat App Password: https://myaccount.google.com/apppasswords';
            } elseif (strpos($errorMsg, 'Could not connect to SMTP host') !== false) {
                $errorMsg = 'Tidak dapat terhubung ke server Gmail. Periksa koneksi internet atau pastikan port 587 tidak diblokir firewall.';
            } elseif (strpos($errorMsg, 'SMTP connect() failed') !== false) {
                $errorMsg = 'Gagal koneksi SMTP. Pastikan SMTP Host (smtp.gmail.com) dan Port (587) sudah benar.';
            }
            
            return ['success' => false, 'message' => 'Connection failed: ' . $errorMsg];
        }
    }
    
    /**
     * Kirim Laporan Harian
     * Format: Info 1 Bulan (All Store + Per Store) dan Info 1 Hari (Per Store)
     */
    public function sendDailyReport($forceOverride = false) {
        // Cek apakah email enabled
        if (!$this->config || !$this->config['is_enabled']) {
            return ['success' => false, 'message' => 'Email notification disabled'];
        }
        
        // Validasi config
        if (empty($this->config['smtp_username']) || empty($this->config['smtp_password']) || empty($this->config['recipient_email'])) {
            return ['success' => false, 'message' => 'Email configuration incomplete'];
        }
        
        // Cek apakah sudah kirim hari ini (anti-duplicate) kecuali force override
        if (!$forceOverride && isset($this->config['last_daily_report_sent'])) {
            $lastSent = date('Y-m-d', strtotime($this->config['last_daily_report_sent']));
            $today = date('Y-m-d');
            if ($lastSent === $today) {
                return ['success' => false, 'message' => 'Laporan harian sudah dikirim hari ini'];
            }
        }
        
        try {
            // Ambil data laporan
            $reportData = $this->generateReportData();
            
            // Format email body
            $emailBody = $this->formatDailyReportEmail($reportData);
            
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
            $mail->setFrom($this->config['smtp_username'], 'Laporan Harian POM MINI');
            $mail->addAddress($this->config['recipient_email']);
            
            // Content
            $mail->isHTML(true);
            $mail->Subject = '📊 Laporan Harian POM MINI - ' . date('d F Y');
            $mail->Body = $emailBody;
            $mail->AltBody = 'Laporan Harian Wallet POM MINI';
            
            // Send
            $mail->send();
            
            // Log success
            $this->logNotification('daily_report', null, 'Laporan Harian', 'Laporan harian berhasil dikirim', 'sent');
            
            // Update last_daily_report_sent
            $this->updateLastDailyReportSent();
            
            return ['success' => true, 'message' => 'Laporan harian berhasil dikirim'];
            
        } catch (Exception $e) {
            $errorMsg = $e->getMessage();
            
            // Provide helpful error messages for common Gmail issues
            if (strpos($errorMsg, 'Username and Password not accepted') !== false || 
                strpos($errorMsg, 'Invalid credentials') !== false) {
                $errorMsg = 'Gmail credentials ditolak. Pastikan Anda menggunakan "App Password" dari Google, bukan password Gmail biasa. Cara membuat App Password: https://myaccount.google.com/apppasswords';
            } elseif (strpos($errorMsg, 'Could not connect to SMTP host') !== false) {
                $errorMsg = 'Tidak dapat terhubung ke server Gmail. Periksa koneksi internet atau pastikan port 587 tidak diblokir firewall.';
            } elseif (strpos($errorMsg, 'SMTP connect() failed') !== false) {
                $errorMsg = 'Gagal koneksi SMTP. Pastikan SMTP Host (smtp.gmail.com) dan Port (587) sudah benar.';
            }
            
            // Log error
            $this->logNotification('daily_report', null, 'Laporan Harian', 'Gagal kirim laporan harian', 'failed', $errorMsg);
            
            return ['success' => false, 'message' => 'Gagal kirim laporan: ' . $errorMsg];
        }
    }
    
    /**
     * Generate data untuk laporan harian
     */
    private function generateReportData() {
        $data = [];
        
        // 1. Info 1 Bulan Wallet (All Store)
        $data['monthly_all'] = $this->getMonthlyDataAllStores();
        
        // 2. Info 1 Bulan Wallet (Per Store)
        $data['monthly_per_store'] = $this->getMonthlyDataPerStore();
        
        // 3. Info 1 Hari Wallet (Per Store)
        $data['daily_per_store'] = $this->getDailyDataPerStore();
        
        return $data;
    }
    
    /**
     * Get data 1 bulan untuk semua store
     */
    private function getMonthlyDataAllStores() {
        // Query Cashflow Management untuk Income & Expense
        $query_cf = "
            SELECT
                SUM(CASE WHEN type = 'Pemasukan' THEN amount ELSE 0 END) as total_pemasukan,
                SUM(CASE WHEN type = 'Pengeluaran' THEN amount ELSE 0 END) as total_pengeluaran
            FROM cash_flow_management
            WHERE MONTH(tanggal) = MONTH(CURRENT_DATE())
              AND YEAR(tanggal) = YEAR(CURRENT_DATE())
        ";
        
        $stmt_cf = $this->pdo->query($query_cf);
        $cf_data = $stmt_cf->fetch(PDO::FETCH_ASSOC);
        
        // Query Total Liter dari tabel Setoran
        $query_liter = "
            SELECT
                COALESCE(SUM(total_liter), 0) as total_liter
            FROM setoran
            WHERE MONTH(tanggal) = MONTH(CURRENT_DATE())
              AND YEAR(tanggal) = YEAR(CURRENT_DATE())
        ";
        
        $stmt_liter = $this->pdo->query($query_liter);
        $liter_data = $stmt_liter->fetch(PDO::FETCH_ASSOC);
        
        $result = [
            'total_pemasukan' => $cf_data['total_pemasukan'] ?? 0,
            'total_pengeluaran' => $cf_data['total_pengeluaran'] ?? 0,
            'total_liter' => $liter_data['total_liter'] ?? 0
        ];
        
        // Hitung saldo bersih
        $result['saldo_bersih'] = $result['total_pemasukan'] - $result['total_pengeluaran'];
        
        return $result;
    }
    
    /**
     * Get data 1 bulan per store
     */
    private function getMonthlyDataPerStore() {
        // Query Cashflow Management per store
        $query = "
            SELECT
                st.store_name,
                COALESCE(SUM(CASE WHEN cfm.type = 'Pemasukan' THEN cfm.amount ELSE 0 END), 0) as total_pemasukan,
                COALESCE(SUM(CASE WHEN cfm.type = 'Pengeluaran' THEN cfm.amount ELSE 0 END), 0) as total_pengeluaran
            FROM stores st
            LEFT JOIN cash_flow_management cfm ON st.id = cfm.store_id 
                AND MONTH(cfm.tanggal) = MONTH(CURRENT_DATE())
                AND YEAR(cfm.tanggal) = YEAR(CURRENT_DATE())
            GROUP BY st.id, st.store_name
            ORDER BY st.store_name
        ";
        
        $stmt = $this->pdo->query($query);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Query liter per store
        $query_liter = "
            SELECT
                s.store_name,
                COALESCE(SUM(st.total_liter), 0) as total_liter
            FROM setoran st
            LEFT JOIN stores s ON st.store_id = s.id
            WHERE MONTH(st.tanggal) = MONTH(CURRENT_DATE())
              AND YEAR(st.tanggal) = YEAR(CURRENT_DATE())
            GROUP BY st.store_id, s.store_name
        ";
        
        $stmt_liter = $this->pdo->query($query_liter);
        $liter_data = $stmt_liter->fetchAll(PDO::FETCH_ASSOC);
        
        // Create a map of store_name => total_liter
        $liter_map = [];
        foreach ($liter_data as $liter_row) {
            $liter_map[$liter_row['store_name']] = $liter_row['total_liter'];
        }
        
        // Merge liter data and calculate saldo_bersih
        foreach ($results as &$row) {
            $row['total_liter'] = $liter_map[$row['store_name']] ?? 0;
            $row['saldo_bersih'] = $row['total_pemasukan'] - $row['total_pengeluaran'];
        }
        
        return $results;
    }
    
    /**
     * Get data hari ini per store
     */
    private function getDailyDataPerStore() {
        // Query Cashflow Management per store untuk hari ini
        $query = "
            SELECT
                st.store_name,
                COALESCE(SUM(CASE WHEN cfm.type = 'Pemasukan' THEN cfm.amount ELSE 0 END), 0) as total_pemasukan,
                COALESCE(SUM(CASE WHEN cfm.type = 'Pengeluaran' THEN cfm.amount ELSE 0 END), 0) as total_pengeluaran
            FROM stores st
            LEFT JOIN cash_flow_management cfm ON st.id = cfm.store_id 
                AND DATE(cfm.tanggal) = CURRENT_DATE()
            GROUP BY st.id, st.store_name
            ORDER BY st.store_name
        ";
        
        $stmt = $this->pdo->query($query);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Query liter per store untuk hari ini
        $query_liter = "
            SELECT
                s.store_name,
                COALESCE(SUM(st.total_liter), 0) as total_liter
            FROM setoran st
            LEFT JOIN stores s ON st.store_id = s.id
            WHERE DATE(st.tanggal) = CURRENT_DATE()
            GROUP BY st.store_id, s.store_name
        ";
        
        $stmt_liter = $this->pdo->query($query_liter);
        $liter_data = $stmt_liter->fetchAll(PDO::FETCH_ASSOC);
        
        // Create a map of store_name => total_liter
        $liter_map = [];
        foreach ($liter_data as $liter_row) {
            $liter_map[$liter_row['store_name']] = $liter_row['total_liter'];
        }
        
        // Merge liter data and calculate saldo_bersih
        foreach ($results as &$row) {
            $row['total_liter'] = $liter_map[$row['store_name']] ?? 0;
            $row['saldo_bersih'] = $row['total_pemasukan'] - $row['total_pengeluaran'];
        }
        
        return $results;
    }
    
    /**
     * Format email body untuk laporan harian
     */
    private function formatDailyReportEmail($data) {
        $monthName = date('F Y');
        $todayDate = date('d F Y');
        
        $html = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <style>
                body { 
                    font-family: Arial, sans-serif; 
                    line-height: 1.6; 
                    color: #333;
                    background: #f5f5f5;
                }
                .container { 
                    max-width: 800px; 
                    margin: 20px auto; 
                    background: white;
                    border-radius: 10px;
                    overflow: hidden;
                    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
                }
                .header { 
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); 
                    color: white; 
                    padding: 30px; 
                    text-align: center;
                }
                .header h1 {
                    margin: 0;
                    font-size: 28px;
                    font-weight: bold;
                }
                .header p {
                    margin: 10px 0 0 0;
                    opacity: 0.9;
                    font-size: 16px;
                }
                .content { 
                    padding: 30px;
                }
                .section {
                    margin-bottom: 30px;
                }
                .section-title {
                    background: #f8f9fa;
                    padding: 15px;
                    border-left: 4px solid #667eea;
                    margin-bottom: 15px;
                    font-weight: bold;
                    font-size: 18px;
                    color: #333;
                }
                table {
                    width: 100%;
                    border-collapse: collapse;
                    margin-bottom: 20px;
                    background: white;
                }
                th {
                    background: #667eea;
                    color: white;
                    padding: 12px;
                    text-align: left;
                    font-weight: 600;
                    font-size: 14px;
                }
                td {
                    padding: 12px;
                    border-bottom: 1px solid #e5e7eb;
                    font-size: 14px;
                }
                tr:hover {
                    background: #f9fafb;
                }
                .summary-box {
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    color: white;
                    padding: 20px;
                    border-radius: 8px;
                    margin-bottom: 20px;
                }
                .summary-row {
                    display: flex;
                    justify-content: space-between;
                    padding: 8px 0;
                    border-bottom: 1px solid rgba(255,255,255,0.2);
                }
                .summary-row:last-child {
                    border-bottom: none;
                    font-weight: bold;
                    font-size: 16px;
                }
                .summary-label {
                    font-weight: 500;
                }
                .summary-value {
                    font-weight: bold;
                }
                .positive {
                    color: #10b981;
                    font-weight: bold;
                }
                .negative {
                    color: #ef4444;
                    font-weight: bold;
                }
                .footer { 
                    background: #374151; 
                    color: white; 
                    padding: 20px; 
                    text-align: center; 
                    font-size: 12px;
                }
                .footer p {
                    margin: 5px 0;
                }
                .no-data {
                    text-align: center;
                    padding: 20px;
                    color: #6b7280;
                    font-style: italic;
                }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1>📊 Laporan Harian Wallet POM MINI</h1>
                    <p>' . $todayDate . '</p>
                </div>
                
                <div class="content">
                    <!-- Section 1: Info 1 Bulan Wallet (All Store) -->
                    <div class="section">
                        <div class="section-title">📈 Informasi Wallet - Semua Store (' . $monthName . ')</div>
                        <div class="summary-box">
                            <div class="summary-row">
                                <span class="summary-label">Total Pemasukan:</span>
                                <span class="summary-value">Rp ' . number_format($data['monthly_all']['total_pemasukan'] ?? 0, 0, ',', '.') . '</span>
                            </div>
                            <div class="summary-row">
                                <span class="summary-label">Total Pengeluaran:</span>
                                <span class="summary-value">Rp ' . number_format($data['monthly_all']['total_pengeluaran'] ?? 0, 0, ',', '.') . '</span>
                            </div>
                            <div class="summary-row">
                                <span class="summary-label">Saldo Bersih:</span>
                                <span class="summary-value">Rp ' . number_format($data['monthly_all']['saldo_bersih'] ?? 0, 0, ',', '.') . '</span>
                            </div>
                            <div class="summary-row">
                                <span class="summary-label">Total Liter Terjual:</span>
                                <span class="summary-value">' . number_format($data['monthly_all']['total_liter'] ?? 0, 2, ',', '.') . ' L</span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Section 2: Info 1 Bulan Wallet (Per Store) -->
                    <div class="section">
                        <div class="section-title">🏪  Informasi Wallet - Per Store (' . $monthName . ')</div>';
        
        if (!empty($data['monthly_per_store'])) {
            $html .= '
                        <table>
                            <thead>
                                <tr>
                                    <th>Nama Store</th>
                                    <th>Total Pemasukan</th>
                                    <th>Total Pengeluaran</th>
                                    <th>Saldo Bersih</th>
                                    <th>Total Liter</th>
                                </tr>
                            </thead>
                            <tbody>';
            
            foreach ($data['monthly_per_store'] as $row) {
                $saldoClass = $row['saldo_bersih'] >= 0 ? 'positive' : 'negative';
                $html .= '
                                <tr>
                                    <td><strong>' . htmlspecialchars($row['store_name'] ?? 'Unknown') . '</strong></td>
                                    <td>Rp ' . number_format($row['total_pemasukan'] ?? 0, 0, ',', '.') . '</td>
                                    <td>Rp ' . number_format($row['total_pengeluaran'] ?? 0, 0, ',', '.') . '</td>
                                    <td class="' . $saldoClass . '">Rp ' . number_format($row['saldo_bersih'] ?? 0, 0, ',', '.') . '</td>
                                    <td>' . number_format($row['total_liter'] ?? 0, 2, ',', '.') . ' L</td>
                                </tr>';
            }
            
            $html .= '
                            </tbody>
                        </table>';
        } else {
            $html .= '<div class="no-data">Tidak ada data untuk bulan ini</div>';
        }
        
        $html .= '
                    </div>
                    
                    <!-- Section 3: Info 1 Hari Wallet (Per Store) -->
                    <div class="section">
                        <div class="section-title">📅 Laporan Hari Ini  (' . $todayDate . ')</div>';
        
        if (!empty($data['daily_per_store'])) {
            $html .= '
                        <table>
                            <thead>
                                <tr>
                                    <th>Nama Store</th>
                                    <th>Total Pemasukan</th>
                                    <th>Total Pengeluaran</th>
                                    <th>Saldo Bersih</th>
                                    <th>Total Liter</th>
                                </tr>
                            </thead>
                            <tbody>';
            
            foreach ($data['daily_per_store'] as $row) {
                $saldoClass = $row['saldo_bersih'] >= 0 ? 'positive' : 'negative';
                $html .= '
                                <tr>
                                    <td><strong>' . htmlspecialchars($row['store_name'] ?? 'Unknown') . '</strong></td>
                                    <td>Rp ' . number_format($row['total_pemasukan'] ?? 0, 0, ',', '.') . '</td>
                                    <td>Rp ' . number_format($row['total_pengeluaran'] ?? 0, 0, ',', '.') . '</td>
                                    <td class="' . $saldoClass . '">Rp ' . number_format($row['saldo_bersih'] ?? 0, 0, ',', '.') . '</td>
                                    <td>' . number_format($row['total_liter'] ?? 0, 2, ',', '.') . ' L</td>
                                </tr>';
            }
            
            $html .= '
                            </tbody>
                        </table>';
        } else {
            $html .= '<div class="no-data">Tidak ada data untuk hari ini</div>';
        }
        
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
$host = $_SERVER['HTTP_HOST'];
$path = dirname($_SERVER['SCRIPT_NAME']);

// Hilangkan trailing slash jika ada
$path = rtrim($path, '/');

define('BASE_URL', $protocol . '://' . $host . $path);
        $html .= '
                    </div>
                </div>
                
                <div class="footer">
                    <h2>Info Selengkapnya <a href="' . BASE_URL . '/admin"">klik disini</a></h2>
                    <p><strong>POM MINI ©2025</strong></p>
                    <p>Laporan otomatis dikirim setiap hari jam 12:00 WIB</p>
                    <p style="opacity: 0.7;">Jangan balas email ini - Email otomatis sistem</p>
                </div>
            </div>
        </body>
        </html>
        ';
        
        return $html;
    }
    
    /**
     * Update last_daily_report_sent timestamp
     */
    private function updateLastDailyReportSent() {
        try {
            $stmt = $this->pdo->prepare("UPDATE email_config SET last_daily_report_sent = NOW() WHERE id = 1");
            $stmt->execute();
            
            // Reload config
            $this->loadConfig();
        } catch (Exception $e) {
            // Silent fail
        }
    }
}
?>