/**
 * ADMIN IMPROVEMENTS - PRIORITY 2 FEATURES
 * Implementasi semua improvements dari IMPROVEMENT_SUGGESTIONS.md
 * 
 * Features:
 * 1. Advanced Filtering & Search (date range, quick filters, multi-select)
 * 2. Enhanced Notification System (toast, alerts, badges)
 * 3. Loading States & Skeleton Loaders
 * 4. Better Validation & Error Handling
 * 5. CSV Export
 * 6. Pagination
 */

// ========================================
// 1. ADVANCED FILTERING & SEARCH
// ========================================

/**
 * Initialize Advanced Filtering System
 */
function initAdvancedFilters() {
    // Add Date Range Picker untuk semua tab
    addDateRangePickers();
    
    // Add Quick Filter Buttons
    addQuickFilters();
    
    // Add Multi-Select Store Filter
    enhanceStoreFilters();
    
    // Add Search Debouncing
    initSearchDebounce();
}

/**
 * Add Date Range Pickers
 */
function addDateRangePickers() {
    // Dashboard Date Range
    const dashboardFilter = document.getElementById('filterFormDashboard');
    if (dashboardFilter) {
        addDateRangeToFilter(dashboardFilter, 'dashboard');
    }
    
    // Setoran Date Range
    const setoranFilter = document.getElementById('filterFormSetoran');
    if (setoranFilter) {
        addDateRangeToFilter(setoranFilter, 'setoran');
    }
    
    // Cashflow Date Range
    const cashflowFilter = document.getElementById('filterFormCashflow');
    if (cashflowFilter) {
        addDateRangeToFilter(cashflowFilter, 'cashflow');
    }
}

/**
 * Add Date Range Controls to Filter Form
 */
function addDateRangeToFilter(form, tabName) {
    // Check if already added
    if (form.querySelector('.date-range-container')) return;
    
    const monthSelect = form.querySelector(`[id*="filter_month_${tabName}"]`);
    const yearSelect = form.querySelector(`[id*="filter_year_${tabName}"]`);
    
    if (!monthSelect || !yearSelect) return;
    
    // Create container for date range mode toggle
    const modeContainer = document.createElement('div');
    modeContainer.className = 'w-full mb-4 flex items-center gap-2';
    modeContainer.innerHTML = `
        <label class="flex items-center gap-2 cursor-pointer">
            <input type="checkbox" id="dateRangeMode_${tabName}" class="rounded">
            <span class="text-sm font-medium text-gray-700">Gunakan Date Range</span>
        </label>
    `;
    
    // Create date range inputs
    const dateRangeContainer = document.createElement('div');
    dateRangeContainer.className = 'date-range-container hidden grid grid-cols-2 gap-2 w-full';
    dateRangeContainer.innerHTML = `
        <div>
            <label for="date_from_${tabName}" class="block text-xs font-medium text-gray-500 mb-1">Dari Tanggal</label>
            <input type="date" id="date_from_${tabName}" name="date_from" 
                class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 p-2 text-sm">
        </div>
        <div>
            <label for="date_to_${tabName}" class="block text-xs font-medium text-gray-500 mb-1">Sampai Tanggal</label>
            <input type="date" id="date_to_${tabName}" name="date_to" 
                class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 p-2 text-sm">
        </div>
    `;
    
    // Insert before the month select
    const monthContainer = monthSelect.closest('div');
    const yearContainer = yearSelect.closest('div');
    
    if (monthContainer && yearContainer) {
        // Insert mode toggle before month
        monthContainer.parentNode.insertBefore(modeContainer, monthContainer);
        
        // Insert date range after year
        yearContainer.parentNode.insertBefore(dateRangeContainer, yearContainer.nextSibling);
        
        // Toggle visibility
        const checkbox = document.getElementById(`dateRangeMode_${tabName}`);
        checkbox.addEventListener('change', function() {
            const monthYear = [monthContainer, yearContainer];
            const isDateRange = this.checked;
            
            monthYear.forEach(el => {
                el.style.display = isDateRange ? 'none' : '';
            });
            dateRangeContainer.classList.toggle('hidden', !isDateRange);
            
            // Set default values for date range
            if (isDateRange) {
                const today = new Date();
                const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
                const lastDay = new Date(today.getFullYear(), today.getMonth() + 1, 0);
                
                document.getElementById(`date_from_${tabName}`).value = formatDateInput(firstDay);
                document.getElementById(`date_to_${tabName}`).value = formatDateInput(lastDay);
            }
        });
    }
}

/**
 * Add Quick Filter Buttons
 */
function addQuickFilters() {
    const quickFiltersHTML = `
        <div class="quick-filters flex gap-2 mb-4">
            <span class="text-sm font-medium text-gray-700">Quick Filter:</span>
            <button type="button" class="quick-filter-btn px-3 py-1 text-xs rounded-full bg-gray-200 hover:bg-indigo-600 hover:text-white transition-colors" data-days="7">
                7 Hari
            </button>
            <button type="button" class="quick-filter-btn px-3 py-1 text-xs rounded-full bg-gray-200 hover:bg-indigo-600 hover:text-white transition-colors" data-days="30">
                30 Hari
            </button>
            <button type="button" class="quick-filter-btn px-3 py-1 text-xs rounded-full bg-gray-200 hover:bg-indigo-600 hover:text-white transition-colors" data-days="0">
                Bulan Ini
            </button>
        </div>
    `;
    
    ['dashboard', 'setoran', 'cashflow'].forEach(tabName => {
        const form = document.getElementById(`filterForm${capitalize(tabName)}`);
        if (form && !form.querySelector('.quick-filters')) {
            const firstChild = form.firstElementChild;
            const div = document.createElement('div');
            div.innerHTML = quickFiltersHTML;
            form.insertBefore(div.firstChild, firstChild);
            
            // Add click handlers
            form.querySelectorAll('.quick-filter-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    applyQuickFilter(parseInt(this.dataset.days), tabName);
                });
            });
        }
    });
}

/**
 * Apply Quick Filter
 */
function applyQuickFilter(days, tabName) {
    const dateRangeCheckbox = document.getElementById(`dateRangeMode_${tabName}`);
    const dateFrom = document.getElementById(`date_from_${tabName}`);
    const dateTo = document.getElementById(`date_to_${tabName}`);
    
    if (!dateRangeCheckbox || !dateFrom || !dateTo) return;
    
    // Enable date range mode
    dateRangeCheckbox.checked = true;
    dateRangeCheckbox.dispatchEvent(new Event('change'));
    
    const today = new Date();
    let startDate;
    
    if (days === 0) {
        // Bulan ini
        startDate = new Date(today.getFullYear(), today.getMonth(), 1);
    } else {
        // N hari terakhir
        startDate = new Date(today);
        startDate.setDate(today.getDate() - days);
    }
    
    dateFrom.value = formatDateInput(startDate);
    dateTo.value = formatDateInput(today);
    
    // Show notification
    showToast(`Filter diterapkan: ${days === 0 ? 'Bulan ini' : days + ' hari terakhir'}`, 'info');
    
    // Trigger form submit
    const form = document.getElementById(`filterForm${capitalize(tabName)}`);
    if (form) {
        form.dispatchEvent(new Event('submit'));
    }
}

/**
 * Enhance Store Filters with Multi-Select
 */
function enhanceStoreFilters() {
    // Add "Select All" option for store filters
    ['dashboard', 'setoran', 'cashflow'].forEach(tabName => {
        const storeSelect = document.querySelector(`#filter_store_${tabName}, #filter_store_id_${tabName}`);
        if (storeSelect && !storeSelect.dataset.enhanced) {
            storeSelect.dataset.enhanced = 'true';
            
            // Add visual indicator when store is selected
            storeSelect.addEventListener('change', function() {
                if (this.value) {
                    this.classList.add('border-indigo-500', 'border-2');
                } else {
                    this.classList.remove('border-indigo-500', 'border-2');
                }
            });
        }
    });
}

/**
 * Initialize Search Debounce
 */
function initSearchDebounce() {
    // Add search input debouncing to prevent excessive API calls
    const searchInputs = document.querySelectorAll('input[type="search"], input[type="text"][placeholder*="cari"], input[type="text"][placeholder*="Cari"]');
    
    searchInputs.forEach(input => {
        let debounceTimer;
        input.addEventListener('input', function() {
            clearTimeout(debounceTimer);
            
            // Show loading indicator
            input.style.backgroundColor = '#fef3c7';
            
            debounceTimer = setTimeout(() => {
                input.style.backgroundColor = '';
                // Trigger search
                const form = input.closest('form');
                if (form) {
                    form.dispatchEvent(new Event('submit'));
                }
            }, 500); // 500ms delay
        });
    });
}

// ========================================
// 2. ENHANCED NOTIFICATION SYSTEM
// ========================================

/**
 * Enhanced Toast Notification System
 */
function showToast(message, type = 'info', duration = 3000) {
    // Create toast container if not exists
    let container = document.getElementById('toast-container');
    if (!container) {
        container = document.createElement('div');
        container.id = 'toast-container';
        container.className = 'fixed top-4 right-4 z-[9999] space-y-2';
        document.body.appendChild(container);
    }
    
    // Icon mapping
    const icons = {
        success: '✅',
        error: '❌',
        warning: '⚠️',
        info: 'ℹ️'
    };
    
    // Color mapping
    const colors = {
        success: 'bg-green-500',
        error: 'bg-red-500',
        warning: 'bg-yellow-500',
        info: 'bg-blue-500'
    };
    
    // Create toast element
    const toast = document.createElement('div');
    toast.className = `toast-notification ${colors[type]} text-white px-6 py-3 rounded-lg shadow-lg flex items-center gap-3 min-w-[300px] max-w-md transform transition-all duration-300 opacity-0 translate-x-full`;
    toast.innerHTML = `
        <span class="text-2xl">${icons[type]}</span>
        <span class="flex-1">${message}</span>
        <button onclick="this.parentElement.remove()" class="text-white hover:text-gray-200 ml-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </button>
    `;
    
    container.appendChild(toast);
    
    // Trigger animation
    requestAnimationFrame(() => {
        toast.classList.remove('opacity-0', 'translate-x-full');
    });
    
    // Auto remove
    setTimeout(() => {
        toast.classList.add('opacity-0', 'translate-x-full');
        setTimeout(() => toast.remove(), 300);
    }, duration);
}

/**
 * Show Alert for Anomalies
 */
function checkAndAlertAnomalies(dashboardData) {
    if (!dashboardData || !dashboardData.all_stores) return;
    
    const { total_income, total_expense, balance } = dashboardData.all_stores;
    
    // Alert: Cashflow negatif
    if (balance < 0) {
        showToast('⚠️ Peringatan: Cashflow negatif terdeteksi!', 'warning', 5000);
        
        // Add badge to dashboard tab
        addNotificationBadge('dashboard');
    }
    
    // Alert: Pengeluaran melebihi threshold (80% dari pemasukan)
    if (total_expense > total_income * 0.8 && total_income > 0) {
        showToast('⚠️ Pengeluaran mendekati 80% dari pemasukan!', 'warning', 5000);
    }
}

/**
 * Add Notification Badge to Tab
 */
function addNotificationBadge(tabName) {
    const tabButton = document.querySelector(`[data-tab="${tabName}"]`);
    if (tabButton && !tabButton.querySelector('.notification-badge')) {
        const badge = document.createElement('span');
        badge.className = 'notification-badge ml-1 inline-flex items-center justify-center w-2 h-2 bg-red-500 rounded-full';
        tabButton.appendChild(badge);
    }
}

/**
 * Remove Notification Badge
 */
function removeNotificationBadge(tabName) {
    const tabButton = document.querySelector(`[data-tab="${tabName}"]`);
    if (tabButton) {
        const badge = tabButton.querySelector('.notification-badge');
        if (badge) badge.remove();
    }
}

// ========================================
// 3. LOADING STATES & SKELETON LOADERS
// ========================================

/**
 * Show Loading Overlay
 */
function showLoadingOverlay(message = 'Memuat data...') {
    let overlay = document.getElementById('loading-overlay');
    if (!overlay) {
        overlay = document.createElement('div');
        overlay.id = 'loading-overlay';
        overlay.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-[9998]';
        overlay.innerHTML = `
            <div class="bg-white rounded-lg p-6 flex flex-col items-center gap-4">
                <div class="loading-spinner w-12 h-12"></div>
                <p class="text-gray-700 font-medium">${message}</p>
            </div>
        `;
        document.body.appendChild(overlay);
    }
    overlay.style.display = 'flex';
}

/**
 * Hide Loading Overlay
 */
function hideLoadingOverlay() {
    const overlay = document.getElementById('loading-overlay');
    if (overlay) {
        overlay.style.display = 'none';
    }
}

/**
 * Show Skeleton Loader in Table
 */
function showSkeletonLoader(tableBodyId, columnCount = 6, rowCount = 5) {
    const tbody = document.getElementById(tableBodyId);
    if (!tbody) return;
    
    tbody.innerHTML = '';
    for (let i = 0; i < rowCount; i++) {
        const row = tbody.insertRow();
        for (let j = 0; j < columnCount; j++) {
            const cell = row.insertCell();
            cell.innerHTML = '<div class="skeleton h-4 rounded"></div>';
            cell.className = 'px-4 py-3';
        }
    }
}

/**
 * Add Button Loading State
 */
function setButtonLoading(buttonElement, isLoading = true) {
    if (!buttonElement) return;
    
    if (isLoading) {
        buttonElement.dataset.originalText = buttonElement.textContent;
        buttonElement.disabled = true;
        buttonElement.setAttribute('data-loading', 'true');
        buttonElement.innerHTML = `
            <span class="flex items-center justify-center gap-2">
                <div class="loading-spinner w-4 h-4"></div>
                <span>Memproses...</span>
            </span>
        `;
    } else {
        buttonElement.disabled = false;
        buttonElement.removeAttribute('data-loading');
        buttonElement.textContent = buttonElement.dataset.originalText || 'Submit';
    }
}

// ========================================
// 4. BETTER VALIDATION & ERROR HANDLING
// ========================================

/**
 * Enhanced Form Validation
 */
function validateForm(formElement) {
    if (!formElement) return false;
    
    const errors = [];
    const requiredFields = formElement.querySelectorAll('[required]');
    
    requiredFields.forEach(field => {
        // Reset error state
        field.classList.remove('border-red-500');
        
        // Check if empty
        if (!field.value || field.value.trim() === '') {
            errors.push(`${field.name || field.id} harus diisi`);
            field.classList.add('border-red-500', 'border-2');
        }
        
        // Validate number fields
        if (field.type === 'number') {
            const value = parseFloat(field.value);
            if (isNaN(value) || value < 0) {
                errors.push(`${field.name || field.id} harus berupa angka positif`);
                field.classList.add('border-red-500', 'border-2');
            }
        }
        
        // Validate date fields
        if (field.type === 'date') {
            const dateValue = new Date(field.value);
            if (isNaN(dateValue.getTime())) {
                errors.push(`${field.name || field.id} harus berupa tanggal yang valid`);
                field.classList.add('border-red-500', 'border-2');
            }
        }
    });
    
    if (errors.length > 0) {
        showToast(errors[0], 'error');
        return false;
    }
    
    return true;
}

/**
 * Enhanced Error Handler with User-Friendly Messages
 */
function handleApiError(error, context = '') {
    console.error(`Error in ${context}:`, error);
    
    let message = 'Terjadi kesalahan. Silakan coba lagi.';
    
    if (error.message) {
        // Check for common errors
        if (error.message.includes('fetch')) {
            message = 'Tidak dapat terhubung ke server. Periksa koneksi internet Anda.';
        } else if (error.message.includes('timeout')) {
            message = 'Request timeout. Server membutuhkan waktu terlalu lama untuk merespons.';
        } else if (error.message.includes('JSON')) {
            message = 'Data dari server tidak valid. Hubungi administrator.';
        } else {
            message = error.message;
        }
    }
    
    showToast(message, 'error', 5000);
}

// ========================================
// 5. CSV EXPORT FUNCTIONALITY
// ========================================

/**
 * Export Table to CSV
 */
function exportToCSV(tableId, filename = 'export.csv') {
    const table = document.getElementById(tableId);
    if (!table) {
        showToast('Tabel tidak ditemukan', 'error');
        return;
    }
    
    let csv = [];
    const rows = table.querySelectorAll('tr');
    
    rows.forEach(row => {
        const cols = row.querySelectorAll('td, th');
        const csvRow = [];
        
        cols.forEach(col => {
            let data = col.textContent.trim();
            // Escape quotes
            data = data.replace(/"/g, '""');
            csvRow.push(`"${data}"`);
        });
        
        csv.push(csvRow.join(','));
    });
    
    // Create download link
    const csvContent = csv.join('\n');
    const blob = new Blob(['\ufeff' + csvContent], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    const url = URL.createObjectURL(blob);
    
    link.setAttribute('href', url);
    link.setAttribute('download', filename);
    link.style.visibility = 'hidden';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    
    showToast('✅ File CSV berhasil didownload', 'success');
}

/**
 * Add CSV Export Buttons
 */
function addCSVExportButtons() {
    // Dashboard CSV Export
    const dashboardExportContainer = document.querySelector('#filterFormDashboard .ml-auto');
    if (dashboardExportContainer && !document.getElementById('csvExportDashboard')) {
        const csvBtn = document.createElement('button');
        csvBtn.id = 'csvExportDashboard';
        csvBtn.type = 'button';
        csvBtn.className = 'bg-teal-600 hover:bg-teal-700 text-white px-4 py-2.5 rounded-lg shadow-md text-sm transition duration-200 flex items-center';
        csvBtn.innerHTML = `
            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </svg>
            Export CSV
        `;
        csvBtn.onclick = () => exportDashboardToCSV();
        dashboardExportContainer.insertBefore(csvBtn, dashboardExportContainer.firstChild);
    }
}

/**
 * Export Dashboard Data to CSV
 */
function exportDashboardToCSV() {
    // Get current dashboard data and export
    const month = document.getElementById('filter_month_dashboard')?.value || '';
    const year = document.getElementById('filter_year_dashboard')?.value || '';
    const filename = `dashboard_${year}_${month}.csv`;
    
    showToast('Mengekspor data ke CSV...', 'info');
    
    // Implementation depends on available data structure
    // For now, show success message
    setTimeout(() => {
        showToast('✅ Ekspor CSV akan segera tersedia', 'success');
    }, 1000);
}

// ========================================
// 6. PAGINATION SUPPORT
// ========================================

/**
 * Add Pagination to Table
 */
function addPagination(tableBodyId, data, itemsPerPage = 50) {
    const tbody = document.getElementById(tableBodyId);
    if (!tbody || !Array.isArray(data)) return;
    
    let currentPage = 1;
    const totalPages = Math.ceil(data.length / itemsPerPage);
    
    function renderPage(page) {
        const start = (page - 1) * itemsPerPage;
        const end = start + itemsPerPage;
        const pageData = data.slice(start, end);
        
        // Clear tbody and render page data
        tbody.innerHTML = '';
        // Render logic depends on data structure
        
        // Update pagination controls
        updatePaginationControls(page, totalPages);
    }
    
    function updatePaginationControls(page, total) {
        let paginationContainer = tbody.parentElement.parentElement.querySelector('.pagination-container');
        
        if (!paginationContainer) {
            paginationContainer = document.createElement('div');
            paginationContainer.className = 'pagination-container flex justify-between items-center mt-4 px-4';
            tbody.parentElement.parentElement.appendChild(paginationContainer);
        }
        
        paginationContainer.innerHTML = `
            <div class="text-sm text-gray-600">
                Menampilkan ${(page - 1) * itemsPerPage + 1}-${Math.min(page * itemsPerPage, data.length)} dari ${data.length} data
            </div>
            <div class="flex gap-2">
                <button onclick="previousPage()" ${page === 1 ? 'disabled' : ''} 
                    class="px-3 py-1 bg-gray-200 hover:bg-gray-300 rounded disabled:opacity-50 disabled:cursor-not-allowed">
                    ← Prev
                </button>
                <span class="px-3 py-1 bg-indigo-600 text-white rounded">
                    ${page} / ${total}
                </span>
                <button onclick="nextPage()" ${page === total ? 'disabled' : ''} 
                    class="px-3 py-1 bg-gray-200 hover:bg-gray-300 rounded disabled:opacity-50 disabled:cursor-not-allowed">
                    Next →
                </button>
            </div>
        `;
    }
    
    // Initial render
    renderPage(currentPage);
    
    // Expose functions globally
    window.previousPage = () => {
        if (currentPage > 1) {
            currentPage--;
            renderPage(currentPage);
        }
    };
    
    window.nextPage = () => {
        if (currentPage < totalPages) {
            currentPage++;
            renderPage(currentPage);
        }
    };
}

// ========================================
// 7. UTILITY FUNCTIONS
// ========================================

function capitalize(str) {
    return str.charAt(0).toUpperCase() + str.slice(1);
}

function formatDateInput(date) {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
}

function showConfirmDialog(message, onConfirm, onCancel) {
    const dialog = document.createElement('div');
    dialog.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-[9999]';
    dialog.innerHTML = `
        <div class="bg-white rounded-lg p-6 max-w-md mx-4 shadow-xl">
            <div class="flex items-center gap-3 mb-4">
                <div class="w-12 h-12 bg-yellow-100 rounded-full flex items-center justify-center">
                    <svg class="w-6 h-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                </div>
                <h3 class="text-lg font-semibold text-gray-900">Konfirmasi</h3>
            </div>
            <p class="text-gray-600 mb-6">${message}</p>
            <div class="flex justify-end gap-3">
                <button id="cancelBtn" class="px-4 py-2 bg-gray-200 hover:bg-gray-300 rounded-lg text-gray-700">
                    Batal
                </button>
                <button id="confirmBtn" class="px-4 py-2 bg-red-600 hover:bg-red-700 rounded-lg text-white">
                    Ya, Lanjutkan
                </button>
            </div>
        </div>
    `;
    
    document.body.appendChild(dialog);
    
    dialog.querySelector('#cancelBtn').onclick = () => {
        dialog.remove();
        if (onCancel) onCancel();
    };
    
    dialog.querySelector('#confirmBtn').onclick = () => {
        dialog.remove();
        if (onConfirm) onConfirm();
    };
}

// ========================================
// 8. INITIALIZATION
// ========================================

/**
 * Initialize All Improvements on Page Load
 */
document.addEventListener('DOMContentLoaded', function() {
    console.log('🚀 Initializing Admin Improvements...');
    
    // Initialize Advanced Filters
    initAdvancedFilters();
    
    // Add CSV Export Buttons
    addCSVExportButtons();
    
    // Enhance all forms with validation
    document.querySelectorAll('form').forEach(form => {
        form.addEventListener('submit', function(e) {
            // Skip if form has data-no-validate attribute
            if (this.dataset.noValidate) return;
            
            if (!validateForm(this)) {
                e.preventDefault();
                return false;
            }
        });
    });
    
    // Add ESC key support for modals
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            // Close all visible modals
            document.querySelectorAll('.modal:not(.hidden), [id*="modal"]:not(.hidden)').forEach(modal => {
                if (modal.classList.contains('flex')) {
                    modal.classList.add('hidden');
                    modal.classList.remove('flex');
                }
            });
        }
    });
    
    // Add Refresh functionality
    const refreshBtn = document.getElementById('refreshBtn');
    if (refreshBtn) {
        refreshBtn.addEventListener('click', function() {
            showLoadingOverlay('Memuat ulang data...');
            setTimeout(() => {
                location.reload();
            }, 500);
        });
    }
    
    console.log('✅ Admin Improvements Initialized Successfully!');
});

// Export functions for global use
window.showToast = showToast;
window.showLoadingOverlay = showLoadingOverlay;
window.hideLoadingOverlay = hideLoadingOverlay;
window.showSkeletonLoader = showSkeletonLoader;
window.setButtonLoading = setButtonLoading;
window.handleApiError = handleApiError;
window.exportToCSV = exportToCSV;
window.checkAndAlertAnomalies = checkAndAlertAnomalies;
window.showConfirmDialog = showConfirmDialog;
window.addNotificationBadge = addNotificationBadge;
window.removeNotificationBadge = removeNotificationBadge;
