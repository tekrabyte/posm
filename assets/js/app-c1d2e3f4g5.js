
 
   // Fungsi bantu: parsing angka termasuk desimal dengan koma/titik
    function parseInputNumber(value) {
        if (!value) return 0;
        value = value.toString().replace(',', '.'); // ubah koma jadi titik
        return parseFloat(value) || 0;
    }

    // Global data storage
    let appData = {
        pengeluaran: [],
        pemasukan: [],
        calculations: {
            totalLiter: 0,
            cash: 0,
            qris: 0,
            total: 0,
            totalPengeluaran: 0,
            totalPemasukan: 0,
            totalKeseluruhan: 0
        }
    };

    // Format number with comma as decimal separator and dot as thousand separator
    function formatNumber(number, decimals = 0) {
        return new Intl.NumberFormat('id-ID', {
            minimumFractionDigits: decimals,
            maximumFractionDigits: decimals
        }).format(number);
    }

    // Format rupiah
    function formatRupiah(amount) {
        return formatNumber(amount, 0);
    }

    // Format input QRIS
    function setupQrisInput() {
        const qrisDisplay = document.getElementById('qris_display');
        const qrisValue = document.getElementById('qris_value');
        
        qrisDisplay.addEventListener('input', function(e) {
            // Hanya allow angka
            let value = e.target.value.replace(/\D/g, '');
            
            // Simpan nilai asli
            qrisValue.value = value;
            
            // Format tampilan
            const numericValue = value ? parseInt(value) : 0;
            const formatted = formatRupiah(numericValue);
            qrisDisplay.value = formatted;
           
            
            calculateAll();
        });
    }

    // Calculate all values
    function calculateAll() {
        const nomorAwal = parseInputNumber(document.getElementById('nomor_awal').value);
        const nomorAkhir = parseInputNumber(document.getElementById('nomor_akhir').value);
        const qris = parseInt(document.getElementById('qris_value').value) || 0;

        // Calculate total liter
        const totalLiter = Math.max(0, nomorAkhir - nomorAwal);
        appData.calculations.totalLiter = totalLiter;

        // Calculate total setoran
        const total = totalLiter * 11500;
        appData.calculations.total = total;

        // Calculate cash
        const cash = Math.max(0, total - qris);
        appData.calculations.cash = cash;
        appData.calculations.qris = qris;

        // Calculate totals
        appData.calculations.totalPengeluaran = appData.pengeluaran.reduce((sum, item) => sum + item.amount, 0);
        appData.calculations.totalPemasukan = appData.pemasukan.reduce((sum, item) => sum + item.amount, 0);
        appData.calculations.totalKeseluruhan = cash + appData.calculations.totalPemasukan - appData.calculations.totalPengeluaran;

        updateDisplay();
    }

    // Fungsi validasi terpusat
    function isDataValid() {
        const storeID = document.getElementById('store_id')?.value;
        const employeeID = document.getElementById('employee_id')?.value;
        const jamMasuk = document.getElementById('jam_masuk')?.value;
        const jamKeluar = document.getElementById('jam_keluar')?.value;
        
        // Total Liter harus lebih dari 0
        const totalLiter = appData.calculations.totalLiter;
        
        // Cek semua field wajib dan total liter > 0
        if (!storeID || !employeeID || !jamMasuk || !jamKeluar || totalLiter <= 0) {
            return false;
        }

        return true;
    }

    // Update display
    function updateDisplay() {
        const calc = appData.calculations;
        const isValid = isDataValid();

        // Update meter display
        document.getElementById('total_liter_display').value = formatNumber(calc.totalLiter, 2) + ' L';
        document.getElementById('total_liter_badge').textContent = formatNumber(calc.totalLiter, 2) + ' L';

        // Update setoran display
        document.getElementById('cash_display').value = formatRupiah(calc.cash);
        document.getElementById('qris_display').value = formatRupiah(calc.qris);
        
        document.getElementById('total_display').value = formatRupiah(calc.total);
        document.getElementById('total_badge').textContent = formatRupiah(calc.total);

        // Update totals
        document.getElementById('total_pengeluaran').textContent = 'Rp ' + formatRupiah(calc.totalPengeluaran);
        document.getElementById('total_pemasukan').textContent = 'Rp ' + formatRupiah(calc.totalPemasukan);
        document.getElementById('total_keseluruhan').textContent = 'Rp ' + formatRupiah(calc.totalKeseluruhan);
        document.getElementById('total_breakdown').textContent = 
            `Setoran: ${formatRupiah(calc.cash)} + Pemasukan: ${formatRupiah(calc.totalPemasukan)} - Pengeluaran: ${formatRupiah(calc.totalPengeluaran)}`;

        // Update jam kerja display
        updateJamKerjaDisplay();

        const btnCopy = document.getElementById('btnn');

        if (btnCopy) {
            btnCopy.disabled = !isValid;
            btnCopy.classList.toggle('bg-gray-400', !isValid);
            btnCopy.classList.toggle('hover:bg-blue-700', isValid);
        }
    }

    // Update jam kerja display
    function updateJamKerjaDisplay() {
        const jamMasuk = document.getElementById('jam_masuk').value;
        const jamKeluar = document.getElementById('jam_keluar').value;
        const display = document.getElementById('jam_kerja_display');
        const text = document.getElementById('jam_kerja_text');

        if (jamMasuk && jamKeluar) {
            text.textContent = `(${jamMasuk} - ${jamKeluar})`;
            display.classList.remove('hidden');
        } else {
            display.classList.add('hidden');
        }
    }

    // Render pengeluaran list
    function renderPengeluaranList() {
        const container = document.getElementById('pengeluaran_list');
        container.innerHTML = '';

        appData.pengeluaran.forEach((item, index) => {
            const div = document.createElement('div');
            div.className = 'flex justify-between items-center p-2.5 bg-red-50 rounded-lg mb-2';
            div.innerHTML = `
                <span class="text-sm text-gray-700">${item.description}</span>
                <div class="flex items-center gap-2">
                    <span class="text-sm text-red-600 font-semibold">Rp ${formatRupiah(item.amount)}</span>
                    <button onclick="removePengeluaran(${index})" class="text-red-600 hover:text-red-800 text-sm">✕</button>
                </div>
            `;
            container.appendChild(div);
        });
    }

    // Render pemasukan list
    function renderPemasukanList() {
        const container = document.getElementById('pemasukan_list');
        container.innerHTML = '';

        appData.pemasukan.forEach((item, index) => {
            const div = document.createElement('div');
            div.className = 'flex justify-between items-center p-2.5 bg-green-50 rounded-lg mb-2';
            div.innerHTML = `
                <span class="text-sm text-gray-700">${item.description}</span>
                <div class="flex items-center gap-2">
                    <span class="text-sm text-green-600 font-semibold">Rp ${formatRupiah(item.amount)}</span>
                    <button onclick="removePemasukan(${index})" class="text-red-600 hover:text-red-800 text-sm">✕</button>
                </div>
            `;
            container.appendChild(div);
        });
    }

    // Add pengeluaran
    function addPengeluaran(description, amount) {
        appData.pengeluaran.push({ description, amount });
        renderPengeluaranList();
        calculateAll();
    }

    // Remove pengeluaran
    function removePengeluaran(index) {
        appData.pengeluaran.splice(index, 1);
        renderPengeluaranList();
        calculateAll();
    }

    // Add pemasukan
    function addPemasukan(description, amount) {
        appData.pemasukan.push({ description, amount });
        renderPemasukanList();
        calculateAll();
    }

    // Remove pemasukan
    function removePemasukan(index) {
        appData.pemasukan.splice(index, 1);
        renderPemasukanList();
        calculateAll();
    }

    // Modal functions
    function showModal(modalId) {
        document.getElementById(modalId).classList.add('show');
    }

    function hideModal(modalId) {
        document.getElementById(modalId).classList.remove('show');
        // Clear form fields
        if (modalId === 'pengeluaranModal') {
            document.getElementById('pengeluaran_desc').value = '';
            document.getElementById('pengeluaran_amount').value = '';
        } else if (modalId === 'pemasukanModal') {
            document.getElementById('pemasukan_desc').value = '';
            document.getElementById('pemasukan_amount').value = '';
        }
    }



    // Generate report text
    function generateReport() {
        const employeeSelect = document.getElementById('employee_id');
        const namaKaryawan = employeeSelect 
            ? employeeSelect.options[employeeSelect.selectedIndex]?.textContent || ''
            : '';
            
        const jamMasuk = document.getElementById('jam_masuk').value || '';
        const jamKeluar = document.getElementById('jam_keluar').value || '';
        
        const nomorAwal = parseFloat(document.getElementById('nomor_awal')?.value.replace(',', '.')) || 0;
        const nomorAkhir = parseFloat(document.getElementById('nomor_akhir')?.value.replace(',', '.')) || 0;
        const qris = parseInt(document.getElementById('qris_value').value) || 0;

        const calc = appData.calculations;
        const jamKerja = (jamMasuk && jamKeluar) ? `(${jamMasuk} - ${jamKeluar})` : '';
        
        let report = "*Setoran Harian* 📋\n";
        report += "<?= getCurrentDate() ?>\n";
        report += `🤦‍♀️ Nama: ${namaKaryawan}\n`;
        report += `🕐 Jam: ${jamKerja}\n\n`;
        
        report += "⛽ Data Meter\n";
        report += `• Nomor Awal : ${nomorAwal.toLocaleString('id-ID', {minimumFractionDigits: 2, maximumFractionDigits: 2})}\n`;
        report += `• Nomor Akhir: ${nomorAkhir.toLocaleString('id-ID', {minimumFractionDigits: 2, maximumFractionDigits: 2})}\n`;
        report += `• Total Liter: ${calc.totalLiter.toLocaleString('id-ID', {minimumFractionDigits: 2, maximumFractionDigits: 2})} L\n\n`;
        
        report += "💰 Setoran\n";
        report += `• Cash  : Rp ${formatRupiah(calc.cash)}\n`;
        report += `• QRIS  : Rp ${formatRupiah(qris)}\n`;
        report += `• Total : Rp ${formatRupiah(calc.total)}\n\n`;
        
        if (appData.pengeluaran.length > 0) {
            report += "💸 Pengeluaran (PU)\n";
            appData.pengeluaran.forEach(item => {
                report += `• ${item.description}: Rp ${formatRupiah(item.amount)}\n`;
            });
            report += `Total Pengeluaran: Rp ${formatRupiah(calc.totalPengeluaran)}\n\n`;
        }
        
        if (appData.pemasukan.length > 0) {
            report += "💵 Pemasukan (PU)\n";
            appData.pemasukan.forEach(item => {
                report += `• ${item.description}: Rp ${formatRupiah(item.amount)}\n`;
            });
            report += `Total Pemasukan: Rp ${formatRupiah(calc.totalPemasukan)}\n\n`;
        }
        
        report += `💼 Total Keseluruhan: Rp ${formatRupiah(calc.totalKeseluruhan)}`;
        
        return report;
    }

    // Copy to clipboard
    function copyToClipboard() {
        const reportText = generateReport();
        
        navigator.clipboard.writeText(reportText).then(() => {
        }).catch(() => {
            // Fallback for older browsers
            const textArea = document.createElement('textarea');
            textArea.value = reportText;
            document.body.appendChild(textArea);
            textArea.select();
            document.execCommand('copy');
            document.body.removeChild(textArea);
        });
    }

    // Save to database
    async function saveToDatabase() {
        if (!isDataValid()) {
            alert('Mohon lengkapi semua data sebelum Salin!');
            return;
        }

        const storeID = document.getElementById('store_id')?.value; 
        const employeeID = document.getElementById('employee_id')?.value;
        
        const jamMasuk = document.getElementById('jam_masuk')?.value;
        const jamKeluar = document.getElementById('jam_keluar')?.value;
        
        const nomorAwal = parseInputNumber(document.getElementById('nomor_awal')?.value);
        const nomorAkhir = parseInputNumber(document.getElementById('nomor_akhir')?.value);
        
        const qris = parseInt(document.getElementById('qris_value').value) || 0;

        const data = {
            store_id: storeID, 
            employee_id: employeeID,
            jam_masuk: jamMasuk,
            jam_keluar: jamKeluar,
            nomor_awal: nomorAwal,
            nomor_akhir: nomorAkhir,
            total_liter: appData.calculations.totalLiter,
            qris: qris,
            cash: appData.calculations.cash,
            total_setoran: appData.calculations.total,
            total_pengeluaran: appData.calculations.totalPengeluaran,
            total_pemasukan: appData.calculations.totalPemasukan,
            total_keseluruhan: appData.calculations.totalKeseluruhan,
            pengeluaran: appData.pengeluaran,
            pemasukan: appData.pemasukan
        };

        try {
            const response = await fetch('api.php?action=save_setoran', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(data)
            });

            const result = await response.json();
            
            if (result.success) {
                alert('Data berhasil disalin ke clipboard!');
            } else {
                alert('Error: ' + result.message);
            }
        } catch (error) {
            alert('Error: Gagal terhubung ke server. ' + error.message);
        }
    }

    // Memuat data Store
    async function fetchAndPopulateStores() {
        const storeSelect = document.getElementById('store_id');
        if (!storeSelect) return; 

        storeSelect.innerHTML = '<option value="" disabled selected>Memuat Store...</option>';
        storeSelect.disabled = true;

        try {
            const response = await fetch(`api.php?action=get_stores`);
            const result = await response.json();
            
            if (result.success && result.data.length > 0) {
                storeSelect.innerHTML = '<option value="" disabled selected>Pilih Store</option>';
                result.data.forEach(store => {
                    const option = document.createElement('option');
                    option.value = store.id; 
                    option.textContent = store.store_name; 
                    storeSelect.appendChild(option);
                });
                storeSelect.disabled = false;
            } else {
                storeSelect.innerHTML = '<option value="" disabled selected>Tidak ada Store</option>';
            }
        } catch (error) {
            console.error('Error fetching stores:', error);
            storeSelect.innerHTML = '<option value="" disabled selected>Gagal memuat Store</option>';
        }
        calculateAll();
    }

    // Memuat data Employee berdasarkan Store yang dipilih
    async function fetchAndPopulateEmployees(store_id) {
        const employeeSelect = document.getElementById('employee_id');
        employeeSelect.innerHTML = '<option value="" disabled selected>Memuat Karyawan...</option>';
        employeeSelect.disabled = true;

        try {
            const response = await fetch(`api.php?action=get_employees&store_id=${store_id}`);
            const result = await response.json();
            
            if (result.success && result.data.length > 0) {
                employeeSelect.innerHTML = '<option value="" disabled selected>Pilih Karyawan</option>';
                result.data.forEach(employee => {
                    const option = document.createElement('option');
                    option.value = employee.id;
                    option.textContent = employee.employee_name;
                    employeeSelect.appendChild(option);
                });
                employeeSelect.disabled = false;
            } else {
                employeeSelect.innerHTML = '<option value="" disabled selected>Tidak ada Karyawan aktif</option>';
            }
        } catch (error) {
            console.error('Error fetching employees:', error);
        }
    }

    // Blok DOMContentLoaded
    document.addEventListener('DOMContentLoaded', function() {
        // Initial Data Load: Memuat Stores
        fetchAndPopulateStores();

        // Setup QRIS input
        setupQrisInput();

        // Listener untuk Dropdown Store
        document.getElementById('store_id')?.addEventListener('change', function() {
            const selectedStoreId = this.value;
            if (selectedStoreId) {
                fetchAndPopulateEmployees(selectedStoreId); 
            } else {
                const employeeSelect = document.getElementById('employee_id');
                if (employeeSelect) {
                    employeeSelect.innerHTML = '<option value="" disabled selected>Pilih Store Dulu</option>';
                    employeeSelect.disabled = true;
                }
            }
            calculateAll();
        });

        // Input event listeners for live calculation
        ['nomor_awal', 'nomor_akhir'].forEach(id => {
            document.getElementById(id)?.addEventListener('input', calculateAll);
        });

        // Jam kerja event listeners
        ['jam_masuk', 'jam_keluar'].forEach(id => {
            document.getElementById(id)?.addEventListener('change', updateJamKerjaDisplay);
            document.getElementById(id)?.addEventListener('change', calculateAll); 
        });

        // Form submissions
        document.getElementById('pengeluaranForm')?.addEventListener('submit', function(e) {
            e.preventDefault();
            const desc = document.getElementById('pengeluaran_desc').value.trim();
            const amount = parseInt(document.getElementById('pengeluaran_amount').value) || 0;
            
            if (desc && amount > 0) {
                addPengeluaran(desc, amount);
                hideModal('pengeluaranModal');
                calculateAll();
            }
        });

        document.getElementById('pemasukanForm')?.addEventListener('submit', function(e) {
            e.preventDefault();
            const desc = document.getElementById('pemasukan_desc').value.trim();
            const amount = parseInt(document.getElementById('pemasukan_amount').value) || 0;
            
            if (desc && amount > 0) {
                addPemasukan(desc, amount);
                hideModal('pemasukanModal');
                calculateAll();
            }
        });

        // Close modal when clicking outside
        document.addEventListener('click', function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.classList.remove('show');
            }
        });

        // Initial calculation
        calculateAll(); 
    });
   