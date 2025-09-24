#!/bin/bash

# Enhanced database connection waiting script
# This script provides better feedback and multiple connection methods

set -e

# Configuration
MAX_RETRIES=${DB_WAIT_TIMEOUT:-60}
SLEEP_INTERVAL=${DB_WAIT_INTERVAL:-2}
RETRY_COUNT=0

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

echo -e "${BLUE}🔍 Checking database connection...${NC}"
echo "Configuration:"
echo "  - Host: ${DB_HOST:-localhost}"
echo "  - Port: ${DB_PORT:-5432}"
echo "  - Database: ${DB_DATABASE:-signature_ecommerce}"
echo "  - Username: ${DB_USERNAME:-signature_user}"
echo "  - Max retries: $MAX_RETRIES"
echo ""

# Function to test database connection using different methods
test_db_connection() {
    # Method 1: Try Laravel's migrate:status command
    if php artisan migrate:status > /dev/null 2>&1; then
        return 0
    fi
    
    # Method 2: Try direct PostgreSQL connection (if psql is available)
    if command -v psql > /dev/null 2>&1; then
        if PGPASSWORD="$DB_PASSWORD" psql -h "$DB_HOST" -p "$DB_PORT" -U "$DB_USERNAME" -d "$DB_DATABASE" -c "SELECT 1;" > /dev/null 2>&1; then
            return 0
        fi
    fi
    
    # Method 3: Try PHP PDO connection
    if php -r "
        try {
            \$pdo = new PDO('pgsql:host=$DB_HOST;port=$DB_PORT;dbname=$DB_DATABASE', '$DB_USERNAME', '$DB_PASSWORD');
            \$pdo->query('SELECT 1');
            exit(0);
        } catch (Exception \$e) {
            exit(1);
        }
    " > /dev/null 2>&1; then
        return 0
    fi
    
    return 1
}

# Main waiting loop
while ! test_db_connection; do
    RETRY_COUNT=$((RETRY_COUNT + 1))
    
    if [ $RETRY_COUNT -gt $MAX_RETRIES ]; then
        echo -e "${RED}❌ Database connection failed after $MAX_RETRIES attempts (${MAX_RETRIES}s timeout)${NC}"
        echo ""
        echo -e "${YELLOW}Troubleshooting steps:${NC}"
        echo "1. Check if database service is running"
        echo "2. Verify database credentials in environment variables"
        echo "3. Ensure database host is reachable"
        echo "4. Check if database exists and user has proper permissions"
        echo ""
        echo -e "${YELLOW}Current environment:${NC}"
        echo "  DB_HOST: ${DB_HOST:-'not set'}"
        echo "  DB_PORT: ${DB_PORT:-'not set'}"
        echo "  DB_DATABASE: ${DB_DATABASE:-'not set'}"
        echo "  DB_USERNAME: ${DB_USERNAME:-'not set'}"
        echo "  DB_PASSWORD: ${DB_PASSWORD:+'***set***'}"
        echo ""
        exit 1
    fi
    
    echo -e "${YELLOW}⏳ Waiting for database... (attempt $RETRY_COUNT/$MAX_RETRIES)${NC}"
    sleep $SLEEP_INTERVAL
done

echo -e "${GREEN}✅ Database connection established successfully!${NC}"
echo ""
