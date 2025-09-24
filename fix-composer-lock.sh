#!/bin/bash

# Script to fix composer.lock compatibility issues
# This script regenerates the lock file with the current PHP version

set -e

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

echo -e "${BLUE}🔧 Fixing Composer Lock File Compatibility${NC}"
echo "=================================================="

# Check current PHP version
PHP_VERSION=$(php --version | head -n 1)
echo -e "${YELLOW}Current PHP Version:${NC} $PHP_VERSION"

# Check if composer.json exists
if [ ! -f "composer.json" ]; then
    echo -e "${RED}❌ composer.json not found!${NC}"
    exit 1
fi

# Backup existing lock file if it exists
if [ -f "composer.lock" ]; then
    echo -e "${YELLOW}📦 Backing up existing composer.lock...${NC}"
    cp composer.lock composer.lock.backup
    echo "✅ Backup created: composer.lock.backup"
fi

# Remove vendor directory to ensure clean install
if [ -d "vendor" ]; then
    echo -e "${YELLOW}🗑️ Removing existing vendor directory...${NC}"
    rm -rf vendor/
fi

# Remove existing lock file
if [ -f "composer.lock" ]; then
    echo -e "${YELLOW}🗑️ Removing incompatible composer.lock...${NC}"
    rm composer.lock
fi

echo ""
echo -e "${BLUE}🔄 Regenerating composer.lock with current PHP version...${NC}"

# Try different memory limits for composer update
MEMORY_LIMITS=("512M" "1G" "2G" "-1")
UPDATE_SUCCESS=false

for MEMORY_LIMIT in "${MEMORY_LIMITS[@]}"; do
    echo "Trying composer update with memory limit: $MEMORY_LIMIT"
    
    if COMPOSER_MEMORY_LIMIT=$MEMORY_LIMIT composer update --no-dev --prefer-dist --no-progress; then
        echo -e "${GREEN}✅ Composer update successful with memory limit: $MEMORY_LIMIT${NC}"
        UPDATE_SUCCESS=true
        break
    else
        echo -e "${RED}❌ Failed with memory limit: $MEMORY_LIMIT${NC}"
    fi
done

if [ "$UPDATE_SUCCESS" = false ]; then
    echo -e "${RED}❌ Failed to regenerate composer.lock${NC}"
    
    # Restore backup if it exists
    if [ -f "composer.lock.backup" ]; then
        echo "Restoring backup..."
        mv composer.lock.backup composer.lock
    fi
    exit 1
fi

echo ""
echo -e "${GREEN}🎉 Composer lock file successfully regenerated!${NC}"

# Verify the installation
echo -e "${BLUE}🔍 Verifying installation...${NC}"
if [ -d "vendor" ] && [ -f "vendor/autoload.php" ]; then
    echo -e "${GREEN}✅ Vendor directory and autoloader created successfully${NC}"
else
    echo -e "${RED}❌ Vendor directory or autoloader missing${NC}"
    exit 1
fi

# Show some statistics
if [ -f "composer.lock" ]; then
    PACKAGE_COUNT=$(grep -c '"name":' composer.lock || echo "unknown")
    echo "📊 Packages installed: $PACKAGE_COUNT"
fi

echo ""
echo -e "${YELLOW}💡 Next steps:${NC}"
echo "1. Test your application to ensure everything works"
echo "2. Commit the new composer.lock file to your repository"
echo "3. Update your deployment to use PHP 8.3"

echo ""
echo "=================================================="
echo -e "${GREEN}✅ Composer lock file fix completed!${NC}"
