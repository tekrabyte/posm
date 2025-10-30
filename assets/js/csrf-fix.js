/**
 * CSRF Token Fix untuk semua POST requests
 * File ini menambahkan CSRF token secara otomatis ke semua fetch POST requests
 */

// Override fetch global untuk menambahkan CSRF token otomatis
(function() {
    const originalFetch = window.fetch;
    
    window.fetch = function(...args) {
        let [url, options] = args;
        
        // Jika ini adalah POST request dan menggunakan JSON
        if (options && options.method === 'POST') {
            try {
                // Jika body adalah JSON string
                if (options.headers && options.headers['Content-Type'] === 'application/json' && options.body) {
                    const data = JSON.parse(options.body);
                    
                    // Tambahkan CSRF token jika belum ada
                    if (!data.csrf_token && typeof CSRF_TOKEN !== 'undefined') {
                        data.csrf_token = CSRF_TOKEN;
                        options.body = JSON.stringify(data);
                        console.log('🔒 CSRF token added automatically to:', url);
                    }
                }
            } catch (e) {
                // Jika parsing gagal, lanjutkan tanpa modifikasi
                console.warn('Could not parse JSON body for CSRF injection', e);
            }
        }
        
        return originalFetch.apply(this, [url, options]);
    };
    
    console.log('✅ CSRF auto-injection enabled');
})();
