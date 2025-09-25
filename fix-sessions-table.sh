#!/bin/bash

# Fix sessions table PostgreSQL error
echo "🔧 Fixing sessions table structure..."

# Check if we can connect to database
if ! php artisan migrate:status > /dev/null 2>&1; then
    echo "❌ Cannot connect to database. Please check database configuration."
    exit 1
fi

echo "✅ Database connection successful"

# Drop and recreate sessions table
echo "🗑️ Dropping existing sessions table..."
php artisan tinker --execute="
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

try {
    if (Schema::hasTable('sessions')) {
        Schema::drop('sessions');
        echo 'Sessions table dropped successfully\n';
    } else {
        echo 'Sessions table does not exist\n';
    }
} catch (Exception \$e) {
    echo 'Error dropping sessions table: ' . \$e->getMessage() . '\n';
}
"

# Re-run the specific migration
echo "🔄 Re-creating sessions table with correct structure..."
if php artisan migrate --path=database/migrations/2024_01_01_000003_create_sessions_table.php --force; then
    echo "✅ Sessions table recreated successfully"
else
    echo "❌ Failed to recreate sessions table"
    exit 1
fi

# Verify the table structure
echo "🔍 Verifying table structure..."
php artisan tinker --execute="
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

try {
    \$columns = DB::select('SELECT column_name, data_type, is_nullable FROM information_schema.columns WHERE table_name = ? ORDER BY ordinal_position', ['sessions']);
    
    echo 'Sessions table structure:\n';
    foreach (\$columns as \$column) {
        echo sprintf('- %s: %s (%s)\n', \$column->column_name, \$column->data_type, \$column->is_nullable === 'YES' ? 'nullable' : 'not null');
    }
} catch (Exception \$e) {
    echo 'Error checking table structure: ' . \$e->getMessage() . '\n';
}
"

echo "🎉 Sessions table fix completed!"
echo ""
echo "The sessions table should now have the correct structure:"
echo "- id: varchar (primary key)"
echo "- user_id: bigint (nullable)"
echo "- ip_address: varchar (nullable)"
echo "- user_agent: text (nullable)"
echo "- payload: text"
echo "- last_activity: integer"
