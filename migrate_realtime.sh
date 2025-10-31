#!/bin/bash
# Migration script for real-time and email features

echo "🚀 Running database migration for real-time & email features..."

# Connect to database and run SQL
php -r "
require_once '/app/config/config.php';

\$sql = file_get_contents('/app/database_realtime_email.sql');

try {
    // Split by semicolon and execute each statement
    \$statements = array_filter(array_map('trim', explode(';', \$sql)));
    
    foreach (\$statements as \$statement) {
        if (!empty(\$statement)) {
            \$pdo->exec(\$statement);
            echo \"✅ Executed statement\\n\";
        }
    }
    
    echo \"\\n🎉 Migration completed successfully!\\n\";
    
} catch (PDOException \$e) {
    echo \"❌ Migration failed: \" . \$e->getMessage() . \"\\n\";
    exit(1);
}
"

echo "✅ Migration script completed!"
