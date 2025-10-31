# 📧 Panduan Setup Email Notifikasi - Admin Panel POSM

## ✅ Status Sistem
**Email system sudah AKTIF dan BERFUNGSI!** ✓

---

## 🔧 Konfigurasi Saat Ini

- **SMTP Host:** smtp.gmail.com
- **SMTP Port:** 587
- **Email Pengirim:** tekrabyte@gmail.com
- **Email Penerima:** alhafizaulia5@gmail.com
- **Status:** ✅ Aktif dan Terkirim

---

## 📨 Kapan Email Dikirim?

Email notifikasi otomatis akan dikirim saat:

1. **Data Setoran Baru** - Setiap kali karyawan submit setoran harian
2. **Transaksi Cash Flow** - Setiap pengeluaran atau pemasukan baru di manajemen kas
3. **Laporan Harian** - Setiap hari jam 12:00 WIB (via cron job)

---

## 🔑 Cara Setup Gmail App Password

Gmail memerlukan "App Password" untuk aplikasi pihak ketiga, bukan password Gmail biasa.

### Langkah-langkah:

1. **Buka Google Account Settings**
   - Kunjungi: https://myaccount.google.com/apppasswords
   - Login dengan akun Gmail Anda

2. **Aktifkan 2-Step Verification** (jika belum)
   - Ke: https://myaccount.google.com/security
   - Aktifkan "2-Step Verification"

3. **Generate App Password**
   - Pilih app: "Mail"
   - Pilih device: "Other (Custom name)"
   - Masukkan nama: "Admin Panel POSM"
   - Klik "Generate"

4. **Copy Password**
   - Google akan menampilkan password 16 karakter
   - **PENTING:** Copy password ini (tanpa spasi)
   - Password ini hanya ditampilkan sekali!

5. **Update di Admin Panel**
   - Buka Admin Panel → Tab "Email Config"
   - Paste App Password ke field "SMTP Password"
   - Klik "Save Config"
   - Klik "Test Connection" untuk verifikasi

---

## 🧪 Cara Test Email

### Via Admin Panel:
1. Buka Admin Panel
2. Klik tab "Email Config" (ikon ⚙️)
3. Klik tombol "Send Test Email"
4. Cek inbox email penerima

### Via Browser Console:
```javascript
// Test kirim email
fetch('/config/realtime_api.php?action=send_test_email')
  .then(r => r.json())
  .then(d => console.log(d));
```

### Via Command Line:
```bash
cd /app/config && php -r "
\$_GET['action'] = 'send_test_email';
include 'realtime_api.php';
"
```

---

## ❗ Troubleshooting

### Error: "Gmail credentials ditolak"

**Penyebab:** Password yang digunakan adalah password Gmail biasa, bukan App Password.

**Solusi:**
1. Generate Gmail App Password (lihat panduan di atas)
2. Update password di Admin Panel
3. Test ulang koneksi

---

### Error: "Tidak dapat terhubung ke server Gmail"

**Penyebab:** Koneksi internet atau port 587 diblokir.

**Solusi:**
1. Cek koneksi internet
2. Test port 587:
   ```bash
   telnet smtp.gmail.com 587
   ```
3. Jika diblokir firewall, hubungi admin server

---

### Error: "SMTP connect() failed"

**Penyebab:** SMTP Host atau Port salah.

**Solusi:**
- Pastikan SMTP Host: `smtp.gmail.com`
- Pastikan SMTP Port: `587`
- Pastikan TLS/STARTTLS enabled

---

### Email Masuk Spam

**Solusi:**
1. Tandai email sebagai "Not Spam"
2. Tambahkan `tekrabyte@gmail.com` ke kontak
3. Buat filter untuk auto-label email dari sistem

---

## 📊 Monitoring Email

### Cek History Email
- Buka Admin Panel → Tab "Email Config"
- Scroll ke bawah untuk melihat "Email Notification History"
- Status "sent" = berhasil terkirim
- Status "failed" = gagal, lihat error message

### Via API:
```bash
curl 'http://localhost/config/realtime_api.php?action=get_notification_history&limit=10'
```

---

## ⏰ Setup Cron Job Laporan Harian

Laporan harian otomatis dikirim jam 12:00 WIB setiap hari.

### Setup Cron:
```bash
# Edit crontab
crontab -e

# Tambahkan baris ini
0 12 * * * /usr/bin/php /app/cron_daily_report.php >> /var/log/daily_report.log 2>&1
```

### Test Manual:
```bash
php /app/cron_daily_report.php
```

### Cek Log:
```bash
tail -f /var/log/daily_report.log
```

---

## 🔄 Force Send Laporan Harian

Jika ingin kirim laporan manual (bypass anti-duplicate):

```bash
cd /app/config && php -r "
require_once 'config.php';
require_once 'email_handler.php';
\$handler = new EmailHandler(\$pdo);
\$result = \$handler->sendDailyReport(true); // true = force override
print_r(\$result);
"
```

---

## 🛠️ Advanced Configuration

### Ganti Provider Email

Jika ingin menggunakan email selain Gmail:

1. **Outlook/Hotmail:**
   - SMTP Host: `smtp.office365.com`
   - SMTP Port: `587`
   - TLS: Enabled

2. **Yahoo:**
   - SMTP Host: `smtp.mail.yahoo.com`
   - SMTP Port: `587`
   - TLS: Enabled

3. **Custom Domain (cPanel):**
   - SMTP Host: `mail.yourdomain.com`
   - SMTP Port: `587` atau `465`
   - Username: `your-email@yourdomain.com`
   - Password: Email password

Update di Admin Panel → Email Config.

---

## 📝 Format Laporan Harian

Laporan harian berisi:

### 1. Info 1 Bulan Wallet (Semua Store)
- Total Pemasukan
- Total Pengeluaran
- Saldo Bersih
- Total Liter Terjual

### 2. Info 1 Bulan Wallet (Per Store)
- Breakdown per store
- Total per store

### 3. Info Hari Ini (Per Store)
- Data transaksi hari ini
- Per store breakdown

---

## 🔐 Security Notes

1. **Jangan Share App Password** - Password ini memberikan akses penuh ke akun Gmail
2. **Revoke Jika Tidak Digunakan** - Hapus App Password dari Google Account jika tidak lagi digunakan
3. **Monitor Email Logs** - Cek history email secara berkala
4. **Update Password Berkala** - Generate App Password baru setiap 3-6 bulan

---

## 📞 Support

Jika masih ada masalah:

1. Cek browser console untuk error detail
2. Cek file log: `/var/log/apache2/error.log`
3. Test API endpoint secara manual
4. Hubungi developer/admin sistem

---

## ✅ Checklist Setup

- [x] PHP dependencies installed (PHPMailer)
- [x] Database tables created
- [x] Gmail App Password generated
- [x] Email config saved di Admin Panel
- [x] Test email berhasil terkirim
- [x] Notification email berfungsi
- [ ] Cron job setup (opsional, untuk laporan harian)

---

**Status Terakhir:** Email system sudah aktif dan berfungsi normal ✓  
**Tanggal Update:** 31 Agustus 2025  
**Version:** 2.0

---

*Dokumen ini dibuat untuk membantu setup dan troubleshoot sistem email notifikasi.*
