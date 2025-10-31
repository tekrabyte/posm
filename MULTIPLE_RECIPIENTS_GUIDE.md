# 📧 Panduan Multiple Email Recipients

## Overview
Sistem email POM MINI sekarang mendukung pengiriman ke **multiple recipients** (lebih dari 1 penerima email).

## Format Email Recipients

Anda dapat memasukkan multiple email addresses menggunakan separator:

### ✅ Separator yang Didukung:

1. **Koma (,)**
   ```
   email1@example.com, email2@example.com, email3@example.com
   ```

2. **Semicolon (;)**
   ```
   email1@example.com; email2@example.com; email3@example.com
   ```

3. **Kombinasi (Mixed)**
   ```
   email1@example.com, email2@example.com; email3@example.com
   ```

### 📝 Contoh Valid:

```
admin@pompom.com, manager@pompom.com
admin@pompom.com; manager@pompom.com; supervisor@pompom.com
john.doe@gmail.com, jane.doe@yahoo.com, admin@company.com
```

## Cara Setup di Database

### Update Email Config di Database

```sql
UPDATE email_config 
SET recipient_email = 'email1@example.com, email2@example.com, email3@example.com'
WHERE id = 1;
```

### Contoh Lengkap:

```sql
UPDATE email_config 
SET 
    smtp_host = 'smtp.gmail.com',
    smtp_port = 587,
    smtp_username = 'your-email@gmail.com',
    smtp_password = 'your-app-password',
    recipient_email = 'admin@pompom.com, manager@pompom.com, supervisor@pompom.com',
    is_enabled = 1
WHERE id = 1;
```

## Validasi Email

Sistem akan otomatis:
- ✅ **Trim** whitespace dari setiap email
- ✅ **Validate** format email (RFC compliant)
- ✅ **Skip** email yang tidak valid
- ✅ **Send** hanya ke email yang valid

### Contoh Validasi:

Input:
```
admin@pompom.com,  , manager@pompom.com, invalid-email, boss@company.com
```

Output (email yang akan dikirim):
```
✅ admin@pompom.com
✅ manager@pompom.com
✅ boss@company.com
❌ (empty) - SKIPPED
❌ invalid-email - SKIPPED (invalid format)
```

## Testing

### Test Multiple Recipients via Admin Panel

1. Login ke admin panel
2. Pergi ke **Email Configuration**
3. Masukkan multiple emails di field **Recipient Email**:
   ```
   email1@test.com, email2@test.com
   ```
4. Klik **Test Connection** atau **Save**
5. Submit setoran untuk test email notification

### Test via Direct SQL

```sql
-- Set multiple recipients
UPDATE email_config 
SET recipient_email = 'test1@example.com, test2@example.com'
WHERE id = 1;

-- Check current config
SELECT recipient_email FROM email_config WHERE id = 1;
```

## Success Messages

Setelah email terkirim, Anda akan melihat pesan:

**Single Recipient:**
```
Email sent successfully
```

**Multiple Recipients:**
```
Email sent successfully to 3 recipient(s)
Laporan harian berhasil dikirim ke 3 recipient(s)
```

## Error Handling

### Jika Semua Email Invalid:

```
Error: No valid recipient email addresses found
```

### Jika Email Config Kosong:

```
Error: Email configuration incomplete
```

## Tips & Best Practices

1. **Gunakan Email Valid**
   - Pastikan semua email address valid dan aktif
   - Test dengan 1-2 email dulu sebelum add banyak

2. **Separator Consistency**
   - Pilih 1 separator (koma atau semicolon)
   - Tapi mixed juga tetap bisa

3. **Whitespace OK**
   - Sistem akan auto-trim whitespace
   - `email1@test.com,  email2@test.com` ✅ OK

4. **Maximum Recipients**
   - Tidak ada hard limit
   - Recommended: maksimal 10-20 recipients
   - Untuk lebih banyak, consider mailing list

5. **Gmail App Password**
   - Jika pakai Gmail, gunakan App Password
   - Bukan password Gmail biasa
   - Generate di: https://myaccount.google.com/apppasswords

## Troubleshooting

### Email tidak sampai ke semua recipient?

**Cek:**
1. Format email valid? (no typos)
2. Email tidak di spam folder?
3. SMTP settings correct?
4. Check email logs di database:
   ```sql
   SELECT * FROM email_notifications 
   ORDER BY sent_at DESC 
   LIMIT 10;
   ```

### Hanya sebagian email yang dikirim?

Sistem akan skip email yang invalid dan continue ke yang valid.

**Check log:**
```bash
tail -f /var/log/email_error.log
```

## Implementation Details

### Method: `parseRecipients()`

```php
/**
 * Parse multiple recipient emails
 * Support separators: comma (,) and semicolon (;)
 */
private function parseRecipients($recipientString) {
    // Replace semicolons with commas
    $recipientString = str_replace(';', ',', $recipientString);
    
    // Split by comma
    $emails = explode(',', $recipientString);
    
    // Clean up and validate
    $validEmails = [];
    foreach ($emails as $email) {
        $email = trim($email);
        
        if (!empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $validEmails[] = $email;
        }
    }
    
    return $validEmails;
}
```

### Usage di Code:

```php
// Automatic - EmailHandler will handle multiple recipients
$emailHandler = new EmailHandler($pdo);
$emailHandler->sendNotification($subject, $message, 'setoran', $id);
```

## Support

Jika ada masalah atau pertanyaan, hubungi tim development.

---
**Last Updated:** 31 Januari 2025
**Version:** 1.0
