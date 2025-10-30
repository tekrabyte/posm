# 💳 QRIS Auto-Sync ke Cashflow Management

## 📋 Overview

Fitur ini otomatis membuat entry di **Cashflow Management** setiap kali ada setoran yang menggunakan QRIS. Hal ini memudahkan tracking pemasukan QRIS per store tanpa perlu input manual.

---

## ✨ Fitur yang Diimplementasi

### 1. **Auto-Create Cashflow Entry**
   - ✅ Setiap setoran dengan QRIS > 0 otomatis create entry di `cash_flow_management`
   - ✅ Category: `qris_setoran`
   - ✅ Type: `Pemasukan`
   - ✅ Description: `Pemasukan QRIS Setoran - {Nama Karyawan} ({Nama Store})`
   - ✅ Notes: Berisi metadata untuk tracking (Setoran ID, Employee, Date)

### 2. **Prevent Double Counting**
   - ✅ Dashboard calculation exclude `qris_setoran` category
   - ✅ Total income = Setoran (Cash+QRIS) + Pemasukan Setoran + Cashflow (excluding QRIS)
   - ✅ Konsisten di semua dashboard (All Stores & Per Store)
   - ✅ Export PDF/Excel juga sudah disesuaikan

### 3. **Update Handling**
   - ✅ Jika setoran di-update, QRIS cashflow entry juga di-update
   - ✅ Hapus entry lama dan create yang baru
   - ✅ Menggunakan transaction untuk data consistency

---

## 🔧 Technical Details

### Database Changes

**Tabel yang Dimodifikasi:**
- `cash_flow_management` - Sekarang ada category baru `qris_setoran`

**Format Notes:**
```
AUTO_SYNC:SETORAN_ID:{id}:EMPLOYEE:{nama}:DATE:{tanggal}
```

### Code Changes

**File Modified:** `/app/config/api.php`

**Locations:**
1. ✅ `case 'save_setoran'` (Line ~190-320)
   - Added transaction handling
   - Auto-create cashflow entry untuk QRIS
   - Delete old entry on update

2. ✅ `case 'get_dashboard_wallet'` (Line ~1084-1220)
   - Exclude `qris_setoran` dari cashflow calculation
   - Update total income formula

3. ✅ `case 'export_dashboard'` (Line ~1224-1370)
   - Exclude `qris_setoran` dari export calculation

---

## 📊 Data Flow

```
┌─────────────────┐
│  Setoran Form   │
│   (index.php)   │
└────────┬────────┘
         │
         ▼
┌─────────────────┐
│  Save Setoran   │
│   API Call      │
└────────┬────────┘
         │
         ▼
┌─────────────────────────────────────┐
│  Process Setoran                    │
│  1. Save to `setoran` table         │
│  2. Save pengeluaran details        │
│  3. Save pemasukan details          │
│  4. IF QRIS > 0:                    │
│     └─> Create entry in             │
│         `cash_flow_management`      │
└─────────────────────────────────────┘
         │
         ▼
┌─────────────────────────────────────┐
│  Dashboard Display                  │
│  - Total = Setoran + Pemasukan +    │
│            Cashflow (excl QRIS)     │
│  - No double counting ✅            │
└─────────────────────────────────────┘
```

---

## 🎯 Benefits

### Untuk User:
1. **Tidak perlu input manual** - QRIS otomatis masuk ke cashflow
2. **Tracking lebih baik** - Bisa filter QRIS di manajemen kas
3. **Laporan lebih akurat** - Tidak ada double counting
4. **Audit trail** - Bisa trace QRIS dari setoran ke cashflow

### Untuk Developer:
1. **Konsistensi data** - Transaction handling untuk data integrity
2. **Easy debugging** - Notes field berisi metadata lengkap
3. **Scalable** - Category system mudah di-extend
4. **Maintainable** - Clear comments di code

---

## 🔍 Query Examples

### View All QRIS Entries
```sql
SELECT 
    tanggal,
    store_id,
    description,
    amount,
    notes
FROM cash_flow_management
WHERE category = 'qris_setoran'
ORDER BY tanggal DESC;
```

### Get Total QRIS per Store
```sql
SELECT 
    s.store_name,
    SUM(cfm.amount) as total_qris,
    COUNT(*) as transaction_count
FROM cash_flow_management cfm
JOIN stores s ON cfm.store_id = s.id
WHERE cfm.category = 'qris_setoran'
    AND YEAR(cfm.tanggal) = 2025
    AND MONTH(cfm.tanggal) = 8
GROUP BY s.store_name
ORDER BY total_qris DESC;
```

### Find QRIS Entry from Setoran ID
```sql
SELECT * FROM cash_flow_management
WHERE notes LIKE '%SETORAN_ID:123%';
```

---

## ⚠️ Important Notes

### Do's ✅
- ✅ QRIS akan otomatis sync setiap save setoran
- ✅ Dashboard sudah tidak double-count
- ✅ Bisa filter QRIS di tab "Manajemen Kas"
- ✅ Export Excel/PDF sudah benar

### Don'ts ❌
- ❌ Jangan manual create entry `qris_setoran` di cashflow
- ❌ Jangan edit/delete QRIS cashflow entry yang auto-generated
- ❌ Jangan ubah category dari `qris_setoran` ke lainnya

### Migration dari Data Lama
Jika ada data setoran lama yang belum ter-sync, jalankan script ini:

```sql
-- Create cashflow entries untuk setoran lama dengan QRIS
INSERT INTO cash_flow_management 
    (tanggal, store_id, description, amount, type, category, notes, created_at)
SELECT 
    s.tanggal,
    s.store_id,
    CONCAT('Pemasukan QRIS Setoran - ', s.employee_name, ' (', s.store_name, ')'),
    s.qris,
    'Pemasukan',
    'qris_setoran',
    CONCAT('AUTO_SYNC:SETORAN_ID:', s.id, ':EMPLOYEE:', s.employee_name, ':DATE:', s.tanggal),
    s.tanggal
FROM setoran s
WHERE s.qris > 0
    AND NOT EXISTS (
        SELECT 1 FROM cash_flow_management cfm
        WHERE cfm.notes LIKE CONCAT('%SETORAN_ID:', s.id, '%')
    );
```

---

## 🧪 Testing

### Test Case 1: New Setoran dengan QRIS
1. Buka `/index.php`
2. Input setoran dengan QRIS > 0
3. Click "Copy ke Clipboard"
4. **Expected:** Message "Data berhasil disimpan | QRIS otomatis masuk ke Cashflow Management"
5. Buka Admin Panel → Manajemen Kas
6. Filter by category "qris_setoran"
7. **Expected:** Ada entry baru dengan description "Pemasukan QRIS Setoran - ..."

### Test Case 2: Update Setoran (QRIS berubah)
1. Input setoran hari ini (misal QRIS: 100,000)
2. Input ulang setoran yang sama (QRIS: 150,000)
3. **Expected:** 
   - Entry lama di cashflow terhapus
   - Entry baru di-create dengan amount 150,000
   - Tidak ada duplicate

### Test Case 3: Dashboard Calculation
1. Buka Dashboard Wallet
2. Check "Total Pemasukan"
3. **Expected:** Tidak double count QRIS
4. Rumus: Setoran (Cash+QRIS) + Pemasukan Setoran + Cashflow (excl QRIS)

### Test Case 4: Zero QRIS
1. Input setoran dengan QRIS = 0
2. **Expected:** Tidak ada entry di cashflow management

---

## 📞 Support

Jika ada pertanyaan atau issue:
1. Check error log: `/var/log/supervisor/backend.*.log`
2. Verify database: Check tabel `cash_flow_management` untuk category `qris_setoran`
3. Debug transaction: Enable error reporting di `config.php`

---

## 🔄 Version History

**v1.0 (Current)**
- ✅ Auto-sync QRIS to cashflow
- ✅ Prevent double counting
- ✅ Update handling
- ✅ Transaction safety

**Future Enhancements:**
- [ ] Batch sync untuk data lama
- [ ] QRIS report dashboard
- [ ] Notification jika QRIS > threshold
- [ ] Export QRIS-only report

---

**Last Updated:** August 2025  
**Status:** ✅ Production Ready
