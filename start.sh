#!/bin/bash

echo "Starting production deployment..."

# Wait for database to be ready
echo "Waiting for database connection..."
until php artisan migrate:status > /dev/null 2>&1; do
    echo "Waiting for database..."
    sleep 2
done

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
php artisan migrate --force

# Check if database is empty and seed if needed
TABLES_COUNT=$(php artisan tinker --execute="echo \DB::table('users')->count();")
if [ "$TABLES_COUNT" -eq "0" ]; then
    echo "Database is empty, seeding initial data..."
    php artisan db:seed --force --class=DatabaseSeeder
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

# Start the Laravel server
exec php artisan serve --host=0.0.0.0 --port=$PORT
