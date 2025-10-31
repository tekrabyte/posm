<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start(); 
require_once 'config.php';
require_once 'security.php';
header('Content-Type: application/json');

// Check session timeout for authenticated endpoints
if (isset($_SESSION['user_id'])) {
    if (!checkSessionTimeout()) {
        jsonResponse(false, 'Sesi Anda telah berakhir. Silakan login kembali.', [], [], 401);
    }
}

// Fungsi pembantu untuk mengeluarkan respons JSON
function jsonResponse($success, $message, $data = [], $summary = [], $httpCode = 200) {
    http_response_code($httpCode);
    
    $response = ['success' => $success, 'message' => $message];

    if ($httpCode >= 400 && $data === [] && $summary === []) {
        $response['error_details'] = $message; 
        unset($response['message']);
    } else {
        $response['data'] = $data;
        $response['summary'] = $summary;
    }
    
    if (!empty($summary) && $data === []) {
        unset($response['data']);
    } else if (empty($summary) && empty($data)) {
        unset($response['data'], $response['summary']);
    }
    
    echo json_encode($response);
    exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

// Logika untuk mengambil data JSON POST body
$data = [];
$requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';

if ($requestMethod === 'POST' && strpos($contentType, 'application/json') !== false) {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (empty($action) && is_array($data) && isset($data['employee_id'])) {
        $action = 'save_setoran';
    }
}

// CSRF Protection for POST requests (except login, get_csrf_token, save_setoran, and send_daily_report)
// save_setoran is public endpoint for frontend form submission  
// send_daily_report is admin-authenticated action, can bypass CSRF if user is logged in
if ($requestMethod === 'POST' && $action !== 'login' && $action !== 'get_csrf_token' && $action !== 'save_setoran') {
    // Skip CSRF for send_daily_report if user is authenticated
    if ($action === 'send_daily_report' && isset($_SESSION['user_id'])) {
        // Authenticated admin action, allow without CSRF token
    } else {
        $csrf_token = $data['csrf_token'] ?? $_POST['csrf_token'] ?? '';
        
        if (!validateCSRFToken($csrf_token)) {
            jsonResponse(false, 'Token keamanan tidak valid. Silakan refresh halaman dan coba lagi.', [], [], 403);
        }
    }
}

// Generate CSRF token for session
if ($action === 'get_csrf_token') {
    $token = generateCSRFToken();
    jsonResponse(true, 'Token berhasil diambil', ['csrf_token' => $token]);
}

// AUTH: Logout
if ($action === 'logout') {
    session_unset();
    session_destroy();
    jsonResponse(true, 'Logout berhasil');
}

switch ($action) {
    // =========================================================
    // LOGIN ADMIN
    // =========================================================
    case 'login':
        try {
            if (empty($data['username']) || empty($data['password'])) {
                throw new Exception("Username dan password harus diisi.");
            }

            $username = $data['username'];
            $password = $data['password'];

            $sql = "SELECT id, username, password FROM users WHERE username = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                if ($password === $user['password']) {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['LAST_ACTIVITY'] = time();
                    
                    // Generate CSRF token for this session
                    $csrf_token = generateCSRFToken();
                    
                    jsonResponse(true, 'Login berhasil.', ['csrf_token' => $csrf_token]);
                } else {
                    jsonResponse(false, 'Username atau password salah.', [], [], 401);
                }
            } else {
                jsonResponse(false, 'Username atau password salah.', [], [], 401);
            }

        } catch (Exception $e) {
            jsonResponse(false, 'Login gagal. ' . $e->getMessage(), [], [], 500);
        }
        break;

    // =========================================================
    // HAPUS SETORAN
    // =========================================================
    case 'delete_setoran':
        try {
            if (!isset($data['id'])) {
                throw new Exception("ID setoran tidak ditemukan.");
            }
            
            $pdo->beginTransaction();
            
            $stmt1 = $pdo->prepare("DELETE FROM pengeluaran WHERE setoran_id = ?");
            $stmt1->execute([$data['id']]);
            
            $stmt2 = $pdo->prepare("DELETE FROM pemasukan WHERE setoran_id = ?");
            $stmt2->execute([$data['id']]);
            
            // Delete related cashflow entries (QRIS, CASH, Pengeluaran, Pemasukan)
            $stmt_cf_qris = $pdo->prepare("DELETE FROM cash_flow_management WHERE category = 'qris_setoran' AND notes LIKE ?");
            $stmt_cf_qris->execute(["%SETORAN_ID:{$data['id']}%"]);
            
            $stmt_cf_cash = $pdo->prepare("DELETE FROM cash_flow_management WHERE category = 'cash_setoran' AND notes LIKE ?");
            $stmt_cf_cash->execute(["%SETORAN_ID:{$data['id']}%"]);
            
            $stmt_cf_pengeluaran = $pdo->prepare("DELETE FROM cash_flow_management WHERE category = 'pengeluaran_setoran' AND notes LIKE ?");
            $stmt_cf_pengeluaran->execute(["%SETORAN_ID:{$data['id']}%"]);
            
            $stmt_cf_pemasukan = $pdo->prepare("DELETE FROM cash_flow_management WHERE category = 'pemasukan_setoran' AND notes LIKE ?");
            $stmt_cf_pemasukan->execute(["%SETORAN_ID:{$data['id']}%"]);
            
            $stmt3 = $pdo->prepare("DELETE FROM setoran WHERE id = ?");
            $stmt3->execute([$data['id']]);
            
            $pdo->commit();
            jsonResponse(true, 'Setoran berhasil dihapus');
            
        } catch (Exception $e) {
            $pdo->rollBack();
            jsonResponse(false, 'Gagal menghapus setoran: ' . $e->getMessage(), [], [], 500);
        }
        break;

    // =========================================================
    // DETAIL SETORAN UNTUK EDIT
    // =========================================================
    case 'get_setoran_detail_to_edit':
        try {
            $setoran_id = $_GET['id'] ?? 0;
            if (!$setoran_id) throw new Exception("ID Setoran tidak ditemukan.");

            $stmt = $pdo->prepare("
                SELECT s.*, e.employee_name, st.store_name 
                FROM setoran s
                LEFT JOIN employees e ON s.employee_id = e.id
                LEFT JOIN stores st ON s.store_id = st.id
                WHERE s.id = ?
            ");
            $stmt->execute([$setoran_id]);
            $setoran = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$setoran) throw new Exception("Data Setoran tidak ditemukan.");

            $stmt_pengeluaran = $pdo->prepare("SELECT description, amount FROM pengeluaran WHERE setoran_id = ?");
            $stmt_pengeluaran->execute([$setoran_id]);
            $pengeluaran = $stmt_pengeluaran->fetchAll(PDO::FETCH_ASSOC);

            $stmt_pemasukan = $pdo->prepare("SELECT description, amount FROM pemasukan WHERE setoran_id = ?");
            $stmt_pemasukan->execute([$setoran_id]);
            $pemasukan = $stmt_pemasukan->fetchAll(PDO::FETCH_ASSOC);

            $result = [
                'setoran' => $setoran,
                'pengeluaran' => $pengeluaran,
                'pemasukan' => $pemasukan
            ];

            jsonResponse(true, 'Detail setoran berhasil dimuat', $result);
        } catch (Exception $e) {
            jsonResponse(false, 'Error: Gagal memuat detail setoran. ' . $e->getMessage(), [], [], 404);
        }
        break;

    // =========================================================
    // SAVE/UPDATE SETORAN
    // =========================================================
    case 'save_setoran':
        try {
            if (empty($data)) throw new Exception("Data setoran kosong atau format tidak valid.");

            $required_fields = [
                'employee_id', 'store_id', 'jam_masuk', 'jam_keluar', 'nomor_awal', 'nomor_akhir',
                'total_liter', 'qris', 'cash', 'total_pengeluaran', 'total_pemasukan', 'total_keseluruhan'
            ];

            foreach ($required_fields as $field) {
                if (!isset($data[$field])) throw new Exception("Field wajib '{$field}' hilang.");
            }

            // jumlah_tera is optional, default to 0
            $jumlah_tera = isset($data['jumlah_tera']) ? (float)$data['jumlah_tera'] : 0;

            // Start transaction untuk data consistency
            $pdo->beginTransaction();

            $today = date('Y-m-d');
            $total_setoran_calculated = (float)$data['qris'] + (float)$data['cash'];
            $qris_amount = (float)$data['qris'];

            $stmtEmp = $pdo->prepare("SELECT employee_name FROM employees WHERE id = ?");
            $stmtEmp->execute([$data['employee_id']]);
            $employee_name = $stmtEmp->fetchColumn() ?: 'Tidak Diketahui';

            $stmtStore = $pdo->prepare("SELECT store_name FROM stores WHERE id = ?");
            $stmtStore->execute([$data['store_id']]);
            $store_name = $stmtStore->fetchColumn() ?: 'Tidak Diketahui';

            $stmtCheck = $pdo->prepare("SELECT id FROM setoran WHERE tanggal = ? AND store_id = ? AND employee_id = ?");
            $stmtCheck->execute([$today, $data['store_id'], $data['employee_id']]);
            $existingSetoranId = $stmtCheck->fetchColumn();

            if ($existingSetoranId) {
                // Get old QRIS value before update
                $stmtOldQris = $pdo->prepare("SELECT qris FROM setoran WHERE id = ?");
                $stmtOldQris->execute([$existingSetoranId]);
                $old_qris = (float)$stmtOldQris->fetchColumn();

                $stmtUpdate = $pdo->prepare("
                    UPDATE setoran SET 
                        jam_masuk = ?, jam_keluar = ?, nomor_awal = ?, nomor_akhir = ?, 
                        jumlah_tera = ?, total_liter = ?, qris = ?, cash = ?, total_setoran = ?, 
                        total_pengeluaran = ?, total_pemasukan = ?, total_keseluruhan = ?, 
                        employee_name = ?, store_name = ?
                    WHERE id = ?
                ");
                $stmtUpdate->execute([
                    $data['jam_masuk'], $data['jam_keluar'], $data['nomor_awal'], $data['nomor_akhir'],
                    $jumlah_tera, $data['total_liter'], $data['qris'], $data['cash'], $total_setoran_calculated,
                    $data['total_pengeluaran'], $data['total_pemasukan'], $data['total_keseluruhan'],
                    $employee_name, $store_name, $existingSetoranId
                ]);

                $setoran_id = $existingSetoranId;
                $pdo->exec("DELETE FROM pengeluaran WHERE setoran_id = $setoran_id");
                $pdo->exec("DELETE FROM pemasukan WHERE setoran_id = $setoran_id");
                
                // Delete old cashflow entries (QRIS, CASH, Pengeluaran, Pemasukan)
                $pdo->prepare("DELETE FROM cash_flow_management WHERE category = 'qris_setoran' AND notes LIKE ?")->execute(["%SETORAN_ID:$setoran_id%"]);
                $pdo->prepare("DELETE FROM cash_flow_management WHERE category = 'cash_setoran' AND notes LIKE ?")->execute(["%SETORAN_ID:$setoran_id%"]);
                $pdo->prepare("DELETE FROM cash_flow_management WHERE category = 'pengeluaran_setoran' AND notes LIKE ?")->execute(["%SETORAN_ID:$setoran_id%"]);
                $pdo->prepare("DELETE FROM cash_flow_management WHERE category = 'pemasukan_setoran' AND notes LIKE ?")->execute(["%SETORAN_ID:$setoran_id%"]);
                
                $message = 'Data setoran berhasil diperbarui (ditimpa)';
            } else {
                $stmtInsert = $pdo->prepare("
                    INSERT INTO setoran (
                        tanggal, employee_id, employee_name, store_id, store_name, 
                        jam_masuk, jam_keluar, nomor_awal, nomor_akhir, jumlah_tera,
                        total_liter, qris, cash, total_setoran,
                        total_pengeluaran, total_pemasukan, total_keseluruhan
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmtInsert->execute([
                    $today,
                    $data['employee_id'],
                    $employee_name,
                    $data['store_id'],
                    $store_name,
                    $data['jam_masuk'],
                    $data['jam_keluar'],
                    $data['nomor_awal'],
                    $data['nomor_akhir'],
                    $jumlah_tera,
                    $data['total_liter'],
                    $data['qris'],
                    $data['cash'],
                    $total_setoran_calculated,
                    $data['total_pengeluaran'],
                    $data['total_pemasukan'],
                    $data['total_keseluruhan']
                ]);
                $setoran_id = $pdo->lastInsertId();
                $message = 'Data setoran berhasil disimpan';
            }

            // Insert pengeluaran details
            if (!empty($data['pengeluaran'])) {
                $stmtPeng = $pdo->prepare("INSERT INTO pengeluaran (setoran_id, description, amount) VALUES (?, ?, ?)");
                foreach ($data['pengeluaran'] as $item) {
                    $stmtPeng->execute([$setoran_id, $item['description'], $item['amount']]);
                }
            }

            // Insert pemasukan details
            if (!empty($data['pemasukan'])) {
                $stmtMasuk = $pdo->prepare("INSERT INTO pemasukan (setoran_id, description, amount) VALUES (?, ?, ?)");
                foreach ($data['pemasukan'] as $item) {
                    $stmtMasuk->execute([$setoran_id, $item['description'], $item['amount']]);
                }
            }

            // ========================================
            // AUTO-SYNC QRIS TO CASHFLOW MANAGEMENT
            // ========================================
            if ($qris_amount > 0) {
                $qris_description = "Pemasukan QRIS Setoran - {$employee_name} ({$store_name})";
                $qris_notes = "AUTO_SYNC:SETORAN_ID:{$setoran_id}:EMPLOYEE:{$employee_name}:DATE:{$today}";
                
                $stmtCashflow = $pdo->prepare("
                    INSERT INTO cash_flow_management 
                    (tanggal, store_id, description, amount, type, category, notes, created_at)
                    VALUES (?, ?, ?, ?, 'Pemasukan', 'qris_setoran', ?, NOW())
                ");
                $stmtCashflow->execute([
                    $today,
                    $data['store_id'],
                    $qris_description,
                    $qris_amount,
                    $qris_notes
                ]);
                
                $message .= ' | QRIS otomatis masuk ke Cashflow Management';
            }

            // ========================================
            // AUTO-SYNC CASH TO CASHFLOW MANAGEMENT
            // Pembulatan ke ribuan terdekat: ROUND(cash/1000,0)*1000
            // ========================================
            $cash_amount = (float)$data['cash'];
            if ($cash_amount > 0) {
                // Pembulatan ke ribuan terdekat
                $cash_rounded = round($cash_amount / 1000, 0) * 1000;
                
                $cash_description = "Pemasukan Cash Setoran - {$employee_name} ({$store_name})";
                $cash_notes = "AUTO_SYNC:SETORAN_ID:{$setoran_id}:EMPLOYEE:{$employee_name}:DATE:{$today}";
                
                $stmtCashflow = $pdo->prepare("
                    INSERT INTO cash_flow_management 
                    (tanggal, store_id, description, amount, type, category, notes, created_at)
                    VALUES (?, ?, ?, ?, 'Pemasukan', 'cash_setoran', ?, NOW())
                ");
                $stmtCashflow->execute([
                    $today,
                    $data['store_id'],
                    $cash_description,
                    $cash_rounded,
                    $cash_notes
                ]);
                
                $message .= ' | Cash otomatis masuk ke Cashflow Management';
            }

            // ========================================
            // AUTO-SYNC PENGELUARAN TO CASHFLOW MANAGEMENT
            // ========================================
            if (!empty($data['pengeluaran'])) {
                $stmtCashflowPengeluaran = $pdo->prepare("
                    INSERT INTO cash_flow_management 
                    (tanggal, store_id, description, amount, type, category, notes, created_at)
                    VALUES (?, ?, ?, ?, 'Pengeluaran', 'pengeluaran_setoran', ?, NOW())
                ");
                
                foreach ($data['pengeluaran'] as $item) {
                    $pengeluaran_description = "Pengeluaran Setoran: {$item['description']} - {$employee_name} ({$store_name})";
                    $pengeluaran_notes = "AUTO_SYNC:SETORAN_ID:{$setoran_id}:EMPLOYEE:{$employee_name}:DATE:{$today}";
                    
                    $stmtCashflowPengeluaran->execute([
                        $today,
                        $data['store_id'],
                        $pengeluaran_description,
                        $item['amount'],
                        $pengeluaran_notes
                    ]);
                }
                
                $message .= ' | Pengeluaran otomatis masuk ke Cashflow Management';
            }

            // ========================================
            // AUTO-SYNC PEMASUKAN TO CASHFLOW MANAGEMENT
            // ========================================
            if (!empty($data['pemasukan'])) {
                $stmtCashflowPemasukan = $pdo->prepare("
                    INSERT INTO cash_flow_management 
                    (tanggal, store_id, description, amount, type, category, notes, created_at)
                    VALUES (?, ?, ?, ?, 'Pemasukan', 'pemasukan_setoran', ?, NOW())
                ");
                
                foreach ($data['pemasukan'] as $item) {
                    $pemasukan_description = "Pemasukan Setoran: {$item['description']} - {$employee_name} ({$store_name})";
                    $pemasukan_notes = "AUTO_SYNC:SETORAN_ID:{$setoran_id}:EMPLOYEE:{$employee_name}:DATE:{$today}";
                    
                    $stmtCashflowPemasukan->execute([
                        $today,
                        $data['store_id'],
                        $pemasukan_description,
                        $item['amount'],
                        $pemasukan_notes
                    ]);
                }
                
                $message .= ' | Pemasukan otomatis masuk ke Cashflow Management';
            }

            // ========================================
            // TRACK CHANGE (Inside Transaction)
            // ========================================
            // Track change untuk real-time polling
            $stmtTrack = $pdo->prepare("
                INSERT INTO data_changes (table_name, record_id, change_type)
                VALUES ('setoran', ?, ?)
            ");
            $stmtTrack->execute([$setoran_id, $existingSetoranId ? 'update' : 'insert']);
            
            // Commit transaction - MUST be done before email
            $pdo->commit();

            // ========================================
            // SEND EMAIL NOTIFICATION (After Commit)
            // ========================================
            try {
                // Load helper functions
                if (!function_exists('formatIndonesianDate')) {
                    require_once __DIR__ . '/helper_functions.php';
                }
                require_once __DIR__ . '/email_handler.php';
                $emailHandler = new EmailHandler($pdo);
                
                // Format email message - WhatsApp style
                $emailSubject = '🆕 Setoran Baru - ' . $store_name;
                
                // Format tanggal Indonesia
                $tanggalIndonesia = formatIndonesianDate($today);
                
                // Build email message text
                $emailText = "*Setoran Harian* 📋\n";
                $emailText .= $tanggalIndonesia . "\n";
                $emailText .= "🤦‍♀️ Nama: " . htmlspecialchars($employee_name) . "\n";
                $emailText .= "🕐 Jam: (" . $data['jam_masuk'] . " - " . $data['jam_keluar'] . ")\n\n";
                
                $emailText .= "⛽ Data Meter\n";
                $emailText .= "• Nomor Awal : " . number_format($data['nomor_awal'], 2, ',', '.') . "\n";
                $emailText .= "• Nomor Akhir: " . number_format($data['nomor_akhir'], 2, ',', '.') . "\n";
                $emailText .= "• Total Liter: " . number_format($data['total_liter'], 2, ',', '.') . " L\n\n";
                
                $emailText .= "💰 Setoran\n";
                $emailText .= "• Cash  : Rp " . formatRupiah($data['cash']) . "\n";
                $emailText .= "• QRIS  : Rp " . formatRupiah($data['qris']) . "\n";
                $emailText .= "• Total : Rp " . formatRupiah($total_setoran_calculated) . "\n\n";
                
                // Pengeluaran
                if (!empty($data['pengeluaran'])) {
                    $emailText .= "💸 Pengeluaran (PU)\n";
                    foreach ($data['pengeluaran'] as $item) {
                        $emailText .= "• " . htmlspecialchars($item['description']) . ": Rp " . formatRupiah($item['amount']) . "\n";
                    }
                    $emailText .= "Total Pengeluaran: Rp " . formatRupiah($data['total_pengeluaran']) . "\n\n";
                }
                
                // Pemasukan
                if (!empty($data['pemasukan'])) {
                    $emailText .= "💵 Pemasukan (PU)\n";
                    foreach ($data['pemasukan'] as $item) {
                        $emailText .= "• " . htmlspecialchars($item['description']) . ": Rp " . formatRupiah($item['amount']) . "\n";
                    }
                    $emailText .= "Total Pemasukan: Rp " . formatRupiah($data['total_pemasukan']) . "\n\n";
                }
                
                $emailText .= "💼 Total Keseluruhan: Rp " . formatRupiah($data['total_keseluruhan']);
                
                // Convert to HTML with proper formatting
                $emailMessage = '
<div style="font-family: \'Courier New\', monospace; max-width: 600px; margin: 0 auto; background: #ffffff; padding: 20px;">
    <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; border-radius: 8px; margin-bottom: 20px;">
        <h2 style="margin: 0; font-size: 20px;">🆕 Setoran Baru - ' . htmlspecialchars($store_name) . '</h2>
    </div>
    
    <div style="background: #f9fafb; padding: 20px; border-radius: 8px; border: 1px solid #e5e7eb;">
        <pre style="font-family: \'Courier New\', monospace; font-size: 14px; line-height: 1.6; margin: 0; white-space: pre-wrap; word-wrap: break-word; color: #1f2937;">' . $emailText . '</pre>
    </div>
    
    <div style="margin-top: 15px; padding: 12px; background: #eff6ff; border-radius: 6px; text-align: center;">
        <p style="margin: 0; font-size: 12px; color: #1e40af;">✅ Data otomatis masuk ke Cashflow Management</p>
    </div>
</div>
';
                
                // Send email (async, don't block if fails)
                $emailHandler->sendNotification($emailSubject, $emailMessage, 'setoran', $setoran_id);
                
            } catch (Exception $emailError) {
                // Silent fail - email error shouldn't stop the main process
                // Log to error log
                error_log('Email notification failed: ' . $emailError->getMessage());
                error_log('Stack trace: ' . $emailError->getTraceAsString());
            }

            jsonResponse(true, $message, ['id' => $setoran_id, 'qris_synced' => ($qris_amount > 0)]);
        } catch (Exception $e) {
            // Only rollback if transaction is active
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            jsonResponse(false, 'Error: Gagal menyimpan data setoran. ' . $e->getMessage(), [], [], 500);
        }
        break;

    // =========================================================
    // GET HISTORY SETORAN
    // =========================================================
    case 'get_history':
        try {
            $month = $_GET['month'] ?? date('m');
            $year = $_GET['year'] ?? date('Y');
            $employee_id_filter = $_GET['employee_id'] ?? '';
            $store_id_filter = $_GET['store_id'] ?? '';

            $where_clause = "YEAR(s.tanggal) = ? AND MONTH(s.tanggal) = ?";
            $params = [$year, $month];

            if (!empty($employee_id_filter)) {
                $where_clause .= " AND s.employee_id = ?";
                $params[] = $employee_id_filter;
            }
            if (!empty($store_id_filter)) {
                $where_clause .= " AND s.store_id = ?";
                $params[] = $store_id_filter;
            }

            $sql_history = "
                SELECT s.*, e.employee_name, st.store_name
                FROM setoran s
                LEFT JOIN employees e ON s.employee_id = e.id
                LEFT JOIN stores st ON s.store_id = st.id
                WHERE {$where_clause}
                ORDER BY s.tanggal DESC, s.jam_masuk DESC
            ";
            $stmt = $pdo->prepare($sql_history);
            $stmt->execute($params);
            $history = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $sql_summary = "
                SELECT
                    COUNT(id) AS count_history,
                    SUM(total_liter) AS total_liter,
                    SUM(cash) AS total_cash,
                    SUM(qris) AS total_qris,
                    SUM(total_setoran) AS total_setoran,
                    SUM(total_pengeluaran) AS total_pengeluaran,
                    SUM(total_pemasukan) AS total_pemasukan,
                    SUM(total_keseluruhan) AS total_keseluruhan
                FROM setoran s
                WHERE {$where_clause}
            ";
            $stmtSum = $pdo->prepare($sql_summary);
            $stmtSum->execute($params);
            $summary = $stmtSum->fetch(PDO::FETCH_ASSOC);

            jsonResponse(true, 'Data history berhasil dimuat', ['setoran' => $history], $summary);
        } catch (Exception $e) {
            jsonResponse(false, 'Error: Gagal memuat history. ' . $e->getMessage(), [], [], 500);
        }
        break;

    // =========================================================
    // DETAIL SETORAN (READ ONLY)
    // =========================================================
    case 'get_setoran_detail':
        try {
            $setoran_id = $_GET['id'] ?? 0;
            if (!$setoran_id) throw new Exception("ID Setoran tidak ditemukan.");

            $stmt = $pdo->prepare("
                SELECT s.*, e.employee_name, st.store_name
                FROM setoran s
                LEFT JOIN employees e ON s.employee_id = e.id
                LEFT JOIN stores st ON s.store_id = st.id
                WHERE s.id = ?
            ");
            $stmt->execute([$setoran_id]);
            $setoran = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$setoran) throw new Exception("Data Setoran tidak ditemukan.");

            $stmt_pengeluaran = $pdo->prepare("SELECT description, amount FROM pengeluaran WHERE setoran_id = ?");
            $stmt_pengeluaran->execute([$setoran_id]);
            $pengeluaran = $stmt_pengeluaran->fetchAll(PDO::FETCH_ASSOC);

            $stmt_pemasukan = $pdo->prepare("SELECT description, amount FROM pemasukan WHERE setoran_id = ?");
            $stmt_pemasukan->execute([$setoran_id]);
            $pemasukan = $stmt_pemasukan->fetchAll(PDO::FETCH_ASSOC);

            $result = [
                'setoran' => [$setoran],
                'pengeluaran' => $pengeluaran,
                'pemasukan' => $pemasukan
            ];

            jsonResponse(true, 'Detail setoran berhasil dimuat', $result);
        } catch (Exception $e) {
            jsonResponse(false, 'Error: Gagal memuat detail setoran. ' . $e->getMessage(), [], [], 404);
        }
        break;

    // =========================================================
    // STORE MANAGEMENT
    // =========================================================
    case 'get_stores':
        try {
            $stmt = $pdo->query("SELECT id, store_name, address FROM stores ORDER BY store_name ASC");
            jsonResponse(true, 'Daftar Store berhasil dimuat', $stmt->fetchAll(PDO::FETCH_ASSOC));
        } catch (Exception $e) {
            jsonResponse(false, 'Error: ' . $e->getMessage(), [], [], 500);
        }
        break;

    case 'add_store':
        try {
            $stmt = $pdo->prepare("INSERT INTO stores (store_name, address) VALUES (?, ?)");
            $stmt->execute([$data['store_name'], $data['address'] ?? null]);
            jsonResponse(true, 'Store baru berhasil ditambahkan');
        } catch (Exception $e) {
            jsonResponse(false, 'Gagal menambahkan Store: ' . $e->getMessage(), [], [], 500);
        }
        break;

    case 'edit_store':
        try {
            $stmt = $pdo->prepare("UPDATE stores SET store_name = ?, address = ? WHERE id = ?");
            $stmt->execute([$data['store_name'], $data['address'] ?? null, $data['id']]);
            jsonResponse(true, 'Store berhasil diperbarui');
        } catch (Exception $e) {
            jsonResponse(false, 'Gagal memperbarui Store: ' . $e->getMessage(), [], [], 500);
        }
        break;

    case 'delete_store':
        try {
            $stmt = $pdo->prepare("DELETE FROM stores WHERE id = ?");
            $stmt->execute([$data['id']]);
            jsonResponse(true, 'Store berhasil dihapus');
        } catch (Exception $e) {
            jsonResponse(false, 'Gagal menghapus Store: ' . $e->getMessage(), [], [], 500);
        }
        break;

    // =========================================================
    // EMPLOYEE MANAGEMENT
    // =========================================================
    case 'get_employees':
        try {
            $store_id_filter = $_GET['store_id'] ?? '';
            
            $sql = "
                SELECT e.*, s.store_name 
                FROM employees e
                LEFT JOIN stores s ON e.store_id = s.id
            ";
            
            $params = [];
            $where_conditions = [];

            if (!empty($store_id_filter)) {
                $where_conditions[] = "e.store_id = ?";
                $params[] = $store_id_filter;
            }

            $where_conditions[] = "e.is_active = 1";

            if (!empty($where_conditions)) {
                $sql .= " WHERE " . implode(" AND ", $where_conditions);
            }
            
            $sql .= " ORDER BY e.employee_name ASC";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            
            jsonResponse(true, 'Daftar Karyawan berhasil dimuat', $stmt->fetchAll(PDO::FETCH_ASSOC));
        } catch (Exception $e) {
            jsonResponse(false, 'Error: ' . $e->getMessage(), [], [], 500);
        }
        break;

    case 'add_employee':
        try {
            if (empty($data['store_id'])) throw new Exception("Store harus dipilih.");
            $stmt = $pdo->prepare("INSERT INTO employees (employee_name, employee_code, store_id, is_active) VALUES (?, ?, ?, 1)");
            $stmt->execute([$data['employee_name'], $data['employee_code'] ?? null, $data['store_id']]);
            jsonResponse(true, 'Karyawan baru berhasil ditambahkan');
        } catch (Exception $e) {
            jsonResponse(false, 'Gagal menambahkan Karyawan: ' . $e->getMessage(), [], [], 500);
        }
        break;

    case 'edit_employee':
        try {
            if (empty($data['store_id'])) throw new Exception("Store harus dipilih.");
            $stmt = $pdo->prepare("UPDATE employees SET employee_name = ?, employee_code = ?, store_id = ?, is_active = ? WHERE id = ?");
            $stmt->execute([$data['employee_name'], $data['employee_code'] ?? null, $data['store_id'], $data['is_active'], $data['id']]);
            jsonResponse(true, 'Karyawan berhasil diperbarui');
        } catch (Exception $e) {
            jsonResponse(false, 'Gagal memperbarui Karyawan: ' . $e->getMessage(), [], [], 500);
        }
        break;
// =========================================================
// GET CASHFLOW SUMMARY FOR DASHBOARD
// =========================================================
case 'get_cashflow_summary':
    try {
        $month = $_GET['month'] ?? date('m');
        $year = $_GET['year'] ?? date('Y');
        $store_id_filter = $_GET['store_id'] ?? '';

        // Validasi input
        if (!is_numeric($month) || !is_numeric($year)) {
            throw new Exception("Bulan dan tahun harus berupa angka");
        }

        $where_clause = "YEAR(cfm.tanggal) = ? AND MONTH(cfm.tanggal) = ?";
        $params = [$year, $month];
        
        if (!empty($store_id_filter)) {
            $where_clause .= " AND cfm.store_id = ?";
            $params[] = $store_id_filter;
        }

        // Query untuk summary per store
        $sql_summary = "
            SELECT 
                s.store_name,
                COALESCE(SUM(CASE WHEN cfm.type = 'Pemasukan' THEN cfm.amount ELSE 0 END), 0) as total_pemasukan,
                COALESCE(SUM(CASE WHEN cfm.type = 'Pengeluaran' THEN cfm.amount ELSE 0 END), 0) as total_pengeluaran,
                COALESCE(SUM(CASE WHEN cfm.type = 'Pemasukan' THEN cfm.amount ELSE -cfm.amount END), 0) as saldo_bersih
            FROM stores s
            LEFT JOIN cash_flow_management cfm ON s.id = cfm.store_id AND {$where_clause}
            GROUP BY s.id, s.store_name
            HAVING total_pemasukan > 0 OR total_pengeluaran > 0
            ORDER BY s.store_name
        ";

        $stmt_summary = $pdo->prepare($sql_summary);
        $stmt_summary->execute($params);
        $summary_data = $stmt_summary->fetchAll(PDO::FETCH_ASSOC);

        // Hitung total keseluruhan
        $sql_total = "
            SELECT 
                COALESCE(SUM(CASE WHEN type = 'Pemasukan' THEN amount ELSE 0 END), 0) as total_pemasukan,
                COALESCE(SUM(CASE WHEN type = 'Pengeluaran' THEN amount ELSE 0 END), 0) as total_pengeluaran
            FROM cash_flow_management
            WHERE {$where_clause}
        ";
        $stmt_total = $pdo->prepare($sql_total);
        $stmt_total->execute($params);
        $total_data = $stmt_total->fetch(PDO::FETCH_ASSOC);

        $result = [
            'summary' => $summary_data,
            'total' => $total_data
        ];

        jsonResponse(true, 'Data summary cashflow berhasil dimuat', $result);

    } catch (Exception $e) {
        jsonResponse(false, 'Error: ' . $e->getMessage(), [], [], 500);
    }
    break;
// =========================================================
// GET MANAGEMENT CASH FLOW - SIMPLE WORKING VERSION
// =========================================================
case 'get_management_cash_flow':
    try {
        $month = $_GET['month'] ?? date('m');
        $year = $_GET['year'] ?? date('Y');
        $store_id_filter = $_GET['store_id'] ?? '';

        // Convert to integers for safety
        $month = intval($month);
        $year = intval($year);
        
        error_log("=== CASH FLOW API CALLED ===");
        error_log("Month: $month, Year: $year, Store ID: $store_id_filter");

        // Build WHERE clause
        $where_parts = ["YEAR(tanggal) = ?", "MONTH(tanggal) = ?"];
        $params = [$year, $month];
        
        if (!empty($store_id_filter) && $store_id_filter !== '') {
            $store_id_filter = intval($store_id_filter);
            $where_parts[] = "store_id = ?";
            $params[] = $store_id_filter;
        }
        
        $where_clause = implode(" AND ", $where_parts);

        // Simple query without complex joins first
        $sql = "
            SELECT 
                id,
                tanggal,
                store_id,
                description,
                amount,
                type,
                category,
                notes,
                created_at,
                'manual' AS source
            FROM cash_flow_management 
            WHERE $where_clause
            ORDER BY tanggal DESC, id DESC
        ";

        error_log("SQL: $sql");
        error_log("Params: " . print_r($params, true));

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

        error_log("Found " . count($transactions) . " transactions");

        // Get store names
        $stores = [];
        $stmt_stores = $pdo->query("SELECT id, store_name FROM stores");
        while ($store = $stmt_stores->fetch(PDO::FETCH_ASSOC)) {
            $stores[$store['id']] = $store['store_name'];
        }

        // Add store_name to each transaction
        foreach ($transactions as &$transaction) {
            $store_id = $transaction['store_id'];
            $transaction['store_name'] = $stores[$store_id] ?? 'Unknown Store';
        }

        // Calculate summary
        $total_pemasukan = 0;
        $total_pengeluaran = 0;

        foreach ($transactions as $t) {
            $amount = (float)$t['amount'];
            if (strtolower($t['type']) === 'pemasukan') {
                $total_pemasukan += $amount;
            } else {
                $total_pengeluaran += $amount;
            }
        }

        $summary = [
            'total_pemasukan_manajemen' => $total_pemasukan,
            'total_pengeluaran_manajemen' => $total_pengeluaran,
            'saldo_bersih' => $total_pemasukan - $total_pengeluaran
        ];

        error_log("Summary - Income: $total_pemasukan, Expense: $total_pengeluaran");

        // Return success response
        $response = [
            'success' => true,
            'message' => 'Data berhasil dimuat',
            'data' => $transactions,
            'summary' => $summary
        ];

        error_log("Sending response: " . json_encode($response));
        
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;

    } catch (Exception $e) {
        error_log("!!! CASH FLOW ERROR: " . $e->getMessage());
        error_log("!!! Stack trace: " . $e->getTraceAsString());
        
        $response = [
            'success' => false,
            'message' => 'Error: ' . $e->getMessage(),
            'data' => [],
            'summary' => []
        ];
        
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }
    break;
// =========================================================
// ADD MANAGEMENT CASH FLOW - FIXED VERSION
// =========================================================
case 'add_management_cash_flow':
    try {
        // Handle JSON input
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        
        error_log("Data received: " . print_r($data, true));

        if (empty($data)) {
            throw new Exception("Data transaksi kosong.");
        }

        // Validasi field wajib
        $required_fields = ['tanggal', 'store_id', 'type', 'category', 'description', 'amount'];
        foreach ($required_fields as $field) {
            if (!isset($data[$field]) || $data[$field] === '') {
                throw new Exception("Field wajib '{$field}' hilang atau kosong.");
            }
        }

        // Validasi numeric amount
        if (!is_numeric($data['amount']) || $data['amount'] <= 0) {
            throw new Exception("Nominal harus berupa angka positif.");
        }

        // Insert data
        $sql = "INSERT INTO cash_flow_management 
                (tanggal, store_id, description, amount, type, category, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, NOW())";
        
        $stmt = $pdo->prepare($sql);
        $success = $stmt->execute([
            $data['tanggal'],
            $data['store_id'],
            $data['description'],
            $data['amount'],
            $data['type'],
            $data['category']
        ]);

        if ($success) {
            $id = $pdo->lastInsertId();
            error_log("Data berhasil disimpan dengan ID: " . $id);
            
            // ========================================
            // TRACK CHANGE & SEND EMAIL NOTIFICATION
            // ========================================
            try {
                // Track change untuk real-time polling
                $stmtTrack = $pdo->prepare("
                    INSERT INTO data_changes (table_name, record_id, change_type)
                    VALUES ('cash_flow_management', ?, 'insert')
                ");
                $stmtTrack->execute([$id]);
                
                // Send email notification
                require_once __DIR__ . '/email_handler.php';
                $emailHandler = new EmailHandler($pdo);
                
                // Get store name
                $stmtStore = $pdo->prepare("SELECT store_name FROM stores WHERE id = ?");
                $stmtStore->execute([$data['store_id']]);
                $store_name = $stmtStore->fetchColumn() ?: 'Tidak Diketahui';
                
                // Format email message
                $typeIcon = $data['type'] === 'Pemasukan' ? '💵' : '💸';
                $emailSubject = $typeIcon . ' ' . $data['type'] . ' Baru - ' . $store_name;
                $emailMessage = '
                    <h3>' . $typeIcon . ' <strong>' . strtoupper($data['type']) . '</strong></h3>
                    <p><strong>Tanggal:</strong> ' . date('d/m/Y', strtotime($data['tanggal'])) . '</p>
                    <p><strong>Store:</strong> ' . htmlspecialchars($store_name) . '</p>
                    
                    <hr style="border: 1px solid #e5e7eb; margin: 15px 0;">
                    
                    <h4>📝 Detail</h4>
                    <p><strong>Kategori:</strong> ' . htmlspecialchars($data['category']) . '</p>
                    <p><strong>Deskripsi:</strong> ' . htmlspecialchars($data['description']) . '</p>
                    
                    <hr style="border: 2px solid #3b82f6; margin: 15px 0;">
                    
                    <h4>💰 Nominal</h4>
                    <p style="font-size: 18px;"><strong><span class="badge ' . ($data['type'] === 'Pemasukan' ? 'badge-success' : 'badge-danger') . '">Rp ' . number_format($data['amount'], 0, ',', '.') . '</span></strong></p>
                ';
                
                // Send email (async, don't block if fails)
                $emailHandler->sendNotification($emailSubject, $emailMessage, $data['type'] === 'Pemasukan' ? 'pemasukan' : 'pengeluaran', $id);
                
            } catch (Exception $emailError) {
                // Silent fail - email error shouldn't stop the main process
                error_log('Email notification failed: ' . $emailError->getMessage());
            }
            
            jsonResponse(true, 'Transaksi kas berhasil ditambahkan', ['id' => $id]);
        } else {
            throw new Exception("Gagal menyimpan ke database.");
        }

    } catch (Exception $e) {
        error_log("Error: " . $e->getMessage());
        jsonResponse(false, 'Gagal menambahkan transaksi: ' . $e->getMessage(), [], [], 500);
    }
    break;
case 'save_bbm_distribution':
    try {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        
        if (empty($data['transaction_id']) || empty($data['distribution'])) {
            throw new Exception("Data distribusi tidak lengkap");
        }
        
        $transaction_id = $data['transaction_id'];
        $distribution = $data['distribution'];
        
        // Simpan ke tabel bbm_distribution_details
        $stmt = $pdo->prepare("
            INSERT INTO bbm_distribution_details 
            (bbm_transaction_id, store_id, jumlah_drigen, subtotal) 
            VALUES (?, ?, ?, ?)
        ");
        
        foreach ($distribution['stores'] as $store) {
            $stmt->execute([
                $transaction_id,
                $store['storeId'],
                $store['jumlahDrigen'],
                $store['amount']
            ]);
        }
        
        jsonResponse(true, 'Detail distribusi BBM berhasil disimpan');
        
    } catch (Exception $e) {
        jsonResponse(false, 'Gagal menyimpan distribusi: ' . $e->getMessage());
    }
    break;

    // =========================================================
// GET BBM SUMMARY PER STORE
// =========================================================
case 'get_bbm_summary':
    try {
        $month = $_GET['month'] ?? date('m');
        $year = $_GET['year'] ?? date('Y');

        // Method 1: Parse dari description (format: BBM_XDRIGEN_StoreName)
        $sql = "
            SELECT 
                s.store_name,
                s.id as store_id,
                COUNT(cfm.id) as transaction_count,
                SUM(
                    CASE 
                        WHEN cfm.description LIKE 'BBM_%DRIGEN_%' THEN
                            CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(cfm.description, '_', 2), '_', -1) AS UNSIGNED)
                        WHEN cfm.notes LIKE 'BBM_DETAIL:%' THEN
                            CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(cfm.notes, ':', 3), ':', -1) AS UNSIGNED)
                        ELSE 0
                    END
                ) as total_drigen,
                SUM(cfm.amount) as total_amount
            FROM cash_flow_management cfm
            INNER JOIN stores s ON cfm.store_id = s.id
            WHERE cfm.category = 'bbm'
                AND YEAR(cfm.tanggal) = ?
                AND MONTH(cfm.tanggal) = ?
            GROUP BY s.id, s.store_name
            ORDER BY total_drigen DESC, s.store_name
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([$year, $month]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Debug: Log results untuk troubleshooting
        error_log("BBM Summary Results: " . print_r($results, true));

        jsonResponse(true, 'Data summary BBM berhasil dimuat', $results);

    } catch (Exception $e) {
        error_log("BBM Summary Error: " . $e->getMessage());
        jsonResponse(false, 'Gagal memuat summary BBM: ' . $e->getMessage());
    }
    break;
    // API endpoint untuk BBM details
// Di case 'get_bbm_detailed_summary', perbaiki line 810:
case 'get_bbm_detailed_summary':
    try {
        $month = $_GET['month'] ?? date('m');
        $year = $_GET['year'] ?? date('Y');

        $sql = "
            SELECT 
                cfm.id,
                cfm.tanggal,
                s.store_name,
                cfm.description,
                cfm.notes,
                cfm.amount
            FROM cash_flow_management cfm
            INNER JOIN stores s ON cfm.store_id = s.id
            WHERE cfm.category = 'bbm'
                AND YEAR(cfm.tanggal) = ?
                AND MONTH(cfm.tanggal) = ?
            ORDER BY s.store_name, cfm.tanggal
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([$year, $month]);
        $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Process dan group manually
        $summary = [];
        foreach ($transactions as $transaction) {
            $storeName = $transaction['store_name'];
            $amount = $transaction['amount'];
            
            // FIX: Handle null values untuk description dan notes
            $description = $transaction['description'] ?? '';
            $notes = $transaction['notes'] ?? '';
            
            // Parse jumlah drigen dari description atau notes
            $jumlahDrigen = 0;
            
            // Method 1: Parse dari description baru (format: Pembelian BBM X Drigen - StoreName)
            if ($description && preg_match('/Pembelian BBM (\d+) Drigen/', $description, $matches)) {
                $jumlahDrigen = intval($matches[1]);
            }
            // Method 2: Parse dari description lama (format: BBM_XDRIGEN_StoreName)
            elseif ($description && preg_match('/BBM_(\d+)DRIGEN_/', $description, $matches)) {
                $jumlahDrigen = intval($matches[1]);
            }
            // Method 3: Parse dari notes
            elseif ($notes && preg_match('/BBM_DETAIL:\d+:(\d+):\d+:\d+/', $notes, $matches)) {
                $jumlahDrigen = intval($matches[1]);
            }
            // Method 4: Parse dari description dengan format lain
            elseif ($description && preg_match('/(\d+)\s*[Dd]rigen/', $description, $matches)) {
                $jumlahDrigen = intval($matches[1]);
            }
            // Method 5: Default ke 1 jika tidak bisa di-parse
            else {
                $jumlahDrigen = 1; // Fallback
                error_log("Could not parse drigen from: " . $description);
            }

            if (!isset($summary[$storeName])) {
                $summary[$storeName] = [
                    'store_name' => $storeName,
                    'total_drigen' => 0,
                    'total_amount' => 0,
                    'transactions' => []
                ];
            }

            $summary[$storeName]['total_drigen'] += $jumlahDrigen;
            $summary[$storeName]['total_amount'] += $amount;
            $summary[$storeName]['transactions'][] = [
                'tanggal' => $transaction['tanggal'],
                'jumlah_drigen' => $jumlahDrigen,
                'amount' => $amount,
                'description' => $description
            ];
        }

        // Convert to array for JSON
        $results = array_values($summary);

        jsonResponse(true, 'Data detail BBM berhasil dimuat', $results);

    } catch (Exception $e) {
        error_log("BBM Detailed Summary Error: " . $e->getMessage());
        jsonResponse(false, 'Gagal memuat detail BBM: ' . $e->getMessage());
    }
    break;
// =========================================================
// EDIT MANAGEMENT CASH FLOW - FIXED VERSION  
// =========================================================
case 'edit_management_cash_flow':
    try {
        // Handle both form data and JSON input
        if (!empty($_POST) && isset($_POST['id'])) {
            $data = $_POST;
        } else {
            $input = file_get_contents('php://input');
            $data = json_decode($input, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception("Format data JSON tidak valid.");
            }
        }
        
        if (empty($data) || !isset($data['id'])) {
            throw new Exception("Data transaksi kas atau ID tidak ditemukan.");
        }

        // Validasi field wajib
        $required_fields = ['tanggal', 'store_id', 'type', 'category', 'description', 'amount'];
        foreach ($required_fields as $field) {
            if (!isset($data[$field]) || $data[$field] === '') {
                throw new Exception("Field wajib '{$field}' hilang atau kosong.");
            }
        }

        // Validasi numeric amount
        if (!is_numeric($data['amount']) || $data['amount'] <= 0) {
            throw new Exception("Nominal harus berupa angka positif.");
        }

        // Cek apakah data exists
        $stmt = $pdo->prepare("SELECT id FROM cash_flow_management WHERE id = ?");
        $stmt->execute([$data['id']]);
        if (!$stmt->fetch()) {
            throw new Exception("Transaksi tidak ditemukan.");
        }

        // Bersihkan data
        $id = intval($data['id']);
        $tanggal = $data['tanggal'];
        $store_id = intval($data['store_id']);
        $description = trim($data['description']);
        $amount = floatval($data['amount']);
        $type = $data['type'];
        $category = $data['category'];

        // Update data
        $sql = "UPDATE cash_flow_management 
                SET tanggal = ?, store_id = ?, description = ?, amount = ?, type = ?, category = ?, updated_at = NOW()
                WHERE id = ?";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $tanggal,
            $store_id,
            $description,
            $amount,
            $type,
            $category,
            $id
        ]);

        // Log activity
        logActivity("Mengedit transaksi kas ID {$id}: {$description}");
        
        jsonResponse(true, 'Transaksi kas berhasil diperbarui');

    } catch (Exception $e) {
        error_log("Cash Flow Edit Error: " . $e->getMessage());
        jsonResponse(false, 'Gagal memperbarui transaksi: ' . $e->getMessage(), [], [], 500);
    }
    break;
case 'delete_management_cash_flow':
    try {
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($data['id'])) {
            throw new Exception("ID transaksi tidak ditemukan.");
        }

        // Ambil data sebelum dihapus untuk logging
        $stmt = $pdo->prepare("SELECT description, amount FROM cash_flow_management WHERE id = ?");
        $stmt->execute([$data['id']]);
        $transaction = $stmt->fetch();
        
        if (!$transaction) {
            throw new Exception("Transaksi tidak ditemukan.");
        }

        // Hapus data
        $stmt = $pdo->prepare("DELETE FROM cash_flow_management WHERE id = ?");
        $stmt->execute([$data['id']]);

        // Log activity
        logActivity("Menghapus transaksi kas: {$transaction['description']} - Rp " . number_format($transaction['amount']));
        
        jsonResponse(true, 'Transaksi kas berhasil dihapus');

    } catch (Exception $e) {
        error_log("Cash Flow Delete Error: " . $e->getMessage());
        jsonResponse(false, 'Gagal menghapus transaksi: ' . $e->getMessage(), [], [], 500);
    }
    break;
// Helper function untuk logging
function logActivity($activity) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("INSERT INTO activity_log (activity, created_at) VALUES (?, NOW())");
        $stmt->execute([$activity]);
    } catch (Exception $e) {
        error_log("Activity Log Error: " . $e->getMessage());
    }
}

// Helper function untuk response JSON
function jsonResponse($success, $message, $data = [], $summary = [], $httpCode = 200) {
    http_response_code($httpCode);
    
    // FIX: Ensure data is always an array
    if (!is_array($data)) {
        $data = [];
    }
    
    // FIX: Ensure summary is always an array
    if (!is_array($summary)) {
        $summary = [];
    }
    
    $response = [
        'success' => $success, 
        'message' => $message,
        'data' => $data,
        'summary' => $summary
    ];
    
    // Remove empty arrays to reduce payload
    if (empty($data)) {
        unset($response['data']);
    }
    if (empty($summary)) {
        unset($response['summary']);
    }
    
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}
    // =========================================================
    // DASHBOARD WALLET & EXPORT
    // =========================================================
    case 'get_dashboard_wallet':
        try {
            $month = $_GET['month'] ?? date('m');
            $year = $_GET['year'] ?? date('Y');
            $store_id_filter = $_GET['store_id'] ?? '';

            $params = [$year, $month];
            $where_clause = "YEAR(cfm.tanggal) = ? AND MONTH(cfm.tanggal) = ?";
            $where_clause_setoran = "YEAR(s.tanggal) = ? AND MONTH(s.tanggal) = ?";
            
            if (!empty($store_id_filter)) {
                $where_clause .= " AND cfm.store_id = ?";
                $where_clause_setoran .= " AND s.store_id = ?";
                $params[] = $store_id_filter;
            }

            // Query Cashflow Management untuk Income & Expense
            $sql_cf = "
                SELECT
                    SUM(CASE WHEN type = 'Pemasukan' THEN amount ELSE 0 END) as total_pemasukan,
                    SUM(CASE WHEN type = 'Pengeluaran' THEN amount ELSE 0 END) as total_pengeluaran
                FROM cash_flow_management cfm
                WHERE {$where_clause}
            ";
            $stmt_cf = $pdo->prepare($sql_cf);
            $stmt_cf->execute($params);
            $cf_data = $stmt_cf->fetch(PDO::FETCH_ASSOC);

            $total_income = $cf_data['total_pemasukan'] ?? 0;
            $total_expense = $cf_data['total_pengeluaran'] ?? 0;

            // Query Total Liter dari tabel Setoran
            $sql_liter = "
                SELECT
                    COALESCE(SUM(s.total_liter), 0) as total_liter
                FROM setoran s
                WHERE {$where_clause_setoran}
            ";
            $stmt_liter = $pdo->prepare($sql_liter);
            $stmt_liter->execute($params);
            $liter_data = $stmt_liter->fetch(PDO::FETCH_ASSOC);
            $total_liter = $liter_data['total_liter'] ?? 0;

            $all_stores = [
                'total_income' => $total_income,
                'total_expense' => $total_expense,
                'balance' => $total_income - $total_expense,
                'total_liter' => $total_liter
            ];

            // Breakdown Pengeluaran - dari cashflow
            $sql_expense = "
                SELECT description, SUM(amount) as amount
                FROM cash_flow_management cfm
                WHERE cfm.type = 'Pengeluaran' AND YEAR(cfm.tanggal) = ? AND MONTH(cfm.tanggal) = ?
                " . (!empty($store_id_filter) ? " AND cfm.store_id = ?" : "") . "
                GROUP BY description
                ORDER BY amount DESC
            ";
            $stmt_expense = $pdo->prepare($sql_expense);
            $expense_params = [$year, $month];
            if (!empty($store_id_filter)) {
                $expense_params[] = $store_id_filter;
            }
            $stmt_expense->execute($expense_params);
            $expense_breakdown = $stmt_expense->fetchAll(PDO::FETCH_ASSOC);

            // Breakdown Pemasukan - dari cashflow
            $sql_income = "
                SELECT description, SUM(amount) as amount
                FROM cash_flow_management cfm
                WHERE cfm.type = 'Pemasukan' AND YEAR(cfm.tanggal) = ? AND MONTH(cfm.tanggal) = ?
                " . (!empty($store_id_filter) ? " AND cfm.store_id = ?" : "") . "
                GROUP BY description
                ORDER BY amount DESC
            ";
            $stmt_income = $pdo->prepare($sql_income);
            $stmt_income->execute($expense_params);
            $income_breakdown = $stmt_income->fetchAll(PDO::FETCH_ASSOC);

            // Per Store - Gabungkan data dari cashflow dan setoran
            $sql_store = "
                SELECT
                    st.id,
                    st.store_name,
                    COALESCE(SUM(CASE WHEN cfm.type = 'Pemasukan' THEN cfm.amount ELSE 0 END), 0) as income,
                    COALESCE(SUM(CASE WHEN cfm.type = 'Pengeluaran' THEN cfm.amount ELSE 0 END), 0) as expense
                FROM stores st
                LEFT JOIN cash_flow_management cfm ON st.id = cfm.store_id 
                    AND YEAR(cfm.tanggal) = ? AND MONTH(cfm.tanggal) = ?
                GROUP BY st.id, st.store_name
                ORDER BY st.store_name
            ";
            $stmt_store = $pdo->prepare($sql_store);
            $stmt_store->execute([$year, $month]);
            $stores_data = $stmt_store->fetchAll(PDO::FETCH_ASSOC);

            // Query liter per store
            $sql_liter_per_store = "
                SELECT
                    store_id,
                    COALESCE(SUM(total_liter), 0) as total_liter
                FROM setoran
                WHERE YEAR(tanggal) = ? AND MONTH(tanggal) = ?
                GROUP BY store_id
            ";
            $stmt_liter_per_store = $pdo->prepare($sql_liter_per_store);
            $stmt_liter_per_store->execute([$year, $month]);
            $liter_per_store_data = $stmt_liter_per_store->fetchAll(PDO::FETCH_ASSOC);
            
            // Create a map of store_id => total_liter
            $liter_map = [];
            foreach ($liter_per_store_data as $liter_row) {
                $liter_map[$liter_row['store_id']] = $liter_row['total_liter'];
            }

            $per_store = [];
            foreach ($stores_data as $store) {
                $store_liter = $liter_map[$store['id']] ?? 0;
                $per_store[] = [
                    'store_name' => $store['store_name'],
                    'income' => $store['income'],
                    'expense' => $store['expense'],
                    'balance' => $store['income'] - $store['expense'],
                    'total_liter' => $store_liter
                ];
            }

            $result = [
                'all_stores' => $all_stores,
                'expense_breakdown' => $expense_breakdown,
                'income_breakdown' => $income_breakdown,
                'per_store' => $per_store
            ];

            jsonResponse(true, 'Dashboard data berhasil dimuat', $result);

        } catch (Exception $e) {
            jsonResponse(false, 'Error: ' . $e->getMessage(), [], [], 500);
        }
        break;

    case 'export_dashboard':
        try {
            $type = $_GET['type'] ?? 'pdf';
            $month = $_GET['month'] ?? date('m');
            $year = $_GET['year'] ?? date('Y');

            $where_clause = "YEAR(s.tanggal) = ? AND MONTH(s.tanggal) = ?";
            $params = [$year, $month];

            $sql_all = "
                SELECT
                    SUM(s.total_liter) as total_liter,
                    SUM(s.cash + s.qris) as total_setoran,
                    SUM(s.total_pemasukan) as total_pemasukan_setoran,
                    SUM(s.total_pengeluaran) as total_pengeluaran_setoran
                FROM setoran s
                WHERE {$where_clause}
            ";
            $stmt = $pdo->prepare($sql_all);
            $stmt->execute($params);
            $setoran_data = $stmt->fetch(PDO::FETCH_ASSOC);

            // NOTE: Exclude qris_setoran category karena sudah included dalam total_setoran
            $sql_cf = "
                SELECT
                    SUM(CASE WHEN type = 'Pemasukan' AND category != 'qris_setoran' THEN amount ELSE 0 END) as pemasukan_manajemen,
                    SUM(CASE WHEN type = 'Pengeluaran' THEN amount ELSE 0 END) as pengeluaran_manajemen
                FROM cash_flow_management
                WHERE YEAR(tanggal) = ? AND MONTH(tanggal) = ?
            ";
            $stmt_cf = $pdo->prepare($sql_cf);
            $stmt_cf->execute([$year, $month]);
            $cf_data = $stmt_cf->fetch(PDO::FETCH_ASSOC);

            // Total income = setoran (cash+qris already included) + pemasukan_setoran + cashflow pemasukan (excluding qris_setoran)
            $total_income = ($setoran_data['total_setoran'] ?? 0) + ($setoran_data['total_pemasukan_setoran'] ?? 0) + ($cf_data['pemasukan_manajemen'] ?? 0);
            $total_expense = ($setoran_data['total_pengeluaran_setoran'] ?? 0) + ($cf_data['pengeluaran_manajemen'] ?? 0);

            if ($type === 'pdf') {
                exportPDF($year, $month, $total_income, $total_expense, $pdo);
            } else if ($type === 'excel') {
                exportExcel($year, $month, $total_income, $total_expense, $pdo);
            }

        } catch (Exception $e) {
            jsonResponse(false, 'Error: ' . $e->getMessage(), [], [], 500);
        }
        break;

    // =========================================================
    // SEND DAILY REPORT EMAIL
    // =========================================================
    case 'send_daily_report':
        try {
            require_once __DIR__ . '/email_handler.php';
            
            // Force override flag untuk manual trigger
            $forceOverride = isset($_POST['force']) && $_POST['force'] === 'true';
            
            $emailHandler = new EmailHandler($pdo);
            $result = $emailHandler->sendDailyReport($forceOverride);
            
            jsonResponse($result['success'], $result['message']);
            
        } catch (Exception $e) {
            jsonResponse(false, 'Error: ' . $e->getMessage(), [], [], 500);
        }
        break;

    default:
        jsonResponse(false, 'Aksi tidak valid atau tidak ditemukan', [], [], 400);
}

// =========================================================
// FUNGSI EXPORT PDF & EXCEL
// =========================================================

function exportPDF($year, $month, $total_income, $total_expense, $pdo) {
    $months = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
    $month_name = $months[intval($month)];

    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="Laporan_Keuangan_' . $month_name . '_' . $year . '.pdf"');

    $html = '<!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Laporan Keuangan</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 20px; }
            h1 { color: #333; text-align: center; }
            table { width: 100%; border-collapse: collapse; margin: 20px 0; }
            th, td { border: 1px solid #ddd; padding: 10px; text-align: left; }
            th { background-color: #4F46E5; color: white; }
            .summary { background-color: #f0f0f0; padding: 15px; margin: 20px 0; border-radius: 5px; }
            .positive { color: green; font-weight: bold; }
            .negative { color: red; font-weight: bold; }
        </style>
    </head>
    <body>
        <h1>LAPORAN KEUANGAN</h1>
        <h2 style="text-align:center;">' . $month_name . ' ' . $year . '</h2>

        <div class="summary">
            <h3>Ringkasan Keuangan</h3>
            <table>
                <tr>
                    <td><strong>Total Pemasukan:</strong></td>
                    <td class="positive">Rp ' . number_format($total_income, 0, ',', '.') . '</td>
                </tr>
                <tr>
                    <td><strong>Total Pengeluaran:</strong></td>
                    <td class="negative">Rp ' . number_format($total_expense, 0, ',', '.') . '</td>
                </tr>
                <tr>
                    <td><strong>Saldo Bersih:</strong></td>
                    <td class="' . ($total_income - $total_expense >= 0 ? 'positive' : 'negative') . '">Rp ' . number_format($total_income - $total_expense, 0, ',', '.') . '</td>
                </tr>
            </table>
        </div>

        <p style="text-align:center; margin-top:40px; font-size:12px; color:#666;">
            Dokumen ini digenerate otomatis oleh sistem Setoran Harian<br>
            Tanggal cetak: ' . date('d-m-Y H:i:s') . '
        </p>
    </body>
    </html>';

    echo $html;
    exit;
}

function exportExcel($year, $month, $total_income, $total_expense, $pdo) {
    $months = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
    $month_name = $months[intval($month)];

    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="Laporan_Keuangan_' . $month_name . '_' . $year . '.xls"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $where_clause = "YEAR(s.tanggal) = ? AND MONTH(s.tanggal) = ?";
    $params = [$year, $month];

    $sql_setoran = "
        SELECT s.tanggal, st.store_name, e.employee_name, s.total_liter, s.cash, s.qris,
               s.total_setoran, s.total_pengeluaran, s.total_pemasukan, s.total_keseluruhan
        FROM setoran s
        LEFT JOIN stores st ON s.store_id = st.id
        LEFT JOIN employees e ON s.employee_id = e.id
        WHERE {$where_clause}
        ORDER BY s.tanggal DESC
    ";
    $stmt = $pdo->prepare($sql_setoran);
    $stmt->execute($params);
    $setoran_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo '<html xmlns:x="urn:schemas-microsoft-com:office:excel">';
    echo '<head>';
    echo '<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />';
    echo '</head>';
    echo '<body>';

    echo '<h1>LAPORAN KEUANGAN - ' . $month_name . ' ' . $year . '</h1>';

    echo '<table border="1">';
    echo '<tr><td colspan="2"><strong>RINGKASAN</strong></td></tr>';
    echo '<tr><td>Total Pemasukan</td><td>Rp ' . number_format($total_income, 0, ',', '.') . '</td></tr>';
    echo '<tr><td>Total Pengeluaran</td><td>Rp ' . number_format($total_expense, 0, ',', '.') . '</td></tr>';
    echo '<tr><td>Saldo Bersih</td><td>Rp ' . number_format($total_income - $total_expense, 0, ',', '.') . '</td></tr>';
    echo '</table>';

    echo '<br><br>';

    echo '<table border="1">';
    echo '<tr>
            <th>Tanggal</th>
            <th>Store</th>
            <th>Karyawan</th>
            <th>Total Liter</th>
            <th>Cash</th>
            <th>QRIS</th>
            <th>Total Setoran</th>
            <th>Pengeluaran</th>
            <th>Pemasukan</th>
            <th>Total Bersih</th>
          </tr>';

    foreach ($setoran_list as $row) {
        echo '<tr>';
        echo '<td>' . $row['tanggal'] . '</td>';
        echo '<td>' . ($row['store_name'] ?? 'N/A') . '</td>';
        echo '<td>' . ($row['employee_name'] ?? 'N/A') . '</td>';
        echo '<td>' . number_format($row['total_liter'], 2, ',', '.') . '</td>';
        echo '<td>Rp ' . number_format($row['cash'], 0, ',', '.') . '</td>';
        echo '<td>Rp ' . number_format($row['qris'], 0, ',', '.') . '</td>';
        echo '<td>Rp ' . number_format($row['total_setoran'], 0, ',', '.') . '</td>';
        echo '<td>Rp ' . number_format($row['total_pengeluaran'], 0, ',', '.') . '</td>';
        echo '<td>Rp ' . number_format($row['total_pemasukan'], 0, ',', '.') . '</td>';
        echo '<td>Rp ' . number_format($row['total_keseluruhan'], 0, ',', '.') . '</td>';
        echo '</tr>';
    }

    echo '</table>';
    echo '</body></html>';
    exit;
}
?>