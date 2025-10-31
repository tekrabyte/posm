<?php
/**
 * Helper functions untuk format tanggal Indonesia dan lainnya
 */

/**
 * Get Indonesian day name
 */
function getIndonesianDayName($date) {
    $days = [
        'Sunday' => 'Minggu',
        'Monday' => 'Senin',
        'Tuesday' => 'Selasa',
        'Wednesday' => 'Rabu',
        'Thursday' => 'Kamis',
        'Friday' => 'Jumat',
        'Saturday' => 'Sabtu'
    ];
    
    $dayName = date('l', strtotime($date));
    return $days[$dayName] ?? $dayName;
}

/**
 * Get Indonesian month name
 */
function getIndonesianMonthName($monthNumber) {
    $months = [
        1 => 'Januari',
        2 => 'Februari',
        3 => 'Maret',
        4 => 'April',
        5 => 'Mei',
        6 => 'Juni',
        7 => 'Juli',
        8 => 'Agustus',
        9 => 'September',
        10 => 'Oktober',
        11 => 'November',
        12 => 'Desember'
    ];
    
    return $months[intval($monthNumber)] ?? '';
}

/**
 * Format date in Indonesian format: Jumat, 31 Oktober 2025
 */
function formatIndonesianDate($date) {
    $dayName = getIndonesianDayName($date);
    $day = date('d', strtotime($date));
    $month = getIndonesianMonthName(date('m', strtotime($date)));
    $year = date('Y', strtotime($date));
    
    // Remove leading zero from day
    $day = ltrim($day, '0');
    
    return "{$dayName}, {$day} {$month} {$year}";
}

/**
 * Format number as Indonesian Rupiah
 */
function formatRupiah($number) {
    return number_format($number, 0, ',', '.');
}
?>