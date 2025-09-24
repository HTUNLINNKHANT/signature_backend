#!/bin/bash

# Enable error handling
set -e

# Trap errors and run debug script
trap 'echo "Error occurred. Running debug script..."; ./debug-deployment.sh' ERR

echo "Starting production deployment..."

# Verify vendor dependencies are installed
echo "Checking vendor dependencies..."
if [ ! -d "vendor" ] || [ ! -f "vendor/autoload.php" ]; then
    echo "❌ Vendor directory missing! Running composer install..."
    
    # Try to install dependencies with fallback memory limits
    MEMORY_LIMITS=("512M" "1G" "2G" "-1")
    
    for MEMORY_LIMIT in "${MEMORY_LIMITS[@]}"; do
        echo "Trying composer install with memory limit: $MEMORY_LIMIT"
        if COMPOSER_MEMORY_LIMIT=$MEMORY_LIMIT composer install --no-dev --prefer-dist --no-progress --optimize-autoloader; then
            echo "✅ Composer install successful"
            break
        elif [ "$MEMORY_LIMIT" = "-1" ]; then
            echo "❌ All composer install attempts failed!"
            exit 1
        fi
    done
else
    echo "✅ Vendor dependencies found"
fi

# Wait for database to be ready using enhanced script
./wait-for-db.sh

# Generate application key if not exists
if [ -z "$APP_KEY" ]; then
    echo "Generating application key..."
    php artisan key:generate --force
fi

# Clear and cache configuration for production
echo "Optimizing application for production..."
php artisan config:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Run database migrations
echo "Running database migrations..."
if ! php artisan migrate --force; then
    echo "❌ Migration failed! Running debug script..."
    ./debug-deployment.sh
    exit 1
fi
echo "✅ Migrations completed successfully"

# Check if database is empty and seed if needed
echo "Checking if database needs seeding..."
USER_COUNT=$(php artisan tinker --execute="try { echo \App\Models\User::count(); } catch (Exception \$e) { echo '0'; }" 2>/dev/null || echo "0")
echo "User count: $USER_COUNT"

if [ "$USER_COUNT" = "0" ] || [ -z "$USER_COUNT" ]; then
    echo "Database is empty or tables don't exist, seeding initial data..."
    php artisan db:seed --force --class=DatabaseSeeder || echo "Warning: Seeding failed, but continuing..."
else
    echo "Database already contains data, skipping seeding..."
fi

# Create storage link if it doesn't exist
if [ ! -L "public/storage" ]; then
    echo "Creating storage link..."
    php artisan storage:link
fi

# Set proper permissions
chmod -R 775 storage bootstrap/cache

echo "Application setup completed successfully!"
echo "Starting Laravel server on port $PORT..."

# Final check and start the Laravel server
echo "Final application status check..."
php artisan about || echo "Warning: Application status check failed"

echo "Starting Laravel server on port ${PORT:-8000}..."
# Start the Laravel server
exec php artisan serve --host=0.0.0.0 --port=${PORT:-8000}
