#!/bin/bash

# Debug script for Composer and vendor directory issues
# Helps diagnose "Class not found" errors

set -e

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

echo -e "${BLUE}🔍 Composer & Vendor Debug Information${NC}"
echo "=================================================="

# Check PHP version
echo -e "${YELLOW}PHP Version:${NC}"
php --version
echo ""

# Check Composer version
echo -e "${YELLOW}Composer Version:${NC}"
composer --version
echo ""

# Check memory limits
echo -e "${YELLOW}Memory Configuration:${NC}"
echo "PHP memory_limit: $(php -r 'echo ini_get("memory_limit");')"
echo "COMPOSER_MEMORY_LIMIT: ${COMPOSER_MEMORY_LIMIT:-'not set'}"
echo ""

# Check current directory and permissions
echo -e "${YELLOW}Current Directory:${NC}"
pwd
ls -la
echo ""

# Check vendor directory
echo -e "${YELLOW}Vendor Directory Status:${NC}"
if [ -d "vendor" ]; then
    echo -e "${GREEN}✅ vendor/ directory exists${NC}"
    echo "Size: $(du -sh vendor/ 2>/dev/null || echo 'unknown')"
    
    if [ -f "vendor/autoload.php" ]; then
        echo -e "${GREEN}✅ vendor/autoload.php exists${NC}"
    else
        echo -e "${RED}❌ vendor/autoload.php missing${NC}"
    fi
    
    if [ -d "vendor/laravel" ]; then
        echo -e "${GREEN}✅ Laravel framework found in vendor${NC}"
    else
        echo -e "${RED}❌ Laravel framework missing from vendor${NC}"
    fi
    
    if [ -d "vendor/illuminate" ]; then
        echo -e "${GREEN}✅ Illuminate components found${NC}"
    else
        echo -e "${RED}❌ Illuminate components missing${NC}"
    fi
else
    echo -e "${RED}❌ vendor/ directory does not exist${NC}"
fi
echo ""

# Check composer files
echo -e "${YELLOW}Composer Files:${NC}"
if [ -f "composer.json" ]; then
    echo -e "${GREEN}✅ composer.json exists${NC}"
else
    echo -e "${RED}❌ composer.json missing${NC}"
fi

if [ -f "composer.lock" ]; then
    echo -e "${GREEN}✅ composer.lock exists${NC}"
else
    echo -e "${YELLOW}⚠️ composer.lock missing (not critical)${NC}"
fi
echo ""

# Check Laravel bootstrap
echo -e "${YELLOW}Laravel Bootstrap:${NC}"
if [ -f "bootstrap/app.php" ]; then
    echo -e "${GREEN}✅ bootstrap/app.php exists${NC}"
    echo "First few lines:"
    head -10 bootstrap/app.php
else
    echo -e "${RED}❌ bootstrap/app.php missing${NC}"
fi
echo ""

# Test autoloader
echo -e "${YELLOW}Autoloader Test:${NC}"
if [ -f "vendor/autoload.php" ]; then
    if php -r "require 'vendor/autoload.php'; echo 'Autoloader works!';"; then
        echo -e "${GREEN}✅ Autoloader loads successfully${NC}"
    else
        echo -e "${RED}❌ Autoloader has errors${NC}"
    fi
else
    echo -e "${RED}❌ Cannot test autoloader - vendor/autoload.php missing${NC}"
fi
echo ""

# Test Laravel Application class
echo -e "${YELLOW}Laravel Application Class Test:${NC}"
if php -r "
require_once 'vendor/autoload.php';
try {
    \$app = new Illuminate\Foundation\Application(realpath(__DIR__));
    echo 'Laravel Application class loads successfully!';
} catch (Exception \$e) {
    echo 'Error: ' . \$e->getMessage();
    exit(1);
}
" 2>/dev/null; then
    echo -e "${GREEN}✅ Laravel Application class works${NC}"
else
    echo -e "${RED}❌ Laravel Application class failed to load${NC}"
fi
echo ""

# Suggest fixes
echo -e "${YELLOW}💡 Suggested Fixes:${NC}"
echo "1. If vendor directory is missing:"
echo "   composer install --no-dev --prefer-dist"
echo ""
echo "2. If autoloader is corrupted:"
echo "   composer dump-autoload --optimize"
echo ""
echo "3. If memory issues persist:"
echo "   COMPOSER_MEMORY_LIMIT=1G composer install"
echo ""
echo "4. If all else fails:"
echo "   rm -rf vendor/ composer.lock"
echo "   composer install --no-dev --prefer-dist"
echo ""

echo "=================================================="
echo -e "${BLUE}Debug information complete${NC}"
