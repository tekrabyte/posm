# 📋 Dokumentasi Perubahan - Laporan Harian & Format Email

## Ringkasan Perubahan

Sistem email notifikasi POM MINI telah diperbarui dengan perubahan berikut:

### 1. ⏰ Waktu Pengiriman Laporan Harian (Summary)
- **Sebelumnya:** Jam 12:00 WIB
- **Sekarang:** Jam 23:50 WIB
- **File:** `/app/config/cron_daily_report.php`
- **Lokasi Fungsi:** `email_handler.php` - method `sendDailyReport()`

**Setup Cron Job:**
```bash
50 23 * * * /usr/bin/php /app/config/cron_daily_report.php >> /var/log/daily_report.log 2>&1
```

### 2. 💌 Format Email "Setoran Baru" (Personal)
Email notifikasi untuk setiap setoran baru telah diubah ke format WhatsApp style.

**Sebelumnya:** Format HTML dengan tabel dan box
**Sekarang:** Format text monospace dengan emoji (WhatsApp style)

**File yang diubah:** `/app/config/api.php` (baris 442-548)

**Contoh Format Baru:**
```
*Setoran Harian* 📋
Jumat, 31 Januari 2025
🤦‍♀️ Nama: John Doe
🕐 Jam: (08:00 - 16:00)

⛽ Data Meter
• Nomor Awal : 1.158,23
• Nomor Akhir: 1.231,12
• Total Liter: 72,89 L

💰 Setoran
• Cash  : Rp 788.235
• QRIS  : Rp 50.000
• Total : Rp 838.235

💸 Pengeluaran (PU)
• Bensin: Rp 20.000
Total Pengeluaran: Rp 20.000

💵 Pemasukan (PU)
• Tips: Rp 23.000
Total Pemasukan: Rp 23.000

💼 Total Keseluruhan: Rp 791.235
```

### 3. 📅 Format Tanggal Indonesia
Ditambahkan helper functions untuk format tanggal dalam bahasa Indonesia.

**File Baru:** `/app/config/helper_functions.php`

**Fungsi yang tersedia:**
- `getIndonesianDayName($date)` - Mengembalikan nama hari (Senin, Selasa, dst)
- `getIndonesianMonthName($monthNumber)` - Mengembalikan nama bulan (Januari, Februari, dst)
- `formatIndonesianDate($date)` - Format lengkap: "Jumat, 31 Januari 2025"
- `formatRupiah($number)` - Format angka Rupiah: "788.235"

## File-File yang Dimodifikasi

### 1. `/app/config/cron_daily_report.php`
- ✅ Update waktu cron dari 12:00 ke 23:50 WIB
- ✅ Update dokumentasi dan komentar

### 2. `/app/config/api.php`
- ✅ Import `helper_functions.php`
- ✅ Ubah format email "Setoran Baru" menjadi WhatsApp style
- ✅ Implementasi format tanggal Indonesia
- ✅ Tampilkan detail pengeluaran dan pemasukan dengan emoji

### 3. `/app/config/email_handler.php`
- ✅ Update footer email dari "jam 12:00 WIB" ke "jam 23:50 WIB"

### 4. `/app/config/helper_functions.php` (File Baru)
- ✅ Fungsi helper untuk format tanggal Indonesia
- ✅ Fungsi helper untuk format Rupiah

## Testing & Verifikasi

### Test Email "Setoran Baru"
Untuk test email setoran baru, submit form setoran dari frontend atau kirim request ke API:

```bash
curl -X POST http://your-domain/config/api.php \
  -H "Content-Type: application/json" \
  -d '{
    "employee_id": 1,
    "store_id": 1,
    "jam_masuk": "08:00",
    "jam_keluar": "16:00",
    "nomor_awal": 1158.23,
    "nomor_akhir": 1231.12,
    "total_liter": 72.89,
    "qris": 50000,
    "cash": 788235,
    "total_pengeluaran": 20000,
    "total_pemasukan": 23000,
    "total_keseluruhan": 791235,
    "pengeluaran": [{"description": "Bensin", "amount": 20000}],
    "pemasukan": [{"description": "Tips", "amount": 23000}]
  }'
```

### Test Laporan Harian (Summary)
Untuk test laporan harian, jalankan manual:

```bash
# Test dengan force override (untuk testing)
curl -X POST http://your-domain/config/api.php \
  -H "Content-Type: application/json" \
  -d '{"action": "send_daily_report"}'

# Atau jalankan cron script langsung
php /app/config/cron_daily_report.php
```

### Test Helper Functions
```php
<?php
require_once '/app/config/helper_functions.php';

// Test format tanggal
echo formatIndonesianDate('2025-01-31'); // Output: Jumat, 31 Januari 2025

// Test format Rupiah
echo formatRupiah(788235); // Output: 788.235
?>
```

## Setup Cron Job di Server

Untuk mengaktifkan pengiriman otomatis laporan harian jam 23:50 WIB:

```bash
# Edit crontab
crontab -e

# Tambahkan baris berikut:
50 23 * * * /usr/bin/php /app/config/cron_daily_report.php >> /var/log/daily_report.log 2>&1

# Simpan dan keluar
# Verifikasi cron job terdaftar
crontab -l
```

## Catatan Penting

1. **Timezone:** Pastikan server menggunakan timezone `Asia/Jakarta` (WIB)
2. **Email Config:** Pastikan email configuration sudah disetup di database (tabel `email_config`)
3. **SMTP Settings:** Email menggunakan Gmail SMTP, pastikan App Password sudah dikonfigurasi dengan benar
4. **Anti-Duplicate:** Laporan harian memiliki proteksi anti-duplicate, tidak akan kirim 2x dalam 1 hari
5. **Email Setoran Baru:** Dikirim setiap kali ada setoran baru (real-time)
6. **Email Summary:** Dikirim 1x per hari jam 23:50 WIB (berisi ringkasan semua store)

## Troubleshooting

### Email tidak terkirim
1. Cek log: `tail -f /var/log/daily_report.log`
2. Cek email config di database: `SELECT * FROM email_config WHERE id = 1`
3. Cek email notification log: `SELECT * FROM email_notifications ORDER BY sent_at DESC LIMIT 10`

### Format tanggal tidak Indonesia
1. Pastikan timezone server: `date` (harus Asia/Jakarta)
2. Cek file helper_functions.php sudah di-include dengan benar

### Cron job tidak jalan
1. Cek crontab: `crontab -l`
2. Cek cron service: `sudo service cron status`
3. Cek log: `tail -f /var/log/daily_report.log`

## Kontak & Support

Jika ada pertanyaan atau masalah, silakan hubungi tim development.

---
**Terakhir diupdate:** 31 Januari 2025
**Versi:** 2.0
