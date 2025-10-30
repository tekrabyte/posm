/**
 * Security & UX Enhancements
 * - CSRF Token Management
 * - Toast Notifications
 * - Better Error Handling
 * - Session Timeout Handler
 */

// Global CSRF Token Storage
let csrfToken = '';

// Initialize CSRF Token
async function initializeCSRFToken() {
    try {
        const response = await fetch('../config/api.php?action=get_csrf_token');
        const result = await response.json();
        if (result.success && result.data && result.data.csrf_token) {
            csrfToken = result.data.csrf_token;
            console.log('CSRF Token initialized');
        }
    } catch (error) {
        console.error('Failed to initialize CSRF token:', error);
    }
}

// Get CSRF Token
function getCSRFToken() {
    return csrfToken;
}

// Set CSRF Token (after login or from response)
function setCSRFToken(token) {
    csrfToken = token;
}

// Enhanced Fetch with CSRF Token and Error Handling
async function secureFetch(url, options = {}) {
    // Add CSRF token to POST requests
    if (options.method === 'POST' || !options.method) {
        if (options.body) {
            // If body is JSON
            if (typeof options.body === 'string') {
                try {
                    const jsonBody = JSON.parse(options.body);
                    jsonBody.csrf_token = csrfToken;
                    options.body = JSON.stringify(jsonBody);
                } catch (e) {
                    // Body is not JSON, might be FormData
                }
            } else if (options.body instanceof FormData) {
                options.body.append('csrf_token', csrfToken);
            }
        } else {
            // No body, create one with CSRF token
            options.body = JSON.stringify({ csrf_token: csrfToken });
            options.headers = {
                ...options.headers,
                'Content-Type': 'application/json'
            };
        }
    }

    try {
        const response = await fetch(url, options);
        const data = await response.json();

        // Update CSRF token if provided in response
        if (data.csrf_token) {
            setCSRFToken(data.csrf_token);
        }

        // Handle session timeout
        if (response.status === 401 && data.message && data.message.includes('berakhir')) {
            showToast('Sesi Anda telah berakhir. Silakan login kembali.', 'error');
            setTimeout(() => {
                window.location.href = 'login.php';
            }, 2000);
            return null;
        }

        // Handle CSRF error
        if (response.status === 403 && data.message && data.message.includes('Token')) {
            showToast('Token keamanan tidak valid. Memuat ulang halaman...', 'error');
            setTimeout(() => {
                location.reload();
            }, 2000);
            return null;
        }

        // Handle network errors
        if (!response.ok && response.status >= 500) {
            showToast('Terjadi kesalahan server. Silakan coba lagi.', 'error');
            return { success: false, message: 'Server error' };
        }

        return data;

    } catch (error) {
        console.error('Fetch error:', error);
        
        // Network error
        if (error.message === 'Failed to fetch' || error.message.includes('NetworkError')) {
            showToast('Tidak dapat terhubung ke server. Periksa koneksi internet Anda.', 'error');
        } else {
            showToast('Terjadi kesalahan. Silakan coba lagi.', 'error');
        }
        
        return { success: false, message: error.message };
    }
}

// Toast Notification System
function showToast(message, type = 'info', duration = 4000) {
    // Create toast container if doesn't exist
    let toastContainer = document.getElementById('toastContainer');
    if (!toastContainer) {
        toastContainer = document.createElement('div');
        toastContainer.id = 'toastContainer';
        toastContainer.className = 'fixed top-4 right-4 z-50 space-y-2';
        document.body.appendChild(toastContainer);
    }

    // Create toast element
    const toast = document.createElement('div');
    toast.className = `toast-notification transform transition-all duration-300 ease-in-out translate-x-full opacity-0`;
    
    // Determine colors based on type
    let bgColor, iconSVG, borderColor;
    switch(type) {
        case 'success':
            bgColor = 'bg-green-500';
            borderColor = 'border-green-600';
            iconSVG = `
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                </svg>
            `;
            break;
        case 'error':
            bgColor = 'bg-red-500';
            borderColor = 'border-red-600';
            iconSVG = `
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            `;
            break;
        case 'warning':
            bgColor = 'bg-yellow-500';
            borderColor = 'border-yellow-600';
            iconSVG = `
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                </svg>
            `;
            break;
        default: // info
            bgColor = 'bg-blue-500';
            borderColor = 'border-blue-600';
            iconSVG = `
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
            `;
    }

    toast.innerHTML = `
        <div class="${bgColor} ${borderColor} border-l-4 text-white px-4 py-3 rounded shadow-lg flex items-center space-x-3 min-w-[300px] max-w-md">
            <div class="flex-shrink-0">
                ${iconSVG}
            </div>
            <div class="flex-1">
                <p class="text-sm font-medium">${message}</p>
            </div>
            <button onclick="this.parentElement.parentElement.remove()" class="flex-shrink-0 hover:bg-white hover:bg-opacity-20 rounded p-1 transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>
    `;

    toastContainer.appendChild(toast);

    // Animate in
    setTimeout(() => {
        toast.classList.remove('translate-x-full', 'opacity-0');
    }, 10);

    // Auto remove after duration
    setTimeout(() => {
        toast.classList.add('translate-x-full', 'opacity-0');
        setTimeout(() => {
            if (toast.parentElement) {
                toast.remove();
            }
        }, 300);
    }, duration);
}

// Loading Overlay
function showLoading(message = 'Memuat...') {
    let loadingOverlay = document.getElementById('loadingOverlay');
    if (!loadingOverlay) {
        loadingOverlay = document.createElement('div');
        loadingOverlay.id = 'loadingOverlay';
        loadingOverlay.className = 'fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center';
        loadingOverlay.innerHTML = `
            <div class="bg-white rounded-lg p-6 flex flex-col items-center space-y-4">
                <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-indigo-600"></div>
                <p class="text-gray-700 font-medium">${message}</p>
            </div>
        `;
        document.body.appendChild(loadingOverlay);
    } else {
        loadingOverlay.querySelector('p').textContent = message;
        loadingOverlay.classList.remove('hidden');
    }
}

function hideLoading() {
    const loadingOverlay = document.getElementById('loadingOverlay');
    if (loadingOverlay) {
        loadingOverlay.classList.add('hidden');
    }
}

// Confirmation Dialog
function showConfirmDialog(message, onConfirm, onCancel = null) {
    const dialog = document.createElement('div');
    dialog.className = 'fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4';
    dialog.innerHTML = `
        <div class="bg-white rounded-lg shadow-xl max-w-md w-full p-6">
            <div class="flex items-center space-x-3 mb-4">
                <div class="flex-shrink-0 w-10 h-10 bg-yellow-100 rounded-full flex items-center justify-center">
                    <svg class="w-6 h-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                    </svg>
                </div>
                <h3 class="text-lg font-semibold text-gray-900">Konfirmasi</h3>
            </div>
            <p class="text-gray-600 mb-6">${message}</p>
            <div class="flex justify-end space-x-3">
                <button id="cancelBtn" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition">
                    Batal
                </button>
                <button id="confirmBtn" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition">
                    Ya, Lanjutkan
                </button>
            </div>
        </div>
    `;
    
    document.body.appendChild(dialog);
    
    dialog.querySelector('#confirmBtn').onclick = () => {
        dialog.remove();
        if (onConfirm) onConfirm();
    };
    
    dialog.querySelector('#cancelBtn').onclick = () => {
        dialog.remove();
        if (onCancel) onCancel();
    };
    
    // Close on outside click
    dialog.onclick = (e) => {
        if (e.target === dialog) {
            dialog.remove();
            if (onCancel) onCancel();
        }
    };
}

// ESC key to close modals
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        // Close all visible modals
        const modals = document.querySelectorAll('.fixed.inset-0:not(.hidden)');
        modals.forEach(modal => {
            if (modal.id && modal.id.includes('modal') || modal.id.includes('Modal')) {
                const closeBtn = modal.querySelector('button[onclick*="hide"]');
                if (closeBtn) {
                    closeBtn.click();
                } else {
                    modal.classList.add('hidden');
                }
            }
        });
    }
});

// Input Validation
function validateEmail(email) {
    const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return re.test(email);
}

function validatePhone(phone) {
    const re = /^[\d\s\-\+\(\)]+$/;
    return re.test(phone) && phone.replace(/\D/g, '').length >= 10;
}

function validateRequired(value) {
    return value !== null && value !== undefined && value.toString().trim() !== '';
}

// Show validation error
function showFieldError(fieldId, message) {
    const field = document.getElementById(fieldId);
    if (!field) return;
    
    // Remove existing error
    const existingError = field.parentElement.querySelector('.field-error');
    if (existingError) {
        existingError.remove();
    }
    
    // Add error class to field
    field.classList.add('border-red-500', 'focus:border-red-500', 'focus:ring-red-500');
    
    // Create error message
    const errorEl = document.createElement('p');
    errorEl.className = 'field-error text-red-500 text-xs mt-1';
    errorEl.textContent = message;
    field.parentElement.appendChild(errorEl);
}

function clearFieldError(fieldId) {
    const field = document.getElementById(fieldId);
    if (!field) return;
    
    // Remove error class
    field.classList.remove('border-red-500', 'focus:border-red-500', 'focus:ring-red-500');
    
    // Remove error message
    const existingError = field.parentElement.querySelector('.field-error');
    if (existingError) {
        existingError.remove();
    }
}

// Initialize on page load
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeCSRFToken);
} else {
    initializeCSRFToken();
}
