#!/bin/bash

# Railway Deployment Verification Script
# Run this after deployment to verify everything is working

echo "🔍 Verifying Railway Deployment..."

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Function to print status
print_status() {
    if [ $1 -eq 0 ]; then
        echo -e "${GREEN}✅ $2${NC}"
    else
        echo -e "${RED}❌ $2${NC}"
    fi
}

print_warning() {
    echo -e "${YELLOW}⚠️  $1${NC}"
}

# Check if Railway environment variables are set
echo "📋 Checking Railway environment..."

if [[ -n "$RAILWAY_STATIC_URL" ]]; then
    print_status 0 "Railway URL detected: $RAILWAY_STATIC_URL"
    APP_URL="$RAILWAY_STATIC_URL"
elif [[ -n "$APP_URL" ]]; then
    print_status 0 "App URL set: $APP_URL"
else
    print_status 1 "No app URL found"
    APP_URL="http://localhost"
fi

# Check database connection
echo "🗄️  Testing database connection..."
if php artisan tinker --execute="DB::connection()->getPdo(); echo 'Connected';" > /dev/null 2>&1; then
    print_status 0 "Database connection successful"
else
    print_status 1 "Database connection failed"
fi

# Check Redis connection
echo "🔴 Testing Redis connection..."
if php artisan tinker --execute="Redis::ping(); echo 'Connected';" > /dev/null 2>&1; then
    print_status 0 "Redis connection successful"
else
    print_warning "Redis connection failed (optional)"
fi

# Check S3 connection
echo "☁️  Testing S3 connection..."
if [[ "$FILESYSTEM_DISK" == "s3" ]]; then
    if php artisan tinker --execute="Storage::disk('s3')->exists('test') || Storage::disk('s3')->put('test', 'test'); Storage::disk('s3')->delete('test'); echo 'Connected';" > /dev/null 2>&1; then
        print_status 0 "S3 connection successful"
    else
        print_status 1 "S3 connection failed"
    fi
else
    print_warning "S3 not configured - using local storage"
fi

# Check if modules are present
echo "📦 Checking modules..."
if [[ -d "modules/OfflinePayments" && -d "modules/PaypalStandard" ]]; then
    print_status 0 "Required modules present"
else
    print_status 1 "Required modules missing"
fi

# Check file permissions
echo "🔐 Checking file permissions..."
if [[ -w "storage" && -w "bootstrap/cache" ]]; then
    print_status 0 "File permissions correct"
else
    print_status 1 "File permissions incorrect"
fi

# Check if app is optimized
echo "⚡ Checking optimization..."
if [[ -f "bootstrap/cache/config.php" && -f "bootstrap/cache/routes-v7.php" ]]; then
    print_status 0 "Application optimized"
else
    print_warning "Application not fully optimized"
fi

# Test health endpoint
echo "🏥 Testing health endpoint..."
if [[ -n "$APP_URL" ]]; then
    HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" "$APP_URL/health" || echo "000")
    if [[ "$HTTP_CODE" == "200" ]]; then
        print_status 0 "Health endpoint responding"
    else
        print_status 1 "Health endpoint not responding (HTTP $HTTP_CODE)"
    fi
else
    print_warning "Cannot test health endpoint - no URL"
fi

# Test main application
echo "🌐 Testing main application..."
if [[ -n "$APP_URL" ]]; then
    HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" "$APP_URL" || echo "000")
    if [[ "$HTTP_CODE" == "200" ]]; then
        print_status 0 "Main application responding"
    else
        print_status 1 "Main application not responding (HTTP $HTTP_CODE)"
    fi
else
    print_warning "Cannot test main application - no URL"
fi

# Check queue system
echo "🔄 Checking queue system..."
if php artisan queue:work --once --quiet > /dev/null 2>&1; then
    print_status 0 "Queue system working"
else
    print_warning "Queue system not responding"
fi

# Check logs for errors
echo "📝 Checking for recent errors..."
if [[ -f "storage/logs/laravel.log" ]]; then
    ERROR_COUNT=$(tail -100 storage/logs/laravel.log | grep -i error | wc -l)
    if [[ $ERROR_COUNT -eq 0 ]]; then
        print_status 0 "No recent errors in logs"
    else
        print_warning "$ERROR_COUNT recent errors found in logs"
    fi
else
    print_warning "No log file found"
fi

echo ""
echo "🎯 Deployment Summary:"
echo "====================="

# Final recommendations
echo "📋 Next Steps:"
echo "1. Visit your application: $APP_URL"
echo "2. Log in with admin credentials (change default password!)"
echo "3. Configure your company settings"
echo "4. Set up payment methods"
echo "5. Test file uploads and receipt processing"
echo "6. Configure email settings"
echo ""

if [[ "$FILESYSTEM_DISK" != "s3" ]]; then
    echo "⚠️  IMPORTANT: Configure S3 for production file storage!"
fi

if [[ -z "$MAIL_HOST" ]]; then
    echo "⚠️  IMPORTANT: Configure email settings for notifications!"
fi

echo ""
echo "🚀 Your Akaunting SaaS is ready for Railway deployment!"
echo "📚 See RAILWAY_DEPLOYMENT.md for detailed setup instructions"