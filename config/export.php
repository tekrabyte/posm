<?php
require_once 'config.php';
require_once '../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

// Include TCPDF
require_once '../vendor/tecnickcom/tcpdf.php';

$action = $_GET['action'] ?? '';

// Fungsi helper untuk format Rupiah
function formatRupiahNumber($number) {
    return number_format($number, 0, ',', '.');
}

// =========================================================
// EXPORT DASHBOARD TO EXCEL (MULTI-SHEET PER STORE)
// =========================================================
if ($action === 'export_dashboard_excel_v2') {
    try {
        $month = $_GET['month'] ?? date('m');
        $year = $_GET['year'] ?? date('Y');

        $months = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
        $month_name = $months[intval($month)];

        // Get all stores
        $stmt_stores = $pdo->query("SELECT id, store_name FROM stores ORDER BY store_name ASC");
        $stores = $stmt_stores->fetchAll(PDO::FETCH_ASSOC);

        // Buat Spreadsheet
        $spreadsheet = new Spreadsheet();
        $spreadsheet->getProperties()
            ->setTitle("Laporan Lengkap $month_name $year")
            ->setSubject("Laporan Keuangan Per Store")
            ->setDescription("Laporan Dashboard, Setoran, dan Cashflow Per Store");

        $sheetIndex = 0;

        // Loop untuk setiap store
        foreach ($stores as $store) {
            $store_id = $store['id'];
            $store_name = $store['store_name'];

            // Query data untuk store ini
            $where_clause = "YEAR(s.tanggal) = ? AND MONTH(s.tanggal) = ? AND s.store_id = ?";
            $params = [$year, $month, $store_id];

            // Data Setoran
            $sql_setoran = "
                SELECT s.tanggal, st.store_name, e.employee_name, s.jam_masuk, s.jam_keluar,
                       s.nomor_awal, s.nomor_akhir, s.jumlah_tera, s.total_liter, s.cash, s.qris,
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
                WHERE YEAR(cfm.tanggal) = ? AND MONTH(cfm.tanggal) = ? AND cfm.store_id = ?
                ORDER BY cfm.tanggal DESC
            ";
            $stmt_cf = $pdo->prepare($sql_cashflow);
            $stmt_cf->execute([$year, $month, $store_id]);
            $cashflow_data = $stmt_cf->fetchAll(PDO::FETCH_ASSOC);

            // Hitung Summary untuk store ini
            $total_income_setoran = array_sum(array_column($setoran_data, 'total_setoran'));
            $total_income_pemasukan = array_sum(array_column($setoran_data, 'total_pemasukan'));
            $total_expense_setoran = array_sum(array_column($setoran_data, 'total_pengeluaran'));
            $total_liter = array_sum(array_column($setoran_data, 'total_liter'));
            
            $total_income_cf = 0;
            $total_expense_cf = 0;
            foreach ($cashflow_data as $cf) {
                if ($cf['type'] === 'pemasukan' || $cf['type'] === 'Pemasukan') {
                    $total_income_cf += $cf['amount'];
                } else {
                    $total_expense_cf += $cf['amount'];
                }
            }
            
            $total_income = $total_income_setoran + $total_income_pemasukan + $total_income_cf;
            $total_expense = $total_expense_setoran + $total_expense_cf;
            $balance = $total_income - $total_expense;

            // Buat sheet baru untuk store ini
            if ($sheetIndex === 0) {
                $sheet = $spreadsheet->getActiveSheet();
            } else {
                $sheet = $spreadsheet->createSheet();
            }
            
            $sheet->setTitle(substr($store_name, 0, 31)); // Max 31 chars for sheet name

            // ===== SECTION 1: DASHBOARD WALLET =====
            $row = 1;
            $sheet->setCellValue("A$row", "LAPORAN KEUANGAN - $store_name");
            $sheet->setCellValue("A".($row+1), "$month_name $year");
            $sheet->mergeCells("A$row:F$row");
            $sheet->mergeCells("A".($row+1).":F".($row+1));
            $sheet->getStyle("A$row:A".($row+1))->getFont()->setBold(true)->setSize(16);
            $sheet->getStyle("A$row:A".($row+1))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            $row += 3;
            $sheet->setCellValue("A$row", 'DASHBOARD WALLET');
            $sheet->mergeCells("A$row:D$row");
            $sheet->getStyle("A$row")->getFont()->setBold(true)->setSize(14);
            $sheet->getStyle("A$row")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('4F46E5');
            $sheet->getStyle("A$row")->getFont()->getColor()->setRGB('FFFFFF');
            
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

            $row++;
            $sheet->setCellValue("A$row", 'Total Liter Terjual');
            $sheet->setCellValue("B$row", number_format($total_liter, 2, ',', '.') . ' L');
            $sheet->getStyle("A$row:B$row")->getFont()->setBold(true);

            $dashboard_end_row = $row;
            $sheet->getStyle("A5:B$dashboard_end_row")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

            // ===== SECTION 2: DATA SETORAN =====
            $row += 3;
            $setoran_start_row = $row;
            $sheet->setCellValue("A$row", 'DATA SETORAN HARIAN');
            $sheet->mergeCells("A$row:O$row");
            $sheet->getStyle("A$row")->getFont()->setBold(true)->setSize(14);
            $sheet->getStyle("A$row")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('10B981');
            $sheet->getStyle("A$row")->getFont()->getColor()->setRGB('FFFFFF');
            $sheet->getStyle("A$row")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            
            $row++;
            $headers = ['Tanggal', 'Karyawan', 'Jam Masuk', 'Jam Keluar', 'No. Awal', 'No. Akhir', 'Jumlah Tera', 'Total Liter', 'Cash', 'QRIS', 'Total Setoran', 'Pengeluaran', 'Pemasukan', 'Total Bersih'];
            $col = 'A';
            foreach ($headers as $header) {
                $sheet->setCellValue($col.$row, $header);
                $col++;
            }
            $sheet->getStyle("A$row:N$row")->getFont()->setBold(true);
            $sheet->getStyle("A$row:N$row")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('D1FAE5');
            
            $row++;
            if (count($setoran_data) > 0) {
                foreach ($setoran_data as $data) {
                    $sheet->setCellValue("A$row", $data['tanggal']);
                    $sheet->setCellValue("B$row", $data['employee_name'] ?? 'N/A');
                    $sheet->setCellValue("C$row", substr($data['jam_masuk'], 0, 5));
                    $sheet->setCellValue("D$row", substr($data['jam_keluar'], 0, 5));
                    $sheet->setCellValue("E$row", number_format($data['nomor_awal'], 2, ',', '.'));
                    $sheet->setCellValue("F$row", number_format($data['nomor_akhir'], 2, ',', '.'));
                    $sheet->setCellValue("G$row", number_format($data['jumlah_tera'] ?? 0, 2, ',', '.'));
                    $sheet->setCellValue("H$row", number_format($data['total_liter'], 2, ',', '.') . ' L');
                    $sheet->setCellValue("I$row", "Rp " . formatRupiahNumber($data['cash']));
                    $sheet->setCellValue("J$row", "Rp " . formatRupiahNumber($data['qris']));
                    $sheet->setCellValue("K$row", "Rp " . formatRupiahNumber($data['total_setoran']));
                    $sheet->setCellValue("L$row", "Rp " . formatRupiahNumber($data['total_pengeluaran']));
                    $sheet->setCellValue("M$row", "Rp " . formatRupiahNumber($data['total_pemasukan']));
                    $sheet->setCellValue("N$row", "Rp " . formatRupiahNumber($data['total_keseluruhan']));
                    $row++;
                }
            } else {
                $sheet->setCellValue("A$row", 'Tidak ada data setoran');
                $sheet->mergeCells("A$row:N$row");
                $sheet->getStyle("A$row")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $row++;
            }
            
            $setoran_end_row = $row - 1;
            $sheet->getStyle("A$setoran_start_row:N$setoran_end_row")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

            // ===== SECTION 3: DATA CASHFLOW =====
            $row += 2;
            $cashflow_start_row = $row;
            $sheet->setCellValue("A$row", 'DATA MANAJEMEN KAS');
            $sheet->mergeCells("A$row:F$row");
            $sheet->getStyle("A$row")->getFont()->setBold(true)->setSize(14);
            $sheet->getStyle("A$row")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F59E0B');
            $sheet->getStyle("A$row")->getFont()->getColor()->setRGB('FFFFFF');
            $sheet->getStyle("A$row")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            
            $row++;
            $headers = ['Tanggal', 'Deskripsi', 'Jenis', 'Kategori', 'Nominal'];
            $col = 'A';
            foreach ($headers as $header) {
                $sheet->setCellValue($col.$row, $header);
                $col++;
            }
            $sheet->getStyle("A$row:E$row")->getFont()->setBold(true);
            $sheet->getStyle("A$row:E$row")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FEF3C7');
            
            $row++;
            if (count($cashflow_data) > 0) {
                foreach ($cashflow_data as $data) {
                    $sheet->setCellValue("A$row", $data['tanggal']);
                    $sheet->setCellValue("B$row", $data['description']);
                    $sheet->setCellValue("C$row", $data['type']);
                    $sheet->setCellValue("D$row", $data['category']);
                    $sheet->setCellValue("E$row", "Rp " . formatRupiahNumber($data['amount']));
                    
                    // Color coding
                    if ($data['type'] === 'pemasukan' || $data['type'] === 'Pemasukan') {
                        $sheet->getStyle("E$row")->getFont()->getColor()->setRGB('059669');
                    } else {
                        $sheet->getStyle("E$row")->getFont()->getColor()->setRGB('DC2626');
                    }
                    $row++;
                }
            } else {
                $sheet->setCellValue("A$row", 'Tidak ada data cashflow');
                $sheet->mergeCells("A$row:E$row");
                $sheet->getStyle("A$row")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $row++;
            }
            
            $cashflow_end_row = $row - 1;
            $sheet->getStyle("A$cashflow_start_row:E$cashflow_end_row")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

            // Set column widths
            $sheet->getColumnDimension('A')->setWidth(15);
            $sheet->getColumnDimension('B')->setWidth(25);
            $sheet->getColumnDimension('C')->setWidth(12);
            $sheet->getColumnDimension('D')->setWidth(12);
            $sheet->getColumnDimension('E')->setWidth(15);
            $sheet->getColumnDimension('F')->setWidth(15);
            $sheet->getColumnDimension('G')->setWidth(13);
            $sheet->getColumnDimension('H')->setWidth(13);
            $sheet->getColumnDimension('I')->setWidth(15);
            $sheet->getColumnDimension('J')->setWidth(15);
            $sheet->getColumnDimension('K')->setWidth(15);
            $sheet->getColumnDimension('L')->setWidth(15);
            $sheet->getColumnDimension('M')->setWidth(15);
            $sheet->getColumnDimension('N')->setWidth(15);

            $sheetIndex++;
        }

        // Output file
        $filename = "Laporan_Lengkap_{$month_name}_{$year}.xlsx";
        
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
// EXPORT DASHBOARD TO PDF (MULTI-PAGE PER STORE)
// =========================================================
if ($action === 'export_dashboard_pdf') {
    try {
        $month = $_GET['month'] ?? date('m');
        $year = $_GET['year'] ?? date('Y');

        $months = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
        $month_name = $months[intval($month)];

        // Get all stores
        $stmt_stores = $pdo->query("SELECT id, store_name FROM stores ORDER BY store_name ASC");
        $stores = $stmt_stores->fetchAll(PDO::FETCH_ASSOC);

        // Buat PDF dengan orientasi LANDSCAPE
        $pdf = new TCPDF('L', PDF_UNIT, 'A4', true, 'UTF-8', false);
        
        // Set document information
        $pdf->SetCreator(PDF_CREATOR);
        $pdf->SetAuthor('Admin Panel POSM');
        $pdf->SetTitle("Laporan Lengkap $month_name $year");
        $pdf->SetSubject("Laporan Keuangan Per Store");

        // Set margins lebih kecil
        $pdf->SetMargins(10, 10, 10);
        $pdf->SetHeaderMargin(5);
        $pdf->SetFooterMargin(8);

        // Set auto page breaks
        $pdf->SetAutoPageBreak(TRUE, 10);

        // Set font
        $pdf->SetFont('dejavusans', '', 8);

        // Loop untuk setiap store
        foreach ($stores as $store) {
            $store_id = $store['id'];
            $store_name = $store['store_name'];

            // Query data untuk store ini
            $where_clause = "YEAR(s.tanggal) = ? AND MONTH(s.tanggal) = ? AND s.store_id = ?";
            $params = [$year, $month, $store_id];

            // Data Setoran
            $sql_setoran = "
                SELECT s.tanggal, st.store_name, e.employee_name, s.jam_masuk, s.jam_keluar,
                       s.nomor_awal, s.nomor_akhir, s.jumlah_tera, s.total_liter, s.cash, s.qris,
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
                WHERE YEAR(cfm.tanggal) = ? AND MONTH(cfm.tanggal) = ? AND cfm.store_id = ?
                ORDER BY cfm.tanggal DESC
            ";
            $stmt_cf = $pdo->prepare($sql_cashflow);
            $stmt_cf->execute([$year, $month, $store_id]);
            $cashflow_data = $stmt_cf->fetchAll(PDO::FETCH_ASSOC);

            // Hitung Summary untuk store ini
            $total_income_setoran = array_sum(array_column($setoran_data, 'total_setoran'));
            $total_income_pemasukan = array_sum(array_column($setoran_data, 'total_pemasukan'));
            $total_expense_setoran = array_sum(array_column($setoran_data, 'total_pengeluaran'));
            $total_liter = array_sum(array_column($setoran_data, 'total_liter'));
            
            $total_income_cf = 0;
            $total_expense_cf = 0;
            foreach ($cashflow_data as $cf) {
                if ($cf['type'] === 'pemasukan' || $cf['type'] === 'Pemasukan') {
                    $total_income_cf += $cf['amount'];
                } else {
                    $total_expense_cf += $cf['amount'];
                }
            }
            
            $total_income = $total_income_setoran + $total_income_pemasukan + $total_income_cf;
            $total_expense = $total_expense_setoran + $total_expense_cf;
            $balance = $total_income - $total_expense;

            // Add new page untuk store ini
            $pdf->AddPage();

            // Header - Compact
            $pdf->SetFont('dejavusans', 'B', 14);
            $pdf->Cell(0, 6, "LAPORAN KEUANGAN - $store_name | $month_name $year", 0, 1, 'C');
            $pdf->Ln(2);

            // Dashboard Wallet - COMPACT DALAM 1 BARIS
            $pdf->SetFont('dejavusans', 'B', 10);
            $pdf->SetFillColor(79, 70, 229);
            $pdf->SetTextColor(255, 255, 255);
            
            // Baris 1: Header Dashboard
            $pdf->Cell(0, 5, 'DASHBOARD WALLET', 1, 1, 'C', true);
            
            // Baris 2: Data dalam 4 kolom
            $pdf->SetFont('dejavusans', '', 8);
            $pdf->SetFillColor(229, 231, 235);
            $pdf->SetTextColor(0, 0, 0);
            
            $colWidth = 67; // 270 / 4 kolom
            
            // Kolom 1: Pemasukan
            $pdf->Cell($colWidth, 5, 'Pemasukan', 1, 0, 'L', true);
            // Kolom 2: Pengeluaran  
            $pdf->Cell($colWidth, 5, 'Pengeluaran', 1, 0, 'L', true);
            // Kolom 3: Saldo
            $pdf->Cell($colWidth, 5, 'Saldo Bersih', 1, 0, 'L', true);
            // Kolom 4: Liter
            $pdf->Cell($colWidth, 5, 'Total Liter', 1, 1, 'L', true);
            
            // Baris 3: Values
            $pdf->SetFont('dejavusans', 'B', 8);
            $pdf->SetTextColor(5, 150, 105);
            $pdf->Cell($colWidth, 5, "Rp " . formatRupiahNumber($total_income), 1, 0, 'R');
            
            $pdf->SetTextColor(220, 38, 38);
            $pdf->Cell($colWidth, 5, "Rp " . formatRupiahNumber($total_expense), 1, 0, 'R');
            
            $pdf->SetTextColor($balance >= 0 ? 5 : 220, $balance >= 0 ? 150 : 38, $balance >= 0 ? 105 : 38);
            $pdf->Cell($colWidth, 5, "Rp " . formatRupiahNumber($balance), 1, 0, 'R');
            
            $pdf->SetTextColor(0, 0, 0);
            $pdf->Cell($colWidth, 5, number_format($total_liter, 2, ',', '.') . ' L', 1, 1, 'R');
            
            $pdf->Ln(3);

            // Data Setoran - OPTIMIZED untuk landscape
            $pdf->SetFont('dejavusans', 'B', 10);
            $pdf->SetFillColor(16, 185, 129);
            $pdf->SetTextColor(255, 255, 255);
            $pdf->Cell(0, 5, 'DATA SETORAN HARIAN', 1, 1, 'C', true);
            
            if (count($setoran_data) > 0) {
                $pdf->SetFont('dejavusans', 'B', 7);
                $pdf->SetFillColor(209, 250, 229);
                $pdf->SetTextColor(0, 0, 0);
                
                // Column widths optimized untuk landscape A4 (270mm available)
                $pdf->Cell(20, 4, 'Tanggal', 1, 0, 'C', true);
                $pdf->Cell(25, 4, 'Karyawan', 1, 0, 'C', true);
                $pdf->Cell(18, 4, 'Jam', 1, 0, 'C', true);
                $pdf->Cell(18, 4, 'No Awal', 1, 0, 'C', true);
                $pdf->Cell(18, 4, 'No Akhir', 1, 0, 'C', true);
                $pdf->Cell(15, 4, 'Tera', 1, 0, 'C', true);
                $pdf->Cell(20, 4, 'Liter', 1, 0, 'C', true);
                $pdf->Cell(25, 4, 'Cash', 1, 0, 'C', true);
                $pdf->Cell(25, 4, 'QRIS', 1, 0, 'C', true);
                $pdf->Cell(28, 4, 'Setoran', 1, 0, 'C', true);
                $pdf->Cell(25, 4, 'Pengeluaran', 1, 0, 'C', true);
                $pdf->Cell(25, 4, 'Pemasukan', 1, 0, 'C', true);
                $pdf->Cell(28, 4, 'Total', 1, 1, 'C', true);
                
                $pdf->SetFont('dejavusans', '', 6);
                foreach ($setoran_data as $data) {
                    $pdf->Cell(20, 4, $data['tanggal'], 1, 0, 'C');
                    $pdf->Cell(25, 4, substr($data['employee_name'] ?? 'N/A', 0, 12), 1, 0, 'L');
                    $pdf->Cell(18, 4, substr($data['jam_masuk'], 0, 5) . '-' . substr($data['jam_keluar'], 0, 5), 1, 0, 'C');
                    $pdf->Cell(18, 4, number_format($data['nomor_awal'], 1), 1, 0, 'R');
                    $pdf->Cell(18, 4, number_format($data['nomor_akhir'], 1), 1, 0, 'R');
                    $pdf->Cell(15, 4, number_format($data['jumlah_tera'] ?? 0, 1), 1, 0, 'R');
                    $pdf->Cell(20, 4, number_format($data['total_liter'], 1), 1, 0, 'R');
                    $pdf->Cell(25, 4, formatRupiahNumber($data['cash']), 1, 0, 'R');
                    $pdf->Cell(25, 4, formatRupiahNumber($data['qris']), 1, 0, 'R');
                    $pdf->Cell(28, 4, formatRupiahNumber($data['total_setoran']), 1, 0, 'R');
                    $pdf->Cell(25, 4, formatRupiahNumber($data['total_pengeluaran']), 1, 0, 'R');
                    $pdf->Cell(25, 4, formatRupiahNumber($data['total_pemasukan']), 1, 0, 'R');
                    $pdf->Cell(28, 4, formatRupiahNumber($data['total_keseluruhan']), 1, 1, 'R');
                }
            } else {
                $pdf->SetFont('dejavusans', 'I', 8);
                $pdf->Cell(0, 5, 'Tidak ada data setoran', 1, 1, 'C');
            }
            
            $pdf->Ln(3);

            // Data Cashflow
            $pdf->SetFont('dejavusans', 'B', 12);
            $pdf->SetFillColor(245, 158, 11);
            $pdf->SetTextColor(255, 255, 255);
            $pdf->Cell(0, 8, 'DATA MANAJEMEN KAS', 1, 1, 'C', true);
            
            if (count($cashflow_data) > 0) {
                $pdf->SetFont('dejavusans', 'B', 8);
                $pdf->SetFillColor(254, 243, 199);
                $pdf->SetTextColor(0, 0, 0);
                $pdf->Cell(25, 6, 'Tanggal', 1, 0, 'C', true);
                $pdf->Cell(60, 6, 'Deskripsi', 1, 0, 'C', true);
                $pdf->Cell(30, 6, 'Jenis', 1, 0, 'C', true);
                $pdf->Cell(30, 6, 'Kategori', 1, 0, 'C', true);
                $pdf->Cell(35, 6, 'Nominal', 1, 1, 'C', true);
                
                $pdf->SetFont('dejavusans', '', 7);
                foreach ($cashflow_data as $data) {
                    $pdf->Cell(25, 5, $data['tanggal'], 1, 0, 'C');
                    $pdf->Cell(60, 5, substr($data['description'], 0, 30), 1, 0, 'L');
                    $pdf->Cell(30, 5, $data['type'], 1, 0, 'C');
                    $pdf->Cell(30, 5, $data['category'], 1, 0, 'C');
                    
                    if ($data['type'] === 'pemasukan' || $data['type'] === 'Pemasukan') {
                        $pdf->SetTextColor(5, 150, 105);
                    } else {
                        $pdf->SetTextColor(220, 38, 38);
                    }
                    $pdf->Cell(35, 5, formatRupiahNumber($data['amount']), 1, 1, 'R');
                    $pdf->SetTextColor(0, 0, 0);
                }
            } else {
                $pdf->SetFont('dejavusans', 'I', 10);
                $pdf->Cell(0, 7, 'Tidak ada data cashflow', 1, 1, 'C');
            }
        }

        // Output PDF
        $filename = "Laporan_Lengkap_{$month_name}_{$year}.pdf";
        $pdf->Output($filename, 'D');
        exit;
        
    } catch (Exception $e) {
        die("Error: " . $e->getMessage());
    }
}

// Keep original export for backward compatibility
include 'export_old.php';
?>
