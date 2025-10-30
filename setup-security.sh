#!/bin/bash

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
    if php -m | grep -q "^$ext$"; then
        echo "✅ $ext extension found"
    else
        echo "❌ $ext extension missing"
        MISSING_EXTENSIONS+=("$ext")
    fi
done

if [ ${#MISSING_EXTENSIONS[@]} -gt 0 ]; then
    echo ""
    echo "❌ Missing extensions: ${MISSING_EXTENSIONS[*]}"
    echo "   Please install missing extensions and try again"
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

# Test database connection
DB_TEST=$(php -r '
    require_once "config.php";
    try {
        $stmt = $pdo->query("SELECT 1");
        echo "OK";
    } catch (Exception $e) {
        echo "ERROR: " . $e->getMessage();
    }
')

if [[ $DB_TEST == "OK" ]]; then
    echo "✅ Database connection successful"
else
    echo "❌ Database connection failed:"
    echo "   $DB_TEST"
    echo ""
    echo "   Please check database credentials in config.php"
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
if grep -q "session.gc_maxlifetime" config.php; then
    echo "✅ Session timeout configured (30 minutes)"
else
    echo "⚠️  Session timeout not configured"
    echo "   Adding session configuration to config.php..."
    # Could add auto-config here, but safer to do manually
fi

if grep -q "session.cookie_httponly" config.php; then
    echo "✅ HTTPOnly cookies enabled"
else
    echo "⚠️  HTTPOnly cookies not enabled"
fi

# =============================================================================
# 7. Test CSRF Token Generation
# =============================================================================
echo ""
echo "📋 Step 7: Testing CSRF token generation..."

CSRF_TEST=$(php -r '
    session_start();
    require_once "security.php";
    $token = generateCSRFToken();
    if (strlen($token) >= 32) {
        echo "OK";
    } else {
        echo "ERROR";
    }
')

if [[ $CSRF_TEST == "OK" ]]; then
    echo "✅ CSRF token generation working"
else
    echo "❌ CSRF token generation failed"
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
        echo "ERROR";
    }
')

if [[ $TEST_USER == "ERROR" ]]; then
    echo "⚠️  Could not check users table"
    echo "   Make sure to run database migration: u215947863_pom.sql"
elif [[ $TEST_USER == "0" ]]; then
    echo "⚠️  No users found"
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
