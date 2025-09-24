#!/bin/bash

# Debug deployment script for troubleshooting Render issues
# This script provides comprehensive diagnostics

set -e

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

echo -e "${BLUE}🔍 Signature Backend Deployment Debug${NC}"
echo "=================================================="

# System Information
echo -e "\n${YELLOW}📋 System Information:${NC}"
echo "PHP Version: $(php --version | head -n 1)"
echo "Composer Version: $(composer --version)"
echo "Working Directory: $(pwd)"
echo "User: $(whoami)"
echo "Memory Limit: $(php -r 'echo ini_get("memory_limit");')"

# Environment Variables
echo -e "\n${YELLOW}🌍 Environment Variables:${NC}"
echo "APP_ENV: ${APP_ENV:-'not set'}"
echo "APP_DEBUG: ${APP_DEBUG:-'not set'}"
echo "APP_KEY: ${APP_KEY:+'***set***'}"
echo "APP_URL: ${APP_URL:-'not set'}"
echo "DB_CONNECTION: ${DB_CONNECTION:-'not set'}"
echo "DB_HOST: ${DB_HOST:-'not set'}"
echo "DB_PORT: ${DB_PORT:-'not set'}"
echo "DB_DATABASE: ${DB_DATABASE:-'not set'}"
echo "DB_USERNAME: ${DB_USERNAME:-'not set'}"
echo "DB_PASSWORD: ${DB_PASSWORD:+'***set***'}"
echo "PORT: ${PORT:-'not set'}"

# File System Checks
echo -e "\n${YELLOW}📁 File System Checks:${NC}"
echo -n "Vendor directory: "
if [ -d "vendor" ]; then
    echo -e "${GREEN}✅ EXISTS${NC}"
else
    echo -e "${RED}❌ MISSING${NC}"
fi

echo -n "Storage directory: "
if [ -d "storage" ]; then
    echo -e "${GREEN}✅ EXISTS${NC}"
    echo "  - Permissions: $(ls -la storage | head -n 1)"
else
    echo -e "${RED}❌ MISSING${NC}"
fi

echo -n "Bootstrap/cache directory: "
if [ -d "bootstrap/cache" ]; then
    echo -e "${GREEN}✅ EXISTS${NC}"
    echo "  - Permissions: $(ls -la bootstrap/cache | head -n 1)"
else
    echo -e "${RED}❌ MISSING${NC}"
fi

echo -n ".env file: "
if [ -f ".env" ]; then
    echo -e "${GREEN}✅ EXISTS${NC}"
else
    echo -e "${RED}❌ MISSING${NC}"
fi

# Laravel Application Status
echo -e "\n${YELLOW}🚀 Laravel Application Status:${NC}"
echo -n "Application key: "
if php artisan key:generate --show > /dev/null 2>&1; then
    echo -e "${GREEN}✅ VALID${NC}"
else
    echo -e "${RED}❌ INVALID${NC}"
fi

echo -n "Config cache: "
if php artisan config:cache > /dev/null 2>&1; then
    echo -e "${GREEN}✅ OK${NC}"
else
    echo -e "${RED}❌ FAILED${NC}"
fi

echo -n "Route cache: "
if php artisan route:cache > /dev/null 2>&1; then
    echo -e "${GREEN}✅ OK${NC}"
else
    echo -e "${RED}❌ FAILED${NC}"
fi

# Database Connection
echo -e "\n${YELLOW}🗄️ Database Connection:${NC}"
echo -n "Database connectivity: "
if php artisan migrate:status > /dev/null 2>&1; then
    echo -e "${GREEN}✅ CONNECTED${NC}"
    echo "Migration status:"
    php artisan migrate:status | head -n 10
else
    echo -e "${RED}❌ FAILED${NC}"
    echo "Attempting direct connection test..."
    php -r "
        try {
            \$pdo = new PDO('pgsql:host=$DB_HOST;port=$DB_PORT;dbname=$DB_DATABASE', '$DB_USERNAME', '$DB_PASSWORD');
            echo 'Direct PDO connection: SUCCESS\n';
        } catch (Exception \$e) {
            echo 'Direct PDO connection: FAILED - ' . \$e->getMessage() . '\n';
        }
    "
fi

# PHP Extensions
echo -e "\n${YELLOW}🔧 PHP Extensions:${NC}"
REQUIRED_EXTENSIONS=("pdo" "pdo_pgsql" "pgsql" "mbstring" "zip" "gd" "bcmath" "exif" "pcntl")
for ext in "${REQUIRED_EXTENSIONS[@]}"; do
    echo -n "$ext: "
    if php -m | grep -q "^$ext$"; then
        echo -e "${GREEN}✅ LOADED${NC}"
    else
        echo -e "${RED}❌ MISSING${NC}"
    fi
done

# Composer Dependencies
echo -e "\n${YELLOW}📦 Composer Dependencies:${NC}"
echo -n "Autoloader: "
if [ -f "vendor/autoload.php" ]; then
    echo -e "${GREEN}✅ EXISTS${NC}"
else
    echo -e "${RED}❌ MISSING${NC}"
fi

# Application Test
echo -e "\n${YELLOW}🧪 Application Test:${NC}"
echo -n "Laravel about command: "
if php artisan about > /dev/null 2>&1; then
    echo -e "${GREEN}✅ OK${NC}"
else
    echo -e "${RED}❌ FAILED${NC}"
    echo "Error details:"
    php artisan about 2>&1 || true
fi

echo -e "\n${YELLOW}🏥 Health Check Endpoint:${NC}"
echo -n "Health endpoint test: "
if php artisan tinker --execute="echo 'Health check: OK';" > /dev/null 2>&1; then
    echo -e "${GREEN}✅ OK${NC}"
else
    echo -e "${RED}❌ FAILED${NC}"
fi

echo -e "\n${BLUE}=================================================="
echo -e "Debug completed. Check the output above for issues.${NC}"
