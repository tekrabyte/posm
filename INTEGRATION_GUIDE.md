# 🔧 INTEGRATION GUIDE - Security Features

## 📋 Cara Mengintegrasikan Security Features ke Admin.js

### ⚠️ PENTING
File `/app/assets/js/admin.js` saat ini masih menggunakan `fetch()` biasa. Untuk mengaktifkan CSRF protection, Anda perlu mengupdate semua panggilan `fetch()` menjadi `secureFetch()`.

---

## 🔄 Step-by-Step Integration

### Option 1: Manual Update (Recommended untuk Production)

**Cari semua instance `fetch()` di admin.js dan ganti dengan `secureFetch()`**

#### Contoh Before:
```javascript
const response = await fetch('api.php?action=get_stores');
const result = await response.json();
```

#### Contoh After:
```javascript
const result = await secureFetch('api.php?action=get_stores');
```

**Note:** `secureFetch()` sudah otomatis return JSON, jadi tidak perlu `.json()` lagi.

---

### Option 2: Auto-Wrapper (Quick Setup)

Tambahkan ini di bagian atas admin.js SETELAH security.js di-load:

```javascript
// Wrap existing fetch untuk backward compatibility
const originalFetch = window.fetch;
window.fetch = async function(url, options = {}) {
    // Jika URL adalah api.php dan method POST, gunakan secureFetch
    if (url.includes('api.php') && (!options.method || options.method === 'POST')) {
        return secureFetch(url, options);
    }
    // Untuk request lainnya, gunakan fetch biasa
    return originalFetch(url, options);
};
```

**⚠️ Warning:** Option ini mengoverride global fetch(), bisa conflict dengan library lain.

---

## 📝 UPDATE PATTERNS

### 1. **GET Requests (No Change Needed)**
```javascript
// Tetap aman, tidak perlu CSRF untuk GET
const response = await fetch('api.php?action=get_stores');
const result = await response.json();
```

### 2. **POST Requests (MUST Update)**

#### ❌ Before (Vulnerable):
```javascript
const response = await fetch('api.php?action=add_store', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ store_name: 'Toko 1' })
});
const result = await response.json();
```

#### ✅ After (Protected):
```javascript
const result = await secureFetch('api.php?action=add_store', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ store_name: 'Toko 1' })
});
// CSRF token otomatis ditambahkan!
```

### 3. **FormData Submissions**

#### ❌ Before:
```javascript
const formData = new FormData();
formData.append('store_name', 'Toko 1');

const response = await fetch('api.php?action=add_store', {
    method: 'POST',
    body: formData
});
```

#### ✅ After:
```javascript
const formData = new FormData();
formData.append('store_name', 'Toko 1');

const result = await secureFetch('api.php?action=add_store', {
    method: 'POST',
    body: formData
});
// CSRF token otomatis ditambahkan ke FormData!
```

---

## 🎯 LOCATIONS TO UPDATE IN admin.js

Berdasarkan struktur yang terlihat, update di area berikut:

### 1. **Logout Handler** (Line ~67)
```javascript
// Before
document.getElementById('logoutBtn').addEventListener('click', async function () {
    if (confirm('Anda yakin ingin logout?')) {
        await fetch('api.php?action=logout');
        window.location.href = 'login.php';
    }
});

// After
document.getElementById('logoutBtn').addEventListener('click', async function () {
    showConfirmDialog('Anda yakin ingin logout?', async () => {
        showLoading('Logging out...');
        await secureFetch('api.php?action=logout');
        hideLoading();
        window.location.href = 'login.php';
    });
});
```

### 2. **Store Operations**
```javascript
// Add Store
async function addStore(storeData) {
    showLoading('Menyimpan store...');
    const result = await secureFetch('api.php?action=add_store', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(storeData)
    });
    hideLoading();
    
    if (result && result.success) {
        showToast('Store berhasil ditambahkan!', 'success');
        fetchStores(); // Refresh list
        hideModal('storeModal');
    } else {
        showToast(result.message || 'Gagal menambahkan store', 'error');
    }
}

// Delete Store
async function deleteStore(storeId) {
    showConfirmDialog('Yakin ingin menghapus store ini?', async () => {
        showLoading('Menghapus store...');
        const result = await secureFetch('api.php?action=delete_store', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: storeId })
        });
        hideLoading();
        
        if (result && result.success) {
            showToast('Store berhasil dihapus!', 'success');
            fetchStores();
        } else {
            showToast(result.message || 'Gagal menghapus store', 'error');
        }
    });
}
```

### 3. **Employee Operations**
```javascript
// Similar pattern untuk add/edit/delete employee
async function addEmployee(employeeData) {
    showLoading('Menyimpan karyawan...');
    const result = await secureFetch('api.php?action=add_employee', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(employeeData)
    });
    hideLoading();
    
    if (result && result.success) {
        showToast('Karyawan berhasil ditambahkan!', 'success');
        fetchEmployees();
        hideModal('employeeModal');
    } else {
        showToast(result.message || 'Gagal menambahkan karyawan', 'error');
    }
}
```

### 4. **Setoran Operations**
```javascript
async function saveSetoran(setoranData) {
    showLoading('Menyimpan setoran...');
    const result = await secureFetch('api.php?action=save_setoran', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(setoranData)
    });
    hideLoading();
    
    if (result && result.success) {
        showToast('Setoran berhasil disimpan!', 'success');
        fetchSetoranData();
    } else {
        showToast(result.message || 'Gagal menyimpan setoran', 'error');
    }
}

async function deleteSetoran(setoranId) {
    showConfirmDialog(
        'Yakin ingin menghapus setoran ini? Data tidak dapat dikembalikan.',
        async () => {
            showLoading('Menghapus setoran...');
            const result = await secureFetch('api.php?action=delete_setoran', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: setoranId })
            });
            hideLoading();
            
            if (result && result.success) {
                showToast('Setoran berhasil dihapus!', 'success');
                fetchSetoranData();
            } else {
                showToast(result.message || 'Gagal menghapus setoran', 'error');
            }
        }
    );
}
```

### 5. **Cashflow Operations**
```javascript
async function saveCashflow(cashflowData) {
    showLoading('Menyimpan transaksi kas...');
    const result = await secureFetch('api.php?action=add_management_cash_flow', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(cashflowData)
    });
    hideLoading();
    
    if (result && result.success) {
        showToast('Transaksi kas berhasil disimpan!', 'success');
        fetchCashFlowData();
        hideModal('modalCashflow');
    } else {
        showToast(result.message || 'Gagal menyimpan transaksi', 'error');
    }
}
```

---

## 🎨 ENHANCED USER FEEDBACK

### Replace All `alert()` and `confirm()`

#### ❌ Old Way:
```javascript
if (confirm('Yakin hapus?')) {
    deleteItem();
}
alert('Berhasil!');
```

#### ✅ New Way:
```javascript
showConfirmDialog('Yakin hapus?', () => {
    deleteItem();
});
showToast('Berhasil!', 'success');
```

---

## ✅ VALIDATION EXAMPLES

### Form Validation Before Submit

```javascript
async function handleStoreForm(event) {
    event.preventDefault();
    
    const storeName = document.getElementById('store_name').value;
    const storeAddress = document.getElementById('store_address').value;
    
    // Clear previous errors
    clearFieldError('store_name');
    clearFieldError('store_address');
    
    // Validate
    let hasError = false;
    
    if (!validateRequired(storeName)) {
        showFieldError('store_name', 'Nama store harus diisi');
        hasError = true;
    }
    
    if (!validateRequired(storeAddress)) {
        showFieldError('store_address', 'Alamat store harus diisi');
        hasError = true;
    }
    
    if (hasError) {
        showToast('Mohon lengkapi semua field yang wajib diisi', 'warning');
        return;
    }
    
    // Submit
    await addStore({ store_name: storeName, address: storeAddress });
}
```

---

## 🔍 TESTING CHECKLIST

Setelah melakukan integrasi, test hal berikut:

### Security Testing:
- [ ] Login dengan credentials valid
- [ ] Login dengan credentials invalid
- [ ] Test session timeout (tunggu 30 menit atau adjust timeout di config.php untuk testing)
- [ ] Test CSRF protection: coba submit form tanpa token (should fail)
- [ ] Test tambah store/employee/setoran
- [ ] Test edit store/employee/setoran
- [ ] Test delete store/employee/setoran

### UX Testing:
- [ ] Toast notification muncul saat success
- [ ] Toast notification muncul saat error
- [ ] Loading overlay muncul saat proses
- [ ] Confirmation dialog muncul saat delete
- [ ] ESC key menutup modal
- [ ] Validation error ditampilkan dengan jelas
- [ ] Mobile table scrollable

### Error Handling:
- [ ] Matikan internet, test error message
- [ ] Test dengan server down
- [ ] Test dengan session expired

---

## 🚨 COMMON ISSUES & SOLUTIONS

### Issue 1: "Token keamanan tidak valid"
**Cause:** CSRF token tidak dikirim atau expired
**Solution:** 
- Pastikan menggunakan `secureFetch()` untuk POST requests
- Check browser console untuk error
- Refresh halaman untuk generate token baru

### Issue 2: "Sesi Anda telah berakhir"
**Cause:** Session timeout setelah 30 menit
**Solution:** 
- Normal behavior, user harus login ulang
- Untuk development, bisa adjust timeout di `/app/config.php`

### Issue 3: Toast tidak muncul
**Cause:** `security.js` tidak di-load atau conflict
**Solution:**
- Check browser console untuk error
- Pastikan `security.js` di-load SEBELUM `admin.js`
- Check order di `admin.php`: `<script src="assets/js/security.js"></script>` harus sebelum `admin.js`

### Issue 4: Modal tidak tertutup dengan ESC
**Cause:** Modal structure berbeda
**Solution:**
- Check modal ID contains 'modal' atau 'Modal'
- Check ada tombol close dengan `onclick="hideModal()"`

---

## 📦 QUICK START CHECKLIST

Untuk mengaktifkan semua fitur security:

1. ✅ Pastikan semua file sudah ter-upload:
   - `/app/security.php`
   - `/app/assets/js/security.js`
   - `/app/assets/css/admin.css` (updated)

2. ✅ Update `/app/admin.php`:
   - Include `security.php`
   - Load `security.js` sebelum `admin.js`
   - Pass CSRF token ke JavaScript

3. ✅ Update `/app/api.php`:
   - Include `security.php`
   - Add CSRF validation

4. ✅ Update `/app/config.php`:
   - Session configuration

5. ✅ Update `/app/login.php`:
   - Enhanced UX

6. ⚠️ **TODO:** Update `/app/assets/js/admin.js`:
   - Replace `fetch()` dengan `secureFetch()` untuk POST
   - Replace `alert()` dengan `showToast()`
   - Replace `confirm()` dengan `showConfirmDialog()`
   - Add loading states dengan `showLoading()` / `hideLoading()`

---

## 🎯 PRIORITY UPDATE ORDER

**High Priority (MUST Update):**
1. All POST requests (add/edit/delete operations)
2. Logout handler
3. Form submissions

**Medium Priority (SHOULD Update):**
1. Replace alert() dengan showToast()
2. Replace confirm() dengan showConfirmDialog()
3. Add loading states

**Low Priority (NICE TO HAVE):**
1. Form validation
2. Error field highlighting
3. Advanced UX enhancements

---

## 📞 SUPPORT

Jika ada pertanyaan atau masalah:
1. Check browser console untuk error messages
2. Check PHP error logs di server
3. Review `/app/SECURITY_IMPLEMENTATION_REPORT.md` untuk detail lengkap
4. Test dengan browser DevTools Network tab untuk debug AJAX calls

---

**Dokumentasi ini dibuat oleh E1 AI Agent**  
**Last Updated:** August 2025  
**Version:** 1.0

---
