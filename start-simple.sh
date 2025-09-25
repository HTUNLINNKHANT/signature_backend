#!/bin/bash

# Simple startup script for Render deployment
set -e

echo "🚀 Starting Signature E-commerce Backend..."

# Check if vendor directory exists
if [ ! -d "vendor" ]; then
    echo "❌ Vendor directory not found! This should have been created during build."
    exit 1
fi

echo "✅ Vendor directory found"

# Set proper permissions
chmod -R 775 storage bootstrap/cache 2>/dev/null || true

# Generate app key if not set
if [ -z "$APP_KEY" ]; then
    echo "⚠️ APP_KEY not set, generating one..."
    php artisan key:generate --force
    echo "✅ App key generated"
else
    echo "✅ APP_KEY is set"
fi

# Wait a moment for database to be ready
echo "⏳ Waiting for database connection..."
sleep 5

# Try to run migrations with retry logic
echo "🔄 Running database migrations..."
MIGRATION_ATTEMPTS=0
MAX_MIGRATION_ATTEMPTS=3

while [ $MIGRATION_ATTEMPTS -lt $MAX_MIGRATION_ATTEMPTS ]; do
    if php artisan migrate --force; then
        echo "✅ Migrations completed successfully"
        break
    else
        MIGRATION_ATTEMPTS=$((MIGRATION_ATTEMPTS + 1))
        echo "⚠️ Migration attempt $MIGRATION_ATTEMPTS failed"
        if [ $MIGRATION_ATTEMPTS -lt $MAX_MIGRATION_ATTEMPTS ]; then
            echo "Retrying in 5 seconds..."
            sleep 5
        else
            echo "⚠️ All migration attempts failed, but continuing startup..."
            echo "The application will start but database may not be properly initialized"
        fi
    fi
done

# Clear and optimize for production
echo "⚡ Optimizing application..."
php artisan config:clear || true
php artisan route:clear || true
php artisan view:clear || true

# Cache configuration for production
php artisan config:cache || true

echo "🎉 Application ready!"

# Start the Laravel development server
echo "🌐 Starting Laravel server on port ${PORT:-8000}..."
exec php artisan serve --host=0.0.0.0 --port=${PORT:-8000}
