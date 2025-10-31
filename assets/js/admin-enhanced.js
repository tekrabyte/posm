/**
 * ADMIN PANEL ENHANCEMENT - Phase 1: Quick Wins + Security Integration
 * This file enhances admin.js with security features and UX improvements
 * Version: 1.0
 * Date: 2025-08
 */

// =============================================================================
// GLOBAL STATE & CONFIGURATION
// =============================================================================

const APP_CONFIG = {
    dateFormat: 'DD/MM/YYYY',
    currencyFormat: 'id-ID',
    loadingDelay: 300, // ms before showing loading
    toastDuration: 4000 // ms
};

// Track active modals for ESC key handling
let activeModals = [];

// =============================================================================
// ENHANCED UTILITY FUNCTIONS
// =============================================================================

/**
 * Format date consistently across the app
 * @param {string|Date} date - Date to format
 * @param {string} format - Format pattern (default: DD/MM/YYYY)
 * @returns {string} Formatted date
 */
function formatDate(date, format = 'DD/MM/YYYY') {
    if (!date) return '-';
    
    const d = new Date(date);
    if (isNaN(d.getTime())) return '-';
    
    const day = String(d.getDate()).padStart(2, '0');
    const month = String(d.getMonth() + 1).padStart(2, '0');
    const year = d.getFullYear();
    
    return format
        .replace('DD', day)
        .replace('MM', month)
        .replace('YYYY', year);
}

/**
 * Format currency with proper Rupiah format
 * @param {number} amount - Amount to format
 * @param {boolean} includeSymbol - Include Rp symbol
 * @returns {string} Formatted currency
 */
function formatCurrency(amount, includeSymbol = true) {
    const num = Number(amount);
    if (isNaN(num)) return includeSymbol ? 'Rp 0' : '0';
    
    const formatted = new Intl.NumberFormat('id-ID', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 0
    }).format(num);
    
    return includeSymbol ? `Rp ${formatted}` : formatted;
}

/**
 * Parse currency string to number
 * @param {string} str - Currency string (e.g., "Rp 15.000" or "15.000")
 * @returns {number} Parsed number
 */
function parseCurrency(str) {
    if (typeof str === 'number') return str;
    if (!str) return 0;
    return parseFloat(str.toString().replace(/[^0-9,-]/g, '').replace(',', '.')) || 0;
}

// =============================================================================
// ENHANCED MODAL MANAGEMENT WITH ESC KEY SUPPORT
// =============================================================================

/**
 * Show modal with proper tracking
 * @param {string} modalId - Modal element ID
 */
function showModalEnhanced(modalId) {
    const modal = document.getElementById(modalId);
    if (!modal) {
        console.warn(`Modal ${modalId} not found`);
        return;
    }
    
    // Add to active modals stack
    if (!activeModals.includes(modalId)) {
        activeModals.push(modalId);
    }
    
    // Show modal
    modal.classList.remove('hidden');
    modal.classList.add('flex', 'items-center', 'justify-center');
    modal.style.zIndex = 9999 + activeModals.length;
    
    // Add body overflow hidden
    document.body.style.overflow = 'hidden';
}

/**
 * Hide modal with proper cleanup
 * @param {string} modalId - Modal element ID
 */
function hideModalEnhanced(modalId) {
    const modal = document.getElementById(modalId);
    if (!modal) return;
    
    // Hide modal
    modal.classList.add('hidden');
    modal.classList.remove('flex', 'items-center', 'justify-center');
    modal.style.zIndex = '';
    
    // Remove from active modals
    activeModals = activeModals.filter(id => id !== modalId);
    
    // Restore body overflow if no modals
    if (activeModals.length === 0) {
        document.body.style.overflow = '';
    }
}

// ESC Key Handler for Modals
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape' && activeModals.length > 0) {
        // Close topmost modal
        const topModal = activeModals[activeModals.length - 1];
        hideModalEnhanced(topModal);
    }
});

// =============================================================================
// EMPTY STATE HANDLER
// =============================================================================

/**
 * Show empty state in table
 * @param {string} tableBodyId - Table body element ID
 * @param {string} message - Empty state message
 * @param {number} colspan - Number of columns
 */
function showEmptyState(tableBodyId, message = 'Tidak ada data untuk ditampilkan', colspan = 10) {
    const tbody = document.getElementById(tableBodyId);
    if (!tbody) return;
    
    tbody.innerHTML = `
        <tr>
            <td colspan="${colspan}" class="text-center py-12">
                <div class="flex flex-col items-center justify-center space-y-4">
                    <svg class="w-16 h-16 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                              d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    <p class="text-gray-500 font-medium">${message}</p>
                    <p class="text-gray-400 text-sm">Coba ubah filter atau tambahkan data baru</p>
                </div>
            </td>
        </tr>
    `;
}

/**
 * Show loading state in table
 * @param {string} tableBodyId - Table body element ID
 * @param {number} colspan - Number of columns
 */
function showTableLoading(tableBodyId, colspan = 10) {
    const tbody = document.getElementById(tableBodyId);
    if (!tbody) return;
    
    tbody.innerHTML = `
        <tr>
            <td colspan="${colspan}" class="text-center py-12">
                <div class="flex flex-col items-center justify-center space-y-4">
                    <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-indigo-600"></div>
                    <p class="text-gray-500 font-medium">Memuat data...</p>
                </div>
            </td>
        </tr>
    `;
}

// =============================================================================
// ENHANCED DELETE CONFIRMATION
// =============================================================================

/**
 * Show delete confirmation with proper dialog
 * @param {string} itemName - Name of item to delete
 * @param {Function} onConfirm - Callback on confirm
 * @returns {Promise<boolean>} User confirmation
 */
async function confirmDelete(itemName, onConfirm) {
    return new Promise((resolve) => {
        showConfirmDialog(
            `Apakah Anda yakin ingin menghapus "${itemName}"?<br><br>
            <span class="text-red-600 text-sm">⚠️ Tindakan ini tidak dapat dibatalkan!</span>`,
            async () => {
                showLoading('Menghapus data...');
                try {
                    await onConfirm();
                    resolve(true);
                } catch (error) {
                    console.error('Delete error:', error);
                    showToast('Gagal menghapus data: ' + error.message, 'error');
                    resolve(false);
                } finally {
                    hideLoading();
                }
            },
            () => {
                resolve(false);
            }
        );
    });
}

// =============================================================================
// TOOLTIP SYSTEM
// =============================================================================

/**
 * Initialize tooltips for elements with data-tooltip attribute
 */
function initializeTooltips() {
    // Create tooltip container
    let tooltipEl = document.getElementById('tooltip-container');
    if (!tooltipEl) {
        tooltipEl = document.createElement('div');
        tooltipEl.id = 'tooltip-container';
        tooltipEl.className = 'fixed bg-gray-900 text-white text-xs px-2 py-1 rounded shadow-lg z-50 hidden';
        document.body.appendChild(tooltipEl);
    }
    
    // Add event listeners to all elements with data-tooltip
    document.querySelectorAll('[data-tooltip]').forEach(element => {
        element.addEventListener('mouseenter', function(e) {
            const text = this.getAttribute('data-tooltip');
            if (!text) return;
            
            tooltipEl.textContent = text;
            tooltipEl.classList.remove('hidden');
            
            // Position tooltip
            const rect = this.getBoundingClientRect();
            tooltipEl.style.left = rect.left + (rect.width / 2) - (tooltipEl.offsetWidth / 2) + 'px';
            tooltipEl.style.top = rect.top - tooltipEl.offsetHeight - 5 + 'px';
        });
        
        element.addEventListener('mouseleave', function() {
            tooltipEl.classList.add('hidden');
        });
    });
}

// =============================================================================
// STICKY TABLE HEADER
// =============================================================================

/**
 * Make table headers sticky on scroll
 */
function enableStickyHeaders() {
    const style = document.createElement('style');
    style.textContent = `
        .sticky-header thead {
            position: sticky;
            top: 0;
            z-index: 10;
            background: white;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .sticky-header thead th {
            background: #f9fafb;
        }
    `;
    document.head.appendChild(style);
    
    // Add class to all tables
    document.querySelectorAll('table').forEach(table => {
        table.classList.add('sticky-header');
    });
}

// =============================================================================
// LOADING BUTTON STATE
// =============================================================================

/**
 * Set button loading state
 * @param {HTMLElement} button - Button element
 * @param {boolean} isLoading - Loading state
 * @param {string} loadingText - Text to show when loading
 */
function setButtonLoading(button, isLoading, loadingText = 'Memproses...') {
    if (!button) return;
    
    if (isLoading) {
        button.disabled = true;
        button.dataset.originalText = button.textContent;
        button.innerHTML = `
            <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white inline-block" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            ${loadingText}
        `;
    } else {
        button.disabled = false;
        button.textContent = button.dataset.originalText || button.textContent;
    }
}

// =============================================================================
// FORM VALIDATION ENHANCEMENTS
// =============================================================================

/**
 * Validate form with better error messages
 * @param {string} formId - Form element ID
 * @returns {boolean} Is form valid
 */
function validateFormEnhanced(formId) {
    const form = document.getElementById(formId);
    if (!form) return false;
    
    let isValid = true;
    const requiredFields = form.querySelectorAll('[required]');
    
    requiredFields.forEach(field => {
        clearFieldError(field.id);
        
        if (!field.value || field.value.trim() === '') {
            const label = field.previousElementSibling?.textContent || field.name || 'Field ini';
            showFieldError(field.id, `${label} wajib diisi`);
            isValid = false;
        }
        
        // Email validation
        if (field.type === 'email' && field.value && !validateEmail(field.value)) {
            showFieldError(field.id, 'Format email tidak valid');
            isValid = false;
        }
        
        // Number validation
        if (field.type === 'number' && field.value) {
            const num = parseFloat(field.value);
            if (isNaN(num)) {
                showFieldError(field.id, 'Harus berupa angka');
                isValid = false;
            }
            if (field.min && num < parseFloat(field.min)) {
                showFieldError(field.id, `Nilai minimum adalah ${field.min}`);
                isValid = false;
            }
            if (field.max && num > parseFloat(field.max)) {
                showFieldError(field.id, `Nilai maksimum adalah ${field.max}`);
                isValid = false;
            }
        }
    });
    
    if (!isValid) {
        showToast('Mohon lengkapi semua field yang wajib diisi', 'warning');
    }
    
    return isValid;
}

// =============================================================================
// ENHANCED API WRAPPER WITH SECURITY
// =============================================================================

/**
 * Enhanced API call with security, loading, and error handling
 * @param {string} url - API URL
 * @param {Object} options - Fetch options
 * @param {Object} config - Additional config (showLoading, showToast, etc.)
 * @returns {Promise} API response
 */
async function apiCall(url, options = {}, config = {}) {
    const {
        showLoadingState = true,
        loadingMessage = 'Memuat data...',
        showSuccessToast = false,
        successMessage = 'Berhasil!',
        showErrorToast = true
    } = config;
    
    try {
        // Show loading
        if (showLoadingState) {
            showLoading(loadingMessage);
        }
        
        // Use secureFetch from security.js
        const result = await secureFetch(url, options);
        
        // Hide loading
        if (showLoadingState) {
            hideLoading();
        }
        
        // Handle response
        if (result && result.success) {
            if (showSuccessToast) {
                showToast(result.message || successMessage, 'success');
            }
            return result;
        } else {
            if (showErrorToast) {
                showToast(result?.message || 'Terjadi kesalahan', 'error');
            }
            throw new Error(result?.message || 'API call failed');
        }
        
    } catch (error) {
        if (showLoadingState) {
            hideLoading();
        }
        
        if (showErrorToast) {
            const errorMsg = error.message || 'Terjadi kesalahan pada server';
            showToast(errorMsg, 'error');
        }
        
        console.error('API Error:', error);
        throw error;
    }
}

// =============================================================================
// REFRESH BUTTON WITH ANIMATION
// =============================================================================

/**
 * Add refresh functionality with animation
 */
function setupRefreshButton() {
    const refreshBtn = document.getElementById('refreshBtn');
    if (!refreshBtn) return;
    
    refreshBtn.addEventListener('click', function() {
        // Animate button
        const icon = this.querySelector('svg');
        if (icon) {
            icon.style.animation = 'spin 0.5s linear';
            setTimeout(() => {
                icon.style.animation = '';
            }, 500);
        }
        
        // Refresh current tab
        const activeTab = document.querySelector('.tab-button.active-tab');
        if (activeTab) {
            const tabName = activeTab.dataset.tab;
            showToast('Memuat ulang data...', 'info', 2000);
            
            // Trigger appropriate fetch function
            setTimeout(() => {
                if (window[`fetch${tabName.charAt(0).toUpperCase() + tabName.slice(1)}Data`]) {
                    window[`fetch${tabName.charAt(0).toUpperCase() + tabName.slice(1)}Data`]();
                } else {
                    location.reload();
                }
            }, 300);
        }
    });
}

// Add spin animation
const style = document.createElement('style');
style.textContent = `
    @keyframes spin {
        from { transform: rotate(0deg); }
        to { transform: rotate(360deg); }
    }
`;
document.head.appendChild(style);



// =============================================================================
// INITIALIZATION
// =============================================================================

/**
 * Initialize all enhancements on page load
 */
function initializeEnhancements() {
    console.log('🚀 Initializing Admin Panel Enhancements...');
    
    // Initialize tooltips
    initializeTooltips();
    
    // Enable sticky headers
    enableStickyHeaders();
    
    // Setup refresh button
    setupRefreshButton();
    
    // Override global showModal and hideModal
    window.showModal = showModalEnhanced;
    window.hideModal = hideModalEnhanced;
    
    // Add enhanced formatters to window
    window.formatDate = formatDate;
    window.formatCurrency = formatCurrency;
    window.parseCurrency = parseCurrency;
    window.apiCall = apiCall;
    window.confirmDelete = confirmDelete;
    window.showEmptyState = showEmptyState;
    window.showTableLoading = showTableLoading;
    window.setButtonLoading = setButtonLoading;
    window.validateFormEnhanced = validateFormEnhanced;
    
    console.log('✅ Admin Panel Enhancements Initialized!');
}

// Initialize when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeEnhancements);
} else {
    initializeEnhancements();
}

// Re-initialize tooltips on dynamic content changes
const observer = new MutationObserver((mutations) => {
    mutations.forEach((mutation) => {
        if (mutation.addedNodes.length) {
            initializeTooltips();
        }
    });
});

observer.observe(document.body, {
    childList: true,
    subtree: true
});
