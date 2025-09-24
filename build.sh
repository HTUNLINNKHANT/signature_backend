#!/bin/bash

echo "Starting build process..."

# Install dependencies with fallback approach
echo "Installing Composer dependencies..."
if ! COMPOSER_MEMORY_LIMIT=1 composer install --no-dev --prefer-dist --no-progress --optimize-autoloader; then
    echo "Optimized install failed, trying without optimization..."
    COMPOSER_MEMORY_LIMIT=1 composer install --no-dev --prefer-dist --no-progress
    echo "Generating optimized autoloader separately..."
    COMPOSER_MEMORY_LIMIT=1 composer dump-autoload --optimize
fi

# Generate application key if not exists
if [ -z "$APP_KEY" ]; then
    echo "Generating application key..."
    php artisan key:generate --force
fi

echo "Build completed successfully!"
