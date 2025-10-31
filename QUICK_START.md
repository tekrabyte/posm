# 🚀 Quick Start - Laporan Harian Email

## ⚡ Setup 3 Langkah Cepat

### 1️⃣ Update Database (1 menit)
```bash
# Via terminal/SSH
php /path/to/app/setup_daily_report.php

# ATAU via phpMyAdmin
# Jalankan query di database_daily_report.sql
```

### 2️⃣ Setup Cron Job (2 menit)
**cPanel → Cron Jobs → Add New:**
```
0 12 * * * /usr/bin/php /home/u215947863/public_html/cron_daily_report.php >> /home/u215947863/logs/daily_report.log 2>&1
```

### 3️⃣ Test Manual (30 detik)
1. Login Admin Panel
2. Tab "Email Settings"
3. Klik "Kirim Laporan Harian Sekarang"
4. ✅ Done!

---

## 📋 Checklist Lengkap

```
✅ File baru sudah ada di server:
   - /config/email_handler.php (UPDATED)
   - /config/api.php (UPDATED)
   - /admin/index.php (UPDATED)
   - /assets/js/realtime-polling.js (UPDATED)
   - /cron_daily_report.php (NEW)
   - /database_daily_report.sql (NEW)
   - /setup_daily_report.php (NEW)

✅ Jalankan setup_daily_report.php
✅ Setup cron job jam 12:00 WIB
✅ Test kirim manual via UI
✅ Cek email apakah diterima
✅ Monitor log besok jam 12:00
```

---

## 🔧 Command Cepat

**Run Setup:**
```bash
php /path/to/app/setup_daily_report.php
```

**Test Cron Manual:**
```bash
php /path/to/app/cron_daily_report.php
```

**Cek Log:**
```bash
tail -f /path/to/logs/daily_report.log
```

**Set Permission:**
```bash
chmod +x /path/to/app/cron_daily_report.php
```

---

## 📊 Format Laporan

✅ **Info 1 Bulan (All Store)**
- Total Pemasukan, Pengeluaran, Saldo Bersih, Liter

✅ **Info 1 Bulan (Per Store)**  
- Breakdown per store

✅ **Info Hari Ini (Per Store)**
- Data hari ini per store

---

## 🎯 Preview Email

Buka file: `preview_email_laporan.html` di browser untuk lihat preview

---

## ⚙️ Setting Email

**Admin Panel → Email Settings:**
- SMTP Host: `smtp.gmail.com`
- SMTP Port: `587`
- Gmail: `your-email@gmail.com`
- App Password: `xxxx xxxx xxxx xxxx`
- Recipient: `recipient@example.com`
- ✅ Enable Notifications

---

## 🐛 Troubleshooting

**Email tidak terkirim?**
→ Cek email config sudah diisi semua

**Cron tidak jalan?**
→ Cek path PHP: `which php`

**Laporan kosong?**
→ Cek apakah ada data di tabel setoran

---

## 📞 Support

**Dokumentasi lengkap:** `SETUP_LAPORAN_HARIAN.md`

**Log location:**
- Cron log: `/path/to/logs/daily_report.log`
- Email history: Admin Panel → Email Settings

---

## ⏰ Jadwal Otomatis

📅 **Setiap hari jam 12:00 WIB**
- Sistem auto kirim laporan
- Anti-duplicate (max 1x per hari)
- Manual trigger bisa kapan saja

---

**🎉 Setup selesai! Tunggu jam 12:00 besok untuk auto-send pertama.**
