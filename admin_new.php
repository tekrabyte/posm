<?php
session_start();
// Cek sesi login
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

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
    <!--<script>console.log = function() {};</script>-->
