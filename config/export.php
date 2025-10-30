<?php
require_once 'config.php';
require_once 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

$action = $_GET['action'] ?? '';

// Fungsi helper untuk format Rupiah
function formatRupiahNumber($number) {
    return number_format($number, 0, ',', '.');
}

// =========================================================
// EXPORT DASHBOARD TO EXCEL
// =========================================================
if ($action === 'export_dashboard_excel') {
    try {
        $month = $_GET['month'] ?? date('m');
        $year = $_GET['year'] ?? date('Y');
        $store_id_filter = $_GET['store_id'] ?? '';

        $months = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
        $month_name = $months[intval($month)];

        // Query data
        $where_clause = "YEAR(s.tanggal) = ? AND MONTH(s.tanggal) = ?";
        $params = [$year, $month];
        
        if (!empty($store_id_filter)) {
            $where_clause .= " AND s.store_id = ?";
            $params[] = $store_id_filter;
        }

        // Data Setoran
        $sql_setoran = "
            SELECT s.tanggal, st.store_name, e.employee_name, s.jam_masuk, s.jam_keluar,
                   s.nomor_awal, s.nomor_akhir, s.total_liter, s.cash, s.qris,
                   s.total_setoran, s.total_pengeluaran, s.total_pemasukan, s.total_keseluruhan
            FROM setoran s
            LEFT JOIN stores st ON s.store_id = st.id
            LEFT JOIN employees e ON s.employee_id = e.id
            WHERE {$where_clause}
            ORDER BY s.tanggal DESC, s.jam_masuk DESC
        ";
        $stmt = $pdo->prepare($sql_setoran);
        $stmt->execute($params);
        $setoran_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Data Cashflow
        $sql_cashflow = "
            SELECT cfm.tanggal, s.store_name, cfm.description, cfm.type, cfm.category, cfm.amount
            FROM cash_flow_management cfm
            LEFT JOIN stores s ON cfm.store_id = s.id
            WHERE YEAR(cfm.tanggal) = ? AND MONTH(cfm.tanggal) = ?
        ";
        $cf_params = [$year, $month];
        if (!empty($store_id_filter)) {
            $sql_cashflow .= " AND cfm.store_id = ?";
            $cf_params[] = $store_id_filter;
        }
        $sql_cashflow .= " ORDER BY cfm.tanggal DESC";
        
        $stmt_cf = $pdo->prepare($sql_cashflow);
        $stmt_cf->execute($cf_params);
        $cashflow_data = $stmt_cf->fetchAll(PDO::FETCH_ASSOC);

        // Hitung Summary
        $total_income_setoran = array_sum(array_column($setoran_data, 'total_setoran'));
        $total_income_pemasukan = array_sum(array_column($setoran_data, 'total_pemasukan'));
        $total_expense_setoran = array_sum(array_column($setoran_data, 'total_pengeluaran'));
        
        $total_income_cf = 0;
        $total_expense_cf = 0;
        foreach ($cashflow_data as $cf) {
            if ($cf['type'] === 'Pemasukan') {
                $total_income_cf += $cf['amount'];
            } else {
                $total_expense_cf += $cf['amount'];
            }
        }
        
        $total_income = $total_income_setoran + $total_income_pemasukan + $total_income_cf;
        $total_expense = $total_expense_setoran + $total_expense_cf;
        $balance = $total_income - $total_expense;

        // Buat Spreadsheet
        $spreadsheet = new Spreadsheet();
        $spreadsheet->getProperties()
            ->setTitle("Laporan Dashboard $month_name $year")
            ->setSubject("Laporan Keuangan")
            ->setDescription("Laporan Dashboard Wallet & Cashflow");

        // ===== SHEET 1: SUMMARY =====
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Summary');

        // Header
        $sheet->setCellValue('A1', 'LAPORAN DASHBOARD KEUANGAN');
        $sheet->setCellValue('A2', "$month_name $year");
        $sheet->mergeCells('A1:D1');
        $sheet->mergeCells('A2:D2');
        
        $sheet->getStyle('A1:A2')->getFont()->setBold(true)->setSize(16);
        $sheet->getStyle('A1:A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Summary Table
        $row = 4;
        $sheet->setCellValue("A$row", 'RINGKASAN KEUANGAN');
        $sheet->getStyle("A$row")->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle("A$row")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('4F46E5');
        $sheet->getStyle("A$row")->getFont()->getColor()->setRGB('FFFFFF');
        $sheet->mergeCells("A$row:D$row");
        
        $row++;
        $sheet->setCellValue("A$row", 'Keterangan');
        $sheet->setCellValue("B$row", 'Jumlah');
        $sheet->getStyle("A$row:B$row")->getFont()->setBold(true);
        $sheet->getStyle("A$row:B$row")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E5E7EB');
        
        $row++;
        $sheet->setCellValue("A$row", 'Total Pemasukan');
        $sheet->setCellValue("B$row", "Rp " . formatRupiahNumber($total_income));
        $sheet->getStyle("B$row")->getFont()->getColor()->setRGB('059669');
        
        $row++;
        $sheet->setCellValue("A$row", 'Total Pengeluaran');
        $sheet->setCellValue("B$row", "Rp " . formatRupiahNumber($total_expense));
        $sheet->getStyle("B$row")->getFont()->getColor()->setRGB('DC2626');
        
        $row++;
        $sheet->setCellValue("A$row", 'Saldo Bersih');
        $sheet->setCellValue("B$row", "Rp " . formatRupiahNumber($balance));
        $sheet->getStyle("A$row:B$row")->getFont()->setBold(true);
        $sheet->getStyle("B$row")->getFont()->getColor()->setRGB($balance >= 0 ? '059669' : 'DC2626');

        // Styling
        $sheet->getStyle("A4:B$row")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $sheet->getColumnDimension('A')->setWidth(30);
        $sheet->getColumnDimension('B')->setWidth(25);

        // ===== SHEET 2: DATA SETORAN =====
        $sheet2 = $spreadsheet->createSheet();
        $sheet2->setTitle('Data Setoran');
        
        $row = 1;
        $sheet2->setCellValue("A$row", 'DATA SETORAN HARIAN');
        $sheet2->mergeCells("A$row:N$row");
        $sheet2->getStyle("A$row")->getFont()->setBold(true)->setSize(14);
        $sheet2->getStyle("A$row")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        
        $row += 2;
        $headers = ['Tanggal', 'Store', 'Karyawan', 'Jam Masuk', 'Jam Keluar', 'No. Awal', 'No. Akhir', 'Total Liter', 'Cash', 'QRIS', 'Total Setoran', 'Pengeluaran', 'Pemasukan', 'Total Bersih'];
        $col = 'A';
        foreach ($headers as $header) {
            $sheet2->setCellValue($col.$row, $header);
            $col++;
        }
        $sheet2->getStyle("A$row:N$row")->getFont()->setBold(true);
        $sheet2->getStyle("A$row:N$row")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('4F46E5');
        $sheet2->getStyle("A$row:N$row")->getFont()->getColor()->setRGB('FFFFFF');
        
        $row++;
        foreach ($setoran_data as $data) {
            $sheet2->setCellValue("A$row", $data['tanggal']);
            $sheet2->setCellValue("B$row", $data['store_name'] ?? 'N/A');
            $sheet2->setCellValue("C$row", $data['employee_name'] ?? 'N/A');
            $sheet2->setCellValue("D$row", substr($data['jam_masuk'], 0, 5));
            $sheet2->setCellValue("E$row", substr($data['jam_keluar'], 0, 5));
            $sheet2->setCellValue("F$row", number_format($data['nomor_awal'], 2, ',', '.'));
            $sheet2->setCellValue("G$row", number_format($data['nomor_akhir'], 2, ',', '.'));
            $sheet2->setCellValue("H$row", number_format($data['total_liter'], 2, ',', '.') . ' L');
            $sheet2->setCellValue("I$row", "Rp " . formatRupiahNumber($data['cash']));
            $sheet2->setCellValue("J$row", "Rp " . formatRupiahNumber($data['qris']));
            $sheet2->setCellValue("K$row", "Rp " . formatRupiahNumber($data['total_setoran']));
            $sheet2->setCellValue("L$row", "Rp " . formatRupiahNumber($data['total_pengeluaran']));
            $sheet2->setCellValue("M$row", "Rp " . formatRupiahNumber($data['total_pemasukan']));
            $sheet2->setCellValue("N$row", "Rp " . formatRupiahNumber($data['total_keseluruhan']));
            $row++;
        }
        
        $sheet2->getStyle("A3:N".($row-1))->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        foreach (range('A', 'N') as $col) {
            $sheet2->getColumnDimension($col)->setAutoSize(true);
        }

        // ===== SHEET 3: DATA CASHFLOW =====
        $sheet3 = $spreadsheet->createSheet();
        $sheet3->setTitle('Data Cashflow');
        
        $row = 1;
        $sheet3->setCellValue("A$row", 'DATA MANAJEMEN KAS');
        $sheet3->mergeCells("A$row:F$row");
        $sheet3->getStyle("A$row")->getFont()->setBold(true)->setSize(14);
        $sheet3->getStyle("A$row")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        
        $row += 2;
        $headers = ['Tanggal', 'Store', 'Deskripsi', 'Jenis', 'Kategori', 'Nominal'];
        $col = 'A';
        foreach ($headers as $header) {
            $sheet3->setCellValue($col.$row, $header);
            $col++;
        }
        $sheet3->getStyle("A$row:F$row")->getFont()->setBold(true);
        $sheet3->getStyle("A$row:F$row")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('4F46E5');
        $sheet3->getStyle("A$row:F$row")->getFont()->getColor()->setRGB('FFFFFF');
        
        $row++;
        foreach ($cashflow_data as $data) {
            $sheet3->setCellValue("A$row", $data['tanggal']);
            $sheet3->setCellValue("B$row", $data['store_name'] ?? 'N/A');
            $sheet3->setCellValue("C$row", $data['description']);
            $sheet3->setCellValue("D$row", $data['type']);
            $sheet3->setCellValue("E$row", $data['category']);
            $sheet3->setCellValue("F$row", "Rp " . formatRupiahNumber($data['amount']));
            
            // Color coding
            if ($data['type'] === 'Pemasukan') {
                $sheet3->getStyle("F$row")->getFont()->getColor()->setRGB('059669');
            } else {
                $sheet3->getStyle("F$row")->getFont()->getColor()->setRGB('DC2626');
            }
            $row++;
        }
        
        $sheet3->getStyle("A3:F".($row-1))->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        foreach (range('A', 'F') as $col) {
            $sheet3->getColumnDimension($col)->setAutoSize(true);
        }

        // Output file
        $filename = "Laporan_Dashboard_{$month_name}_{$year}.xlsx";
        
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header("Content-Disposition: attachment;filename=\"$filename\"");
        header('Cache-Control: max-age=0');
        
        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
        
    } catch (Exception $e) {
        die("Error: " . $e->getMessage());
    }
}

// =========================================================
// EXPORT CASHFLOW TO EXCEL
// =========================================================
if ($action === 'export_cashflow_excel') {
    try {
        $month = $_GET['month'] ?? date('m');
        $year = $_GET['year'] ?? date('Y');
        $store_id_filter = $_GET['store_id'] ?? '';

        $months = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
        $month_name = $months[intval($month)];

        // Query data
        $sql = "
            SELECT cfm.tanggal, s.store_name, cfm.description, cfm.type, cfm.category, cfm.amount, cfm.notes
            FROM cash_flow_management cfm
            LEFT JOIN stores s ON cfm.store_id = s.id
            WHERE YEAR(cfm.tanggal) = ? AND MONTH(cfm.tanggal) = ?
        ";
        $params = [$year, $month];
        
        if (!empty($store_id_filter)) {
            $sql .= " AND cfm.store_id = ?";
            $params[] = $store_id_filter;
        }
        
        $sql .= " ORDER BY cfm.tanggal DESC, cfm.id DESC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Hitung summary
        $total_income = 0;
        $total_expense = 0;
        foreach ($data as $row) {
            if ($row['type'] === 'Pemasukan') {
                $total_income += $row['amount'];
            } else {
                $total_expense += $row['amount'];
            }
        }

        // Buat Spreadsheet
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Manajemen Kas');

        // Header
        $sheet->setCellValue('A1', 'LAPORAN MANAJEMEN KAS');
        $sheet->setCellValue('A2', "$month_name $year");
        $sheet->mergeCells('A1:G1');
        $sheet->mergeCells('A2:G2');
        
        $sheet->getStyle('A1:A2')->getFont()->setBold(true)->setSize(16);
        $sheet->getStyle('A1:A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Summary
        $row = 4;
        $sheet->setCellValue("A$row", 'Total Pemasukan:');
        $sheet->setCellValue("B$row", "Rp " . formatRupiahNumber($total_income));
        $sheet->getStyle("A$row:B$row")->getFont()->setBold(true);
        $sheet->getStyle("B$row")->getFont()->getColor()->setRGB('059669');
        
        $row++;
        $sheet->setCellValue("A$row", 'Total Pengeluaran:');
        $sheet->setCellValue("B$row", "Rp " . formatRupiahNumber($total_expense));
        $sheet->getStyle("A$row:B$row")->getFont()->setBold(true);
        $sheet->getStyle("B$row")->getFont()->getColor()->setRGB('DC2626');
        
        $row++;
        $sheet->setCellValue("A$row", 'Saldo Bersih:');
        $sheet->setCellValue("B$row", "Rp " . formatRupiahNumber($total_income - $total_expense));
        $sheet->getStyle("A$row:B$row")->getFont()->setBold(true)->setSize(12);
        $sheet->getStyle("B$row")->getFont()->getColor()->setRGB(($total_income - $total_expense) >= 0 ? '059669' : 'DC2626');

        // Table Header
        $row += 2;
        $headers = ['Tanggal', 'Store', 'Deskripsi', 'Jenis', 'Kategori', 'Nominal', 'Catatan'];
        $col = 'A';
        foreach ($headers as $header) {
            $sheet->setCellValue($col.$row, $header);
            $col++;
        }
        $sheet->getStyle("A$row:G$row")->getFont()->setBold(true);
        $sheet->getStyle("A$row:G$row")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('4F46E5');
        $sheet->getStyle("A$row:G$row")->getFont()->getColor()->setRGB('FFFFFF');

        // Data
        $row++;
        foreach ($data as $item) {
            $sheet->setCellValue("A$row", $item['tanggal']);
            $sheet->setCellValue("B$row", $item['store_name'] ?? 'N/A');
            $sheet->setCellValue("C$row", $item['description']);
            $sheet->setCellValue("D$row", $item['type']);
            $sheet->setCellValue("E$row", $item['category']);
            $sheet->setCellValue("F$row", "Rp " . formatRupiahNumber($item['amount']));
            $sheet->setCellValue("G$row", $item['notes'] ?? '');
            
            // Color coding
            if ($item['type'] === 'Pemasukan') {
                $sheet->getStyle("F$row")->getFont()->getColor()->setRGB('059669');
            } else {
                $sheet->getStyle("F$row")->getFont()->getColor()->setRGB('DC2626');
            }
            
            $row++;
        }

        // Styling
        $lastRow = $row - 1;
        $sheet->getStyle("A8:G$lastRow")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        
        foreach (range('A', 'G') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Output
        $filename = "Laporan_Kas_{$month_name}_{$year}.xlsx";
        
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header("Content-Disposition: attachment;filename=\"$filename\"");
        header('Cache-Control: max-age=0');
        
        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
        
    } catch (Exception $e) {
        die("Error: " . $e->getMessage());
    }
}

// =========================================================
// EXPORT STORES TO EXCEL
// =========================================================
if ($action === 'export_stores_excel') {
    try {
        $stmt = $pdo->query("SELECT id, store_name, address, created_at FROM stores ORDER BY store_name ASC");
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Data Store');

        // Header
        $sheet->setCellValue('A1', 'DATA STORE / SPBU');
        $sheet->mergeCells('A1:D1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Table Header
        $row = 3;
        $headers = ['ID', 'Nama Store', 'Alamat', 'Tanggal Dibuat'];
        $col = 'A';
        foreach ($headers as $header) {
            $sheet->setCellValue($col.$row, $header);
            $col++;
        }
        $sheet->getStyle("A$row:D$row")->getFont()->setBold(true);
        $sheet->getStyle("A$row:D$row")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('4F46E5');
        $sheet->getStyle("A$row:D$row")->getFont()->getColor()->setRGB('FFFFFF');

        // Data
        $row++;
        foreach ($data as $item) {
            $sheet->setCellValue("A$row", $item['id']);
            $sheet->setCellValue("B$row", $item['store_name']);
            $sheet->setCellValue("C$row", $item['address'] ?? '-');
            $sheet->setCellValue("D$row", $item['created_at']);
            $row++;
        }

        // Styling
        $lastRow = $row - 1;
        $sheet->getStyle("A3:D$lastRow")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        
        foreach (range('A', 'D') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Output
        $filename = "Data_Store_" . date('Y-m-d') . ".xlsx";
        
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header("Content-Disposition: attachment;filename=\"$filename\"");
        header('Cache-Control: max-age=0');
        
        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
        
    } catch (Exception $e) {
        die("Error: " . $e->getMessage());
    }
}

// =========================================================
// EXPORT EMPLOYEES TO EXCEL
// =========================================================
if ($action === 'export_employees_excel') {
    try {
        $sql = "
            SELECT e.id, e.employee_name, e.employee_code, s.store_name, e.is_active, e.created_at
            FROM employees e
            LEFT JOIN stores s ON e.store_id = s.id
            ORDER BY e.employee_name ASC
        ";
        $stmt = $pdo->query($sql);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Data Karyawan');

        // Header
        $sheet->setCellValue('A1', 'DATA KARYAWAN');
        $sheet->mergeCells('A1:F1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Table Header
        $row = 3;
        $headers = ['ID', 'Nama Karyawan', 'Kode', 'Store', 'Status', 'Tanggal Dibuat'];
        $col = 'A';
        foreach ($headers as $header) {
            $sheet->setCellValue($col.$row, $header);
            $col++;
        }
        $sheet->getStyle("A$row:F$row")->getFont()->setBold(true);
        $sheet->getStyle("A$row:F$row")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('4F46E5');
        $sheet->getStyle("A$row:F$row")->getFont()->getColor()->setRGB('FFFFFF');

        // Data
        $row++;
        foreach ($data as $item) {
            $sheet->setCellValue("A$row", $item['id']);
            $sheet->setCellValue("B$row", $item['employee_name']);
            $sheet->setCellValue("C$row", $item['employee_code'] ?? '-');
            $sheet->setCellValue("D$row", $item['store_name'] ?? '-');
            $sheet->setCellValue("E$row", $item['is_active'] ? 'Aktif' : 'Tidak Aktif');
            $sheet->setCellValue("F$row", $item['created_at']);
            $row++;
        }

        // Styling
        $lastRow = $row - 1;
        $sheet->getStyle("A3:F$lastRow")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        
        foreach (range('A', 'F') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Output
        $filename = "Data_Karyawan_" . date('Y-m-d') . ".xlsx";
        
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header("Content-Disposition: attachment;filename=\"$filename\"");
        header('Cache-Control: max-age=0');
        
        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
        
    } catch (Exception $e) {
        die("Error: " . $e->getMessage());
    }
}

// Invalid action
http_response_code(400);
echo json_encode(['success' => false, 'message' => 'Invalid export action']);
?>
