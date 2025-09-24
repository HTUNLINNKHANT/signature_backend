#!/bin/bash

# Health check script for monitoring database and application status
# Can be used by Docker, Kubernetes, or monitoring systems

set -e

# Colors
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m'

echo -e "${YELLOW}🏥 Running health checks...${NC}"

# Check 1: Database connectivity
echo -n "Database connection: "
if php artisan migrate:status > /dev/null 2>&1; then
    echo -e "${GREEN}✅ OK${NC}"
    DB_STATUS="healthy"
else
    echo -e "${RED}❌ FAILED${NC}"
    DB_STATUS="unhealthy"
fi

# Check 2: Application response
echo -n "Application response: "
if php artisan about > /dev/null 2>&1; then
    echo -e "${GREEN}✅ OK${NC}"
    APP_STATUS="healthy"
else
    echo -e "${RED}❌ FAILED${NC}"
    APP_STATUS="unhealthy"
fi

# Check 3: Storage permissions
echo -n "Storage permissions: "
if [ -w "storage/logs" ] && [ -w "bootstrap/cache" ]; then
    echo -e "${GREEN}✅ OK${NC}"
    STORAGE_STATUS="healthy"
else
    echo -e "${RED}❌ FAILED${NC}"
    STORAGE_STATUS="unhealthy"
fi

# Overall status
echo ""
if [ "$DB_STATUS" = "healthy" ] && [ "$APP_STATUS" = "healthy" ] && [ "$STORAGE_STATUS" = "healthy" ]; then
    echo -e "${GREEN}🎉 All health checks passed!${NC}"
    exit 0
else
    echo -e "${RED}💥 Some health checks failed!${NC}"
    exit 1
fi
