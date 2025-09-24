#!/bin/bash

echo "Starting build process..."

# Install dependencies with fallback approach
echo "Installing Composer dependencies..."
if ! composer install --no-dev --prefer-dist --no-progress --optimize-autoloader; then
    echo "Optimized install failed, trying without optimization..."
    composer install --no-dev --prefer-dist --no-progress
    echo "Generating optimized autoloader separately..."
    composer dump-autoload --optimize
fi

# Generate application key if not exists
if [ -z "$APP_KEY" ]; then
    echo "Generating application key..."
    php artisan key:generate --force
fi

echo "Build completed successfully!"
