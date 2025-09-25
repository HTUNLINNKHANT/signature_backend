#!/bin/bash

# Health check script for Docker/Render deployment
# Focuses on basic application health without database dependency

set -e

# Colors
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m'

echo -e "${YELLOW}🏥 Running health checks...${NC}"

# Check 1: Basic HTTP response (most important for Render)
echo -n "HTTP endpoint (/up): "
if curl -f -s "http://localhost:${PORT:-8000}/up" > /dev/null 2>&1; then
    echo -e "${GREEN}✅ OK${NC}"
    HTTP_STATUS="healthy"
else
    echo -e "${RED}❌ FAILED${NC}"
    HTTP_STATUS="unhealthy"
fi

# Check 2: Application response (Laravel artisan)
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

# Check 4: Database connectivity (optional - won't fail health check)
echo -n "Database connection: "
if php artisan migrate:status > /dev/null 2>&1; then
    echo -e "${GREEN}✅ OK${NC}"
    DB_STATUS="healthy"
else
    echo -e "${YELLOW}⚠️ WARNING${NC}"
    DB_STATUS="warning"
fi

# Overall status - prioritize HTTP and basic app functionality
echo ""
if [ "$HTTP_STATUS" = "healthy" ] && [ "$APP_STATUS" = "healthy" ] && [ "$STORAGE_STATUS" = "healthy" ]; then
    echo -e "${GREEN}🎉 Core health checks passed!${NC}"
    if [ "$DB_STATUS" = "warning" ]; then
        echo -e "${YELLOW}⚠️ Database connectivity issue detected but not blocking${NC}"
    fi
    exit 0
else
    echo -e "${RED}💥 Critical health checks failed!${NC}"
    exit 1
fi
