#!/bin/bash

echo "Starting application..."

# Run database migrations
echo "Running database migrations..."
php artisan migrate --force

# Seed database (only if needed)
echo "Seeding database..."
php artisan db:seed --force --class=DatabaseSeeder

# Start the Laravel server
echo "Starting Laravel server on port $PORT..."
php artisan serve --host=0.0.0.0 --port=$PORT
