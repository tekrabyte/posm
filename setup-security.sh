#!/bin/bash
apt update && apt install -y php php-cli php-mysql php-pdo php-json php-mbstring 2>&1 | tail -20
# =============================================================================
# Admin Panel POSM - Security Setup Script
# =============================================================================

echo "🚀 Starting Admin Panel POSM Security Setup..."
echo ""

# Check if running from correct directory
if [ ! -f "config.php" ]; then
    echo "❌ Error: Please run this script from the application root directory"
    exit 1
fi

# =============================================================================
# 1. Check PHP Installation
# =============================================================================
echo "📋 Step 1: Checking PHP installation..."

if ! command -v php &> /dev/null; then
    echo "❌ PHP is not installed"
    echo "   Please install PHP 7.4 or higher"
    echo ""
    echo "   Ubuntu/Debian:"
    echo "   sudo apt update"
    echo "   sudo apt install php php-mysql php-json php-mbstring"
    echo ""
    echo "   CentOS/RHEL:"
    echo "   sudo yum install php php-mysqlnd php-json php-mbstring"
    exit 1
else
    PHP_VERSION=$(php -v | head -n 1 | cut -d " " -f 2 | cut -d "." -f 1,2)
    echo "✅ PHP $PHP_VERSION found"
fi

# =============================================================================
# 2. Check Required PHP Extensions
# =============================================================================
echo ""
echo "📋 Step 2: Checking PHP extensions..."

REQUIRED_EXTENSIONS=("pdo" "pdo_mysql" "json" "mbstring" "session")
MISSING_EXTENSIONS=()

for ext in "${REQUIRED_EXTENSIONS[@]}"; do
    if php -m | grep -i "^$ext$" > /dev/null; then
        echo "✅ $ext extension found"
    else
        echo "❌ $ext extension missing"
        MISSING_EXTENSIONS+=("$ext")
    fi
done

# Enhanced PDO detection
echo ""
echo "🔍 Detailed PDO check:"
php -r "
if (extension_loaded('pdo')) {
    echo '✅ PDO extension is loaded\n';
    \$drivers = (extension_loaded('pdo_mysql')) ? PDO::getAvailableDrivers() : [];
    echo '📋 PDO drivers: ' . (!empty(\$drivers) ? implode(', ', \$drivers) : 'None');
} else {
    echo '❌ PDO extension is NOT loaded';
}
" 2>/dev/null || echo "❌ PDO check failed"

if [ ${#MISSING_EXTENSIONS[@]} -gt 0 ]; then
    echo ""
    echo "❌ Missing extensions: ${MISSING_EXTENSIONS[*]}"
    echo ""
    
    # Provide specific installation commands
    if [[ " ${MISSING_EXTENSIONS[@]} " =~ " pdo " ]]; then
        echo "💡 Solution for PDO extension:"
        if command -v apt &> /dev/null; then
            echo "   Ubuntu/Debian: sudo apt install php${PHP_VERSION}-pdo php${PHP_VERSION}-mysql"
        elif command -v yum &> /dev/null; then
            echo "   CentOS/RHEL: sudo yum install php-pdo php-mysqlnd"
        elif command -v dnf &> /dev/null; then
            echo "   Fedora: sudo dnf install php-pdo php-mysqlnd"
        else
            echo "   Please install php-pdo and php-mysql packages for your system"
        fi
    fi
    
    # General installation guide
    echo ""
    echo "📚 Installation Guide:"
    echo "   1. Install missing extensions using commands above"
    echo "   2. Restart your web server:"
    echo "      - Apache: sudo systemctl restart apache2"
    echo "      - Nginx + PHP-FPM: sudo systemctl restart php${PHP_VERSION}-fpm"
    echo "   3. Run this script again"
    exit 1
fi

# =============================================================================
# 3. Check File Permissions
# =============================================================================
echo ""
echo "📋 Step 3: Checking file permissions..."

# Check if files exist
REQUIRED_FILES=(
    "security.php"
    "config.php"
    "api.php"
    "admin.php"
    "login.php"
    "assets/js/security.js"
    "assets/js/admin-integration.js"
    "assets/css/admin.css"
)

for file in "${REQUIRED_FILES[@]}"; do
    if [ -f "$file" ]; then
        echo "✅ $file exists"
    else
        echo "❌ $file not found"
        exit 1
    fi
done

# Set proper permissions
chmod 644 *.php 2>/dev/null
chmod 644 assets/js/*.js 2>/dev/null
chmod 644 assets/css/*.css 2>/dev/null

echo "✅ File permissions set"

# =============================================================================
# 4. Check Database Connection
# =============================================================================
echo ""
echo "📋 Step 4: Checking database connection..."

# Test database connection with better error handling
DB_TEST=$(php -r '
    @session_start();
    require_once "config.php";
    try {
        // Test basic PDO connection
        $test = $pdo->query("SELECT 1")->fetchColumn();
        if ($test == "1") {
            echo "OK";
        } else {
            echo "ERROR: Query test failed";
        }
    } catch (PDOException $e) {
        echo "PDO ERROR: " . $e->getMessage();
    } catch (Exception $e) {
        echo "ERROR: " . $e->getMessage();
    }
' 2>&1)

if [[ $DB_TEST == "OK" ]]; then
    echo "✅ Database connection successful"
else
    echo "❌ Database connection failed:"
    echo "   $DB_TEST"
    echo ""
    echo "   Please check:"
    echo "   1. Database credentials in config.php"
    echo "   2. Database server is running"
    echo "   3. Database user has proper permissions"
    exit 1
fi

# =============================================================================
# 5. Verify Security Features
# =============================================================================
echo ""
echo "📋 Step 5: Verifying security features..."

# Check if security.php has required functions
SECURITY_FUNCTIONS=("generateCSRFToken" "validateCSRFToken" "checkSessionTimeout" "sanitizeInput")
SECURITY_OK=true

for func in "${SECURITY_FUNCTIONS[@]}"; do
    if grep -q "function $func" security.php; then
        echo "✅ $func() found"
    else
        echo "❌ $func() not found in security.php"
        SECURITY_OK=false
    fi
done

if [ "$SECURITY_OK" = false ]; then
    echo "❌ Security functions missing"
    exit 1
fi

# =============================================================================
# 6. Check Session Configuration
# =============================================================================
echo ""
echo "📋 Step 6: Checking session configuration..."

# Check if session settings are in config.php
if grep -q "session.gc_maxlifetime" config.php || grep -q "session_set_cookie_params" config.php; then
    echo "✅ Session timeout configured"
else
    echo "⚠️  Session timeout not configured in config.php"
fi

if grep -q "session.cookie_httponly" config.php || grep -q "httponly.*true" config.php; then
    echo "✅ HTTPOnly cookies enabled"
else
    echo "⚠️  HTTPOnly cookies not explicitly configured"
fi

# =============================================================================
# 7. Test CSRF Token Generation
# =============================================================================
echo ""
echo "📋 Step 7: Testing CSRF token generation..."

CSRF_TEST=$(php -r '
    @session_start();
    require_once "security.php";
    try {
        $token = generateCSRFToken();
        if (strlen($token) >= 32 && validateCSRFToken($token)) {
            echo "OK";
        } else {
            echo "ERROR: Token validation failed";
        }
    } catch (Exception $e) {
        echo "ERROR: " . $e->getMessage();
    }
' 2>&1)

if [[ $CSRF_TEST == "OK" ]]; then
    echo "✅ CSRF token generation and validation working"
else
    echo "❌ CSRF system test failed: $CSRF_TEST"
    exit 1
fi

# =============================================================================
# 8. Create Test User (Optional)
# =============================================================================
echo ""
echo "📋 Step 8: Checking for test user..."

TEST_USER=$(php -r '
    require_once "config.php";
    try {
        $stmt = $pdo->query("SELECT COUNT(*) FROM users");
        $count = $stmt->fetchColumn();
        echo $count;
    } catch (Exception $e) {
        echo "ERROR: " . $e->getMessage();
    }
')

if [[ $TEST_USER == "ERROR"* ]]; then
    echo "⚠️  Could not check users table: $TEST_USER"
    echo "   Make sure to run database migration: u215947863_pom.sql"
elif [[ $TEST_USER == "0" ]]; then
    echo "⚠️  No users found in database"
    echo "   Please create an admin user in the database"
else
    echo "✅ Found $TEST_USER user(s) in database"
fi

# =============================================================================
# 9. Security Checklist
# =============================================================================
echo ""
echo "📋 Step 9: Security Checklist..."
echo ""
echo "Please verify the following manually:"
echo ""
echo "  [ ] HTTPS enabled (required for production)"
echo "  [ ] Database credentials secure"
echo "  [ ] Error reporting disabled in production"
echo "  [ ] File upload directory protected (if applicable)"
echo "  [ ] Backup strategy in place"
echo "  [ ] Admin user password is strong"
echo ""

# =============================================================================
# 10. Final Summary
# =============================================================================
echo "═══════════════════════════════════════════════════════════"
echo "🎉 SETUP COMPLETE!"
echo "═══════════════════════════════════════════════════════════"
echo ""
echo "✅ Security Features Installed:"
echo "   • CSRF Protection"
echo "   • Session Timeout (30 minutes)"
echo "   • Enhanced Error Handling"
echo "   • Input Validation Framework"
echo "   • Toast Notifications"
echo "   • Loading Overlays"
echo "   • Confirmation Dialogs"
echo "   • Mobile Responsive Tables"
echo ""
echo "📚 Documentation:"
echo "   • SECURITY_IMPLEMENTATION_REPORT.md - Complete feature list"
echo "   • INTEGRATION_GUIDE.md - How to use new features"
echo ""
echo "🚀 Next Steps:"
echo "   1. Access login page: http://your-domain/login.php"
echo "   2. Login with admin credentials"
echo "   3. Test all CRUD operations"
echo "   4. Review browser console for any errors"
echo "   5. Test on mobile devices"
echo ""
echo "⚠️  Production Deployment:"
echo "   • Set display_errors = 0 in PHP config"
echo "   • Enable HTTPS (Let's Encrypt recommended)"
echo "   • Set session.cookie_secure = 1 for HTTPS"
echo "   • Regular database backups"
echo "   • Monitor error logs"
echo ""
echo "📞 Support:"
echo "   Check documentation files for detailed information"
echo "   Review browser DevTools console for debugging"
echo ""
echo "═══════════════════════════════════════════════════════════"

exit 0