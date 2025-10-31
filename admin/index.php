<?php
session_start();
require_once '../config/config.php';
require_once '../config/security.php';

// Cek sesi login
if (!isset($_SESSION['user_id'])) {
    header('Location: ../config/login.php');
    exit;
}

// Check session timeout
if (!checkSessionTimeout()) {
    session_unset();
    session_destroy();
    header('Location: ../config/login.php?expired=1');
    exit;
}

// Generate CSRF token for this session
$csrf_token = generateCSRFToken();

// Ambil data filter dari URL atau set default ke bulan/tahun saat ini
$current_month = date('m');
$current_year = date('Y');

$months_data = [
    '01' => 'Januari', '02' => 'Februari', '03' => 'Maret', '04' => 'April',
    '05' => 'Mei', '06' => 'Juni', '07' => 'Juli', '08' => 'Agustus',
    '09' => 'September', '10' => 'Oktober', '11' => 'November', '12' => 'Desember'
];

// Menghasilkan tahun dari 3 tahun ke belakang sampai tahun depan
$years_data = range($current_year - 2, $current_year + 1);

$selected_month = $_GET['month'] ?? $current_month;
$selected_year = $_GET['year'] ?? $current_year;
$selected_employee_id = $_GET['employee_id'] ?? '';
$selected_store_id = $_GET['store_id'] ?? ''; 

?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel Admin | Setoran Harian</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Chart.js for Dashboard Analytics -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <!--<script>console.log = function() {};</script>-->
    <link rel="stylesheet" href="../assets/css/admin.css">
    <script>
        // Initialize CSRF Token from PHP
        const CSRF_TOKEN = '<?php echo $csrf_token; ?>';
    </script>
</head>

<body class="bg-gray-100">
    <div class="min-h-screen flex flex-col">

        <header class="bg-white shadow-lg sticky top-0 z-10 border-b border-gray-200">
            <div class="content-wrapper px-4 sm:px-6 lg:px-8 py-4 flex justify-between items-center">
                <h1 class="text-3xl font-extrabold text-indigo-700">Admin Dashboard</h1>
                <div class="flex items-center space-x-4">
                    <span class="text-gray-700 text-sm">Masuk sebagai: <span class="font-semibold">
                            <?php echo htmlspecialchars($_SESSION['username']); ?>
                        </span></span>

                    <!-- TOMBOL REFRESH BARU -->
                    <button id="refreshBtn"
                        class="bg-blue-500 hover:bg-blue-600 text-white px-3 py-1 rounded-full text-sm font-medium transition duration-200 flex items-center"
                        data-tooltip="Muat ulang data terbaru" title="Refresh Halaman">
                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                        </svg>
                        Refresh
                    </button>

                    <button id="logoutBtn"
                        class="bg-red-500 hover:bg-red-600 text-white px-3 py-1 rounded-full text-sm font-medium transition duration-200"
                        data-tooltip="Keluar dari sistem">
                        <svg class="w-4 h-4 inline-block mr-1 -mt-0.5" fill="none" stroke="currentColor"
                            viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1">
                            </path>
                        </svg>
                        Logout
                    </button>
                </div>
            </div>

            <!-- Navigation menu tetap sama -->
            <nav class="bg-gray-50 border-t border-gray-200">
                <div class="content-wrapper px-4 sm:px-6 lg:px-8">
                    <div class="flex space-x-6">
                        <button class="tab-button py-3 px-1 text-sm text-gray-700 hover:text-indigo-600 active-tab"
                            data-tab="dashboard">Dashboard Wallet</button>
                        <button class="tab-button py-3 px-1 text-sm text-gray-700 hover:text-indigo-600"
                            data-tab="setoran">History Setoran</button>
                        <button class="tab-button py-3 px-1 text-sm text-gray-700 hover:text-indigo-600"
                            data-tab="cashflow">Manajemen Kas</button>
                        <button class="tab-button py-3 px-1 text-sm text-gray-700 hover:text-indigo-600"
                            data-tab="stores">Manajemen Store</button>
                        <button class="tab-button py-3 px-1 text-sm text-gray-700 hover:text-indigo-600"
                            data-tab="employees">Manajemen Karyawan</button>
                        <button class="tab-button py-3 px-1 text-sm text-gray-700 hover:text-indigo-600"
                            data-tab="email-settings">⚙️ Email Settings</button>
                    </div>
                </div>
            </nav>
        </header>

        <main class="w-full px-4 sm:px-6 lg:px-8 py-8 flex-grow">
            <div class="content-wrapper">

                <div id="tab-dashboard" class="tab-content">
                    <h2 class="text-2xl font-bold mb-6 text-gray-800">Dashboard Wallet & Cashflow</h2>

                    <form id="filterFormDashboard"
                        class="bg-white p-5 rounded-xl shadow-md mb-8 flex flex-wrap gap-4 items-end">
                        <div class="w-full sm:w-auto">
                            <label for="filter_month_dashboard"
                                class="block text-xs font-medium text-gray-500 mb-1">Bulan</label>
                            <select id="filter_month_dashboard" name="month"
                                class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 p-2 text-sm">
                                <?php foreach ($months_data as $key => $value) : ?>
                                <option value="<?php echo $key; ?>" <?php echo ($selected_month==$key) ? 'selected' : ''
                                    ; ?>>
                                    <?php echo $value; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="w-full sm:w-auto">
                            <label for="filter_year_dashboard"
                                class="block text-xs font-medium text-gray-500 mb-1">Tahun</label>
                            <select id="filter_year_dashboard" name="year"
                                class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 p-2 text-sm">
                                <?php foreach ($years_data as $year) : ?>
                                <option value="<?php echo $year; ?>" <?php echo ($selected_year==$year) ? 'selected'
                                    : '' ; ?>>
                                    <?php echo $year; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit"
                            class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2.5 rounded-lg shadow-md text-sm transition duration-200"
                            data-tooltip="Terapkan filter yang dipilih">
                            Terapkan Filter
                        </button>
                        <button type="button" id="resetFilterBtnDashboard"
                            class="bg-gray-300 hover:bg-gray-400 text-gray-800 px-4 py-2.5 rounded-lg shadow-md text-sm transition duration-200"
                            data-tooltip="Reset filter ke bulan ini">
                            Reset
                        </button>
                        <div class="ml-auto flex gap-2">
                            <button type="button" onclick="exportDashboard('pdf')"
                                class="bg-red-600 hover:bg-red-700 text-white px-4 py-2.5 rounded-lg shadow-md text-sm transition duration-200 flex items-center"
                                data-tooltip="Export laporan ke format PDF">
                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z">
                                    </path>
                                </svg>
                                Export PDF
                            </button>
                            <button type="button" onclick="exportDashboard('excel')"
                                class="bg-green-600 hover:bg-green-700 text-white px-4 py-2.5 rounded-lg shadow-md text-sm transition duration-200 flex items-center"
                                data-tooltip="Export laporan ke format Excel">
                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z">
                                    </path>
                                </svg>
                                Export Excel
                            </button>
                        </div>
                    </form>

                    <div class="bg-gradient-to-r from-indigo-600 to-blue-600 text-white p-6 rounded-xl shadow-xl mb-8">
                        <h3 class="text-xl font-bold mb-4">Wallet Utama (All Stores)</h3>
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                            <div class="bg-white bg-opacity-20 p-4 rounded-lg">
                                <p class="text-sm opacity-90">Total Pemasukan</p>
                                <p id="wallet_all_income" class="text-2xl font-bold mt-1">Rp 0</p>
                            </div>
                            <div class="bg-white bg-opacity-20 p-4 rounded-lg">
                                <p class="text-sm opacity-90">Total Pengeluaran</p>
                                <p id="wallet_all_expense" class="text-2xl font-bold mt-1">Rp 0</p>
                            </div>
                            <div class="bg-white bg-opacity-20 p-4 rounded-lg">
                                <p class="text-sm opacity-90">Saldo Bersih</p>
                                <p id="wallet_all_balance" class="text-2xl font-bold mt-1">Rp 0</p>
                            </div>
                            <div class="bg-white bg-opacity-20 p-4 rounded-lg">
                                <p class="text-sm opacity-90">Total Liter Terjual</p>
                                <p id="wallet_all_liter" class="text-2xl font-bold mt-1">0 L</p>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white p-6 rounded-xl shadow-md">
                        <h3 class="text-lg font-bold mb-4 text-gray-800">Wallet Per Store</h3>
                        <div id="wallet_per_store" class="space-y-4"></div>
                    </div>

                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8 mt-8">
                        <div class="bg-white p-6 rounded-xl shadow-md">
                            <h3 class="text-lg font-bold mb-4 text-gray-800">Breakdown Pengeluaran</h3>
                            <div id="expense_breakdown" class="space-y-2"></div>
                        </div>
                        <div class="bg-white p-6 rounded-xl shadow-md">
                            <h3 class="text-lg font-bold mb-4 text-gray-800">Breakdown Pemasukan</h3>
                            <div id="income_breakdown" class="space-y-2"></div>
                        </div>
                    </div>

                    <div class="mb-6">
                        <div id="bbmSummaryContainer">
                            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                                <div class="bg-blue-600 px-4 py-3">
                                    <h3 class="text-lg font-semibold text-white flex items-center gap-2">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                                        </svg>
                                        Laporan Pembelian BBM per Store
                                    </h3>
                                </div>
                                <div class="overflow-x-auto">
                                    <table class="min-w-full divide-y divide-gray-200">
                                        <thead class="bg-gray-50">
                                            <tr>
                                                <th
                                                    class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                    Store</th>
                                                <th
                                                    class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                    Total Drigen</th>
                                                <th
                                                    class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                    Harga Pokok</th>
                                                <th
                                                    class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                    Pajak & Beban</th>
                                                <th
                                                    class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                    Total Pembelian</th>
                                            </tr>
                                        </thead>
                                        <tbody id="bbmTableBody" class="bg-white divide-y divide-gray-200">
                                            <!-- Data akan diisi otomatis oleh JavaScript -->
                                        </tbody>
                                        <tfoot class="bg-gray-50 font-semibold border-t-2 border-gray-200">
                                            <tr>
                                                <td class="px-4 py-3 whitespace-nowrap text-gray-900">TOTAL</td>
                                                <td class="px-4 py-3 whitespace-nowrap text-center text-gray-900">
                                                    <span
                                                        class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-blue-600 text-white">
                                                        0 drigen
                                                    </span>
                                                </td>
                                                <td class="px-4 py-3 whitespace-nowrap text-right text-blue-700">
                                                    Rp 0
                                                </td>
                                                <td class="px-4 py-3 whitespace-nowrap text-right text-orange-700">
                                                    Rp 0
                                                </td>
                                                <td class="px-4 py-3 whitespace-nowrap text-right text-green-700">
                                                    Rp 0
                                                </td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div id="tab-setoran" class="tab-content hidden">
                    <h2 class="text-2xl font-bold mb-6 text-gray-800">Riwayat Setoran Harian</h2>

                    <!-- FILTER FORM -->
                    <form id="filterFormSetoran"
                        class="bg-white p-5 rounded-xl shadow-md mb-8 flex flex-wrap gap-4 items-end">
                        <div class="w-full sm:w-auto flex-grow">
                            <label for="filter_store_id_setoran"
                                class="block text-xs font-medium text-gray-500 mb-1">Store</label>
                            <select id="filter_store_id_setoran" name="store_id"
                                class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 p-2 text-sm">
                                <option value="">Semua Store</option>
                            </select>
                        </div>
                        <div class="w-full sm:w-auto flex-grow">
                            <label for="filter_employee_id_setoran"
                                class="block text-xs font-medium text-gray-500 mb-1">Karyawan</label>
                            <select id="filter_employee_id_setoran" name="employee_id"
                                class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 p-2 text-sm">
                                <option value="">Semua Karyawan</option>
                            </select>
                        </div>
                        <div class="w-full sm:w-auto">
                            <label for="filter_month_setoran"
                                class="block text-xs font-medium text-gray-500 mb-1">Bulan</label>
                            <select id="filter_month_setoran" name="month"
                                class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 p-2 text-sm">
                                <?php foreach ($months_data as $key => $value) : ?>
                                <option value="<?php echo $key; ?>" <?php echo ($selected_month==$key) ? 'selected' : ''
                                    ; ?>>
                                    <?php echo $value; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="w-full sm:w-auto">
                            <label for="filter_year_setoran"
                                class="block text-xs font-medium text-gray-500 mb-1">Tahun</label>
                            <select id="filter_year_setoran" name="year"
                                class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 p-2 text-sm">
                                <?php foreach ($years_data as $year) : ?>
                                <option value="<?php echo $year; ?>" <?php echo ($selected_year==$year) ? 'selected'
                                    : '' ; ?>>
                                    <?php echo $year; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit"
                            class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2.5 rounded-lg shadow-md text-sm transition duration-200">
                            Terapkan Filter
                        </button>
                        <button type="button" id="resetFilterBtnSetoran"
                            class="bg-gray-300 hover:bg-gray-400 text-gray-800 px-4 py-2.5 rounded-lg shadow-md text-sm transition duration-200">
                            Reset
                        </button>
                    </form>

                    <!-- SUMMARY -->
                    <div id="summaryCardSetoran" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-6 mb-8">
                        <div class="bg-white p-5 rounded-xl shadow-md border-l-4 border-indigo-500">
                            <p class="text-sm font-medium text-gray-500">Total Liter</p>
                            <p id="sum_liter" class="text-2xl font-bold text-indigo-700 mt-1">...</p>
                        </div>
                        <div class="bg-white p-5 rounded-xl shadow-md border-l-4 border-green-500">
                            <p class="text-sm font-medium text-gray-500">Total Cash</p>
                            <p id="sum_cash" class="text-2xl font-bold text-green-700 mt-1">...</p>
                        </div>
                        <div class="bg-white p-5 rounded-xl shadow-md border-l-4 border-blue-500">
                            <p class="text-sm font-medium text-gray-500">Total QRIS</p>
                            <p id="sum_qris" class="text-2xl font-bold text-blue-700 mt-1">...</p>
                        </div>
                        <div class="bg-white p-5 rounded-xl shadow-md border-l-4 border-red-500">
                            <p class="text-sm font-medium text-gray-500">Total Pengeluaran</p>
                            <p id="sum_pengeluaran" class="text-2xl font-bold text-red-700 mt-1">...</p>
                        </div>
                        <div class="bg-white p-5 rounded-xl shadow-md border-l-4 border-orange-500">
                            <p class="text-sm font-medium text-gray-500">Total Keseluruhan</p>
                            <p id="sum_total_keseluruhan" class="text-2xl font-bold text-orange-700 mt-1">...</p>
                        </div>
                    </div>

                    <!-- TABEL SETORAN LENGKAP -->
                    <div class="bg-white p-6 rounded-xl shadow-md overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50 text-gray-700">
                                <tr>
                                    <th class="px-4 py-2 text-left font-semibold">Tanggal</th>
                                    <th class="px-4 py-2 text-left font-semibold">Karyawan</th>
                                    <th class="px-4 py-2 text-left font-semibold">Store</th>
                                    <th class="px-4 py-2 text-center font-semibold">Jam Masuk</th>
                                    <th class="px-4 py-2 text-center font-semibold">Jam Keluar</th>
                                    <th class="px-4 py-2 text-right font-semibold">No Awal</th>
                                    <th class="px-4 py-2 text-right font-semibold">No Akhir</th>
                                    <th class="px-4 py-2 text-right font-semibold">Jumlah Tera</th>
                                    <th class="px-4 py-2 text-right font-semibold">Total Liter</th>
                                    <th class="px-4 py-2 text-right font-semibold">QRIS</th>
                                    <th class="px-4 py-2 text-right font-semibold">Cash</th>
                                    <th class="px-4 py-2 text-right font-semibold">Total Setoran</th>
                                    <th class="px-4 py-2 text-right font-semibold">Total Pengeluaran</th>
                                    <th class="px-4 py-2 text-right font-semibold">Total Pemasukan</th>
                                    <th class="px-4 py-2 text-right font-semibold">Total Keseluruhan</th>
                                    <th class="px-4 py-2 text-center font-semibold">Aksi</th>
                                </tr>
                            </thead>
                            <tbody id="setoranTableBody" class="bg-white divide-y divide-gray-200">
                                <tr>
                                    <td colspan="16" class="text-center py-6 text-gray-500">Memuat data...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>


                    <div id="detailModal"
                        class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50 p-4">
                        <div class="bg-white w-full max-w-4xl rounded-2xl shadow-2xl overflow-hidden">
                            <!-- Header -->
                            <div class="bg-gradient-to-r from-blue-600 to-blue-700 px-6 py-4">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center space-x-3">
                                        <div class="bg-white bg-opacity-20 p-2 rounded-lg">
                                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor"
                                                viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z">
                                                </path>
                                            </svg>
                                        </div>
                                        <div>
                                            <h3 class="text-xl font-bold text-white">Detail Setoran</h3>
                                            <p class="text-blue-100 text-sm">Informasi lengkap setoran harian</p>
                                        </div>
                                    </div>
                                    <button onclick="hideModal('detailModal')"
                                        class="text-white hover:bg-white hover:bg-opacity-20 p-2 rounded-lg transition-all duration-200">
                                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M6 18L18 6M6 6l12 12"></path>
                                        </svg>
                                    </button>
                                </div>
                            </div>

                            <!-- Loading -->
                            <div id="detailLoading" class="p-8 text-center">
                                <div
                                    class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600 mx-auto mb-4">
                                </div>
                                <p class="text-gray-600 font-medium">Memuat rincian setoran...</p>
                            </div>

                            <!-- Content -->
                            <div id="detailContent" class="hidden">
                                <div class="p-6 space-y-6">
                                    <!-- Informasi Utama -->
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                        <!-- Data Karyawan & Toko -->
                                        <div class="bg-blue-50 rounded-xl p-4 border border-blue-100">
                                            <h4 class="font-semibold text-blue-800 mb-3 flex items-center gap-2">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor"
                                                    viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="2"
                                                        d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z">
                                                    </path>
                                                </svg>
                                                Data Karyawan & Toko
                                            </h4>
                                            <div class="space-y-2 text-sm">
                                                <div class="flex justify-between">
                                                    <span class="text-gray-600">ID Setoran:</span>
                                                    <span id="detail_id" class="font-semibold text-gray-800"></span>
                                                </div>
                                                <div class="flex justify-between">
                                                    <span class="text-gray-600">Tanggal:</span>
                                                    <span id="detail_tanggal"
                                                        class="font-semibold text-gray-800"></span>
                                                </div>
                                                <div class="flex justify-between">
                                                    <span class="text-gray-600">Karyawan:</span>
                                                    <span id="detail_employee"
                                                        class="font-semibold text-gray-800"></span>
                                                </div>
                                                <div class="flex justify-between">
                                                    <span class="text-gray-600">Store:</span>
                                                    <span id="detail_store" class="font-semibold text-gray-800"></span>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Jam Kerja -->
                                        <div class="bg-purple-50 rounded-xl p-4 border border-purple-100">
                                            <h4 class="font-semibold text-purple-800 mb-3 flex items-center gap-2">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor"
                                                    viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="2"
                                                        d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                                </svg>
                                                Jam Kerja
                                            </h4>
                                            <div class="space-y-2 text-sm">
                                                <div class="flex justify-between">
                                                    <span class="text-gray-600">Jam Masuk:</span>
                                                    <span id="detail_jam_masuk"
                                                        class="font-semibold text-gray-800"></span>
                                                </div>
                                                <div class="flex justify-between">
                                                    <span class="text-gray-600">Jam Keluar:</span>
                                                    <span id="detail_jam_keluar"
                                                        class="font-semibold text-gray-800"></span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Data Meter & Setoran -->
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                        <!-- Data Meter -->
                                        <div class="bg-green-50 rounded-xl p-4 border border-green-100">
                                            <h4 class="font-semibold text-green-800 mb-3 flex items-center gap-2">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor"
                                                    viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="2"
                                                        d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z">
                                                    </path>
                                                </svg>
                                                Data Meter
                                            </h4>
                                            <div class="space-y-2 text-sm">
                                                <div class="flex justify-between">
                                                    <span class="text-gray-600">No. Awal:</span>
                                                    <span id="detail_nomor_awal"
                                                        class="font-semibold text-gray-800"></span>
                                                </div>
                                                <div class="flex justify-between">
                                                    <span class="text-gray-600">No. Akhir:</span>
                                                    <span id="detail_nomor_akhir"
                                                        class="font-semibold text-gray-800"></span>
                                                </div>
                                                <div class="flex justify-between">
                                                    <span class="text-gray-600">Jumlah Tera:</span>
                                                    <span id="detail_jumlah_tera"
                                                        class="font-semibold text-orange-600"></span>
                                                </div>
                                                <div class="flex justify-between border-t pt-2">
                                                    <span class="text-gray-600 font-medium">Total Liter:</span>
                                                    <span id="detail_total_liter"
                                                        class="font-semibold text-green-700"></span>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Setoran -->
                                        <div class="bg-yellow-50 rounded-xl p-4 border border-yellow-100">
                                            <h4 class="font-semibold text-yellow-800 mb-3 flex items-center gap-2">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor"
                                                    viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="2"
                                                        d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1">
                                                    </path>
                                                </svg>
                                                Setoran
                                            </h4>
                                            <div class="space-y-2 text-sm">
                                                <div class="flex justify-between">
                                                    <span class="text-gray-600">Cash:</span>
                                                    <span id="detail_cash" class="font-semibold text-gray-800"></span>
                                                </div>
                                                <div class="flex justify-between">
                                                    <span class="text-gray-600">QRIS:</span>
                                                    <span id="detail_qris" class="font-semibold text-gray-800"></span>
                                                </div>
                                                <div class="flex justify-between border-t pt-2">
                                                    <span class="text-gray-600 font-medium">Total Setoran:</span>
                                                    <span id="detail_total_setoran"
                                                        class="font-bold text-yellow-700"></span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Pengeluaran & Pemasukan -->
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                        <!-- Pengeluaran -->
                                        <div class="bg-red-50 rounded-xl p-4 border border-red-100">
                                            <h4 class="font-semibold text-red-800 mb-3 flex items-center gap-2">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor"
                                                    viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3"></path>
                                                </svg>
                                                Pengeluaran (PU)
                                            </h4>
                                            <div class="space-y-2 text-sm mb-3">
                                                <div class="flex justify-between">
                                                    <span class="text-gray-600">Total Pengeluaran:</span>
                                                    <span id="detail_total_pengeluaran"
                                                        class="font-bold text-red-700"></span>
                                                </div>
                                            </div>
                                            <ul id="detail_pengeluaran_list" class="space-y-2 max-h-32 overflow-y-auto">
                                            </ul>
                                        </div>

                                        <!-- Pemasukan -->
                                        <div class="bg-emerald-50 rounded-xl p-4 border border-emerald-100">
                                            <h4 class="font-semibold text-emerald-800 mb-3 flex items-center gap-2">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor"
                                                    viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                                                </svg>
                                                Pemasukan (PU)
                                            </h4>
                                            <div class="space-y-2 text-sm mb-3">
                                                <div class="flex justify-between">
                                                    <span class="text-gray-600">Total Pemasukan:</span>
                                                    <span id="detail_total_pemasukan"
                                                        class="font-bold text-emerald-700"></span>
                                                </div>
                                            </div>
                                            <ul id="detail_pemasukan_list" class="space-y-2 max-h-32 overflow-y-auto">
                                            </ul>
                                        </div>
                                    </div>

                                    <!-- Total Keseluruhan -->
                                    <div class="bg-gradient-to-r from-gray-900 to-gray-800 rounded-xl p-6 text-white">
                                        <div class="flex items-center justify-between">
                                            <div class="flex items-center space-x-3">
                                                <div class="bg-white bg-opacity-20 p-3 rounded-xl">
                                                    <svg class="w-6 h-6" fill="none" stroke="currentColor"
                                                        viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            stroke-width="2"
                                                            d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z">
                                                        </path>
                                                    </svg>
                                                </div>
                                                <div>
                                                    <h4 class="font-bold text-lg">Total Keseluruhan</h4>
                                                    <p class="text-gray-300 text-sm">Setoran + Pemasukan - Pengeluaran
                                                    </p>
                                                </div>
                                            </div>
                                            <div class="text-right">
                                                <div id="detail_total_keseluruhan"
                                                    class="text-2xl font-bold text-white">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div id="tab-cashflow" class="tab-content hidden">
                    <h2 class="text-2xl font-bold mb-6 text-gray-800">Manajemen Kas: Pemasukan & Pengeluaran</h2>

                    <!-- Filter Form -->
                    <form id="filterFormCashflow"
                        class="bg-white p-5 rounded-xl shadow-md mb-8 flex flex-wrap gap-4 items-end">
                        <div class="w-full sm:w-auto">
                            <label for="filter_month_cashflow"
                                class="block text-xs font-medium text-gray-500 mb-1">Bulan</label>
                            <select id="filter_month_cashflow" name="month"
                                class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 p-2 text-sm">
                                <?php foreach ($months_data as $key => $value) : ?>
                                <option value="<?php echo $key; ?>" <?php echo ($selected_month==$key) ? 'selected' : ''
                                    ; ?>>
                                    <?php echo $value; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="w-full sm:w-auto">
                            <label for="filter_year_cashflow"
                                class="block text-xs font-medium text-gray-500 mb-1">Tahun</label>
                            <select id="filter_year_cashflow" name="year"
                                class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 p-2 text-sm">
                                <?php foreach ($years_data as $year) : ?>
                                <option value="<?php echo $year; ?>" <?php echo ($selected_year==$year) ? 'selected'
                                    : '' ; ?>>
                                    <?php echo $year; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-4 md:mb-0 md:mr-3">
                            <label for="filter_store_cashflow" class="block text-sm font-medium text-gray-700">Filter
                                Store</label>
                            <select id="filter_store_cashflow" name="store_id"
                                class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md">
                                <option value="">Semua Store</option>
                                <?php
                require_once __DIR__ . '/../config/config.php';
                $stores = $pdo->query('SELECT id, store_name FROM stores')->fetchAll();
                foreach ($stores as $s) {
                    echo "<option value='{$s['id']}'>" . htmlspecialchars($s['store_name']) . "</option>";
                }
                ?>
                            </select>
                        </div>
                        <button type="submit"
                            class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2.5 rounded-lg shadow-md text-sm transition duration-200">
                            Terapkan Filter
                        </button>
                    </form>

                    <!-- Action Buttons -->
                    <div class="flex justify-end gap-3 mb-6">
                        <button onclick="showCashFlowModal()"
                            class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg shadow-md text-sm transition duration-200 flex items-center">
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                            </svg>
                            Tambah Transaksi Kas
                        </button>
                        <button onclick="exportCashflow('excel')"
                            class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg shadow-md text-sm transition duration-200 flex items-center">
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
                            </svg>
                            Export Excel
                        </button>
                    </div>

                    <!-- Data Table -->
                    <div class="bg-white p-6 rounded-xl shadow-md">
                        <h3 class="text-lg font-bold mb-4 text-gray-800">Daftar Transaksi Kas</h3>

                        <!-- Summary Cards -->
                        <div class="grid grid-cols-3 gap-4 mb-4 text-center">
                            <div class="p-3 bg-green-100 rounded-lg">
                                <p class="text-sm text-green-700">Total Pemasukan</p>
                                <p id="cashflow_total_income" class="text-xl font-bold text-green-700">Rp 0</p>
                            </div>
                            <div class="p-3 bg-red-100 rounded-lg">
                                <p class="text-sm text-red-700">Total Pengeluaran</p>
                                <p id="cashflow_total_expense" class="text-xl font-bold text-red-700">Rp 0</p>
                            </div>
                            <div class="p-3 bg-indigo-100 rounded-lg">
                                <p class="text-sm text-indigo-700">Saldo Akhir</p>
                                <p id="cashflow_balance" class="text-xl font-bold text-indigo-700">Rp 0</p>
                            </div>
                        </div>

                        <!-- Table -->
                        <div class="overflow-x-auto">
                            <table class="min-w-full border-collapse divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th
                                            class="px-4 py-3 text-xs font-medium text-gray-500 uppercase tracking-wider text-left w-[100px]">
                                            Tgl</th>
                                        <th
                                            class="px-4 py-3 text-xs font-medium text-gray-500 uppercase tracking-wider text-left w-[160px]">
                                            Store</th>
                                        <th
                                            class="px-4 py-3 text-xs font-medium text-gray-500 uppercase tracking-wider text-left">
                                            Deskripsi</th>
                                        <th
                                            class="px-4 py-3 text-xs font-medium text-gray-500 uppercase tracking-wider text-center w-[120px]">
                                            Jenis</th>
                                        <th
                                            class="px-4 py-3 text-xs font-medium text-gray-500 uppercase tracking-wider text-right w-[140px]">
                                            Nominal</th>
                                        <th
                                            class="px-4 py-3 text-xs font-medium text-gray-500 uppercase tracking-wider text-center w-[100px]">
                                            Aksi</th>
                                    </tr>
                                </thead>
                                <tbody id="cashflowTableBody" class="bg-white divide-y divide-gray-200 text-sm">
                                    <tr>
                                        <td colspan="6" class="text-center py-4 text-gray-400">Silakan terapkan filter
                                            untuk memuat data.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Modal Cashflow -->
                    <div id="modalCashflow"
                        class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
                        <div class="relative top-10 mx-auto p-4 w-full max-w-md">
                            <div class="bg-white rounded-lg shadow-xl">
                                <!-- Header -->
                                <div class="px-6 py-4 border-b border-gray-200">
                                    <h3 class="text-lg font-semibold text-gray-900" id="modalCashflowTitle">
                                        Tambah Transaksi Kas
                                    </h3>
                                </div>

                                <!-- Form Content -->
                                <div class="px-6 py-4">
                                    <form id="cashflowForm" method="POST" action="../config/api.php">
                                        <input type="hidden" id="cashflow_id" name="id">

                                        <!-- Message Area -->
                                        <div id="cashflowMessage" class="hidden mb-4 p-2 rounded text-sm"></div>

                                        <!-- Tanggal -->
                                        <div class="mb-4">
                                            <label for="cashflow_date"
                                                class="block text-sm font-medium text-gray-700 mb-1">Tanggal</label>
                                            <input type="date" id="cashflow_date" name="tanggal" required
                                                class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                                        </div>

                                        <!-- Store -->
                                        <div id="storeSelectContainer" class="mb-4">
                                            <label for="cashflow_store_id"
                                                class="block text-sm font-medium text-gray-700 mb-1">Store</label>
                                            <select id="cashflow_store_id" name="store_id"
                                                class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                                                <option value="">Pilih Store</option>
                                                <?php
                                require_once __DIR__ . '/../config/config.php';
                                $stores = $pdo->query('SELECT id, store_name FROM stores')->fetchAll();
                                foreach ($stores as $s) {
                                    echo "<option value='{$s['id']}'>" . htmlspecialchars($s['store_name']) . "</option>";
                                }
                                ?>
                                            </select>
                                        </div>

                                        <!-- Jenis Transaksi -->
                                        <div class="mb-4">
                                            <label for="cashflow_type"
                                                class="block text-sm font-medium text-gray-700 mb-1">Jenis
                                                Transaksi</label>
                                            <select id="cashflow_type" name="type" required
                                                class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                                                <option value="pemasukan">Pemasukan</option>
                                                <option value="pengeluaran">Pengeluaran</option>
                                            </select>
                                        </div>

                                        <!-- Kategori -->
                                        <div class="mb-4">
                                            <label for="cashflow_category"
                                                class="block text-sm font-medium text-gray-700 mb-1">Kategori</label>
                                            <select id="cashflow_category" name="category" required
                                                class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                                                <option value="">-- Pilih Kategori --</option>
                                                <option value="bbm">BBM</option>
                                                <option value="operasional">Operasional</option>
                                                <option value="penjualan">Penjualan</option>
                                                <option value="gaji">Gaji</option>
                                                <option value="lainnya">Lainnya</option>
                                            </select>
                                        </div>

                                        <!-- Deskripsi -->
                                        <div class="mb-4">
                                            <label for="cashflow_description"
                                                class="block text-sm font-medium text-gray-700 mb-1">Deskripsi</label>
                                            <input type="text" id="cashflow_description" name="description" required
                                                class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                                placeholder="Masukkan deskripsi transaksi">
                                        </div>

                                        <!-- Nominal -->
                                        <div class="mb-4">
                                            <label for="cashflow_amount"
                                                class="block text-sm font-medium text-gray-700 mb-1">Nominal
                                                (Rp)</label>
                                            <input type="number" id="cashflow_amount" name="amount" required min="1"
                                                class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                                placeholder="0">
                                        </div>

                                        <!-- SIMULATOR BBM -->
                                        <div id="bbmSimulator" class="hidden border-t border-gray-200 pt-4 mt-4">
                                            <h4
                                                class="text-md font-semibold text-gray-800 mb-3 flex items-center gap-2">
                                                <svg class="w-5 h-5 text-orange-500" fill="none" stroke="currentColor"
                                                    viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="2"
                                                        d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z">
                                                    </path>
                                                </svg>
                                                Simulasi Pembelian BBM
                                            </h4>

                                            <div class="space-y-3">
                                                <div>
                                                    <label class="block text-sm font-medium text-gray-700 mb-1">Harga
                                                        per Drigen (Rp)</label>
                                                    <input type="number" id="bbm_harga"
                                                        class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                                        value="340000">
                                                </div>

                                                <div>
                                                    <label class="block text-sm font-medium text-gray-700 mb-1">Pajak
                                                        per Drigen (Rp)</label>
                                                    <input type="number" id="bbm_pajak"
                                                        class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                                        value="12000">
                                                </div>

                                                <div>
                                                    <p class="text-sm font-medium text-gray-700 mb-2">Pilih Store:</p>
                                                    <div id="storeCheckboxContainer"
                                                        class="grid grid-cols-1 gap-2 max-h-32 overflow-y-auto p-2 border border-gray-200 rounded-md">
                                                        <?php foreach ($stores as $store): ?>
                                                        <label
                                                            class="flex items-center gap-2 p-1 hover:bg-gray-50 rounded">
                                                            <input type="checkbox" name="store_bbm[]"
                                                                value="<?= $store['id'] ?>"
                                                                class="store-check h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                                                            <span class="text-sm text-gray-700">
                                                                <?= htmlspecialchars($store['store_name']) ?>
                                                            </span>
                                                        </label>
                                                        <?php endforeach; ?>
                                                    </div>
                                                </div>

                                                <div id="storeInputs" class="hidden space-y-2"></div>

                                                <div class="pt-2">
                                                    <label class="block text-sm font-medium text-gray-700 mb-1">Total
                                                        Akhir (Rp)</label>
                                                    <input type="number" id="bbm_total" name="bbm_total"
                                                        class="w-full px-3 py-2 border border-gray-300 bg-gray-50 rounded-md font-semibold"
                                                        readonly>
                                                    <small id="bbm_info"
                                                        class="text-xs text-gray-500 mt-1 block"></small>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Tombol Action -->
                                        <div class="flex justify-end gap-3 pt-4 mt-6 border-t border-gray-200">
                                            <button type="button" onclick="hideModal('modalCashflow')"
                                                class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-colors">
                                                Batal
                                            </button>
                                            <button type="submit"
                                                class="px-4 py-2 text-sm font-medium text-white bg-indigo-600 border border-transparent rounded-md shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-colors"
                                                id="cashflowSubmitBtn">
                                                Simpan Transaksi
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div id="tab-stores" class="tab-content hidden">
                    <div class="flex justify-between items-center mb-6">
                        <h2 class="text-2xl font-bold text-gray-800">Manajemen Store/SPBU</h2>
                        <div class="flex gap-2">
                            <button onclick="exportStores()"
                                class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg shadow-md text-sm transition duration-200 flex items-center">
                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z">
                                    </path>
                                </svg>
                                Export Excel
                            </button>
                            <button onclick="showStoreModal()"
                                class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg shadow-md text-sm transition duration-200 flex items-center">
                                <svg class="w-5 h-5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                    xmlns="http://www.w3.org/2000/svg">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                </svg>
                                Tambah Store
                            </button>
                        </div>
                    </div>

                    <div class="bg-white p-6 rounded-xl shadow-md overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th
                                        class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                        ID</th>
                                    <th
                                        class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                        Nama Store</th>
                                    <th
                                        class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                        Alamat</th>
                                    <th
                                        class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                        Aksi</th>
                                </tr>
                            </thead>
                            <tbody id="storesTableBody" class="bg-white divide-y divide-gray-200">
                                <tr>
                                    <td colspan="4" class="text-center py-4 text-gray-500">Memuat data...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div id="tab-employees" class="tab-content hidden">
                    <div class="flex justify-between items-center mb-6">
                        <h2 class="text-2xl font-bold text-gray-800">Manajemen Karyawan</h2>
                        <div class="flex gap-2">
                            <button onclick="exportEmployees()"
                                class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg shadow-md text-sm transition duration-200 flex items-center">
                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z">
                                    </path>
                                </svg>
                                Export Excel
                            </button>
                            <button onclick="showEmployeeModal()"
                                class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg shadow-md text-sm transition duration-200 flex items-center">
                                <svg class="w-5 h-5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                    xmlns="http://www.w3.org/2000/svg">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                </svg>
                                Tambah Karyawan
                            </button>
                        </div>
                    </div>

                    <div class="bg-white p-6 rounded-xl shadow-md overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th
                                        class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                        ID</th>
                                    <th
                                        class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                        Nama</th>
                                    <th
                                        class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                        Kode</th>
                                    <th
                                        class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                        Store</th>
                                    <th
                                        class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                        Status</th>
                                    <th
                                        class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                        Aksi</th>
                                </tr>
                            </thead>
                            <tbody id="employeesTableBody" class="bg-white divide-y divide-gray-200">
                                <tr>
                                    <td colspan="6" class="text-center py-4 text-gray-500">Memuat data...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- TAB EMAIL SETTINGS -->
                <div id="tab-email-settings" class="tab-content hidden">
                    <h2 class="text-2xl font-bold mb-6 text-gray-800 flex items-center gap-2">
                        ⚙️ Email Notification Settings
                        <span id="emailStatusBadge" class="text-xs px-3 py-1 rounded-full bg-gray-300 text-gray-700">Loading...</span>
                    </h2>

                    <!-- Email Configuration Form -->
                    <div class="bg-white p-6 rounded-xl shadow-md mb-6">
                        <h3 class="text-lg font-semibold mb-4 text-gray-700">📧 Konfigurasi SMTP</h3>
                        <form id="emailConfigForm" class="space-y-4">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">SMTP Host</label>
                                    <input type="text" id="smtp_host" name="smtp_host" value="smtp.gmail.com" required
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                    <p class="text-xs text-gray-500 mt-1">Default: smtp.gmail.com</p>
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">SMTP Port</label>
                                    <input type="number" id="smtp_port" name="smtp_port" value="587" required
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                    <p class="text-xs text-gray-500 mt-1">Default: 587 (TLS)</p>
                                </div>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Gmail Address (Pengirim)</label>
                                <input type="email" id="smtp_username" name="smtp_username" placeholder="your-email@gmail.com" required
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                <p class="text-xs text-gray-500 mt-1">Email Gmail yang akan digunakan untuk mengirim notifikasi</p>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Gmail App Password</label>
                                <input type="password" id="smtp_password" name="smtp_password" placeholder="App Password (bukan password Gmail biasa)" required
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                <p class="text-xs text-gray-500 mt-1">
                                    <a href="https://support.google.com/accounts/answer/185833" target="_blank" class="text-blue-600 hover:underline">
                                        Cara membuat App Password →
                                    </a>
                                </p>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Email Penerima Notifikasi</label>
                                <input type="email" id="recipient_email" name="recipient_email" placeholder="recipient@example.com" required
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                <p class="text-xs text-gray-500 mt-1">Email yang akan menerima notifikasi data baru</p>
                            </div>

                            <div class="flex items-center">
                                <input type="checkbox" id="is_enabled" name="is_enabled" checked
                                    class="w-4 h-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500">
                                <label for="is_enabled" class="ml-2 text-sm font-medium text-gray-700">
                                    Enable Email Notifications
                                </label>
                            </div>

                            <div class="flex gap-3 pt-4 border-t">
                                <button type="submit"
                                    class="px-6 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg shadow-md transition duration-200 flex items-center">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                    </svg>
                                    Simpan Konfigurasi
                                </button>
                                
                                <button type="button" id="testEmailBtn"
                                    class="px-6 py-2.5 bg-green-600 hover:bg-green-700 text-white rounded-lg shadow-md transition duration-200 flex items-center">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                                    </svg>
                                    Kirim Test Email
                                </button>

                                <button type="button" id="testConnectionBtn"
                                    class="px-6 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-lg shadow-md transition duration-200 flex items-center">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                    Test Connection
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Notification History -->
                    <div class="bg-white p-6 rounded-xl shadow-md">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-lg font-semibold text-gray-700">📬 History Notifikasi (50 Terakhir)</h3>
                            <button id="refreshHistoryBtn"
                                class="px-4 py-2 bg-gray-600 hover:bg-gray-700 text-white rounded-lg text-sm transition duration-200">
                                Refresh
                            </button>
                        </div>
                        
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">ID</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Type</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Subject</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Status</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Sent At</th>
                                    </tr>
                                </thead>
                                <tbody id="emailHistoryTableBody" class="bg-white divide-y divide-gray-200">
                                    <tr>
                                        <td colspan="5" class="text-center py-4 text-gray-500">Memuat history...</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Real-time Status Indicator -->
                    <div class="mt-6 bg-gradient-to-r from-indigo-500 to-purple-600 text-white p-4 rounded-xl shadow-md">
                        <div class="flex items-center justify-between">
                            <div>
                                <h4 class="font-semibold">🔄 Real-time Monitoring Status</h4>
                                <p class="text-sm opacity-90 mt-1">Polling setiap 5 detik untuk deteksi data baru</p>
                            </div>
                            <div id="realtimeStatusIndicator" class="flex items-center gap-2">
                                <div class="w-3 h-3 bg-green-400 rounded-full animate-pulse"></div>
                                <span class="text-sm font-medium">Active</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- MODAL STORE -->
                <div id="modalStore"
                    class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
                    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
                        <div class="mt-3">
                            <h3 class="text-lg font-medium text-gray-900 mb-4" id="modalStoreTitle">Tambah Store</h3>
                            <form id="storeForm">
                                <input type="hidden" id="store_id" name="id">

                                <div class="mb-4">
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Nama Store</label>
                                    <input type="text" id="store_name" name="store_name" required
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                </div>

                                <div class="mb-4">
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Alamat</label>
                                    <textarea id="store_address" name="address" rows="3"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500"></textarea>
                                </div>

                                <div class="flex justify-end gap-3 mt-6">
                                    <button type="button" onclick="hideModal('modalStore')"
                                        class="px-4 py-2 bg-gray-300 hover:bg-gray-400 text-gray-800 rounded-md">
                                        Batal
                                    </button>
                                    <button type="submit"
                                        class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-md">
                                        Simpan
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- MODAL EMPLOYEE -->
                <div id="modalEmployee"
                    class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
                    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
                        <div class="mt-3">
                            <h3 class="text-lg font-medium text-gray-900 mb-4" id="modalEmployeeTitle">Tambah Karyawan
                            </h3>
                            <form id="employeeForm">
                                <input type="hidden" id="employee_id" name="id">

                                <div class="mb-4">
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Nama Karyawan</label>
                                    <input type="text" id="employee_name" name="employee_name" required
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                </div>

                                <div class="mb-4">
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Kode Karyawan</label>
                                    <input type="text" id="employee_code" name="employee_code"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                </div>

                                <div class="mb-4">
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Store</label>
                                    <select id="employee_store_id" name="store_id" required
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                        <option value="">Pilih Store</option>
                                    </select>
                                </div>

                                <div class="mb-4" id="employee_status_container" style="display: none;">
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                                    <select id="employee_is_active" name="is_active"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                        <option value="1">Aktif</option>
                                        <option value="0">Tidak Aktif</option>
                                    </select>
                                </div>

                                <div class="flex justify-end gap-3 mt-6">
                                    <button type="button" onclick="hideModal('modalEmployee')"
                                        class="px-4 py-2 bg-gray-300 hover:bg-gray-400 text-gray-800 rounded-md">
                                        Batal
                                    </button>
                                    <button type="submit"
                                        class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-md">
                                        Simpan
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

            </div>
        </main>

        <footer class="bg-white border-t border-gray-200 mt-8">
            <div class="content-wrapper px-4 sm:px-6 lg:px-8 py-3 text-center text-sm text-gray-500">
                &copy;
                <?php echo date('Y'); ?> Setoran Harian. All rights reserved.
            </div>
        </footer>
    </div>
    <div id="detailModal" class="modal fixed inset-0 z-50 flex items-center justify-center p-4">
        <div
            class="bg-white rounded-xl shadow-2xl w-full max-w-lg p-6 transform transition-all duration-300 scale-95 modal-content">
            <h3 class="text-xl font-bold mb-4 border-b pb-2 text-indigo-700">Rincian Setoran <span
                    id="detail_id">#...</span></h3>
            <div id="detailLoading" class="text-center text-gray-500 py-4">Memuat...</div>
            <div id="detailContent" class="space-y-4 text-sm hidden">
                <div class="grid grid-cols-2 gap-x-4">
                    <p class="p-2 bg-indigo-50 rounded"><strong>Store:</strong> <span id="detail_store"
                            class="font-medium"></span></p>
                    <p class="p-2 bg-indigo-50 rounded"><strong>Karyawan:</strong> <span id="detail_employee"
                            class="font-medium"></span></p>
                    <p class="p-2 bg-indigo-50 rounded"><strong>Tanggal:</strong> <span id="detail_tanggal"
                            class="font-medium"></span></p>
                    <p class="p-2 bg-indigo-50 rounded"><strong>Jam Kerja:</strong> <span id="detail_jam_masuk"
                            class="font-medium"></span> - <span id="detail_jam_keluar" class="font-medium"></span></p>
                </div>

                <div class="bg-yellow-50 p-3 rounded-lg border-l-4 border-yellow-500">
                    <p class="text-sm text-gray-700">Meter Awal/Akhir: <span id="detail_nomor_awal"
                            class="font-medium"></span> - <span id="detail_nomor_akhir" class="font-medium"></span></p>
                    <p class="font-bold text-lg text-yellow-800 mt-1">Total Liter: <span id="detail_total_liter"></span>
                        L</p>
                </div>

                <div class="border border-indigo-200 p-3 rounded-lg bg-indigo-50">
                    <p class="font-bold text-lg mb-2 text-indigo-700">Total Setoran Kasir: <span
                            id="detail_total_setoran"></span></p>
                    <div class="flex justify-between text-sm">
                        <p class="text-green-600">Cash: <span id="detail_cash" class="font-medium"></span></p>
                        <p class="text-blue-600">QRIS: <span id="detail_qris" class="font-medium"></span></p>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div class="p-3 border rounded-lg bg-red-50 border-red-200">
                        <h4 class="font-semibold text-red-700 border-b border-red-300 pb-1 mb-2">Pengeluaran (<span
                                id="detail_total_pengeluaran"></span>)</h4>
                        <ul id="detail_pengeluaran_list" class="space-y-1 text-xs"></ul>
                    </div>
                    <div class="p-3 border rounded-lg bg-green-50 border-green-200">
                        <h4 class="font-semibold text-green-700 border-b border-green-300 pb-1 mb-2">Pemasukan (<span
                                id="detail_total_pemasukan"></span>)</h4>
                        <ul id="detail_pemasukan_list" class="space-y-1 text-xs"></ul>
                    </div>
                </div>

                <p class="font-bold text-xl pt-3 border-t-2 border-dashed mt-4 text-center">TOTAL BERSIH: <span
                        id="detail_total_keseluruhan" class="text-green-800"></span></p>
            </div>
            <div class="flex justify-end mt-6">
                <button onclick="hideModal('detailModal')"
                    class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg transition duration-200">Tutup</button>
            </div>
        </div>
    </div>

    <div id="storeModal" class="modal fixed inset-0 z-50 flex items-center justify-center p-4">
        <div
            class="bg-white rounded-xl shadow-2xl w-full max-w-md p-6 transform transition-all duration-300 scale-95 modal-content">
            <h3 id="storeModalTitle" class="text-xl font-bold mb-4 border-b pb-2 text-indigo-700">Tambah Store Baru</h3>
            <form id="storeForm">
                <input type="hidden" id="store_id_field" name="id">
                <div class="mb-4">
                    <label for="store_name_field" class="block text-sm font-medium text-gray-700">Nama Store</label>
                    <input type="text" id="store_name_field" name="store_name"
                        class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm p-2 focus:border-indigo-500 focus:ring-indigo-500"
                        required>
                </div>
                <div class="mb-4">
                    <label for="store_address_field" class="block text-sm font-medium text-gray-700">Alamat
                        (Opsional)</label>
                    <textarea id="store_address_field" name="address" rows="2"
                        class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm p-2 focus:border-indigo-500 focus:ring-indigo-500"></textarea>
                </div>
                <p id="storeMessage" class="text-center text-red-500 mb-4 hidden"></p>
                <div class="flex justify-end space-x-3 mt-6">
                    <button type="button" onclick="hideModal('storeModal')"
                        class="bg-gray-300 hover:bg-gray-400 text-gray-800 px-4 py-2 rounded-lg transition duration-200">Batal</button>
                    <button type="submit"
                        class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg transition duration-200">Simpan</button>
                </div>
            </form>
        </div>
    </div>

    <div id="employeeModal" class="modal fixed inset-0 z-50 flex items-center justify-center p-4">
        <div
            class="bg-white rounded-xl shadow-2xl w-full max-w-md p-6 transform transition-all duration-300 scale-95 modal-content">
            <h3 id="employeeModalTitle" class="text-xl font-bold mb-4 border-b pb-2 text-indigo-700">Tambah Karyawan
                Baru</h3>
            <form id="employeeForm">
                <input type="hidden" id="employee_id_field" name="id">
                <div class="mb-4">
                    <label for="employee_name_field" class="block text-sm font-medium text-gray-700">Nama
                        Karyawan</label>
                    <input type="text" id="employee_name_field" name="employee_name"
                        class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm p-2 focus:border-indigo-500 focus:ring-indigo-500"
                        required>
                </div>
                <div class="mb-4">
                    <label for="employee_code_field" class="block text-sm font-medium text-gray-700">Kode Karyawan
                        (Opsional)</label>
                    <input type="text" id="employee_code_field" name="employee_code"
                        class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm p-2 focus:border-indigo-500 focus:ring-indigo-500">
                </div>
                <div class="mb-4">
                    <label for="employee_store_id_field" class="block text-sm font-medium text-gray-700">Store /
                        SPBU</label>
                    <select id="employee_store_id_field" name="store_id"
                        class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm p-2 focus:border-indigo-500 focus:ring-indigo-500"
                        required>
                    </select>
                </div>
                <div id="employeeStatusDiv" class="mb-4 hidden">
                    <label class="inline-flex items-center">
                        <input type="checkbox" id="employee_is_active_field" name="is_active"
                            class="rounded text-indigo-600 focus:ring-indigo-500">
                        <span class="ml-2 text-sm text-gray-700 font-medium">Karyawan Aktif</span>
                    </label>
                </div>
                <p id="employeeMessage" class="text-center text-red-500 mb-4 hidden"></p>
                <div class="flex justify-end space-x-3 mt-6">
                    <button type="button" onclick="hideModal('employeeModal')"
                        class="bg-gray-300 hover:bg-gray-400 text-gray-800 px-4 py-2 rounded-lg transition duration-200">Batal</button>
                    <button type="submit"
                        class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg transition duration-200">Simpan</button>
                </div>
            </form>
        </div>
    </div>

    <div id="cashFlowModal" class="modal fixed inset-0 z-50 flex items-center justify-center p-4">
        <div
            class="bg-white rounded-xl shadow-2xl w-full max-w-md p-6 transform transition-all duration-300 scale-95 modal-content">
            <h3 id="cashFlowModalTitle" class="text-xl font-bold mb-4 border-b pb-2 text-indigo-700">Catat Transaksi Kas
                Manajemen</h3>
            <form id="cashFlowForm">
                <input type="hidden" id="cf_id_field" name="id">
                <div class="mb-4">
                    <label for="cf_tanggal_field" class="block text-sm font-medium text-gray-700">Tanggal
                        Transaksi</label>
                    <input type="date" id="cf_tanggal_field" name="tanggal"
                        class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm p-2 focus:border-indigo-500 focus:ring-indigo-500"
                        required value="<?php echo date('Y-m-d'); ?>">
                </div>
                <div class="mb-4">
                    <label for="cf_store_id_field" class="block text-sm font-medium text-gray-700">Store Terkait</label>
                    <select id="cf_store_id_field" name="store_id"
                        class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm p-2 focus:border-indigo-500 focus:ring-indigo-500"
                        required>
                        <option value="" disabled selected>Pilih Store (Wajib)</option>
                    </select>
                </div>
                <div class="mb-4">
                    <label for="cf_type_field" class="block text-sm font-medium text-gray-700">Tipe Transaksi</label>
                    <select id="cf_type_field" name="type"
                        class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm p-2 focus:border-indigo-500 focus:ring-indigo-500"
                        required>
                        <option value="" disabled selected>Pilih Tipe</option>
                        <option value="Pemasukan">Pemasukan</option>
                        <option value="Pengeluaran">Pengeluaran</option>
                    </select>
                </div>
                <div class="mb-4">
                    <label for="cf_description_field"
                        class="block text-sm font-medium text-gray-700">Deskripsi/Keterangan</label>
                    <input type="text" id="cf_description_field" name="description"
                        class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm p-2 focus:border-indigo-500 focus:ring-indigo-500"
                        required>
                </div>
                <div class="mb-4">
                    <label for="cf_amount_field" class="block text-sm font-medium text-gray-700">Jumlah (Rp)</label>
                    <input type="number" id="cf_amount_field" name="amount"
                        class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm p-2 focus:border-indigo-500 focus:ring-indigo-500"
                        required min="1000">
                </div>
                <div class="mb-4">
                    <label for="cf_notes_field" class="block text-sm font-medium text-gray-700">Catatan
                        (Opsional)</label>
                    <textarea id="cf_notes_field" name="notes" rows="2"
                        class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm p-2 focus:border-indigo-500 focus:ring-indigo-500"></textarea>
                </div>
                <p id="cashFlowMessage" class="text-center text-red-500 mb-4 hidden"></p>
                <div class="flex justify-end space-x-3 mt-6">
                    <button type="button" onclick="hideModal('cashFlowModal')"
                        class="bg-gray-300 hover:bg-gray-400 text-gray-800 px-4 py-2 rounded-lg transition duration-200">Batal</button>
                    <button type="submit"
                        class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg transition duration-200">Simpan</button>
                </div>
            </form>
        </div>
    </div>



    <!-- External JavaScript -->
    <script src="../assets/js/security.js"></script>
    <script src="../assets/js/csrf-fix.js"></script>
    <script src="../assets/js/admin-enhanced.js"></script>
    <script src="../assets/js/admin-integration.js"></script>
    <script src="../assets/js/admin.js"></script>
    <!-- dashboard-charts.js removed - charts section no longer used -->
    <script src="../assets/js/admin-improvements.js"></script>
    <script>
        // Initialize CSRF Token from PHP
        if (typeof CSRF_TOKEN !== 'undefined') {
            setCSRFToken(CSRF_TOKEN);
        }
    </script>
</body>

</html>