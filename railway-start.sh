#!/bin/bash

# Exit on any error
set -e

echo "🚀 Starting Akaunting on Railway..."

# Wait for database to be ready
echo "⏳ Waiting for database connection..."
php artisan tinker --execute="DB::connection()->getPdo();" || {
    echo "❌ Database connection failed. Retrying in 5 seconds..."
    sleep 5
    php artisan tinker --execute="DB::connection()->getPdo();"
}

echo "✅ Database connection established"

# Run Railway setup
echo "🔧 Running Railway setup..."
php artisan railway:setup

# Check if app is installed
if [ "$APP_INSTALLED" != "true" ]; then
    echo "🔧 Setting up Akaunting for first time..."
    
    # Generate app key if not set
    if [ -z "$APP_KEY" ]; then
        echo "🔑 Generating application key..."
        php artisan key:generate --force
    fi
    
    # Run installation
    echo "📦 Installing Akaunting..."
    php artisan install \
        --db-name="$DB_DATABASE" \
        --db-username="$DB_USERNAME" \
        --db-password="$DB_PASSWORD" \
        --db-host="$DB_HOST" \
        --db-port="$DB_PORT" \
        --admin-email="admin@akaunting.com" \
        --admin-password="123456" \
        --company-name="My Company" \
        --company-email="info@company.com" \
        --locale="en-US" \
        --no-interaction
        
    echo "✅ Akaunting installation completed"
else
    echo "🔄 Running database migrations..."
    php artisan migrate --force
fi

# Set proper permissions
echo "🔐 Setting file permissions..."
chmod -R 775 storage bootstrap/cache

# Start the application
echo "🌟 Starting Akaunting server on port $PORT..."
exec php artisan serve --host=0.0.0.0 --port=$PORT