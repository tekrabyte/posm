/**
 * Real-time Polling & Email Notification Management
 * Polling interval: 5 seconds
 * Auto-reload data when changes detected
 */

// Global state
let pollingInterval = null;
let lastCheckTime = new Date().toISOString();
let isPollingActive = false;

/**
 * Initialize real-time polling
 */
function initRealtimePolling() {
    console.log('🔄 Initializing real-time polling...');
    
    // Start polling
    startPolling();
    
    // Cleanup on page unload
    window.addEventListener('beforeunload', () => {
        stopPolling();
    });
}

/**
 * Start polling
 */
function startPolling() {
    if (isPollingActive) {
        console.log('⚠️ Polling already active');
        return;
    }
    
    isPollingActive = true;
    console.log('✅ Polling started - checking every 5 seconds');
    
    // Update indicator
    updatePollingIndicator(true);
    
    // Poll immediately
    pollForChanges();
    
    // Then poll every 5 seconds
    pollingInterval = setInterval(pollForChanges, 5000);
}

/**
 * Stop polling
 */
function stopPolling() {
    if (!isPollingActive) {
        return;
    }
    
    isPollingActive = false;
    
    if (pollingInterval) {
        clearInterval(pollingInterval);
        pollingInterval = null;
    }
    
    console.log('⛔ Polling stopped');
    updatePollingIndicator(false);
}

/**
 * Poll for changes
 */
async function pollForChanges() {
    try {
        const response = await fetch(`../config/realtime_api.php?action=get_latest_changes&last_check=${encodeURIComponent(lastCheckTime)}`);
        
        if (!response.ok) {
            throw new Error('Polling request failed');
        }
        
        const result = await response.json();
        
        if (result.success && result.has_changes) {
            console.log('🆕 New changes detected:', result.changes);
            
            // Show toast notification
            showToast('Data baru terdeteksi! Memperbarui halaman...', 'info');
            
            // Reload current active tab data
            reloadCurrentTabData();
            
            // Play notification sound (optional)
            playNotificationSound();
        }
        
        // Update last check time
        if (result.current_time) {
            lastCheckTime = result.current_time;
        }
        
    } catch (error) {
        console.error('❌ Polling error:', error);
        // Don't stop polling on error, just log it
    }
}

/**
 * Reload current active tab data
 */
function reloadCurrentTabData() {
    const activeTab = document.querySelector('.tab-button.active-tab');
    
    if (!activeTab) {
        return;
    }
    
    const tabName = activeTab.getAttribute('data-tab');
    
    console.log(`🔄 Reloading tab: ${tabName}`);
    
    // Reload based on active tab
    switch (tabName) {
        case 'dashboard':
            if (typeof fetchDashboardData === 'function') {
                fetchDashboardData();
            }
            break;
            
        case 'setoran':
            if (typeof fetchSetoranData === 'function') {
                fetchSetoranData();
            }
            break;
            
        case 'cashflow':
            if (typeof fetchCashFlowData === 'function') {
                fetchCashFlowData();
            }
            break;
            
        case 'stores':
            if (typeof fetchStores === 'function') {
                fetchStores();
            }
            break;
            
        case 'employees':
            if (typeof fetchEmployees === 'function') {
                fetchEmployees();
            }
            break;
            
        default:
            console.log('No reload handler for tab:', tabName);
    }
}

/**
 * Update polling indicator
 */
function updatePollingIndicator(isActive) {
    const indicator = document.getElementById('realtimeStatusIndicator');
    
    if (!indicator) {
        return;
    }
    
    if (isActive) {
        indicator.innerHTML = `
            <div class="w-3 h-3 bg-green-400 rounded-full animate-pulse"></div>
            <span class="text-sm font-medium">Active</span>
        `;
    } else {
        indicator.innerHTML = `
            <div class="w-3 h-3 bg-red-400 rounded-full"></div>
            <span class="text-sm font-medium">Inactive</span>
        `;
    }
}

/**
 * Play notification sound (optional)
 */
function playNotificationSound() {
    try {
        // Simple beep using Web Audio API
        const audioContext = new (window.AudioContext || window.webkitAudioContext)();
        const oscillator = audioContext.createOscillator();
        const gainNode = audioContext.createGain();
        
        oscillator.connect(gainNode);
        gainNode.connect(audioContext.destination);
        
        oscillator.frequency.value = 800;
        oscillator.type = 'sine';
        
        gainNode.gain.setValueAtTime(0.3, audioContext.currentTime);
        gainNode.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 0.2);
        
        oscillator.start(audioContext.currentTime);
        oscillator.stop(audioContext.currentTime + 0.2);
    } catch (error) {
        // Silent fail - sound is optional
    }
}

/**
 * Email Settings Management
 */

// Load email config
async function loadEmailConfig() {
    try {
        const response = await fetch('../config/realtime_api.php?action=get_email_config');
        const result = await response.json();
        
        if (result.success && result.config) {
            const config = result.config;
            
            // Populate form
            document.getElementById('smtp_host').value = config.smtp_host || 'smtp.gmail.com';
            document.getElementById('smtp_port').value = config.smtp_port || 587;
            document.getElementById('smtp_username').value = config.smtp_username || '';
            document.getElementById('smtp_password').value = config.smtp_password || '';
            document.getElementById('recipient_email').value = config.recipient_email || '';
            document.getElementById('is_enabled').checked = config.is_enabled == 1;
            
            // Update status badge
            updateEmailStatusBadge(config.is_enabled == 1);
        }
    } catch (error) {
        console.error('Failed to load email config:', error);
        showToast('Gagal memuat konfigurasi email', 'error');
    }
}

// Save email config
async function saveEmailConfig(formData) {
    try {
        showLoading('Menyimpan konfigurasi...');
        
        const response = await fetch('../config/realtime_api.php?action=update_email_config', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(formData)
        });
        
        const result = await response.json();
        
        hideLoading();
        
        if (result.success) {
            showToast('Konfigurasi email berhasil disimpan!', 'success');
            updateEmailStatusBadge(formData.is_enabled);
        } else {
            showToast('Gagal menyimpan: ' + result.message, 'error');
        }
        
        return result;
    } catch (error) {
        hideLoading();
        console.error('Failed to save email config:', error);
        showToast('Error: ' + error.message, 'error');
        return { success: false };
    }
}

// Test email connection
async function testEmailConnection() {
    try {
        showLoading('Testing connection...');
        
        const response = await fetch('../config/realtime_api.php?action=test_email');
        const result = await response.json();
        
        hideLoading();
        
        if (result.success) {
            showToast('✅ Koneksi berhasil! SMTP settings valid.', 'success');
        } else {
            showToast('❌ Koneksi gagal: ' + result.message, 'error');
        }
    } catch (error) {
        hideLoading();
        showToast('Error: ' + error.message, 'error');
    }
}

// Send test email
async function sendTestEmail() {
    try {
        showLoading('Mengirim test email...');
        
        const response = await fetch('../config/realtime_api.php?action=send_test_email');
        const result = await response.json();
        
        hideLoading();
        
        if (result.success) {
            showToast('✅ Test email berhasil dikirim! Cek inbox Anda.', 'success');
        } else {
            showToast('❌ Gagal mengirim: ' + result.message, 'error');
        }
    } catch (error) {
        hideLoading();
        showToast('Error: ' + error.message, 'error');
    }
}

// Load notification history
async function loadNotificationHistory() {
    try {
        const response = await fetch('../config/realtime_api.php?action=get_notification_history&limit=50');
        const result = await response.json();
        
        if (result.success && result.history) {
            renderNotificationHistory(result.history);
        }
    } catch (error) {
        console.error('Failed to load notification history:', error);
    }
}

// Render notification history
function renderNotificationHistory(history) {
    const tbody = document.getElementById('emailHistoryTableBody');
    
    if (!tbody) {
        return;
    }
    
    if (history.length === 0) {
        tbody.innerHTML = '<tr><td colspan="5" class="text-center py-4 text-gray-500">Belum ada history notifikasi</td></tr>';
        return;
    }
    
    tbody.innerHTML = history.map(item => {
        const statusClass = item.status === 'sent' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800';
        const statusIcon = item.status === 'sent' ? '✅' : '❌';
        
        return `
            <tr>
                <td class="px-4 py-2 text-sm text-gray-700">${item.id}</td>
                <td class="px-4 py-2 text-sm">
                    <span class="px-2 py-1 bg-blue-100 text-blue-800 rounded-full text-xs">
                        ${item.notification_type}
                    </span>
                </td>
                <td class="px-4 py-2 text-sm text-gray-700">${item.subject}</td>
                <td class="px-4 py-2 text-sm">
                    <span class="px-2 py-1 ${statusClass} rounded-full text-xs">
                        ${statusIcon} ${item.status}
                    </span>
                </td>
                <td class="px-4 py-2 text-sm text-gray-500">
                    ${item.sent_at ? new Date(item.sent_at).toLocaleString('id-ID') : '-'}
                </td>
            </tr>
        `;
    }).join('');
}

// Update email status badge
function updateEmailStatusBadge(isEnabled) {
    const badge = document.getElementById('emailStatusBadge');
    
    if (!badge) {
        return;
    }
    
    if (isEnabled) {
        badge.className = 'text-xs px-3 py-1 rounded-full bg-green-500 text-white';
        badge.textContent = '✅ Enabled';
    } else {
        badge.className = 'text-xs px-3 py-1 rounded-full bg-red-500 text-white';
        badge.textContent = '❌ Disabled';
    }
}

/**
 * Initialize event listeners
 */
document.addEventListener('DOMContentLoaded', function() {
    
    // Start real-time polling
    initRealtimePolling();
    
    // Email config form submission
    const emailConfigForm = document.getElementById('emailConfigForm');
    if (emailConfigForm) {
        emailConfigForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = {
                smtp_host: document.getElementById('smtp_host').value,
                smtp_port: parseInt(document.getElementById('smtp_port').value),
                smtp_username: document.getElementById('smtp_username').value,
                smtp_password: document.getElementById('smtp_password').value,
                recipient_email: document.getElementById('recipient_email').value,
                is_enabled: document.getElementById('is_enabled').checked ? 1 : 0
            };
            
            await saveEmailConfig(formData);
        });
    }
    
    // Test connection button
    const testConnectionBtn = document.getElementById('testConnectionBtn');
    if (testConnectionBtn) {
        testConnectionBtn.addEventListener('click', testEmailConnection);
    }
    
    // Test email button
    const testEmailBtn = document.getElementById('testEmailBtn');
    if (testEmailBtn) {
        testEmailBtn.addEventListener('click', sendTestEmail);
    }
    
    // Send daily report button
    const sendDailyReportBtn = document.getElementById('sendDailyReportBtn');
    if (sendDailyReportBtn) {
        sendDailyReportBtn.addEventListener('click', async function() {
            if (!confirm('Kirim Laporan Harian sekarang?\n\nLaporan akan dikirim ke email penerima yang sudah dikonfigurasi.')) {
                return;
            }
            
            try {
                showLoading('Mengirim laporan harian...');
                
                // Use FormData untuk auto-handle CSRF token jika ada window.secureFetch
                const data = {
                    action: 'send_daily_report',
                    force: true
                };
                
                let response;
                if (typeof window.secureFetch === 'function') {
                    // Use secureFetch if available (handles CSRF automatically)
                    response = await window.secureFetch('../config/api.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify(data)
                    });
                } else {
                    // Fallback ke FormData
                    const formData = new FormData();
                    formData.append('action', 'send_daily_report');
                    formData.append('force', true);
                    
                    response = await fetch('../config/api.php', {
                        method: 'POST',
                        body: formData
                    });
                }
                
                const result = await response.json();
                hideLoading();
                
                if (result.success) {
                    showToast(result.message || 'Laporan harian berhasil dikirim!', 'success');
                    // Reload notification history
                    loadNotificationHistory();
                } else {
                    showToast(result.message || 'Gagal mengirim laporan harian', 'error');
                }
            } catch (error) {
                hideLoading();
                showToast('Error: ' + error.message, 'error');
                console.error('Error sending daily report:', error);
            }
        });
    }
    
    // Refresh history button
    const refreshHistoryBtn = document.getElementById('refreshHistoryBtn');
    if (refreshHistoryBtn) {
        refreshHistoryBtn.addEventListener('click', loadNotificationHistory);
    }
    
    // Load email config when email-settings tab is opened
    document.querySelectorAll('.tab-button').forEach(button => {
        button.addEventListener('click', function() {
            const tabName = this.getAttribute('data-tab');
            
            if (tabName === 'email-settings') {
                // Load email config
                loadEmailConfig();
                // Load notification history
                loadNotificationHistory();
            }
        });
    });
});

// Export functions for global access
window.initRealtimePolling = initRealtimePolling;
window.startPolling = startPolling;
window.stopPolling = stopPolling;
window.loadEmailConfig = loadEmailConfig;
window.loadNotificationHistory = loadNotificationHistory;
