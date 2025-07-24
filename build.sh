#!/bin/bash

# Exit on any error
set -e

echo "🚀 Starting Akaunting build process for Railway..."

# Install PHP dependencies
echo "📦 Installing Composer dependencies..."
composer install --no-dev --optimize-autoloader --no-interaction

# Install Node.js dependencies and build assets
echo "🎨 Building frontend assets..."
npm ci --only=production
npm run production

# Cache Laravel configuration for better performance
echo "⚡ Optimizing Laravel..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Generate application key if not set
if [ -z "$APP_KEY" ]; then
    echo "🔑 Generating application key..."
    php artisan key:generate --force
fi

# Set proper permissions for storage
echo "🔐 Setting storage permissions..."
chmod -R 775 storage bootstrap/cache

echo "✅ Build completed successfully for Railway!"