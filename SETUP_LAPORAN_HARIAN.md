# 📊 Setup Laporan Harian Email - POSM

## 🎯 Fitur Baru

Fungsi email notification telah diubah menjadi **Laporan Harian** dengan format:

### 📈 Format Laporan:

1. **Info 1 Bulan Wallet (All Store)**
   - Total Pemasukan
   - Total Pengeluaran
   - Saldo Bersih
   - Total Liter Terjual

2. **Info 1 Bulan Wallet (Per Store)**
   - Total Pemasukan per Store
   - Total Pengeluaran per Store
   - Saldo Bersih per Store
   - Total Liter Terjual per Store

3. **Info 1 Hari Wallet (Per Store)**
   - Total Pemasukan hari ini per Store
   - Total Pengeluaran hari ini per Store
   - Saldo Bersih hari ini per Store
   - Total Liter Terjual hari ini per Store

---

## 🚀 Cara Setup

### Step 1: Update Database

Jalankan query SQL berikut di database Anda:

```sql
-- Tambah kolom untuk tracking laporan harian
ALTER TABLE `email_config` 
ADD COLUMN `last_daily_report_sent` DATETIME NULL DEFAULT NULL AFTER `last_sent`;
```

**Via phpMyAdmin:**
1. Login ke phpMyAdmin
2. Pilih database `u215947863_pom`
3. Klik tab "SQL"
4. Copy-paste query di atas
5. Klik "Go"

---

### Step 2: Setup Cron Job untuk Otomatis Kirim Jam 12:00 WIB

**Via cPanel:**

1. Login ke cPanel hosting Anda
2. Cari menu **"Cron Jobs"**
3. Tambah Cron Job baru dengan setting:
   - **Minute:** 0
   - **Hour:** 12
   - **Day:** * (setiap hari)
   - **Month:** * (setiap bulan)
   - **Weekday:** * (setiap hari dalam seminggu)
   - **Command:** 
   ```bash
   /usr/bin/php /path/to/your/app/cron_daily_report.php >> /path/to/logs/daily_report.log 2>&1
   ```

**Contoh lengkap:**
```
0 12 * * * /usr/bin/php /home/u215947863/public_html/cron_daily_report.php >> /home/u215947863/logs/daily_report.log 2>&1
```

**Penjelasan:**
- `0 12 * * *` = Setiap hari jam 12:00 (siang)
- `/usr/bin/php` = Path ke PHP (bisa juga `/usr/local/bin/php` tergantung hosting)
- `/path/to/cron_daily_report.php` = Path lengkap ke file cron
- `>> /path/to/logs/daily_report.log` = Log hasil eksekusi
- `2>&1` = Redirect error ke log file juga

---

### Step 3: Set Permission untuk Cron File

Via SSH atau File Manager:

```bash
chmod +x /path/to/your/app/cron_daily_report.php
chmod 755 /path/to/your/app/cron_daily_report.php
```

---

### Step 4: Test Kirim Laporan Manual

1. Login ke **Admin Panel**
2. Buka tab **"Email Settings"**
3. Scroll ke bawah, cari section **"📊 Laporan Harian Otomatis"**
4. Klik tombol **"Kirim Laporan Harian Sekarang"**
5. Cek email penerima untuk melihat hasil laporan

---

## 📝 Fitur Anti-Duplicate

Sistem memiliki proteksi agar laporan **tidak dikirim 2x** dalam 1 hari:

- ✅ Cron job otomatis: Hanya kirim 1x per hari meskipun cron berjalan berkali-kali
- ✅ Manual trigger via UI: Bisa kirim berkali-kali (untuk testing)

---

## 🎨 Preview Format Email

Email laporan akan dikirim dengan format **HTML profesional**:

- Header dengan gradient purple
- Tabel yang rapi dan responsive
- Color coding untuk saldo (hijau = positif, merah = negatif)
- Summary box dengan gradient background
- Footer dengan info sistem

---

## 🔍 Cara Cek Log Cron Job

**Via SSH:**
```bash
tail -f /path/to/logs/daily_report.log
```

**Via cPanel File Manager:**
1. Navigate ke folder logs
2. Download file `daily_report.log`
3. Buka dengan text editor

**Format Log:**
```
[2025-08-15 12:00:01] Starting daily report cron job...
[2025-08-15 12:00:03] SUCCESS: Laporan harian berhasil dikirim
[2025-08-15 12:00:03] Cron job completed.
```

---

## ⚠️ Troubleshooting

### Masalah: Email tidak terkirim otomatis

**Solusi:**
1. Cek apakah cron job sudah aktif:
   ```bash
   crontab -l
   ```
2. Cek log file untuk error
3. Test manual via Admin Panel dulu
4. Pastikan email config sudah diisi dengan benar

---

### Masalah: "Email configuration incomplete"

**Solusi:**
1. Login Admin Panel
2. Buka Email Settings
3. Isi semua field:
   - SMTP Host
   - SMTP Port
   - Gmail Address
   - Gmail App Password
   - Recipient Email
4. Centang "Enable Email Notifications"
5. Klik "Simpan Konfigurasi"

---

### Masalah: Laporan kosong / tidak ada data

**Penyebab:**
- Tidak ada data transaksi untuk bulan/hari ini
- Format tanggal di database tidak sesuai

**Solusi:**
1. Cek apakah ada data di tabel `setoran` untuk bulan ini
2. Test dengan tanggal yang ada datanya

---

### Masalah: Cron tidak jalan

**Solusi:**
1. Cek path PHP dengan command:
   ```bash
   which php
   ```
2. Update cron command dengan path yang benar
3. Cek permission file cron (harus executable)
4. Cek log cron di `/var/log/cron` (untuk VPS/Dedicated)

---

## 📊 File-file yang Diupdate

1. **`/config/email_handler.php`**
   - Tambah method `sendDailyReport()`
   - Tambah query untuk data 1 bulan & 1 hari
   - Tambah format email HTML professional

2. **`/config/api.php`**
   - Tambah endpoint `send_daily_report`

3. **`/admin/index.php`**
   - Tambah section "Laporan Harian Otomatis"
   - Tambah tombol "Kirim Laporan Harian Sekarang"

4. **`/assets/js/realtime-polling.js`**
   - Tambah event handler untuk tombol kirim laporan

5. **`/cron_daily_report.php`** (NEW)
   - File cron job untuk otomatis kirim jam 12:00 WIB

6. **`/database_daily_report.sql`** (NEW)
   - Query update database

---

## ✅ Checklist Setup

- [ ] Update database (jalankan query SQL)
- [ ] Setup cron job di cPanel (jam 12:00 WIB)
- [ ] Set permission file cron
- [ ] Test kirim manual via Admin Panel
- [ ] Cek email apakah laporan diterima
- [ ] Tunggu besok jam 12:00 untuk test otomatis
- [ ] Monitor log cron untuk pastikan jalan

---

## 🎉 Selesai!

Setelah setup selesai, sistem akan otomatis kirim laporan harian setiap hari jam **12:00 WIB** ke email yang sudah dikonfigurasi.

**Kontak Support:**
Jika ada masalah, hubungi developer atau cek dokumentasi di folder `/app/`

---

*Last Updated: August 2025*  
*Version: 2.1 - Daily Report Feature*
