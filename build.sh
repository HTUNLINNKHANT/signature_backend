#!/bin/bash

echo "Starting build process..."

# Install dependencies with fallback approach
echo "Installing Composer dependencies..."

# Try with different memory limits, starting conservative
MEMORY_LIMITS=("512M" "1G" "2G" "-1")

for MEMORY_LIMIT in "${MEMORY_LIMITS[@]}"; do
    echo "Trying with memory limit: $MEMORY_LIMIT"
    
    if COMPOSER_MEMORY_LIMIT=$MEMORY_LIMIT composer install --no-dev --prefer-dist --no-progress --optimize-autoloader; then
        echo "✅ Composer install successful with memory limit: $MEMORY_LIMIT"
        break
    else
        echo "❌ Failed with memory limit: $MEMORY_LIMIT, trying next..."
        
        # If this is the last attempt, try without optimization
        if [ "$MEMORY_LIMIT" = "-1" ]; then
            echo "Final attempt: installing without optimization..."
            if COMPOSER_MEMORY_LIMIT=-1 composer install --no-dev --prefer-dist --no-progress; then
                echo "Generating optimized autoloader separately..."
                COMPOSER_MEMORY_LIMIT=-1 composer dump-autoload --optimize
                echo "✅ Composer install successful without initial optimization"
                break
            else
                echo "❌ All Composer install attempts failed!"
                exit 1
            fi
        fi
    fi
done

# Verify vendor directory exists
if [ ! -d "vendor" ] || [ ! -f "vendor/autoload.php" ]; then
    echo "❌ Vendor directory or autoloader not found after installation!"
    echo "Contents of current directory:"
    ls -la
    exit 1
fi

echo "✅ Vendor dependencies verified successfully"

# Generate application key if not exists
if [ -z "$APP_KEY" ]; then
    echo "Generating application key..."
    php artisan key:generate --force
fi

echo "Build completed successfully!"
