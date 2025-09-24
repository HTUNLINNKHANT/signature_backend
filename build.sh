#!/bin/bash

echo "Starting build process..."

# Install dependencies with fallback approach
echo "Installing Composer dependencies..."

# Check PHP version
echo "PHP Version: $(php --version | head -n 1)"

# Check for PHP version compatibility with composer.lock
if [ -f "composer.lock" ]; then
    # Try a quick composer check to see if lock file is compatible
    if ! composer check-platform-reqs --no-dev > /dev/null 2>&1; then
        echo "⚠️ Composer lock file may be incompatible with current PHP version"
        echo "Running automatic fix..."
        ./fix-composer-lock.sh
        echo "Lock file regenerated, continuing with build..."
    fi
fi

# Try with different memory limits and fallback strategies
MEMORY_LIMITS=("512M" "1G" "2G" "-1")
INSTALL_SUCCESS=false

for MEMORY_LIMIT in "${MEMORY_LIMITS[@]}"; do
    echo "Trying with memory limit: $MEMORY_LIMIT"
    
    # First try: normal install with lock file
    if COMPOSER_MEMORY_LIMIT=$MEMORY_LIMIT composer install --no-dev --prefer-dist --no-progress --optimize-autoloader; then
        echo "✅ Composer install successful with memory limit: $MEMORY_LIMIT"
        INSTALL_SUCCESS=true
        break
    else
        echo "❌ Failed with memory limit: $MEMORY_LIMIT"
        
        # Second try: install without optimization
        echo "Trying without optimization..."
        if COMPOSER_MEMORY_LIMIT=$MEMORY_LIMIT composer install --no-dev --prefer-dist --no-progress; then
            echo "Generating optimized autoloader separately..."
            COMPOSER_MEMORY_LIMIT=$MEMORY_LIMIT composer dump-autoload --optimize
            echo "✅ Composer install successful without initial optimization"
            INSTALL_SUCCESS=true
            break
        fi
        
        # Third try: update composer.lock if it's the last memory limit
        if [ "$MEMORY_LIMIT" = "-1" ]; then
            echo "Lock file may be incompatible. Trying composer update..."
            if COMPOSER_MEMORY_LIMIT=-1 composer update --no-dev --prefer-dist --no-progress --with-all-dependencies; then
                echo "✅ Composer update successful - lock file updated"
                INSTALL_SUCCESS=true
                break
            fi
            
            # Fourth try: install ignoring platform requirements (last resort)
            echo "Final attempt: ignoring platform requirements..."
            if COMPOSER_MEMORY_LIMIT=-1 composer install --no-dev --prefer-dist --no-progress --ignore-platform-reqs; then
                echo "⚠️ Composer install successful but ignoring platform requirements"
                echo "This may cause runtime issues - consider updating your dependencies"
                INSTALL_SUCCESS=true
                break
            fi
        fi
    fi
done

if [ "$INSTALL_SUCCESS" = false ]; then
    echo "❌ All Composer install attempts failed!"
    echo "Please check:"
    echo "1. PHP version compatibility with composer.lock"
    echo "2. Available memory"
    echo "3. Network connectivity"
    echo "4. Composer.json syntax"
    exit 1
fi

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
