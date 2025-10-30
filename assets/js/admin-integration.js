/**
 * Admin.js Integration Helper
 * Include this AFTER security.js and BEFORE admin.js
 * This provides backward compatibility while adding security features
 */

// =============================================================================
// FETCH WRAPPER - Auto-adds CSRF token to all POST requests to api.php
// =============================================================================

// Store original fetch
const _originalFetch = window.fetch;

// Override fetch for automatic CSRF protection
window.fetch = async function(url, options = {}) {
    // Check if this is a request to api.php
    const isApiRequest = typeof url === 'string' && url.includes('api.php');
    const isPostRequest = !options.method || options.method.toUpperCase() === 'POST';
    
    // If it's a POST to api.php, use secureFetch
    if (isApiRequest && isPostRequest) {
        try {
            // Use secureFetch which handles CSRF automatically
            const result = await secureFetch(url, options);
            
            // Return a Response-like object for backward compatibility
            return {
                ok: result && result.success !== false,
                status: result && result.success ? 200 : 400,
                json: async () => result,
                text: async () => JSON.stringify(result)
            };
        } catch (error) {
            console.error('Fetch error:', error);
            // Return error response
            return {
                ok: false,
                status: 500,
                json: async () => ({ success: false, message: error.message }),
                text: async () => JSON.stringify({ success: false, message: error.message })
            };
        }
    }
    
    // For other requests, use original fetch
    return _originalFetch(url, options);
};

// =============================================================================
// ENHANCED ALERT/CONFIRM - Better UX
// =============================================================================

// Store original functions
const _originalAlert = window.alert;
const _originalConfirm = window.confirm;

// Override alert with toast
window.alert = function(message) {
    // Determine toast type based on message
    let type = 'info';
    const lowerMessage = message.toLowerCase();
    
    if (lowerMessage.includes('berhasil') || lowerMessage.includes('sukses')) {
        type = 'success';
    } else if (lowerMessage.includes('gagal') || lowerMessage.includes('error') || lowerMessage.includes('salah')) {
        type = 'error';
    } else if (lowerMessage.includes('peringatan') || lowerMessage.includes('perhatian')) {
        type = 'warning';
    }
    
    showToast(message, type);
};

// Override confirm with dialog
window.confirm = function(message) {
    return new Promise((resolve) => {
        showConfirmDialog(
            message,
            () => resolve(true),
            () => resolve(false)
        );
    });
};

// Async confirm helper
window.confirmAsync = async function(message) {
    return new Promise((resolve) => {
        showConfirmDialog(
            message,
            () => resolve(true),
            () => resolve(false)
        );
    });
};

// =============================================================================
// LOADING HELPERS - Simplified API
// =============================================================================

// Auto-loading wrapper for async functions
window.withLoading = async function(promise, message = 'Memproses...') {
    showLoading(message);
    try {
        const result = await promise;
        hideLoading();
        return result;
    } catch (error) {
        hideLoading();
        throw error;
    }
};

// =============================================================================
// FORM VALIDATION HELPERS
// =============================================================================

// Validate entire form
window.validateForm = function(formId) {
    const form = document.getElementById(formId);
    if (!form) return false;
    
    let isValid = true;
    const requiredFields = form.querySelectorAll('[required]');
    
    requiredFields.forEach(field => {
        clearFieldError(field.id);
        
        if (!validateRequired(field.value)) {
            showFieldError(field.id, 'Field ini wajib diisi');
            isValid = false;
        } else if (field.type === 'email' && !validateEmail(field.value)) {
            showFieldError(field.id, 'Format email tidak valid');
            isValid = false;
        } else if (field.type === 'tel' && !validatePhone(field.value)) {
            showFieldError(field.id, 'Format nomor telepon tidak valid');
            isValid = false;
        }
    });
    
    if (!isValid) {
        showToast('Mohon lengkapi semua field yang wajib diisi', 'warning');
    }
    
    return isValid;
};

// Clear all form errors
window.clearFormErrors = function(formId) {
    const form = document.getElementById(formId);
    if (!form) return;
    
    const fields = form.querySelectorAll('input, select, textarea');
    fields.forEach(field => {
        if (field.id) clearFieldError(field.id);
    });
};

// =============================================================================
// AUTO SUCCESS/ERROR HANDLING
// =============================================================================

// Handle API response automatically
window.handleApiResponse = function(result, successMessage = 'Operasi berhasil!', errorMessage = null) {
    if (result && result.success) {
        showToast(successMessage, 'success');
        return true;
    } else {
        const msg = errorMessage || result?.message || 'Terjadi kesalahan';
        showToast(msg, 'error');
        return false;
    }
};

// =============================================================================
// UTILITY FUNCTIONS
// =============================================================================

// Debounce function for search/filter inputs
window.debounce = function(func, wait = 300) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
};

// Throttle function for scroll events
window.throttle = function(func, limit = 100) {
    let inThrottle;
    return function(...args) {
        if (!inThrottle) {
            func.apply(this, args);
            inThrottle = true;
            setTimeout(() => inThrottle = false, limit);
        }
    };
};

// Format currency helper (same as existing formatRupiah)
window.formatCurrency = function(number) {
    const num = Number(number);
    if (isNaN(num)) return 'Rp 0';
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0
    }).format(num);
};

// Format date helper
window.formatDate = function(dateString, format = 'DD/MM/YYYY') {
    if (!dateString) return '-';
    const date = new Date(dateString);
    
    const day = String(date.getDate()).padStart(2, '0');
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const year = date.getFullYear();
    const hours = String(date.getHours()).padStart(2, '0');
    const minutes = String(date.getMinutes()).padStart(2, '0');
    
    return format
        .replace('DD', day)
        .replace('MM', month)
        .replace('YYYY', year)
        .replace('HH', hours)
        .replace('mm', minutes);
};

// =============================================================================
// INITIALIZATION
// =============================================================================

console.log('✅ Admin Integration Helper loaded');
console.log('📦 Features enabled:');
console.log('  - Auto CSRF protection for fetch()');
console.log('  - Enhanced alert() with toast notifications');
console.log('  - Enhanced confirm() with modal dialogs');
console.log('  - Form validation helpers');
console.log('  - Loading state helpers');
console.log('  - API response handlers');

// Add visual indicator that security is active
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', addSecurityIndicator);
} else {
    addSecurityIndicator();
}

function addSecurityIndicator() {
    // Add a small security badge to header (optional)
    const header = document.querySelector('header');
    if (header) {
        const badge = document.createElement('div');
        badge.className = 'hidden'; // Hidden by default, can be shown for debugging
        badge.innerHTML = `
            <div class="fixed bottom-4 right-4 bg-green-500 text-white px-3 py-1 rounded-full text-xs font-medium shadow-lg">
                🔒 Security Active
            </div>
        `;
        document.body.appendChild(badge);
    }
}

// =============================================================================
// BACKWARD COMPATIBILITY NOTES
// =============================================================================

/*
With this integration helper, existing code will work with minimal changes:

1. FETCH CALLS - No change needed!
   OLD: await fetch('api.php?action=add_store', {...})
   NEW: Same code works! CSRF automatically added.

2. ALERTS - No change needed!
   OLD: alert('Berhasil!')
   NEW: Same code works! Shows as toast.

3. CONFIRMS - Async required for proper handling
   OLD: if (confirm('Yakin?')) { delete(); }
   NEW: const confirmed = await confirmAsync('Yakin?');
        if (confirmed) { delete(); }
   
   OR use callback:
   confirmAsync('Yakin?').then(confirmed => {
       if (confirmed) delete();
   });

4. LOADING STATES - New feature, add where needed
   await withLoading(saveData(), 'Menyimpan...');

5. FORM VALIDATION - New feature, add where needed
   if (validateForm('myForm')) {
       submitForm();
   }
*/
