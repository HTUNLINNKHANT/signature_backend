#!/bin/bash

# Minimal startup script for Render deployment
# Focus on getting the basic application running quickly

set -e

echo "🚀 Starting Signature E-commerce Backend (Minimal Mode)..."

# Check if vendor directory exists
if [ ! -d "vendor" ]; then
    echo "❌ Vendor directory not found! This should have been created during build."
    exit 1
fi

echo "✅ Vendor directory found"

# Set proper permissions (ignore errors)
chmod -R 775 storage bootstrap/cache 2>/dev/null || true

# Generate app key if not set
if [ -z "$APP_KEY" ]; then
    echo "⚠️ APP_KEY not set, generating one..."
    php artisan key:generate --force
    echo "✅ App key generated"
else
    echo "✅ APP_KEY is set"
fi

# Clear any cached config that might cause issues
echo "🧹 Clearing caches..."
php artisan config:clear || true
php artisan route:clear || true
php artisan view:clear || true

# Try to run migrations in background (non-blocking)
echo "🔄 Attempting database migrations (non-blocking)..."
(
    sleep 10  # Wait for database to be ready
    if php artisan migrate --force 2>/dev/null; then
        echo "✅ Migrations completed successfully"
    else
        echo "⚠️ Migrations failed, but continuing startup..."
        # Try to fix sessions table if needed
        if [ -f "./fix-sessions-table.sh" ]; then
            echo "🔧 Attempting sessions table fix..."
            ./fix-sessions-table.sh || true
        fi
    fi
) &

echo "🎉 Application starting!"

# Start the Laravel development server immediately
echo "🌐 Starting Laravel server on port ${PORT:-8000}..."
exec php artisan serve --host=0.0.0.0 --port=${PORT:-8000}
