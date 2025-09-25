#!/bin/bash

# Debug script for 500 Server Error issues
echo "🔍 Debugging 500 Server Error..."

# Check if Laravel is properly installed
echo "=== Laravel Installation Check ==="
if [ -f "artisan" ]; then
    echo "✅ artisan file exists"
else
    echo "❌ artisan file missing"
fi

if [ -d "vendor" ]; then
    echo "✅ vendor directory exists"
else
    echo "❌ vendor directory missing"
fi

if [ -f "vendor/autoload.php" ]; then
    echo "✅ autoloader exists"
else
    echo "❌ autoloader missing"
fi

# Check environment variables
echo ""
echo "=== Environment Variables ==="
echo "APP_ENV: ${APP_ENV:-not set}"
echo "APP_DEBUG: ${APP_DEBUG:-not set}"
echo "APP_KEY: ${APP_KEY:+set (hidden)}${APP_KEY:-not set}"
echo "DB_CONNECTION: ${DB_CONNECTION:-not set}"
echo "DB_HOST: ${DB_HOST:-not set}"
echo "DB_DATABASE: ${DB_DATABASE:-not set}"

# Check storage permissions
echo ""
echo "=== Storage Permissions ==="
ls -la storage/ 2>/dev/null || echo "❌ storage directory not accessible"
ls -la bootstrap/cache/ 2>/dev/null || echo "❌ bootstrap/cache directory not accessible"

# Try basic Laravel commands
echo ""
echo "=== Laravel Commands Test ==="
echo -n "php artisan --version: "
if php artisan --version 2>/dev/null; then
    echo "✅ Laravel CLI working"
else
    echo "❌ Laravel CLI failed"
fi

echo -n "php artisan config:show app.key: "
if php artisan config:show app.key 2>/dev/null; then
    echo "✅ App key accessible"
else
    echo "❌ App key not accessible"
fi

# Check database connectivity
echo ""
echo "=== Database Connectivity ==="
echo -n "Database connection test: "
if php artisan migrate:status 2>/dev/null >/dev/null; then
    echo "✅ Database connected"
else
    echo "❌ Database connection failed"
    echo "Trying to show database error:"
    php artisan migrate:status 2>&1 | head -5
fi

# Check if server can start
echo ""
echo "=== Server Test ==="
echo "Testing if Laravel server can start..."
timeout 10s php artisan serve --host=0.0.0.0 --port=8001 &
SERVER_PID=$!
sleep 3

if curl -f -s "http://localhost:8001/" > /dev/null 2>&1; then
    echo "✅ Server responds to HTTP requests"
else
    echo "❌ Server not responding"
fi

if curl -f -s "http://localhost:8001/up" > /dev/null 2>&1; then
    echo "✅ Health endpoint (/up) working"
else
    echo "❌ Health endpoint (/up) not working"
fi

# Clean up
kill $SERVER_PID 2>/dev/null || true

echo ""
echo "=== Recent Laravel Logs ==="
if [ -f "storage/logs/laravel.log" ]; then
    echo "Last 10 lines of Laravel log:"
    tail -10 storage/logs/laravel.log
else
    echo "No Laravel log file found"
fi

echo ""
echo "🔍 Debug complete!"
