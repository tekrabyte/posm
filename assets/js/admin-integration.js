/**
 * Admin.js Integration Helper - FIXED VERSION
 * No circular references, safe for production
 */

// =============================================================================
// SECURE FETCH WRAPPER - No circular references
// =============================================================================

// Create a secure fetch wrapper without overriding original fetch
window.secureFetch = async function(url, options = {}) {
    const isApiRequest = typeof url === 'string' && url.includes('api.php');
    const isPostRequest = (!options.method || options.method.toUpperCase() === 'POST');
    
    try {
        // For API POST requests, add CSRF protection
        if (isApiRequest && isPostRequest) {
            return await handleSecureApiCall(url, options);
        }
        
        // For other requests, use original fetch
        return await window._originalFetch(url, options);
        
    } catch (error) {
        console.error('Secure fetch error:', error);
        throw error;
    }
};

// Store original fetch for fallback
window._originalFetch = window.fetch;

// Handle secure API calls with CSRF
async function handleSecureApiCall(url, options) {
    try {
        // Get CSRF token first
        const csrfToken = await getCSRFToken();
        
        // Prepare headers
        const headers = {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest',
            ...options.headers
        };
        
        // Add CSRF token to form data or URL
        let body = options.body;
        if (body instanceof FormData) {
            body.append('csrf_token', csrfToken);
        } else if (typeof body === 'string') {
            body += `&csrf_token=${encodeURIComponent(csrfToken)}`;
        } else {
            body = `csrf_token=${encodeURIComponent(csrfToken)}`;
        }
        
        // Make the request using ORIGINAL fetch to avoid recursion
        const response = await window._originalFetch(url, {
            ...options,
            headers,
            body
        });
        
        return await parseResponse(response);
        
    } catch (error) {
        console.error('API call failed:', error);
        return {
            success: false,
            message: 'Network error: ' + error.message
        };
    }
}

// Get CSRF token from server
async function getCSRFToken() {
    try {
        const response = await window._originalFetch('security.php?action=get_token');
        const data = await response.json();
        return data.token || '';
    } catch (error) {
        console.warn('Failed to get CSRF token:', error);
        return '';
    }
}

// Parse response safely
async function parseResponse(response) {
    try {
        const text = await response.text();
        
        // Try to parse as JSON
        try {
            return JSON.parse(text);
        } catch (e) {
            // If not JSON, return as text
            return {
                success: response.ok,
                message: text,
                raw: text
            };
        }
    } catch (error) {
        return {
            success: false,
            message: 'Failed to parse response: ' + error.message
        };
    }
}

// =============================================================================
// ENHANCED ALERT/CONFIRM - Safe implementation
// =============================================================================

// Safe alert replacement
window.safeAlert = function(message, type = 'info') {
    if (typeof showToast === 'function') {
        showToast(message, type);
    } else {
        // Fallback to original alert
        console.log(`[${type.toUpperCase()}] ${message}`);
        alert(message);
    }
};

// Safe confirm replacement
window.safeConfirm = function(message, title = 'Konfirmasi') {
    return new Promise((resolve) => {
        if (typeof showConfirmDialog === 'function') {
            showConfirmDialog(
                message,
                () => resolve(true),
                () => resolve(false),
                title
            );
        } else {
            // Fallback to original confirm
            const result = confirm(message);
            resolve(result);
        }
    });
};

// =============================================================================
// LOADING HELPERS - Safe implementation
// =============================================================================

window.withLoading = async function(promise, message = 'Memproses...') {
    const loadingId = 'loading-' + Date.now();
    
    try {
        showLoading(message, loadingId);
        const result = await promise;
        return result;
    } catch (error) {
        throw error;
    } finally {
        hideLoading(loadingId);
    }
};

// Safe loading functions with ID support
function showLoading(message = 'Loading...', id = 'default') {
    // Check if loading already exists
    const existingLoader = document.getElementById(`loader-${id}`);
    if (existingLoader) return;
    
    const loader = document.createElement('div');
    loader.id = `loader-${id}`;
    loader.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50';
    loader.innerHTML = `
        <div class="bg-white rounded-lg p-6 flex items-center space-x-3">
            <div class="animate-spin rounded-full h-6 w-6 border-b-2 border-blue-600"></div>
            <span class="text-gray-700">${message}</span>
        </div>
    `;
    
    document.body.appendChild(loader);
}

function hideLoading(id = 'default') {
    const loader = document.getElementById(`loader-${id}`);
    if (loader) {
        loader.remove();
    }
}

// =============================================================================
// FORM VALIDATION - Safe implementation
// =============================================================================

window.validateForm = function(formSelector) {
    const form = typeof formSelector === 'string' 
        ? document.querySelector(formSelector)
        : formSelector;
    
    if (!form) {
        console.error('Form not found:', formSelector);
        return false;
    }
    
    let isValid = true;
    const requiredFields = form.querySelectorAll('[required]');
    
    requiredFields.forEach(field => {
        if (field.id) clearFieldError(field.id);
        
        if (!field.value.trim()) {
            if (field.id) showFieldError(field.id, 'Field ini wajib diisi');
            isValid = false;
        }
    });
    
    if (!isValid) {
        safeAlert('Mohon lengkapi semua field yang wajib diisi', 'warning');
    }
    
    return isValid;
};

// Safe field error functions
function showFieldError(fieldId, message) {
    const field = document.getElementById(fieldId);
    if (!field) return;
    
    // Remove existing error
    clearFieldError(fieldId);
    
    // Add error class
    field.classList.add('border-red-500', 'bg-red-50');
    
    // Create error message
    const errorElement = document.createElement('div');
    errorElement.className = 'text-red-600 text-sm mt-1';
    errorElement.id = `error-${fieldId}`;
    errorElement.textContent = message;
    
    // Insert after field
    field.parentNode.appendChild(errorElement);
}

function clearFieldError(fieldId) {
    const field = document.getElementById(fieldId);
    const errorElement = document.getElementById(`error-${fieldId}`);
    
    if (field) {
        field.classList.remove('border-red-500', 'bg-red-50');
    }
    
    if (errorElement) {
        errorElement.remove();
    }
}

// =============================================================================
// AUTO-INITIALIZATION - Safe and controlled
// =============================================================================

class AdminIntegration {
    constructor() {
        this.initialized = false;
        this.init = this.init.bind(this);
    }
    
    init() {
        if (this.initialized) return;
        
        try {
            console.log('🚀 Initializing Admin Integration...');
            
            this.setupFormInterceptors();
            this.setupButtonHandlers();
            this.addSecurityIndicator();
            
            this.initialized = true;
            console.log('✅ Admin Integration initialized successfully');
            
        } catch (error) {
            console.error('❌ Admin Integration initialization failed:', error);
        }
    }
    
    setupFormInterceptors() {
        // Intercept form submissions
        document.addEventListener('submit', (e) => {
            const form = e.target;
            
            // Only handle forms with specific attribute
            if (form.hasAttribute('data-enhanced')) {
                e.preventDefault();
                this.handleFormSubmit(form);
            }
        });
    }
    
    setupButtonHandlers() {
        // Enhanced delete buttons
        document.addEventListener('click', async (e) => {
            const button = e.target.closest('[data-action="delete"]');
            if (button) {
                e.preventDefault();
                await this.handleDelete(button);
            }
        });
    }
    
    async handleFormSubmit(form) {
        if (!validateForm(form)) return;
        
        try {
            const formData = new FormData(form);
            const action = form.getAttribute('action') || window.location.href;
            const method = form.getAttribute('method') || 'POST';
            
            const result = await withLoading(
                secureFetch(action, {
                    method: method,
                    body: formData
                }),
                'Menyimpan data...'
            );
            
            if (result.success) {
                safeAlert(result.message || 'Data berhasil disimpan!', 'success');
                
                // Optional: reset form or redirect
                if (form.hasAttribute('data-reset-on-success')) {
                    form.reset();
                }
                
                if (result.redirect) {
                    setTimeout(() => {
                        window.location.href = result.redirect;
                    }, 1500);
                }
            } else {
                safeAlert(result.message || 'Terjadi kesalahan!', 'error');
            }
            
        } catch (error) {
            safeAlert('Network error: ' + error.message, 'error');
        }
    }
    
    async handleDelete(button) {
        const message = button.getAttribute('data-confirm') || 'Yakin ingin menghapus?';
        const url = button.getAttribute('href') || button.getAttribute('data-url');
        
        if (!url) {
            safeAlert('Delete URL tidak ditemukan', 'error');
            return;
        }
        
        const confirmed = await safeConfirm(message);
        if (!confirmed) return;
        
        try {
            const result = await withLoading(
                secureFetch(url, { method: 'POST' }),
                'Menghapus...'
            );
            
            if (result.success) {
                safeAlert(result.message || 'Data berhasil dihapus!', 'success');
                
                // Remove element or reload
                if (button.closest('tr')) {
                    button.closest('tr').remove();
                } else if (result.reload) {
                    setTimeout(() => location.reload(), 1000);
                }
            } else {
                safeAlert(result.message || 'Gagal menghapus data!', 'error');
            }
            
        } catch (error) {
            safeAlert('Network error: ' + error.message, 'error');
        }
    }
    
    addSecurityIndicator() {
        // Only show in development
        if (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1') {
            const indicator = document.createElement('div');
            indicator.className = 'fixed bottom-4 right-4 bg-green-500 text-white px-3 py-1 rounded-full text-xs font-medium shadow-lg z-50';
            indicator.textContent = '🔒 Security Active';
            document.body.appendChild(indicator);
        }
    }
}

// =============================================================================
// INITIALIZATION - Safe and controlled
// =============================================================================

// Create global instance
window.adminIntegration = new AdminIntegration();

// Initialize when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', window.adminIntegration.init);
} else {
    window.adminIntegration.init();
}

console.log('✅ Admin Integration Helper loaded safely');
console.log('📦 Available features:');
console.log('  - secureFetch() for API calls');
console.log('  - safeAlert() for notifications');
console.log('  - safeConfirm() for confirmations');
console.log('  - withLoading() for async operations');
console.log('  - validateForm() for form validation');