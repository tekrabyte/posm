<?php

header('Content-Type: application/javascript');

// // Cek jika diakses langsung dari browser
// if (isset($_SERVER['HTTP_SEC_FETCH_DEST']) && $_SERVER['HTTP_SEC_FETCH_DEST'] === 'script') {
//     // Ini request legitimate dari browser, lanjutkan
// } elseif (basename(__FILE__) == basename($_SERVER['SCRIPT_FILENAME'])) {
//     header('HTTP/1.0 403 Forbidden');
//     exit('Access forbidden');
// }

// Path yang benar ke file JavaScript
$jsPath = __DIR__ . '/js/app-c1d2e3f4g5.js';

if (file_exists($jsPath)) {
    $js = file_get_contents($jsPath);
    
    // Hapus komentar dan minify (opsional)
    $js = preg_replace('/\/\*.*?\*\//s', '', $js);
    $js = preg_replace('/\/\/[^\n]*/', '', $js);
    $js = preg_replace('/\s+/', ' ', $js);
    
    echo $js;
} else {
    // Fallback: output basic JavaScript jika file tidak ditemukan
    echo 'console.error("JavaScript file not found: ' . $jsPath . '");';
}
function getCurrentDate() {
    $days = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
    $months = [
        'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
    ];
    
    $day = $days[date('w')];
    $date = date('j');
    $month = $months[date('n') - 1];
    $year = date('Y');    
    return "$day, $date $month $year";
}
?>