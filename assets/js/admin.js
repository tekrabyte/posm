        // Variabel global untuk data store
        let allStores = [];
        let allEmployees = [];
        let allCashFlowTransactions = [];


        // =========================================================
        // --- Utility Functions (Final) ---
        // =========================================================

        // Format angka jadi Rupiah, misal 15000 -> Rp 15.000
        function formatRupiah(number) {
            const num = Number(number);
            if (isNaN(num)) return 'Rp 0';
            return new Intl.NumberFormat('id-ID', {
                style: 'currency',
                currency: 'IDR',
                minimumFractionDigits: 0
            }).format(num);
        }

        // Tampilkan modal (hapus class hidden, tambah z-index dan layout center)
        function showModal(id) {
            const modal = document.getElementById(id);
            if (!modal) return;

            modal.classList.remove('hidden');
            modal.classList.add('flex', 'items-center', 'justify-center');
            modal.style.zIndex = '9999';
        }

        // Sembunyikan modal (tambah class hidden kembali)
        function hideModal(id) {
            const modal = document.getElementById(id);
            if (!modal) return;

            modal.classList.add('hidden');
            modal.classList.remove('flex', 'items-center', 'justify-center');
            modal.style.zIndex = '';
        }


        // --- Navigasi Tab ---
        document.querySelectorAll('.tab-button').forEach(button => {
            button.addEventListener('click', function () {
                document.querySelectorAll('.tab-button').forEach(btn => btn.classList.remove('active-tab'));
                document.querySelectorAll('.tab-content').forEach(content => content.classList.add('hidden'));

                this.classList.add('active-tab');
                const tabId = this.dataset.tab;
                document.getElementById(`tab-${tabId}`).classList.remove('hidden');

                // Muat data spesifik saat tab dibuka
                if (tabId === 'dashboard') fetchDashboardData();
                if (tabId === 'setoran') fetchSetoranData();
                if (tabId === 'cashflow') fetchCashFlowData();
                if (tabId === 'stores') fetchStores();
                if (tabId === 'employees') fetchEmployees();
            });
        });

        // --- Autentikasi ---
        document.getElementById('logoutBtn').addEventListener('click', async function () {
            if (confirm('Anda yakin ingin logout?')) {
                await fetch('../config/api.php?action=logout');
                window.location.href = '../config/login.php';
            }
        });

        // --- Populate Store/Employee Filter ---
        async function fetchAllStoresAndEmployeesForFilter() {
            // Ambil Store untuk filter dan modal
            const storeResponse = await fetch('../config/api.php?action=get_stores');
            const storeResult = await storeResponse.json();
            if (storeResult.success) {
                allStores = storeResult.data;

                // Populate Filter Setoran
                const filterStoreSelectSetoran = document.getElementById('filter_store_id_setoran');
                const selectedStoreIdSetoran = '<?php echo $selected_store_id; ?>';
                filterStoreSelectSetoran.innerHTML = '<option value="">Semua Store</option>';

                // Populate Filter Cash Flow
                const filterStoreSelectCashFlow = document.getElementById('filter_store_id_cashflow');
                filterStoreSelectCashFlow.innerHTML = '<option value="">Semua Store</option>';

                // Populate Modal Cash Flow
                const modalStoreSelectCashFlow = document.getElementById('cf_store_id_field');
                modalStoreSelectCashFlow.innerHTML = '<option value="" disabled selected>Pilih Store (Wajib)</option>';

                allStores.forEach(store => {
                    // Setoran Filter
                    const optionSetoran = new Option(store.store_name, store.id);
                    if (store.id == selectedStoreIdSetoran) optionSetoran.selected = true;
                    filterStoreSelectSetoran.appendChild(optionSetoran);

                    // Cash Flow Filter
                    const optionCashFlowFilter = new Option(store.store_name, store.id);
                    filterStoreSelectCashFlow.appendChild(optionCashFlowFilter);

                    // Cash Flow Modal
                    const optionCashFlowModal = new Option(store.store_name, store.id);
                    modalStoreSelectCashFlow.appendChild(optionCashFlowModal);
                });
            }

            // Ambil Employee untuk filter 
            const employeeResponse = await fetch('../config/api.php?action=get_employees');
            const employeeResult = await employeeResponse.json();
            if (employeeResult.success) {
                allEmployees = employeeResult.data;
                const filterEmployeeSelect = document.getElementById('filter_employee_id_setoran');
                const selectedEmployeeId = '<?php echo $selected_employee_id; ?>';

                filterEmployeeSelect.innerHTML = '<option value="">Semua Karyawan</option>';

                allEmployees.forEach(employee => {
                    const option = new Option(`${employee.employee_name} (${employee.store_name})`, employee.id);
                    if (employee.id == selectedEmployeeId) option.selected = true;
                    filterEmployeeSelect.appendChild(option);
                });
            }
        }


        // =========================================================
        // A. LOGIKA UTAMA SETORAN HISTORY
        // =========================================================

        async function fetchSetoranData() {
            const tableBody = document.getElementById('setoranTableBody');
            tableBody.innerHTML = '<tr><td colspan="15" class="text-center py-6 text-gray-500">Memuat data...</td></tr>';

            // Ambil nilai dari form filter, bukan dari URL
            const month = document.getElementById('filter_month_setoran').value || '<?php echo $current_month; ?>';
            const year = document.getElementById('filter_year_setoran').value || '<?php echo $current_year; ?>';
            const employeeId = document.getElementById('filter_employee_id_setoran').value || '';
            const storeId = document.getElementById('filter_store_id_setoran').value || '';

            let apiUrl = `../config/api.php?action=get_history&month=${month}&year=${year}`;
            if (employeeId) apiUrl += `&employee_id=${employeeId}`;
            if (storeId) apiUrl += `&store_id=${storeId}`;

            try {
                const response = await fetch(apiUrl);
                const result = await response.json();

                if (result.success && result.data.setoran.length > 0) {
                    tableBody.innerHTML = '';
                    result.data.setoran.forEach(setoran => {
                        const row = tableBody.insertRow();
                        row.className = 'hover:bg-gray-50 transition duration-150 ease-in-out';

                        const total_liter_formatted = parseFloat(setoran.total_liter).toFixed(2);

                        row.innerHTML = `
                    <td class="px-4 py-2 whitespace-nowrap text-gray-800">${setoran.tanggal}</td>
                    <td class="px-4 py-2 whitespace-nowrap text-gray-800">${setoran.employee_name || 'N/A'}</td>
                    <td class="px-4 py-2 whitespace-nowrap text-gray-800 font-medium">${setoran.store_name || 'N/A'}</td>
                    <td class="px-4 py-2 whitespace-nowrap text-center text-gray-800">${setoran.jam_masuk.substring(0, 5)}</td>
                    <td class="px-4 py-2 whitespace-nowrap text-center text-gray-800">${setoran.jam_keluar.substring(0, 5)}</td>
                    <td class="px-4 py-2 whitespace-nowrap text-right text-gray-800">${parseFloat(setoran.nomor_awal).toFixed(2)}</td>
                    <td class="px-4 py-2 whitespace-nowrap text-right text-gray-800">${parseFloat(setoran.nomor_akhir).toFixed(2)}</td>
                    <td class="px-4 py-2 whitespace-nowrap text-right text-indigo-700 font-medium">${total_liter_formatted} L</td>
                    <td class="px-4 py-2 whitespace-nowrap text-right text-blue-600">${formatRupiah(setoran.qris)}</td>
                    <td class="px-4 py-2 whitespace-nowrap text-right text-green-600">${formatRupiah(setoran.cash)}</td>
                    <td class="px-4 py-2 whitespace-nowrap text-right font-medium">${formatRupiah(setoran.total_setoran)}</td>
                    <td class="px-4 py-2 whitespace-nowrap text-right text-red-600">${formatRupiah(setoran.total_pengeluaran)}</td>
                    <td class="px-4 py-2 whitespace-nowrap text-right text-green-600">${formatRupiah(setoran.total_pemasukan)}</td>
                    <td class="px-4 py-2 whitespace-nowrap text-right font-semibold text-green-700">${formatRupiah(setoran.total_keseluruhan)}</td>
                    <td class="px-4 py-2 whitespace-nowrap text-center">
                        <div class="flex justify-center space-x-1">
                            <button onclick="showDetail(${setoran.id})" class="text-blue-600 hover:text-blue-900 text-xs font-semibold bg-blue-100 px-2 py-1 rounded" title="Lihat Detail">
                                👁️
                            </button>
                            <button onclick="deleteSetoran(${setoran.id})" class="text-red-600 hover:text-red-900 text-xs font-semibold bg-red-100 px-2 py-1 rounded" title="Hapus">
                                🗑️
                            </button>
                        </div>
                    </td>
                `;
                    });
                    updateSetoranSummary(result.summary);
                } else {
                    tableBody.innerHTML = '<tr><td colspan="15" class="text-center py-6 text-gray-500">Tidak ada data setoran yang ditemukan.</td></tr>';
                    updateSetoranSummary(result.summary || {});
                }
            } catch (error) {
                tableBody.innerHTML = `<tr><td colspan="15" class="text-center py-6 text-red-500">Gagal memuat data: ${error.message}</td></tr>`;
            }
        }

        function updateSetoranSummary(summary) {
            document.getElementById('sum_liter').textContent = summary.total_liter ? `${parseFloat(summary.total_liter).toFixed(2)} L` : '0 L';
            document.getElementById('sum_cash').textContent = summary.total_cash ? formatRupiah(summary.total_cash) : formatRupiah(0);
            document.getElementById('sum_qris').textContent = summary.total_qris ? formatRupiah(summary.total_qris) : formatRupiah(0);
            document.getElementById('sum_pengeluaran').textContent = summary.total_pengeluaran ? formatRupiah(summary.total_pengeluaran) : formatRupiah(0);
            document.getElementById('sum_total_keseluruhan').textContent = summary.total_keseluruhan ? formatRupiah(summary.total_keseluruhan) : formatRupiah(0);
        }

        async function deleteSetoran(id) {
            if (!confirm('Anda yakin ingin menghapus setoran ini?')) return;

            try {
                const response = await fetch('../config/api.php?action=delete_setoran', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: id, csrf_token: CSRF_TOKEN })
                });
                const result = await response.json();
                alert(result.message);
                if (result.success) fetchSetoranData(); // Refresh data
            } catch (error) {
                alert('Gagal terhubung ke server.');
            }
        }

        // --- FUNGSI LIHAT DETAIL SETORAN ---
        async function showDetail(setoranId) {
            const modal = document.getElementById('detailModal');
            const loading = document.getElementById('detailLoading');
            const content = document.getElementById('detailContent');

            modal.classList.remove('hidden');
            modal.classList.add('flex');
            content.classList.add('hidden');
            loading.classList.remove('hidden');
            loading.textContent = 'Memuat rincian setoran...';

            try {
                const response = await fetch(`../config/api.php?action=get_setoran_detail&id=${setoranId}`);
                const result = await response.json();

                if (result.success && result.data.setoran.length === 1) {
                    const detail = result.data.setoran[0];
                    const pemasukan = result.data.pemasukan;
                    const pengeluaran = result.data.pengeluaran;

                    // Isi data utama
                    document.getElementById('detail_id').textContent = detail.id;
                    document.getElementById('detail_tanggal').textContent = detail.tanggal;
                    document.getElementById('detail_employee').textContent = detail.employee_name || 'N/A';
                    document.getElementById('detail_store').textContent = detail.store_name || 'N/A';
                    document.getElementById('detail_jam_masuk').textContent = detail.jam_masuk.substring(0, 5);
                    document.getElementById('detail_jam_keluar').textContent = detail.jam_keluar.substring(0, 5);
                    document.getElementById('detail_nomor_awal').textContent = parseFloat(detail.nomor_awal).toFixed(2);
                    document.getElementById('detail_nomor_akhir').textContent = parseFloat(detail.nomor_akhir).toFixed(2);
                    document.getElementById('detail_total_liter').textContent = parseFloat(detail.total_liter).toFixed(2) + ' L';
                    document.getElementById('detail_cash').textContent = formatRupiah(detail.cash);
                    document.getElementById('detail_qris').textContent = formatRupiah(detail.qris);
                    document.getElementById('detail_total_setoran').textContent = formatRupiah(detail.total_setoran);
                    document.getElementById('detail_total_pengeluaran').textContent = formatRupiah(detail.total_pengeluaran);
                    document.getElementById('detail_total_pemasukan').textContent = formatRupiah(detail.total_pemasukan);
                    document.getElementById('detail_total_keseluruhan').textContent = formatRupiah(detail.total_keseluruhan);

                    // Isi rincian pengeluaran
                    const pengeluaranList = document.getElementById('detail_pengeluaran_list');
                    pengeluaranList.innerHTML = '';
                    if (pengeluaran.length > 0) {
                        pengeluaran.forEach(item => {
                            pengeluaranList.innerHTML += `<li>${item.description}: ${formatRupiah(item.amount)}</li>`;
                        });
                    } else {
                        pengeluaranList.innerHTML = '<li class="text-gray-500 italic">Tidak ada pengeluaran tambahan</li>';
                    }

                    // Isi rincian pemasukan
                    const pemasukanList = document.getElementById('detail_pemasukan_list');
                    pemasukanList.innerHTML = '';
                    if (pemasukan.length > 0) {
                        pemasukan.forEach(item => {
                            pemasukanList.innerHTML += `<li>${item.description}: ${formatRupiah(item.amount)}</li>`;
                        });
                    } else {
                        pemasukanList.innerHTML = '<li class="text-gray-500 italic">Tidak ada pemasukan tambahan</li>';
                    }

                    loading.classList.add('hidden');
                    content.classList.remove('hidden');
                } else {
                    alert(result.message || 'Gagal memuat detail setoran.');
                    hideModal('detailModal');
                }
            } catch (error) {
                loading.textContent = 'Gagal memuat detail: ' + error.message;
            }
        }

        // =========================================================
        // C. EVENT LISTENERS UNTUK FILTER SETORAN
        // =========================================================

        document.getElementById('filterFormSetoran').addEventListener('submit', function (e) {
            e.preventDefault();
            fetchSetoranData(); // Langsung fetch data tanpa reload page
        });

        document.getElementById('resetFilterBtnSetoran').addEventListener('click', function () {
            // Reset form values
            document.getElementById('filter_store_id_setoran').value = '';
            document.getElementById('filter_employee_id_setoran').value = '';
            document.getElementById('filter_month_setoran').value = '<?php echo $current_month; ?>';
            document.getElementById('filter_year_setoran').value = '<?php echo $current_year; ?>';
            // Fetch data dengan filter default
            fetchSetoranData();
        });



        // =========================================================
        // INISIALISASI SAAT DOKUMEN SIAP
        // =========================================================
        document.addEventListener("DOMContentLoaded", function () {
            initializeCashFlow();
            loadStoresForFilter();
        });

        // =========================================================
        // INISIALISASI CASH FLOW
        // =========================================================
        function initializeCashFlow() {
            // Event listener untuk form filter
            const filterForm = document.getElementById('filterFormCashflow');
            if (filterForm) {
                filterForm.addEventListener('submit', function (e) {
                    e.preventDefault();
                    fetchCashFlowData();
                });
            }

            // Event listener untuk form cashflow
            const cashflowForm = document.getElementById('cashflowForm');
            if (cashflowForm) {
                cashflowForm.addEventListener('submit', handleCashFlowSubmit);
            }

            // Inisialisasi fitur BBM
            initializeBBMFeature();

            // Load data awal
            fetchCashFlowData();
        }

        // =========================================================
        // LOAD STORES UNTUK FILTER
        // =========================================================
        // =========================================================
        // IMPROVED JAVASCRIPT WITH BETTER ERROR HANDLING
        // =========================================================

        async function loadStoresForFilter() {
            try {
                console.log('Loading stores...');
                const response = await fetch('../config/api.php?action=get_stores');

                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }

                const text = await response.text();
                console.log('Raw response:', text);

                const result = JSON.parse(text);

                if (result.success) {
                    allStores = result.data;
                    const filterStoreSelect = document.getElementById('filter_store_cashflow');

                    if (filterStoreSelect) {
                        // Clear existing options except "Semua Store"
                        const allStoresOption = filterStoreSelect.querySelector('option[value=""]');
                        filterStoreSelect.innerHTML = '';
                        if (allStoresOption) {
                            filterStoreSelect.appendChild(allStoresOption);
                        }

                        // Add stores from API
                        result.data.forEach(store => {
                            const option = document.createElement('option');
                            option.value = store.id;
                            option.textContent = store.store_name;
                            filterStoreSelect.appendChild(option);
                        });
                    }
                    console.log('Stores loaded successfully:', allStores.length);
                } else {
                    throw new Error(result.message || 'Failed to load stores');
                }
            } catch (error) {
                console.error('Gagal memuat data store:', error);
                showNotification('Gagal memuat data store: ' + error.message, 'error');
            }
        }

        async function fetchCashFlowData() {
            try {
                const month = document.getElementById('filter_month_cashflow')?.value || new Date().getMonth() + 1;
                const year = document.getElementById('filter_year_cashflow')?.value || new Date().getFullYear();
                const storeId = document.getElementById('filter_store_cashflow')?.value || '';

                const params = new URLSearchParams({
                    action: 'get_management_cash_flow',
                    month: month,
                    year: year,
                    store_id: storeId
                    
                });

                console.log('🔍 Fetching cashflow data with params:', params.toString());

                const response = await fetch(`../config/api.php?${params}`);

                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }

                const result = await response.json();
                console.log('🔍 RAW API RESPONSE:', result);

                // DEBUG: Check structure
                console.log('🔍 result.success:', result.success);
                console.log('🔍 result.data:', result.data);
                console.log('🔍 result.data type:', typeof result.data);
                console.log('🔍 result.message:', result.message);

                if (result.success) {
                    // FIX: Handle case where data might be undefined or null
                    const transactions = result.data && Array.isArray(result.data) ? result.data : [];
                    allCashFlowTransactions = transactions;

                    console.log('🔍 Transactions to render:', transactions);
                    renderCashFlowTable(transactions);

                    // FIX: Handle summary
                    const summary = result.summary || {
                        total_pemasukan_manajemen: 0,
                        total_pengeluaran_manajemen: 0,
                        saldo_bersih: 0
                    };
                    updateCashFlowSummary(summary);
                     getBBMSummary();

                    console.log('✅ Cashflow data loaded successfully:', transactions.length, 'transactions');
                } else {
                    console.error('❌ API returned success:false');
                    throw new Error(result.message || 'Failed to load cashflow data');
                }
            } catch (error) {
                console.error('❌ Error fetching cash flow data:', error);
                showNotification('Gagal memuat data cash flow: ' + error.message, 'error');

                // Fallback: show empty state
                const tbody = document.getElementById('cashflowTableBody');
                if (tbody) {
                    tbody.innerHTML = '<tr><td colspan="6" class="text-center py-4 text-gray-400">Gagal memuat data. ' + error.message + '</td></tr>';
                }
            }
        }

        // =========================================================
        // RENDER TABEL CASH FLOW - FIXED VERSION
        // =========================================================
        function renderCashFlowTable(transactions) {
            const tbody = document.getElementById('cashflowTableBody');

            if (!tbody) return;

            if (transactions.length === 0) {
                tbody.innerHTML = '<tr><td colspan="6" class="text-center py-4 text-gray-400">Tidak ada data transaksi untuk periode yang dipilih.</td></tr>';
                return;
            }

            tbody.innerHTML = transactions.map(transaction => {
                // Highlight transaksi BBM
                const isBBM = transaction.category === 'bbm';
                const bbmClass = isBBM ? 'bg-blue-50 border-l-4 border-blue-400' : '';

                return `
            <tr class="hover:bg-gray-50 ${bbmClass}">
                <td class="px-4 py-3 whitespace-nowrap">${formatDate(transaction.tanggal)}</td>
                <td class="px-4 py-3 whitespace-nowrap">
                    ${transaction.store_name || '-'}
                    ${isBBM ? '<span class="text-xs bg-blue-100 text-blue-800 px-1 rounded">BBM</span>' : ''}
                </td>
                <td class="px-4 py-3">
                    <div class="font-medium">${transaction.description}</div>
                    ${transaction.notes ? `<div class="text-xs text-gray-500">${transaction.notes}</div>` : ''}
                    ${transaction.category && transaction.category !== 'lainnya' ? `<div class="text-xs text-gray-500">${transaction.category}</div>` : ''}
                </td>
                <td class="px-4 py-3 text-center">
                    <span class="px-2 py-1 text-xs rounded-full ${transaction.type === 'Pemasukan' || transaction.type === 'pemasukan'
                        ? 'bg-green-100 text-green-800'
                        : 'bg-red-100 text-red-800'
                    }">
                        ${transaction.type}
                    </span>
                </td>
                <td class="px-4 py-3 text-right font-medium ${transaction.type === 'Pemasukan' || transaction.type === 'pemasukan'
                        ? 'text-green-600'
                        : 'text-red-600'
                    }">
                    Rp ${formatRupiah(transaction.amount)}
                </td>
                <td class="px-4 py-3 text-center">
                    ${transaction.source === 'manual' ? `
                    <div class="flex justify-center space-x-2">
                        <button onclick="editCashFlow(${transaction.id})" 
                            class="text-blue-600 hover:text-blue-900 transition-colors" title="Edit">
                            ✏️
                        </button>
                        <button onclick="deleteCashFlow(${transaction.id})" 
                            class="text-red-600 hover:text-red-900 transition-colors" title="Hapus">
                            🗑️
                        </button>
                    </div>
                    ` : '<span class="text-gray-400 text-xs">Auto</span>'}
                </td>
            </tr>
        `;
            }).join('');
        }
        // =========================================================
        // RESET BBM SIMULATOR
        // =========================================================
        function resetBBMSimulator() {
            const bbmSection = document.getElementById('bbmSimulator');
            const storeSelectContainer = document.getElementById('storeSelectContainer');
            const storeSelect = document.getElementById('cashflow_store_id');
            const storeContainer = document.getElementById('storeInputs');
            const checkboxes = document.querySelectorAll('.store-check');
            const totalInput = document.getElementById('bbm_total');
            const info = document.getElementById('bbm_info');
            const nominalField = document.getElementById('cashflow_amount');


            // Reset semua input BBM
            if (bbmSection) bbmSection.classList.add('hidden');
            if (storeSelectContainer) storeSelectContainer.classList.remove('hidden');
            if (storeSelect && allStores.length > 0) {
                storeSelect.value = allStores[0].id; // Set ke store yang valid
                storeSelect.setAttribute('required', 'required');
            }

            // Uncheck semua checkbox
            checkboxes.forEach(cb => cb.checked = false);
            document.querySelectorAll('.store-check').forEach(cb => cb.checked = false);
            // Kosongkan container store inputs
            if (storeContainer) {
                storeContainer.innerHTML = '';
                storeContainer.classList.add('hidden');
            }
            document.getElementById('storeInputs').innerHTML = '';
            document.getElementById('storeInputs').classList.add('hidden');

            console.log('✅ BBM Simulator reset dengan store valid');
            // Reset nilai total dan info
            if (totalInput) totalInput.value = '';
            if (nominalField) nominalField.value = '';
            if (info) info.textContent = '';

            console.log('BBM Simulator reset successfully');
        }
        // Improved fetch function dengan timeout
        async function safeFetch(url, options = {}) {
            const controller = new AbortController();
            const timeoutId = setTimeout(() => controller.abort(), 10000); // 10 second timeout

            try {
                const response = await fetch(url, {
                    ...options,
                    signal: controller.signal
                });
                clearTimeout(timeoutId);

                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }

                const text = await response.text();

                // Check if response is HTML error page
                if (text.trim().startsWith('<!DOCTYPE') || text.includes('<html') || text.includes('<br />')) {
                    throw new Error('Server returned HTML instead of JSON. Possible PHP error.');
                }

                return JSON.parse(text);
            } catch (error) {
                clearTimeout(timeoutId);
                if (error.name === 'AbortError') {
                    throw new Error('Request timeout - server tidak merespons');
                }
                throw error;
            }
        }

        // Usage example:
        async function loadStoresForFilter() {
            try {
                const result = await safeFetch('../config/api.php?action=get_stores');

                if (result.success) {
                    allStores = result.data;
                    // ... update UI
                } else {
                    throw new Error(result.message);
                }
            } catch (error) {
                console.error('Failed to load stores:', error);
                showNotification('Error: ' + error.message, 'error');
            }
        }
        function testNonBBM() {
            document.getElementById('cashflow_date').value = '2025-10-23';
            document.getElementById('cashflow_store_id').value = '1'; // Store ID yang valid
            document.getElementById('cashflow_type').value = 'Pengeluaran';
            document.getElementById('cashflow_category').value = 'operasional';
            document.getElementById('cashflow_description').value = 'Test Operasional';
            document.getElementById('cashflow_amount').value = '500000';

            console.log('🧪 Testing non-BBM transaction...');
        }
        // =========================================================
        // FETCH DATA CASH FLOW
        // =========================================================
        async function fetchCashFlowData() {
            try {
                const month = document.getElementById('filter_month_cashflow').value;
                const year = document.getElementById('filter_year_cashflow').value;
                const storeId = document.getElementById('filter_store_cashflow').value;

                const params = new URLSearchParams({
                    action: 'get_management_cash_flow',
                    month: month,
                    year: year,
                    store_id: storeId
                });

                const response = await fetch(`../config/api.php?${params}`);
                const result = await response.json();

                if (result.success) {
                    allCashFlowTransactions = result.data;
                    renderCashFlowTable(result.data);
                    updateCashFlowSummary(result.summary);
                } else {
                    showNotification('Gagal memuat data cash flow: ' + result.message, 'error');
                }
            } catch (error) {
                console.error('Error fetching cash flow data:', error);
                showNotification('Terjadi kesalahan saat memuat data', 'error');
            }
        }

        // =========================================================
        // RENDER TABEL CASH FLOW
        // =========================================================
        function renderCashFlowTable(transactions) {
            const tbody = document.getElementById('cashflowTableBody');

            if (!tbody) return;

            if (transactions.length === 0) {
                tbody.innerHTML = '<tr><td colspan="6" class="text-center py-4 text-gray-400">Tidak ada data transaksi untuk periode yang dipilih.</td></tr>';
                return;
            }

            tbody.innerHTML = transactions.map(transaction => `
        <tr class="hover:bg-gray-50">
            <td class="px-4 py-3 whitespace-nowrap">${formatDate(transaction.tanggal)}</td>
            <td class="px-4 py-3 whitespace-nowrap">${transaction.store_name || '-'}</td>
            <td class="px-4 py-3">
                <div class="font-medium">${transaction.description}</div>
                ${transaction.category ? `<div class="text-xs text-gray-500">${transaction.category}</div>` : ''}
            </td>
            <td class="px-4 py-3 text-center">
                <span class="px-2 py-1 text-xs rounded-full ${transaction.type === 'Pemasukan' || transaction.type === 'pemasukan'
                    ? 'bg-green-100 text-green-800'
                    : 'bg-red-100 text-red-800'
                }">
                    ${transaction.type}
                </span>
            </td>
            <td class="px-4 py-3 text-right font-medium ${transaction.type === 'Pemasukan' || transaction.type === 'pemasukan'
                    ? 'text-green-600'
                    : 'text-red-600'
                }">
                Rp ${formatRupiah(transaction.amount)}
            </td>
            <td class="px-4 py-3 text-center">
                ${transaction.source === 'manual' ? `
                <div class="flex justify-center space-x-2">
                    <button onclick="editCashFlow(${transaction.id})" 
                        class="text-blue-600 hover:text-blue-900 transition-colors" title="Edit">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                        </svg>
                    </button>
                    <button onclick="deleteCashFlow(${transaction.id})" 
                        class="text-red-600 hover:text-red-900 transition-colors" title="Hapus">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                        </svg>
                    </button>
                </div>
                ` : '<span class="text-gray-400 text-xs">Auto</span>'}
            </td>
        </tr>
    `).join('');
        }

        // =========================================================
        // UPDATE SUMMARY CASH FLOW
        // =========================================================
        function updateCashFlowSummary(summary) {
            const totalIncomeEl = document.getElementById('cashflow_total_income');
            const totalExpenseEl = document.getElementById('cashflow_total_expense');
            const balanceEl = document.getElementById('cashflow_balance');

            if (totalIncomeEl) {
                totalIncomeEl.textContent = `Rp ${formatRupiah(summary.total_pemasukan_manajemen || 0)}`;
            }
            if (totalExpenseEl) {
                totalExpenseEl.textContent = `Rp ${formatRupiah(summary.total_pengeluaran_manajemen || 0)}`;
            }
            if (balanceEl) {
                const balance = (summary.total_pemasukan_manajemen || 0) - (summary.total_pengeluaran_manajemen || 0);
                balanceEl.textContent = `Rp ${formatRupiah(balance)}`;
            }
        }

        // =========================================================
        // SHOW CASH FLOW MODAL - IMPROVED VERSION
        // =========================================================
        function showCashFlowModal(transaction = null) {
            const modal = document.getElementById('modalCashflow');
            const form = document.getElementById('cashflowForm');
            const title = document.getElementById('modalCashflowTitle');
            const messageEl = document.getElementById('cashflowMessage');

            if (!modal || !form) {
                console.error('Modal atau form tidak ditemukan');
                return;
            }

            // Reset form completely
            form.reset();

            // Reset error highlights
            const fields = ['cashflow_date', 'cashflow_store_id', 'cashflow_type', 'cashflow_category', 'cashflow_description', 'cashflow_amount'];
            fields.forEach(fieldId => {
                const field = document.getElementById(fieldId);
                if (field) {
                    field.classList.remove('border-red-500', 'border-2');
                }
            });

            // Reset message
            if (messageEl) {
                messageEl.classList.add('hidden');
                messageEl.textContent = '';
            }

            // Set judul modal
            if (title) {
                title.textContent = transaction ? 'Edit Transaksi Kas' : 'Tambah Transaksi Kas';
            }

            // Set default values
            const today = new Date().toISOString().split('T')[0];
            document.getElementById('cashflow_date').value = today;

            // Set default category jika tidak ada transaksi
            if (!transaction) {
                document.getElementById('cashflow_category').value = 'lainnya';
            }

            // Isi form jika edit
            if (transaction) {
                document.getElementById('cashflow_id').value = transaction.id;
                document.getElementById('cashflow_date').value = transaction.tanggal;
                document.getElementById('cashflow_store_id').value = transaction.store_id;
                document.getElementById('cashflow_type').value = transaction.type;
                document.getElementById('cashflow_category').value = transaction.category;
                document.getElementById('cashflow_description').value = transaction.description;
                document.getElementById('cashflow_amount').value = transaction.amount;
            }

            // Reset simulator BBM
            resetBBMSimulator();

            // Tampilkan modal
            modal.classList.remove('hidden');

            // Focus ke field pertama
            setTimeout(() => {
                document.getElementById('cashflow_description')?.focus();
            }, 100);

            console.log('✅ Cashflow modal shown successfully');
        }

        // =========================================================
        // HANDLE SUBMIT CASH FLOW - FIXED BBM VERSION
        // =========================================================
        async function handleCashFlowSubmit(e) {
            e.preventDefault();

            const submitBtn = document.getElementById('cashflowSubmitBtn');
            const messageEl = document.getElementById('cashflowMessage');

            try {
                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.textContent = 'Menyimpan...';
                }

                const formData = new FormData(document.getElementById('cashflowForm'));
                const data = Object.fromEntries(formData.entries());

                console.log('📤 Data form:', data);

                // =============================================
                // FIX: Handle BBM Multiple Stores
                // =============================================
                if (data.category === 'bbm') {
                    await handleBBMTransaction(data, messageEl);
                } else {
                    await handleRegularTransaction(data, messageEl);
                }

            } catch (error) {
                console.error('💥 Error:', error);
                showMessage('❌ ' + error.message, 'error', messageEl);
            } finally {
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'Simpan Transaksi';
                }
            }
        }

 async function handleBBMTransaction(data, messageEl) {
    const storeCheckboxes = document.querySelectorAll('.store-check:checked');
    
    if (storeCheckboxes.length === 0) {
        throw new Error('Pilih minimal satu store untuk distribusi BBM');
    }

    const totalAmount = parseFloat(data.amount);
    if (!totalAmount || totalAmount <= 0) {
        throw new Error('Total amount BBM tidak valid');
    }

    const distribution = getBBMDistributionDetails(data, totalAmount);
    
    console.log('💰 BBM Distribution:', distribution);

    let successCount = 0;
    const totalStores = distribution.stores.length;

    showMessage(`🔄 Menyimpan ${totalStores} transaksi BBM...`, 'info', messageEl);

    // Simpan transaksi untuk setiap store
    for (const store of distribution.stores) {
        // FIX: Buat deskripsi yang lebih rapi dan profesional
        const transaction = {
            tanggal: data.tanggal,
            store_id: store.storeId,
            type: data.type,
            category: 'bbm',
            description: `Pembelian BBM ${store.jumlahDrigen} Drigen - ${store.storeName}`,
            amount: store.amount,
            notes: `BBM_DETAIL:${store.storeId}:${store.jumlahDrigen}:${store.amount}:${Math.round(store.proportion * 100)}`
        };

        try {
            const response = await saveCashFlowTransaction(transaction);
            if (response.success) {
                successCount++;
                console.log(`✅ Saved for store ${store.storeName}: ${store.jumlahDrigen} drigen = Rp ${store.amount.toLocaleString()}`);
            }
        } catch (error) {
            console.error(`❌ Failed for store ${store.storeName}:`, error);
        }
    }

    if (successCount > 0) {
        showMessage(`✅ ${successCount} transaksi BBM berhasil disimpan`, 'success', messageEl);
        
        setTimeout(() => {
            hideModal('modalCashflow');
            fetchCashFlowData();
        }, 2000);
    } else {
        throw new Error('Gagal menyimpan semua transaksi BBM');
    }
}
        // Fungsi untuk regular transaction
        async function handleRegularTransaction(data, messageEl) {
            const payload = {
                tanggal: data.tanggal,
                store_id: parseInt(data.store_id) || 0,
                type: data.type,
                category: data.category,
                description: data.description.trim(),
                amount: parseFloat(data.amount)
            };

            if (data.id && data.id !== '') {
                payload.id = parseInt(data.id);
            }

            const response = await saveCashFlowTransaction(payload);

            if (response.success) {
                showMessage('✅ ' + response.message, 'success', messageEl);

                setTimeout(() => {
                    hideModal('modalCashflow');
                    fetchCashFlowData();
                }, 1500);
            } else {
                throw new Error(response.message);
            }
        }

        // Helper function untuk save transaction
        async function saveCashFlowTransaction(payload) {
            const action = payload.id ? 'edit_management_cash_flow' : 'add_management_cash_flow';

            // Add CSRF token to payload
            payload.csrf_token = CSRF_TOKEN;

            const response = await fetch(`../config/api.php?action=${action}`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });

            return await response.json();
        }

        // Fungsi untuk mendapatkan detail distribusi BBM
        function getBBMDistributionDetails(data, totalAmount) {
            const storeCheckboxes = document.querySelectorAll('.store-check:checked');
            const stores = [];
            let totalDrigen = 0;

            // Kumpulkan data store dan hitung total drigen
            storeCheckboxes.forEach(checkbox => {
                const storeId = checkbox.value;
                const storeName = checkbox.nextElementSibling.textContent.split(' - ')[0].trim();
                const jumlahDrigen = parseInt(data[`jumlah_drigen[${storeId}]`] || 0);

                if (jumlahDrigen > 0) {
                    stores.push({
                        storeId: parseInt(storeId),
                        storeName: storeName,
                        jumlahDrigen: jumlahDrigen
                    });
                    totalDrigen += jumlahDrigen;
                }
            });

            // Hitung proporsi dan amount untuk setiap store
            if (totalDrigen > 0) {
                stores.forEach(store => {
                    store.proportion = store.jumlahDrigen / totalDrigen;
                    store.amount = Math.round(totalAmount * store.proportion);
                });
            }

            return {
                stores: stores,
                totalDrigen: totalDrigen,
                totalAmount: totalAmount
            };
        }

        // Fungsi untuk menyimpan detail distribusi (jika menggunakan tabel terpisah)
        async function saveBBMDistributionDetails(transactionId, distribution) {
            try {
                // Simpan ke API khusus untuk BBM distribution
                const response = await fetch('../config/api.php?action=save_bbm_distribution', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        transaction_id: transactionId,
                        distribution: distribution,
                        csrf_token: CSRF_TOKEN
                    })
                });

                const result = await response.json();
                console.log('💾 BBM Distribution saved:', result);
            } catch (error) {
                console.error('Error saving BBM distribution:', error);
            }
        }

        // Helper function untuk generate notes BBM
        function generateBBMNotes(formData) {
            const distribution = getBBMDistributionDetails(formData, 0); // Total amount tidak perlu untuk notes

            if (distribution.stores.length === 0) {
                return 'Distribusi BBM';
            }

            const storeDetails = distribution.stores.map(store =>
                `${store.storeName}: ${store.jumlahDrigen} drigen`
            );

            return `Distribusi BBM - ${storeDetails.join(', ')}`;
        }
        // Helper function untuk highlight field error
        function highlightErrorFields(errors) {
            // Reset semua field
            const fields = ['cashflow_date', 'cashflow_store_id', 'cashflow_type', 'cashflow_category', 'cashflow_description', 'cashflow_amount'];
            fields.forEach(fieldId => {
                const field = document.getElementById(fieldId);
                if (field) {
                    field.classList.remove('border-red-500', 'border-2');
                }
            });

            // Reset BBM section
            const bbmSection = document.getElementById('bbmSimulator');
            if (bbmSection) {
                bbmSection.classList.remove('border-red-500', 'border-2');
            }

            // Highlight field yang error
            if (errors.includes('Tanggal harus diisi')) {
                document.getElementById('cashflow_date')?.classList.add('border-red-500', 'border-2');
            }
            if (errors.includes('Store harus dipilih') || errors.includes('Pilih minimal satu store untuk distribusi BBM')) {
                const isBBM = document.getElementById('cashflow_category')?.value === 'bbm';
                if (isBBM) {
                    document.getElementById('bbmSimulator')?.classList.add('border-red-500', 'border-2');
                } else {
                    document.getElementById('cashflow_store_id')?.classList.add('border-red-500', 'border-2');
                }
            }
            if (errors.includes('Jenis transaksi harus dipilih')) {
                document.getElementById('cashflow_type')?.classList.add('border-red-500', 'border-2');
            }
            if (errors.includes('Kategori harus dipilih')) {
                document.getElementById('cashflow_category')?.classList.add('border-red-500', 'border-2');
            }
            if (errors.includes('Deskripsi harus diisi')) {
                document.getElementById('cashflow_description')?.classList.add('border-red-500', 'border-2');
            }
            if (errors.includes('Nominal harus lebih dari 0')) {
                document.getElementById('cashflow_amount')?.classList.add('border-red-500', 'border-2');
            }
        }
        // =========================================================
        // EDIT DAN DELETE CASH FLOW
        // =========================================================
        function editCashFlow(id) {
            const transaction = allCashFlowTransactions.find(t => t.id == id && t.source === 'manual');
            if (transaction) {
                showCashFlowModal(transaction);
            } else {
                showNotification('Hanya transaksi manual yang dapat diedit', 'warning');
            }
        }

       async function deleteCashFlow(id) {
    const transaction = allCashFlowTransactions.find(t => t.id == id && t.source === 'manual');

    if (!transaction) {
        alert('Hanya transaksi manual yang dapat dihapus');
        return;
    }

    if (!confirm(`Apakah Anda yakin ingin menghapus transaksi "${transaction.description}" senilai Rp ${formatRupiah(transaction.amount)}?`)) {
        return;
    }

    try {
        const response = await fetch('../config/api.php?action=delete_management_cash_flow', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ id: id })
        });

        const text = await response.text();
        
        let result;
        try {
            result = JSON.parse(text);
        } catch (e) {
            // Jika parse JSON gagal, tapi mungkin data tetap terhapus
            // Cek jika response mengandung kata sukses
            if (text.includes('success') || response.ok) {
                alert('Transaksi berhasil dihapus');
                fetchCashFlowData();
                return;
            }
            throw new Error('Response tidak valid dari server');
        }

        if (result.success) {
            alert('Transaksi berhasil dihapus');
            fetchCashFlowData();
        } else {
            alert('Gagal menghapus transaksi: ' + (result.message || 'Unknown error'));
        }
    } catch (error) {
        // Jika sampai sini, coba refresh data saja
        alert('Transaksi mungkin telah dihapus. Merefresh data...');
        fetchCashFlowData();
    }
}

        // =========================================================
        // FITUR BBM - IMPROVED
        // =========================================================
        function initializeBBMFeature() {
            const categorySelect = document.getElementById("cashflow_category");
            const storeSelectContainer = document.getElementById("storeSelectContainer");
            const storeSelect = document.getElementById("cashflow_store_id");
            const bbmSection = document.getElementById("bbmSimulator");
            const storeContainer = document.getElementById("storeInputs");
            const checkboxes = document.querySelectorAll(".store-check");
            const hargaInput = document.getElementById("bbm_harga");
            const pajakInput = document.getElementById("bbm_pajak");
            const totalInput = document.getElementById("bbm_total");
            const info = document.getElementById("bbm_info");
            const nominalField = document.getElementById("cashflow_amount");

            let isManualEdit = false;

            // Toggle BBM simulator
            categorySelect.addEventListener("change", () => {
                const isBBM = categorySelect.value === "bbm";

                if (isBBM) {
                    bbmSection.classList.remove("hidden");
                    storeSelectContainer.classList.add("hidden");
                    storeSelect.removeAttribute("required");
                    storeSelect.value = "";
                } else {
                    bbmSection.classList.add("hidden");
                    storeSelectContainer.classList.remove("hidden");
                    storeSelect.setAttribute("required", "required");
                    resetBBMSimulator();
                }
            });

            // Reset simulator BBM
            function resetBBMSimulator() {
                checkboxes.forEach(cb => cb.checked = false);
                storeContainer.innerHTML = "";
                storeContainer.classList.add("hidden");
                totalInput.value = "";
                if (info) info.textContent = "";
                isManualEdit = false;
            }

            // Handle checkbox store BBM
            checkboxes.forEach(cb => {
                cb.addEventListener("change", () => {
                    const anyChecked = [...checkboxes].some(c => c.checked);
                    storeContainer.classList.toggle("hidden", !anyChecked);

                    // Generate input fields untuk store yang dicentang
                    storeContainer.innerHTML = "";
                    checkboxes.forEach(box => {
                        if (box.checked) {
                            const storeName = box.nextElementSibling.textContent.split(' - ')[0];
                            const div = document.createElement("div");
                            div.className = "grid grid-cols-2 gap-2 items-center";
                            div.innerHTML = `
                        <label class="text-sm font-medium">${storeName}</label>
                        <input type="number" name="jumlah_drigen[${box.value}]" 
                            class="drigen-input border rounded p-2 text-sm" min="0" value="0" 
                            placeholder="Jumlah drigen">
                    `;
                            storeContainer.appendChild(div);
                        }
                    });

                    if (anyChecked) {
                        calculateBBM();
                    } else {
                        totalInput.value = "";
                        nominalField.value = "";
                        updateStorePercentages();
                    }
                });
            });

            // Hitung total BBM
            function calculateBBM() {
                if (isManualEdit) return;

                const harga = parseFloat(hargaInput.value) || 0;
                const pajak = parseFloat(pajakInput.value) || 0;
                const inputs = storeContainer.querySelectorAll(".drigen-input");

                let totalDrigen = 0;
                inputs.forEach(input => {
                    totalDrigen += parseFloat(input.value) || 0;
                });

                if (totalDrigen === 0) {
                    totalInput.value = "";
                    nominalField.value = "";
                    if (info) info.textContent = "";
                    updateStorePercentages();
                    return;
                }

                const totalHarga = totalDrigen * (harga + pajak);
                const pembulatan = Math.round(totalHarga / 100) * 100;
                const selisih = pembulatan - totalHarga;

                totalInput.value = pembulatan;
                nominalField.value = pembulatan;

                updateStorePercentages(inputs, totalDrigen, pembulatan);

                if (info) {
                    info.textContent = `Pembulatan: ${selisih >= 0 ? '+' : ''}${selisih.toLocaleString()}`;
                    info.className = `text-xs mt-1 block ${selisih !== 0 ? 'text-orange-600' : 'text-gray-500'}`;
                }
                if (nominalField) {
        nominalField.addEventListener("input", () => {
            if (document.getElementById("cashflow_category")?.value === "bbm") {
                isManualEdit = true;
                const manualValue = parseFloat(nominalField.value) || 0;
                if (totalInput) totalInput.value = manualValue;
                
                const inputs = storeContainer?.querySelectorAll(".drigen-input") || [];
                
                // FIX: Panggil updateStorePercentages dengan parameter yang benar
                updateStorePercentages(inputs, 0, manualValue);
                
                if (info) {
                    info.textContent = "Manual input - distribusi berdasarkan jumlah drigen";
                    info.className = "text-xs text-blue-600 mt-1 block";
                }
            }
        });
    }
            }

            // Update persentase store
            function updateStorePercentages(inputs = [], totalDrigen = 0, totalNominal = 0) {
                checkboxes.forEach(box => {
                    if (box.checked) {
                        const storeId = box.value;
                        const input = Array.from(inputs).find(i => i.name.includes(storeId));
                        const drigen = parseFloat(input?.value) || 0;
                        const persentase = totalDrigen > 0 ? (drigen / totalDrigen * 100) : 0;
                        const nominal = totalDrigen > 0 ? totalNominal * (drigen / totalDrigen) : 0;

                        const storeText = box.nextElementSibling;
                        const storeName = storeText.textContent.split(' - ')[0];
                        storeText.innerHTML = `
                    ${storeName} 
                    <span class="text-xs text-gray-600">
                        - ${persentase.toFixed(1)}% (Rp ${Math.round(nominal).toLocaleString()})
                    </span>
                `;
                    } else {
                        // Reset text untuk store yang tidak dicentang
                        const storeText = box.nextElementSibling;
                        const originalText = storeText.textContent.split(' - ')[0];
                        storeText.textContent = originalText;
                    }
                });
            }

            // Event listeners untuk kalkulasi otomatis
            [hargaInput, pajakInput].forEach(input => {
                input.addEventListener("input", calculateBBM);
            });

            document.addEventListener("input", (e) => {
                if (e.target.classList.contains("drigen-input")) {
                    isManualEdit = false;
                    calculateBBM();
                }
            });

            // Handle manual input di nominal field
            nominalField.addEventListener("input", () => {
                if (document.getElementById("cashflow_category").value === "bbm") {
                    isManualEdit = true;
                    const manualValue = parseFloat(nominalField.value) || 0;
                    totalInput.value = manualValue;

                    const inputs = storeContainer.querySelectorAll(".drigen-input");
                    updateStorePercentages(inputs, 0, manualValue);

                    if (info) {
                        info.textContent = "Manual input - pastikan pembagian sudah sesuai";
                        info.className = "text-xs text-blue-600 mt-1 block";
                    }
                }
            });
        }

        // =========================================================
        // UTILITY FUNCTIONS
        // =========================================================
        function formatDate(dateString) {
            const date = new Date(dateString);
            return date.toLocaleDateString('id-ID', {
                day: '2-digit',
                month: '2-digit',
                year: 'numeric'
            });
        }

        function formatRupiah(amount) {
            return new Intl.NumberFormat('id-ID').format(amount);
        }

        function showMessage(message, type, element) {
            if (!element) return;

            element.textContent = message;
            element.className = `p-2 rounded text-sm ${type === 'success' ? 'bg-green-100 text-green-700' :
                    type === 'error' ? 'bg-red-100 text-red-700' :
                        'bg-blue-100 text-blue-700'
                }`;
            element.classList.remove('hidden');
        }

        function showNotification(message, type = 'info') {
            // Implementasi notifikasi toast sederhana
            const notification = document.createElement('div');
            notification.className = `fixed top-4 right-4 p-4 rounded-lg shadow-lg z-50 transform transition-transform duration-300 ${type === 'success' ? 'bg-green-500 text-white' :
                    type === 'error' ? 'bg-red-500 text-white' :
                        type === 'warning' ? 'bg-yellow-500 text-white' :
                            'bg-blue-500 text-white'
                }`;
            notification.textContent = message;

            document.body.appendChild(notification);

            setTimeout(() => {
                notification.remove();
            }, 3000);
        }

        // =========================================================
        // EXPORT FUNCTION
        // =========================================================
        function exportCashflow(format) {
            const month = document.getElementById('filter_month_cashflow').value;
            const year = document.getElementById('filter_year_cashflow').value;
            const storeId = document.getElementById('filter_store_cashflow').value;

            if (format === 'excel') {
                const params = new URLSearchParams({
                    action: 'export_cashflow_excel',
                    month: month,
                    year: year,
                    store_id: storeId
                });
                window.open(`export.php?${params}`, '_blank');
            } else {
                alert('Format PDF coming soon!');
            }
        }
        // =========================================================
        // C. LOGIKA MANAJEMEN STORE (DIPERTAHANKAN)
        // =========================================================
        async function fetchStores() {
            const tableBody = document.getElementById('storesTableBody');
            tableBody.innerHTML = '<tr><td colspan="4" class="text-center py-4 text-gray-500">Memuat data...</td></tr>';

            try {
                const response = await fetch('../config/api.php?action=get_stores');
                const result = await response.json();
                allStores = result.data || [];

                if (result.success && allStores.length > 0) {
                    tableBody.innerHTML = '';
                    allStores.forEach(store => {
                        const row = tableBody.insertRow();
                        row.className = 'hover:bg-gray-50 transition duration-150 ease-in-out';
                        row.innerHTML = `
                            <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900">${store.id}</td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-800 font-medium">${store.store_name}</td>
                            <td class="px-4 py-3 text-sm text-gray-800">${store.address || '-'}</td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm font-medium">
                                <button onclick="editStore(${store.id})" class="text-indigo-600 hover:text-indigo-900 text-xs font-semibold mr-2 bg-indigo-100 px-2 py-1 rounded">Edit</button>
                                <button onclick="deleteStore(${store.id})" class="text-red-600 hover:text-red-900 text-xs font-semibold bg-red-100 px-2 py-1 rounded">Hapus</button>
                            </td>
                        `;
                    });
                } else {
                    tableBody.innerHTML = '<tr><td colspan="4" class="text-center py-4 text-gray-500">Tidak ada data Store.</td></tr>';
                }
            } catch (error) {
                tableBody.innerHTML = `<tr><td colspan="4" class="text-center py-4 text-red-500">Gagal memuat data: ${error.message}</td></tr>`;
            }
        }

        function showStoreModal(store = null) {
            const form = document.getElementById('storeForm');
            const title = document.getElementById('modalStoreTitle');
            
            form.reset();

            if (store) {
                title.textContent = 'Edit Store';
                document.getElementById('store_id').value = store.id;
                document.getElementById('store_name').value = store.store_name;
                document.getElementById('store_address').value = store.address || '';
            } else {
                title.textContent = 'Tambah Store Baru';
                document.getElementById('store_id').value = '';
            }
            
            showModal('modalStore');
        }

        function editStore(id) {
            const store = allStores.find(s => s.id === id);
            if (store) showStoreModal(store);
        }

        async function deleteStore(id) {
            if (!confirm('Anda yakin ingin menghapus Store ini? Semua data setoran yang terikat akan kehilangan relasi ke store ini.')) return;

            try {
                const response = await fetch('../config/api.php?action=delete_store', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: id })
                });
                const result = await response.json();
                alert(result.message);
                if (result.success) fetchStores();
            } catch (error) {
                alert('Gagal terhubung ke server.');
            }
        }

        document.getElementById('storeForm').addEventListener('submit', async function (e) {
            e.preventDefault();
            const id = document.getElementById('store_id').value;
            const action = id ? 'edit_store' : 'add_store';
            const formData = {
                id: id,
                store_name: document.getElementById('store_name').value,
                address: document.getElementById('store_address').value,
            };

            try {
                const response = await fetch(`../config/api.php?action=${action}`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(formData)
                });
                const result = await response.json();
                
                if (result.success) {
                    alert(result.message);
                    hideModal('modalStore');
                    fetchStores();
                } else {
                    alert(result.message);
                }
            } catch (error) {
                alert('Gagal terhubung ke server: ' + error.message);
            }
        });


        // =========================================================
        // D. LOGIKA MANAJEMEN EMPLOYEE (DIPERTAHANKAN)
        // =========================================================

        async function fetchEmployees() {
            const tableBody = document.getElementById('employeesTableBody');
            tableBody.innerHTML = '<tr><td colspan="6" class="text-center py-4 text-gray-500">Memuat data...</td></tr>';

            try {
                const response = await fetch('../config/api.php?action=get_employees');
                const result = await response.json();
                allEmployees = result.data || [];

                if (result.success && allEmployees.length > 0) {
                    tableBody.innerHTML = '';
                    allEmployees.forEach(employee => {
                        const row = tableBody.insertRow();
                        row.className = 'hover:bg-gray-50 transition duration-150 ease-in-out';
                        const isActive = employee.is_active == 1;
                        const statusClass = isActive ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800';
                        const statusText = isActive ? 'Aktif' : 'Nonaktif';
                        const toggleButtonText = isActive ? 'Nonaktifkan' : 'Aktifkan';
                        const toggleButtonClass = isActive ? 'text-red-600 bg-red-100' : 'text-green-600 bg-green-100';

                        row.innerHTML = `
                            <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900">${employee.id}</td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-800 font-medium">${employee.employee_name}</td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600">${employee.employee_code || '-'}</td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600">${employee.store_name || 'N/A'}</td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm font-semibold text-center"><span class="px-2 py-0.5 rounded-full text-xs ${statusClass}">${statusText}</span></td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm font-medium">
                                <button onclick="editEmployee(${employee.id})" class="text-indigo-600 hover:text-indigo-900 text-xs font-semibold mr-2 bg-indigo-100 px-2 py-1 rounded">Edit</button>
                                <button onclick="toggleEmployeeStatus(${employee.id})" class="text-xs font-semibold px-2 py-1 rounded ${toggleButtonClass}">${toggleButtonText}</button>
                            </td>
                        `;
                    });
                } else {
                    tableBody.innerHTML = '<tr><td colspan="6" class="text-center py-4 text-gray-500">Tidak ada data Karyawan.</td></tr>';
                }
            } catch (error) {
                tableBody.innerHTML = `<tr><td colspan="6" class="text-center py-4 text-red-500">Gagal memuat data: ${error.message}</td></tr>`;
            }
        }

        async function populateStoreDropdown(selectId, selectedId = null) {
            if (allStores.length === 0) {
                const response = await fetch('../config/api.php?action=get_stores');
                const result = await response.json();
                allStores = result.data || [];
            }

            const selectEl = document.getElementById(selectId);
            selectEl.innerHTML = '<option value="" disabled selected>Pilih Store</option>';

            allStores.forEach(store => {
                const option = new Option(store.store_name, store.id);
                if (store.id == selectedId) option.selected = true;
                selectEl.appendChild(option);
            });
        }

        async function showEmployeeModal(employee = null) {
            const form = document.getElementById('employeeForm');
            const title = document.getElementById('modalEmployeeTitle');
            
            form.reset();

            // Populate store dropdown
            await populateStoreDropdown('employee_store_id', employee ? employee.store_id : null);

            if (employee) {
                title.textContent = 'Edit Karyawan';
                document.getElementById('employee_id').value = employee.id;
                document.getElementById('employee_name').value = employee.employee_name;
                document.getElementById('employee_code').value = employee.employee_code || '';
                document.getElementById('employee_is_active').value = employee.is_active;
                document.getElementById('employee_status_container').style.display = 'block';
            } else {
                title.textContent = 'Tambah Karyawan Baru';
                document.getElementById('employee_id').value = '';
                document.getElementById('employee_status_container').style.display = 'none';
            }
            
            showModal('modalEmployee');
        }

        function editEmployee(id) {
            const employee = allEmployees.find(e => e.id === id);
            if (employee) showEmployeeModal(employee);
        }

        async function toggleEmployeeStatus(id) {
            const employee = allEmployees.find(e => e.id === id);
            const newStatus = employee.is_active == 1 ? 0 : 1;
            const statusText = newStatus == 1 ? 'Mengaktifkan' : 'Menonaktifkan';

            if (!confirm(`Anda yakin ingin ${statusText} Karyawan ${employee.employee_name}?`)) return;

            try {
                const response = await fetch('../config/api.php?action=edit_employee', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        id: id,
                        employee_name: employee.employee_name,
                        store_id: employee.store_id,
                        is_active: newStatus
                    })
                });
                const result = await response.json();
                alert(result.message);
                if (result.success) fetchEmployees();
            } catch (error) {
                alert('Gagal terhubung ke server.');
            }
        }

        document.getElementById('employeeForm').addEventListener('submit', async function (e) {
            e.preventDefault();
            const id = document.getElementById('employee_id_field').value;
            const action = id ? 'edit_employee' : 'add_employee';

            const formData = {
                id: id,
                employee_name: document.getElementById('employee_name_field').value,
                employee_code: document.getElementById('employee_code_field').value,
                store_id: document.getElementById('employee_store_id_field').value,
                is_active: document.getElementById('employee_is_active_field').checked ? 1 : 0,
            };

            const messageEl = document.getElementById('employeeMessage');
            messageEl.classList.add('hidden');

            try {
                const response = await fetch(`../config/api.php?action=${action}`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(formData)
                });
                const result = await response.json();
                messageEl.textContent = result.message;
                messageEl.classList.remove('hidden');
                messageEl.classList.add(result.success ? 'text-green-600' : 'text-red-600');

                if (result.success) {
                    setTimeout(() => { hideModal('employeeModal'); fetchEmployees(); }, 1500);
                }
            } catch (error) {
                messageEl.textContent = 'Gagal terhubung ke server.';
                messageEl.classList.remove('hidden');
                messageEl.classList.add('text-red-600');
            }
        });
        // =========================================================
        // E. DASHBOARD WALLET & CASHFLOW
        // =========================================================

    // =========================================================
// E. DASHBOARD WALLET & CASHFLOW
// =========================================================
     async function fetchDashboardData() {
            const month = document.getElementById('filter_month_dashboard').value || '<?php echo $current_month; ?>';
            const year = document.getElementById('filter_year_dashboard').value || '<?php echo $current_year; ?>';
            const store_id = document.getElementById('filter_store_dashboard')?.value || '';

            console.log('🔍 Fetching dashboard data for:', { month, year, store_id });

            // --- 1. Ambil data Wallet dan Breakdown (get_dashboard_wallet) ---
            try {
                const walletResponse = await fetch(`../config/api.php?action=get_dashboard_wallet&month=${month}&year=${year}&store_id=${store_id}`);
                const walletResult = await walletResponse.json();

                console.log('✅ Wallet data received:', walletResult);

                if (walletResult.success) {
                    updateDashboardDisplay(walletResult.data);
                    
                    // Charts have been removed from UI - commented out initialization
                    // if (typeof initializeDashboardCharts === 'function') {
                    //     console.log('📊 Initializing dashboard charts...');
                    //     initializeDashboardCharts(walletResult.data);
                    // } else {
                    //     console.warn('⚠️ initializeDashboardCharts function not found');
                    // }
                } else {
                    console.error('API Error fetching dashboard wallet:', walletResult.message);
                }
            } catch (error) {
                console.error('Error fetching dashboard wallet:', error);
            }

            // --- 2. Ambil data Ringkasan Cash Flow (gunakan endpoint yang benar) ---
            try {
                const cashflowUrl = `../config/api.php?action=get_management_cash_flow&month=${month}&year=${year}&store_id=${store_id}`;
                const cashflowResponse = await fetch(cashflowUrl);
                const cashflowResult = await cashflowResponse.json();

                if (cashflowResult.success) {
                    // Gunakan data yang sudah ada untuk summary
                    const transactions = Array.isArray(cashflowResult.data) ? cashflowResult.data : [];
                    const summary = cashflowResult.summary || {};

                    // Update summary cards di dashboard jika ada
                    if (summary.total_pemasukan_manajemen !== undefined) {
                        document.getElementById('cashflow_total_income').textContent =
                            `Rp ${formatRupiah(summary.total_pemasukan_manajemen)}`;
                        document.getElementById('cashflow_total_expense').textContent =
                            `Rp ${formatRupiah(summary.total_pengeluaran_manajemen)}`;
                        document.getElementById('cashflow_balance').textContent =
                            `Rp ${formatRupiah(summary.saldo_bersih)}`;
                    }
                } else {
                    console.error('API Error fetching cashflow data:', cashflowResult.message);
                }
            } catch (error) {
                console.error('Error fetching cashflow data for dashboard:', error);
            }

            // --- 3. Ambil data BBM Summary ---
            try {
                if (typeof getBBMSummary === 'function') {
                    console.log('⛽ Loading BBM Summary...');
                    await getBBMSummary(month, year);
                } else {
                    console.warn('⚠️ getBBMSummary function not found');
                }
            } catch (error) {
                console.error('Error loading BBM summary:', error);
            }
        }

        function updateDashboardDisplay(data) {
            // Update Wallet Utama
            document.getElementById('wallet_all_income').textContent = formatRupiah(data.all_stores.total_income);
            document.getElementById('wallet_all_expense').textContent = formatRupiah(data.all_stores.total_expense);
            document.getElementById('wallet_all_balance').textContent = formatRupiah(data.all_stores.balance);
            document.getElementById('wallet_all_liter').textContent = parseFloat(data.all_stores.total_liter).toFixed(2) + ' L';

            // Update Expense Breakdown
            const expenseContainer = document.getElementById('expense_breakdown');
            expenseContainer.innerHTML = '';

            if (data.expense_breakdown && data.expense_breakdown.length > 0) {
                data.expense_breakdown.forEach(item => {
                    const percentage = ((item.amount / data.all_stores.total_expense) * 100).toFixed(1);
                    expenseContainer.innerHTML += `
                <div class="flex justify-between items-center p-3 bg-red-50 rounded-lg">
                    <div class="flex-1">
                        <p class="text-sm font-medium text-gray-800">${item.description}</p>
                        <div class="w-full bg-gray-200 rounded-full h-2 mt-1">
                            <div class="bg-red-600 h-2 rounded-full" style="width: ${percentage}%"></div>
                        </div>
                    </div>
                    <div class="ml-4 text-right">
                        <p class="text-sm font-bold text-red-600">${formatRupiah(item.amount)}</p>
                        <p class="text-xs text-gray-500">${percentage}%</p>
                    </div>
                </div>
            `;
                });
            } else {
                expenseContainer.innerHTML = '<p class="text-gray-500 text-sm">Tidak ada pengeluaran</p>';
            }

            // Update Income Breakdown
            const incomeContainer = document.getElementById('income_breakdown');
            incomeContainer.innerHTML = '';

            if (data.income_breakdown && data.income_breakdown.length > 0) {
                data.income_breakdown.forEach(item => {
                    const percentage = ((item.amount / data.all_stores.total_income) * 100).toFixed(1);
                    incomeContainer.innerHTML += `
                <div class="flex justify-between items-center p-3 bg-green-50 rounded-lg">
                    <div class="flex-1">
                        <p class="text-sm font-medium text-gray-800">${item.description}</p>
                        <div class="w-full bg-gray-200 rounded-full h-2 mt-1">
                            <div class="bg-green-600 h-2 rounded-full" style="width: ${percentage}%"></div>
                        </div>
                    </div>
                    <div class="ml-4 text-right">
                        <p class="text-sm font-bold text-green-600">${formatRupiah(item.amount)}</p>
                        <p class="text-xs text-gray-500">${percentage}%</p>
                    </div>
                </div>
            `;
                });
            } else {
                incomeContainer.innerHTML = '<p class="text-gray-500 text-sm">Tidak ada pemasukan</p>';
            }

            // Update Wallet Per Store (Logika ini seharusnya diganti dengan displayDashboardSummary)
            const storeContainer = document.getElementById('wallet_per_store');
            storeContainer.innerHTML = '';

            if (data.per_store && data.per_store.length > 0) {
                data.per_store.forEach(store => {
                    const balanceClass = store.balance >= 0 ? 'text-green-600' : 'text-red-600';
                    storeContainer.innerHTML += `
                <div class="border border-gray-200 rounded-lg p-4 hover:shadow-lg transition">
                    <h4 class="font-bold text-lg mb-3 text-gray-800">${store.store_name}</h4>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                        <div>
                            <p class="text-xs text-gray-500">Pemasukan</p>
                            <p class="text-sm font-bold text-green-600">${formatRupiah(store.income)}</p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500">Pengeluaran</p>
                            <p class="text-sm font-bold text-red-600">${formatRupiah(store.expense)}</p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500">Saldo</p>
                            <p class="text-sm font-bold ${balanceClass}">${formatRupiah(store.balance)}</p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500">Total Liter</p>
                            <p class="text-sm font-bold text-indigo-600">${parseFloat(store.total_liter).toFixed(2)} L</p>
                        </div>
                    </div>
                </div>
            `;
                });
            } else {
                storeContainer.innerHTML = '<p class="text-gray-500 text-sm">Tidak ada data store</p>';
            }
        }

// Update store wallet data - HANYA CASHFLOW MANAGEMENT
function updateStoreWallet(stores) {
    const container = document.getElementById('wallet_per_store');
    if (!container) {
        console.warn('⚠️ Container #wallet_per_store not found');
        return;
    }

    if (stores && stores.length > 0) {
        let storeHtml = '';
        
        stores.forEach(store => {
            const income = parseFloat(store.income) || 0;
            const expense = parseFloat(store.expense) || 0;
            const balance = parseFloat(store.balance) || 0;
            
            const balanceClass = balance >= 0 ? 'text-green-600' : 'text-red-600';
            const storeName = store.store_name || 'Toko Tidak Dikenal';
            
            storeHtml += `
                <div class="border border-gray-200 rounded-lg p-4 hover:shadow-lg transition mb-4">
                    <h4 class="font-bold text-lg mb-3 text-gray-800">${storeName}</h4>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                        <div>
                            <p class="text-xs text-gray-500">Pemasukan Operasional</p>
                            <p class="text-sm font-bold text-green-600">${formatRupiah(income)}</p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500">Pengeluaran Operasional</p>
                            <p class="text-sm font-bold text-red-600">${formatRupiah(expense)}</p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500">Saldo Operasional</p>
                            <p class="text-sm font-bold ${balanceClass}">${formatRupiah(balance)}</p>
                        </div>
                    </div>
                    <div class="mt-3 pt-3 border-t border-gray-100">
                        <p class="text-xs text-gray-500">Total Liter Terjual</p>
                        <p class="text-sm font-bold text-indigo-600">${parseFloat(store.total_liter || 0).toFixed(2)} L</p>
                    </div>
                </div>
            `;
        });
        container.innerHTML = storeHtml;
    } else {
        container.innerHTML = `
            <div class="text-center py-8">
                <svg class="w-16 h-16 text-gray-300 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                          d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                </svg>
                <p class="text-gray-500 text-sm">Tidak ada data operasional store</p>
                <p class="text-gray-400 text-xs mt-1">Data akan muncul setelah ada transaksi cash flow di Manajemen Kas</p>
            </div>
        `;
    }
}

        function updateDashboardDisplay(data) {
            // Update Wallet Utama
            document.getElementById('wallet_all_income').textContent = formatRupiah(data.all_stores.total_income);
            document.getElementById('wallet_all_expense').textContent = formatRupiah(data.all_stores.total_expense);
            document.getElementById('wallet_all_balance').textContent = formatRupiah(data.all_stores.balance);
            document.getElementById('wallet_all_liter').textContent = parseFloat(data.all_stores.total_liter).toFixed(2) + ' L';

            // Update Expense Breakdown
            const expenseContainer = document.getElementById('expense_breakdown');
            expenseContainer.innerHTML = '';

            if (data.expense_breakdown && data.expense_breakdown.length > 0) {
                data.expense_breakdown.forEach(item => {
                    const percentage = ((item.amount / data.all_stores.total_expense) * 100).toFixed(1);
                    expenseContainer.innerHTML += `
                <div class="flex justify-between items-center p-3 bg-red-50 rounded-lg">
                    <div class="flex-1">
                        <p class="text-sm font-medium text-gray-800">${item.description}</p>
                        <div class="w-full bg-gray-200 rounded-full h-2 mt-1">
                            <div class="bg-red-600 h-2 rounded-full" style="width: ${percentage}%"></div>
                        </div>
                    </div>
                    <div class="ml-4 text-right">
                        <p class="text-sm font-bold text-red-600">${formatRupiah(item.amount)}</p>
                        <p class="text-xs text-gray-500">${percentage}%</p>
                    </div>
                </div>
            `;
                });
            } else {
                expenseContainer.innerHTML = '<p class="text-gray-500 text-sm">Tidak ada pengeluaran</p>';
            }

            // Update Income Breakdown
            const incomeContainer = document.getElementById('income_breakdown');
            incomeContainer.innerHTML = '';

            if (data.income_breakdown && data.income_breakdown.length > 0) {
                data.income_breakdown.forEach(item => {
                    const percentage = ((item.amount / data.all_stores.total_income) * 100).toFixed(1);
                    incomeContainer.innerHTML += `
                <div class="flex justify-between items-center p-3 bg-green-50 rounded-lg">
                    <div class="flex-1">
                        <p class="text-sm font-medium text-gray-800">${item.description}</p>
                        <div class="w-full bg-gray-200 rounded-full h-2 mt-1">
                            <div class="bg-green-600 h-2 rounded-full" style="width: ${percentage}%"></div>
                        </div>
                    </div>
                    <div class="ml-4 text-right">
                        <p class="text-sm font-bold text-green-600">${formatRupiah(item.amount)}</p>
                        <p class="text-xs text-gray-500">${percentage}%</p>
                    </div>
                </div>
            `;
                });
            } else {
                incomeContainer.innerHTML = '<p class="text-gray-500 text-sm">Tidak ada pemasukan</p>';
            }

            // Update Wallet Per Store (Logika ini seharusnya diganti dengan displayDashboardSummary)
            const storeContainer = document.getElementById('wallet_per_store');
            storeContainer.innerHTML = '';

            if (data.per_store && data.per_store.length > 0) {
                data.per_store.forEach(store => {
                    const balanceClass = store.balance >= 0 ? 'text-green-600' : 'text-red-600';
                    storeContainer.innerHTML += `
                <div class="border border-gray-200 rounded-lg p-4 hover:shadow-lg transition">
                    <h4 class="font-bold text-lg mb-3 text-gray-800">${store.store_name}</h4>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                        <div>
                            <p class="text-xs text-gray-500">Pemasukan</p>
                            <p class="text-sm font-bold text-green-600">${formatRupiah(store.income)}</p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500">Pengeluaran</p>
                            <p class="text-sm font-bold text-red-600">${formatRupiah(store.expense)}</p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500">Saldo</p>
                            <p class="text-sm font-bold ${balanceClass}">${formatRupiah(store.balance)}</p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500">Total Liter</p>
                            <p class="text-sm font-bold text-indigo-600">${parseFloat(store.total_liter).toFixed(2)} L</p>
                        </div>
                    </div>
                </div>
            `;
                });
            } else {
                storeContainer.innerHTML = '<p class="text-gray-500 text-sm">Tidak ada data store</p>';
            }
        }

        // Fungsi untuk mengambil data toko dan karyawan (untuk filter)
        // 🔥 PERBAIKAN untuk error Baris 653: Menambahkan || [] pada hasil API
        async function fetchAllStoresAndEmployeesForFilter() {
            try {
                // --- Ambil Store untuk filter dan modal ---
                const storeResponse = await fetch('../config/api.php?action=get_stores');
                const storeResult = await storeResponse.json();

                if (storeResult.success && Array.isArray(storeResult.data)) {
                    allStores = storeResult.data;

                    // FILTER: Setoran
                    const filterStoreSelectSetoran = document.getElementById('filter_store_id_setoran');
                    if (filterStoreSelectSetoran) {
                        const selectedStoreIdSetoran = '<?php echo $selected_store_id; ?>';
                        filterStoreSelectSetoran.innerHTML = '<option value="">Semua Store</option>';
                        allStores.forEach(store => {
                            const opt = new Option(store.store_name, store.id);
                            if (store.id == selectedStoreIdSetoran) opt.selected = true;
                            filterStoreSelectSetoran.appendChild(opt);
                        });
                    }

                    // FILTER: Cashflow
                    const filterStoreSelectCashFlow = document.getElementById('filter_store_cashflow');
                    if (filterStoreSelectCashFlow) {
                        filterStoreSelectCashFlow.innerHTML = '<option value="">Semua Store</option>';
                        allStores.forEach(store => {
                            const opt = new Option(store.store_name, store.id);
                            filterStoreSelectCashFlow.appendChild(opt);
                        });
                    }

                    // MODAL: Cashflow
                    const modalStoreSelectCashFlow = document.getElementById('cashflow_store_id');
                    if (modalStoreSelectCashFlow) {
                        modalStoreSelectCashFlow.innerHTML = '<option value="" disabled selected>Pilih Store</option>';
                        allStores.forEach(store => {
                            const opt = new Option(store.store_name, store.id);
                            modalStoreSelectCashFlow.appendChild(opt);
                        });
                    }
                }

                // --- Ambil Employee untuk filter setoran ---
                const employeeResponse = await fetch('../config/api.php?action=get_employees');
                const employeeResult = await employeeResponse.json();

                if (employeeResult.success && Array.isArray(employeeResult.data)) {
                    allEmployees = employeeResult.data;

                    const filterEmployeeSelect = document.getElementById('filter_employee_id_setoran');
                    if (filterEmployeeSelect) {
                        const selectedEmployeeId = '<?php echo $selected_employee_id; ?>';
                        filterEmployeeSelect.innerHTML = '<option value="">Semua Karyawan</option>';
                        allEmployees.forEach(emp => {
                            const opt = new Option(`${emp.employee_name} (${emp.store_name})`, emp.id);
                            if (emp.id == selectedEmployeeId) opt.selected = true;
                            filterEmployeeSelect.appendChild(opt);
                        });
                    }
                }

            } catch (error) {
                console.error('Gagal memuat data store/employee:', error);
            }
        }


        // Fungsi baru untuk menampilkan ringkasan per store
        function displayDashboardSummary(summaryData) {
            const tableBody = document.getElementById('dashboardTableBody');
            let html = '';

            // 🔥 PERBAIKAN untuk error Baris 1438: Pemeriksaan null untuk mencegah error
            if (!tableBody) {
                console.error("Kesalahan Fatal: Elemen dengan ID 'dashboardTableBody' tidak ditemukan di DOM.");
                // SOLUSI NYATA: Pastikan HTML Anda memiliki <tbody id="dashboardTableBody">
                return;
            }

            if (summaryData.length === 0) {
                html = '<tr><td colspan="4" class="text-center py-4">Tidak ada data setoran dan cash flow untuk periode ini.</td></tr>';
            } else {
                summaryData.forEach(store => {
                    // Format angka ke mata uang
                    const formatRupiah = (number) => new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(number);

                    html += `
                <tr>
                    <td class="px-6 py-4 whitespace-nowrap font-medium">${store.store_name}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-green-600">${formatRupiah(store.total_pemasukan)}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-red-600">${formatRupiah(store.total_pengeluaran)}</td>
                    <td class="px-6 py-4 whitespace-nowrap font-bold">${formatRupiah(store.saldo_bersih)}</td>
                </tr>
            `;
                });
            }

            tableBody.innerHTML = html;
        }


        document.getElementById('filterFormDashboard').addEventListener('submit', function (e) {
            e.preventDefault();
            fetchDashboardData();
        });

        document.getElementById('resetFilterBtnDashboard').addEventListener('click', function () {
            document.getElementById('filter_month_dashboard').value = '<?php echo $current_month; ?>';
            document.getElementById('filter_year_dashboard').value = '<?php echo $current_year; ?>';
            fetchDashboardData();
        });

        function exportDashboard(type) {
            const month = document.getElementById('filter_month_dashboard').value;
            const year = document.getElementById('filter_year_dashboard').value;
            const storeId = document.getElementById('filter_store_dashboard').value;

            if (type === 'excel') {
                const params = new URLSearchParams({
                    action: 'export_dashboard_excel',
                    month: month,
                    year: year,
                    store_id: storeId
                });
                window.open(`export.php?${params}`, '_blank');
            } else if (type === 'pdf') {
                alert('Format PDF coming soon!');
            }
        }

        // Export functions for Store and Employee
        function exportStores() {
            window.open('export.php?action=export_stores_excel', '_blank');
        }

        function exportEmployees() {
            window.open('export.php?action=export_employees_excel', '_blank');
        }

        // Execute on load
        document.addEventListener('DOMContentLoaded', function () {
            // Panggil semua fungsi muat data awal
            fetchAllStoresAndEmployeesForFilter();

            // Tentukan tab mana yang harus aktif saat pertama kali dibuka (default: dashboard)
            const activeTabButton = document.querySelector('.tab-button.active-tab');
            if (activeTabButton && activeTabButton.dataset.tab === 'dashboard') {
                fetchDashboardData();
            }
        });

        // Debug function untuk memeriksa data
        function debugCashFlowData() {
            const form = document.getElementById('cashflowForm');
            const formData = new FormData(form);
            const data = Object.fromEntries(formData.entries());
            console.log('Form Data:', data);

            // Check required fields
            const required = ['tanggal', 'store_id', 'type', 'category', 'description', 'amount'];
            const missing = required.filter(field => !data[field]);
            if (missing.length > 0) {
                console.error('Missing fields:', missing);
            }

            return data;
        }
        // =========================================================
        // DEBUG FORM DATA
        // =========================================================
        function debugFormData() {
            const form = document.getElementById('cashflowForm');
            const formData = new FormData(form);
            const data = Object.fromEntries(formData.entries());

            console.log('🔍 FORM DATA DEBUG:');
            console.log('Tanggal:', data.tanggal, '->', data.tanggal ? '✅' : '❌');
            console.log('Store ID:', data.store_id, '->', data.store_id ? '✅' : '❌');
            console.log('Type:', data.type, '->', data.type ? '✅' : '❌');
            console.log('Category:', data.category, '->', data.category ? '✅' : '❌');
            console.log('Description:', data.description, '->', data.description ? '✅' : '❌');
            console.log('Amount:', data.amount, '->', data.amount ? '✅' : '❌');
            console.log('Full data:', data);

            return data;
        }
        function ensureTypeSelectOptions() {
            const typeSelect = document.getElementById('cashflow_type');
            if (!typeSelect) {
                console.error('❌ cashflow_type element not found');
                return false;
            }

            // Check if options exist
            if (typeSelect.options.length <= 1) {
                console.log('🔄 Adding options to type select...');
                typeSelect.innerHTML = `
            <option value="">Pilih Jenis</option>
            <option value="Pemasukan">Pemasukan</option>
            <option value="Pengeluaran">Pengeluaran</option>
        `;
                console.log('✅ Type select options added');
            }

            // Ensure a value is selected
            if (!typeSelect.value) {
                typeSelect.value = 'Pengeluaran';
                console.log('✅ Type select value set to:', typeSelect.value);
            }

            return true;
        }
        // =========================================================
        // EMERGENCY FORM FIX - COMPREHENSIVE
        // =========================================================
        function emergencyFormFix() {
            console.log('🚨 APPLYING COMPREHENSIVE FORM FIX');

            // Fix type select first
            ensureTypeSelectOptions();

            // Ensure amount has value  
            const amountInput = document.getElementById('cashflow_amount');
            if (amountInput && !amountInput.value) {
                amountInput.value = '5000000';
                console.log('🚨 Emergency fixed amount to:', amountInput.value);
            }

            // Ensure BBM calculation is triggered
            const category = document.getElementById('cashflow_category').value;
            if (category === 'bbm') {
                setTimeout(() => {
                    fixBBMCalculation();
                }, 100);
            }

            // Final verification
            setTimeout(() => {
                const formData = new FormData(document.getElementById('cashflowForm'));
                const data = Object.fromEntries(formData.entries());
                console.log('🚨 AFTER EMERGENCY FIX - Type:', data.type, 'Amount:', data.amount);
            }, 200);
        }
        // =========================================================
        // COMPLETE TEST FUNCTION - ULTIMATE VERSION
        // =========================================================
        function completeFormTest() {
            console.log('🧪 COMPLETE FORM TEST - FIXED VERSION');

            // Step 1: Set all required values
            document.getElementById('cashflow_date').value = '2025-10-23';
            document.getElementById('cashflow_category').value = 'bbm';
            document.getElementById('cashflow_type').value = 'Pengeluaran';
            document.getElementById('cashflow_description').value = 'Pembelian BBM Test';
            document.getElementById('cashflow_amount').value = '1000000';

            // Step 2: Pilih store yang valid (bukan 0)
            if (allStores.length > 0) {
                const firstStoreId = allStores[0].id;
                document.querySelector('.store-check').checked = true;
                console.log('✅ Selected store ID:', firstStoreId);
            }

            // Step 3: Set jumlah drigen
            setTimeout(() => {
                const drigenInputs = document.querySelectorAll('.drigen-input');
                if (drigenInputs.length > 0) {
                    drigenInputs[0].value = '2';
                    console.log('✅ Set drigen quantity');
                }

                // Trigger calculation
                const hargaInput = document.getElementById('bbm_harga');
                if (hargaInput) {
                    const event = new Event('input', { bubbles: true });
                    hargaInput.dispatchEvent(event);
                }
            }, 100);

            console.log('✅ Test setup completed');

            // Step 4: Manual verification
            setTimeout(() => {
                const formData = new FormData(document.getElementById('cashflowForm'));
                const data = Object.fromEntries(formData.entries());

                console.log('🔍 FINAL FORM STATE:');
                console.log('Type:', data.type);
                console.log('Amount:', data.amount);
                console.log('Store BBM:', data['store_bbm[]']);
                console.log('Category:', data.category);

                // Auto submit jika data sudah ok
                if (data.type && data.amount && data['store_bbm[]']) {
                    console.log('🔄 All values good, submitting...');
                    document.getElementById('cashflowForm').dispatchEvent(new Event('submit'));
                }
            }, 500);
        }

        // Jalankan test lengkap:
        completeFormTest()
        function nuclearModalRefresh() {
            console.log('💥 NUCLEAR MODAL REFRESH - RECREATING MODAL');

            // Close modal
            hideModal('modalCashflow');

            // Clear any cached form data
            localStorage.removeItem('cashflow_form_cache');

            // Reopen after delay
            setTimeout(() => {
                showCashFlowModal();

                // Wait for modal to fully render, then test
                setTimeout(() => {
                    console.log('🔄 Modal reopened, running complete test...');
                    completeFormTest();
                }, 1000);
            }, 500);
        }

        // Jalankan nuclear option jika diperlukan:
        // nuclearModalRefresh()

        // =========================================================
        // FIX BBM CALCULATION - MISSING FUNCTION
        // =========================================================
        function fixBBMCalculation() {
            console.log('🔧 FIXING BBM CALCULATION...');

            try {
                // Set harga dan pajak default
                const hargaInput = document.getElementById('bbm_harga');
                const pajakInput = document.getElementById('bbm_pajak');

                if (hargaInput) {
                    hargaInput.value = '340000';
                    console.log('✅ Set BBM harga:', hargaInput.value);
                }

                if (pajakInput) {
                    pajakInput.value = '12000';
                    console.log('✅ Set BBM pajak:', pajakInput.value);
                }

                // Set jumlah drigen untuk store yang checked
                const storeCheckboxes = document.querySelectorAll('.store-check:checked');
                console.log('🔍 Found', storeCheckboxes.length, 'checked stores');

                storeCheckboxes.forEach((checkbox, index) => {
                    const storeId = checkbox.value;
                    const drigenInput = document.querySelector(`input[name="jumlah_drigen[${storeId}]"]`);
                    if (drigenInput) {
                        drigenInput.value = (index + 1) * 2; // 2, 4, 6, etc
                        console.log(`✅ Set drigen for store ${storeId}: ${drigenInput.value}`);

                        // Trigger input event untuk kalkulasi
                        setTimeout(() => {
                            const event = new Event('input', { bubbles: true });
                            drigenInput.dispatchEvent(event);
                        }, 50);
                    }
                });

                // Trigger calculation on harga input
                setTimeout(() => {
                    if (hargaInput) {
                        const event = new Event('input', { bubbles: true });
                        hargaInput.dispatchEvent(event);
                    }
                }, 200);

                console.log('✅ BBM calculation fix completed');
            } catch (error) {
                console.error('❌ Error in BBM calculation fix:', error);
            }
        }

        async function testCashFlowSubmission() {
            const testData = {
                tanggal: '2025-10-23',
                store_id: 1,
                type: 'Pengeluaran',
                category: 'bbm',
                description: 'Test BBM Manual',
                amount: 1000000
            };

            try {
                console.log('🧪 Testing cashflow submission...');

                const response = await fetch('../config/api.php?action=add_management_cash_flow', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(testData)
                });

                const result = await response.json();
                console.log('Test Result:', result);

                if (result.success) {
                    alert('✅ Test berhasil! Data bisa masuk ke database.');
                } else {
                    alert('❌ Test gagal: ' + result.message);
                }
            } catch (error) {
                console.error('Test Error:', error);
                alert('❌ Test error: ' + error.message);
            }
        }

        
        // Panggil fungsi test
        // testCashFlowSubmission();
        // Fungsi untuk mengambil summary BBM per store
async function getBBMSummary(month = null, year = null) {
    try {
        if (!month) month = document.getElementById('filter_month_cashflow')?.value || new Date().getMonth() + 1;
        if (!year) year = document.getElementById('filter_year_cashflow')?.value || new Date().getFullYear();

        const response = await fetch(`../config/api.php?action=get_bbm_detailed_summary&month=${month}&year=${year}`);
        const result = await response.json();

        console.log('📊 BBM Summary Result:', result);

        if (result.success) {
            displayBBMSummary(result.data);
        } else {
            console.error('Gagal memuat summary BBM:', result.message);
            showBBMError(result.message);
        }
    } catch (error) {
        console.error('Error fetching BBM summary:', error);
        showBBMError(error.message);
    }
}

// Fungsi untuk refresh data cashflow sebelum menampilkan BBM summary
async function refreshBBMSummary() {
    console.log('🔄 Refreshing cashflow data first...');
    
    // Refresh data cashflow terlebih dahulu
    await fetchCashFlowData();
    
    // Tunggu sebentar lalu tampilkan BBM summary
    setTimeout(() => {
        getBBMSummary();
    }, 500);
}

// Fungsi untuk menampilkan error
function showBBMError(message) {
    const container = document.getElementById('bbmSummaryContainer');
    if (!container) return;

    container.innerHTML = `
        <div class="bg-red-50 border border-red-200 rounded-lg p-4">
            <div class="flex items-center">
                <svg class="w-5 h-5 text-red-400 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <h3 class="text-red-800 font-medium">Gagal memuat data BBM</h3>
            </div>
            <p class="text-red-600 text-sm mt-1">${message}</p>
        </div>
    `;
}

// Fungsi untuk menampilkan summary BBM
function displayBBMSummary(data) {
    const tbody = document.getElementById('bbmTableBody');
    const tfoot = document.querySelector('#bbmSummaryContainer tfoot');
    
    if (!tbody || !tfoot) return;

    if (!data || data.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="5" class="px-4 py-8 text-center text-gray-500">
                    <div class="flex flex-col items-center justify-center">
                        <svg class="w-12 h-12 text-gray-300 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                  d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                        </svg>
                        <p class="text-gray-400 font-medium">Belum ada data pembelian BBM</p>
                        <p class="text-gray-400 text-sm mt-1">Tidak ada transaksi BBM untuk periode yang dipilih</p>
                    </div>
                </td>
            </tr>
        `;
        
        // Reset footer ke 0
        tfoot.innerHTML = `
            <tr>
                <td class="px-4 py-3 whitespace-nowrap text-gray-900">TOTAL</td>
                <td class="px-4 py-3 whitespace-nowrap text-center text-gray-900">
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-blue-600 text-white">
                        0 drigen
                    </span>
                </td>
                <td class="px-4 py-3 whitespace-nowrap text-right text-blue-700">
                    Rp 0
                </td>
                <td class="px-4 py-3 whitespace-nowrap text-right text-orange-700">
                    Rp 0
                </td>
                <td class="px-4 py-3 whitespace-nowrap text-right text-green-700">
                    Rp 0
                </td>
            </tr>
        `;
        return;
    }

    // Konstanta harga pokok tetap
    const HARGA_POKOK_PER_DRIGEN = 340000;

    let html = '';
    let totalAllDrigen = 0;
    let totalAllHargaPokok = 0;
    let totalAllPajak = 0;
    let totalAllAmount = 0;

    data.forEach(store => {
        // Harga pokok tetap berdasarkan jumlah drigen
        const hargaPokok = store.total_drigen * HARGA_POKOK_PER_DRIGEN;
        
        // Pajak dihitung dari selisih total amount dengan harga pokok
        const pajak = store.total_amount - hargaPokok;
        
        // Hitung pajak per drigen
        const pajakPerDrigen = store.total_drigen > 0 ? Math.round(pajak / store.total_drigen) : 0;
        
        totalAllDrigen += store.total_drigen;
        totalAllHargaPokok += hargaPokok;
        totalAllPajak += pajak;
        totalAllAmount += store.total_amount;

        html += `
            <tr class="hover:bg-gray-50">
                <td class="px-4 py-3 whitespace-nowrap">
                    <div class="font-medium ${store.total_drigen > 0 ? 'text-gray-900 font-bold' : 'text-gray-700'}">
                        ${store.store_name}
                    </div>
                    <div class="text-xs text-gray-500">${store.transactions?.length || 0} transaksi</div>
                </td>
                <td class="px-4 py-3 whitespace-nowrap text-center">
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium ${store.total_drigen > 0 ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-500'}">
                        ${store.total_drigen} drigen
                    </span>
                </td>
                <td class="px-4 py-3 whitespace-nowrap text-right text-sm ${store.total_drigen > 0 ? 'text-blue-600' : 'text-gray-500'} font-medium">
                    Rp ${formatRupiah(hargaPokok)}
                    <div class="text-xs text-gray-500">(Rp ${formatRupiah(HARGA_POKOK_PER_DRIGEN)}/drigen)</div>
                </td>
                <td class="px-4 py-3 whitespace-nowrap text-right text-sm ${store.total_drigen > 0 ? 'text-orange-600' : 'text-gray-500'} font-medium">
                    Rp ${formatRupiah(pajak)}
                    ${store.total_drigen > 0 ? `<div class="text-xs text-gray-500">(Rp ${formatRupiah(pajakPerDrigen)}/drigen)</div>` : ''}
                </td>
                <td class="px-4 py-3 whitespace-nowrap text-right font-medium ${store.total_drigen > 0 ? 'text-green-600' : 'text-gray-500'}">
                    Rp ${formatRupiah(store.total_amount)}
                    ${store.total_drigen > 0 ? `<div class="text-xs text-gray-500">(Rp ${formatRupiah(HARGA_POKOK_PER_DRIGEN + pajakPerDrigen)}/drigen)</div>` : ''}
                </td>
            </tr>
        `;
    });

    tbody.innerHTML = html;

    // Hitung rata-rata pajak per drigen untuk total
    const avgPajakPerDrigen = totalAllDrigen > 0 ? Math.round(totalAllPajak / totalAllDrigen) : 0;

    // Update footer total
    tfoot.innerHTML = `
        <tr>
            <td class="px-4 py-3 whitespace-nowrap text-gray-900 font-bold">TOTAL</td>
            <td class="px-4 py-3 whitespace-nowrap text-center text-gray-900">
                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-blue-600 text-white">
                    ${totalAllDrigen} drigen
                </span>
            </td>
            <td class="px-4 py-3 whitespace-nowrap text-right text-blue-700 font-bold">
                Rp ${formatRupiah(totalAllHargaPokok)}
                <div class="text-xs text-gray-600">(Rp ${formatRupiah(HARGA_POKOK_PER_DRIGEN)}/drigen)</div>
            </td>
            <td class="px-4 py-3 whitespace-nowrap text-right text-orange-700 font-bold">
                Rp ${formatRupiah(totalAllPajak)}
                ${totalAllDrigen > 0 ? `<div class="text-xs text-gray-600">(Rp ${formatRupiah(avgPajakPerDrigen)}/drigen)</div>` : ''}
            </td>
            <td class="px-4 py-3 whitespace-nowrap text-right text-green-700 font-bold">
                Rp ${formatRupiah(totalAllAmount)}
                ${totalAllDrigen > 0 ? `<div class="text-xs text-gray-600">(Rp ${formatRupiah(HARGA_POKOK_PER_DRIGEN + avgPajakPerDrigen)}/drigen)</div>` : ''}
            </td>
        </tr>
    `;
}

// Format Rupiah helper function
function formatRupiah(number) {
    return new Intl.NumberFormat('id-ID').format(number);
}

// Auto load BBM summary ketika halaman dimuat
document.addEventListener('DOMContentLoaded', function() {
    // Tunggu sebentar untuk memastikan data store sudah terload
    setTimeout(() => {
        getBBMSummary();
    }, 1000);
});
// --- Refresh Page Function ---
document.getElementById('refreshBtn').addEventListener('click', function() {
    // Tampilkan loading state
    const originalText = this.innerHTML;
    this.innerHTML = `
        <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
        Refreshing...
    `;
    this.disabled = true;

    // Refresh berdasarkan tab yang aktif
    const activeTab = document.querySelector('.tab-button.active-tab').dataset.tab;
    
    setTimeout(() => {
        if (activeTab === 'dashboard') {
            fetchDashboardData();
            getBBMSummary();
        } else if (activeTab === 'setoran') {
            fetchSetoranData();
        } else if (activeTab === 'cashflow') {
            fetchCashFlowData();
        } else if (activeTab === 'stores') {
            fetchStores();
        } else if (activeTab === 'employees') {
            fetchEmployees();
        }
        
        // Reset tombol setelah 1 detik
        setTimeout(() => {
            this.innerHTML = originalText;
            this.disabled = false;
        }, 1000);
        
    }, 500);
});