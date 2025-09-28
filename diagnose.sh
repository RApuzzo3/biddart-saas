#!/bin/bash

echo "=========================================="
echo "LARAVEL APPLICATION DIAGNOSTIC SCRIPT"
echo "=========================================="
echo ""

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Function to print status
print_status() {
    if [ $1 -eq 0 ]; then
        echo -e "${GREEN}✓ $2${NC}"
    else
        echo -e "${RED}✗ $2${NC}"
    fi
}

echo "1. CHECKING BASIC SYSTEM..."
echo "=========================="

# Check if we're in the right directory
if [ -f "artisan" ]; then
    echo -e "${GREEN}✓ In Laravel project directory${NC}"
else
    echo -e "${RED}✗ Not in Laravel project directory${NC}"
    exit 1
fi

# Check PHP version
php -v > /dev/null 2>&1
print_status $? "PHP is installed and accessible"

# Check PHP-FPM status
if pgrep php-fpm > /dev/null 2>&1; then
    echo -e "${GREEN}✓ PHP-FPM is running${NC}"
else
    echo -e "${RED}✗ PHP-FPM is not running${NC}"
fi

echo ""
echo "2. CHECKING FILE PERMISSIONS..."
echo "==============================="

# Check storage permissions
if [ -w "storage" ] && [ -w "storage/logs" ] && [ -w "bootstrap/cache" ]; then
    echo -e "${GREEN}✓ Storage and cache directories are writable${NC}"
else
    echo -e "${RED}✗ Storage or cache directories not writable${NC}"
fi

# Check public directory
if [ -f "public/index.php" ]; then
    echo -e "${GREEN}✓ index.php exists in public directory${NC}"
else
    echo -e "${RED}✗ index.php missing from public directory${NC}"
fi

echo ""
echo "3. CHECKING PHP FUNCTIONALITY..."
echo "==============================="

# Test PHP execution
php -r "echo 'PHP execution test passed\n';" 2>/dev/null
print_status $? "Basic PHP execution"

# Test Laravel requirements
php -r "
\$extensions = ['pdo', 'mbstring', 'openssl', 'tokenizer', 'xml', 'ctype', 'json', 'bcmath'];
\$missing = [];
foreach (\$extensions as \$ext) {
    if (!extension_loaded(\$ext)) {
        \$missing[] = \$ext;
    }
}
if (empty(\$missing)) {
    echo \"✓ All required PHP extensions are loaded\n\";
} else {
    echo \"✗ Missing extensions: \" . implode(', ', \$missing) . \"\n\";
}
" 2>/dev/null

echo ""
echo "4. CHECKING LARAVEL CONFIGURATION..."
echo "==================================="

# Check .env file
if [ -f ".env" ]; then
    echo -e "${GREEN}✓ .env file exists${NC}"

    # Check APP_KEY
    if grep -q "APP_KEY=base64:" .env; then
        echo -e "${GREEN}✓ APP_KEY is set${NC}"
    else
        echo -e "${RED}✗ APP_KEY is not set or invalid${NC}"
    fi

    # Check database configuration
    if grep -q "DB_CONNECTION=mysql" .env && grep -q "DB_HOST=" .env && grep -q "DB_DATABASE=" .env; then
        echo -e "${GREEN}✓ Database configuration found${NC}"
    else
        echo -e "${RED}✗ Database configuration incomplete${NC}"
    fi
else
    echo -e "${RED}✗ .env file missing${NC}"
fi

# Test artisan command
php artisan --version > /dev/null 2>&1
print_status $? "Laravel artisan command works"

echo ""
echo "5. CHECKING DATABASE CONNECTIVITY..."
echo "==================================="

# Test database connection
php artisan tinker --execute="try { DB::connection()->getPdo(); echo 'Database connection successful'; } catch (Exception \$e) { echo 'Database connection failed: ' . \$e->getMessage(); }" 2>/dev/null
if [ $? -eq 0 ]; then
    echo -e "${GREEN}✓ Database connection successful${NC}"
else
    echo -e "${RED}✗ Database connection failed${NC}"
fi

echo ""
echo "6. CHECKING WEB SERVER RESPONSE..."
echo "================================="

# Test local file access
if curl -s "http://localhost/index.php" | head -1 | grep -q "Laravel"; then
    echo -e "${GREEN}✓ Local Laravel response detected${NC}"
else
    echo -e "${YELLOW}? Local response unclear${NC}"
fi

echo ""
echo "7. CHECKING COMPOSER AND DEPENDENCIES..."
echo "======================================="

# Check composer
composer --version > /dev/null 2>&1
print_status $? "Composer is available"

# Check vendor directory
if [ -d "vendor" ] && [ -f "vendor/autoload.php" ]; then
    echo -e "${GREEN}✓ Composer dependencies installed${NC}"
else
    echo -e "${RED}✗ Composer dependencies missing${NC}"
fi

echo ""
echo "8. CHECKING NODE.JS ASSETS..."
echo "============================"

# Check if build assets exist
if [ -d "public/build" ] && [ -f "public/build/manifest.json" ]; then
    echo -e "${GREEN}✓ Frontend assets are built${NC}"
else
    echo -e "${YELLOW}? Frontend assets may not be built${NC}"
fi

echo ""
echo "=========================================="
echo "DIAGNOSTIC COMPLETE"
echo "=========================================="
echo ""
echo "If you see any ✗ or ? marks above, those indicate"
echo "potential issues that need to be addressed."
echo ""
echo "Common fixes:"
echo "- Run: php artisan config:clear"
echo "- Run: php artisan cache:clear"
echo "- Run: composer dump-autoload"
echo "- Check file permissions: chmod -R 755 storage bootstrap/cache"
echo "- Verify .env file has correct database credentials"
echo ""
echo "For nginx configuration issues, contact Cloudways support"
echo "to ensure the document root points to: public_html/public/"
